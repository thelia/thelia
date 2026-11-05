import { mkdirSync } from 'node:fs';
import { type Browser, type Page } from '@playwright/test';
import { expect, test } from '../fixtures/customer';
import { addCurrentProductToCart, cartItem, gotoCart, gotoProduct, setQuantity } from '../helpers/cart';
import { placeOrderWithCheque } from '../helpers/checkout';
import { ddevMysql } from '../helpers/db';
import { ensureCustomDeliveryConfigured } from '../helpers/delivery';
import { loginAdmin } from '../helpers/admin';
import {
  cartLines,
  countLogLines,
  expireReservations,
  mailpitDeleteMatching,
  mailpitMessage,
  mailpitSearch,
  orderIdOf,
  renderMail,
  shopMailTemplateHas,
  snapshotConfigTable,
  orderLines,
  physicalStock,
  POLICY_LIMITED,
  POLICY_REFUSE,
  POLICY_UNLIMITED,
  readConfig,
  readPse,
  RESERVATION_CONFIG_NAMES,
  setReservation,
  snapshotCartLines,
  snapshotConfig,
  snapshotPse,
  writeConfig,
  writePse,
} from '../helpers/stock';
import { ConfigStorePage } from '../poms/backoffice/config-store-page';

/**
 * Recette of the stock work: reservation of a cart, preorder (open and capped), low stock
 * threshold, and the stock settings of the combinations grid of the back-office.
 *
 * Each scenario takes its own demo product (one sale element each) so that no scenario depends on
 * the state another one leaves. Settings and stock levels are written in the database, and put
 * back after every test.
 */

// Demo products with a single sale element, by scenario.
const RESERVED = { slug: 'victoria', pse: 49 };
const PREORDER = { slug: 'violet', pse: 50 };
const CAPPED = { slug: 'lexie', pse: 57 };
const ALERTED = { slug: 'emily', pse: 62 };
const WIDGET_PSE = 68;
// A product with five combinations, for the grid of the back-office.
const GRID_PRODUCT = 1;
const GRID_PSES = [1, 2, 3, 4, 5];

const RESTOCK_DATE = '2027-03-15';
const SHOT_DIR = new URL('../../../var/t217/recette/', import.meta.url).pathname;

const ALL_PSES = [RESERVED.pse, PREORDER.pse, CAPPED.pse, ALERTED.pse, WIDGET_PSE, ...GRID_PSES];
const CONFIG_NAMES = [...RESERVATION_CONFIG_NAMES, 'low_stock_threshold'];

const stockTag = (page: Page) => page.locator('form[name="thelia_cart_add"] .StockTag');
const submitButton = (page: Page) => page.locator('form[name="thelia_cart_add"] button[type="submit"]');

async function guest(browser: Browser): Promise<Page> {
  return (await browser.newContext({ ignoreHTTPSErrors: true })).newPage();
}

/**
 * The Cheque module takes the stock when the order is paid, not when it is placed
 * (`manageStockOnCreation()` is false), so the stock assertions come after the back-office has
 * moved the order to "paid", like the merchant does when the cheque arrives.
 */
async function markOrderPaid(browser: Browser, orderRef: string): Promise<void> {
  const admin = await guest(browser);
  await loginAdmin(admin);
  const orderId = await ddevMysql(`SELECT id FROM \`order\` WHERE ref = '${orderRef}'`);
  await admin.goto(`/admin/order/update/${orderId}`);
  await admin.getByTestId('order-status-select').selectOption({ label: 'Paid' }).catch(async () => {
    await admin.getByTestId('order-status-select').selectOption('2');
  });
  await Promise.all([admin.waitForLoadState('load'), admin.getByTestId('order-status-submit').click()]);
  await expect.poll(() => ddevMysql(`SELECT status_id FROM \`order\` WHERE id = ${orderId}`), { timeout: 15_000 }).toBe('2');
  await admin.context().close();
}

async function newLines(pse: number, before: Set<number>) {
  return (await cartLines(pse)).filter((line) => !before.has(line.id));
}

async function knownLineIds(pse: number): Promise<Set<number>> {
  return new Set((await cartLines(pse)).map((line) => line.id));
}

async function reservedRowsNow(pse: number): Promise<number> {
  return Number(await ddevMysql(`SELECT COUNT(*) FROM cart_item WHERE product_sale_elements_id = ${pse} AND reserved_until > NOW()`));
}

test.describe('Stock: reservation, preorder, low stock threshold', () => {
  // An order walks the whole checkout, then the back-office: more than the 60 s of the other specs.
  test.describe.configure({ timeout: 180_000 });

  let restoreConfig: () => Promise<void>;
  let restorePse: () => Promise<void>;
  let restoreCartLines: () => Promise<void>;
  let restoreAllConfig: (() => Promise<void>) | null = null;

  test.beforeAll(async () => {
    await ensureCustomDeliveryConfigured();
    restoreConfig = await snapshotConfig(CONFIG_NAMES);
    restorePse = await snapshotPse(ALL_PSES);
    restoreCartLines = await snapshotCartLines(ALL_PSES);
  });

  test.afterEach(async () => {
    if (restoreAllConfig) {
      await restoreAllConfig();
      restoreAllConfig = null;
    }
    await restoreCartLines();
    await restorePse();
    await restoreConfig();
  });

  // ------------------------------------------------------------------ 1, 3: reservation

  test('1. a reserved last item cannot be added from another browser, and can once the reservation has run out', async ({ browser }) => {
    await setReservation({ enabled: true, scope: 'cart', durationMinutes: 1 });
    await writePse(RESERVED.pse, { quantity: 1, outOfStockPolicy: POLICY_REFUSE });
    const known = await knownLineIds(RESERVED.pse);

    const first = await guest(browser);
    await gotoProduct(first, RESERVED.slug);
    await expect(stockTag(first)).toContainText(/in stock/i);
    await addCurrentProductToCart(first);

    // The line of the first cart holds the item for the configured minute.
    const held = await newLines(RESERVED.pse, known);
    expect(held).toHaveLength(1);
    expect(held[0].reservedUntil, 'the line carries the end of its reservation').not.toBeNull();
    expect(await reservedRowsNow(RESERVED.pse)).toBe(1);
    expect(await physicalStock(RESERVED.pse), 'a reservation does not touch the physical stock').toBe(1);

    // Another browser (another cart): the last item is out of stock, nothing to add.
    const second = await guest(browser);
    await gotoProduct(second, RESERVED.slug);
    await expect(stockTag(second)).toContainText(/out of stock/i);
    await expect(submitButton(second)).toHaveCount(0);
    expect(await newLines(RESERVED.pse, known), 'no line was created for the second cart').toHaveLength(1);

    // The minute runs out. A reservation past its end is never counted, whether or not the purge
    // command has removed it: its end is moved into the past here instead of waiting 60 seconds.
    await expireReservations(RESERVED.pse);
    expect(await reservedRowsNow(RESERVED.pse)).toBe(0);

    await gotoProduct(second, RESERVED.slug);
    await expect(stockTag(second)).toContainText(/in stock/i);
    await expect(submitButton(second)).toHaveCount(1);
    await addCurrentProductToCart(second);
    expect(await newLines(RESERVED.pse, known), 'the second cart now holds the item').toHaveLength(2);

    await first.context().close();
    await second.context().close();
  });

  test('2. the order placed from the first cart turns the reservation into a sale: stock down once', async ({ authedPage, freshCustomer }) => {
    await setReservation({ enabled: true, scope: 'cart', durationMinutes: 15 });
    await writePse(RESERVED.pse, { quantity: 1, outOfStockPolicy: POLICY_REFUSE });
    const known = await knownLineIds(RESERVED.pse);

    await gotoProduct(authedPage, RESERVED.slug);
    await addCurrentProductToCart(authedPage);
    const held = await newLines(RESERVED.pse, known);
    expect(held).toHaveLength(1);
    expect(held[0].reservedUntil).not.toBeNull();
    expect(await physicalStock(RESERVED.pse)).toBe(1);

    const orderRef = await placeOrderWithCheque(authedPage, freshCustomer.email);
    console.log(`[stock] order ${orderRef} placed, unpaid: physical=${await physicalStock(RESERVED.pse)}, active reservations=${await reservedRowsNow(RESERVED.pse)}`);

    await markOrderPaid(authedPage.context().browser()!, orderRef);
    console.log(`[stock] order ${orderRef} paid: physical=${await physicalStock(RESERVED.pse)}, active reservations=${await reservedRowsNow(RESERVED.pse)}`);

    expect(await physicalStock(RESERVED.pse), 'physical stock 1 -> 0: decremented once, never twice').toBe(0);
    expect(await orderLines(orderRef)).toEqual([{ isPreorder: 0, restockDate: null, quantity: 1 }]);
    expect(await reservedRowsNow(RESERVED.pse), 'nothing is held any more once the order is placed').toBe(0);

    // The sale is not a preorder: the order list of the back-office carries no preorder badge.
    const admin = await guest(authedPage.context().browser()!);
    await loginAdmin(admin);
    await admin.goto(`/admin/orders?q=${orderRef}`);
    const row = admin.locator('tr', { hasText: orderRef });
    await expect(row).toBeVisible();
    await expect(row.getByTestId('bo-order-preorder-badge')).toHaveCount(0);
    await admin.context().close();
  });

  test('3. reservation off: two carts can hold the last item', async ({ browser }) => {
    await setReservation({ enabled: false });
    await writePse(RESERVED.pse, { quantity: 1, outOfStockPolicy: POLICY_REFUSE });
    const known = await knownLineIds(RESERVED.pse);

    const first = await guest(browser);
    await gotoProduct(first, RESERVED.slug);
    await addCurrentProductToCart(first);

    const second = await guest(browser);
    await gotoProduct(second, RESERVED.slug);
    await expect(stockTag(second)).toContainText(/in stock/i);
    await addCurrentProductToCart(second);

    const lines = await newLines(RESERVED.pse, known);
    expect(lines, 'both carts hold the item').toHaveLength(2);
    expect(new Set(lines.map((line) => line.cartId)).size).toBe(2);
    expect(lines.every((line) => null === line.reservedUntil), 'no reservation is written').toBe(true);

    await first.context().close();
    await second.context().close();
  });

  // ------------------------------------------------------------------ 4: preorder, open

  test.describe('4. preorder open on an item at zero with a restock date', () => {
    for (const mode of [
      { label: 'unlimited', policy: POLICY_UNLIMITED, limit: null },
      { label: 'limited to 10', policy: POLICY_LIMITED, limit: 10 },
    ]) {
      test(`4. ${mode.label}: mention and date on the product, order passes, e-mail repeats the date, back-office flags it`, async ({ authedPage, freshCustomer, browser }) => {
        await setReservation({ enabled: false });
        await writePse(PREORDER.pse, { quantity: 0, outOfStockPolicy: mode.policy, preorderLimit: mode.limit, restockDate: RESTOCK_DATE });

        await gotoProduct(authedPage, PREORDER.slug);
        await expect(stockTag(authedPage)).toContainText(/pre-?order/i);
        await expect(authedPage.locator('.PseSelector-restock')).toContainText(/March 15, 2027/);
        await expect(submitButton(authedPage)).toHaveCount(1);
        await addCurrentProductToCart(authedPage);

        // The cart line says it too.
        await gotoCart(authedPage);
        await expect(cartItem(authedPage, 0)).toContainText(/pre-?order/i);
        await expect(cartItem(authedPage, 0)).toContainText(/March 15, 2027/);

        const orderRef = await placeOrderWithCheque(authedPage, freshCustomer.email);
        await markOrderPaid(authedPage.context().browser()!, orderRef);

        expect(await physicalStock(PREORDER.pse), 'sold under zero').toBe(-1);
        expect(await orderLines(orderRef)).toEqual([{ isPreorder: 1, restockDate: RESTOCK_DATE, quantity: 1 }]);

        // E-mail of the order. It leaves the shop for Mailpit...
        await expect
          .poll(async () => (await mailpitSearch(`to:${freshCustomer.email} subject:"Your order ${orderRef}"`)).length, { timeout: 30_000, message: 'the order confirmation must reach Mailpit' })
          .toBeGreaterThan(0);
        const [sent] = await mailpitSearch(`to:${freshCustomer.email} subject:"Your order ${orderRef}"`);
        const delivered = await mailpitMessage(sent.ID);
        console.log(`[mailpit] ${mode.label}: order confirmation id=${delivered.ID} subject="${delivered.Subject}"`);
        const shopTemplateMentions = /pre-?order/i.test(delivered.HTML + delivered.Text);
        test.info().annotations.push({ type: 'mail-template', description: shopTemplateMentions ? 'the shop mail template carries the preorder mention' : 'the shop mail template (pinned by ACTIVE_MAIL_TEMPLATE) has no preorder mention: content checked by rendering with templates/email-dev' });

        // ... and the template of the clone carries the date of the order line.
        const rendered = renderMail('order_confirmation', { order: await orderIdOf(orderRef) });
        expect(rendered.status, rendered.output.slice(0, 400)).toBe(0);
        expect(rendered.output).toMatch(/Pre-order, restock expected on [^<\n]*2027/);
        if (shopTemplateMentions) {
          expect(delivered.HTML).toMatch(/Pre-order, restock expected on [^<]*2027/);
          expect(delivered.Text).toMatch(/Pre-order, restock expected on [^\n]*2027/);
        }

        // Back-office: the order is flagged in the list.
        const admin = await guest(authedPage.context().browser()!);
        await loginAdmin(admin);
        await admin.goto(`/admin/orders?q=${orderRef}`);
        const row = admin.locator('tr', { hasText: orderRef });
        await expect(row).toBeVisible();
        await expect(row.getByTestId('bo-order-preorder-badge')).toBeVisible();
        await admin.close();
      });
    }
  });

  // ------------------------------------------------------------------ 5: preorder capped at 10

  test('5. preorder capped at 10: the 11th unit is refused and the stock never goes under -10', async ({ authedPage, freshCustomer, browser }) => {
    await setReservation({ enabled: false });
    await writePse(CAPPED.pse, { quantity: 0, outOfStockPolicy: POLICY_LIMITED, preorderLimit: 10, restockDate: RESTOCK_DATE });
    const known = await knownLineIds(CAPPED.pse);

    await gotoProduct(authedPage, CAPPED.slug);
    const quantityInput = authedPage.locator('form[name="thelia_cart_add"] input[name="thelia_cart_add[quantity]"]');
    await expect(quantityInput, 'the product page bounds the quantity at the cap').toHaveAttribute('max', '10');

    // Ask for 11: the cart never holds more than 10.
    await setQuantity(authedPage, 11);
    await addCurrentProductToCart(authedPage);
    const lines = await newLines(CAPPED.pse, known);
    expect(lines).toHaveLength(1);
    expect(lines[0].quantity, 'the 11th unit is not taken').toBeLessThanOrEqual(10);

    // Top the line up to 10 from the cart if the product page clamped the request lower, then look for one more.
    await gotoCart(authedPage);
    const plus = cartItem(authedPage, 0).locator('button[data-live-action-param="plus"]');
    for (let step = 0; step < 12; step++) {
      const quantity = (await newLines(CAPPED.pse, known))[0].quantity;
      if (quantity >= 10) break;
      await plus.click();
      await expect.poll(async () => (await newLines(CAPPED.pse, known))[0].quantity).toBeGreaterThan(quantity);
    }
    expect((await newLines(CAPPED.pse, known))[0].quantity).toBe(10);
    await expect(plus, 'at the cap the cart no longer offers one more unit').toBeHidden();
    expect((await newLines(CAPPED.pse, known))[0].quantity, 'the cart still holds 10').toBe(10);

    const orderRef = await placeOrderWithCheque(authedPage, freshCustomer.email);
    await markOrderPaid(browser, orderRef);
    expect(await physicalStock(CAPPED.pse), '0 - 10').toBe(-10);
    expect(await orderLines(orderRef)).toEqual([{ isPreorder: 1, restockDate: RESTOCK_DATE, quantity: 10 }]);

    // Cap reached: nothing more to sell, the stock cannot go lower.
    const other = await guest(browser);
    await gotoProduct(other, CAPPED.slug);
    await expect(stockTag(other)).toContainText(/out of stock/i);
    await expect(submitButton(other)).toHaveCount(0);
    expect(await physicalStock(CAPPED.pse)).toBe(-10);
    await other.context().close();
  });

  // ------------------------------------------------------------------ 6: low stock threshold

  test.describe('6. low stock threshold at 20', () => {
    test('6a. set from the store page of the back-office: the dashboard widget lists the items under 20', async ({ browser }) => {
      await writePse(WIDGET_PSE, { quantity: 6, outOfStockPolicy: POLICY_REFUSE, lowStockThreshold: null });
      await writeConfig('low_stock_threshold', '5');
      const ref = await ddevMysql(`SELECT ref FROM product_sale_elements WHERE id = ${WIDGET_PSE}`);

      // The store form of the back-office saves every setting it shows, not the threshold alone.
      restoreAllConfig = await snapshotConfigTable();
      const admin = await guest(browser);
      await loginAdmin(admin);

      // Threshold 5: a quantity of 6 is not low.
      await admin.goto('/admin');
      await expect(admin.getByTestId('dashboard-low-stock-title')).toContainText('5');
      await expect(admin.getByTestId('dashboard-low-stock').filter({ hasText: ref })).toHaveCount(0);

      const store = new ConfigStorePage(admin);
      await store.goto();
      await store.expectLoaded();
      await admin.getByTestId('config-store-low-stock-threshold').fill('20');
      // The dev shop has no postal code in its address, and the form requires one.
      const zipcode = admin.locator('#thelia_configuration_store_store_zipcode');
      if ('' === (await zipcode.inputValue())) await zipcode.fill('63000');
      await Promise.all([admin.waitForLoadState('load'), store.saveAndStayButton.click()]);
      expect(await readConfig('low_stock_threshold'), 'the store page stored the threshold').toBe('20');

      await store.goto();
      await expect(admin.getByTestId('config-store-low-stock-threshold')).toHaveValue('20');

      await admin.goto('/admin');
      await expect(admin.getByTestId('dashboard-low-stock-title')).toContainText('20');
      const items = admin.getByTestId('dashboard-low-stock');
      await expect(items.filter({ hasText: ref }), 'the item at 6 is listed under the threshold of 20').toHaveCount(1);
      for (const badge of await items.locator('.badge').allTextContents()) {
        expect(Number(badge), 'every listed item is at or under 20').toBeLessThanOrEqual(20);
      }
      mkdirSync(SHOT_DIR, { recursive: true });
      await admin.screenshot({ path: `${SHOT_DIR}dashboard-low-stock-20.png`, fullPage: true, clip: await clipOf(admin, admin.locator('.card', { has: admin.getByTestId('dashboard-low-stock-title') }), 400) });
      await admin.context().close();
    });

    test.describe('6b. an order that crosses the threshold sends the alert', () => {
      test('alert sent once on the crossing, not again below it', async ({ authedPage, freshCustomer, browser }) => {
        await writeConfig('low_stock_threshold', '20');
        await setReservation({ enabled: false });
        await writePse(ALERTED.pse, { quantity: 22, outOfStockPolicy: POLICY_REFUSE, lowStockThreshold: null });
        const title = await ddevMysql(`SELECT title FROM product_i18n WHERE id = (SELECT product_id FROM product_sale_elements WHERE id = ${ALERTED.pse}) AND locale = 'en_US'`);
        const subject = `subject:"Low stock on" "${title}"`;
        await mailpitDeleteMatching(subject);
        const templateHasAlert = shopMailTemplateHas('low_stock_alert');
        const loggedBefore = countLogLines(/Error while sending email message low_stock_alert/);

        const notified = (await readConfig('store_notification_emails')) ?? (await readConfig('store_email')) ?? '';
        expect(notified).not.toBe('');

        // 22 -> 19: crosses 20.
        await gotoProduct(authedPage, ALERTED.slug);
        await setQuantity(authedPage, 3);
        await addCurrentProductToCart(authedPage);
        await markOrderPaid(browser, await placeOrderWithCheque(authedPage, freshCustomer.email));
        expect(await physicalStock(ALERTED.pse)).toBe(19);

        // The alert leaves the shop as the template of the shop allows: with the stock mails in the
        // active template it reaches Mailpit; with a template that lacks `low_stock_alert` the shop
        // logs the failure (and the order is not affected). Either way it is raised once.
        const logged = () => countLogLines(/Error while sending email message low_stock_alert/) - loggedBefore;
        if (templateHasAlert) {
          await expect.poll(async () => (await mailpitSearch(subject)).length, { timeout: 30_000, message: 'the low stock alert must reach Mailpit' }).toBe(1);
          const [alert] = await mailpitSearch(subject);
          const mail = await mailpitMessage(alert.ID);
          console.log(`[mailpit] low_stock_alert id=${mail.ID} to=${mail.To.map((to) => to.Address).join(',')} subject="${mail.Subject}"`);
          expect(mail.To.map((to) => to.Address)).toEqual(notified.split(/[,;]\s*/).filter(Boolean));
          expect(mail.HTML).toContain(title);
        } else {
          await expect.poll(logged, { timeout: 30_000, message: 'the alert must be raised (and its sending logged as failed)' }).toBe(1);
          test.info().annotations.push({ type: 'mail-template', description: 'the shop mail template (pinned by ACTIVE_MAIL_TEMPLATE) has no low_stock_alert: the alert is raised but not delivered; content checked by rendering with templates/email-dev' });
        }

        // The template of the clone renders the alert with the stock figures.
        const rendered = renderMail('low_stock_alert');
        expect(rendered.status, rendered.output.slice(0, 400)).toBe(0);
        expect(rendered.output).toMatch(/Remaining stock/);

        // 19 -> 17: already under the threshold, no second signal.
        await gotoProduct(authedPage, ALERTED.slug);
        await setQuantity(authedPage, 2);
        await addCurrentProductToCart(authedPage);
        await markOrderPaid(browser, await placeOrderWithCheque(authedPage, freshCustomer.email));
        expect(await physicalStock(ALERTED.pse)).toBe(17);
        await authedPage.waitForTimeout(3_000);
        if (templateHasAlert) {
          expect((await mailpitSearch(subject)).length, 'no new alert while the stock stays under the threshold').toBe(1);
        } else {
          expect(logged(), 'no new alert while the stock stays under the threshold').toBe(1);
        }
      });
    });
  });

  // ------------------------------------------------------------------ 7: back-office combinations grid

  test('7. back-office combinations grid: the four stock fields are in the grid, the limit only in limited mode, an entry is read back', async ({ browser }) => {
    mkdirSync(SHOT_DIR, { recursive: true });
    await writeConfig('low_stock_threshold', '5');
    await Promise.all(GRID_PSES.map((pse) => writePse(pse, { outOfStockPolicy: POLICY_REFUSE, preorderLimit: null, restockDate: null, lowStockThreshold: null })));
    const [first, second] = GRID_PSES;

    for (const viewport of [
      // The narrow screen first: the 1440 pass saves an entry.
      { width: 390, height: 844, name: '390' },
      { width: 1440, height: 900, name: '1440' },
    ]) {
      const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: viewport.width, height: viewport.height } });
      const admin = await context.newPage();
      await loginAdmin(admin);
      await admin.goto(`/admin/products/update?product_id=${GRID_PRODUCT}&current_tab=pse`);
      const table = admin.getByTestId('combinations-table');
      await expect(table).toBeVisible();

      // The four fields are laid out in the grid itself, not folded behind "show the other fields".
      for (const pse of GRID_PSES) {
        const fields = admin.getByTestId(`combinations-stock-fields-${pse}`);
        await expect(fields).toBeVisible();
        await expect(admin.getByTestId(`combinations-policy-${pse}`)).toBeVisible();
        await expect(admin.getByTestId(`combinations-restock-date-${pse}`)).toBeVisible();
        await expect(admin.getByTestId(`combinations-low-stock-threshold-${pse}`)).toBeVisible();
        await expect(admin.getByTestId(`combinations-preorder-limit-${pse}`), 'the limit is hidden outside the limited mode').toBeHidden();
      }
      const policy = admin.getByTestId(`combinations-policy-${first}`);
      const box = (await policy.boundingBox())!;
      console.log(`[grid ${viewport.name}] policy select x=${Math.round(box.x)} width=${Math.round(box.width)}, page scrollWidth=${await admin.evaluate(() => document.documentElement.scrollWidth)}`);
      expect(await admin.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), 'the page itself does not scroll sideways').toBe(true);

      // Each stock field is compact and stays inside the identity cell of its line.
      const cellBox = (await admin.getByTestId(`combinations-stock-fields-${first}`).locator('xpath=ancestor::td[1]').boundingBox())!;
      for (const id of ['policy', 'restock-date', 'low-stock-threshold']) {
        const fieldBox = (await admin.getByTestId(`combinations-${id}-${first}`).boundingBox())!;
        expect(fieldBox.width, `${id} is at most 20rem wide`).toBeLessThanOrEqual(320);
        expect(fieldBox.x, `${id} starts inside its cell`).toBeGreaterThanOrEqual(cellBox.x - 1);
        expect(fieldBox.x + fieldBox.width, `${id} ends inside its cell`).toBeLessThanOrEqual(cellBox.x + cellBox.width + 1);
      }

      await admin.getByTestId(`combinations-row-${first}`).screenshot({ path: `${SHOT_DIR}combinations-grid-row-${viewport.name}.png` });
      await admin.screenshot({ path: `${SHOT_DIR}combinations-grid-${viewport.name}.png`, fullPage: true, clip: await clipOf(admin, table, viewport.height) });

      if ('1440' === viewport.name) {
        // Limited mode shows the limit; the entry is saved and read back.
        await policy.selectOption(String(POLICY_LIMITED));
        const limit = admin.getByTestId(`combinations-preorder-limit-${first}`);
        await expect(limit).toBeVisible();
        await limit.fill('7');
        await admin.getByTestId(`combinations-restock-date-${first}`).fill('2027-02-01');
        await admin.getByTestId(`combinations-low-stock-threshold-${first}`).fill('3');
        await admin.getByTestId(`combinations-policy-${second}`).selectOption(String(POLICY_UNLIMITED));
        await expect(admin.getByTestId(`combinations-preorder-limit-${second}`), 'unlimited mode has no limit').toBeHidden();

        await Promise.all([admin.waitForLoadState('load'), admin.getByTestId('combinations-submit').click()]);

        expect(await readPse(first)).toMatchObject({ outOfStockPolicy: POLICY_LIMITED, preorderLimit: 7, restockDate: '2027-02-01', lowStockThreshold: 3 });
        expect(await readPse(second)).toMatchObject({ outOfStockPolicy: POLICY_UNLIMITED, preorderLimit: null });

        await admin.goto(`/admin/products/update?product_id=${GRID_PRODUCT}&current_tab=pse`);
        await expect(admin.getByTestId(`combinations-policy-${first}`)).toHaveValue(String(POLICY_LIMITED));
        await expect(admin.getByTestId(`combinations-preorder-limit-${first}`)).toHaveValue('7');
        await expect(admin.getByTestId(`combinations-restock-date-${first}`)).toHaveValue('2027-02-01');
        await expect(admin.getByTestId(`combinations-low-stock-threshold-${first}`)).toHaveValue('3');
        await expect(admin.getByTestId(`combinations-policy-${second}`)).toHaveValue(String(POLICY_UNLIMITED));
        await expect(admin.getByTestId(`combinations-preorder-limit-${second}`)).toBeHidden();
      }
      await context.close();
    }
  });
});

/** Page rectangle of a locator, cropped to its top `maxHeight` pixels, for a screenshot of the page. */
async function clipOf(page: Page, locator: ReturnType<Page['locator']>, maxHeight = 1200) {
  await locator.scrollIntoViewIfNeeded();
  const box = (await locator.boundingBox())!;
  const scroll = await page.evaluate(() => ({ x: window.scrollX, y: window.scrollY }));
  return { x: Math.max(0, box.x + scroll.x), y: Math.max(0, box.y + scroll.y), width: box.width, height: Math.min(box.height, maxHeight) };
}
