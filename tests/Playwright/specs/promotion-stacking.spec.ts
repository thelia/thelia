import { expect, test, type Page } from '@playwright/test';
import { loginAdmin } from '../helpers/admin';
import { addCurrentProductToCart, gotoCart, gotoProduct } from '../helpers/cart';
import { ddevMysql } from '../helpers/db';

const PREFIX = 'E2E-PSTK-';
const CODE_A = `${PREFIX}A`;
const CODE_B = `${PREFIX}B`;
const CAPTURE_DIR = process.env.R1_CAPTURE_DIR;

type Promotion = {
  code: string;
  percentage?: number;
  amount?: number;
  priority: number;
  cumulative: boolean;
  stop?: boolean;
};

function titleOf(code: string): string {
  return `Title ${code}`;
}

async function createPromotion(promotion: Promotion): Promise<number> {
  const effects = JSON.stringify(
    promotion.percentage !== undefined ? { percentage: promotion.percentage } : { amount: promotion.amount },
  ).replace(/'/g, "''");
  const conditions = Buffer.from(JSON.stringify([
    { conditionServiceId: 'thelia.condition.match_for_everyone', operators: {}, values: {} },
  ])).toString('base64');
  const type = promotion.percentage !== undefined
    ? 'thelia.coupon.type.remove_x_percent'
    : 'thelia.coupon.type.remove_x_amount';
  await ddevMysql(`
    INSERT INTO coupon (code, type, serialized_effects, is_enabled, expiration_date, max_usage, is_cumulative,
                        is_removing_postage, is_available_on_special_offers, is_used, serialized_conditions,
                        per_customer_usage_count, priority, stop_processing, created_at, updated_at)
    VALUES ('${promotion.code}', '${type}', '${effects}', 1, DATE_ADD(NOW(), INTERVAL 1 YEAR), -1,
            ${promotion.cumulative ? 1 : 0}, 0, 1, 0, '${conditions}', 0, ${promotion.priority},
            ${promotion.stop ? 1 : 0}, NOW(), NOW())
  `);
  const id = Number(await ddevMysql(`SELECT id FROM coupon WHERE code = '${promotion.code}'`));
  await ddevMysql(`
    INSERT INTO coupon_i18n (id, locale, title, short_description, description) VALUES
      (${id}, 'en_US', '${titleOf(promotion.code)}', 's', 'd'),
      (${id}, 'fr_FR', '${titleOf(promotion.code)}', 's', 'd')
  `);
  return id;
}

async function excludeEachOther(first: number, second: number): Promise<void> {
  await ddevMysql(`INSERT INTO coupon_exclusion (coupon_id, excluded_coupon_id) VALUES (${first}, ${second}), (${second}, ${first})`);
}

async function cleanUp(): Promise<void> {
  await ddevMysql(`DELETE FROM coupon_exclusion WHERE coupon_id IN (SELECT id FROM coupon WHERE code LIKE '${PREFIX}%')
    OR excluded_coupon_id IN (SELECT id FROM coupon WHERE code LIKE '${PREFIX}%')`);
  await ddevMysql(`DELETE FROM coupon_i18n WHERE id IN (SELECT id FROM coupon WHERE code LIKE '${PREFIX}%')`);
  await ddevMysql(`DELETE FROM coupon WHERE code LIKE '${PREFIX}%'`);
}

async function newCart(page: Page): Promise<void> {
  await gotoProduct(page, 'horatio');
  await addCurrentProductToCart(page);
  await gotoCart(page);
}

async function submitCode(page: Page, code: string): Promise<void> {
  const promo = page.locator('.PromoCode');
  const trigger = promo.locator('[data-slot="accordion-trigger"]').first();
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

function discountLabels(page: Page): Promise<string[]> {
  return page.locator('.Summary-discountLine').evaluateAll((nodes) =>
    nodes.map((node) => node.getAttribute('data-summary-discount-label') ?? ''),
  );
}

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow, 'horizontal overflow in px').toBeLessThanOrEqual(0);
}

test.describe('Promotion stacking, cart summary', () => {
  test.beforeAll(cleanUp);
  test.afterAll(cleanUp);
  test.afterEach(cleanUp);

  test('incompatible codes: one discount, the set-aside code is named, whatever the entry order', async ({ page }) => {
    const first = await createPromotion({ code: CODE_A, percentage: 10, priority: 10, cumulative: true });
    const second = await createPromotion({ code: CODE_B, amount: 5, priority: 20, cumulative: true });
    await excludeEachOther(first, second);

    for (const [one, other] of [[CODE_A, CODE_B], [CODE_B, CODE_A]]) {
      await newCart(page);
      await submitCode(page, one);
      await expect(page.locator('.Summary-discountLine')).toHaveCount(1);
      await submitCode(page, other);

      const message = page.locator(`[data-summary-excluded-promotion="${CODE_B}"]:visible`);
      await expect(message).toBeVisible({ timeout: 10_000 });
      await expect(message).toContainText(CODE_B);
      await expect(message).toContainText(titleOf(CODE_A));
      await expect(page.locator('.Summary-discountLine')).toHaveCount(1);
      expect(await discountLabels(page)).toEqual([titleOf(CODE_A)]);
      await page.context().clearCookies();
    }
  });

  test('a promotion that stops the next ones: the later code is announced in favour of it', async ({ page }) => {
    await createPromotion({ code: CODE_A, percentage: 10, priority: 10, cumulative: true, stop: true });
    await createPromotion({ code: CODE_B, amount: 5, priority: 20, cumulative: true });

    await newCart(page);
    await submitCode(page, CODE_A);
    await submitCode(page, CODE_B);

    const message = page.locator(`[data-summary-excluded-promotion="${CODE_B}"]:visible`);
    await expect(message).toBeVisible({ timeout: 10_000 });
    await expect(message).toContainText(titleOf(CODE_A));
    expect(await discountLabels(page)).toEqual([titleOf(CODE_A)]);
  });

  test('two cumulative codes: two discount lines, priority 10 then 20', async ({ page }) => {
    await createPromotion({ code: CODE_A, percentage: 10, priority: 10, cumulative: true });
    await createPromotion({ code: CODE_B, amount: 5, priority: 20, cumulative: true });

    await newCart(page);
    // Entered in the opposite order of the priority: the order shown follows the priority.
    await submitCode(page, CODE_B);
    await submitCode(page, CODE_A);

    await expect(page.locator('.Summary-discountLine')).toHaveCount(2, { timeout: 10_000 });
    expect(await discountLabels(page)).toEqual([titleOf(CODE_A), titleOf(CODE_B)]);
    await expect(page.locator('[data-summary-excluded-promotion]')).toHaveCount(0);
  });

  test('non-regression: a single non-cumulative code applies as before, no message', async ({ page }) => {
    await createPromotion({ code: CODE_A, amount: 5, priority: 0, cumulative: false });

    await newCart(page);
    await submitCode(page, CODE_A);

    await expect(page.locator('.Summary')).toContainText(CODE_A, { timeout: 10_000 });
    await expect(page.locator('.Summary-discountLine')).toHaveCount(1);
    await expect(page.locator('[data-summary-excluded-promotion]')).toHaveCount(0);
  });

  for (const viewport of [{ width: 1440, height: 900 }, { width: 390, height: 844 }]) {
    test(`the message reads at ${viewport.width}px without horizontal overflow`, async ({ page }) => {
      await page.setViewportSize(viewport);
      const first = await createPromotion({ code: CODE_A, percentage: 10, priority: 10, cumulative: true });
      const second = await createPromotion({ code: CODE_B, amount: 5, priority: 20, cumulative: true });
      await excludeEachOther(first, second);

      await newCart(page);
      await submitCode(page, CODE_A);
      await submitCode(page, CODE_B);

      const message = page.locator(`[data-summary-excluded-promotion="${CODE_B}"]:visible`);
      await expect(message).toBeVisible({ timeout: 10_000 });
      await expectNoHorizontalOverflow(page);
      const box = await message.boundingBox();
      expect(box).not.toBeNull();
      expect(box!.x).toBeGreaterThanOrEqual(0);
      expect(box!.x + box!.width).toBeLessThanOrEqual(viewport.width);
      await expect(message).toContainText(titleOf(CODE_A));

      if (CAPTURE_DIR) {
        await message.locator('xpath=ancestor::*[contains(@class,"Summary")][1]').screenshot({
          path: `${CAPTURE_DIR}/cart-summary-${viewport.width}.png`,
        });
      }
    });
  }

  test('captures of the back-office screens', async ({ page }) => {
    test.skip(!CAPTURE_DIR, 'Only when R1_CAPTURE_DIR is set.');
    const first = await createPromotion({ code: CODE_A, percentage: 10, priority: 10, cumulative: true });
    const second = await createPromotion({ code: CODE_B, amount: 5, priority: 20, cumulative: true, stop: true });
    await excludeEachOther(first, second);

    await loginAdmin(page);
    for (const width of [1440, 390]) {
      await page.setViewportSize({ width, height: width === 1440 ? 900 : 844 });
      await page.goto(`/admin/coupon/update/${second}`);
      const block = page.getByTestId('coupon-stacking');
      await expect(block).toBeVisible();
      await block.screenshot({ path: `${CAPTURE_DIR}/bo-coupon-stacking-${width}.png` });
    }
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/admin/coupon?order=priority&direction=ASC');
    const table = page.locator('[data-testid="coupons-page"] table').first();
    await expect(table).toBeVisible();
    await table.screenshot({ path: `${CAPTURE_DIR}/bo-coupon-list-priority-1440.png` });
  });
});
