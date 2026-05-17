import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';

test.describe('Back-office — folders (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('folders root list loads with create button', async ({ page }) => {
    await page.goto('/admin/folders');
    await expect(page.getByTestId('folders-page')).toBeVisible();
    await expect(page.getByTestId('folder-create-button')).toBeVisible();
  });

  test('clicking create folder opens modale with form', async ({ page }) => {
    await page.goto('/admin/folders');
    await page.getByTestId('folder-create-button').click();
    await expect(page.getByTestId('folder-create-form')).toBeVisible();
    await expect(page.getByTestId('folder-create-submit')).toBeVisible();
  });
});
