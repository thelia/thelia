import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';

test.describe('Back-office — brand list (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('brand list page loads with DataTable and create button', async ({ page }) => {
    await page.goto('/admin/brand');
    await expect(page.getByTestId('brands-page')).toBeVisible();
    await expect(page.getByTestId('brand-create-button')).toBeVisible();
  });

  test('clicking create brand opens modale with form', async ({ page }) => {
    await page.goto('/admin/brand');
    await page.getByTestId('brand-create-button').click();
    await expect(page.getByTestId('brand-create-form')).toBeVisible();
    await expect(page.getByTestId('brand-create-submit')).toBeVisible();
  });

  test('brand delete modale is attached on page (CSRF-protected form)', async ({ page }) => {
    await page.goto('/admin/brand');
    await expect(page.getByTestId('brand-delete-form')).toBeAttached();
  });
});
