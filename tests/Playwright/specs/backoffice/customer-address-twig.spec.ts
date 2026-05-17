import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { CustomerEditPage } from '../../poms/backoffice/customer-edit-page';

test.describe('Back-office — customer addresses (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('customer edit page exposes the addresses section', async ({ page }) => {
    const editPage = new CustomerEditPage(page, 1);
    await editPage.goto();

    const section = page.getByTestId('customer-addresses-section');
    await expect(section).toBeVisible();
    await expect(page.getByTestId('customer-addresses-table')).toBeVisible();
    await expect(page.getByTestId('customer-address-row').first()).toBeVisible();
  });

  test('address edit page loads from customer edit', async ({ page }) => {
    await page.goto('/admin/address/update?address_id=1');
    await expect(page.getByTestId('address-edit-page')).toBeVisible();
    await expect(page.getByTestId('address-edit-form')).toBeVisible();
    await expect(page.getByTestId('address-edit-submit')).toBeVisible();
  });

  test('unknown address id redirects to customers list', async ({ page }) => {
    await page.goto('/admin/address/update?address_id=99999');
    await expect(page).toHaveURL(/\/admin\/customers$/);
  });
});
