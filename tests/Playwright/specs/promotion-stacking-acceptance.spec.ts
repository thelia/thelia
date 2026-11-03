import { expect, test, type Locator, type Page } from '@playwright/test';
import { loginAdmin } from '../helpers/admin';
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
import { login } from '../helpers/customer';
import { ddevMysql } from '../helpers/db';
import { ensureCustomDeliveryConfigured } from '../helpers/delivery';

// Project-manager acceptance run of the promotion stacking story (one scenario per criterion).
const PREFIX = 'E2E-CP-';
const A = `${PREFIX}A`;
const B = `${PREFIX}B`;
const C = `${PREFIX}C`;
const CAPTURE_DIR = process.env.R2_CAPTURE_DIR ?? '/tmp/claude-1000/-home-mbruchet-code-osa-modules-thelia/01a7443a-b394-4f27-adb2-cfde6c0b7204/scratchpad/R2';
// bcrypt hash of the test password below (fixture value, test customer only).
const TEST_PASSWORD = 'E2eCp-Test-9!';
const TEST_PASSWORD_HASH = '$2y$10$RApaR20edxXJ724kvm/pAObZuT/sNSHClhOKnrlHAWzrkGO7US0RC';
const CUSTOMER_EMAIL = 'e2e-cp-customer@example.test';

const title = (code: string) => `Title ${code}`;

type Promotion = {
  code: string;
  percentage?: number;
  amount?: number;
  priority: number;
  cumulative: boolean;
  stop?: boolean;
  maxUsage?: number;
};

async function createPromotion(p: Promotion): Promise<number> {
  const effects = JSON.stringify(p.percentage !== undefined ? { percentage: p.percentage } : { amount: p.amount });
  const conditions = Buffer.from(JSON.stringify([
    { conditionServiceId: 'thelia.condition.match_for_everyone', operators: {}, values: {} },
  ])).toString('base64');
  const type = p.percentage !== undefined ? 'thelia.coupon.type.remove_x_percent' : 'thelia.coupon.type.remove_x_amount';
  await ddevMysql(`
    INSERT INTO coupon (code, type, serialized_effects, is_enabled, expiration_date, max_usage, is_cumulative,
                        is_removing_postage, is_available_on_special_offers, is_used, serialized_conditions,
                        per_customer_usage_count, priority, stop_processing, created_at, updated_at)
    VALUES ('${p.code}', '${type}', '${effects}', 1, DATE_ADD(NOW(), INTERVAL 1 YEAR), ${p.maxUsage ?? -1},
            ${p.cumulative ? 1 : 0}, 0, 1, 0, '${conditions}', 0, ${p.priority}, ${p.stop ? 1 : 0}, NOW(), NOW())`);
  const id = Number(await ddevMysql(`SELECT id FROM coupon WHERE code = '${p.code}'`));
  await ddevMysql(`INSERT INTO coupon_i18n (id, locale, title, short_description, description) VALUES
    (${id}, 'en_US', '${title(p.code)}', 's', 'd'), (${id}, 'fr_FR', '${title(p.code)}', 's', 'd')`);
  return id;
}

async function excludeEachOther(first: number, second: number): Promise<void> {
  await ddevMysql(`INSERT INTO coupon_exclusion (coupon_id, excluded_coupon_id) VALUES (${first}, ${second}), (${second}, ${first})`);
}

async function createCustomer(): Promise<number> {
  // A shop whose database carries a mandatory customer group (a schema this branch does not
  // ship) needs one on the row; any other shop has no such column.
  const hasGroupColumn = '1' === (await ddevMysql(
    "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'customer_group_id'",
  )).trim();
  const groupColumn = hasGroupColumn ? ', customer_group_id' : '';
  const groupValue = hasGroupColumn ? `, ${(await ddevMysql('SELECT id FROM customer_group ORDER BY id LIMIT 1')).trim()}` : '';
  await ddevMysql(`
    INSERT INTO customer (title_id, lang_id, ref, firstname, lastname, email, password, algo, reseller, discount, enable,
                          is_guest${groupColumn}, created_at, updated_at, version)
    VALUES (1, 2, '${PREFIX}CUSTOMER', '${PREFIX}Jane', '${PREFIX}Doe', '${CUSTOMER_EMAIL}', '${TEST_PASSWORD_HASH}',
            'PASSWORD_BCRYPT', 0, 0, 1, 0${groupValue}, NOW(), NOW(), 1)`);
  const id = Number(await ddevMysql(`SELECT id FROM customer WHERE email = '${CUSTOMER_EMAIL}'`));
  await ddevMysql(`
    INSERT INTO address (label, customer_id, title_id, firstname, lastname, address1, address2, address3, zipcode, city,
                         country_id, phone, cellphone, is_default, created_at, updated_at)
    VALUES ('${PREFIX}home', ${id}, 1, '${PREFIX}Jane', '${PREFIX}Doe', '12 rue du Test', '', '', '44000', 'Nantes',
            64, '0240000000', '0240000000', 1, NOW(), NOW())`);
  return id;
}

async function cleanUp(): Promise<void> {
  const customers = `(SELECT id FROM customer WHERE email LIKE 'e2e-cp-%')`;
  const orders = `(SELECT id FROM \`order\` WHERE customer_id IN ${customers})`;
  const addresses = (await ddevMysql(`SELECT invoice_order_address_id FROM \`order\` WHERE customer_id IN ${customers}
    UNION SELECT delivery_order_address_id FROM \`order\` WHERE customer_id IN ${customers}`)).split('\n').filter(Boolean);
  await ddevMysql(`DELETE FROM order_coupon_country WHERE coupon_id IN (SELECT id FROM order_coupon WHERE order_id IN ${orders})`);
  await ddevMysql(`DELETE FROM \`order\` WHERE customer_id IN ${customers}`);
  if (addresses.length) await ddevMysql(`DELETE FROM order_address WHERE id IN (${addresses.join(',')})`);
  await ddevMysql(`DELETE FROM customer WHERE email LIKE 'e2e-cp-%'`);
  const ids = `(SELECT id FROM coupon WHERE code LIKE '${PREFIX}%')`;
  await ddevMysql(`DELETE FROM coupon_exclusion WHERE coupon_id IN ${ids} OR excluded_coupon_id IN ${ids}`);
  await ddevMysql(`DELETE FROM coupon_i18n WHERE id IN ${ids}`);
  await ddevMysql(`DELETE FROM coupon WHERE code LIKE '${PREFIX}%'`);
}

async function remaining(): Promise<string> {
  return await ddevMysql(`SELECT
    (SELECT COUNT(*) FROM coupon WHERE code LIKE '${PREFIX}%'),
    (SELECT COUNT(*) FROM customer WHERE email LIKE 'e2e-cp-%'),
    (SELECT COUNT(*) FROM \`order\` o JOIN customer c ON c.id=o.customer_id WHERE c.email LIKE 'e2e-cp-%'),
    (SELECT COUNT(*) FROM coupon_exclusion WHERE coupon_id NOT IN (SELECT id FROM coupon))`);
}

async function newCart(page: Page): Promise<void> {
  await gotoProduct(page, 'horatio');
  await addCurrentProductToCart(page);
  await gotoCart(page);
}

async function submitCode(page: Page, code: string): Promise<void> {
  const trigger = page.locator('.PromoCode [data-slot="accordion-trigger"]').first();
  await expect(trigger).toBeVisible();
  if ((await trigger.getAttribute('aria-expanded')) !== 'true') await trigger.click();
  const input = page.locator('input[name="thelia_coupon_code[coupon-code]"]');
  await expect(input).toBeVisible();
  await input.fill(code);
  await page.locator('form[name="thelia_coupon_code"] button[type="submit"]').click();
  await page.waitForLoadState('networkidle').catch(() => {});
}

const discountLabels = (page: Page) =>
  page.locator('.Summary-discountLine').evaluateAll((n) => n.map((e) => e.getAttribute('data-summary-discount-label') ?? ''));

const width = (page: Page) => page.viewportSize()!.width;

async function noOverflow(page: Page): Promise<void> {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow, 'horizontal overflow in px').toBeLessThanOrEqual(0);
}

async function shot(page: Page, target: Locator, name: string): Promise<void> {
  await target.scrollIntoViewIfNeeded();
  await target.screenshot({ path: `${CAPTURE_DIR}/${name}-${width(page)}.png` });
}

const summary = (page: Page) => page.locator('.Summary:visible').first();

async function createViaBackOffice(page: Page, code: string, o: {
  percentage?: string; amount?: string; mode: 'cumulative' | 'stop' | 'alone'; priority: string; excludedLabel?: string;
}): Promise<void> {
  await page.goto('/admin/coupon/create');
  if (o.percentage) {
    await page.locator('#coupon-type').selectOption('thelia.coupon.type.remove_x_percent');
    await page.locator('input[name="coupon_specific[percentage]"]').fill(o.percentage);
  } else {
    await page.locator('#coupon-type').selectOption('thelia.coupon.type.remove_x_amount');
    await page.locator('input[name="coupon_specific[amount]"]').fill(o.amount ?? '5');
  }
  await page.locator('#coupon-code').fill(code);
  await page.locator('#coupon-title').fill(title(code));
  await page.getByTestId(`coupon-stacking-${o.mode}`).check();
  await page.getByTestId('coupon-priority').fill(o.priority);
  if (o.excludedLabel) await page.getByTestId('coupon-excluded-select').selectOption({ label: o.excludedLabel });
  await page.getByTestId('coupon-save-close').click();
  await expect(page.getByTestId('coupons-page')).toBeVisible();
}

async function openByCode(page: Page, code: string): Promise<void> {
  const id = await ddevMysql(`SELECT id FROM coupon WHERE code = '${code}' LIMIT 1`);
  expect(id, `promotion ${code} must exist`).not.toBe('');
  await page.goto(`/admin/coupon/update/${id}`);
  await expect(page.getByTestId('coupon-stacking')).toBeVisible();
}

test.describe('Acceptance, promotion stacking', () => {
  test.beforeAll(cleanUp);
  test.afterEach(cleanUp);
  test.afterAll(async () => {
    await cleanUp();
    const left = await remaining();
    console.log(`REMAINING E2E-CP rows (coupons, customers, orders, orphan exclusions): ${left.replace(/\s+/g, ' ')}`);
    expect(left.split(/\s+/).map(Number).reduce((a, b) => a + b, 0)).toBe(0);
  });

  test('P1 back-office: three choices, exclusion both ways, list sorted by priority', async ({ page }) => {
    await loginAdmin(page);
    await createViaBackOffice(page, A, { percentage: '10', mode: 'cumulative', priority: '10' });
    await createViaBackOffice(page, B, { amount: '5', mode: 'stop', priority: '20', excludedLabel: `${A} (${title(A)})` });
    await createViaBackOffice(page, C, { amount: '3', mode: 'alone', priority: '30' });

    await openByCode(page, A);
    await expect(page.getByTestId('coupon-stacking-cumulative')).toBeChecked();
    await expect(page.getByTestId('coupon-priority')).toHaveValue('10');
    // Declared on B only, A shows B without anyone touching A.
    const onA = page.getByTestId('coupon-excluded-select').locator('option:checked');
    await expect(onA).toHaveCount(1);
    await expect(onA).toContainText(B);
    await shot(page, page.getByTestId('coupon-stacking'), 'p1-A-exclusions');

    await openByCode(page, B);
    await expect(page.getByTestId('coupon-stacking-stop')).toBeChecked();
    await expect(page.getByTestId('coupon-priority')).toHaveValue('20');
    const onB = page.getByTestId('coupon-excluded-select').locator('option:checked');
    await expect(onB).toHaveCount(1);
    await expect(onB).toContainText(A);
    await shot(page, page.getByTestId('coupon-stacking'), 'p1-B-exclusions');

    await openByCode(page, C);
    await expect(page.getByTestId('coupon-stacking-alone')).toBeChecked();
    await expect(page.getByTestId('coupon-priority')).toHaveValue('30');

    await page.goto('/admin/coupon?order=priority&direction=ASC');
    const rows = page.locator('[data-testid="coupons-page"] tbody tr').filter({ hasText: PREFIX });
    await expect(rows).toHaveCount(3);
    const order = (await rows.evaluateAll((r) => r.map((e) => e.textContent ?? ''))).map((t) => [A, B, C].find((c) => t.includes(c)));
    expect(order).toEqual([A, B, C]);
    await noOverflow(page); // P7 (back-office list)
    await shot(page, page.locator('[data-testid="coupons-page"] table').first(), 'p1-list');
  });

  test('P2 cart: two cumulative promotions, two discounts A then B', async ({ page }) => {
    await createPromotion({ code: A, percentage: 10, priority: 10, cumulative: true });
    await createPromotion({ code: B, amount: 5, priority: 20, cumulative: true });
    await newCart(page);
    await submitCode(page, B); // entered against the priority order on purpose
    await submitCode(page, A);
    await expect(page.locator('.Summary-discountLine')).toHaveCount(2, { timeout: 10_000 });
    expect(await discountLabels(page)).toEqual([title(A), title(B)]);
    await expect(page.locator('[data-summary-excluded-promotion]')).toHaveCount(0);
    await noOverflow(page);
    await shot(page, summary(page), 'p2-summary');
  });

  test('P3 cart: A stops the next ones, B entered first then A: only A, B announced', async ({ page }) => {
    await createPromotion({ code: A, percentage: 10, priority: 10, cumulative: true, stop: true });
    await createPromotion({ code: B, amount: 5, priority: 20, cumulative: true });
    await newCart(page);
    await submitCode(page, B);
    await expect(page.locator('.Summary-discountLine')).toHaveCount(1, { timeout: 10_000 });
    await submitCode(page, A);
    const message = page.locator(`[data-summary-excluded-promotion="${B}"]:visible`);
    await expect(message).toBeVisible({ timeout: 10_000 });
    await expect(message).toContainText(title(A));
    expect(await discountLabels(page)).toEqual([title(A)]);
    await noOverflow(page);
    await shot(page, summary(page), 'p3-summary');
  });

  test('P4 cart: A incompatible with B, either entry order gives the same result', async ({ page }) => {
    const a = await createPromotion({ code: A, percentage: 10, priority: 10, cumulative: true });
    const b = await createPromotion({ code: B, amount: 5, priority: 20, cumulative: true });
    await excludeEachOther(a, b);
    let step = 0;
    for (const [first, second] of [[B, A], [A, B]]) {
      step++;
      await newCart(page);
      await submitCode(page, first);
      await submitCode(page, second);
      const message = page.locator(`[data-summary-excluded-promotion="${B}"]:visible`);
      await expect(message).toBeVisible({ timeout: 10_000 });
      await expect(message).toContainText(title(A));
      expect(await discountLabels(page)).toEqual([title(A)]);
      await noOverflow(page);
      await shot(page, summary(page), `p4-order${step}-summary`);
      await page.context().clearCookies();
    }
  });

  test('P5 cart: previous behaviour, one non-cumulative code at priority 0, no message', async ({ page }) => {
    await createPromotion({ code: A, amount: 5, priority: 0, cumulative: false });
    await newCart(page);
    await submitCode(page, A);
    await expect(page.locator('.Summary')).toContainText(A, { timeout: 10_000 });
    await expect(page.locator('.Summary-discountLine')).toHaveCount(1);
    await expect(page.locator('[data-summary-excluded-promotion]')).toHaveCount(0);
    await noOverflow(page);
    await shot(page, summary(page), 'p5-summary');
  });

  test('P6 order: A kept and B set aside, usage counter of B untouched, A consumed', async ({ page }) => {
    test.setTimeout(180_000);
    await ensureCustomDeliveryConfigured();
    const a = await createPromotion({ code: A, percentage: 10, priority: 10, cumulative: true, maxUsage: 5 });
    const b = await createPromotion({ code: B, amount: 5, priority: 20, cumulative: true, maxUsage: 5 });
    await excludeEachOther(a, b);
    await createCustomer();

    await login(page, CUSTOMER_EMAIL, TEST_PASSWORD);
    await newCart(page);
    await submitCode(page, A);
    await submitCode(page, B);
    const message = page.locator(`[data-summary-excluded-promotion="${B}"]:visible`);
    await expect(message).toBeVisible({ timeout: 10_000 });
    await shot(page, summary(page), 'p6-cart-summary');

    await page.goto('/checkout/cart');
    await clickNextOrder(page, /\/checkout\/delivery/);
    const radio = page.locator('button[data-live-event-param="SET_DELIVERY_MODULE_OPTION"]').first();
    if (await radio.isVisible()) {
      await selectFirstDeliveryMethod(page);
    } else {
      // Narrow screens: the radio is hidden, the whole delivery card is the click target.
      await page.locator('.DeliveryMode').first().click();
      await page.waitForLoadState('networkidle').catch(() => {});
      await page.waitForTimeout(1500);
    }
    await clickNextOrder(page, /\/checkout\/payment/);
    await selectFirstInvoiceAddress(page);
    await selectPaymentByLabel(page, /cheque|check/i);
    await acceptMandatoryConsents(page);
    await shot(page, summary(page), 'p6-payment-summary');
    await clickNextOrder(page, ORDER_PLACED_URL);
    await expectOrderPlaced(page);
    await shot(page, page.getByRole('heading', { name: /thanks for your order|merci pour votre commande/i }), 'p6-confirmation');

    const orderId = await ddevMysql(`SELECT o.id FROM \`order\` o JOIN customer c ON c.id=o.customer_id WHERE c.email='${CUSTOMER_EMAIL}' ORDER BY o.id DESC LIMIT 1`);
    const usageOf = (code: string) => ddevMysql(`SELECT max_usage FROM coupon WHERE code='${code}'`);
    const customerCounts = (code: string) => ddevMysql(`SELECT COUNT(*) FROM coupon_customer_count WHERE coupon_id=(SELECT id FROM coupon WHERE code='${code}')`);
    const orderCoupons = await ddevMysql(`SELECT GROUP_CONCAT(oc.code) FROM order_coupon oc JOIN \`order\` o ON o.id=oc.order_id JOIN customer c ON c.id=o.customer_id WHERE c.email='${CUSTOMER_EMAIL}'`);
    console.log(`P6 usage A=${await usageOf(A)} B=${await usageOf(B)} perCustomerRows A=${await customerCounts(A)} B=${await customerCounts(B)} orderCoupons=${orderCoupons}`);
    expect(orderCoupons).toBe(A);
    // A cheque order is "not paid": no usage counted yet, for anyone.
    expect(await usageOf(A)).toBe('5');
    expect(await usageOf(B)).toBe('5');

    // The shop manager marks the order paid: usage of the kept promotion is counted then.
    const adminContext = await page.context().browser()!.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport: page.viewportSize()! });
    const adminPage = await adminContext.newPage();
    try {
      await loginAdmin(adminPage);
      await adminPage.goto(`/admin/order/update/${orderId}`);
      await adminPage.getByTestId('order-status-select').selectOption({ value: '2' });
      await adminPage.getByTestId('order-status-submit').click();
      // The status titles of this shop are not translated in the admin language: read the status in the database.
      await expect.poll(() => ddevMysql(`SELECT status_id FROM \`order\` WHERE id=${orderId}`), { timeout: 15_000 }).toBe('2');
    } finally {
      await adminContext.close();
    }
    console.log(`P6 after paid: A=${await usageOf(A)} B=${await usageOf(B)} perCustomerRows B=${await customerCounts(B)}`);
    expect(await usageOf(A)).toBe('4');
    expect(await usageOf(B)).toBe('5');
    expect(await customerCounts(B)).toBe('0');
  });

  test('P7 back-office promotion screen: no horizontal overflow', async ({ page }) => {
    const a = await createPromotion({ code: A, percentage: 10, priority: 10, cumulative: true });
    const b = await createPromotion({ code: B, amount: 5, priority: 20, cumulative: false, stop: true });
    await excludeEachOther(a, b);
    await loginAdmin(page);
    await page.goto(`/admin/coupon/update/${b}`);
    await expect(page.getByTestId('coupon-stacking')).toBeVisible();
    await noOverflow(page);
    await shot(page, page.getByTestId('coupon-stacking'), 'p7-bo-screen');
  });
});
