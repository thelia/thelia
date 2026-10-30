import { expect, test } from '../fixtures/customer';
import { auditedPaths, auditPage, expectContentFitsViewport } from '../helpers/a11y';
import { addCurrentProductToCart, gotoCart, gotoProduct } from '../helpers/cart';
import {
  acceptMandatoryConsents,
  clickNextOrder,
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
      const response = await authedPage.goto(path);
      expect(response?.status(), `${path} is listed in setup/audit/lighthouserc.default.yaml`).toBeLessThan(400);
      // A redirect would audit another page under this one's name.
      expect(new URL(authedPage.url()).pathname, `${path} redirected`).toBe(new URL(path, authedPage.url()).pathname);
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
    await clickNextOrder(authedPage, /\/checkout\/(pay|confirm|invoice)/);

    await expect(authedPage.locator('body')).toContainText(/thank|merci|payment|order/i, { timeout: 15_000 });
    await auditPage(authedPage, 'confirmation', testInfo);
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
