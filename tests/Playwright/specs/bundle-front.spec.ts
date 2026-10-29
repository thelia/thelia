import { expect, test, type Browser, type Locator, type Page } from '@playwright/test';
import { loginAdmin } from '../helpers/admin';
import {
  VIEWPORTS,
  AdminApi,
  clearRateLimiters,
  cleanupBundleWorld,
  createBundleWorld,
  createExtraPack,
  createTestCustomer,
  dropTestCustomer,
  parseMoney,
  purgeLeftovers,
  setStock,
  stockOf,
  watchConsole,
  type BundleWorld,
  type TestCustomer,
} from '../helpers/bundle';
import { gotoCart } from '../helpers/cart';
import {
  acceptMandatoryConsents,
  clickNextOrder,
  selectFirstInvoiceAddress,
} from '../helpers/checkout';
import { ddevMysql } from '../helpers/db';
import { ensureCustomDeliveryConfigured } from '../helpers/delivery';
import { login } from '../helpers/customer';

/**
 * Front office of the product bundles on Flexy: the product page and its composition, the cart, the
 * checkout up to the confirmation, the refusals and the customer account. The numbers are the ones
 * of the plan (bike 1 x 500, helmet 2 x 30, optional lock 0..3 x 20, 10 % off, TTC 20 %): default
 * composition 604.80 struck 672.00, lock twice 648.00 struck 720.00 (saving 72.00).
 */

const CAPTURES = new URL('../../../var/t204/recette/', import.meta.url).pathname;
const SUBMIT = 'form[name="thelia_cart_add"] button[type="submit"]';

const lockOf = (page: Page, world: BundleWorld) => page.locator(`[data-bundle-item="${world.itemIds.lock}"]`);
const more = (page: Page, world: BundleWorld) => lockOf(page, world).locator('button[aria-label="Increase quantity"]');
const less = (page: Page, world: BundleWorld) => lockOf(page, world).locator('button[aria-label="Decrease quantity"]');
const lockInput = (page: Page, world: BundleWorld) => lockOf(page, world).locator('input[type="number"]');

async function expectPrice(page: Page, price: number, original: number, saving: number): Promise<void> {
  const root = page.locator('.PseSelector-price');
  await expect.poll(async () => parseMoney(await root.locator('strong').innerText()), { timeout: 15_000 }).toBe(price);
  expect(parseMoney(await root.locator('.Price-original').innerText())).toBe(original);
  expect(parseMoney(await root.getByTestId('price-saving').innerText())).toBe(saving);
}

async function openPack(page: Page, world: BundleWorld): Promise<void> {
  await page.goto(`/${world.pack.slug}.html`);
  await expect(page.getByTestId('bundle-composition')).toBeVisible();
  await page.waitForLoadState('networkidle').catch(() => {});
}

/** Picks the lock twice with the + button, waiting for the price to answer each press. */
async function chooseLockTwice(page: Page, world: BundleWorld): Promise<void> {
  await more(page, world).click();
  await expectPrice(page, 626.4, 696, 69.6);
  await more(page, world).click();
  await expectPrice(page, 648, 720, 72);
}

async function expectNoHorizontalScroll(page: Page): Promise<void> {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow, 'horizontal scroll of the page').toBeLessThanOrEqual(0);
}

async function addToCart(page: Page): Promise<void> {
  await page.locator(SUBMIT).first().click();
  await page.waitForLoadState('networkidle').catch(() => {});
}

async function cartTotal(page: Page): Promise<number> {
  const text = await page.locator('.Summary').innerText();
  const match = text.match(/Total \(including VAT\)\s*([^\n]+)/i);
  if (!match) throw new Error(`no total in the summary: ${text}`);
  return parseMoney(match[1]);
}

/** The radio of a card is visible on a wide screen only: on a phone the whole card is the target. */
async function chooseCard(page: Page, card: Locator, radioSelector: string): Promise<void> {
  const radio = card.locator(radioSelector).first();
  if (await radio.isVisible()) {
    await radio.click();
  } else {
    await card.click();
  }
  await page.waitForLoadState('networkidle').catch(() => {});
  // The live component is done re-rendering when no root carries the busy flag any more.
  await expect(page.locator('[data-live-name-value][busy]')).toHaveCount(0);
}

async function walkToConfirmation(page: Page): Promise<void> {
  await gotoCart(page);
  await clickNextOrder(page, /\/checkout\/delivery/);
  await chooseCard(page, page.locator('.DeliveryMode').first(), 'button[data-live-event-param="SET_DELIVERY_MODULE_OPTION"]');
  await clickNextOrder(page, /\/checkout\/payment/);
  await selectFirstInvoiceAddress(page);
  await chooseCard(page, page.locator('.PaymentCard').filter({ hasText: /cheque|check/i }).first(), 'button[data-live-event-param="SET_PAYMENT_MODULE_ID"]');
  await acceptMandatoryConsents(page);
  await clickNextOrder(page, /\/checkout\/(pay|confirm|invoice)/);
  await expect(page).toHaveURL(/\/checkout\/confirm/, { timeout: 20_000 });
}

async function adminPage(browser: Browser, viewport: { width: number; height: number }): Promise<Page> {
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport, locale: 'en-US' });
  const page = await context.newPage();
  await loginAdmin(page);
  return page;
}

for (const { name, viewport } of VIEWPORTS) {
  test.describe(`Front-office bundles, ${name}`, () => {
    test.describe.configure({ mode: 'serial' });
    test.use({ viewport });

    let world: BundleWorld;
    let customer: TestCustomer;
    let orderId = 0;
    let orderUrl = '';

    test.beforeAll(async () => {
      await purgeLeftovers().catch(() => 0);
      await clearRateLimiters();
      await ensureCustomDeliveryConfigured();
      world = await createBundleWorld(`FO${name === 'desktop' ? 'D' : 'M'}`);
      customer = await createTestCustomer(`fo${name}`);
    });

    test.afterAll(async () => {
      await dropTestCustomer(customer);
      await cleanupBundleWorld(world);
    });

    test('product page: composition, fixed lines, optional lock with + / - and keyboard, prices follow, 4 refused', async ({ page }) => {
      const console = watchConsole(page);
      await openPack(page, world);

      const components = page.getByTestId('bundle-component');
      await expect(components).toHaveCount(3);
      await expect(components.nth(0)).toContainText(world.bike.title);
      await expect(components.nth(1)).toContainText(world.helmet.title);
      await expect(components.nth(2)).toContainText(world.lock.title);

      // Required components are fixed: a quantity, no control.
      for (const [index, quantity] of [[0, '1'], [1, '2']] as const) {
        await expect(components.nth(index).locator('.BundleComposition-quantity')).toContainText(`${quantity} ×`);
        await expect(components.nth(index).locator('input, button')).toHaveCount(0);
      }
      // The lock is optional: a tag, a quantity control bounded 0..3, none chosen.
      await expect(components.nth(2)).toContainText('Optional');
      await expect(lockInput(page, world)).toHaveValue('0');
      await expect(lockInput(page, world)).toHaveAttribute('min', '0');
      await expect(lockInput(page, world)).toHaveAttribute('max', '3');

      // Default composition: 560 -> 504 excl. tax, so 672 struck / 604.80 with 20 % tax, saving 67.20.
      await expectPrice(page, 604.8, 672, 67.2);

      // + / -
      await more(page, world).click();
      await expectPrice(page, 626.4, 696, 69.6);
      await expect(lockInput(page, world)).toHaveValue('1');
      await more(page, world).click();
      await expectPrice(page, 648, 720, 72);
      await expect(lockInput(page, world)).toHaveValue('2');
      await less(page, world).click();
      await expectPrice(page, 626.4, 696, 69.6);
      await less(page, world).click();
      await expectPrice(page, 604.8, 672, 67.2);

      // Keyboard: type 2, leave the field.
      const input = lockInput(page, world);
      await input.click();
      await input.press('Control+a');
      await input.pressSequentially('2');
      await input.press('Tab');
      await expectPrice(page, 648, 720, 72);

      await page.locator('.PseSelector').scrollIntoViewIfNeeded();
      await page.locator('#infos').screenshot({ path: `${CAPTURES}front-pack-${name}.png` });
      await expectNoHorizontalScroll(page);

      // 4 typed at the keyboard is above the maximum of 3: the field keeps 3 (500 + 60 + 3 x 20 = 620, 558, TTC 669.60).
      await input.click();
      await input.press('Control+a');
      await input.pressSequentially('4');
      await input.press('Tab');
      await expect(input).toHaveValue('3');
      await expectPrice(page, 669.6, 744, 74.4);

      // 4 forced past the field (a change event with 4, as a tampered page would send): the shop refuses the
      // composition, shows the default one and says so.
      await input.evaluate((element) => {
        (element as HTMLInputElement).value = '4';
        element.dispatchEvent(new Event('change', { bubbles: true }));
      });
      await expectPrice(page, 604.8, 672, 67.2);
      await expect(input).toHaveValue('0');
      await expect(page.getByTestId('bundle-composition')).toContainText(/cannot be sold as chosen|default one is shown/i);

      expect(console.errors, console.errors.join('\n')).toEqual([]);
    });

    test('cart: the pack line with its included components, no price on them, the total', async ({ page }) => {
      const console = watchConsole(page);
      await openPack(page, world);
      await chooseLockTwice(page, world);
      await addToCart(page);

      await gotoCart(page);
      const lines = page.locator('[data-live-name-value="Organisms:Cart:Base"] .CartItem');
      await expect(lines).toHaveCount(1);
      const line = lines.first();
      await expect(line).toContainText(world.pack.title);
      const included = line.getByTestId('bundle-included');
      await expect(included).toContainText('Included in the bundle');
      const items = included.getByTestId('bundle-included-item');
      await expect(items).toHaveCount(3);
      await expect(items.nth(0)).toHaveText(new RegExp(`1\\s*×\\s*${world.bike.title}`));
      await expect(items.nth(1)).toHaveText(new RegExp(`2\\s*×\\s*${world.helmet.title}`));
      await expect(items.nth(2)).toHaveText(new RegExp(`2\\s*×\\s*${world.lock.title}`));
      // No price and no control on the components.
      expect(await included.innerText()).not.toMatch(/[€$£]|\d[.,]\d{2}/);
      await expect(included.locator('button, input')).toHaveCount(0);

      // Price of the line: 648 struck 720; the only priced line, so total 648.
      await expect(line.locator('.CartItem-price')).toContainText('648.00');
      await expect(line.locator('.CartItem-originalPrice')).toContainText('720.00');
      expect(await cartTotal(page)).toBe(648);

      await page.locator('main').first().screenshot({ path: `${CAPTURES}front-cart-${name}.png` });
      await expectNoHorizontalScroll(page);
      expect(console.errors, console.errors.join('\n')).toEqual([]);
    });

    test('a component out of stock: the pack cannot be added, with a message', async ({ page }) => {
      await setStock(world.helmet, 0);
      try {
        await page.goto(`/${world.pack.slug}.html`);
        await expect(page.getByTestId('bundle-composition')).toBeVisible();
        await expect(page.locator(SUBMIT)).toHaveCount(0);
        await expect(page.locator('#infos')).toContainText(/out of stock|unavailable/i);
        await page.locator('#infos').screenshot({ path: `${CAPTURES}front-pack-out-of-stock-${name}.png` });
      } finally {
        await setStock(world.helmet, 5);
      }
      // Back in stock, the pack is offered again.
      await page.goto(`/${world.pack.slug}.html`);
      await expect(page.locator(SUBMIT)).toHaveCount(1);
    });

    test('checkout: order placed, components under the pack, stock moves with the payment', async ({ page, browser }) => {
      await login(page, customer.email, customer.password);
      await openPack(page, world);
      await chooseLockTwice(page, world);
      await addToCart(page);
      const packStockBefore = await stockOf(world.pack);

      await walkToConfirmation(page);

      // Confirmation: the pack and its components, no price on them.
      const included = page.getByTestId('bundle-included');
      await expect(included).toHaveCount(1);
      await expect(included.getByTestId('bundle-included-item')).toHaveCount(3);
      await expect(page.locator('main')).toContainText(world.pack.title);
      orderUrl = (await page.getByRole('link', { name: /see my order/i }).getAttribute('href')) ?? '';
      expect(orderUrl).not.toBe('');

      // In the database: one bundle line, three component lines without price pointing at it.
      orderId = Number(await ddevMysql(`SELECT id FROM \`order\` WHERE customer_id = ${customer.id} ORDER BY id DESC LIMIT 1;`));
      const lines = (
        await ddevMysql(
          `SELECT line_type, product_ref, quantity, price, promo_price, parent IS NOT NULL FROM order_product WHERE order_id = ${orderId} ORDER BY id;`,
        )
      )
        .split('\n')
        .map((row) => row.split('\t'));
      expect(lines.map((row) => `${row[0]}:${row[1]}:${Number(row[2])}`)).toEqual([
        `bundle:${world.pack.ref}:1`,
        `bundle_component:${world.bike.ref}:1`,
        `bundle_component:${world.helmet.ref}:2`,
        `bundle_component:${world.lock.ref}:2`,
      ]);
      expect(Number(lines[0][4])).toBe(540); // 600 struck less 10 %, excl. tax
      for (const row of lines.slice(1)) {
        expect(Number(row[3])).toBe(0);
        expect(Number(row[4])).toBe(0);
        expect(row[5]).toBe('1');
      }
      expect(Number(await ddevMysql(`SELECT COUNT(*) FROM order_product WHERE order_id = ${orderId} AND parent = (SELECT id FROM order_product WHERE order_id = ${orderId} AND line_type = 'bundle');`))).toBe(3);

      // The cheque takes the stock when the order is paid, not at the placement.
      expect([await stockOf(world.bike), await stockOf(world.helmet), await stockOf(world.lock)]).toEqual([7, 5, 10]);

      const admin = await adminPage(browser, viewport);
      try {
        await admin.goto(`/admin/order/update/${orderId}`);
        await admin.getByTestId('order-status-select').selectOption({ label: 'Paid' });
        await Promise.all([
          admin.waitForLoadState('load'),
          admin.getByTestId('order-status-submit').click(),
        ]);
        await expect.poll(async () => [await stockOf(world.bike), await stockOf(world.helmet), await stockOf(world.lock)], { timeout: 15_000 }).toEqual([6, 3, 8]);
        // The technical sale element of the pack is never touched.
        expect(await stockOf(world.pack)).toBe(packStockBefore);
        expect(await stockOf(world.pack)).toBe(0);

        // The back-office order shows the components with the pack.
        await admin.goto(`/admin/order/update/${orderId}`);
        for (const component of [world.pack, world.bike, world.helmet, world.lock]) {
          await expect(admin.locator('body')).toContainText(component.title);
        }

        // Cancelled, the components go back to the shelf: 7 / 5 / 10 again.
        await admin.getByTestId('order-status-select').selectOption({ label: 'Canceled' });
        await Promise.all([
          admin.waitForLoadState('load'),
          admin.getByTestId('order-status-submit').click(),
        ]);
        await expect.poll(async () => [await stockOf(world.bike), await stockOf(world.helmet), await stockOf(world.lock)], { timeout: 15_000 }).toEqual([7, 5, 10]);
        expect(await stockOf(world.pack)).toBe(0);
      } finally {
        await admin.context().close();
      }
    });

    test('account: the order detail groups the components under the pack', async ({ page }) => {
      expect(orderUrl, 'the checkout test must have placed the order').not.toBe('');
      await login(page, customer.email, customer.password);
      await page.goto(orderUrl);
      await expect(page.locator('main')).toContainText(world.pack.title);
      const included = page.getByTestId('bundle-included');
      await expect(included).toHaveCount(1);
      const items = included.getByTestId('bundle-included-item');
      await expect(items).toHaveCount(3);
      await expect(items.nth(1)).toHaveText(new RegExp(`2\\s*×\\s*${world.helmet.title}`));
      await expect(items.nth(2)).toHaveText(new RegExp(`2\\s*×\\s*${world.lock.title}`));
      expect(await included.innerText()).not.toMatch(/[€$£]|\d[.,]\d{2}/);
      // The components are not lines of their own: only the pack carries a title outside the group.
      await expect(page.locator('main')).toContainText('Included in the bundle');
      await page.locator('main').first().screenshot({ path: `${CAPTURES}front-account-order-${name}.png` });
    });

    test('a pack made invalid after the addition (required component deleted) is refused in the checkout, no 500', async ({ page }) => {
      const own = await createExtraPack(world, 'INVALID');
      try {
        await login(page, customer.email, customer.password);
        await page.goto(`/${own.pack.slug}.html`);
        await expect(page.getByTestId('bundle-composition')).toBeVisible();
        await addToCart(page);
        await gotoCart(page);
        await expect(page.locator('[data-live-name-value="Organisms:Cart:Base"] .CartItem')).toHaveCount(1);

        // The merchant deletes a required component while the pack waits in the cart.
        const api = await AdminApi.open();
        expect(await api.delete(`/api/admin/product_bundle_items/${own.itemIds.helmet}`)).toBe(204);
        await api.dispose();

        const statuses: number[] = [];
        page.on('response', (response) => {
          if (response.request().resourceType() === 'document') statuses.push(response.status());
        });

        // The steps after the cart send the shopper back to it, never on to the payment or the confirmation.
        for (const path of ['/checkout/delivery', '/checkout/payment']) {
          await page.goto(path);
          await expect(page, path).toHaveURL(/\/checkout\/cart/);
        }

        // The pay action says why, in the cart the shopper lands on.
        await page.goto('/checkout/pay');
        await expect(page).toHaveURL(/\/checkout\/cart/);
        await expect(page.locator('.SnackBar--error')).toContainText(/can no longer be ordered as it is in your cart\. Please remove it and add it again/);
        await page.screenshot({ path: `${CAPTURES}front-invalid-pack-${name}.png`, fullPage: true });

        expect(statuses.filter((status) => status >= 500), `statuses: ${statuses.join(',')}`).toEqual([]);
        expect(Number(await ddevMysql(`SELECT COUNT(*) FROM \`order\` WHERE customer_id = ${customer.id};`))).toBe(1);
      } finally {
        // Whatever happens above, the next test starts on an empty cart.
        await ddevMysql(`DELETE ci FROM cart_item ci JOIN cart c ON c.id = ci.cart_id WHERE c.customer_id = ${customer.id};`);
      }
    });

    // The cart says on the line of the pack that it can no longer be ordered (no price shown as valid, no partial
    // list of components), and the "Order" link, whose step guard sends the shopper back to the cart, flashes why.
    test('a pack made invalid: the cart says so on the line and the Order button explains why the shopper stays on it', async ({ page }) => {
      const own = await createExtraPack(world, 'SILENT');
      try {
        await login(page, customer.email, customer.password);
        await page.goto(`/${own.pack.slug}.html`);
        await expect(page.getByTestId('bundle-composition')).toBeVisible();
        await addToCart(page);
        await gotoCart(page);
        await expect(page.locator('[data-live-name-value="Organisms:Cart:Base"] .CartItem')).toHaveCount(1);
        const api = await AdminApi.open();
        expect(await api.delete(`/api/admin/product_bundle_items/${own.itemIds.helmet}`)).toBe(204);
        await api.dispose();

        const sentence = new RegExp(`The pack ${own.pack.ref} can no longer be ordered as it is in your cart\\. Please remove it and add it again`);

        // KO2: the line itself is flagged; the old price and the partial list of components are not presented as valid.
        await page.goto('/checkout/cart');
        const line = page.locator('[data-live-name-value="Organisms:Cart:Base"] .CartItem-wrapper').first();
        await expect(line.locator('[data-cart-item-unorderable]')).toContainText(sentence);
        await expect(line.locator('.CartItem-price')).toHaveCount(0);
        await expect(line.getByTestId('bundle-included')).toHaveCount(0);
        await expect(line).not.toContainText(/604[.,]80/);
        await page.locator('main').first().screenshot({ path: `${CAPTURES}front-invalid-pack-line-${name}.png` });

        // KO1: the Order link leads to /checkout/delivery, which sends the shopper back to the cart; the reason is flashed.
        await expect(page.locator('.SnackBar--error')).toHaveCount(0);
        // Turbo 8 would fetch the next step on hover and spend the flash before any click.
        await expect(page.locator('a:has-text("Order")').last()).toHaveAttribute('data-turbo-prefetch', 'false');
        await page.locator('a:has-text("Order")').last().click();
        await expect(page).toHaveURL(/\/checkout\/cart/);
        await page.waitForLoadState('networkidle').catch(() => {});
        await expect(page.locator('.SnackBar--error')).toContainText(sentence, { timeout: 5_000 });
        await page.screenshot({ path: `${CAPTURES}front-invalid-pack-order-${name}.png`, fullPage: true });
      } finally {
        // Whatever happens above, the next test starts on an empty cart.
        await ddevMysql(`DELETE ci FROM cart_item ci JOIN cart c ON c.id = ci.cart_id WHERE c.customer_id = ${customer.id};`);
      }
    });
  });
}
