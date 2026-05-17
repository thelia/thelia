import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { OrderListPage } from '../../poms/backoffice/order-list-page';

test.describe('Back-office — order list', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default',
    'Legacy Smarty markup; not applicable under default-twig.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  test('order list page loads', async ({ page }) => {
    const orderList = new OrderListPage(page);
    await orderList.goto();
    await orderList.expectLoaded();
  });

  test('orders table is rendered (rows optional — demo may be empty)', async ({ page }) => {
    const orderList = new OrderListPage(page);
    await orderList.goto();
    // The demo dataset doesn't create orders by default. We only assert the
    // table structure exists; row count is informational.
    await expect(orderList.readyLocator).toBeVisible();
    test.info().annotations.push({ type: 'order-rows', description: String(await orderList.rows.count()) });
  });
});
