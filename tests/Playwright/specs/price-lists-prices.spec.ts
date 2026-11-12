import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { addCurrentProductToCart, changeCartItemQuantity, expectCartItemCount, gotoCart, gotoProduct, setQuantity } from '../helpers/cart';
import { placeOrderWithCheque } from '../helpers/checkout';
import { createGroup, customerIdOf, registerMember, signedInContext, type CustomerGroup } from '../helpers/customer-group';
import type { Customer } from '../helpers/customer';
import { ensureCustomDeliveryConfigured } from '../helpers/delivery';
import { ddevMysql } from '../helpers/db';
import { readSummary } from '../helpers/group-pricing';
import {
  ADD_TO_CART,
  PRODUCTS,
  addPriceListLine,
  assignPriceListToCustomer,
  assignPriceListToGroup,
  cardAmount,
  cartLineAmount,
  createPriceList,
  dropPriceLists,
  emptyCartOf,
  pageAmount,
  priceState,
  purgePriceListData,
  remainingPriceListRows,
  restoreStock,
  setCustomerDiscount,
  snapshotStock,
  taxed,
  type StockSnapshot,
} from '../helpers/price-list';

/**
 * Price lists, prices, in the browser, plus the same price on
 * every screen, the order taxes and the quantity tiers.
 *
 *  1. no list, no assortment: catalog and discount of the customer sheet unchanged
 *  2. a list of three lines assigned to a customer: the three negotiated prices, the catalog for the rest
 *  3. two lists, one of the group and one of the customer: the second wins on its lines
 *  4. an exclusive list: what it does not contain cannot be bought, no button
 *  5. same price on the listing, the product page, the cart and the order, taxes computed on it
 *  6. tiers: table on the product page, cart price at 9 and at 10 units
 *
 * Data: one group, four customers (e2e-cg- prefix) and the lists (e2e-pl- prefix), written with SQL and
 * removed in afterAll with the orders; the stock an order took is put back. Demo products read: the
 * armchair Owen (395.00 untaxed), the sofas Nigel (638.00) and Heathcliff (1120.00).
 */
const OWEN = PRODUCTS.owen;
const NIGEL = PRODUCTS.nigel;
const HEATHCLIFF = PRODUCTS.heathcliff;
const DISCOUNT = 10;

test.describe('Price lists: prices', () => {
  test.describe.configure({ mode: 'serial' });

  let group: CustomerGroup;
  let discounted: Customer;
  let client: Customer;
  let member: Customer;
  let both: Customer;
  let contexts: BrowserContext[] = [];
  let discountedPage: Page;
  let clientPage: Page;
  let memberPage: Page;
  let bothPage: Page;
  let stock: StockSnapshot;

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(300_000);
    await ensureCustomDeliveryConfigured();
    await purgePriceListData();
    stock = await snapshotStock([OWEN.pseId]);
    group = await createGroup();
    discounted = await registerMember(browser, null);
    await setCustomerDiscount(discounted.email, DISCOUNT);
    client = await registerMember(browser, null);
    member = await registerMember(browser, group.id);
    both = await registerMember(browser, group.id);
    const signed = await Promise.all([discounted, client, member, both].map((customer) => signedInContext(browser, customer)));
    contexts = signed.map(({ context }) => context);
    [discountedPage, clientPage, memberPage, bothPage] = signed.map(({ page }) => page);
  });

  test.afterAll(async () => {
    for (const context of contexts) await context.close();
    await restoreStock(stock);
    await purgePriceListData();
    expect(await remainingPriceListRows()).toEqual({ lists: 0, lines: 0, assignments: 0, restrictions: 0, overrides: 0, customers: 0, shopMode: 0 });
  });

  test.describe('1. no list, no assortment', () => {
    test('the shop has no list and no rule: the feature is dormant', async () => {
      expect(await ddevMysql('SELECT COUNT(*) FROM price_list')).toBe('0');
      expect(await ddevMysql('SELECT COUNT(*) FROM catalog_restriction')).toBe('0');
    });

    test('a visitor pays the catalog prices, taxed, on the listing and the product page', async ({ page }) => {
      for (const product of [OWEN, NIGEL, HEATHCLIFF]) {
        expect(await cardAmount(page, product.listing, product.slug), `${product.slug} listing`).toBe(taxed(product.price));
        expect(await pageAmount(page, product.slug), `${product.slug} page`).toBe(taxed(product.price));
      }
    });

    test('a customer with a discount of ten percent on the sheet keeps it, on the listing and the product page', async () => {
      for (const product of [OWEN, NIGEL]) {
        const expected = taxed(product.price * (1 - DISCOUNT / 100));
        expect(expected).toBeLessThan(taxed(product.price));
        expect(await cardAmount(discountedPage, product.listing, product.slug), `${product.slug} listing`).toBe(expected);
        expect(await pageAmount(discountedPage, product.slug), `${product.slug} page`).toBe(expected);
      }
    });
  });

  test.describe('2. a list of three lines assigned to a customer', () => {
    const negotiated = [
      { product: OWEN, price: 300 },
      { product: NIGEL, price: 500 },
      { product: HEATHCLIFF, price: 900 },
    ];

    test.beforeAll(async () => {
      const list = await createPriceList();
      for (const { product, price } of negotiated) await addPriceListLine(list.id, product.pseId, price);
      await assignPriceListToCustomer(list.id, await customerIdOf(client.email));
    });

    test.afterAll(async () => {
      await dropPriceLists();
    });

    test('the customer sees the three negotiated prices on the listings and the product pages', async () => {
      for (const { product, price } of negotiated) {
        expect(await cardAmount(clientPage, product.listing, product.slug), `${product.slug} listing`).toBe(taxed(price));
        expect(await pageAmount(clientPage, product.slug), `${product.slug} page`).toBe(taxed(price));
      }
    });

    test('the customer pays the catalog price for what the list does not price', async () => {
      expect(await cardAmount(clientPage, PRODUCTS.tina.listing, PRODUCTS.tina.slug)).toBe(taxed(PRODUCTS.tina.price));
      expect(await pageAmount(clientPage, PRODUCTS.tina.slug)).toBe(taxed(PRODUCTS.tina.price));
      // The catalog promotion of the stool is untouched too.
      expect(await pageAmount(clientPage, PRODUCTS.travis.slug)).toBe(taxed(19));
    });

    test('nobody else is served the negotiated price: a visitor and a customer of another sheet keep theirs', async ({ page }) => {
      expect(await cardAmount(page, OWEN.listing, OWEN.slug)).toBe(taxed(OWEN.price));
      expect(await pageAmount(page, OWEN.slug)).toBe(taxed(OWEN.price));
      expect(await pageAmount(discountedPage, OWEN.slug)).toBe(taxed(OWEN.price * (1 - DISCOUNT / 100)));
    });

    test('the cart of the customer carries the negotiated price', async () => {
      await emptyCartOf(client.email);
      await gotoProduct(clientPage, OWEN.slug);
      await addCurrentProductToCart(clientPage);
      await gotoCart(clientPage);
      await expectCartItemCount(clientPage, 1);
      expect(await cartLineAmount(clientPage, /owen/i)).toBe(taxed(300));
      expect((await readSummary(clientPage)).total).toBe(taxed(300));
      await emptyCartOf(client.email);
    });

    test('the same price on the listing, the product page, the cart and the order, and the taxes of the order follow it', async () => {
      test.setTimeout(240_000);
      const expected = taxed(300);
      expect(await cardAmount(clientPage, OWEN.listing, OWEN.slug)).toBe(expected);
      expect(await pageAmount(clientPage, OWEN.slug)).toBe(expected);

      await emptyCartOf(client.email);
      await gotoProduct(clientPage, OWEN.slug);
      await addCurrentProductToCart(clientPage);
      await gotoCart(clientPage);
      expect(await cartLineAmount(clientPage, /owen/i)).toBe(expected);

      const reference = await placeOrderWithCheque(clientPage, client.email);

      // What the order stored: the untaxed price of the list, and the tax computed on it (not on the catalog price).
      const line = await ddevMysql(`
        SELECT op.price, op.promo_price, op.was_in_promo, opt.amount
        FROM order_product op JOIN \`order\` o ON o.id = op.order_id JOIN order_product_tax opt ON opt.order_product_id = op.id
        WHERE o.ref = '${reference}'`);
      const [price, , wasInPromo, taxAmount] = line.split('\n')[0].split('\t').map(Number);
      expect(price).toBe(300);
      expect(wasInPromo).toBe(0);
      expect(taxAmount).toBeCloseTo(300 * 0.2, 6);

      // The order page of the account says the same figure.
      await clientPage.goto('/account/orders');
      await clientPage.locator('main a[href^="/account/order/"]').first().click();
      await expect(clientPage).toHaveURL(/\/account\/order\/\d+/);
      await expect(clientPage.locator('main')).toContainText(expected.toFixed(2));
      await expect(clientPage.locator('main')).not.toContainText(taxed(OWEN.price).toFixed(2));
      const postage = Number(await ddevMysql(`SELECT postage FROM \`order\` WHERE ref = '${reference}'`));
      expect((await readSummary(clientPage)).total).toBe(Math.round((expected + postage) * 100) / 100);
    });
  });

  test.describe('3. two lists, one of the group and one of the customer', () => {
    test.beforeAll(async () => {
      // The list of the group (priority 30): A = 50, B = 60. The list of the customer (priority 10): A = 45.
      const ofGroup = await createPriceList({ priority: 30 });
      await addPriceListLine(ofGroup.id, OWEN.pseId, 50);
      await addPriceListLine(ofGroup.id, NIGEL.pseId, 60);
      await assignPriceListToGroup(ofGroup.id, group.id);
      const ofCustomer = await createPriceList({ priority: 10 });
      await addPriceListLine(ofCustomer.id, OWEN.pseId, 45);
      await assignPriceListToCustomer(ofCustomer.id, await customerIdOf(both.email));
    });

    test.afterAll(async () => {
      await dropPriceLists();
    });

    test('the customer of the group who has his own list: A at 45 (his list), B at 60 (the group list), C at the catalog price', async () => {
      const expected = [
        { product: OWEN, price: 45 },
        { product: NIGEL, price: 60 },
        { product: HEATHCLIFF, price: HEATHCLIFF.price },
      ];
      for (const { product, price } of expected) {
        expect(await cardAmount(bothPage, product.listing, product.slug), `${product.slug} listing`).toBe(taxed(price));
        expect(await pageAmount(bothPage, product.slug), `${product.slug} page`).toBe(taxed(price));
      }
    });

    test('a member of the group without a list of his own: A at 50, B at 60', async () => {
      expect(await pageAmount(memberPage, OWEN.slug)).toBe(taxed(50));
      expect(await pageAmount(memberPage, NIGEL.slug)).toBe(taxed(60));
      expect(await pageAmount(memberPage, HEATHCLIFF.slug)).toBe(taxed(HEATHCLIFF.price));
    });

    test('the cart of the customer prices each line with the list that wins on it', async () => {
      await emptyCartOf(both.email);
      for (const product of [OWEN, NIGEL]) {
        await gotoProduct(bothPage, product.slug);
        await addCurrentProductToCart(bothPage);
      }
      await gotoCart(bothPage);
      await expectCartItemCount(bothPage, 2);
      expect(await cartLineAmount(bothPage, /owen/i)).toBe(taxed(45));
      expect(await cartLineAmount(bothPage, /nigel/i)).toBe(taxed(60));
      expect((await readSummary(bothPage)).total).toBe(Math.round((taxed(45) + taxed(60)) * 100) / 100);
      await emptyCartOf(both.email);
    });

    test('a visitor still pays the catalog price', async ({ page }) => {
      expect(await pageAmount(page, OWEN.slug)).toBe(taxed(OWEN.price));
    });
  });

  test.describe('4. an exclusive list', () => {
    test.beforeAll(async () => {
      const list = await createPriceList({ exclusive: true });
      await addPriceListLine(list.id, OWEN.pseId, 300);
      await addPriceListLine(list.id, NIGEL.pseId, 500);
      await assignPriceListToCustomer(list.id, await customerIdOf(client.email));
    });

    test.afterAll(async () => {
      await dropPriceLists();
    });

    test('what the list contains is bought at its price, with the button', async () => {
      for (const { product, price } of [{ product: OWEN, price: 300 }, { product: NIGEL, price: 500 }]) {
        expect(await pageAmount(clientPage, product.slug), product.slug).toBe(taxed(price));
        await expect(clientPage.locator(ADD_TO_CART).first(), product.slug).toBeVisible();
      }
    });

    test('what the list does not contain cannot be bought: no button, a plain statement', async () => {
      await gotoProduct(clientPage, HEATHCLIFF.slug);
      await expect(clientPage.locator(ADD_TO_CART)).toHaveCount(0);
      await expect(priceState(clientPage)).toHaveAttribute('data-price-state', 'unavailable');
      await expect(clientPage.locator('main')).toContainText(/not available for purchase/i);
    });

    test('a visitor buys the whole catalog as before', async ({ page }) => {
      await gotoProduct(page, HEATHCLIFF.slug);
      await expect(page.locator(ADD_TO_CART).first()).toBeVisible();
      expect(await pageAmount(page, HEATHCLIFF.slug)).toBe(taxed(HEATHCLIFF.price));
    });
  });

  test.describe('6. tiers', () => {
    test.beforeAll(async () => {
      // Lines 1 -> 10, 10 -> 9 (untaxed) on the armchair, as in the plan.
      const list = await createPriceList();
      await addPriceListLine(list.id, OWEN.pseId, 10, 1);
      await addPriceListLine(list.id, OWEN.pseId, 9, 10);
      await assignPriceListToCustomer(list.id, await customerIdOf(client.email));
    });

    test.afterAll(async () => {
      await emptyCartOf(client.email);
      await dropPriceLists();
    });

    test('the product page shows the price of the first tier and the table of the tiers; the listing the first tier', async () => {
      expect(await cardAmount(clientPage, OWEN.listing, OWEN.slug)).toBe(taxed(10));
      expect(await pageAmount(clientPage, OWEN.slug)).toBe(taxed(10));
      const tiers = clientPage.locator('[data-testid="price-tiers"] li');
      await expect(tiers).toHaveCount(2);
      await expect(tiers.nth(0)).toHaveAttribute('data-min-quantity', '1');
      await expect(tiers.nth(0)).toContainText(taxed(10).toFixed(2));
      await expect(tiers.nth(1)).toHaveAttribute('data-min-quantity', '10');
      await expect(tiers.nth(1)).toContainText(taxed(9).toFixed(2));
    });

    test('a visitor sees no tier', async ({ page }) => {
      await gotoProduct(page, OWEN.slug);
      await expect(page.locator('[data-testid="price-tiers"]')).toHaveCount(0);
    });

    test('the cart prices nine units at the first tier and ten units at the second', async () => {
      await emptyCartOf(client.email);
      await gotoProduct(clientPage, OWEN.slug);
      await setQuantity(clientPage, 9);
      await addCurrentProductToCart(clientPage);
      await gotoCart(clientPage);
      await expectCartItemCount(clientPage, 1);
      await expect(clientPage.locator('.CartItem').first()).toContainText(/\b9\b/);
      expect(await cartLineAmount(clientPage, /owen/i)).toBe(taxed(10));

      await changeCartItemQuantity(clientPage, 0, 'plus');
      await expect.poll(async () => cartLineAmount(clientPage, /owen/i), { timeout: 15_000 }).toBe(taxed(9));
      // The same figure after a reload: it is the cart of the shop that holds it, not the page.
      await gotoCart(clientPage);
      expect(await cartLineAmount(clientPage, /owen/i)).toBe(taxed(9));
    });
  });
  // Last of the file: a red here (the catalog price of a closed combination) must not hide the tiers above.
  test.describe('7. a closed combination keeps its catalog price', () => {
    test.beforeAll(async () => {
      const list = await createPriceList({ exclusive: true });
      await addPriceListLine(list.id, OWEN.pseId, 300);
      await assignPriceListToCustomer(list.id, await customerIdOf(client.email));
    });

    test.afterAll(async () => {
      await dropPriceLists();
    });

    test('the sheet shows the catalog price next to the statement, and no button', async () => {
      await gotoProduct(clientPage, HEATHCLIFF.slug);
      const catalog = taxed(HEATHCLIFF.price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      await expect(priceState(clientPage)).toHaveAttribute('data-price-state', 'unavailable');
      await expect(clientPage.locator('main')).toContainText(/not available for purchase/i);
      await expect(clientPage.locator('main')).toContainText(catalog);
      await expect(clientPage.locator(ADD_TO_CART)).toHaveCount(0);
    });
  });
});
