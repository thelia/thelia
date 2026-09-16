import { expect, test } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';

/**
 * The back-office states its direction the same way the front does, from the
 * language of the request. Only the attribute is checked here: the layout of the
 * admin is not part of the right-to-left journey.
 */
test.describe('Back-office — right-to-left', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only — the legacy Smarty base template states no direction.',
  );

  test('the login page is served dir="rtl" in Arabic and dir="ltr" in French', async ({ page }) => {
    await page.goto('/admin/login?lang=ar', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('html')).toHaveAttribute('lang', /^ar/);

    await page.goto('/admin/login?lang=fr', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  });

  test('an authenticated admin page is served dir="rtl" in Arabic', async ({ page }) => {
    await loginAdmin(page);

    await page.goto('/admin/home?lang=ar');
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
    await expect(page.locator('html')).toHaveAttribute('lang', /^ar/);

    await page.goto('/admin/home?lang=fr');
    await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
  });
});
