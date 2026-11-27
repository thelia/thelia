import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { loginAdmin } from '../helpers/admin';
import type { Customer } from '../helpers/customer';
import {
  capture,
  fillCart,
  isoDay,
  openQuoteSheet,
  priceAndSendQuote,
  purgeQuoteData,
  QUOTE_PRODUCTS,
  quoteOf,
  readQuoteMode,
  registerQuoteCustomer,
  remainingQuoteRows,
  requestQuoteFromCart,
  setQuoteMode,
  setValidUntil,
  signedInAs,
  type QuoteMode,
} from '../helpers/quote';

/**
 * Validity of a quote: the last day is acceptable, the day after it is not (no acceptance, a way to
 * ask the shop for an extension), and the merchant's extension makes the quote acceptable again.
 * The past date is written in the database: the screens of the shop refuse to pick one.
 */
const { chair } = QUOTE_PRODUCTS;

test.describe('Quote: validity and extension', () => {
  test.describe.configure({ mode: 'serial' });

  let originalMode: QuoteMode;
  let buyer: Customer;
  let buyerContext: BrowserContext;
  let shop: Page;
  let adminContext: BrowserContext;
  let admin: Page;
  let quoteId = 0;

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(300_000);
    originalMode = readQuoteMode();
    await purgeQuoteData();
    setQuoteMode('alongside');
    buyer = await registerQuoteCustomer(browser, 'exp');
    ({ context: buyerContext, page: shop } = await signedInAs(browser, buyer));
    adminContext = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport: { width: 1440, height: 900 } });
    admin = await adminContext.newPage();
    await loginAdmin(admin);

    await fillCart(shop, buyer.email, [{ product: chair, quantity: 2 }]);
    quoteId = await requestQuoteFromCart(shop, 'Please quote.');
    await priceAndSendQuote(admin, quoteId, { Tina: '65' });
  });

  test.afterAll(async () => {
    await buyerContext?.close();
    await adminContext?.close();
    setQuoteMode(originalMode ?? 'disabled');
    await purgeQuoteData();
    expect(await remainingQuoteRows()).toEqual({ customers: 0, quotes: 0, revisions: 0 });
  });

  test('on its last day the quote can still be accepted', async () => {
    await setValidUntil(quoteId, isoDay(0));
    await shop.goto(`/account/quote/${quoteId}`);
    await expect(shop.getByTestId('quote-state')).toHaveAttribute('data-state', 'quoted');
    await expect(shop.getByTestId('quote-accept')).toBeVisible();
    await expect(shop.getByTestId('quote-expired')).toHaveCount(0);
  });

  test('the day after, the quote cannot be accepted and the buyer is offered a way to ask for an extension', async () => {
    await setValidUntil(quoteId, isoDay(-1));
    await shop.goto(`/account/quote/${quoteId}`);
    await expect(shop.getByTestId('quote-state')).toHaveAttribute('data-state', 'expired');
    await expect(shop.getByTestId('quote-expired')).toBeVisible();
    await expect(shop.getByTestId('quote-accept')).toHaveCount(0);
    await expect(shop.getByTestId('quote-counter-offer-zone')).toHaveCount(0);
    await expect(shop.getByTestId('quote-extension-link')).toHaveAttribute('href', /^mailto:.+subject=/);
    await capture(shop, 'quote-16-buyer-expired');

    // The acceptance is refused by the shop itself, not only hidden.
    const refused = await shop.request.post(`/account/quote/${quoteId}/accept`, { form: { revision: '2', _token: 'x' }, maxRedirects: 0 });
    expect(refused.status(), 'a forged acceptance of an expired quote').not.toBe(200);
    expect((await quoteOf(quoteId)).state).not.toBe('accepted');
    expect((await quoteOf(quoteId)).orderId).toBeNull();
  });

  test('the merchant extends the quote and the buyer can accept it again', async () => {
    await openQuoteSheet(admin, quoteId);
    await expect(admin.getByTestId('quote-state')).toHaveAttribute('data-state', 'expired');
    const form = admin.getByTestId('quote-extend-form');
    await expect(form).toBeVisible();
    await capture(admin, 'quote-17-admin-expired-extend');
    await form.locator('input[name="valid_until"]').fill(isoDay(7));
    await Promise.all([admin.waitForResponse((response) => response.url().includes('/extend') && 'POST' === response.request().method()), admin.getByTestId('quote-extend').click()]);
    await expect.poll(async () => (await quoteOf(quoteId)).validUntil, { timeout: 15_000 }).toBe(isoDay(7));
    await expect.poll(async () => (await quoteOf(quoteId)).state).toBe('quoted');

    await shop.goto(`/account/quote/${quoteId}`);
    await expect(shop.getByTestId('quote-state')).toHaveAttribute('data-state', 'quoted');
    await expect(shop.getByTestId('quote-expired')).toHaveCount(0);
    await expect(shop.getByTestId('quote-accept')).toBeVisible();
    await Promise.all([shop.waitForURL(/\/checkout\//, { timeout: 30_000 }), shop.getByTestId('quote-accept').click()]);
    expect((await quoteOf(quoteId)).state).toBe('accepted');
    await capture(shop, 'quote-18-buyer-accepted-after-extension');
  });
});
