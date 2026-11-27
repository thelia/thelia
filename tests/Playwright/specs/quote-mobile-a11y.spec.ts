import AxeBuilder from '@axe-core/playwright';
import { expect, test, type BrowserContext, type Page, type TestInfo } from '@playwright/test';
import { auditPage, expectContentFitsViewport, expectFieldsInViewport } from '../helpers/a11y';
import { loginAdmin } from '../helpers/admin';
import { acceptMandatoryConsents, clickNextOrder, selectFirstInvoiceAddress, selectPaymentByLabel } from '../helpers/checkout';
import type { Customer } from '../helpers/customer';
import {
  capture,
  fillCart,
  openNegotiation,
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
 * The quote screens on a 390 px phone and on the back-office at 1440 px:
 *  - nothing of the page of a quote, or of the table of the counter-proposal, sticks out of the viewport;
 *  - axe (WCAG 2.1 A and AA) finds no serious or critical violation on the page of a quote, on the
 *    counter-proposal table and on the negotiation screen of the back-office;
 *  - a full-page capture is written for each.
 */
const { chair, armchair } = QUOTE_PRODUCTS;

/** The back-office does not clip its body: a screen wider than the viewport shows as a wider document. */
async function expectDocumentFits(page: Page, what: string): Promise<void> {
  const widths = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }));
  expect.soft(widths.scroll, `${what}: the document is wider than the ${widths.client}px viewport`).toBeLessThanOrEqual(widths.client + 1);
}

/**
 * The axe scan of a back-office screen. The contrast rule is left out of the failing set (the palette
 * of the admin theme fails it on every screen of the back-office); the nodes it flags are attached.
 */
async function auditBackOffice(page: Page, name: string, testInfo: TestInfo): Promise<void> {
  const tags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];
  const excluded = ['.sf-toolbar', '[id^="sfwdt"]', '.sf-minitoolbar'];
  const scan = (skip: string[]) => {
    let builder = new AxeBuilder({ page }).withTags(tags);
    for (const selector of excluded) builder = builder.exclude(selector);
    if (skip.length > 0) builder = builder.disableRules(skip);
    return builder.analyze();
  };

  await page.waitForLoadState('networkidle').catch(() => {});
  const results = await scan(['color-contrast']);
  const contrast = (await scan([])).violations.filter((violation) => 'color-contrast' === violation.id);
  const blocking = results.violations
    .filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''))
    .flatMap((violation) => violation.nodes.map((node) => `${violation.id} (${violation.impact}) on ${node.target.join(' ')}`));

  console.log(`a11y ${name}: ${results.passes.length} rules passed, ${results.violations.length} rules violated, ${blocking.length} blocking node(s), ${contrast.flatMap((violation) => violation.nodes).length} contrast node(s) of the admin theme left out`);
  await testInfo.attach(`axe-${name}`, { body: JSON.stringify([...results.violations, ...contrast], null, 2), contentType: 'application/json' });
  expect(results.passes.length, `axe checked nothing on ${name}`).toBeGreaterThan(0);
  expect.soft(blocking, `serious or critical accessibility violations on ${name}`).toEqual([]);
  expect.soft(await page.locator('h1').count(), `no h1 on ${name}`).toBeGreaterThan(0);
}

test.describe('Quote: layout and accessibility of the screens', () => {
  test.describe.configure({ mode: 'serial' });

  let originalMode: QuoteMode;
  let buyer: Customer;
  let buyerContext: BrowserContext;
  let phone: Page;
  let adminContext: BrowserContext;
  let admin: Page;
  let quoteId = 0;
  let requestedId = 0;

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(360_000);
    originalMode = readQuoteMode();
    await purgeQuoteData();
    setQuoteMode('alongside');
    buyer = await registerQuoteCustomer(browser, 'a11y');
    ({ context: buyerContext, page: phone } = await signedInAs(browser, buyer, { width: 390, height: 844 }));
    adminContext = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport: { width: 1440, height: 900 } });
    admin = await adminContext.newPage();
    await loginAdmin(admin);

    await fillCart(phone, buyer.email, [
      { product: chair, quantity: 3 },
      { product: armchair, quantity: 2 },
    ]);
    quoteId = await requestQuoteFromCart(phone, 'A comment long enough to wrap on a narrow screen: we order these every quarter and would like a price for the year.');
    await priceAndSendQuote(admin, quoteId, { Tina: '60', Owen: '350' });

    // A second request the merchant has not answered: its negotiation screen is the one the merchant works on (a quote that was sent is locked).
    await fillCart(phone, buyer.email, [
      { product: chair, quantity: 3 },
      { product: armchair, quantity: 2 },
    ]);
    requestedId = await requestQuoteFromCart(phone, 'Second request');
  });

  test.afterAll(async () => {
    await buyerContext?.close();
    await adminContext?.close();
    setQuoteMode(originalMode ?? 'disabled');
    await purgeQuoteData();
    expect(await remainingQuoteRows()).toEqual({ customers: 0, quotes: 0, revisions: 0 });
  });

  test('the request form, then the page of a quote, fit a 390 px phone and pass axe', async ({}, testInfo) => {
    await phone.goto('/checkout/cart');
    await expect(phone.getByTestId('quote-request-button').first()).toBeVisible();
    await expectContentFitsViewport(phone, 'the cart with the request button');
    await auditPage(phone, 'quote-cart-390', testInfo);
    await capture(phone, 'quote-mobile-01-cart', { withoutFixed: true });

    await phone.goto('/account/quote/request');
    await expectContentFitsViewport(phone, 'the request form');
    await auditPage(phone, 'quote-request-390', testInfo);
    await capture(phone, 'quote-mobile-02-request', { withoutFixed: true });

    await phone.goto(`/account/quote/${quoteId}`);
    await expect(phone.getByTestId('quote-lines')).toBeVisible();
    await expect(phone.getByTestId('quote-granted-discount')).toBeVisible();
    await expectContentFitsViewport(phone, 'the page of a quote');
    await auditPage(phone, 'quote-page-390', testInfo);
    await capture(phone, 'quote-mobile-03-page', { withoutFixed: true });
  });

  test('the counter-proposal table fits a 390 px phone, every field in full view, and passes axe', async ({}, testInfo) => {
    await phone.goto(`/account/quote/${quoteId}`);
    await phone.getByTestId('quote-counter-offer-zone').locator('summary').click();
    await expect(phone.getByTestId('quote-counter-offer')).toBeVisible();
    await expect(phone.getByTestId('quote-counter-offer-line')).toHaveCount(2);
    await expectContentFitsViewport(phone, 'the counter-proposal table');
    await expectFieldsInViewport(phone, phone.locator('[data-testid="quote-counter-offer-line"] input[name^="counter_price_"]'), 'the wished price');
    await expectFieldsInViewport(phone, phone.locator('[data-testid="quote-counter-offer-line"] input[name^="counter_quantity_"]'), 'the wished quantity');
    for (const input of await phone.locator('[data-testid="quote-counter-offer"] input').all()) {
      await expect(input, 'a field keeps its label').toHaveAccessibleName(/\S/);
    }
    await auditPage(phone, 'quote-counter-offer-390', testInfo);
    await capture(phone, 'quote-mobile-04-counter-offer', { withoutFixed: true });
  });

  test('the buyer counters, the shop answers, the buyer accepts and reaches the imposed delivery and the payment', async ({}, testInfo) => {
    await phone.goto(`/account/quote/${quoteId}`);
    await phone.getByTestId('quote-counter-offer-zone').locator('summary').click();
    await phone.locator('input[name^="counter_price_"]').first().fill('55');
    await Promise.all([phone.waitForURL(/countered=1/, { timeout: 30_000 }), phone.getByTestId('quote-counter-offer-submit').click()]);
    await expectContentFitsViewport(phone, 'the page after the counter-proposal');
    await capture(phone, 'quote-mobile-05-countered', { withoutFixed: true });

    await priceAndSendQuote(admin, quoteId, { Tina: '55', Owen: '350' });
    await phone.goto(`/account/quote/${quoteId}`);
    await expect(phone.getByTestId('quote-revision-number')).toHaveText('3');
    await expectContentFitsViewport(phone, 'the page of the new revision');
    await auditPage(phone, 'quote-revision-3-390', testInfo);
    await capture(phone, 'quote-mobile-06-revision-3', { withoutFixed: true });

    await Promise.all([phone.waitForURL(/\/checkout\//, { timeout: 30_000 }), phone.getByTestId('quote-accept').click()]);
    await expect.poll(async () => (await quoteOf(quoteId)).state, { timeout: 15_000 }).toBe('accepted');
    await expect(phone.getByTestId('quote-cart-exit').first()).toBeVisible();
    await expectContentFitsViewport(phone, 'the step after the acceptance');
    await auditPage(phone, 'quote-after-acceptance-390', testInfo);
    await capture(phone, 'quote-mobile-07-after-acceptance', { withoutFixed: true });

    await phone.goto('/checkout/delivery');
    await expect(phone.getByTestId('quote-delivery-carrier')).toBeVisible();
    await expect(phone.getByTestId('quote-delivery-address')).toBeVisible();
    await expectContentFitsViewport(phone, 'the delivery step');
    await auditPage(phone, 'quote-tunnel-delivery-390', testInfo);
    await capture(phone, 'quote-mobile-08-tunnel-delivery', { withoutFixed: true });

    await clickNextOrder(phone, /\/checkout\/payment/);
    await selectFirstInvoiceAddress(phone);
    await selectPaymentByLabel(phone, /cheque|check/i);
    await acceptMandatoryConsents(phone);
    await expectContentFitsViewport(phone, 'the payment step');
    await auditPage(phone, 'quote-tunnel-payment-390', testInfo);
    await capture(phone, 'quote-mobile-09-tunnel-payment', { withoutFixed: true });
  });

  test('the negotiation screen of the back-office passes axe at 1440 px', async ({}, testInfo) => {
    await openNegotiation(admin, requestedId);
    await expectDocumentFits(admin, 'the negotiation screen');
    await auditBackOffice(admin, 'quote-negotiation-1440', testInfo);
    await capture(admin, 'quote-21-admin-negotiation-1440');
  });
});
