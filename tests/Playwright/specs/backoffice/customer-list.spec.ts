import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { CustomerListPage } from '../../poms/backoffice/customer-list-page';

test.describe('Back-office — customer list', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default',
    'Legacy Smarty markup; BO Twig has its own customer-list-twig.spec.ts.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  test('customer list page loads', async ({ page }) => {
    const customerList = new CustomerListPage(page);
    await customerList.goto();
    await customerList.expectLoaded();
  });

  test('demo dataset contains at least one customer row', async ({ page }) => {
    const customerList = new CustomerListPage(page);
    await customerList.goto();
    await expect(customerList.rows.first()).toBeVisible({ timeout: 10_000 });
    const count = await customerList.rows.count();
    expect(count).toBeGreaterThan(0);
  });
});
