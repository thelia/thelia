import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { loginAdmin } from '../helpers/admin';
import { gotoProduct } from '../helpers/cart';
import { acceptMandatoryConsents, clickNextOrder, selectFirstInvoiceAddress, selectPaymentByLabel } from '../helpers/checkout';
import type { Customer } from '../helpers/customer';
import {
  amountOf,
  capture,
  cartLinesOf,
  fillCart,
  isAutosave,
  isoDay,
  openNegotiation,
  openQuoteSheet,
  orderOfQuote,
  orderRefOf,
  postageVatRate,
  purgeQuoteData,
  QUOTE_PRODUCTS,
  quoteCarrierTitle,
  quoteOf,
  quoteTotalTaxed,
  readQuoteMode,
  registerQuoteCustomer,
  remainingQuoteRows,
  requestQuoteFromCart,
  revisionCountOf,
  setQuoteMode,
  signedInAs,
  typeAndSave,
  type QuoteMode,
} from '../helpers/quote';

/**
 * The whole negotiation of a quote, from the cart of a buyer to the order:
 *
 *   request (comment, day needed, cart untouched) -> the merchant prices it in the back-office
 *   (quantity, negotiated prices, a line added by the search, global discount, postage, validity;
 *   totals follow without a reload) -> the buyer reads it (catalogue and negotiated prices side by
 *   side, PDF) and counter-proposes a price -> the merchant sees both values, adjusts and sends
 *   again (3 revisions) -> the buyer accepts, goes through the tunnel and pays -> the order carries
 *   the negotiated prices and the quote carries the order.
 *
 * Amounts (20 % VAT): revision 2 = tina x4 at 60, owen x2 at 350, nigel x1 at 600 (added), postage 15
 * untaxed, discount 30 taxed. The postage is taxed only when the shop names a tax rule for delivery:
 * the dev shop does not, so revision 2 = 1540 x 1.2 + 15 - 30 = 1833.00 and revision 3 (tina at 57) =
 * 1528 x 1.2 + 15 - 30 = 1818.60; the helper computes it from the setting of the shop.
 *
 * Also followed: "Price the quote" opens the draft in one gesture; a quote the buyer asks for shows "Requested amount" in
 * both lists; the word of the shop is text, an apostrophe included; the tunnel imposes the carrier and the address of the
 * quote; a buyer who left the payment resumes it from the page of the quote, which then links the order.
 */
const { chair, armchair, sofa } = QUOTE_PRODUCTS;

const REVISION_2 = [
  { product: chair, price: 60, quantity: 4 },
  { product: armchair, price: 350, quantity: 2 },
  { product: sofa, price: 600, quantity: 1 },
];
const POSTAGE = 15;
const DISCOUNT = 30;
const REVISION_3 = [{ ...REVISION_2[0], price: 57 }, REVISION_2[1], REVISION_2[2]];

test.describe('Quote: the negotiation from request to order', () => {
  test.describe.configure({ mode: 'serial' });

  let originalMode: QuoteMode;
  let buyer: Customer;
  let buyerContext: BrowserContext;
  let shop: Page;
  let adminContext: BrowserContext;
  let admin: Page;
  let quoteId = 0;
  let quoteRef = '';
  let note = '';
  let TOTAL_2 = 0;
  let TOTAL_3 = 0;

  /** The row of a product in the negotiation table of the back-office. */
  const row = (page: Page, title: string) => page.locator('[data-testid="quote-lines-table"] tbody tr').filter({ hasText: title });

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(360_000);
    originalMode = readQuoteMode();
    const postageVat = await postageVatRate();
    TOTAL_2 = quoteTotalTaxed(REVISION_2, POSTAGE, DISCOUNT, postageVat);
    TOTAL_3 = quoteTotalTaxed(REVISION_3, POSTAGE, DISCOUNT, postageVat);
    await purgeQuoteData();
    setQuoteMode('alongside');
    buyer = await registerQuoteCustomer(browser, 'neg');
    ({ context: buyerContext, page: shop } = await signedInAs(browser, buyer));
    adminContext = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport: { width: 1440, height: 900 } });
    admin = await adminContext.newPage();
    await loginAdmin(admin);
  });

  test.afterAll(async () => {
    await buyerContext?.close();
    await adminContext?.close();
    setQuoteMode(originalMode ?? 'disabled');
    await purgeQuoteData();
    expect(await remainingQuoteRows()).toEqual({ customers: 0, quotes: 0, revisions: 0 });
  });

  test('the buyer asks for a quote from a cart of two products, and the cart stays as it is', async () => {
    await fillCart(shop, buyer.email, [
      { product: chair, quantity: 3 },
      { product: armchair, quantity: 2 },
    ]);
    await capture(shop, 'quote-01-cart-with-request-button');

    quoteId = await requestQuoteFromCart(shop, 'We buy these every quarter: what is your best price?', isoDay(30));
    await expect(shop.getByTestId('quote-created')).toBeVisible();
    await expect(shop.getByTestId('quote-state')).toHaveAttribute('data-state', 'requested');
    await expect(shop.getByTestId('quote-buyer-comment')).toHaveText('We buy these every quarter: what is your best price?');
    await expect(shop.getByTestId('quote-line')).toHaveCount(2);
    await capture(shop, 'quote-02-buyer-request-sent');

    // In the list of the buyer, the total of a request is the amount asked for, not a price of the shop.
    await shop.goto('/account/quotes');
    await expect(shop.getByTestId('quote').first().getByTestId('quote-total-label')).toHaveText(/Requested amount|Montant demandé/);

    const stored = await quoteOf(quoteId);
    quoteRef = stored.ref;
    expect(stored.state).toBe('requested');
    expect(await revisionCountOf(quoteId)).toBe(1);
    // The request is a copy: the cart still holds both lines, and the tunnel still sees them.
    expect(await cartLinesOf(buyer.email)).toBe(2);
    await shop.goto('/checkout/cart');
    await expect(shop.locator('[data-live-name-value="Organisms:Cart:Base"] .CartItem')).toHaveCount(2);
  });

  test('the merchant finds the request in the list, opens the sheet and prices it', async () => {
    await admin.goto('/admin/quotes');
    await expect(admin.getByTestId('quotes-page')).toBeVisible();
    await admin.getByTestId('quotes-filters').locator('input[name="q"]').fill(quoteRef);
    await Promise.all([admin.waitForURL(/q=/), admin.getByTestId('quotes-filter-submit').click()]);
    await expect(admin.locator('[data-testid="quotes-page"] a', { hasText: quoteRef }).first()).toBeVisible();
    await expect(admin.locator('[data-testid="quotes-page"] tr').filter({ hasText: quoteRef }).getByTestId('quote-total-asked')).toHaveText(/Requested amount|Montant demandé/);
    await capture(admin, 'quote-03-admin-list');

    await openQuoteSheet(admin, quoteId);
    await expect(admin.getByTestId('quote-state')).toHaveAttribute('data-state', 'requested');
    await expect(admin.getByTestId('quote-buyer-comment')).toContainText('best price');
    await capture(admin, 'quote-04-admin-sheet');

    await openNegotiation(admin, quoteId);
    await expect(admin.locator('[data-testid="quote-lines-table"] tbody tr')).toHaveCount(2);

    // A page that reloads would lose this marker: the totals below must move without it.
    await admin.evaluate(() => ((window as unknown as { __noReload: boolean }).__noReload = true));

    // Quantity of the chair 3 -> 4, then the negotiated prices of both lines.
    await typeAndSave(admin, row(admin, 'Tina').getByTestId('quote-line-quantity-input'), '4');
    await typeAndSave(admin, row(admin, 'Tina').getByTestId('quote-line-price-input'), '60');
    await typeAndSave(admin, row(admin, 'Owen').getByTestId('quote-line-price-input'), '350');
    await expect(admin.getByTestId('quote-total-items')).toHaveText(/940/);

    // A line added by the product search; the price is the one of the catalogue until it is negotiated.
    const search = admin.getByTestId('quote-product-search');
    await search.fill('Nigel');
    const result = admin.getByTestId('quote-product-results').locator('li').first();
    await expect(result).toBeVisible({ timeout: 15_000 });
    const added = admin.waitForResponse((response) => isAutosave(response.url(), response.request().method()), { timeout: 20_000 });
    await result.locator('button[data-action*="addProduct"]').click();
    await added;
    await expect(admin.locator('[data-testid="quote-lines-table"] tbody tr')).toHaveCount(3);
    await typeAndSave(admin, row(admin, 'Nigel').getByTestId('quote-line-price-input'), '600');

    await typeAndSave(admin, admin.getByTestId('quote-discount-input'), String(DISCOUNT));
    await typeAndSave(admin, admin.getByTestId('quote-postage-input'), String(POSTAGE));
    const modules = admin.getByTestId('quote-delivery-module-input').locator('option:not([value=""])');
    expect(await modules.count(), 'the shop offers a delivery module').toBeGreaterThan(0);
    const picked = admin.waitForResponse((response) => isAutosave(response.url(), response.request().method()), { timeout: 20_000 });
    await admin.getByTestId('quote-delivery-module-input').selectOption({ index: 1 });
    await picked;
    await typeAndSave(admin, admin.getByTestId('quote-valid-until-input'), isoDay(15));
    note = "Prices valid until the end of the quarter (jusqu'à fin décembre), it's our best offer.";
    await typeAndSave(admin, admin.getByTestId('quote-merchant-note-input'), note);

    // The totals were updated in place, by the background saves, with no navigation.
    expect(await admin.evaluate(() => (window as unknown as { __noReload?: boolean }).__noReload), 'the page was not reloaded').toBe(true);
    await expect(admin.getByTestId('quote-total-taxed')).toBeVisible();
    expect(amountOf(await admin.getByTestId('quote-total-taxed').innerText())).toBe(TOTAL_2);
    expect(amountOf(await admin.getByTestId('quote-total-items').innerText())).toBe(1540);
    expect(amountOf(await admin.getByTestId('quote-total-postage').innerText())).toBe(POSTAGE);
    expect(amountOf(await admin.getByTestId('quote-total-discount').innerText())).toBe(DISCOUNT);
    await capture(admin, 'quote-05-admin-negotiation');

    // Send: the confirmation dialog, then the sheet shows the revision that was sent.
    await admin.getByTestId('quote-send-open').click();
    await expect(admin.getByTestId('quote-send-modal')).toBeVisible();
    await Promise.all([admin.waitForURL((url) => !url.pathname.endsWith('/edit') || url.searchParams.has('tab')), admin.getByTestId('quote-send').click()]);
    await expect.poll(async () => (await quoteOf(quoteId)).state, { timeout: 15_000 }).toBe('quoted');
    expect((await quoteOf(quoteId)).currentRevision).toBe(2);
    expect(await revisionCountOf(quoteId)).toBe(2);
  });

  test('the buyer reads the quote, with the catalogue and the negotiated prices side by side, and downloads the PDF', async () => {
    await shop.goto(`/account/quote/${quoteId}`);
    await expect(shop.getByTestId('quote-state')).toHaveAttribute('data-state', 'quoted');
    await expect(shop.getByTestId('quote-revision-number')).toHaveText('2');
    // Text as typed: an apostrophe is shown as an apostrophe, not as its entity, and nothing is read as markup.
    await expect(shop.getByTestId('quote-merchant-note')).toHaveText(note);
    expect(await shop.getByTestId('quote-merchant-note').innerHTML()).not.toMatch(/&amp;#|&amp;(?:apos|quot);/);
    await expect(shop.getByTestId('quote-valid-until')).toBeVisible();

    const lines = shop.getByTestId('quote-line');
    await expect(lines).toHaveCount(3);
    const tina = lines.filter({ hasText: 'Tina' });
    expect(amountOf(await tina.getByTestId('quote-line-catalogue-price').innerText())).toBe(chair.price);
    expect(amountOf(await tina.getByTestId('quote-line-price').innerText())).toBe(60);
    expect(amountOf(await lines.filter({ hasText: 'Owen' }).getByTestId('quote-line-price').innerText())).toBe(350);
    expect(amountOf(await lines.filter({ hasText: 'Nigel' }).getByTestId('quote-line-price').innerText())).toBe(600);
    expect(amountOf(await shop.getByTestId('quote-total-taxed').innerText())).toBe(TOTAL_2);
    await capture(shop, 'quote-06-buyer-quoted');

    const href = await shop.getByTestId('quote-pdf').getAttribute('href');
    expect(href).toBeTruthy();
    const pdf = await shop.request.get(href as string);
    expect(pdf.status(), 'status of the PDF').toBe(200);
    expect(pdf.headers()['content-type']).toContain('application/pdf');
    expect((await pdf.body()).subarray(0, 4).toString()).toBe('%PDF');
  });

  test('the buyer counter-proposes a price on one line', async () => {
    await shop.getByTestId('quote-counter-offer-zone').locator('summary').click();
    const form = shop.getByTestId('quote-counter-offer');
    await expect(form).toBeVisible();
    const tinaLine = form.getByTestId('quote-counter-offer-line').filter({ hasText: 'Tina' });
    await expect(tinaLine).toHaveCount(1);
    await capture(shop, 'quote-07-buyer-counter-offer-form');
    await tinaLine.locator('input[name^="counter_price_"]').fill('55');
    await Promise.all([shop.waitForURL(/countered=1/, { timeout: 30_000 }), form.getByTestId('quote-counter-offer-submit').click()]);
    await expect(shop.getByTestId('quote-countered')).toBeVisible();
    await expect(shop.getByTestId('quote-state')).toHaveAttribute('data-state', 'countered');
    await expect(shop.getByTestId('quote-waiting-for-the-shop')).toBeVisible();
    // A counter-proposal adds no revision and no line.
    expect(await revisionCountOf(quoteId)).toBe(2);
    await expect(shop.getByTestId('quote-line')).toHaveCount(3);
    await expect(shop.getByTestId('quote-line').filter({ hasText: 'Tina' }).getByTestId('quote-line-counter-offer')).toContainText('55');
    await capture(shop, 'quote-08-buyer-counter-offer-sent');
    expect((await quoteOf(quoteId)).state).toBe('countered');
  });

  test('the merchant sees both values, adjusts the price and sends a third revision', async () => {
    await openQuoteSheet(admin, quoteId);
    await expect(admin.getByTestId('quote-state')).toHaveAttribute('data-state', 'countered');
    // Next to the price the merchant sent, the price the buyer asks for.
    await expect(admin.getByTestId('quote-line-request').first()).toBeVisible();
    await capture(admin, 'quote-09-admin-counter-offer');

    await openNegotiation(admin, quoteId);
    const tinaRow = row(admin, 'Tina');
    await expect(tinaRow.getByTestId('quote-line-price-input')).toHaveValue(/^60(\.0+)?$/);
    expect(amountOf(await tinaRow.getByTestId('quote-line-asked-price').innerText()), 'price asked by the buyer').toBe(55);
    await capture(admin, 'quote-10-admin-negotiation-with-counter-offer');

    await typeAndSave(admin, tinaRow.getByTestId('quote-line-price-input'), '57');
    expect(amountOf(await admin.getByTestId('quote-total-taxed').innerText())).toBe(TOTAL_3);

    await admin.getByTestId('quote-send-open').click();
    await expect(admin.getByTestId('quote-send-modal')).toBeVisible();
    await Promise.all([admin.waitForURL((url) => !url.pathname.endsWith('/edit') || url.searchParams.has('tab')), admin.getByTestId('quote-send').click()]);
    await expect.poll(async () => (await quoteOf(quoteId)).state, { timeout: 15_000 }).toBe('quoted');
    expect(await revisionCountOf(quoteId)).toBe(3);

    // The history lists the three revisions.
    await admin.goto(`/admin/quotes/${quoteId}?tab=history`);
    await expect(admin.getByTestId('quote-tab-history')).toContainText('3');
    await capture(admin, 'quote-11-admin-history');
  });

  test('the buyer accepts the third revision, goes through the tunnel and pays: the order carries the negotiated prices', async () => {
    await shop.goto(`/account/quote/${quoteId}`);
    await expect(shop.getByTestId('quote-revision-number')).toHaveText('3');
    expect(amountOf(await shop.getByTestId('quote-total-taxed').innerText())).toBe(TOTAL_3);
    await capture(shop, 'quote-12-buyer-revision-3');

    await Promise.all([shop.waitForURL(/\/checkout\//, { timeout: 30_000 }), shop.getByTestId('quote-accept').click()]);
    await expect.poll(async () => (await quoteOf(quoteId)).state, { timeout: 15_000 }).toBe('accepted');

    // The buyer leaves before paying: every step of the tunnel offers the way back to their own cart.
    for (const step of ['/checkout/cart', '/checkout/delivery', '/checkout/payment']) {
      await shop.goto(step);
      await expect(shop.getByTestId('quote-cart-exit'), `the way back on ${step}`).toBeVisible();
    }
    await capture(shop, 'quote-25-quote-cart-way-back');

    // A product added from its page changes nothing in the cart of the quote: message, way back, same line.
    await shop.goto('/checkout/cart');
    const quoteLines = shop.locator('[data-live-name-value="Organisms:Cart:Base"] .CartItem');
    await expect(quoteLines).toHaveCount(3);
    await gotoProduct(shop, chair.slug);
    await shop.locator('form[name="thelia_cart_add"] button[type="submit"]').first().click();
    await expect(shop.getByTestId('quote-cart-exit')).toBeVisible({ timeout: 15_000 });
    await capture(shop, 'quote-26-add-refused-in-quote-cart');
    await shop.goto('/checkout/cart');
    await expect(quoteLines, 'the cart of the quote is untouched').toHaveCount(3);

    // The way back gives the own cart back (two lines), and the quote is left as it was.
    await Promise.all([shop.waitForLoadState('load'), shop.getByTestId('quote-cart-exit-button').first().click()]);
    await shop.goto('/checkout/cart');
    await expect(shop.getByTestId('quote-cart-exit')).toHaveCount(0);
    await expect(quoteLines).toHaveCount(2);
    await capture(shop, 'quote-27-own-cart-back');
    expect((await quoteOf(quoteId)).state, 'the quote was not touched').toBe('accepted');
    expect(await cartLinesOf(buyer.email)).toBe(2);

    // The page of the quote offers to resume the payment, not to accept again.
    await shop.goto(`/account/quote/${quoteId}`);
    await expect(shop.getByTestId('quote-accept')).toHaveCount(0);
    await expect(shop.getByTestId('quote-resume')).toBeVisible();
    await capture(shop, 'quote-22-buyer-resume-payment');
    await Promise.all([shop.waitForURL(/\/checkout\//, { timeout: 30_000 }), shop.getByTestId('quote-resume').click()]);

    // The carrier and the address of the quote are imposed on the delivery step: shown, never offered as a choice.
    const carrierTitle = await quoteCarrierTitle(quoteId);
    expect(carrierTitle, 'the revision names a carrier').not.toBe('');
    await shop.goto('/checkout/delivery');
    await expect(shop.getByTestId('quote-delivery-address')).toBeVisible();
    await expect(shop.getByTestId('quote-delivery-carrier')).toBeVisible();
    await expect(shop.getByTestId('quote-delivery-carrier')).toContainText(carrierTitle);
    await expect(shop.locator('button[data-live-event-param="SET_DELIVERY_MODULE_OPTION"]')).toHaveCount(0);
    await expect(shop.getByTestId('quote-delivery-carrier-unavailable')).toHaveCount(0);
    await capture(shop, 'quote-23-tunnel-delivery-imposed');
    await clickNextOrder(shop, /\/checkout\/payment/);
    await expect(shop).toHaveURL(/\/checkout\/payment/);
    await selectFirstInvoiceAddress(shop);
    await selectPaymentByLabel(shop, /cheque|check/i);
    await acceptMandatoryConsents(shop);
    await capture(shop, 'quote-14-tunnel-payment');

    const pay = shop.locator('a:has-text("Order")').last();
    await expect(pay).toBeVisible({ timeout: 12_000 });
    await Promise.all([shop.waitForURL((url) => !/\/checkout\/payment/.test(url.pathname), { timeout: 30_000 }), pay.click()]);

    await expect.poll(async () => (await quoteOf(quoteId)).state, { timeout: 20_000, message: 'the quote becomes ordered' }).toBe('ordered');
    const order = await orderOfQuote(quoteId);
    expect(order, 'an order is attached to the quote').not.toBeNull();
    expect(order?.quoteId).toBe(quoteId);
    const byRef = Object.fromEntries((order?.lines ?? []).map((line) => [line.productRef, line]));
    expect(Object.keys(byRef).sort()).toEqual([armchair.productRef, chair.productRef, sofa.productRef].sort());
    expect(byRef[chair.productRef].price, 'price of the chair on the order').toBe(57);
    expect(byRef[chair.productRef].quantity).toBe(4);
    expect(byRef[armchair.productRef].price, 'price of the armchair on the order').toBe(350);
    expect(byRef[armchair.productRef].quantity).toBe(2);
    expect(byRef[sofa.productRef].price, 'price of the sofa on the order').toBe(600);
    expect(byRef[sofa.productRef].quantity).toBe(1);
    expect(order?.discount, 'discount of the order').toBe(DISCOUNT);
    expect(order?.postage, 'postage of the order').toBe(POSTAGE);
    expect((await quoteOf(quoteId)).orderId).toBe(order?.id);
    // The cart the buyer had before is back.
    expect(await cartLinesOf(buyer.email)).toBe(2);
  });

  test('the page of the quote links the order, and nothing is left to accept or to pay', async () => {
    const order = await orderOfQuote(quoteId);
    const ref = await orderRefOf(order?.id as number);
    await shop.goto(`/account/quote/${quoteId}`);
    await expect(shop.getByTestId('quote-state')).toHaveAttribute('data-state', 'ordered');
    const link = shop.getByTestId('quote-order').locator('a');
    await expect(link).toBeVisible();
    await expect(link).toContainText(ref);
    await expect(shop.getByTestId('quote-accept')).toHaveCount(0);
    await expect(shop.getByTestId('quote-resume')).toHaveCount(0);
    await capture(shop, 'quote-24-buyer-quote-with-order');
    await Promise.all([shop.waitForURL(new RegExp(`/account/order/${order?.id}`)), link.click()]);
    await expect(shop.locator('main')).toContainText(ref);
  });

  test('the sheet of the quote in the back-office carries the order', async () => {
    await openQuoteSheet(admin, quoteId);
    const order = await orderOfQuote(quoteId);
    await expect(admin.getByTestId('quote-state')).toHaveAttribute('data-state', 'ordered');
    await expect(admin.getByTestId('quote-order')).toBeVisible();
    await expect(admin.getByTestId('quote-order')).toContainText(await orderRefOf(order?.id as number));
    expect(await admin.getByTestId('quote-order').locator('a').count(), 'link to the order').toBeGreaterThan(0);
    await capture(admin, 'quote-15-admin-ordered');
  });
});
