import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { ProfileListPage } from '../../poms/backoffice/profile-list-page';

test.describe('Back-office — profiles list (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('profile list page loads', async ({ page }) => {
    const list = new ProfileListPage(page);
    await list.goto();
    await list.expectLoaded();
  });

  test('create button opens the create modal', async ({ page }) => {
    const list = new ProfileListPage(page);
    await list.goto();
    await list.createButton.click();
    await expect(list.createForm).toBeVisible();
  });

  test('datatable renders (empty or populated depending on demo dataset)', async ({ page }) => {
    const list = new ProfileListPage(page);
    await list.goto();
    await list.expectLoaded();
  });

  test('creating a profile then opening its edit page renders 3 tabs', async ({ page }) => {
    const list = new ProfileListPage(page);
    await list.goto();
    await list.createButton.click();
    await page.getByLabel(/profile code/i).fill('test-profile-tabs');
    await page.getByLabel(/title/i).first().fill('Test Profile (tabs)');
    await page.getByTestId('profile-create-form').locator('button[type="submit"]').click();
    await list.expectLoaded();

    const editAction = list.rows.first().locator('[data-testid="datatable-action-edit"]');
    await editAction.click();
    await expect(page.getByTestId('profile-tab-general')).toBeVisible();
    await expect(page.getByTestId('profile-tab-resources')).toBeVisible();
    await expect(page.getByTestId('profile-tab-modules')).toBeVisible();
  });
});
