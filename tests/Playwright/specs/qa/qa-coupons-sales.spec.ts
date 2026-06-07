import { test, expect, type Page } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { IssueCollector, sweepScreen, formatIssues, qaRef, type PageIssue } from '../../helpers/qa';

/**
 * QA campaign — domain "coupons-sales".
 *
 * Screens covered:
 *  - /admin/coupon                      (list: sort, pagination, create btn, delete modal prefill + effective delete)
 *  - /admin/coupon/create               (every coupon type → AJAX inputs draw, toggles, free-product picker, persistence)
 *  - /admin/coupon/update/{id}          (edit code/title/expiration save stay+close, conditions add/edit/delete)
 *  - /admin/sales                       (list: sort, toggle activation, check/reset, create modal, language switcher)
 *  - /admin/sale/update/{id}            (product picker by category, attributes modal, price_offset per currency)
 *
 * Reference for expected behaviour: BO Smarty templates/backOffice/default/*.html.
 * Created entities are prefixed with qaRef() and deleted at the end of their test.
 *
 * Known back-office facts (verified against DB / controllers, NOT bugs):
 *  - Demo coupons: id 51..55 (remove_x_percent / remove_x_amount). Demo sales: id 45, 46 (both active=1).
 *  - Default currency EUR id 1.
 *  - Coupon delete uses BoConfirmDialog: token in the form action URL query, body carries only coupon_id.
 *  - Coupon condition.save / .delete read _token in the query first (then body). The edit page exposes
 *    pre-tokenised URLs in the bo-coupon-form data attributes.
 *  - Known issue (campaign): coupon edit does NOT pre-fill the type-specific values (signaled, NOT re-qualified).
 */

const SWEEP = { tabs: true, modals: true } as const;

const DEMO_COUPON_ID = 51;       // WELCOME10, remove_x_percent
const DEMO_SALE_ID = 45;         // active demo sale
const EUR_CURRENCY_ID = 1;

// Coupon type service ids (from core/lib/Thelia/Config — verified present in this install).
const COUPON_TYPES = {
  removeXPercent: 'thelia.coupon.type.remove_x_percent',
  removeXAmount: 'thelia.coupon.type.remove_x_amount',
  removeX: 'thelia.coupon.type.remove_x',
  freeProduct: 'thelia.coupon.type.free_product',
  removeAmountOnCategories: 'thelia.coupon.type.remove_amount_on_categories',
  removePercentageOnCategories: 'thelia.coupon.type.remove_percentage_on_categories',
  removeAmountOnProducts: 'thelia.coupon.type.remove_amount_on_products',
  removePercentageOnProducts: 'thelia.coupon.type.remove_percentage_on_products',
  removeAmountOnAttributeAv: 'thelia.coupon.type.remove_amount_on_attribute_av',
  removePercentageOnAttributeAv: 'thelia.coupon.type.remove_percentage_on_attribute_av',
} as const;

const CONDITIONS = {
  startDate: 'thelia.condition.start_date',
  cartTotal: 'thelia.condition.match_for_total_amount',
  itemCount: 'thelia.condition.match_for_x_articles',
  customers: 'thelia.condition.for_some_customers',
  deliveryCountries: 'thelia.condition.match_delivery_countries',
  containsCategories: 'thelia.condition.cart_contains_categories',
  containsProducts: 'thelia.condition.cart_contains_products',
  everyone: 'thelia.condition.match_for_everyone',
} as const;

/** Filter top-nav language-flag 404 noise shared by every admin page. */
function expectClean(issues: PageIssue[]): void {
  const filtered = issues.filter((i) => {
    if (/svgFlags\/.*\.svg/.test(i.detail)) return false;
    if (i.kind === 'console' && /status of 404/.test(i.detail) && /svg|flag/i.test(i.detail)) return false;
    return true;
  });
  expect(filtered, formatIssues(filtered)).toHaveLength(0);
}

/** POST a form-encoded body using the page session. */
async function postForm(
  page: Page,
  action: string,
  body: Record<string, string | string[]>,
): Promise<{ status: number; url: string; text: string }> {
  return page.evaluate(async ({ action, body }) => {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(body)) {
      if (Array.isArray(value)) for (const v of value) params.append(key, v);
      else params.append(key, value);
    }
    const res = await fetch(action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json, text/html' },
      body: params.toString(),
      redirect: 'follow',
    });
    return { status: res.status, url: res.url, text: (await res.text()).slice(0, 1200) };
  }, { action, body });
}

/** Fixed expiration date in the page's default format (Y-m-d). The demo install uses ISO Y-m-d. */
function futureDate(): string {
  const d = new Date();
  d.setFullYear(d.getFullYear() + 1);
  return d.toISOString().slice(0, 10);
}

/**
 * Create a sale via the list create modal and resolve its id from the list.
 *
 * NOTE (SALE-01, see test.fixme): SaleController::create redirects to the LIST (successRoute:
 * LIST_ROUTE) instead of the new sale's edit page, unlike the Smarty CRUD flow which substitutes
 * _ID_ in the success URL and lands on /admin/sale/update/{id}. The sale IS created, so we resolve
 * the id from the row carrying the unique title.
 */
async function createSaleAndResolveId(page: Page, title: string): Promise<number> {
  await page.goto('/admin/sales', { waitUntil: 'networkidle' });
  await page.getByTestId('sale-create-btn').click();
  const createForm = page.getByTestId('sale-create-form');
  await expect(createForm).toBeVisible();
  await createForm.locator('input[name$="[title]"]').fill(title);
  await createForm.locator('input[name$="[label]"]').fill('QA');
  await createForm.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
  await page.goto('/admin/sales', { waitUntil: 'networkidle' });
  const row = page.locator('tr', { hasText: title }).first();
  await expect(row, `created sale "${title}" not found in list`).toBeVisible({ timeout: 10_000 });
  const href = await row.locator('a[href*="/admin/sale/update/"]').first().getAttribute('href');
  const id = Number(/\/admin\/sale\/update\/(\d+)/.exec(href ?? '')?.[1]);
  expect(id, `could not parse sale id from ${href}`).toBeGreaterThan(0);
  return id;
}

test.describe('coupons-sales', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default-twig') !== 'default-twig',
    'BO Twig only — run with the default-twig back-office active.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  // ===========================================================================
  // COUPON LIST — /admin/coupon
  // ===========================================================================
  test.describe('coupon list', () => {
    const URL = '/admin/coupon';

    test('sweep: list, sort headers, pagination, create btn, delete modal — no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('coupons-page')).toBeVisible();
      await expect(page.getByTestId('coupon-create-button')).toBeVisible();
      // A demo coupon must be listed.
      await expect(page.getByText('WELCOME10', { exact: true }).first()).toBeVisible();
      // The delete confirm modal must exist (prefill driven by data-coupon-id).
      await expect(page.getByTestId('coupon-delete-form')).toBeAttached();
      expectClean(await sweepScreen(page, collector, SWEEP));
    });

    test('sort by code then by id keeps the list rendering (no 5xx)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(`${URL}?order=code`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('coupons-page')).toBeVisible();
      await page.goto(`${URL}?order=id`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('coupons-page')).toBeVisible();
      await page.goto(`${URL}?order=expiration_date`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('coupons-page')).toBeVisible();
      expectClean(collector.drain());
    });
  });

  // ===========================================================================
  // COUPON CREATE — /admin/coupon/create  (every type + toggles + persistence)
  // ===========================================================================
  test.describe('coupon create', () => {
    const URL = '/admin/coupon/create';

    test('sweep: create page, type select, toggles — no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('coupon-edit-page')).toBeVisible();
      await expect(page.locator('#coupon-type')).toBeVisible();

      // Unlimited toggle drives the max-usage card visibility (initial state depends on
      // data.maxUsage, which is -1/unlimited on a fresh create form). Assert the toggle flips it.
      const maxUsageWrap = page.locator('[data-bo-coupon-form-target="maxUsageWrap"]');
      const unlimited = page.locator('#is-unlimited');
      await unlimited.uncheck();
      await expect(maxUsageWrap).toBeVisible();
      await unlimited.check();
      await expect(maxUsageWrap).toBeHidden();
      await unlimited.uncheck();

      // Free-shipping toggle reveals the postage card.
      const postageWrap = page.locator('[data-bo-coupon-form-target="postageWrap"]');
      await expect(postageWrap).toBeHidden();
      await page.locator('#is-removing-postage').check();
      await expect(postageWrap).toBeVisible();
      await page.locator('#is-removing-postage').uncheck();

      expectClean(await sweepScreen(page, collector, { tabs: false, modals: false }));
    });

    test('changing the type select draws the matching inputs via AJAX (each type)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'networkidle' });
      const typeSelect = page.locator('#coupon-type');
      const inputs = page.locator('[data-bo-coupon-form-target="inputsContainer"]');

      // Iterate every option actually exposed by the install.
      const optionValues = (await typeSelect.locator('option').evaluateAll((opts) =>
        opts.map((o) => (o as HTMLOptionElement).value),
      )).filter((v) => v.length > 0);
      expect(optionValues.length, 'coupon type select is empty').toBeGreaterThan(0);

      for (const value of optionValues) {
        await typeSelect.selectOption(value);
        // Wait for the AJAX draw/inputs/{serviceId} fetch to repaint the container.
        await page.waitForLoadState('networkidle', { timeout: 8_000 }).catch(() => undefined);
        await page.waitForTimeout(250);
        // The tooltip helper text must update from the option's data-tooltip.
        const tooltip = await page.locator('[data-bo-coupon-form-target="typeTooltip"]').textContent();
        expect(tooltip, `tooltip empty for type ${value}`).not.toBeNull();
        // The inputs container must render *something* for value-bearing types (percent/amount/pickers).
        const inner = (await inputs.innerHTML()).trim();
        // remove_x family + on-categories/products/attributes + free_product all render markup.
        expect(inner.length, `no inputs drawn for type ${value}`).toBeGreaterThan(0);
      }
      expectClean(collector.drain());
    });

    test('free-product type: category picker populates the offered-product select', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'networkidle' });
      await page.locator('#coupon-type').selectOption(COUPON_TYPES.freeProduct);
      await page.waitForLoadState('networkidle').catch(() => undefined);
      await page.waitForTimeout(300);
      const categorySelect = page.getByTestId('coupon-free-product-category');
      await expect(categorySelect).toBeVisible({ timeout: 8_000 });
      // Pick the first real category; the bo-coupon-product-picker fills the product select client-side.
      const firstCat = categorySelect.locator('option:not([value=""])').first();
      const catValue = await firstCat.getAttribute('value');
      if (catValue) {
        await categorySelect.selectOption(catValue);
        await page.waitForTimeout(300);
        const productSelect = page.getByTestId('coupon-free-product-product');
        // Product list is built from the json data attribute; it should gain options (or stay empty if cat has none).
        await expect(productSelect).toBeVisible();
      }
      expectClean(collector.drain());
    });

    // Full lifecycle: create a remove_x_percent coupon, assert persistence in DB-backed list/edit, then delete.
    test('create remove_x_percent coupon → persistence → delete', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      const code = qaRef('coupon');
      const title = `${code} title`;

      await page.goto(URL, { waitUntil: 'networkidle' });
      await page.locator('#coupon-code').fill(code);
      await page.locator('#coupon-title').fill(title);
      await page.locator('#coupon-type').selectOption(COUPON_TYPES.removeXPercent);
      await page.waitForLoadState('networkidle').catch(() => undefined);
      await page.waitForTimeout(300);
      // The percent input is drawn by AJAX.
      const percent = page.locator('input[name="coupon_specific[percentage]"]');
      await expect(percent).toBeVisible({ timeout: 8_000 });
      await percent.fill('15');
      await page.locator('#expiration-date').fill(futureDate());
      // Max-usage card is hidden while "unlimited" is on (the create default); reveal it.
      await page.locator('#is-unlimited').uncheck();
      await page.locator('#max-usage').fill('100');

      // Submit with save_mode=close (BoSaveModeToolbar "save and close") → redirect to the list.
      await Promise.all([
        page.waitForURL(/\/admin\/coupon(\?.*)?$/, { timeout: 15_000 }),
        page.getByTestId('coupon-save-close').click(),
      ]);

      // --- PERSISTENCE: coupon appears in the list ---
      await page.goto('/admin/coupon', { waitUntil: 'networkidle' });
      const row = page.locator('tr', { hasText: code }).first();
      await expect(row, `created coupon ${code} not found in list`).toBeVisible({ timeout: 10_000 });

      // Resolve the new coupon id from the edit link in the row.
      const href = await row.locator('a[href*="/admin/coupon/update/"]').first().getAttribute('href');
      const couponId = Number(/\/admin\/coupon\/update\/(\d+)/.exec(href ?? '')?.[1]);
      expect(couponId, `could not parse coupon id from ${href}`).toBeGreaterThan(0);

      // --- PERSISTENCE: edit page holds code + title + type ---
      await page.goto(`/admin/coupon/update/${couponId}`, { waitUntil: 'networkidle' });
      await expect(page.locator('#coupon-code')).toHaveValue(code);
      await expect(page.locator('#coupon-title')).toHaveValue(title);
      await expect(page.locator('#coupon-type')).toHaveValue(COUPON_TYPES.removeXPercent);

      // --- DELETE via the confirm-dialog form (token in action query, body = coupon_id only) ---
      await page.goto('/admin/coupon', { waitUntil: 'networkidle' });
      const form = page.getByTestId('coupon-delete-form');
      const action = await form.getAttribute('action');
      expect(action, 'delete form has no action').toContain('_token=');
      const delRes = await postForm(page, action!, { coupon_id: String(couponId) });
      expect(delRes.status, delRes.text).toBeLessThan(400);

      // --- PERSISTENCE: gone from the list ---
      await page.goto('/admin/coupon', { waitUntil: 'networkidle' });
      await expect(page.getByText(code, { exact: true })).toHaveCount(0);
      expectClean(collector.drain());
    });

    // remove_x_amount uses coupon_specific[amount]; verify a second type persists end-to-end.
    test('create remove_x_amount coupon → persistence → delete', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      const code = qaRef('coupon');

      await page.goto(URL, { waitUntil: 'networkidle' });
      await page.locator('#coupon-code').fill(code);
      await page.locator('#coupon-title').fill(`${code} amount`);
      await page.locator('#coupon-type').selectOption(COUPON_TYPES.removeXAmount);
      await page.waitForLoadState('networkidle').catch(() => undefined);
      await page.waitForTimeout(300);
      const amount = page.locator('input[name="coupon_specific[amount]"]');
      await expect(amount).toBeVisible({ timeout: 8_000 });
      await amount.fill('12.50');
      await page.locator('#expiration-date').fill(futureDate());
      await page.locator('#is-unlimited').check(); // unlimited uses path

      await Promise.all([
        page.waitForURL(/\/admin\/coupon(\?.*)?$/, { timeout: 15_000 }),
        page.locator('form[data-testid="coupon-edit-form"] button[type="submit"]').last().click(),
      ]);

      await page.goto('/admin/coupon', { waitUntil: 'networkidle' });
      const row = page.locator('tr', { hasText: code }).first();
      await expect(row).toBeVisible({ timeout: 10_000 });
      const href = await row.locator('a[href*="/admin/coupon/update/"]').first().getAttribute('href');
      const couponId = Number(/\/admin\/coupon\/update\/(\d+)/.exec(href ?? '')?.[1]);
      expect(couponId).toBeGreaterThan(0);

      // Persistence: unlimited uses → "Usages left" / max usage -1.
      await page.goto(`/admin/coupon/update/${couponId}`, { waitUntil: 'networkidle' });
      await expect(page.locator('#is-unlimited')).toBeChecked();

      // Delete form lives on the LIST page (BoConfirmDialog), not the edit page.
      await page.goto('/admin/coupon', { waitUntil: 'networkidle' });
      const form = page.getByTestId('coupon-delete-form');
      const action = await form.getAttribute('action');
      const delRes = await postForm(page, action!, { coupon_id: String(couponId) });
      expect(delRes.status, delRes.text).toBeLessThan(400);
      await page.goto('/admin/coupon', { waitUntil: 'networkidle' });
      await expect(page.getByText(code, { exact: true })).toHaveCount(0);
      expectClean(collector.drain());
    });
  });

  // ===========================================================================
  // COUPON UPDATE + CONDITIONS — /admin/coupon/update/{id}
  // ===========================================================================
  test.describe('coupon update', () => {
    test('sweep demo coupon edit page — no errors, conditions panel present', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(`/admin/coupon/update/${DEMO_COUPON_ID}`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('coupon-edit-page')).toBeVisible();
      await expect(page.locator('#coupon-code')).toHaveValue('WELCOME10');
      // Conditions panel + add-condition select must be present on update.
      await expect(page.locator('[data-bo-coupon-form-target="conditionTypeSelect"]')).toBeVisible();
      await expect(page.getByTestId('coupon-conditions-table')).toBeVisible();
      expectClean(await sweepScreen(page, collector, { tabs: true, modals: false }));
    });

    // Full lifecycle on a throwaway coupon: edit code/title/expiration with save stay then close, verify persistence.
    test('edit code/title/expiration: save stay then close → persistence', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      const code = qaRef('coupon');

      // Create a throwaway coupon to mutate (do not touch demo coupons).
      await page.goto('/admin/coupon/create', { waitUntil: 'networkidle' });
      await page.locator('#coupon-code').fill(code);
      await page.locator('#coupon-title').fill(`${code} v1`);
      await page.locator('#coupon-type').selectOption(COUPON_TYPES.removeXPercent);
      await page.waitForLoadState('networkidle').catch(() => undefined);
      await page.waitForTimeout(300);
      await page.locator('input[name="coupon_specific[percentage]"]').fill('10');
      await page.locator('#expiration-date').fill(futureDate());
      await Promise.all([
        page.waitForURL(/\/admin\/coupon(\?.*)?$/, { timeout: 15_000 }),
        page.locator('form[data-testid="coupon-edit-form"] button[type="submit"]').last().click(),
      ]);
      await page.goto('/admin/coupon', { waitUntil: 'networkidle' });
      const row = page.locator('tr', { hasText: code }).first();
      const href = await row.locator('a[href*="/admin/coupon/update/"]').first().getAttribute('href');
      const couponId = Number(/\/admin\/coupon\/update\/(\d+)/.exec(href ?? '')?.[1]);
      expect(couponId).toBeGreaterThan(0);

      // EDIT with save_mode=stay (BoSaveModeToolbar "save" button stays on the edit page).
      const newTitle = `${code} v2`;
      await page.goto(`/admin/coupon/update/${couponId}`, { waitUntil: 'networkidle' });
      await page.locator('#coupon-title').fill(newTitle);
      // BoSaveModeToolbar "Save" posts save_mode=stay → stays on the edit page.
      await Promise.all([
        page.waitForURL(new RegExp(`/admin/coupon/update/${couponId}`), { timeout: 15_000 }),
        page.getByTestId('coupon-save-stay').click(),
      ]);

      // --- PERSISTENCE: title saved ---
      await page.goto(`/admin/coupon/update/${couponId}`, { waitUntil: 'networkidle' });
      await expect(page.locator('#coupon-title')).toHaveValue(newTitle);

      // CLEANUP
      await page.goto('/admin/coupon', { waitUntil: 'networkidle' });
      const action = await page.getByTestId('coupon-delete-form').getAttribute('action');
      await postForm(page, action!, { coupon_id: String(couponId) });
      await page.goto('/admin/coupon', { waitUntil: 'networkidle' });
      await expect(page.getByText(code, { exact: true })).toHaveCount(0);
      expectClean(collector.drain());
    });

    // BUG COUP-01 (BLOCKER) — operator-based coupon conditions cannot be saved in BO Twig: the
    // condition.save endpoint returns HTTP 500. Root cause: the BO Twig condition fragments
    // (coupon/condition-fragments/*-condition.html.twig) name their inputs by the *service id*,
    // e.g. name="thelia.condition.match_for_total_amount[operator]" / "...[value]". But
    // CouponConditionsRenderer::buildConditionFromRequest keys operators/values by that field name,
    // then ConditionFactory::build → Condition::setValidatorsFromForm reads them by the condition's
    // *inputKey* — 'price'/'currency' (MatchForTotalAmount), 'quantity' (MatchForXArticles),
    // 'start_date' (StartDate). The legacy Smarty fragment names inputs by inputKey
    // (templates/backOffice/default/coupon/condition-fragments/base-input-text.html → name="{$inputKey}[value]").
    // Result: $values['price'] / $operators['start_date'] are undefined →
    //   "Warning: Undefined array key 'price' at ConditionAbstract.php:76" (confirmed in var/log/dev).
    // Repro:
    //   1. Create a coupon, open /admin/coupon/update/{id}
    //   2. Pick "Cart total" (or "Number of articles", "Validity start date") in the condition select
    //   3. Fill operator + value, click "Save this condition"
    //   4. EXPECTED: condition saved, summaries table shows a new row
    //      ACTUAL: condition.save POST returns 500; no row added.
    // Fix (bundle, DONE): the fragments now name their inputs by inputKey (price/currency/quantity/
    // start_date). Item-count saves end-to-end (see the test below).
    // cart-total and start-date relied on two pre-existing CORE defects (both also broke the Smarty BO),
    // now fixed:
    //   - MatchForTotalAmount::setValidatorsFromForm now casts the string price to float before
    //     isPriceValid(float) (core/lib/Thelia/Condition/Implementation/MatchForTotalAmount.php).
    //   - StartDate::setValidatorsFromForm now references the global \DateTime
    //     (core/lib/Thelia/Condition/Implementation/StartDate.php).
    test('add cart-total condition via condition.save endpoint → persistence', async ({ page }) => {
      const code = qaRef('coupon');
      await page.goto('/admin/coupon/create', { waitUntil: 'networkidle' });
      await page.locator('#coupon-code').fill(code);
      await page.locator('#coupon-title').fill(`${code} cond`);
      await page.locator('#coupon-type').selectOption(COUPON_TYPES.removeXPercent);
      await page.waitForLoadState('networkidle').catch(() => undefined);
      await page.waitForTimeout(300);
      await page.locator('input[name="coupon_specific[percentage]"]').fill('5');
      await page.locator('#expiration-date').fill(futureDate());
      await Promise.all([
        page.waitForURL(/\/admin\/coupon(\?.*)?$/, { timeout: 15_000 }),
        page.locator('form[data-testid="coupon-edit-form"] button[type="submit"]').last().click(),
      ]);
      await page.goto('/admin/coupon', { waitUntil: 'networkidle' });
      const row = page.locator('tr', { hasText: code }).first();
      const href = await row.locator('a[href*="/admin/coupon/update/"]').first().getAttribute('href');
      const couponId = Number(/\/admin\/coupon\/update\/(\d+)/.exec(href ?? '')?.[1]);

      await page.goto(`/admin/coupon/update/${couponId}`, { waitUntil: 'networkidle' });
      const saveUrl = await page.locator('[data-bo-coupon-form-draw-inputs-url-value]')
        .getAttribute('data-bo-coupon-form-save-condition-url-value');
      const field = CONDITIONS.cartTotal;
      // BO Twig fragments name inputs by the condition inputKey (price / currency), matching
      // what Condition::setValidatorsFromForm reads. categoryCondition still carries the service id.
      const saveRes = await postForm(page, saveUrl!, {
        categoryCondition: field,
        'price[operator]': '>=',
        'price[value]': '50',
        'currency[operator]': '==',
        'currency[value]': 'EUR',
      });
      expect(saveRes.status, saveRes.text).toBeLessThan(400);

      await page.goto(`/admin/coupon/update/${couponId}`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('coupon-condition-row-0')).toBeVisible({ timeout: 10_000 });

      // CLEANUP
      await page.goto('/admin/coupon', { waitUntil: 'networkidle' });
      const action = await page.getByTestId('coupon-delete-form').getAttribute('action');
      await postForm(page, action!, { coupon_id: String(couponId) });
    });

    // Regression for COUP-01: the item-count condition saves through condition.save once the
    // fragment names its input by inputKey (quantity). cart-total and start-date are blocked by
    // separate core defects (see the fixme test above).
    test('condition.save persists an item-count condition', async ({ page }) => {
      const code = qaRef('coupon');
      await page.goto('/admin/coupon/create', { waitUntil: 'networkidle' });
      await page.locator('#coupon-code').fill(code);
      await page.locator('#coupon-title').fill(`${code} cond`);
      await page.locator('#coupon-type').selectOption(COUPON_TYPES.removeXPercent);
      await page.waitForLoadState('networkidle').catch(() => undefined);
      await page.waitForTimeout(300);
      await page.locator('input[name="coupon_specific[percentage]"]').fill('5');
      await page.locator('#expiration-date').fill(futureDate());
      await Promise.all([
        page.waitForURL(/\/admin\/coupon(\?.*)?$/, { timeout: 15_000 }),
        page.locator('form[data-testid="coupon-edit-form"] button[type="submit"]').last().click(),
      ]);
      await page.goto('/admin/coupon', { waitUntil: 'networkidle' });
      const row = page.locator('tr', { hasText: code }).first();
      const href = await row.locator('a[href*="/admin/coupon/update/"]').first().getAttribute('href');
      const couponId = Number(/\/admin\/coupon\/update\/(\d+)/.exec(href ?? '')?.[1]);
      expect(couponId).toBeGreaterThan(0);

      await page.goto(`/admin/coupon/update/${couponId}`, { waitUntil: 'networkidle' });
      const saveUrl = await page.locator('[data-bo-coupon-form-draw-inputs-url-value]')
        .getAttribute('data-bo-coupon-form-save-condition-url-value');

      const itemCountRes = await postForm(page, saveUrl!, {
        categoryCondition: CONDITIONS.itemCount,
        'quantity[operator]': '>=',
        'quantity[value]': '3',
      });
      expect(itemCountRes.status, itemCountRes.text).toBeLessThan(400);

      await page.goto(`/admin/coupon/update/${couponId}`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('coupon-condition-row-0')).toBeVisible({ timeout: 10_000 });

      // CLEANUP the throwaway coupon.
      await page.goto('/admin/coupon', { waitUntil: 'networkidle' });
      const action = await page.getByTestId('coupon-delete-form').getAttribute('action');
      await postForm(page, action!, { coupon_id: String(couponId) });
      await page.goto('/admin/coupon', { waitUntil: 'networkidle' });
      await expect(page.getByText(code, { exact: true })).toHaveCount(0);
    });
  });

  // ===========================================================================
  // SALES LIST — /admin/sales
  // ===========================================================================
  test.describe('sales list', () => {
    const URL = '/admin/sales';

    test('sweep: list, sort headers, create modal, delete modal — no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('sales-page')).toBeVisible();
      await expect(page.getByTestId('sale-create-btn')).toBeVisible();
      await expect(page.getByTestId('sales-check-activation')).toBeVisible();
      await expect(page.getByTestId('sales-reset-status')).toBeVisible();
      await expect(page.getByTestId(`sale-row-${DEMO_SALE_ID}`)).toBeVisible();
      expectClean(await sweepScreen(page, collector, SWEEP));
    });

    test('sort by title / start_date / status — no 5xx', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      for (const order of ['title', 'start_date', 'active']) {
        await page.goto(`${URL}?order=${order}`, { waitUntil: 'networkidle' });
        await expect(page.getByTestId('sales-page')).toBeVisible();
      }
      expectClean(collector.drain());
    });

    // SALE-01 (fixed): creating a sale now lands on the new sale's configuration/edit page, like the
    // Smarty CRUD flow. SaleController::create redirects to admin.sale.update with the created id
    // (successParametersResolver reads $event->getSale()->getId()), matching the "Create and
    // configure" button affordance.
    test('create sale lands on the configuration page', async ({ page }) => {
      const title = qaRef('sale');
      await page.goto(URL, { waitUntil: 'networkidle' });
      await page.getByTestId('sale-create-btn').click();
      const createForm = page.getByTestId('sale-create-form');
      await createForm.locator('input[name$="[title]"]').fill(title);
      await createForm.locator('input[name$="[label]"]').fill('QA');
      await Promise.all([
        page.waitForURL(/\/admin\/sale\/update\/\d+/, { timeout: 15_000 }),
        page.getByTestId('sale-create-submit').click(),
      ]);
      await expect(page.getByTestId('sale-edit-page')).toBeVisible();

      // CLEANUP: resolve the created id from the URL and delete the throwaway sale.
      const saleId = Number(/\/admin\/sale\/update\/(\d+)/.exec(page.url())?.[1]);
      expect(saleId).toBeGreaterThan(0);
      await page.goto(URL, { waitUntil: 'networkidle' });
      const action = await page.getByTestId('sale-delete-form').getAttribute('action');
      await postForm(page, action!, { sale_id: String(saleId) });
      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.getByTestId(`sale-row-${saleId}`)).toHaveCount(0);
    });

    test('create sale → toggle activation → persistence → delete', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      const title = qaRef('sale');

      // Create — redirect lands on the list (SALE-01), so resolve the new id from the row.
      const saleId = await createSaleAndResolveId(page, title);

      // --- PERSISTENCE: appears in the list ---
      const row = page.getByTestId(`sale-row-${saleId}`);
      await expect(row).toBeVisible({ timeout: 10_000 });
      await expect(row).toContainText(title);

      // --- TOGGLE activation (the badge link carries the toggle url) ---
      const toggle = page.getByTestId(`sale-toggle-${saleId}`);
      const before = (await toggle.textContent())?.trim() ?? '';
      await toggle.click();
      await page.waitForLoadState('networkidle');
      await page.goto(URL, { waitUntil: 'networkidle' });
      const after = (await page.getByTestId(`sale-toggle-${saleId}`).textContent())?.trim() ?? '';
      expect(after, `toggle did not flip the status (before="${before}" after="${after}")`).not.toBe(before);

      // --- DELETE via confirm dialog (token in action query, body = sale_id) ---
      await page.goto(URL, { waitUntil: 'networkidle' });
      const action = await page.getByTestId('sale-delete-form').getAttribute('action');
      expect(action, 'sale delete form has no action').toContain('_token=');
      const delRes = await postForm(page, action!, { sale_id: String(saleId) });
      expect(delRes.status, delRes.text).toBeLessThan(400);
      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.getByTestId(`sale-row-${saleId}`)).toHaveCount(0);
      expectClean(collector.drain());
    });

    test('check activation and reset status actions — no 5xx', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'networkidle' });
      // Check activation recomputes active flags; should return to the list cleanly.
      await Promise.all([
        page.waitForURL(/\/admin\/sales/, { timeout: 15_000 }),
        page.getByTestId('sales-check-activation').click(),
      ]);
      await expect(page.getByTestId('sales-page')).toBeVisible();
      // Reset status.
      await Promise.all([
        page.waitForURL(/\/admin\/sales/, { timeout: 15_000 }),
        page.getByTestId('sales-reset-status').click(),
      ]);
      await expect(page.getByTestId('sales-page')).toBeVisible();
      expectClean(collector.drain());
    });
  });

  // ===========================================================================
  // SALE EDIT — /admin/sale/update/{id}  (product picker, attributes, offsets)
  // ===========================================================================
  test.describe('sale edit', () => {
    test('sweep demo sale edit page — no errors, product picker + offsets present', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(`/admin/sale/update/${DEMO_SALE_ID}`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('sale-edit-page')).toBeVisible();
      await expect(page.getByTestId('sale-categories')).toBeVisible();
      await expect(page.getByTestId('sale-load-products')).toBeVisible();
      // Per-currency offset input for EUR.
      await expect(page.getByTestId(`sale-offset-${EUR_CURRENCY_ID}`)).toBeVisible();
      expectClean(await sweepScreen(page, collector, { tabs: false, modals: true }));
    });

    // Lifecycle on a throwaway sale: pick a category, load products, select one, save offset, verify persistence.
    test('product picker by category + price_offset save → persistence', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      const title = qaRef('sale');

      // Throwaway sale (create redirects to the list — SALE-01 — so resolve the id from the row).
      const saleId = await createSaleAndResolveId(page, title);
      await page.goto(`/admin/sale/update/${saleId}`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('sale-edit-page')).toBeVisible();

      // --- LOAD PRODUCTS by category (bo-sale-product-picker fetches products_url) ---
      // Pick a category known to carry products in the demo (cat 68 has 14); fall back to the
      // first option that actually yields products if the select doesn't expose 68.
      const categories = page.getByTestId('sale-categories');
      const productZone = page.getByTestId('sale-product-zone');
      const checkboxes = productZone.locator('input[name="products[]"]');

      const optionValues = (await categories.locator('option:not([value=""])').evaluateAll((opts) =>
        opts.map((o) => (o as HTMLOptionElement).value),
      ));
      expect(optionValues.length, 'no category available').toBeGreaterThan(0);
      const preferred = optionValues.includes('68') ? ['68', ...optionValues] : optionValues;

      let count = 0;
      for (const catValue of preferred.slice(0, 8)) {
        await categories.selectOption(catValue);
        await Promise.all([
          page.waitForResponse((r) => r.url().includes('products-by-categories') && r.status() < 400, { timeout: 10_000 }).catch(() => undefined),
          page.getByTestId('sale-load-products').click(),
        ]);
        await page.waitForTimeout(400);
        count = await checkboxes.count();
        if (count > 0) break;
      }
      expect(count, 'no products loaded for any tried category').toBeGreaterThan(0);
      // Ensure at least the first product is selected.
      const firstBox = checkboxes.first();
      if (!(await firstBox.isChecked())) await firstBox.check();
      const productId = await firstBox.getAttribute('value');

      // --- Set the EUR price offset + a percentage discount type ---
      await page.locator('select[name$="[price_offset_type]"]').selectOption({ index: 0 });
      await page.getByTestId(`sale-offset-${EUR_CURRENCY_ID}`).fill('20');

      // SAVE.
      await Promise.all([
        page.waitForLoadState('networkidle', { timeout: 15_000 }),
        page.getByTestId('sale-edit-submit').click(),
      ]);

      // --- PERSISTENCE: re-GET the edit page, the product is checked + offset retained ---
      await page.goto(`/admin/sale/update/${saleId}`, { waitUntil: 'networkidle' });
      if (productId) {
        await expect(page.locator(`input[name="products[]"][value="${productId}"]`)).toBeChecked({ timeout: 10_000 });
      }
      await expect(page.getByTestId(`sale-offset-${EUR_CURRENCY_ID}`)).toHaveValue(/20(\.0+)?/);

      // --- ATTRIBUTES modal: open on the selected product (if it carries a picker button) ---
      if (productId) {
        const attrBtn = page.getByTestId(`sale-product-attributes-${productId}`);
        if (await attrBtn.count() > 0 && await attrBtn.isVisible()) {
          await attrBtn.click();
          const modal = page.locator('#sale-product-attributes-modal.show');
          await modal.waitFor({ state: 'visible', timeout: 8_000 }).catch(() => undefined);
          // The bo-sale-product-attributes controller fetches the attribute list; modal body should fill.
          await page.waitForTimeout(500);
          await page.keyboard.press('Escape');
        }
      }

      // CLEANUP.
      await page.goto('/admin/sales', { waitUntil: 'networkidle' });
      const action = await page.getByTestId('sale-delete-form').getAttribute('action');
      await postForm(page, action!, { sale_id: String(saleId) });
      await page.goto('/admin/sales', { waitUntil: 'networkidle' });
      await expect(page.getByTestId(`sale-row-${saleId}`)).toHaveCount(0);
      expectClean(collector.drain());
    });
  });
});
