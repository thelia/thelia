import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { CustomerEditPage } from '../../poms/backoffice/customer-edit-page';

test.describe('Back-office — customer edit (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('customer edit page loads with form and existing data', async ({ page }) => {
    const editPage = new CustomerEditPage(page, 1);
    await editPage.goto();
    await editPage.expectLoaded();

    await expect(editPage.form).toBeVisible();
    await expect(editPage.titleHeading).toBeVisible();
    await expect(editPage.submitButton).toBeVisible();
    await expect(editPage.firstNameInput()).not.toHaveValue('');
    await expect(editPage.emailInput()).not.toHaveValue('');
  });

  test('unknown customer id redirects to customers list', async ({ page }) => {
    await page.goto('/admin/customer/update?customer_id=99999');
    await expect(page).toHaveURL(/\/admin\/customers$/);
  });
});
