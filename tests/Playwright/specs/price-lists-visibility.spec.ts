import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { addCurrentProductToCart, expectCartItemCount, gotoCart, gotoProduct } from '../helpers/cart';
import { registerMember, signedInContext } from '../helpers/customer-group';
import type { Customer } from '../helpers/customer';
import {
  ADD_TO_CART,
  PRODUCTS,
  cardAmount,
  cartLineAmount,
  emptyCartOf,
  pageAmount,
  priceState,
  purgePriceListData,
  remainingPriceListRows,
  setProductPriceDisplayMode,
  setShopPriceDisplayMode,
  shopPriceDisplayMode,
  taxed,
} from '../helpers/price-list';

/**
 * Price lists, display of the prices in the browser.
 *
 *  7. shop setting "identified customers" (`logged_in`): a visitor sees the products with no price and no
 *     purchase button, and signing in makes both appear
 *  8. a product "on request": the request button replaces the purchase button for everyone, leads to the
 *     contact page with the reference of the product, and no price is shown or sent
 *
 * Data: the shop setting `price_display_mode` (written through the console, put back to `visible` after
 * each block and by the purge whatever happened), the per-product override of the armchair Owen
 * (removed by the purge) and one customer (e2e-cg- prefix).
 */
const OWEN = PRODUCTS.owen;
const NIGEL = PRODUCTS.nigel;
const OWEN_CATALOG_PRICE = taxed(OWEN.price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

test.describe('Price lists: display of the prices', () => {
  test.describe.configure({ mode: 'serial' });

  let customer: Customer;
  let customerContext: BrowserContext;
  let customerPage: Page;

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(180_000);
    await purgePriceListData();
    customer = await registerMember(browser, null);
    ({ context: customerContext, page: customerPage } = await signedInContext(browser, customer));
  });

  test.afterAll(async () => {
    await customerContext?.close();
    await purgePriceListData();
    expect(shopPriceDisplayMode()).toBe('visible');
    expect(await remainingPriceListRows()).toEqual({ lists: 0, lines: 0, assignments: 0, restrictions: 0, overrides: 0, customers: 0, shopMode: 0 });
  });

  test.describe('7. prices shown to identified customers only', () => {
    test.beforeAll(() => {
      setShopPriceDisplayMode('logged_in');
    });

    test.afterAll(() => {
      setShopPriceDisplayMode('visible');
    });

    test('a visitor sees the product with no price and no purchase button, and an invitation to sign in', async ({ page }) => {
      await gotoProduct(page, OWEN.slug);
      await expect(priceState(page)).toHaveAttribute('data-price-state', 'hidden');
      await expect(page.locator('main')).toContainText(/log in to see the prices/i);
      await expect(page.locator('[data-testid="price-login"]').first()).toBeVisible();
      await expect(page.locator(ADD_TO_CART)).toHaveCount(0);
      await expect(page.locator('main')).not.toContainText(OWEN_CATALOG_PRICE);
      expect(await pageAmount(page, OWEN.slug)).toBeNull();
    });

    test('a visitor sees the card of the product on the listing, with no price', async ({ page }) => {
      expect(await cardAmount(page, OWEN.listing, OWEN.slug)).toBeNull();
      await expect(page.locator('main')).not.toContainText(OWEN_CATALOG_PRICE);
    });

    test('signing in from the invitation brings the visitor back on the page, with the price and the button', async ({ page }) => {
      await gotoProduct(page, OWEN.slug);
      await page.locator('[data-testid="price-login"]').first().click();
      await expect(page).toHaveURL(/\/customer\/login/);
      await page.fill('#thelia_customer_login_email', customer.email);
      await page.fill('#thelia_customer_login_password', customer.password);
      await Promise.all([
        page.waitForURL((url) => url.pathname === `/${OWEN.slug}.html`, { timeout: 20_000 }),
        page.locator('form[name="thelia_customer_login"] button[type="submit"]').click(),
      ]);
      await expect(priceState(page)).toHaveAttribute('data-price-state', 'price');
      expect(await pageAmount(page, OWEN.slug)).toBe(taxed(OWEN.price));
      await expect(page.locator(ADD_TO_CART).first()).toBeVisible();
    });

    test('an identified customer sees the prices on the listing and buys at the catalog price', async () => {
      expect(await cardAmount(customerPage, OWEN.listing, OWEN.slug)).toBe(taxed(OWEN.price));
      expect(await pageAmount(customerPage, OWEN.slug)).toBe(taxed(OWEN.price));
      await emptyCartOf(customer.email);
      await gotoProduct(customerPage, OWEN.slug);
      await addCurrentProductToCart(customerPage);
      await gotoCart(customerPage);
      await expectCartItemCount(customerPage, 1);
      expect(await cartLineAmount(customerPage, /owen/i)).toBe(taxed(OWEN.price));
      await emptyCartOf(customer.email);
    });

    test('back to "visible", the visitor sees the price and the button again', async ({ page }) => {
      setShopPriceDisplayMode('visible');
      expect(await pageAmount(page, OWEN.slug)).toBe(taxed(OWEN.price));
      await expect(page.locator(ADD_TO_CART).first()).toBeVisible();
      setShopPriceDisplayMode('logged_in');
    });
  });

  test.describe('8. a product on request', () => {
    test.beforeAll(async () => {
      await setProductPriceDisplayMode(OWEN.productId, 'on_request');
    });

    test.afterAll(async () => {
      await setProductPriceDisplayMode(OWEN.productId, null);
    });

    for (const who of ['a visitor', 'a signed-in customer']) {
      test(`${who} sees no price and no purchase button, but the request button`, async ({ page }) => {
        const target = who === 'a visitor' ? page : customerPage;
        await gotoProduct(target, OWEN.slug);
        await expect(priceState(target)).toHaveAttribute('data-price-state', 'on_request');
        await expect(target.locator('main')).toContainText(/price on request/i);
        await expect(target.locator('[data-testid="price-request"]').first()).toBeVisible();
        await expect(target.locator(ADD_TO_CART)).toHaveCount(0);
        await expect(target.locator('main')).not.toContainText(OWEN_CATALOG_PRICE);
        expect(await pageAmount(target, OWEN.slug)).toBeNull();
      });
    }

    test('the listing shows the product with no price', async ({ page }) => {
      expect(await cardAmount(page, OWEN.listing, OWEN.slug)).toBeNull();
    });

    test('the other products of the shop keep their price and their button', async ({ page }) => {
      expect(await pageAmount(page, NIGEL.slug)).toBe(taxed(NIGEL.price));
      await expect(page.locator(ADD_TO_CART).first()).toBeVisible();
    });

    test('the request button leads to the contact page with the reference in the message, and no amount', async ({ page }) => {
      await gotoProduct(page, OWEN.slug);
      await page.locator('[data-testid="price-request"]').first().click();
      await expect(page).toHaveURL(/\/contact/);
      const url = page.url();
      expect(url).toContain(OWEN.pseRef);
      expect(url).not.toMatch(/395|474/);
      await expect(page.locator('form[name="thelia_contact"]')).toBeVisible();
      await expect(page.locator('form[name="thelia_contact"] textarea')).toHaveValue(new RegExp(OWEN.pseRef));
      await expect(page.locator('form[name="thelia_contact"]')).not.toContainText(/395|474/);
    });
  });

  test.describe('8 bis. the whole shop on request', () => {
    test.beforeAll(() => {
      setShopPriceDisplayMode('on_request');
    });

    test.afterAll(() => {
      setShopPriceDisplayMode('visible');
    });

    test('every product asks for a price, for a visitor and for a customer', async ({ page }) => {
      for (const target of [page, customerPage]) {
        await gotoProduct(target, NIGEL.slug);
        await expect(priceState(target)).toHaveAttribute('data-price-state', 'on_request');
        await expect(target.locator(ADD_TO_CART)).toHaveCount(0);
        await expect(target.locator('[data-testid="price-request"]').first()).toBeVisible();
      }
    });
  });
});
