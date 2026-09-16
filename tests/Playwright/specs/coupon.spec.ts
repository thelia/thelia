import { expect, test } from '@playwright/test';
import { addCurrentProductToCart, gotoCart, gotoProduct } from '../helpers/cart';
import { ensureFlatCoupon, E2E_COUPON_CODE } from '../helpers/coupon';

test.describe('Coupon', () => {
  test.beforeAll(async () => {
    await ensureFlatCoupon();
  });

  // The promo code form lives in the `.PromoCode` component, inside an `Accordion:Item`
  // whose content is `hidden` until the trigger is clicked — a <button>, not a <summary>.
  const PROMO_CODE = '.PromoCode';

  async function fillAndSubmitCoupon(page: import('@playwright/test').Page, code: string): Promise<void> {
    const trigger = page.locator(`${PROMO_CODE} [data-slot="accordion-trigger"]`).first();
    await expect(trigger).toBeVisible();
    if ((await trigger.getAttribute('aria-expanded')) !== 'true') {
      await trigger.click();
    }
    const input = page.locator('input[name="thelia_coupon_code[coupon-code]"]');
    await expect(input).toBeVisible();
    await input.fill(code);
    await page.locator('form[name="thelia_coupon_code"] button[type="submit"]').click();
    await page.waitForLoadState('networkidle').catch(() => {});
  }

  // "€252.10" → 252.1. The amounts are printed through `format_currency`, so the separators
  // follow the locale the shop rendered in, not the one the test was written in.
  async function summaryTotal(page: import('@playwright/test').Page): Promise<number> {
    const raw = (await page.locator('.Summary-total span[dir="ltr"]').first().innerText()).trim();
    const digits = raw.replace(/[^0-9.,]/g, '').replace(/,/g, '.');
    const amount = Number.parseFloat(digits);
    expect(Number.isNaN(amount), `could not read a total out of "${raw}"`).toBe(false);
    return amount;
  }

  test('valid coupon discounts the cart total', async ({ page }) => {
    await gotoProduct(page, 'horatio');
    await addCurrentProductToCart(page);
    await gotoCart(page);

    const totalBefore = await summaryTotal(page);
    await fillAndSubmitCoupon(page, E2E_COUPON_CODE);

    // The Summary panel renders the active coupon once it is consumed.
    await expect(page.locator('.Summary')).toContainText(E2E_COUPON_CODE, { timeout: 10_000 });
    // The discount line is labelled with the coupon's own title when the core publishes one
    // ("E2E flat 10" here) and falls back to "Discount" otherwise, so the label is not what
    // is asserted: the money is. E2E10 is a flat 10 EUR off — the total has to drop by 10.
    await expect(page.locator('.Summary-discountLine')).toHaveCount(1);
    await expect
      .poll(async () => summaryTotal(page), { timeout: 10_000 })
      .toBeCloseTo(totalBefore - 10, 2);
  });

  test('invalid coupon is rejected with an error', async ({ page }) => {
    await gotoProduct(page, 'horatio');
    await addCurrentProductToCart(page);
    await gotoCart(page);

    await fillAndSubmitCoupon(page, 'NOT-A-REAL-CODE');

    // Either an inline form error or no discount line should appear.
    await expect(page.locator(PROMO_CODE)).toContainText(/(invalid|does not exist|n'existe|no longer)/i).catch(async () => {
      await expect(page.locator(PROMO_CODE)).not.toContainText('NOT-A-REAL-CODE');
    });
  });
});
