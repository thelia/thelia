import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';

test.describe('Back-office — category list (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('category root list loads with create button', async ({ page }) => {
    await page.goto('/admin/categories');
    await expect(page.getByTestId('categories-page')).toBeVisible();
    await expect(page.getByTestId('category-create-button')).toBeVisible();
  });

  test('demo dataset contains category rows at root', async ({ page }) => {
    await page.goto('/admin/categories');
    const rows = page.locator('tbody[data-controller="bo-sortable"] tr');
    expect(await rows.count()).toBeGreaterThan(0);
  });

  test('clicking create category opens modale with form', async ({ page }) => {
    await page.goto('/admin/categories');
    await page.getByTestId('category-create-button').click();
    await expect(page.getByTestId('category-create-form')).toBeVisible();
    await expect(page.getByTestId('category-create-submit')).toBeVisible();
  });

  test('category edit page loads with form and SEO tab', async ({ page }) => {
    await page.goto('/admin/categories/update?category_id=2');
    await expect(page.getByTestId('category-edit-page')).toBeVisible();
    await expect(page.getByTestId('category-edit-form')).toBeVisible();
    await expect(page.getByTestId('category-tab-seo')).toBeVisible();
  });
});
