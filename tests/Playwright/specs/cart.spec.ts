import { expect, test } from '@playwright/test';
import {
  addCurrentProductToCart,
  cartItem,
  changeCartItemQuantity,
  expectCartItemCount,
  gotoCart,
  gotoProduct,
  selectAttributePse,
  setQuantity,
} from '../helpers/cart';

test.describe('Cart', () => {
  test('add a product to an empty cart', async ({ page }) => {
    await gotoProduct(page, 'horatio');
    await addCurrentProductToCart(page);
    await gotoCart(page);
    await expectCartItemCount(page, 1);
  });

  test('add the same product twice keeps a single line and increments quantity', async ({ page }) => {
    await gotoProduct(page, 'horatio');
    await addCurrentProductToCart(page);
    await gotoProduct(page, 'horatio');
    await addCurrentProductToCart(page);
    await gotoCart(page);
    await expectCartItemCount(page, 1);
    await expect(cartItem(page, 0).locator('.CartItem-quantity input[type="number"]')).toHaveValue('2');
  });

  test('add two different products', async ({ page }) => {
    await gotoProduct(page, 'horatio');
    await addCurrentProductToCart(page);
    await gotoProduct(page, 'travis');
    await addCurrentProductToCart(page);
    await gotoCart(page);
    await expectCartItemCount(page, 2);
  });

  test('add a product with a non-default attribute (PSE)', async ({ page }) => {
    await gotoProduct(page, 'horatio');
    // Switch from Blue (default) to Pink (id 4) on the Color attribute (id 1).
    await selectAttributePse(page, 1, 4);
    await addCurrentProductToCart(page);
    await gotoCart(page);
    await expectCartItemCount(page, 1);
    await expect(cartItem(page, 0)).toContainText(/Pink/);
  });

  test('add custom quantity from product page', async ({ page }) => {
    await gotoProduct(page, 'horatio');
    await setQuantity(page, 3);
    await addCurrentProductToCart(page);
    await gotoCart(page);
    await expectCartItemCount(page, 1);
    await expect(cartItem(page, 0).locator('.CartItem-quantity input[type="number"]')).toHaveValue('3');
  });

  test('increment then decrement an existing cart item', async ({ page }) => {
    await gotoProduct(page, 'horatio');
    await addCurrentProductToCart(page);
    await gotoCart(page);

    await changeCartItemQuantity(page, 0, 'plus');
    await expect(cartItem(page, 0).locator('.CartItem-quantity input[type="number"]')).toHaveValue('2');

    await changeCartItemQuantity(page, 0, 'minus');
    await expect(cartItem(page, 0).locator('.CartItem-quantity input[type="number"]')).toHaveValue('1');
  });

  test('remove a cart item shows a Restore notice', async ({ page }) => {
    await gotoProduct(page, 'horatio');
    await addCurrentProductToCart(page);
    await gotoCart(page);

    await cartItem(page, 0).locator('button:has-text("Delete")').click();
    await page.waitForLoadState('networkidle').catch(() => {});

    // After removal, the CartItemDelete snippet appears with a "Restore" button.
    await expect(page.locator('.CartItemDelete')).toBeVisible();
    await expect(page.locator('.CartItemDelete')).toContainText(/has been removed/i);
  });

  test('cart persists across page reloads (anonymous session)', async ({ page }) => {
    await gotoProduct(page, 'horatio');
    await addCurrentProductToCart(page);
    await gotoCart(page);
    await expectCartItemCount(page, 1);

    await page.reload();
    await expectCartItemCount(page, 1);
  });
});
