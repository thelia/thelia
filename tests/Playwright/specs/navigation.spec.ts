import { expect, test } from '@playwright/test';

test.describe('Navigation', () => {
  test('home renders the menu and product highlights', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('header')).toBeVisible();
    // Home shows at least one product card link to the product page (rewritten URL).
    // Scoped to the product cards on purpose: the first `a[href$=".html"]` of the document
    // belongs to the header mega-menu, which is a dropdown and is legitimately collapsed
    // until its top-level item is opened.
    await expect(page.locator('a.ProductCard-title[href$=".html"]').first()).toBeVisible();
  });

  test('navigate from home to a category page', async ({ page }) => {
    await page.goto('/');
    // The header menu is a two-level dropdown: the top level ("Living Room", "Dining Room")
    // is a <button> that reveals its submenu, and the categories are the links inside it.
    // "Chairs" still exists in the demo dataset, under "Dining Room".
    await page.getByRole('button', { name: 'Dining Room' }).first().click();
    const categoryLink = page.getByRole('link', { name: 'Chairs', exact: true }).first();
    await expect(categoryLink).toBeVisible();
    await categoryLink.click();
    await expect(page).toHaveURL(/\/chairs\.html/);
  });

  test('navigate from home to a product page (slug-rewriting)', async ({ page }) => {
    await page.goto('/');
    const productLink = page.locator('a[href$="/horatio.html"]').first();
    await productLink.click();
    await expect(page).toHaveURL(/horatio\.html/);
    await expect(page.locator('form[name="thelia_cart_add"]')).toBeAttached();
  });

  test('renders a 404 for a missing slug', async ({ page }) => {
    const response = await page.goto('/does-not-exist-please.html');
    expect(response?.status()).toBe(404);
  });

  test('language switch keeps you on the same page', async ({ page }) => {
    await page.goto('/');
    await page.goto('/?lang=fr_FR');
    await expect(page).toHaveURL(/lang=fr_FR/);
    await expect(page.locator('html')).toHaveAttribute('lang', /fr/);
  });

  test('contact page is reachable with a working form', async ({ page }) => {
    await page.goto('/contact');
    // Named rather than taken by position: the first <form> of the document is the header
    // search, which sits in a panel that stays collapsed until the magnifier is clicked.
    await expect(page.locator('form[name="thelia_contact"]')).toBeVisible();
  });

  test('product page boots the LiveComponent without crashing', async ({ page }) => {
    // Smoke test the LiveComponent serializer — historically a brittle area.
    const response = await page.goto('/horatio.html');
    expect(response?.status()).toBe(200);
    // The product page's live component is `Layouts:ProductDetails:Base` since the theme was
    // reorganised into Atoms/Molecules/Organisms/Layouts.
    await expect(page.locator('[data-live-name-value="Layouts:ProductDetails:Base"]')).toBeAttached();
  });
});
