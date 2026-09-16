import { expect, test } from '../fixtures/customer';
import { addCurrentProductToCart, gotoCart, gotoProduct } from '../helpers/cart';
import {
  acceptMandatoryConsents,
  clickNextOrder,
  gotoCheckoutDelivery,
  gotoCheckoutPayment,
  selectFirstDeliveryMethod,
  selectFirstInvoiceAddress,
  selectPaymentByLabel,
} from '../helpers/checkout';
import { ddevMysql } from '../helpers/db';
import { ensureCustomDeliveryConfigured } from '../helpers/delivery';

test.describe('Checkout', () => {
  test.beforeAll(async () => {
    await ensureCustomDeliveryConfigured();
  });

  test('full happy path: cart → delivery → payment → confirm with Cheque', async ({ authedPage, freshCustomer }) => {
    await gotoProduct(authedPage, 'horatio');
    await addCurrentProductToCart(authedPage);

    await gotoCart(authedPage);
    await clickNextOrder(authedPage, /\/checkout\/delivery/);

    // The default address is auto-selected; just pick any delivery method.
    await selectFirstDeliveryMethod(authedPage);
    await clickNextOrder(authedPage, /\/checkout\/payment/);

    await selectFirstInvoiceAddress(authedPage);
    await selectPaymentByLabel(authedPage, /cheque|check/i);
    // The payment step asks for the shop's mandatory consents before it lets the order
    // through — the happy path has to answer them like a buyer would.
    await acceptMandatoryConsents(authedPage);
    // /checkout/pay renders the checkout-confirm template directly when the gateway
    // returns no redirect (Cheque just returns null), so the URL may not change away from /pay.
    await clickNextOrder(authedPage, /\/checkout\/(pay|confirm|invoice)/);

    // Whatever the URL, the confirm page advertises the order placement.
    await expect(authedPage.locator('body')).toContainText(/thank|merci|payment|order/i, { timeout: 15_000 });

    // And the order must exist in the database.
    const orderCount = await ddevMysql(`SELECT COUNT(*) FROM \`order\` o JOIN customer c ON c.id = o.customer_id WHERE c.email = '${freshCustomer.email}'`);
    expect(Number(orderCount)).toBeGreaterThan(0);
  });

  test('cannot proceed past delivery if no delivery method is selected', async ({ authedPage }) => {
    await gotoProduct(authedPage, 'horatio');
    await addCurrentProductToCart(authedPage);

    await gotoCheckoutDelivery(authedPage);

    // No delivery method picked yet. `Organisms:NextButton` only passes an href once the
    // step is settled, and `Molecules:Button:Base` switches to <a> only when it gets one —
    // so an invalid step renders a disabled <button> and no link at all.
    // Asserted rather than guarded on: the previous `if (count > 0)` form made this test
    // pass without checking anything, since the missing link is exactly the expected state.
    const nextButton = authedPage.locator('[data-live-name-value="Organisms:NextButton:Base"]');
    await expect(nextButton).toBeVisible();
    await expect(nextButton.locator('a')).toHaveCount(0);
    await expect(nextButton.locator('button')).toBeDisabled();
  });

  test('cannot reach payment with an empty cart', async ({ authedPage }) => {
    // Cart is empty for a freshly registered customer.
    await authedPage.goto('/checkout/payment');
    // Server should redirect to the cart page (which displays an "empty" state).
    await expect(authedPage).toHaveURL(/\/checkout\/cart|\/$/);
  });
});
