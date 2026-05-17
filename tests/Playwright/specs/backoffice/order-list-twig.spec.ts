import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';

test.describe('Back-office — order list (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('orders list loads with status filter and search', async ({ page }) => {
    await page.goto('/admin/orders');
    await expect(page.getByTestId('orders-page')).toBeVisible();
    await expect(page.getByTestId('order-search-form')).toBeVisible();
    await expect(page.getByTestId('order-filter-status')).toBeVisible();
  });

  test('order status configuration loads', async ({ page }) => {
    await page.goto('/admin/configuration/order-status');
    await expect(page.getByTestId('order-statuses-page')).toBeVisible();
    await expect(page.getByTestId('order-status-create-button')).toBeVisible();
  });
});
