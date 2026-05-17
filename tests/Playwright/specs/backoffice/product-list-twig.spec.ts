import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';

test.describe('Back-office — product list (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('product list page loads with DataTable, search and create button', async ({ page }) => {
    await page.goto('/admin/products');
    await expect(page.getByTestId('products-page')).toBeVisible();
    await expect(page.getByTestId('product-create-button')).toBeVisible();
    await expect(page.getByTestId('product-search-form')).toBeVisible();
  });

  test('demo dataset contains at least one product row', async ({ page }) => {
    await page.goto('/admin/products');
    const rows = page.getByTestId('datatable-products-row');
    expect(await rows.count()).toBeGreaterThan(0);
  });

  test('search filters the product list', async ({ page }) => {
    await page.goto('/admin/products');
    await page.getByTestId('product-search-input').fill('zzz-non-existent');
    await page.getByTestId('product-search-submit').click();
    await expect(page).toHaveURL(/q=zzz/);
    const rows = page.getByTestId('datatable-products-row');
    expect(await rows.count()).toBe(0);
    await expect(page.getByTestId('datatable-products-empty')).toBeVisible();
  });

  test('clicking create product opens modale with form', async ({ page }) => {
    await page.goto('/admin/products');
    await page.getByTestId('product-create-button').click();
    await expect(page.getByTestId('product-create-form')).toBeVisible();
    await expect(page.getByTestId('product-create-submit')).toBeVisible();
  });

  test('product edit page loads with general form and SEO tab', async ({ page }) => {
    await page.goto('/admin/products/update?product_id=1');
    await expect(page.getByTestId('product-edit-page')).toBeVisible();
    await expect(page.getByTestId('product-edit-form')).toBeVisible();
    await expect(page.getByTestId('product-tab-seo')).toBeVisible();
  });
});
