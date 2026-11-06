import { mkdirSync } from 'node:fs';
import { type Browser, type Page } from '@playwright/test';
import { expect, test } from '../fixtures/customer';
import { addCurrentProductToCart, gotoProduct, setQuantity } from '../helpers/cart';
import { placeOrderWithCheque } from '../helpers/checkout';
import { loginAdmin } from '../helpers/admin';
import { ddevMysql } from '../helpers/db';
import { ensureCustomDeliveryConfigured } from '../helpers/delivery';
import {
  cleanupBundleWorld,
  clearRateLimiters,
  createBundleWorld,
  createExtraProduct,
  type BundleWorld,
  type Component,
} from '../helpers/bundle';
import { newCustomer, registerCustomer } from '../helpers/customer';
import {
  cartLines,
  expireCartHolds,
  expireReservations,
  heldNow,
  latestCartIdOf,
  orderLines,
  payOrderInBackOffice,
  physicalStock,
  POLICY_REFUSE,
  POLICY_UNLIMITED,
  purgeOrdersAndCustomers,
  readPse,
  RESERVATION_CONFIG_NAMES,
  setReservation,
  snapshotConfig,
  writePse,
} from '../helpers/stock';

/**
 * Recette of the second batch of the stock work (#217, v2): what a combination may still sell in the
 * back-office (D13), the preorder filter of the order list (D14), the preorder follow-up screen
 * (D15), and the checkout of the Flexy front above the budget of holds (D21, with D20).
 *
 * Everything the spec needs is created for the run: products (through the admin API, refs
 * `PW-BUNDLE-V2…`), the customers, the carts and the orders, and removed afterwards.
 */

const RESTOCK_DATE = '2027-03-15';
const SOONER_DATE = '2027-01-10';
const SHOT_DIR = new URL('../../../var/t217/recette/v2/', import.meta.url).pathname;
// The per-cart budget of new holds in an hour (framework.php, THELIA_STOCK_RESERVATION_RATE_LIMIT_PER_CART).
const CART_BUDGET = 60;
// A product with five combinations of the demo, read and never written.
const GRID_PRODUCT = 1;
const GRID_PSES = [1, 2, 3, 4, 5];

const pseRefOf = (component: Component): string => `${component.ref}-0`;

async function adminPage(browser: Browser, width = 1440): Promise<Page> {
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width, height: 900 } });
  const page = await context.newPage();
  await loginAdmin(page);
  return page;
}

async function guest(browser: Browser): Promise<Page> {
  return (await browser.newContext({ ignoreHTTPSErrors: true })).newPage();
}

test.describe('Stock v2: sellable figure, preorder filter, follow-up screen, checkout over the budget', () => {
  test.describe.configure({ mode: 'serial', timeout: 300_000 });

  const tag = `V2${Date.now().toString(36)}`;
  let world: BundleWorld;
  // The three preorder products of the follow-up screen (the first holds the worked example).
  let example: Component;
  let sooner: Component;
  let later: Component;
  let undated: Component;
  const placedOrders: { ref: string; quantity: number }[] = [];
  const customerEmails: string[] = [];
  let restoreConfig: () => Promise<void>;

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(600_000);
    mkdirSync(SHOT_DIR, { recursive: true });
    await ensureCustomDeliveryConfigured();
    restoreConfig = await snapshotConfig(RESERVATION_CONFIG_NAMES);
    await setReservation({ enabled: false });

    world = await createBundleWorld(tag);
    example = await createExtraProduct(world, 'EXAMPLE', 'Example', 10, 3);
    sooner = await createExtraProduct(world, 'SOONER', 'Sooner', 10, 0);
    later = await createExtraProduct(world, 'LATER', 'Later', 10, 0);
    undated = await createExtraProduct(world, 'UNDATED', 'Undated', 10, 0);

    // The worked example of D15: stock 3, A orders 2, B 4, C 2 (placed one after the other, each
    // paid in the back-office, as the Cheque module takes the stock at payment): stock -5.
    await writePse(example.saleElementsId, { quantity: 3, outOfStockPolicy: POLICY_UNLIMITED, restockDate: RESTOCK_DATE });
    const customer = newCustomer();
    customerEmails.push(customer.email);
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    await registerCustomer(page, customer);
    for (const quantity of [2, 4, 2]) {
      await gotoProduct(page, example.slug);
      if (quantity > 1) await setQuantity(page, quantity);
      await addCurrentProductToCart(page);
      const ref = await placeOrderWithCheque(page, customer.email);
      await payOrderInBackOffice(browser, ref);
      placedOrders.push({ ref, quantity });
      console.log(`[v2] order ${ref} (${quantity}) paid, physical stock now ${await physicalStock(example.saleElementsId)}`);
    }
    await context.close();

    // The other rows of the follow-up screen: shortage 2 with an earlier date, 8 with the same date as the example, 9 without a date.
    await writePse(sooner.saleElementsId, { quantity: -2, outOfStockPolicy: POLICY_UNLIMITED, restockDate: SOONER_DATE });
    await writePse(later.saleElementsId, { quantity: -8, outOfStockPolicy: POLICY_UNLIMITED, restockDate: RESTOCK_DATE });
    await writePse(undated.saleElementsId, { quantity: -9, outOfStockPolicy: POLICY_UNLIMITED, restockDate: null });
  });

  test.afterAll(async () => {
    await restoreConfig?.();
    await purgeOrdersAndCustomers(placedOrders.map((order) => order.ref), customerEmails);
    if (world) await cleanupBundleWorld(world);
    await clearRateLimiters();
  });

  // ------------------------------------------------------------------ 1. D13: sellable figure of the combinations tab

  test('1. combinations tab: "Sellable N" under the physical stock, tooltip of what the carts hold, unlimited, nothing for a pack', async ({ browser }) => {
    const bike = world.bike;
    await setReservation({ enabled: true, scope: 'cart', durationMinutes: 15 });
    await writePse(bike.saleElementsId, { quantity: 7, outOfStockPolicy: POLICY_REFUSE });

    // Two carts hold 2 and 1 of the 7.
    const first = await guest(browser);
    await gotoProduct(first, bike.slug);
    await setQuantity(first, 2);
    await addCurrentProductToCart(first);
    const second = await guest(browser);
    await gotoProduct(second, bike.slug);
    await addCurrentProductToCart(second);
    const lines = await cartLines(bike.saleElementsId);
    expect(lines, 'two carts hold the bike').toHaveLength(2);
    expect(lines.every((line) => null !== line.reservedUntil), 'both lines carry the end of their hold').toBe(true);
    const held = await heldNow(bike.saleElementsId);
    expect(held.quantity).toBe(3);
    expect(held.carts).toBe(2);
    expect(await physicalStock(bike.saleElementsId), 'holding never touches the physical stock').toBe(7);

    const admin = await adminPage(browser);
    const tab = `/admin/products/update?product_id=${bike.productId}&current_tab=pse`;
    await admin.goto(tab);
    const note = admin.getByTestId(`combinations-sellable-${bike.saleElementsId}`);
    await expect(note).toBeVisible();
    await expect(note, '7 physical - 3 held').toContainText(/\b4\b/);
    expect(await note.innerText()).not.toMatch(/\b7\b/);

    // It sits under the physical stock field.
    const stockField = admin.locator('#default_pse_quantity, input[name="quantity[]"]').first();
    const fieldBox = (await stockField.boundingBox())!;
    const noteBox = (await note.boundingBox())!;
    expect(noteBox.y, 'the figure is below the stock input').toBeGreaterThanOrEqual(fieldBox.y + fieldBox.height - 1);

    // Tooltip on hover: held quantity, number of carts, nearest end of hold.
    await admin.getByTestId(`combinations-sellable-hint-${bike.saleElementsId}`).hover();
    const tooltip = admin.locator('.tooltip-inner');
    await expect(tooltip).toBeVisible();
    const tooltipText = (await tooltip.innerText()).trim();
    console.log(`[v2] D13 tooltip: ${tooltipText}`);
    expect(tooltipText).toMatch(/\b3\b[^\d]*\b2\b/);
    expect(tooltipText, 'nearest expiry as the database holds it').toContain(held.nextExpiry!);
    await admin.screenshot({ path: `${SHOT_DIR}d13-sellable-tooltip-1440.png`, fullPage: false });

    // The holds run out: the whole stock is sellable again and the tooltip says no cart holds it.
    await admin.mouse.move(0, 0);
    await expireReservations(bike.saleElementsId);
    await admin.goto(tab);
    await expect(admin.getByTestId(`combinations-sellable-${bike.saleElementsId}`)).toContainText(/\b7\b/);
    await admin.getByTestId(`combinations-sellable-hint-${bike.saleElementsId}`).hover();
    await expect(admin.locator('.tooltip-inner')).toBeVisible();
    expect((await admin.locator('.tooltip-inner').innerText()).trim(), 'no hold left: no quantity, no date').not.toMatch(/\d{4}-\d{2}-\d{2}/);

    // A policy without a limit: "unlimited".
    await admin.mouse.move(0, 0);
    await writePse(bike.saleElementsId, { outOfStockPolicy: POLICY_UNLIMITED });
    await admin.goto(tab);
    await expect(admin.getByTestId(`combinations-sellable-${bike.saleElementsId}`)).toContainText(/unlimited|illimit/i);

    // A grid of five combinations: every row carries its figure, right for the policy it has (the data is read, not written).
    await admin.goto(`/admin/products/update?product_id=${GRID_PRODUCT}&current_tab=pse`);
    for (const pseId of GRID_PSES) {
      const stock = await readPse(pseId);
      const gridNote = admin.getByTestId(`combinations-sellable-${pseId}`);
      await expect(gridNote, `combination ${pseId}`).toBeVisible();
      if (POLICY_UNLIMITED === stock.outOfStockPolicy) {
        await expect(gridNote).toContainText(/unlimited|illimit/i);
      } else if (POLICY_REFUSE === stock.outOfStockPolicy) {
        await expect(gridNote).toContainText(new RegExp(`\\b${Math.max(0, stock.quantity)}\\b`));
      }
    }
    await admin.screenshot({ path: `${SHOT_DIR}d13-sellable-grid-1440.png`, fullPage: false });

    // A pack: no figure at all (D22), whatever its technical combination holds.
    await admin.goto(`/admin/products/update?product_id=${world.pack.productId}&current_tab=pse`);
    await expect(admin.locator('body')).toBeVisible();
    await expect(admin.locator('[data-testid^="combinations-sellable-"]')).toHaveCount(0);
    expect(await ddevMysql(`SELECT is_bundle FROM product WHERE id = ${world.pack.productId}`), 'the product is a pack').toBe('1');

    await first.context().close();
    await second.context().close();
    await admin.context().close();
  });

  // ------------------------------------------------------------------ 2. D14: preorder filter of the order list

  test('2. order list: preorder filter with / without, chip, results agree with the badge', async ({ browser }) => {
    expect(placedOrders).toHaveLength(3);
    const [served, preorderB, preorderC] = placedOrders.map((order) => order.ref);
    expect(await orderLines(served)).toEqual([{ isPreorder: 0, restockDate: null, quantity: 2 }]);
    expect(await orderLines(preorderB)).toEqual([{ isPreorder: 1, restockDate: RESTOCK_DATE, quantity: 4 }]);
    expect(await orderLines(preorderC)).toEqual([{ isPreorder: 1, restockDate: RESTOCK_DATE, quantity: 2 }]);

    const admin = await adminPage(browser);
    const badge = (ref: string) => admin.locator('tr', { hasText: ref }).getByTestId('bo-order-preorder-badge');

    const filter = async (value: 'with' | 'without', search = ''): Promise<void> => {
      await admin.goto('/admin/orders');
      await admin.getByTestId('order-advanced-toggle').click();
      const group = admin.getByTestId('order-filter-preorder');
      await expect(group).toBeVisible();
      await group.locator(`label[for="filter-preorder-${value}"]`).click();
      await admin.getByTestId('order-search-input').fill(search);
      await Promise.all([admin.waitForURL(new RegExp(`preorder=${value}`)), admin.getByTestId('order-search-submit').click()]);
    };

    // With: the chip says so, the two preorders are there, the order served from the stock is not, every row has the badge.
    await filter('with');
    const chip = admin.getByTestId('order-active-chip-preorder');
    await expect(chip).toBeVisible();
    await expect(chip).toContainText(/with|con/i);
    await expect(admin.locator('tr', { hasText: preorderB })).toBeVisible();
    await expect(admin.locator('tr', { hasText: preorderC })).toBeVisible();
    await expect(admin.locator('tr', { hasText: served })).toHaveCount(0);
    const rows = await admin.locator('tbody tr').count();
    const badges = await admin.getByTestId('bo-order-preorder-badge').count();
    console.log(`[v2] D14 with: ${rows} rows, ${badges} badges`);
    expect(rows).toBeGreaterThan(0);
    expect(badges, 'every row of the "with" result carries the preorder badge').toBe(rows);
    await admin.screenshot({ path: `${SHOT_DIR}d14-orders-preorder-with-1440.png`, fullPage: false });

    // Without: the order served from the stock is there, with no badge; the preorders are not.
    await filter('without', served);
    await expect(admin.getByTestId('order-active-chip-preorder')).toContainText(/without|sin|senza/i);
    await expect(admin.locator('tr', { hasText: served })).toBeVisible();
    await expect(badge(served)).toHaveCount(0);
    await filter('without', preorderB);
    await expect(admin.locator('tr', { hasText: preorderB })).toHaveCount(0);
    await filter('with', served);
    await expect(admin.locator('tr', { hasText: served })).toHaveCount(0);
    await filter('with', preorderC);
    await expect(admin.locator('tr', { hasText: preorderC })).toBeVisible();
    await expect(badge(preorderC)).toBeVisible();

    // Without any filter the badge tells the same.
    await admin.goto(`/admin/orders?q=${preorderB}`);
    await expect(badge(preorderB)).toBeVisible();
    await admin.goto(`/admin/orders?q=${served}`);
    await expect(badge(served)).toHaveCount(0);

    // The filter is cleared by its chip.
    await filter('without', served);
    await Promise.all([admin.waitForURL((url) => !url.search.includes('preorder=')), admin.getByTestId('order-active-chip-preorder').click()]);
    await admin.context().close();
  });

  // ------------------------------------------------------------------ 3. D15: preorder follow-up screen

  test('3. /admin/preorders: worked example (stock -5 = 5 to order), restock date, sort, Catalog menu entry', async ({ browser }) => {
    expect(await physicalStock(example.saleElementsId), 'stock 3 - 2 - 4 - 2').toBe(-5);

    const admin = await adminPage(browser);
    // The menu entry, under Catalog, shown to an administrator who can see the products.
    await admin.goto('/admin');
    const entry = admin.getByTestId('bo-nav-catalog-preorders');
    if (!(await entry.isVisible())) await admin.getByTestId('bo-nav-catalog').locator('a.bo-side-nav__toggle').click();
    await expect(entry, 'the Catalog menu carries the follow-up').toBeVisible();
    await Promise.all([admin.waitForURL(/\/admin\/preorders/), entry.click()]);
    await expect(admin.getByTestId('preorder-tracking-page')).toBeVisible();

    const rowOf = (component: Component) =>
      admin.locator('[data-testid="datatable-preorders-row"]').filter({ has: admin.getByText(pseRefOf(component), { exact: true }) });

    // The worked example.
    const row = rowOf(example);
    await expect(row).toHaveCount(1);
    await expect(row.getByTestId('datatable-preorders-cell-to_order'), 'the stock it lacks, not the 6 of the two preorder lines').toHaveText('5');
    await expect(row.getByTestId('datatable-preorders-cell-restock_date')).toHaveText(RESTOCK_DATE);
    const openOrders = await row.getByTestId('datatable-preorders-cell-open_orders').innerText();
    const openPreorderOrders = await ddevMysql(
      `SELECT COUNT(DISTINCT op.order_id) FROM order_product op JOIN \`order\` o ON o.id = op.order_id WHERE op.product_sale_elements_id = ${example.saleElementsId} AND op.is_preorder = 1`,
    );
    const allOrders = await ddevMysql(`SELECT COUNT(DISTINCT op.order_id) FROM order_product op WHERE op.product_sale_elements_id = ${example.saleElementsId}`);
    console.log(`[v2] D15 example: to_order=5, open_orders cell=${openOrders}, orders with a preorder line=${openPreorderOrders}, orders of the combination=${allOrders}`);
    expect(openOrders, 'open orders holding a preorder line').toBe(openPreorderOrders);
    // The product has a title in en_US only: the screen, in the locale of the administrator, falls back on the product reference.
    await expect(row.getByTestId('preorder-product-link')).toContainText(new RegExp(`${example.title}|${example.ref}`));

    // Sort: soonest restock date first, undated last, the largest shortage first on a same date.
    const refs = await admin.locator('[data-testid="datatable-preorders-cell-ref"]').allInnerTexts();
    const mine = [sooner, later, example, undated].map(pseRefOf);
    const order = refs.filter((ref) => mine.includes(ref.trim())).map((ref) => ref.trim());
    expect(order, `${SOONER_DATE} first; ${RESTOCK_DATE}: shortage 8 before 5; no date last`).toEqual(mine);
    await expect(rowOf(sooner).getByTestId('datatable-preorders-cell-restock_date')).toHaveText(SOONER_DATE);
    await expect(rowOf(undated).getByTestId('datatable-preorders-cell-restock_date')).toHaveText('-');
    await expect(rowOf(later).getByTestId('datatable-preorders-cell-to_order')).toHaveText('8');
    await expect(rowOf(sooner).getByTestId('datatable-preorders-cell-open_orders')).toHaveText('0');
    await admin.screenshot({ path: `${SHOT_DIR}d15-preorders-1440.png`, fullPage: false });

    // The product link leads to the combinations tab of the product.
    await row.getByTestId('preorder-product-link').click();
    await expect(admin).toHaveURL(new RegExp(`product_id=${example.productId}.*current_tab=pse|current_tab=pse.*product_id=${example.productId}`));
    await admin.context().close();
  });

  // ------------------------------------------------------------------ 4. D21 (with D20): checkout over the budget of holds

  test('4. Flexy checkout over the budget of holds: a refresh costs nothing, the 61st new hold is told with a delay, never a 500 nor an out-of-stock message', async ({ authedPage, freshCustomer }) => {
    customerEmails.push(freshCustomer.email);
    await clearRateLimiters();
    const product = world.lock;
    await writePse(product.saleElementsId, { quantity: 100, outOfStockPolicy: POLICY_REFUSE });
    await setReservation({ enabled: true, scope: 'checkout', durationMinutes: 15 });

    await gotoProduct(authedPage, product.slug);
    await addCurrentProductToCart(authedPage);
    const cartId = await latestCartIdOf(freshCustomer.email);
    expect(cartId).toBeGreaterThan(0);

    const rateLimited = /Too many attempts to hold your products/i;
    const shortage = /held in another cart|ran out while your order|no longer available in the requested quantity|out of stock/i;
    const visit = async (): Promise<{ status: number; text: string }> => {
      const response = await authedPage.request.get('/checkout/delivery');
      return { status: response.status(), text: await response.text() };
    };

    // The step holds the line once (a new hold: 1 of 60).
    let answer = await visit();
    expect(answer.status).toBe(200);
    expect(answer.text).not.toMatch(rateLimited);
    expect((await cartLines(product.saleElementsId)).filter((line) => line.cartId === cartId && null !== line.reservedUntil)).toHaveLength(1);

    // D20: 61 refreshes of a cart that is already held cost nothing.
    for (let refresh = 1; refresh <= CART_BUDGET + 1; refresh++) {
      answer = await visit();
      if (200 !== answer.status || rateLimited.test(answer.text)) {
        throw new Error(`refresh ${refresh} of a held cart was refused (status ${answer.status})`);
      }
    }

    // New holds: each time the hold has run out, the step poses it again and spends one of the 60. The first was spent above.
    let newHolds = 1;
    let refusal: { status: number; text: string } | null = null;
    for (let attempt = 0; attempt < CART_BUDGET + 10 && null === refusal; attempt++) {
      await expireCartHolds(cartId);
      answer = await visit();
      expect(answer.status, `status of new hold ${newHolds + 1}`).not.toBeGreaterThanOrEqual(500);
      if (rateLimited.test(answer.text)) refusal = answer;
      else newHolds++;
    }
    console.log(`[v2] D21: new holds accepted before the refusal = ${newHolds}`);
    expect(refusal, 'the budget of the cart must end up refusing a new hold').not.toBeNull();
    expect(newHolds, 'the 61st new hold is the first refused').toBe(CART_BUDGET);
    expect(refusal!.status).toBe(200);
    expect(refusal!.text).not.toMatch(shortage);

    // In the browser: the step is shown, with how long to wait; no 500 page; no shortage sentence.
    await expireCartHolds(cartId);
    const response = await authedPage.goto('/checkout/delivery');
    expect(response!.status()).toBe(200);
    await expect(authedPage).toHaveURL(/\/checkout\/delivery/);
    const flash = authedPage.getByText(rateLimited).first();
    await expect(flash).toBeVisible();
    const sentence = (await flash.innerText()).trim();
    console.log(`[v2] D21 message: ${sentence}`);
    expect(sentence).toMatch(/try again in \d+ (second|minute)s?/i);
    await expect(authedPage.getByText(shortage)).toHaveCount(0);
    await expect(authedPage.locator('body')).not.toContainText(/Internal Server Error|Whoops|Exception/i);
    await authedPage.screenshot({ path: `${SHOT_DIR}d21-checkout-over-budget.png`, fullPage: false });

    // Nothing was missing from the stock: the line is still there and the stock untouched.
    expect(await physicalStock(product.saleElementsId)).toBe(100);

    // The payment step answers the same way.
    await expireCartHolds(cartId);
    const payment = await authedPage.goto('/checkout/payment');
    expect(payment!.status()).toBeLessThan(500);
    await expect(authedPage.getByText(shortage)).toHaveCount(0);

    // The budget is a window: once the limiter is emptied the step holds again, with no message.
    await clearRateLimiters();
    await expireCartHolds(cartId);
    answer = await visit();
    expect(answer.status).toBe(200);
    expect(answer.text).not.toMatch(rateLimited);
  });
});
