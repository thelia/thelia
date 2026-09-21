import { expect, test } from '@playwright/test';
import { newCustomer, registerCustomer, login, logout } from '../helpers/customer';
import { gotoProduct, addCurrentProductToCart, gotoCart, expectCartItemCount } from '../helpers/cart';

test.describe('Smoke', () => {
  test('home is reachable', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/Thelia/i);
  });

  test('product page renders an add-to-cart form', async ({ page }) => {
    await gotoProduct(page, 'horatio');
    await expect(page.locator('form[name="thelia_cart_add"] button[type="submit"]').first()).toBeVisible();
  });

  test('full register + login + add-to-cart + logout', async ({ page }) => {
    const customer = newCustomer();

    // Registering signs the customer in on a shop that does not confirm email addresses,
    // so the journey starts already authenticated (CustomerController::registerCreate).
    await registerCustomer(page, customer);
    await expect(page).toHaveURL(/\/account/);

    // Sign out and back in with the credentials just created: the point of this smoke is
    // that the password persisted, not that the registration redirect landed somewhere.
    await logout(page);
    await login(page, customer.email, customer.password);
    await expect(page).toHaveURL(/\/account/);

    await gotoProduct(page, 'horatio');
    await addCurrentProductToCart(page);
    await gotoCart(page);
    await expectCartItemCount(page, 1);

    await logout(page);
  });
});
