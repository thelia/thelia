import { mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { expect, test, type BrowserContext, type Cookie, type Locator, type Page } from '@playwright/test';
import { auditPage } from '../helpers/a11y';
import { addCurrentProductToCart, gotoCart, gotoProduct } from '../helpers/cart';
import { registerMember, signedInContext } from '../helpers/customer-group';
import { login, type Customer } from '../helpers/customer';
import { expectBlocksLaidOut } from '../helpers/layout';
import {
  ADD_TO_CART,
  PRODUCTS,
  emptyCartOf,
  priceState,
  purgePriceListData,
  remainingPriceListRows,
  setShopPriceDisplayMode,
  shopPriceDisplayMode,
  type PriceDisplayMode,
} from '../helpers/price-list';

/**
 * The three displays of the prices on a phone (375 px): prices visible, prices for identified
 * customers only, prices on request.
 *
 * For each of them, on the product sheet, the listing and the cart, and for a visitor and for a signed-in
 * customer:
 *  - the price block, the invitation to sign in or to ask, the purchase button and the cart message are
 *    not cut, not off the screen, not covered and do not overlap (bounding boxes, `helpers/layout.ts`);
 *  - the card of the product and the product sheet pass the axe scan on the WCAG 2.1 A and AA rules.
 *
 * Data: the shop setting `price_display_mode` (written through the console, put back to `visible` after
 * each block and by the purge whatever happened), one customer (e2e-cg- prefix), one visitor context per
 * block. Set CAPTURE_DIR to a folder to also write a cropped capture of each block checked.
 */
const OWEN = PRODUCTS.owen;

type Display = {
  mode: PriceDisplayMode;
  title: string;
  /** What a visitor and a customer are told in place of, or beside, the price. */
  visitorState: 'price' | 'hidden' | 'on_request';
  customerState: 'price' | 'hidden' | 'on_request';
  /** The line of the cart is taken away from the buyers who may no longer be sold the product. */
  visitorCartKeepsLine: boolean;
  customerCartKeepsLine: boolean;
  /** The customer signs in once the shop has switched, with the cart of the visitor they were. */
  customerSignsInAfterSwitch: boolean;
};

const DISPLAYS: Display[] = [
  { mode: 'visible', title: 'prices visible', visitorState: 'price', customerState: 'price', visitorCartKeepsLine: true, customerCartKeepsLine: true, customerSignsInAfterSwitch: false },
  { mode: 'logged_in', title: 'prices for identified customers', visitorState: 'hidden', customerState: 'price', visitorCartKeepsLine: false, customerCartKeepsLine: true, customerSignsInAfterSwitch: false },
  { mode: 'on_request', title: 'prices on request', visitorState: 'on_request', customerState: 'on_request', visitorCartKeepsLine: false, customerCartKeepsLine: false, customerSignsInAfterSwitch: true },
];

const MESSAGE_BY_STATE = {
  price: null,
  hidden: /log in to see the prices/i,
  on_request: /price on request/i,
} as const;

/**
 * A cropped capture of a block, when CAPTURE_DIR is set. Taken from the screen rather than with the
 * element screenshot: the theme scrolls smoothly, and a capture started during the scroll lands elsewhere.
 */
async function capture(target: Locator, name: string): Promise<void> {
  const directory = process.env.CAPTURE_DIR;
  if (!directory) return;
  mkdirSync(directory, { recursive: true });
  const page = target.page();
  await page.addStyleTag({ content: '.sf-toolbar, [id^="sfwdt"], .sf-minitoolbar { display: none !important; }' });
  await target.evaluate((element) => element.scrollIntoView({ block: 'center', behavior: 'instant' }));
  await page.waitForTimeout(400);
  const box = await target.boundingBox();
  const viewport = page.viewportSize();
  if (!box || !viewport || box.height > viewport.height - 120) {
    await target.screenshot({ path: join(directory, `${name}.png`), animations: 'disabled' });
    return;
  }
  const scrolled = await page.evaluate(() => window.scrollY);
  await page.screenshot({ path: join(directory, `${name}.png`), fullPage: true, clip: { x: 0, y: box.y + scrolled, width: viewport.width, height: box.height } });
}

test.describe('Price lists: the displays of the prices on a phone', () => {
  test.describe.configure({ mode: 'serial' });

  let customer: Customer;

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(180_000);
    await purgePriceListData();
    customer = await registerMember(browser, null);
  });

  test.afterAll(async () => {
    await purgePriceListData();
    expect(shopPriceDisplayMode()).toBe('visible');
    expect(await remainingPriceListRows()).toEqual({ lists: 0, lines: 0, assignments: 0, restrictions: 0, overrides: 0, customers: 0, shopMode: 0 });
  });

  for (const display of DISPLAYS) {
    test.describe(`${display.title} (${display.mode})`, () => {
      let visitorContext: BrowserContext;
      let visitorPage: Page;
      let customerContext: BrowserContext;
      let customerPage: Page;

      // Two visitors put the armchair in their cart while the prices are visible, then the shop switches
      // to the display under test and they come back with the cookie of their cart: one stays a visitor, the
      // other signs in as the customer. That is when the shop reads a cart against the display: a cart kept
      // in a session that is already open is not read again, so each buyer gets a fresh browser.
      test.beforeAll(async ({ browser }) => {
        test.setTimeout(180_000);
        const viewport = test.info().project.use.viewport ?? { width: 375, height: 812 };
        setShopPriceDisplayMode('visible');
        await emptyCartOf(customer.email);

        // The cookie that carries a cart from one visit to the next, taken after the buyer put the armchair in.
        const bringTheCart = async (): Promise<Cookie[]> => {
          const context = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport });
          const page = await context.newPage();
          await gotoProduct(page, OWEN.slug);
          await addCurrentProductToCart(page);
          const cookies = (await context.cookies()).filter((cookie) => cookie.name === 'thelia_cart');
          expect(cookies, 'a cart has no cookie').toHaveLength(1);
          await context.close();
          return cookies;
        };
        const visitorCart = await bringTheCart();
        const customerCart = await bringTheCart();

        customerContext = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport });
        await customerContext.addCookies(customerCart);
        customerPage = await customerContext.newPage();
        // Signed in before the switch, the customer is entitled to what the display keeps for them and
        // their cart stays as it is; signed in after, on a display that closes the product to them, the
        // cart of the visitor they were is read against it.
        if (!display.customerSignsInAfterSwitch) await login(customerPage, customer.email, customer.password);

        setShopPriceDisplayMode(display.mode);

        visitorContext = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport });
        await visitorContext.addCookies(visitorCart);
        visitorPage = await visitorContext.newPage();

        if (display.customerSignsInAfterSwitch) await login(customerPage, customer.email, customer.password);
      });

      test.afterAll(async () => {
        await visitorContext?.close();
        await customerContext?.close();
        setShopPriceDisplayMode('visible');
      });

      // First of the block: the cart is read once, the message of a line taken away is shown once.
      for (const who of ['visitor', 'customer'] as const) {
        test(`cart of a ${who}`, async () => {
          const page = who === 'visitor' ? visitorPage : customerPage;
          const keepsLine = who === 'visitor' ? display.visitorCartKeepsLine : display.customerCartKeepsLine;
          await gotoCart(page);
          const cart = page.locator('[data-live-name-value="Organisms:Cart:Base"]');
          if (keepsLine) {
            await expect(cart.locator('.CartItem')).toHaveCount(1);
            await expect(cart.locator('.CartItem-price')).toContainText(/\d/);
          } else {
            await expect(cart.locator('.CartItem')).toHaveCount(0);
            await expect(page.locator('.SnackBar--error')).toBeVisible();
          }
          await expectBlocksLaidOut(page, '[data-live-name-value="Organisms:Cart:Base"], .SnackBar', `cart of a ${who}, ${display.mode}`);
          await capture(page.locator('main').first(), `cart-${display.mode}-${who}`);
        });
      }

      for (const who of ['visitor', 'customer'] as const) {
        test(`product sheet for a ${who}`, async () => {
          const page = who === 'visitor' ? visitorPage : customerPage;
          const state = who === 'visitor' ? display.visitorState : display.customerState;
          // No purchase form when the product cannot be bought: the sheet itself is what is read.
          await page.goto(`/${OWEN.slug}.html`);
          await expect(priceState(page)).toHaveAttribute('data-price-state', state);
          const message = MESSAGE_BY_STATE[state];
          if (message) await expect(page.locator('.PseSelector')).toContainText(message);
          if (state === 'price') {
            await expect(page.locator(ADD_TO_CART).first()).toBeVisible();
          } else {
            await expect(page.locator(ADD_TO_CART)).toHaveCount(0);
            await expect(page.locator(state === 'hidden' ? '[data-testid="price-login"]' : '[data-testid="price-request"]').first()).toBeVisible();
          }
          await expectBlocksLaidOut(page, '.PseSelector', `product sheet for a ${who}, ${display.mode}`, ['.MobileDrawer']);
          await capture(page.locator('.PseSelector').first(), `sheet-${display.mode}-${who}`);
        });

        test(`listing for a ${who}`, async () => {
          const page = who === 'visitor' ? visitorPage : customerPage;
          const state = who === 'visitor' ? display.visitorState : display.customerState;
          await page.goto(OWEN.listing);
          const card = page.locator('.ProductCard').filter({ has: page.locator(`a.ProductCard-title[href$="/${OWEN.slug}.html"]`) }).first();
          await expect(card).toBeVisible();
          await expect(card.locator(`[data-price-state="${state}"]`)).toBeVisible();
          await expectBlocksLaidOut(page, '.ProductCard', `listing for a ${who}, ${display.mode}`, ['.ProductCard-imgLink']);
          await capture(card, `card-${display.mode}-${who}`);
        });
      }

      // The words of the invitations, in the language of the people they are for: longer than the English ones.
      for (const who of ['visitor', 'customer'] as const) {
        test(`in French: product sheet and listing for a ${who}`, async () => {
          const page = who === 'visitor' ? visitorPage : customerPage;
          const state = who === 'visitor' ? display.visitorState : display.customerState;
          const words = { price: null, hidden: /connectez-vous pour voir les prix/i, on_request: /prix sur demande/i }[state];
          try {
            await page.goto(`/${OWEN.slug}.html?lang=fr_FR`);
            await expect(priceState(page)).toHaveAttribute('data-price-state', state);
            if (words) await expect(page.locator('.PseSelector')).toContainText(words);
            if (state === 'on_request') await expect(page.locator('[data-testid="price-request"]').first()).toContainText(/demander un tarif/i);
            await expectBlocksLaidOut(page, '.PseSelector', `product sheet in French for a ${who}, ${display.mode}`, ['.MobileDrawer']);
            await capture(page.locator('.PseSelector').first(), `sheet-fr-${display.mode}-${who}`);

            await page.goto(`${OWEN.listing}?lang=fr_FR`);
            await expect(page.locator('.ProductCard [data-price-state]').first()).toBeVisible();
            await expectBlocksLaidOut(page, '.ProductCard', `listing in French for a ${who}, ${display.mode}`, ['.ProductCard-imgLink']);
          } finally {
            await page.goto('/?lang=en_US');
          }
        });
      }

      // WCAG 1.4.10: a 1280 px screen zoomed to 400 % is 320 CSS pixels wide, and nothing may be lost there.
      test('at 320 pixels: product sheet and listing for a visitor', async () => {
        const page = visitorPage;
        const original = page.viewportSize() ?? { width: 375, height: 812 };
        try {
          await page.setViewportSize({ width: 320, height: 640 });
          await page.goto(`/${OWEN.slug}.html`);
          await expectBlocksLaidOut(page, '.PseSelector', `product sheet at 320 px for a visitor, ${display.mode}`, ['.MobileDrawer']);
          await page.goto(OWEN.listing);
          await expect(page.locator('.ProductCard [data-price-state]').first()).toBeVisible();
          await expectBlocksLaidOut(page, '.ProductCard', `listing at 320 px for a visitor, ${display.mode}`, ['.ProductCard-imgLink']);
        } finally {
          await page.setViewportSize(original);
        }
      });

      for (const who of ['visitor', 'customer'] as const) {
        test(`axe: card and product sheet for a ${who}`, async ({}, testInfo) => {
          const page = who === 'visitor' ? visitorPage : customerPage;
          await page.goto(OWEN.listing);
          await expect(page.locator('.ProductCard [data-price-state]').first()).toBeVisible();
          const card = await auditPage(page, `card-${display.mode}-${who}`, testInfo, [], ['.ProductCard']);
          expect.soft(card.violations, `axe rules violated on the cards, ${display.mode}, ${who}`).toBe(0);

          await page.goto(`/${OWEN.slug}.html`);
          await expect(page.locator('.PseSelector [data-price-state]').first()).toBeVisible();
          const sheet = await auditPage(page, `sheet-${display.mode}-${who}`, testInfo, [], ['main']);
          expect.soft(sheet.violations, `axe rules violated on the product sheet, ${display.mode}, ${who}`).toBe(0);
        });
      }
    });
  }
});
