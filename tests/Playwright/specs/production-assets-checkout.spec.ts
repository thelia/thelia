import { expect, test } from '../fixtures/customer';
import type { Page } from '@playwright/test';
import { addCurrentProductToCart, gotoCart, gotoProduct } from '../helpers/cart';
import {
  acceptMandatoryConsents,
  clickNextOrder,
  selectFirstInvoiceAddress,
  selectPaymentByLabel,
} from '../helpers/checkout';
import { ensureCustomDeliveryConfigured } from '../helpers/delivery';

// Walks the storefront checkout up to the payment step on the assets compiled for production
// (`php bin/console asset-map:compile --env=prod`), on a desktop and a mobile viewport: every
// step must render with the compiled stylesheet, without a console error or a failed asset.
// Without a compiled front manifest the instance serves its development assets, which is not
// what this spec checks.

const FRONT_MANIFEST = '/assets/frontOffice/flexy/manifest.json';
const FRONT_STYLESHEET = /\/assets\/frontOffice\/flexy\/styles\/app-[^/]+\.css$/;

const viewports = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'mobile', width: 390, height: 844 },
];

function collectErrors(page: Page): string[] {
  const errors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(`console: ${message.text()}`);
  });
  page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));
  page.on('response', (response) => {
    if (response.url().includes('/assets/') && response.status() >= 400) {
      errors.push(`asset ${response.status()} ${response.url()}`);
    }
  });
  return errors;
}

async function expectCompiledStylesheet(page: Page): Promise<void> {
  const sheets = await page.evaluate(() =>
    Array.from(document.styleSheets)
      .filter((sheet) => (sheet.href ?? '').includes('/assets/'))
      .map((sheet) => {
        let rules = 0;
        try {
          rules = sheet.cssRules.length;
        } catch {
          rules = 0;
        }
        return { href: sheet.href ?? '', rules };
      }),
  );
  const front = sheets.find((sheet) => FRONT_STYLESHEET.test(new URL(sheet.href).pathname));
  expect(front, `front stylesheet linked on ${page.url()}`).toBeDefined();
  expect(front!.rules, `front stylesheet applied on ${page.url()}`).toBeGreaterThan(50);
}

async function expectMinifiedFrontStylesheet(page: Page): Promise<void> {
  const href = await page
    .locator('link[rel="stylesheet"][href*="/assets/frontOffice/flexy/styles/app-"]')
    .first()
    .getAttribute('href');
  expect(href, 'front stylesheet link').not.toBeNull();
  const response = await page.request.get(href!);
  expect(response.status()).toBe(200);
  const body = await response.text();
  // The production build is minified: a readable Tailwind build of the same theme spreads its
  // rules over thousands of lines.
  const lines = body.split('\n').length;
  expect(lines, `${href} is the minified production build (${body.length} bytes)`).toBeLessThan(
    Math.max(10, body.length / 2000),
  );
}

// Below the `md` breakpoint the delivery card hides its radio and the whole card is the click
// target, as a buyer on a phone uses it.
async function chooseFirstDeliveryCard(page: Page): Promise<void> {
  await page.locator('.DeliveryMode label.DeliveryMode-content').first().click();
  await expect(page.locator('.DeliveryMode.selected').first()).toBeVisible({ timeout: 10_000 });
  await page.waitForLoadState('networkidle').catch(() => {});
}

test.describe('Checkout on the assets compiled for production', () => {
  test.beforeAll(async () => {
    await ensureCustomDeliveryConfigured();
  });

  test.beforeEach(async ({ request }) => {
    const manifest = await request.get(FRONT_MANIFEST);
    test.skip(manifest.status() !== 200, 'Front assets are not compiled: run asset-map:compile --env=prod first.');
  });

  for (const viewport of viewports) {
    test.describe(viewport.name, () => {
      test.use({ viewport: { width: viewport.width, height: viewport.height } });

      test(`product, cart, delivery and payment render styled (${viewport.name})`, async ({ authedPage }) => {
        const errors = collectErrors(authedPage);

        await gotoProduct(authedPage, 'horatio');
        await expectCompiledStylesheet(authedPage);
        await expectMinifiedFrontStylesheet(authedPage);
        await addCurrentProductToCart(authedPage);

        await gotoCart(authedPage);
        await expectCompiledStylesheet(authedPage);
        await expect(authedPage.locator('body')).toContainText(/horatio/i);

        await clickNextOrder(authedPage, /\/checkout\/delivery/);
        await expectCompiledStylesheet(authedPage);
        await chooseFirstDeliveryCard(authedPage);

        await clickNextOrder(authedPage, /\/checkout\/payment/);
        await expectCompiledStylesheet(authedPage);
        await selectFirstInvoiceAddress(authedPage);
        await selectPaymentByLabel(authedPage, /cheque|check/i);
        await acceptMandatoryConsents(authedPage);

        // The payment step is interactive (live components): the order button turns into a link
        // only once the delivery, the address, the payment method and the consents are accepted.
        await expect(authedPage.locator('a:has-text("Order")').last()).toBeVisible({ timeout: 15_000 });
        await authedPage.screenshot({ path: `test-results/production-assets-payment-${viewport.name}.png` });

        expect(errors).toEqual([]);
      });
    });
  }
});
