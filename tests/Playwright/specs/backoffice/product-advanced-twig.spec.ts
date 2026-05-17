import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';

test.describe('Back-office — product advanced endpoints (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('related tab fragment loads (iso compatibility)', async ({ page }) => {
    await page.goto('/admin/products/related/tab?product_id=1');
    await expect(page.getByTestId('product-related-tab')).toBeVisible();
  });

  test('attributes tab fragment loads (iso compatibility)', async ({ page }) => {
    await page.goto('/admin/products/attributes/tab?product_id=1');
    await expect(page.getByTestId('product-attributes-tab')).toBeVisible();
  });

  test('calculate price endpoint returns JSON', async ({ page }) => {
    const response = await page.request.get('/admin/product/calculate-price?price=100&tax_rule_id=1');
    expect(response.ok()).toBeTruthy();
    const body = await response.json();
    expect(body).toHaveProperty('price');
  });

  test('categories autocomplete returns JSON', async ({ page }) => {
    const response = await page.request.get('/admin/products/related/tab/categories/search?q=test');
    expect(response.ok()).toBeTruthy();
    const body = await response.json();
    expect(Array.isArray(body)).toBeTruthy();
  });

  test('attribute values endpoint returns JSON list', async ({ page }) => {
    const response = await page.request.get('/admin/product/1/attribute-values/1.json');
    expect(response.ok()).toBeTruthy();
    const body = await response.json();
    expect(Array.isArray(body)).toBeTruthy();
  });
});
