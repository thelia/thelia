import { expect, test } from '@playwright/test';
import { addCurrentProductToCart, expectCartItemCount, gotoCart, gotoProduct } from '../helpers/cart';
import { DEMO, createGroup, purgeCustomerGroupData, registerMember, type CustomerGroup } from '../helpers/customer-group';
import type { Customer } from '../helpers/customer';
import { login } from '../helpers/customer';
import { ddevMysql } from '../helpers/db';

/**
 * Customer groups, cart: a visitor (default group) puts a product outside the whitelist of a group
 * and a product of the whitelisted category in the cart, then signs in as a member of that group.
 * The product the group may not see is taken out of the cart with a message; the other stays.
 * A member of the default group keeps both.
 */
async function productIdsInCartOf(email: string): Promise<number[]> {
  const out = await ddevMysql(`
    SELECT ci.product_id FROM cart_item ci JOIN cart ca ON ca.id = ci.cart_id JOIN customer cu ON cu.id = ca.customer_id
    WHERE cu.email = '${email}' ORDER BY ci.product_id`);
  return out === '' ? [] : out.split('\n').map(Number);
}

test.describe('Customer groups: cart at sign-in', () => {
  test.describe.configure({ mode: 'serial' });

  let group: CustomerGroup;
  let restricted: Customer;
  let regular: Customer;

  test.beforeAll(async ({ browser }) => {
    await purgeCustomerGroupData();
    group = await createGroup({ categoryIds: [DEMO.categories.sofas] });
    restricted = await registerMember(browser, group.id);
    regular = await registerMember(browser, null);
  });

  test.afterAll(async () => {
    await purgeCustomerGroupData();
  });

  async function guestFillsTheCart(page: import('@playwright/test').Page): Promise<void> {
    await gotoProduct(page, DEMO.armchair.slug); // outside the whitelist
    await addCurrentProductToCart(page);
    await gotoProduct(page, DEMO.sofa.slug); // in the whitelisted category
    await addCurrentProductToCart(page);
    await gotoCart(page);
    await expectCartItemCount(page, 2);
  }

  test('signing in as a restricted member takes the closed product out of the cart, with a message', async ({ page }) => {
    await guestFillsTheCart(page);

    await login(page, restricted.email, restricted.password);
    await gotoCart(page);

    await expectCartItemCount(page, 1);
    await expect(page.locator('.CartItem').first()).toContainText(/nigel/i);
    await expect(page.locator('.CartItem', { hasText: /owen/i })).toHaveCount(0);
    // The message names the product taken out.
    await expect(page.locator('main')).toContainText(/not available to your account/i);
    await expect(page.locator('main')).toContainText(/owen/i);

    expect(await productIdsInCartOf(restricted.email)).toEqual([DEMO.sofa.productId]);
  });

  test('signing in as a member of the default group keeps both products', async ({ page }) => {
    await guestFillsTheCart(page);

    await login(page, regular.email, regular.password);
    await gotoCart(page);

    await expectCartItemCount(page, 2);
    await expect(page.locator('main')).not.toContainText(/not available to your account/i);
    expect(await productIdsInCartOf(regular.email)).toEqual([DEMO.armchair.productId, DEMO.sofa.productId].sort((a, b) => a - b));
  });
});
