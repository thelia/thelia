import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';

test.describe('Back-office — configuration index (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('configuration index page loads with links to system sub-pages', async ({ page }) => {
    await page.goto('/admin/configuration');
    await expect(page.getByTestId('configuration-page')).toBeVisible();
    await expect(page.getByTestId('configuration-link-admin.configuration.languages.default')).toBeVisible();
    await expect(page.getByTestId('configuration-link-admin.configuration.currencies.default')).toBeVisible();
    await expect(page.getByTestId('configuration-link-admin.configuration.profiles.list')).toBeVisible();
    await expect(page.getByTestId('configuration-link-admin.configuration.administrators.view')).toBeVisible();
  });

  test('advanced configuration page exposes 3 flush actions', async ({ page }) => {
    await page.goto('/admin/configuration/advanced');
    await expect(page.getByTestId('advanced-config-page')).toBeVisible();
    await expect(page.getByTestId('advanced-flush-cache')).toBeVisible();
    await expect(page.getByTestId('advanced-flush-assets')).toBeVisible();
    await expect(page.getByTestId('advanced-flush-images')).toBeVisible();
  });
});
