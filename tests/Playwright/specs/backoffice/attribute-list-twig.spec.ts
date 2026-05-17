import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';

test.describe('Back-office — attribute list (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('attribute list page loads with DataTable and create button', async ({ page }) => {
    await page.goto('/admin/configuration/attributes');
    await expect(page.getByTestId('attributes-page')).toBeVisible();
    await expect(page.getByTestId('attribute-create-button')).toBeVisible();
  });

  test('clicking create attribute opens modale with form', async ({ page }) => {
    await page.goto('/admin/configuration/attributes');
    await page.getByTestId('attribute-create-button').click();
    await expect(page.getByTestId('attribute-create-form')).toBeVisible();
    await expect(page.getByTestId('attribute-create-submit')).toBeVisible();
  });

  test('attribute edit page loads with form and values section', async ({ page }) => {
    await page.goto('/admin/configuration/attributes/update?attribute_id=1');
    await expect(page.getByTestId('attribute-edit-page')).toBeVisible();
    await expect(page.getByTestId('attribute-edit-form')).toBeVisible();
    await expect(page.getByTestId('attributeav-create-button')).toBeVisible();
  });
});
