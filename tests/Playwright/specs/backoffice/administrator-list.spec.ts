import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { AdministratorListPage } from '../../poms/backoffice/administrator-list-page';

test.describe('Back-office — administrators list (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('administrators list page loads', async ({ page }) => {
    const list = new AdministratorListPage(page);
    await list.goto();
    await list.expectLoaded();
  });

  test('demo dataset shows at least one administrator row', async ({ page }) => {
    const list = new AdministratorListPage(page);
    await list.goto();
    await expect(list.rows.first()).toBeVisible({ timeout: 10_000 });
    expect(await list.rows.count()).toBeGreaterThan(0);
  });

  test('create button opens the create modal', async ({ page }) => {
    const list = new AdministratorListPage(page);
    await list.goto();
    await list.createButton.click();
    await expect(list.createForm).toBeVisible();
  });
});
