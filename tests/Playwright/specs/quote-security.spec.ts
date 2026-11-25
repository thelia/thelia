import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { loginAdmin } from '../helpers/admin';
import type { Customer } from '../helpers/customer';
import {
  fillCart,
  priceAndSendQuote,
  purgeQuoteData,
  QUOTE_PRODUCTS,
  quoteOf,
  readQuoteMode,
  registerQuoteCustomer,
  remainingQuoteRows,
  requestQuoteFromCart,
  setQuoteMode,
  signedInAs,
  type QuoteMode,
} from '../helpers/quote';

/**
 * Isolation between buyers, in both directions: A holds a quote the merchant sent, B holds a request
 * the merchant has not answered; neither reads the page, the history or the PDF of the other's quote
 * (404, as if it did not exist), the list of each shows only its own, and a visitor reads none.
 */
const { chair, armchair } = QUOTE_PRODUCTS;

test.describe('Quote: a buyer reads only their own quotes', () => {
  test.describe.configure({ mode: 'serial' });

  let originalMode: QuoteMode;
  let a: Customer;
  let b: Customer;
  let sessionA: { context: BrowserContext; page: Page };
  let sessionB: { context: BrowserContext; page: Page };
  let adminContext: BrowserContext;
  let admin: Page;
  let anonymous: BrowserContext;
  let quoteA = 0;
  let quoteB = 0;
  let refA = '';
  let refB = '';

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(360_000);
    originalMode = readQuoteMode();
    await purgeQuoteData();
    setQuoteMode('alongside');
    a = await registerQuoteCustomer(browser, 'seca');
    b = await registerQuoteCustomer(browser, 'secb');
    sessionA = await signedInAs(browser, a);
    sessionB = await signedInAs(browser, b);
    adminContext = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport: { width: 1440, height: 900 } });
    admin = await adminContext.newPage();
    await loginAdmin(admin);
    anonymous = await browser.newContext({ ignoreHTTPSErrors: true });

    await fillCart(sessionA.page, a.email, [{ product: chair, quantity: 2 }]);
    quoteA = await requestQuoteFromCart(sessionA.page, 'Quote of A');
    await priceAndSendQuote(admin, quoteA, { Tina: '65' });

    await fillCart(sessionB.page, b.email, [{ product: armchair, quantity: 1 }]);
    quoteB = await requestQuoteFromCart(sessionB.page, 'Quote of B');
    refA = (await quoteOf(quoteA)).ref;
    refB = (await quoteOf(quoteB)).ref;
  });

  test.afterAll(async () => {
    for (const context of [sessionA?.context, sessionB?.context, adminContext, anonymous]) await context?.close();
    setQuoteMode(originalMode ?? 'disabled');
    await purgeQuoteData();
    expect(await remainingQuoteRows()).toEqual({ customers: 0, quotes: 0, revisions: 0 });
  });

  const routesOf = (id: number) => [
    `/account/quote/${id}`,
    `/account/quote/${id}/history`,
    `/account/quote/${id}/pdf`,
    `/account/quote/${id}/pdf/2`,
    `/account/quote/${id}/pdf/1`,
  ];

  test('the owner reads the page and the PDF of their quote', async () => {
    expect((await sessionA.page.request.get(`/account/quote/${quoteA}`)).status()).toBe(200);
    const pdf = await sessionA.page.request.get(`/account/quote/${quoteA}/pdf`);
    expect(pdf.status()).toBe(200);
    expect(pdf.headers()['content-type']).toContain('application/pdf');
    expect((await sessionB.page.request.get(`/account/quote/${quoteB}`)).status()).toBe(200);
  });

  test('B reads nothing of the quote of A: page, history and PDF answer 404', async () => {
    for (const route of routesOf(quoteA)) {
      expect((await sessionB.page.request.get(route, { maxRedirects: 0 })).status(), `B on ${route}`).toBe(404);
    }
  });

  test('A reads nothing of the quote of B: page, history and PDF answer 404', async () => {
    for (const route of routesOf(quoteB)) {
      expect((await sessionA.page.request.get(route, { maxRedirects: 0 })).status(), `A on ${route}`).toBe(404);
    }
  });

  test('the list of each buyer holds their own quote only', async () => {
    await sessionA.page.goto('/account/quotes');
    await expect(sessionA.page.getByTestId('quote')).toHaveCount(1);
    await expect(sessionA.page.getByTestId('quote')).toContainText(refA);
    await expect(sessionA.page.locator('body')).not.toContainText(refB);
    await sessionB.page.goto('/account/quotes');
    await expect(sessionB.page.getByTestId('quote')).toHaveCount(1);
    await expect(sessionB.page.getByTestId('quote')).toContainText(refB);
    await expect(sessionB.page.locator('body')).not.toContainText(refA);
  });

  test('B cannot answer the quote of A', async () => {
    const before = await quoteOf(quoteA);
    const page = await sessionB.context.newPage();
    // The token is forged: this checks that nothing changes, not which barrier (token or ownership) answers first.
    const response = await page.request.post(`/account/quote/${quoteA}/reject`, { form: { revision: '2', confirm: '1', _token: 'forged' }, maxRedirects: 0 });
    expect(response.status(), 'rejection of a quote of someone else').not.toBe(200);
    await page.close();
    const after = await quoteOf(quoteA);
    expect(after.state).toBe(before.state);
    expect(after.orderId).toBeNull();
  });

  test('a visitor reads no quote', async () => {
    const page = await anonymous.newPage();
    for (const route of [...routesOf(quoteA), '/account/quotes']) {
      const response = await page.request.get(route, { maxRedirects: 0 });
      expect(response.status(), `visitor on ${route}`).not.toBe(200);
      expect(await response.text(), `visitor on ${route}`).not.toContain(refA);
    }
    await page.close();
  });
});
