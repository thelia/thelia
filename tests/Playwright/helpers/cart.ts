import { expect, type Page } from '@playwright/test';

// Taken as the form declares it rather than by a css class: the theme's Button component
// renders its filled variant as `button--fill` (html_cva), and the quantity stepper around
// it renders `type="button"`, so the submit is the only submit inside the form.
const ADD_TO_CART_BUTTON = 'form[name="thelia_cart_add"] button[type="submit"]';
const CART_LIVE_ROOT = '[data-live-name-value="Organisms:Cart:Base"]';
const QUANTITY_INPUT = 'form[name="thelia_cart_add"] input[name="thelia_cart_add[quantity]"]';

export async function gotoProduct(page: Page, slug: string): Promise<void> {
  await page.goto(`/${slug}.html`);
  await expect(page.locator('form[name="thelia_cart_add"]')).toBeAttached();
}

export async function setQuantity(page: Page, qty: number): Promise<void> {
  const input = page.locator(QUANTITY_INPUT);
  await input.fill(String(qty));
  await input.dispatchEvent('change');
}

export async function addCurrentProductToCart(page: Page): Promise<void> {
  const cartCounter = page.locator('a[href="/checkout/cart"]').first();
  const before = (await cartCounter.textContent())?.trim() ?? '';
  await page.locator(ADD_TO_CART_BUTTON).first().click();
  // Toast appears or live cart updates — wait for it.
  await page.waitForTimeout(500);
  await page.waitForLoadState('networkidle').catch(() => {});
  await expect(cartCounter).not.toHaveText(before, { timeout: 5_000 }).catch(() => {});
}

export async function selectAttributePse(page: Page, attributeId: number, valueId: number): Promise<void> {
  const button = page.locator(
    `button[data-live-action-param="updateCurrentCombination"][data-live-attr-param="${attributeId}"][data-live-value-param="${valueId}"]`,
  );
  await button.first().click();
  await page.waitForLoadState('networkidle').catch(() => {});
}

export async function gotoCart(page: Page): Promise<void> {
  await page.goto('/checkout/cart');
  await expect(page.locator(CART_LIVE_ROOT)).toBeVisible();
}

export async function expectCartItemCount(page: Page, expected: number): Promise<void> {
  const items = page.locator(`${CART_LIVE_ROOT} .CartItem`);
  await expect(items).toHaveCount(expected, { timeout: 10_000 });
}

export function cartItem(page: Page, index = 0) {
  return page.locator(`${CART_LIVE_ROOT} .CartItem`).nth(index);
}

export async function changeCartItemQuantity(page: Page, index: number, action: 'plus' | 'minus'): Promise<void> {
  const item = cartItem(page, index);
  await item.locator(`button[data-live-action-param="${action}"]`).click();
  await page.waitForLoadState('networkidle').catch(() => {});
}

export async function removeCartItem(page: Page, index: number): Promise<void> {
  const item = cartItem(page, index);
  await item.locator('button:has-text("Delete")').click();
  // Confirm deletion modal — `CartItemDelete.html.twig` is rendered inline with a confirm button.
  const confirm = page.locator('[data-live-action-param="confirmDelete"], button:has-text("Confirm")').first();
  if (await confirm.count() > 0) {
    await confirm.click();
  }
  await page.waitForLoadState('networkidle').catch(() => {});
}

export async function expectCartEmpty(page: Page): Promise<void> {
  await expect(page.locator(CART_LIVE_ROOT)).toContainText(/Your cart is empty/i);
}
