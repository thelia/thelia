import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { DashboardPage } from '../../poms/backoffice/dashboard-page';

test.describe('Back-office — dashboard', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default',
    'Legacy Smarty markup; not applicable under default-twig.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  test('dashboard loads after login', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();
    await dashboard.expectLoaded();
  });

  test('main side nav is visible', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();
    await expect(dashboard.mainNav).toBeVisible();
    await expect(dashboard.navLink('home')).toBeVisible();
  });

  test('home blocks render (at least one)', async ({ page }) => {
    const dashboard = new DashboardPage(page);
    await dashboard.goto();
    // home.block is a Smarty hookblock with at least one fragment provided
    // by the HookAdminHome module shipped with the demo dataset.
    await expect(dashboard.homeBlocks.first()).toBeVisible({ timeout: 10_000 });
  });
});
