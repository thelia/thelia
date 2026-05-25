import { expect, test } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';

/**
 * Covers the "Edit in <lang>" language switcher that lets the back-office
 * edit i18n fields of an entity in another language. Loads the brand edit
 * page (pilot wiring) and walks across the matrix of switcher behaviours.
 *
 * BO Twig only — the legacy Smarty back-office ships its own toolbar switcher.
 */
test.describe('Back-office — language switcher (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  test('renders flag buttons on a brand edit page', async ({ page }) => {
    await page.goto('/admin/brand/update/1');

    await expect(page.getByTestId('bo-language-switcher')).toBeVisible();
    await expect(page.getByTestId('bo-language-switcher-fr')).toBeVisible();
    await expect(page.getByTestId('bo-language-switcher-en')).toBeVisible();
  });

  test('marks the requested language active and keeps the others inactive', async ({ page }) => {
    await page.goto('/admin/brand/update/1?edit_language_id=2');

    await expect(page.getByTestId('bo-language-switcher-en')).toHaveClass(/btn-primary/);
    await expect(page.getByTestId('bo-language-switcher-fr')).toHaveClass(/btn-outline-secondary/);

    // The hidden locale field that drives the i18n save path must track the chosen language.
    await expect(page.locator('#thelia_brand_modification_locale')).toHaveValue('en_US');

    // Swap to French and check the active state flips back.
    await page.goto('/admin/brand/update/1?edit_language_id=1');
    await expect(page.getByTestId('bo-language-switcher-fr')).toHaveClass(/btn-primary/);
    await expect(page.getByTestId('bo-language-switcher-en')).toHaveClass(/btn-outline-secondary/);
    await expect(page.locator('#thelia_brand_modification_locale')).toHaveValue('fr_FR');
  });

  test('preserves current_tab when navigating to another language', async ({ page }) => {
    await page.goto('/admin/brand/update/1?current_tab=seo');

    const enFlag = page.getByTestId('bo-language-switcher-en');
    const href = await enFlag.getAttribute('href');
    expect(href).toContain('edit_language_id=2');
    expect(href).toContain('current_tab=seo');
  });

  test('shows up on the other translatable edit views (smoke)', async ({ page }) => {
    // Sample one entity per lot to confirm the wiring follows the pilot pattern.
    const views = [
      '/admin/categories/update?category_id=1',
      '/admin/products/update?product_id=1',
      '/admin/configuration/customer-titles/update/1',
      '/admin/configuration/order-status/update/1',
      '/admin/sale/update/1',
    ];

    for (const url of views) {
      await page.goto(url);
      await expect(page.getByTestId('bo-language-switcher'), `switcher should be visible on ${url}`).toBeVisible();
    }
  });
});
