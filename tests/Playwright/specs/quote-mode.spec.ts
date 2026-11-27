import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { loginAdmin } from '../helpers/admin';
import { ORDER_PLACED_URL } from '../helpers/checkout';
import type { Customer } from '../helpers/customer';
import {
  capture,
  configSnapshot,
  fillCart,
  orderCountOf,
  purgeQuoteData,
  QUOTE_PRODUCTS,
  readQuoteMode,
  removeAddedConfigRows,
  registerQuoteCustomer,
  remainingQuoteRows,
  setQuoteMode,
  setQuoteModeInBackOffice,
  signedInAs,
  type QuoteMode,
} from '../helpers/quote';

/**
 * The quote mode of the shop, set on the store configuration screen of the back-office:
 *
 *   disabled   no quote button, no "My quotes" entry, the checkout is as it was;
 *   alongside  the quote button next to the way to the checkout;
 *   only       the way to the checkout is gone and a direct visit of a step past the cart goes back to the cart.
 */
const { chair } = QUOTE_PRODUCTS;

test.describe('Quote: the mode of the shop', () => {
  test.describe.configure({ mode: 'serial' });

  let originalMode: QuoteMode;
  let configBefore: Map<string, string>;
  let buyer: Customer;
  let buyerContext: BrowserContext;
  let shop: Page;
  let adminContext: BrowserContext;
  let admin: Page;

  const checkoutLink = (page: Page) => page.locator('main a[href*="/checkout/delivery"]');
  const quoteButton = (page: Page) => page.getByTestId('quote-request-button');

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(240_000);
    originalMode = readQuoteMode();
    configBefore = await configSnapshot();
    await purgeQuoteData();
    setQuoteMode('disabled');
    buyer = await registerQuoteCustomer(browser, 'mode');
    ({ context: buyerContext, page: shop } = await signedInAs(browser, buyer));
    adminContext = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport: { width: 1440, height: 900 } });
    admin = await adminContext.newPage();
    await loginAdmin(admin);
    await fillCart(shop, buyer.email, [{ product: chair, quantity: 1 }]);
  });

  test.afterAll(async () => {
    await buyerContext?.close();
    await adminContext?.close();
    setQuoteMode(originalMode ?? 'disabled');
    if (configBefore) console.log('configuration rows a save of the screen changed (put back: added rows)', JSON.stringify(await removeAddedConfigRows(configBefore)));
    await purgeQuoteData();
    expect(await remainingQuoteRows()).toEqual({ customers: 0, quotes: 0, revisions: 0 });
  });

  test('disabled: no quote button, no quote screen, the checkout is unchanged', async () => {
    await setQuoteModeInBackOffice(admin, 'disabled');

    await shop.goto('/checkout/cart');
    await expect(checkoutLink(shop).first()).toBeVisible();
    await expect(quoteButton(shop)).toHaveCount(0);
    await shop.goto('/account');
    await expect(shop.locator('a[href="/account/quotes"]')).toHaveCount(0);
    expect((await shop.goto('/account/quote/request'))?.status(), 'request form of a shop without quotes').toBeGreaterThanOrEqual(400);

    // The checkout still takes the cart to the delivery step.
    await shop.goto('/checkout/cart');
    await capture(shop, 'quote-mode-1-disabled');
    await shop.goto('/checkout/delivery');
    await expect(shop).toHaveURL(/\/checkout\/delivery/);
  });

  test('alongside: the quote button comes next to the way to the checkout', async () => {
    await setQuoteModeInBackOffice(admin, 'alongside');

    await shop.goto('/checkout/cart');
    await expect(quoteButton(shop)).toBeVisible();
    await expect(checkoutLink(shop).first()).toBeVisible();
    await shop.goto('/account');
    await expect(shop.locator('a[href="/account/quotes"]:visible').first()).toBeVisible();
    await shop.goto('/checkout/cart');
    await capture(shop, 'quote-mode-2-alongside');
  });

  test('only: the way to the checkout is gone, the quote button stays, and a direct visit of the delivery step is refused', async () => {
    await setQuoteModeInBackOffice(admin, 'only');

    await shop.goto('/checkout/cart');
    await expect(quoteButton(shop)).toBeVisible();
    await expect(checkoutLink(shop)).toHaveCount(0);
    await capture(shop, 'quote-mode-3-only');

    // The refusal comes at the first step past the cart: delivery, then payment, send the buyer back to the cart with the sentence.
    for (const step of ['/checkout/delivery', '/checkout/payment']) {
      await shop.goto(step);
      await expect(shop, `a direct visit of ${step}`).toHaveURL(/\/checkout\/cart/);
      await expect(shop.locator('.SnackBar--error').first()).toContainText(/only sells through quotes/i);
      await expect(quoteButton(shop)).toBeVisible();
    }
    await capture(shop, 'quote-mode-4-only-delivery-refused');
    expect(await orderCountOf(buyer.email), 'no order was placed from a cart that is no quote').toBe(0);
    await expect(shop, 'the buyer is not taken to a confirmation').not.toHaveURL(ORDER_PLACED_URL);
  });

  test('the screen of the back-office keeps the mode it was given', async () => {
    await setQuoteModeInBackOffice(admin, 'alongside');
    expect(readQuoteMode()).toBe('alongside');
    await setQuoteModeInBackOffice(admin, 'disabled');
    expect(readQuoteMode()).toBe('disabled');
  });
});
