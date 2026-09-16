import { expect, test } from '@playwright/test';
import { login, logout, newCustomer, registerCustomer } from '../helpers/customer';

test.describe('Auth', () => {
  // A shop that does not confirm email addresses signs the new customer in at the end of
  // the registration instead of sending them to a sign-in form they have just filled in
  // (CustomerController::registerCreate, guarded by `$customer->getEnable()`). An account
  // still waiting for its activation code stays signed out — that branch is not exercised
  // here, the demo shop enables its customers on creation.
  test('register a new customer and end up signed in on the account page', async ({ page }) => {
    const customer = newCustomer();
    await registerCustomer(page, customer);
    await expect(page).toHaveURL(/\/account/);

    // The URL alone would also be reached by a redirect from an anonymous /account; the
    // account page only renders the profile form for a customer who is really signed in.
    await expect(page.locator('#flexybundle_form_customer_update_form_email')).toHaveValue(customer.email);
  });

  test('register then login then logout', async ({ page }) => {
    const customer = newCustomer();
    await registerCustomer(page, customer);

    // Registration leaves a session open — drop it so the sign-in below is a real one.
    await logout(page);
    await login(page, customer.email, customer.password);
    await expect(page).toHaveURL(/\/account/);

    await logout(page);
    // After logout, /account should redirect to login.
    await page.goto('/account');
    await expect(page).toHaveURL(/\/customer\/login/);
  });

  test('login fails on bad password', async ({ page }) => {
    const customer = newCustomer();
    await registerCustomer(page, customer);
    await logout(page);

    await page.goto('/customer/login');
    await page.fill('#thelia_customer_login_email', customer.email);
    await page.fill('#thelia_customer_login_password', 'totally-wrong-password');
    await page.locator('form[name="thelia_customer_login"] button[type="submit"]').click();

    // Stays on login page with an error.
    await expect(page).toHaveURL(/\/customer\/login/);
  });

  test('register rejects an existing email', async ({ page }) => {
    const customer = newCustomer();
    await registerCustomer(page, customer);
    // The registration form is only served to a signed-out visitor: registering now leaves
    // a session open, and /customer/register would redirect instead of rendering the form.
    await logout(page);

    // Try to register again with the same email.
    await page.goto('/customer/register');
    await page.fill('#flexybundle_form_customer_register_form_firstname', 'Other');
    await page.fill('#flexybundle_form_customer_register_form_lastname', 'Person');
    await page.fill('#flexybundle_form_customer_register_form_email', customer.email);
    await page.fill('#flexybundle_form_customer_register_form_password', 'AnotherPassword!9');
    await page.locator('form[name="flexybundle_form_customer_register_form"] button[type="submit"]').click();

    // Server should keep us on the register page (or send us back) with an error.
    await expect(page).toHaveURL(/\/customer\/register/);
  });
});
