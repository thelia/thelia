import { expect, test } from '@playwright/test';
import {
  LTR_LANG,
  MOBILE_VIEWPORT,
  RTL_LANG,
  WIDE_VIEWPORT,
  expectNoHorizontalOverflow,
  expectReadsInOrder,
  headerLogoCenter,
  priceReadingOrders,
  readingOrders,
  shopStops,
  stopNamed,
} from '../helpers/rtl';

/**
 * The buying journey walked in Arabic, then replayed in French.
 *
 * Arabic is served without rewritten URLs, so every stop but the home page is
 * discovered from the links the shop itself renders — see `helpers/rtl.ts`.
 */
test.describe('Right-to-left', () => {
  test('the journey is served dir="rtl" in Arabic and dir="ltr" in French', async ({ page }) => {
    for (const [lang, direction] of [[RTL_LANG, 'rtl'], [LTR_LANG, 'ltr']] as const) {
      for (const stop of await shopStops(page, lang)) {
        await page.goto(stop.url);
        await expect(page.locator('html'), `${stop.name} in ${lang}`).toHaveAttribute('dir', direction);
        await expect(page.locator('html'), `${stop.name} in ${lang}`).toHaveAttribute('lang', new RegExp(lang));
      }
    }
  });

  test('no stop of the journey scrolls horizontally, wide or mobile', async ({ page }) => {
    for (const viewport of [WIDE_VIEWPORT, MOBILE_VIEWPORT]) {
      await page.setViewportSize(viewport);

      for (const lang of [RTL_LANG, LTR_LANG]) {
        for (const stop of await shopStops(page, lang)) {
          await page.goto(stop.url);
          await expectNoHorizontalOverflow(page, `${stop.name} in ${lang} at ${viewport.width}px`);
        }
      }
    }
  });

  test('the header logo sits on the opposite side in Arabic and in French', async ({ page }) => {
    await page.setViewportSize(WIDE_VIEWPORT);
    const middle = WIDE_VIEWPORT.width / 2;

    await page.goto(`/?lang=${RTL_LANG}`);
    const inArabic = await headerLogoCenter(page);

    await page.goto(`/?lang=${LTR_LANG}`);
    const inFrench = await headerLogoCenter(page);

    expect(inArabic, `the logo should hug the right edge in Arabic, found at x=${inArabic}`).toBeGreaterThan(middle);
    expect(inFrench, `the logo should hug the left edge in French, found at x=${inFrench}`).toBeLessThan(middle);
  });

  test('prices keep their left-to-right reading order inside Arabic text', async ({ page }) => {
    const stops = await shopStops(page, RTL_LANG);

    // The cart needs a line to show any amount, and the button is taken as the form
    // declares it rather than by a class the Button component may rename.
    await page.goto(stopNamed(stops, 'product'));
    await page.locator('form[name="thelia_cart_add"] button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle').catch(() => {});

    for (const name of ['product', 'cart']) {
      await page.goto(stopNamed(stops, name));
      await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

      const prices = await priceReadingOrders(page);
      expect(prices.length, `no price found on the ${name} page in Arabic`).toBeGreaterThan(0);
      for (const price of prices) {
        expectReadsInOrder(price, `the price on the ${name} page in Arabic`);
      }
    }
  });

  test('the store phone number keeps its left-to-right reading order in Arabic', async ({ page }) => {
    await page.goto(`/?lang=${RTL_LANG}`);

    const [phone] = await readingOrders(page, '.Footer-storePhone a');
    expect(phone, 'no store phone number in the footer').toBeDefined();
    expect(phone.logical, 'the store phone number is empty').toMatch(/\d/);
    expectReadsInOrder(phone, 'the store phone number in Arabic');
  });
});
