import { test, expect } from '@playwright/test';
import { LoginPage } from '../../poms/backoffice/login-page';
import { DEFAULT_ADMIN } from '../../helpers/admin';

test.describe('Back-office — login', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default',
    'Legacy Smarty markup; not applicable under default-twig.',
  );

  test('successful login redirects to /admin/home', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();
    await loginPage.expectLoaded();

    await loginPage.login(DEFAULT_ADMIN);

    await expect(page).toHaveURL(/\/admin(\/home)?$/);
  });

  test('rejects empty credentials', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();
    await loginPage.submitButton.click();

    // Thelia re-renders the login template at /admin/checklogin on failure
    // (no redirect back to /admin/login), so accept either URL.
    await expect(page).toHaveURL(/\/admin\/(login|checklogin)/);
    await expect(loginPage.readyLocator).toBeVisible();
  });

  test('rejects bad credentials', async ({ page }) => {
    const loginPage = new LoginPage(page);
    await loginPage.goto();
    await loginPage.usernameInput.fill('totally-not-a-user');
    await loginPage.passwordInput.fill('wrong-password');
    await loginPage.submitButton.click();

    // On failure, the form template is rendered at /admin/checklogin
    // with the data-testid="login-error" alert.
    await expect(loginPage.errorMessage).toBeVisible();
    await expect(page).toHaveURL(/\/admin\/(login|checklogin)/);
  });
});
