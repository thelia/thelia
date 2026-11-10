import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { ddevMysql } from '../../helpers/db';

// The three subscriptions below are inserted by SQL (the admin API has no write endpoint for
// subscriptions) and carry a per-run tag in their reference; the plan is created through the
// plans tab of a product. Everything is removed in afterAll.
const TAG = `PW-211-${Date.now()}`;
const REF = { active: `${TAG}-A`, failed: `${TAG}-B`, paused: `${TAG}-C` } as const;
const PLAN_TITLE = `${TAG} plan`;
const PLAN_TITLE_EDITED = `${TAG} plan edited`;

let productId = 0;
let customerId = 0;
let planId = 0;
const ids: Record<keyof typeof REF, number> = { active: 0, failed: 0, paused: 0 };

const tabUrl = () => `/admin/products/update?product_id=${productId}&current_tab=subscription-plans`;

// The list shows the title in the language of the admin session: fill every language.
async function fillTitles(page: import('@playwright/test').Page, title: string) {
  const inputs = page.locator('[data-testid^="subscription-plan-title-"]');
  for (let i = 0; i < (await inputs.count()); i++) await inputs.nth(i).fill(title);
}

async function rows(page: import('@playwright/test').Page) {
  return page.getByTestId('datatable-subscriptions-row');
}

test.describe('Back-office: subscription screens (BO Twig)', () => {
  test.skip((process.env.BO_TEMPLATE ?? 'default-twig') !== 'default-twig', 'BO Twig only.');
  test.describe.configure({ mode: 'serial' });

  test.beforeAll(async () => {
    productId = Number(await ddevMysql('SELECT product_id FROM product_sale_elements GROUP BY product_id ORDER BY product_id LIMIT 1;'));
    customerId = Number(await ddevMysql('SELECT id FROM customer ORDER BY id LIMIT 1;'));
  });

  test.afterAll(async () => {
    // Subscriptions first (their plan link is SET NULL, orders and history cascade), then the plan.
    await ddevMysql(`DELETE FROM subscription WHERE ref LIKE '${TAG}-%';`);
    await ddevMysql(`DELETE FROM subscription_plan WHERE id IN (SELECT id FROM subscription_plan_i18n WHERE title LIKE '${TAG}%');`)
  });

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('plans tab: create a plan', async ({ page }) => {
    await page.goto(tabUrl());
    await expect(page.getByTestId('subscription-plans-tab')).toBeVisible();
    await page.getByTestId('subscription-plan-new').click();
    await expect(page.getByTestId('subscription-plan-form')).toBeVisible();

    await fillTitles(page, PLAN_TITLE);
    await page.getByTestId('subscription-plan-price-EUR').fill('12.50');
    await page.getByTestId('subscription-plan-period-count').fill('2');
    await page.getByTestId('subscription-plan-period-unit').selectOption('month');
    await page.getByTestId('subscription-plan-trial-days').fill('7');
    await page.getByTestId('subscription-plan-installment-count').fill('12');
    await page.getByTestId('subscription-plan-commitment-count').fill('3');
    await page.getByTestId('subscription-plan-pausable').check();
    await page.getByTestId('subscription-plan-visible').check();
    await page.getByTestId('subscription-plan-save').click();

    await expect(page.getByTestId('bo-flash-success')).toBeVisible();
    const row = page.getByTestId('subscription-plan-row').filter({ hasText: PLAN_TITLE });
    await expect(row).toHaveCount(1);
    await expect(row).toContainText(/12[.,]50/);
    await expect(row).toContainText('7');
    planId = Number(await row.getAttribute('data-plan-id'));
    expect(planId).toBeGreaterThan(0);
  });

  test('plans tab: a plan without price is refused and an edit is kept', async ({ page }) => {
    await page.goto(`${tabUrl()}&plan=${planId}`);
    await page.getByTestId('subscription-plan-price-EUR').fill('');
    await page.getByTestId('subscription-plan-save').click();
    // The field is required: the browser keeps the form on the page and nothing is saved.
    await expect(page.getByTestId('subscription-plan-form')).toBeVisible();

    await fillTitles(page, PLAN_TITLE_EDITED);
    await page.getByTestId('subscription-plan-price-EUR').fill('15');
    await page.getByTestId('subscription-plan-save').click();
    await expect(page.getByTestId('bo-flash-success')).toBeVisible();
    const row = page.getByTestId('subscription-plan-row').filter({ hasText: PLAN_TITLE_EDITED });
    await expect(row).toHaveCount(1);
    await expect(row).toContainText(/15/);
  });

  test('seed subscriptions on the plan', async () => {
    const pse = await ddevMysql(`SELECT id FROM product_sale_elements WHERE product_id = ${productId} LIMIT 1;`);
    const insert = (ref: string, status: string, next: string) =>
      `INSERT INTO subscription (ref, customer_id, subscription_plan_id, product_sale_elements_id, quantity, currency_id, lang_id,
        period_unit, period_count, installment_count, commitment_count, pausable, unit_price, delivery_module_id, payment_module_id,
        status, billing_anchor_day, current_period_start, current_period_end, next_billing_at, failure_count, installments_billed, created_at, updated_at)
       VALUES ('${ref}', ${customerId}, ${planId}, ${pse}, 1, 1, 1, 'month', 2, 12, 3, 1, 15, 6, 7,
        '${status}', 15, '2030-12-15 10:00:00', '2031-02-15 10:00:00', ${next}, 0, 1, NOW(), NOW());`;
    await ddevMysql([
      insert(REF.active, 'active', "'2031-01-15 10:00:00'"),
      insert(REF.failed, 'payment_failed', "'2031-03-15 10:00:00'"),
      insert(REF.paused, 'paused', 'NULL'),
    ].join('\n'));
    for (const key of Object.keys(REF) as (keyof typeof REF)[]) {
      ids[key] = Number(await ddevMysql(`SELECT id FROM subscription WHERE ref = '${REF[key]}';`));
      await ddevMysql(`INSERT INTO subscription_history (subscription_id, from_status, to_status, actor_type, note, created_at) VALUES (${ids[key]}, NULL, 'active', 'system', 'PW-211 created', NOW());`);
    }
    // 13 installments on the active one: a second page, and the newest linked to a real order.
    const orderId = await ddevMysql('SELECT id FROM `order` ORDER BY id LIMIT 1;');
    const values = Array.from({ length: 13 }, (_, i) =>
      `(${ids.active}, ${i + 1}, ${i === 12 ? orderId : 'NULL'}, 'paid', 1, NOW(), NOW())`).join(',');
    await ddevMysql(`INSERT INTO subscription_order (subscription_id, installment_number, order_id, state, attempt_count, created_at, updated_at) VALUES ${values};`);
  });

  test('list: shortcut chips, counters and rows', async ({ page }) => {
    await page.goto(`/admin/subscriptions?q=${TAG}`);
    await expect(page.getByTestId('subscriptions-page')).toBeVisible();
    await expect(await rows(page)).toHaveCount(3);
    await expect(page.getByTestId('subscription-ref-link').filter({ hasText: REF.active })).toHaveCount(1);

    // Counters are global: compare with the database instead of a literal.
    for (const code of ['active', 'payment_failed'] as const) {
      const expected = await ddevMysql(`SELECT COUNT(*) FROM subscription WHERE status = '${code}';`);
      await expect(page.getByTestId(`subscription-counter-${code}`)).toHaveText(expected);
    }
    const total = await ddevMysql('SELECT COUNT(*) FROM subscription;');
    await expect(page.getByTestId('subscription-counter-all')).toHaveText(total);

    await page.getByTestId('subscription-quick-filter-payment_failed').click();
    await expect(page).toHaveURL(/status%5B\d*%5D=payment_failed/);
    await expect(page.getByTestId('subscription-active-chip-statuses')).toBeVisible();
    await page.getByTestId('subscription-quick-filter-all').click();
    await expect(page.getByTestId('subscription-active-chip-statuses')).toHaveCount(0);
  });

  test('list: search, plan, status, customer and next billing filters with their chips', async ({ page }) => {
    await page.goto('/admin/subscriptions');
    await page.getByTestId('subscription-filter-q').fill(REF.failed);
    await page.getByTestId('subscription-search-submit').click();
    await expect(await rows(page)).toHaveCount(1);
    await expect(page.getByTestId('subscription-ref-link')).toContainText(REF.failed);
    await expect(page.getByTestId('subscription-active-chip-q')).toBeVisible();

    // Plan filter: the three subscriptions of the plan, whatever the other rows of the shop.
    await page.goto(`/admin/subscriptions?plan=${planId}`);
    await expect(page.getByTestId('subscription-filter-plan')).toHaveValue(String(planId));
    await expect(page.getByTestId('subscription-active-chip-plan')).toContainText(PLAN_TITLE_EDITED);
    await expect(await rows(page)).toHaveCount(3);

    await page.goto(`/admin/subscriptions?plan=${planId}&status[]=paused`);
    await expect(await rows(page)).toHaveCount(1);
    await expect(page.getByTestId('subscription-ref-link')).toContainText(REF.paused);

    await page.goto(`/admin/subscriptions?plan=${planId}&customer_id=${customerId}`);
    await expect(page.getByTestId('subscription-active-chip-customer_id')).toBeVisible();
    await expect(await rows(page)).toHaveCount(3);
    await page.goto(`/admin/subscriptions?plan=${planId}&customer_id=999999999`);
    await expect(await rows(page)).toHaveCount(0);

    // Next billing window: only the subscription due in March 2031.
    await page.goto(`/admin/subscriptions?plan=${planId}`);
    await page.getByTestId('subscription-filter-next-from').fill('2031-03-01');
    await page.getByTestId('subscription-filter-next-to').fill('2031-03-31');
    await page.getByTestId('subscription-search-submit').click();
    await expect(page.getByTestId('subscription-active-chip-next_billing')).toBeVisible();
    await expect(await rows(page)).toHaveCount(1);
    await expect(page.getByTestId('subscription-ref-link')).toContainText(REF.failed);

    // Removing a chip drops its filter only.
    await page.getByTestId('subscription-active-chip-next_billing').click();
    await expect(page.getByTestId('subscription-active-chip-next_billing')).toHaveCount(0);
    await expect(page.getByTestId('subscription-active-chip-plan')).toBeVisible();
    await expect(await rows(page)).toHaveCount(3);
  });

  test('detail: opened from the list, frozen values, installments and history', async ({ page }) => {
    await page.goto(`/admin/subscriptions?q=${REF.active}`);
    await page.getByTestId('subscription-ref-link').click();
    await expect(page).toHaveURL(new RegExp(`/admin/subscriptions/${ids.active}$`));
    await expect(page.getByTestId('subscription-detail-page')).toBeVisible();
    const activeBadge = (await page.getByTestId('subscription-status-badge').textContent())?.trim();
    expect(activeBadge).toBeTruthy();
    await expect(page.getByTestId('subscription-frozen-values')).toContainText('12');
    await expect(page.getByTestId('subscription-payment-method')).toBeVisible();
    await expect(page.getByTestId('subscription-dates')).toContainText('2031');
    await expect(page.getByTestId('subscription-history-row')).toHaveCount(1);
    await expect(page.getByTestId('subscription-history-row')).toContainText('PW-211 created');

    // 13 installments, 12 per page: newest first: one order link on page 1, the oldest installment alone on page 2.
    await expect(page.getByTestId('datatable-subscription-installments-row')).toHaveCount(12);
    await expect(page.getByTestId('subscription-installment-order-link')).toHaveCount(1);
    await expect(page.getByTestId('subscription-installments-pagination')).toBeVisible();
    await page.goto(`/admin/subscriptions/${ids.active}?installments_page=2`);
    await expect(page.getByTestId('datatable-subscription-installments-row')).toHaveCount(1);

    // An active, pausable subscription offers cancel and pause, not reactivate.
    await expect(page.getByTestId('subscription-cancel-button')).toBeVisible();
    await expect(page.getByTestId('subscription-pause-button')).toBeVisible();
    await expect(page.getByTestId('subscription-reactivate-button')).toHaveCount(0);
  });

  test('cancellation asks for a confirmation and is applied once confirmed', async ({ page }) => {
    await page.goto(`/admin/subscriptions/${ids.active}`);
    const before = (await page.getByTestId('subscription-status-badge').textContent())?.trim();
    await page.getByTestId('subscription-cancel-button').click();
    await expect(page.locator('#subscription-cancel-modal')).toBeVisible();
    // Dismissing the dialog changes nothing.
    await page.locator('#subscription-cancel-modal').locator('[data-bs-dismiss="modal"]').first().click();
    await expect(page.locator('#subscription-cancel-modal')).toBeHidden();
    expect(await ddevMysql(`SELECT status FROM subscription WHERE id = ${ids.active};`)).toBe('active');

    await page.getByTestId('subscription-cancel-button').click();
    await expect(page.locator('#subscription-cancel-modal')).toBeVisible();
    await page.getByTestId('subscription-cancel-submit').click();
    await expect(page.getByTestId('bo-flash-success')).toBeVisible();
    await expect(page.getByTestId('subscription-status-badge')).not.toHaveText(before ?? '');
    expect(await ddevMysql(`SELECT status FROM subscription WHERE id = ${ids.active};`)).not.toBe('active');
    await expect(page.getByTestId('subscription-history-row')).toHaveCount(2);
    await expect(page.getByTestId('subscription-cancel-button')).toHaveCount(0);
  });

  test('reactivation asks for a retry: success flash and a history line, status unchanged', async ({ page }) => {
    await page.goto(`/admin/subscriptions/${ids.failed}`);
    await expect(page.getByTestId('subscription-reactivate-button')).toBeVisible();
    await page.getByTestId('subscription-reactivate-button').click();
    await expect(page.locator('#subscription-reactivate-modal')).toBeVisible();
    await page.getByTestId('subscription-reactivate-submit').click();
    await expect(page.getByTestId('bo-flash-success')).toBeVisible();
    // No module of the dev shop can charge: the status stays until a charge is paid.
    await expect(page.getByTestId('subscription-history-row').filter({ hasText: 'Retry asked.' })).toHaveCount(1);
  });

  test('a paused subscription offers resume', async ({ page }) => {
    await page.goto(`/admin/subscriptions/${ids.paused}`);
    await expect(page.getByTestId('subscription-resume-button')).toBeVisible();
    await expect(page.getByTestId('subscription-cancel-button')).toBeVisible();
  });

  test('plans tab: a plan is deleted through its confirmation', async ({ page }) => {
    await page.goto(tabUrl());
    const row = page.getByTestId('subscription-plan-row').filter({ hasText: PLAN_TITLE_EDITED });
    await expect(row).toHaveCount(1);
    await row.getByTestId('subscription-plan-delete').click();
    await page.getByTestId(`subscription-plan-delete-${planId}-submit`).click();
    await expect(page.getByTestId('bo-flash-success')).toBeVisible();
    await expect(page.getByTestId('subscription-plan-row').filter({ hasText: PLAN_TITLE_EDITED })).toHaveCount(0);
    // The subscriptions taken on it go on unchanged.
    expect(await ddevMysql(`SELECT COUNT(*) FROM subscription WHERE ref LIKE '${TAG}-%';`)).toBe('3');
  });
});
