import { expect, test } from '../fixtures/customer';
import { auditedPaths, auditPage, expectContentFitsViewport, gotoAuditedPath, tabTo } from '../helpers/a11y';
import { addCurrentProductToCart, gotoCart, gotoProduct } from '../helpers/cart';
import {
  acceptMandatoryConsents,
  clickNextOrder,
  expectOrderPlaced,
  ORDER_PLACED_URL,
  selectFirstDeliveryMethod,
  selectFirstInvoiceAddress,
  selectPaymentByLabel,
} from '../helpers/checkout';
import { ensureCustomDeliveryConfigured } from '../helpers/delivery';

test.describe('Accessibility (RGAA / WCAG 2.1 AA)', () => {
  test('signed-out forms', async ({ page }, testInfo) => {
    for (const path of ['/customer/login', '/customer/register']) {
      await page.goto(path);
      await auditPage(page, path, testInfo);
    }
  });

  test('pages of the shipped audit sample', async ({ authedPage }, testInfo) => {
    for (const path of auditedPaths()) {
      await gotoAuditedPath(authedPage, path);
      await auditPage(authedPage, path, testInfo);
    }
  });

  test('checkout tunnel up to the confirmation', async ({ authedPage }, testInfo) => {
    await ensureCustomDeliveryConfigured();
    await gotoProduct(authedPage, 'horatio');
    await addCurrentProductToCart(authedPage);

    await gotoCart(authedPage);
    await auditPage(authedPage, 'cart', testInfo);
    await clickNextOrder(authedPage, /\/checkout\/delivery/);

    await selectFirstDeliveryMethod(authedPage);
    await auditPage(authedPage, 'delivery', testInfo);
    await clickNextOrder(authedPage, /\/checkout\/payment/);

    await selectFirstInvoiceAddress(authedPage);
    await selectPaymentByLabel(authedPage, /cheque|check/i);
    await acceptMandatoryConsents(authedPage);
    await auditPage(authedPage, 'payment', testInfo);
    await clickNextOrder(authedPage, ORDER_PLACED_URL);

    await expectOrderPlaced(authedPage);
    await auditPage(authedPage, 'confirmation', testInfo);
  });

  // RGAA 7.3 and 12.9: the whole purchase with Tab, Enter and Space, no pointer.
  test('an order can be placed with the keyboard alone', async ({ authedPage: page }) => {
    await ensureCustomDeliveryConfigured();
    await gotoProduct(page, 'horatio');
    await tabTo(page, page.locator('form[name="thelia_cart_add"] button[type="submit"]'), 'the add to cart button');
    await page.keyboard.press('Enter');
    await page.waitForLoadState('networkidle').catch(() => {});

    await gotoCart(page);
    await tabTo(page, page.locator('a:has-text("Order")').last(), 'the order link of the cart');
    await Promise.all([page.waitForURL(/\/checkout\/delivery/), page.keyboard.press('Enter')]);

    await tabTo(page, page.locator('button[data-live-event-param="SET_DELIVERY_MODULE_OPTION"]'), 'a delivery method');
    await page.keyboard.press('Enter');
    await tabTo(page, page.locator('a:has-text("Order")').last(), 'the order link of the delivery step');
    await Promise.all([page.waitForURL(/\/checkout\/payment/), page.keyboard.press('Enter')]);

    const invoiceAddress = page.locator('button[data-live-event-param="SET_INVOICE_ORDER_ADDRESS_ID"]');
    if (await invoiceAddress.count() > 0) {
      await tabTo(page, invoiceAddress, 'an invoice address');
      await page.keyboard.press('Enter');
    }
    const cheque = page.locator('.PaymentCard').filter({ hasText: /cheque|check/i }).locator('button[data-live-event-param="SET_PAYMENT_MODULE_ID"]');
    await tabTo(page, cheque, 'the cheque payment');
    await page.keyboard.press('Enter');
    for (const consent of await page.locator('input[id^="consent-"][required]').all()) {
      await tabTo(page, consent, 'a mandatory consent');
      await page.keyboard.press('Space');
      await expect(consent).toBeChecked();
    }
    await tabTo(page, page.locator('a:has-text("Order")').last(), 'the order link of the payment step');
    await Promise.all([page.waitForURL(ORDER_PLACED_URL), page.keyboard.press('Enter')]);

    await expectOrderPlaced(page);
  });

  // The checks must be able to fail: an audited URL that is gone, and an audit that measured nothing.
  test('a page of the sample that is gone fails the audit', async ({ page }) => {
    test.fail();
    await gotoAuditedPath(page, '/no-such-page-a11y.html');
  });

  test('an audit that checked no rule fails', async ({ page }, testInfo) => {
    test.fail();
    await page.goto('/');
    await auditPage(page, 'home with the body excluded', testInfo, ['body']);
  });

  // RGAA 10.11: 320 CSS pixels wide is a 1280 pixel screen zoomed to 400 %, so it covers 200 % too.
  test('pages reflow at 320 pixels without scrolling sideways', async ({ authedPage }) => {
    await authedPage.setViewportSize({ width: 320, height: 640 });
    for (const path of auditedPaths()) {
      await authedPage.goto(path);
      await authedPage.waitForLoadState('networkidle').catch(() => {});
      await expectContentFitsViewport(authedPage, path);
    }
  });

  test('the first tab stop skips to the main content', async ({ page }) => {
    await page.goto('/');
    await page.keyboard.press('Tab');
    const skipLink = page.locator(':focus');
    await expect(skipLink).toHaveAttribute('href', '#main-content');
    await expect(skipLink).toBeVisible();

    await page.keyboard.press('Enter');
    await expect(page.locator('#main-content')).toBeFocused();
  });
});
