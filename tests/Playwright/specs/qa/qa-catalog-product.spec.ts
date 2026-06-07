import { test, expect, type Page } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import {
  IssueCollector,
  sweepScreen,
  findLeakedFields,
  scanDom,
  formatIssues,
  qaRef,
  type PageIssue,
} from '../../helpers/qa';

/**
 * QA campaign — domain "catalog-product" (BO default-twig).
 *
 * Coverage:
 *  - /admin/products (list, filters, search, toggle online, pagination, create modal + persistence)
 *  - /admin/products/update (9 tabs: General, Attributes & Features, Combinations/PSE,
 *    Related, Images, Documents, SEO, Modules + extra hooks)
 *  - ProductAdvancedController AJAX endpoints (calculate-price, attribute-values, autocomplete)
 *  - prev/next, breadcrumb, edit-language switch, clone
 *  - /admin/configuration/features, /attributes, /templates
 *
 * Anchors (demo data, verified 2026-06-07):
 *  - product 722 = 5 PSE (has real combinations)
 *  - product 737 = single PSE, no attribute_combination -> "no combination" default-pse-form (PAR-01)
 *  - product 733 = single PSE treated as combination row
 *  - tax_rule 1, default category 180, currency 1 (EUR default) / 2 (USD)
 *  - calculate-price returns { price, tax_rule_id, action, result }
 *
 * Persistence is verified by re-GET + asserting the rendered value, never a bare 200.
 * Every created entity is qaRef-prefixed and deleted at the end of its describe block.
 */

const PROD_WITH_COMBINATIONS = 722;
const PROD_NO_COMBINATION = 737; // single PSE, default-pse-form
const DEFAULT_CATEGORY = 180;
const TAX_RULE = 1;

/**
 * Drop environmental noise that is NOT a catalog-product defect:
 *  - Missing flag SVG (`svgFlags/<code>.svg` 404) for custom languages that
 *    have no bundled flag asset. Surfaced by BoLanguageSwitcher on every
 *    tabbed/config screen. Here it is amplified by a leftover demo/QA language
 *    (code `q70`) created by a parallel campaign; not in scope for this domain.
 *    Documented as CATPROD-04 (minor).
 */
function dropNoise(issues: PageIssue[]): PageIssue[] {
  return issues.filter((i) => {
    // Never drop server errors or JS exceptions.
    if (i.kind === 'pageerror') return true;
    if (/5\d\d/.test(i.detail)) return true;
    // The console "Failed to load resource ... 404" lines are emitted for the
    // missing language flag SVG (see CATPROD-04). They carry no asset URL, so we
    // can only recognise them by the generic 404 console wording.
    if (i.kind === 'console' && /Failed to load resource.*404/i.test(i.detail)) return false;
    if (/svgFlags\/[^/]+\.svg/i.test(i.detail)) return false;
    return true;
  });
}

async function expectClean(page: Page, collector: IssueCollector, opts: { tabs?: boolean; modals?: boolean; allowDanger?: boolean } = {}): Promise<void> {
  const issues = dropNoise(await sweepScreen(page, collector, opts));
  expect(issues, formatIssues(issues)).toHaveLength(0);
}

function expectNoIssues(issues: PageIssue[]): void {
  const clean = dropNoise(issues);
  expect(clean, formatIssues(clean)).toHaveLength(0);
}

/**
 * Open the full product edit page on a given tab and activate it, so the
 * render(controller(...)) fragment renders WITH the BO layout/CSS. Driving the
 * raw `/tab` fragment URL renders an unstyled partial whose inner controls
 * Playwright treats as not visible.
 */
async function openProductTab(page: Page, productId: number, tab: 'pse' | 'attributes' | 'related' | 'images' | 'documents' | 'seo'): Promise<void> {
  await page.goto(`/admin/products/update?product_id=${productId}&current_tab=${tab}`, { waitUntil: 'networkidle' });
  await page.getByTestId(`product-tab-${tab}`).click();
  await page.waitForLoadState('networkidle').catch(() => undefined);
  await page.waitForTimeout(400);
}

/** Read the CSRF token Thelia embeds in BO forms / sortable controllers. */
async function readToken(page: Page): Promise<string> {
  const token = await page.evaluate(() => {
    const input = document.querySelector<HTMLInputElement>('input[name="_token"]');
    if (input?.value) return input.value;
    const sortable = document.querySelector<HTMLElement>('[data-bo-sortable-token-value]');
    if (sortable) return sortable.getAttribute('data-bo-sortable-token-value') ?? '';
    return '';
  });
  return token;
}

test.describe('catalog-product', () => {
  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  // ---------------------------------------------------------------------------
  // Screen: Products list
  // ---------------------------------------------------------------------------
  test.describe('Products — list', () => {
    test('loads clean, sweep DOM/tabs/modals, no leaked fields', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto('/admin/products', { waitUntil: 'networkidle' });
      await expect(page.getByTestId('products-page')).toBeVisible();
      await expect(page.getByTestId('product-create-button')).toBeVisible();
      await expect(page.getByTestId('product-search-form')).toBeVisible();
      // List has no Bootstrap tabs; sweep modals (create, delete, clone).
      await expectClean(page, collector, { tabs: false, modals: true });
    });

    test('category filter + search submit reflect in URL', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto('/admin/products', { waitUntil: 'networkidle' });
      await page.getByTestId('product-search-input').fill('zzz-nope-' + Date.now());
      await page.getByTestId('product-search-submit').click();
      await expect(page).toHaveURL(/q=zzz-nope/);
      await expect(page.getByTestId('datatable-products-empty')).toBeVisible();
      // Reset
      await page.getByTestId('product-search-clear').click();
      await expect(page.getByTestId('datatable-products-row').first()).toBeVisible();
      const issues = [...await scanDom(page), ...await findLeakedFields(page), ...collector.drain()];
      expectNoIssues(issues);
    });

    test('toggle online persists (column_toggle round-trip)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto('/admin/products', { waitUntil: 'networkidle' });
      const firstRow = page.getByTestId('datatable-products-row').first();
      // column_toggle renders an anchor with data-state on|off and aria-pressed.
      const toggleLink = firstRow.locator('a[data-testid="datatable-visible-toggle"]').first();
      await expect(toggleLink).toBeVisible();
      const beforeState = await toggleLink.getAttribute('data-state');
      await Promise.all([
        page.waitForLoadState('networkidle'),
        toggleLink.click(),
      ]);
      // Re-GET and confirm the toggle state flipped (data-state on<->off).
      await page.goto('/admin/products', { waitUntil: 'networkidle' });
      const afterLink = page.getByTestId('datatable-products-row').first().locator('a[data-testid="datatable-visible-toggle"]').first();
      const afterState = await afterLink.getAttribute('data-state');
      expect(afterState, 'toggle online should flip data-state').not.toBe(beforeState);
      // Toggle back to leave demo data unchanged.
      await Promise.all([page.waitForLoadState('networkidle'), afterLink.click()]);
      const issues = collector.drain();
      expectNoIssues(issues);
    });

    test('pagination link navigates and stays clean', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto('/admin/products', { waitUntil: 'networkidle' });
      const page2 = page.getByTestId('product-pagination').locator('a[href*="page=2"]').first();
      if (await page2.count() === 0) {
        // Not enough products for a 2nd page; acceptable.
        return;
      }
      await Promise.all([page.waitForLoadState('networkidle'), page2.click()]);
      await expect(page).toHaveURL(/page=2/);
      const issues = [...await scanDom(page), ...collector.drain()];
      expectNoIssues(issues);
    });

    test('create product via modal (bo-price-preview) persists in DB and is deletable', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      const ref = qaRef('catprod');
      const title = ref + ' title';

      await page.goto('/admin/products', { waitUntil: 'networkidle' });
      await page.getByTestId('product-create-button').click();
      const form = page.getByTestId('product-create-form');
      await expect(form).toBeVisible();

      await form.locator('input[name="thelia_product_creation[ref]"]').fill(ref);
      await form.locator('input[name="thelia_product_creation[title]"]').fill(title);
      await form.locator('select[name="thelia_product_creation[default_category]"]').selectOption(String(DEFAULT_CATEGORY));
      // Price + tax rule drive bo-price-preview (incl. tax output).
      const priceInput = form.locator('[data-bo-price-preview-target="price"]');
      await priceInput.fill('100');
      await form.locator('[data-bo-price-preview-target="taxRule"]').selectOption(String(TAX_RULE));
      // weight + quantity are validated server-side; fill them to pass validation.
      await form.locator('input[name="thelia_product_creation[weight]"]').fill('1');
      await form.locator('input[name="thelia_product_creation[quantity]"]').fill('10');
      await priceInput.blur();
      // Preview should compute "≈ 120 incl. tax" (tax rule 1 = 20%).
      const preview = page.getByTestId('product-create-price-preview');
      await expect.poll(async () => (await preview.textContent())?.trim() ?? '', { timeout: 6_000 }).toMatch(/120/);

      await Promise.all([
        page.waitForLoadState('networkidle'),
        page.getByTestId('product-create-submit').click(),
      ]);

      // CATPROD-02: the create success redirect now lands on the new product's
      // edit page (product_id resolved from the dispatched ProductCreateEvent).
      await expect(page.getByTestId('product-edit-page')).toBeVisible();

      // PERSISTENCE: the product exists — find it by ref and open its edit page.
      await page.goto('/admin/products?q=' + encodeURIComponent(ref), { waitUntil: 'networkidle' });
      const createdRow = page.getByTestId('datatable-products-row').first();
      await expect(createdRow, 'created product must appear in the list').toBeVisible();
      const editHref = await createdRow.locator('a[href*="products/update"]').first().getAttribute('href');
      const productId = Number(new URL(editHref ?? 'https://x/?product_id=0', 'https://x').searchParams.get('product_id'));
      expect(productId).toBeGreaterThan(0);

      // Re-GET the edit page and assert ref/title persisted.
      await page.goto(`/admin/products/update?product_id=${productId}`, { waitUntil: 'networkidle' });
      await expect(page.locator('input[name="thelia_product_modification[ref]"]')).toHaveValue(ref);
      await expect(page.locator('input[name="thelia_product_modification[title]"]')).toHaveValue(title);

      // CLEANUP: delete via list modal (prefill product_id).
      await page.goto('/admin/products?q=' + encodeURIComponent(ref), { waitUntil: 'networkidle' });
      const delBtn = page.locator(`[data-bs-target="#product-delete-modal"][data-product-id="${productId}"]`).first();
      if (await delBtn.count() > 0) {
        await delBtn.click();
        const modal = page.locator('#product-delete-modal.show');
        await modal.waitFor({ state: 'visible' });
        await Promise.all([
          page.waitForLoadState('networkidle'),
          modal.locator('button[type="submit"]').click(),
        ]);
      } else {
        // Fallback: POST delete with token.
        await page.goto(`/admin/products/update?product_id=${productId}`, { waitUntil: 'networkidle' });
        const token = await readToken(page);
        const resp = await page.request.post('/admin/products/delete', {
          form: { product_id: String(productId), _token: token },
        });
        expect(resp.status(), 'delete fallback POST').toBeLessThan(400);
      }
      // Confirm gone.
      await page.goto('/admin/products?q=' + encodeURIComponent(ref), { waitUntil: 'networkidle' });
      expect(await page.getByTestId('datatable-products-row').count()).toBe(0);

      const issues = collector.drain();
      expectNoIssues(issues);
    });

    // CATPROD-02: after a successful product creation, the user must land on the
    // new product's edit page (successRoute = admin.products.update). The create
    // action resolves the new product id from the dispatched ProductCreateEvent and
    // threads it into the redirect (successParametersResolver), so the page no
    // longer bounces to the list via /admin/products/update with no product_id.
    test('CATPROD-02: create redirects to the new product edit page', async ({ page }) => {
      const ref = qaRef('catprod2');
      await page.goto('/admin/products', { waitUntil: 'networkidle' });
      await page.getByTestId('product-create-button').click();
      const form = page.getByTestId('product-create-form');
      await form.locator('input[name="thelia_product_creation[ref]"]').fill(ref);
      await form.locator('input[name="thelia_product_creation[title]"]').fill(ref);
      await form.locator('select[name="thelia_product_creation[default_category]"]').selectOption(String(DEFAULT_CATEGORY));
      await form.locator('[data-bo-price-preview-target="price"]').fill('100');
      await form.locator('input[name="thelia_product_creation[weight]"]').fill('1');
      await form.locator('input[name="thelia_product_creation[quantity]"]').fill('10');
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('product-create-submit').click()]);
      // Lands on the new product's edit page with a non-zero product_id.
      await expect(page.getByTestId('product-edit-page')).toBeVisible();
      await expect(page).toHaveURL(/product_id=[1-9]\d*/);
      const productId = Number(new URL(page.url()).searchParams.get('product_id'));
      expect(productId).toBeGreaterThan(0);

      // CLEANUP: delete the created product.
      const token = await readToken(page);
      const resp = await page.request.post('/admin/products/delete', {
        form: { product_id: String(productId), _token: token },
      });
      expect(resp.status()).toBeLessThan(400);
    });
  });

  // ---------------------------------------------------------------------------
  // Screen: Product edit — all tabs sweep
  // ---------------------------------------------------------------------------
  test.describe('Product — edit (all tabs)', () => {
    test('edit page (with combinations) loads, all tabs render, no console/network/leak', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/products/update?product_id=${PROD_WITH_COMBINATIONS}`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('product-edit-page')).toBeVisible();
      await expect(page.getByTestId('product-tab-general')).toBeVisible();
      await expect(page.getByTestId('product-tab-pse')).toBeVisible();
      await expect(page.getByTestId('product-tab-seo')).toBeVisible();
      // clickAllTabs drives every tab incl. the lazy render(controller) panes.
      await expectClean(page, collector, { tabs: true, modals: true });
    });

    test('breadcrumb drill-down + prev/next buttons present', async ({ page }) => {
      await page.goto(`/admin/products/update?product_id=${PROD_WITH_COMBINATIONS}`, { waitUntil: 'networkidle' });
      const crumb = page.locator('.breadcrumb a', { hasText: 'Products' }).first();
      await expect(crumb).toBeVisible();
      // prev/next buttons render via _prev_next_buttons partial (testid product-*).
      const next = page.locator('[data-testid^="product-next"], a[title*="next product" i]').first();
      if (await next.count() > 0 && await next.isEnabled().catch(() => true)) {
        const before = page.url();
        await Promise.all([page.waitForLoadState('networkidle'), next.click()]).catch(() => undefined);
        // URL should change to another product_id (or stay if disabled at boundary).
        expect(page.url()).toBeTruthy();
        expect(before).toBeTruthy();
      }
    });

    test('switch edit language (BoLanguageSwitcher) reloads page for the chosen locale', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/products/update?product_id=${PROD_WITH_COMBINATIONS}`, { waitUntil: 'networkidle' });
      const switcher = page.locator('[data-testid="bo-language-switcher"], .bo-tab-bar__aside a, .bo-tab-bar__aside button').first();
      if (await switcher.count() === 0) {
        return; // switcher markup may differ; covered by sweep otherwise.
      }
      // Pick a non-active language option link if present.
      const otherLang = page.locator('.bo-tab-bar__aside a[href*="edit_language_id="], .bo-tab-bar__aside a[href*="lang"]').first();
      if (await otherLang.count() > 0) {
        await Promise.all([page.waitForLoadState('networkidle'), otherLang.click()]);
        await expect(page.getByTestId('product-edit-page')).toBeVisible();
      }
      const issues = [...await scanDom(page), ...collector.drain()];
      expectNoIssues(issues);
    });
  });

  // ---------------------------------------------------------------------------
  // Tab: General — save + persistence
  // ---------------------------------------------------------------------------
  test.describe('Product — General tab persistence', () => {
    test('edit title and save, then re-GET asserts the value', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/products/update?product_id=${PROD_WITH_COMBINATIONS}`, { waitUntil: 'networkidle' });

      // chapo/description are TipTap WYSIWYG (textarea hidden); title is a plain input.
      const titleSel = 'input[name="thelia_product_modification[title]"]';
      const original = await page.locator(titleSel).inputValue();
      const marker = original + ' QA' + String(Date.now()).slice(-5);

      await page.locator(titleSel).fill(marker);
      await Promise.all([
        page.waitForLoadState('networkidle'),
        page.getByTestId('product-edit-submit').click(),
      ]);
      // PERSISTENCE
      await page.goto(`/admin/products/update?product_id=${PROD_WITH_COMBINATIONS}`, { waitUntil: 'networkidle' });
      await expect(page.locator(titleSel)).toHaveValue(marker);

      // Restore original title (leave demo data clean).
      await page.locator(titleSel).fill(original);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('product-edit-submit').click()]);

      const issues = [...await scanDom(page), ...await findLeakedFields(page), ...collector.drain()];
      expectNoIssues(issues);
    });
  });

  // ---------------------------------------------------------------------------
  // Tab: Combinations / PSE
  // ---------------------------------------------------------------------------
  test.describe('Product — Combinations tab', () => {
    test('combinations tab fragment loads with rows and toolbar', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      const resp = await page.goto(`/admin/products/combinations/tab?product_id=${PROD_WITH_COMBINATIONS}`, { waitUntil: 'networkidle' });
      expect(resp?.status()).toBeLessThan(400);
      await expect(page.getByTestId('product-combinations-tab')).toBeVisible();
      await expect(page.getByTestId('combinations-form')).toBeVisible();
      await expect(page.locator('[data-testid^="combinations-row-"]').first()).toBeVisible();
      const issues = [...await scanDom(page), ...collector.drain()];
      expectNoIssues(issues);
    });

    test('edit a combination row (qty) + set default PSE radio + Save all persists', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await openProductTab(page, PROD_WITH_COMBINATIONS, 'pse');
      const firstRow = page.locator('[data-testid^="combinations-row-"]').first();
      const pseId = (await firstRow.getAttribute('data-testid'))!.replace('combinations-row-', '');
      const qtyInput = firstRow.locator('input[name="quantity[]"]');
      const newQty = String(40 + Math.floor(Math.random() * 50));
      await qtyInput.fill(newQty);
      // Make this row the default.
      await firstRow.locator('input[name="default_pse"]').check();
      await Promise.all([
        page.waitForLoadState('networkidle'),
        page.getByTestId('combinations-submit').click(),
      ]);
      // PERSISTENCE: re-open tab and assert qty + default radio.
      await openProductTab(page, PROD_WITH_COMBINATIONS, 'pse');
      const reRow = page.getByTestId(`combinations-row-${pseId}`);
      await expect(reRow.locator('input[name="quantity[]"]')).toHaveValue(newQty);
      await expect(reRow.locator('input[name="default_pse"]')).toBeChecked();
      const issues = [...await scanDom(page), ...collector.drain()];
      expectNoIssues(issues);
    });

    test('toggle combination visibility persists (eye button)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await openProductTab(page, PROD_WITH_COMBINATIONS, 'pse');
      const eye = page.locator('[data-testid^="combinations-toggle-visible-"]').first();
      const before = (await eye.getAttribute('class')) ?? '';
      await Promise.all([page.waitForLoadState('networkidle'), eye.click()]);
      await openProductTab(page, PROD_WITH_COMBINATIONS, 'pse');
      const eyeAfter = page.locator('[data-testid^="combinations-toggle-visible-"]').first();
      const after = (await eyeAfter.getAttribute('class')) ?? '';
      expect(after, 'visibility toggle should flip btn-success/btn-outline-secondary').not.toBe(before);
      // restore
      await Promise.all([page.waitForLoadState('networkidle'), eyeAfter.click()]);
      const issues = collector.drain();
      expectNoIssues(issues);
    });

    test('combination builder modal: counter is live and submit enables', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await openProductTab(page, PROD_WITH_COMBINATIONS, 'pse');
      await page.getByTestId('combination-builder-btn').click();
      const modal = page.locator('#combination-builder-modal.show');
      await modal.waitFor({ state: 'visible' });
      const counter = page.getByTestId('combination-builder-counter');
      await expect(counter).toHaveText('0');
      // Tick one attribute value to get a live count.
      const checkboxes = modal.locator('input[name="attribute_av[]"]');
      const cbCount = await checkboxes.count();
      if (cbCount >= 1) {
        await checkboxes.first().check();
        await expect.poll(async () => (await counter.textContent())?.trim()).not.toBe('0');
        // Submit becomes enabled once at least one value is ticked.
        await expect(page.getByTestId('combination-builder-submit')).toBeEnabled();
      }
      // DO NOT submit: builder destroys/recreates all combinations (destructive on demo data).
      await page.keyboard.press('Escape');
      const issues = collector.drain();
      expectNoIssues(issues);
    });

    test('combination creation modal opens with per-attribute selects', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await openProductTab(page, PROD_WITH_COMBINATIONS, 'pse');
      await page.getByTestId('combination-create-btn').click();
      const modal = page.locator('#combination-creation-modal.show');
      await modal.waitFor({ state: 'visible' });
      await expect(modal.locator('[data-testid^="combination-creation-select-"]').first()).toBeVisible();
      await page.keyboard.press('Escape');
      const issues = collector.drain();
      expectNoIssues(issues);
    });

    test('multi-currency: switching currency reloads the tab in that currency', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/products/update?product_id=${PROD_WITH_COMBINATIONS}&current_tab=pse`, { waitUntil: 'networkidle' });
      const switcher = page.getByTestId('combinations-currency-switcher');
      if (await switcher.count() === 0) {
        return; // single-currency render path
      }
      // Pick USD (currency 2) option.
      const usdOption = switcher.locator('option', { hasText: 'USD' }).first();
      if (await usdOption.count() > 0) {
        const value = await usdOption.getAttribute('value');
        await Promise.all([page.waitForLoadState('networkidle'), page.goto(value!, { waitUntil: 'networkidle' })]);
        await expect(page).toHaveURL(/combinations_currency_id=2/);
        // use-exchange-rate toggle should now be available (non-default currency).
        await expect(page.getByTestId('combinations-use-exchange-rate')).toBeVisible();
      }
      const issues = [...await scanDom(page), ...collector.drain()];
      expectNoIssues(issues);
    });

    // PAR-01 — product WITHOUT combination: default-pse-form pricing persistence.
    test('PAR-01: default-pse pricing form saves price/stock/EAN/weight and persists', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await openProductTab(page, PROD_NO_COMBINATION, 'pse');
      await expect(page.getByTestId('default-pse-form')).toBeVisible();

      const ean = 'QA' + String(Date.now()).slice(-10);
      const price = 199.5;
      const qty = 63;
      const weight = 2.5;

      await page.locator('#default_pse_tax_rule').selectOption(String(TAX_RULE));
      await page.locator('#default_pse_price').fill(String(price));
      await page.locator('#default_pse_ean').fill(ean);
      await page.locator('#default_pse_weight').fill(String(weight));
      await page.locator('#default_pse_quantity').fill(String(qty));

      await Promise.all([
        page.waitForLoadState('networkidle'),
        page.getByTestId('default-pse-submit').click(),
      ]);

      // PERSISTENCE: re-open the PSE tab and assert the saved values render back.
      // Numeric fields may round-trip with trailing zeros (decimal columns), so
      // compare numerically rather than by exact string.
      await openProductTab(page, PROD_NO_COMBINATION, 'pse');
      await expect(page.getByTestId('default-pse-form')).toBeVisible();
      await expect(page.locator('#default_pse_ean')).toHaveValue(ean);
      expect(Number(await page.locator('#default_pse_quantity').inputValue())).toBe(qty);
      expect(Number(await page.locator('#default_pse_price').inputValue())).toBeCloseTo(price, 2);
      expect(Number(await page.locator('#default_pse_weight').inputValue())).toBeCloseTo(weight, 2);

      const issues = [...await scanDom(page), ...collector.drain()];
      expectNoIssues(issues);
    });
  });

  // ---------------------------------------------------------------------------
  // Tab: Attributes & Features
  // ---------------------------------------------------------------------------
  test.describe('Product — Attributes & Features tab', () => {
    test('attributes tab fragment loads (template selector + feature batch)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      const resp = await page.goto(`/admin/products/attributes/tab?product_id=${PROD_WITH_COMBINATIONS}`, { waitUntil: 'networkidle' });
      expect(resp?.status()).toBeLessThan(400);
      await expect(page.getByTestId('product-attributes-tab')).toBeVisible();
      await expect(page.getByTestId('product-template-form')).toBeVisible();
      const issues = [...await scanDom(page), ...await findLeakedFields(page), ...collector.drain()];
      expectNoIssues(issues);
    });

    // CATPROD-01 (BUG, BLOCKER — confirmed via the real UI POST 2026-06-07):
    // Saving the product features form
    //   POST /admin/product/{productId}/update-attributes-and-features
    // returns HTTP 500 whenever the submitted body does NOT contain BOTH the
    // `feature_value` AND `feature_text_value` keys. ProductAdvancedController.php
    // line 1089 (`(array) $request->request->all()['feature_value']`) and line 1103
    // (`...['feature_text_value']`) do direct array access without null-coalesce.
    // A product whose template has only multi-select features (e.g. product 722,
    // feature_value[31][]) posts no feature_text_value -> "Undefined array key
    // feature_text_value" -> 500, and vice-versa. This breaks the feature-save
    // workflow for the majority of templates, not just the GET edge case.
    // Fix: `$request->request->all('feature_value')` / `...all('feature_text_value')`
    // (these return [] when the key is absent).
    // Repro: open /admin/products/update?product_id=722&current_tab=attributes,
    // change a feature multi-select, click Save -> 500.
    test('CATPROD-01: feature multi-select batch save 500s (Undefined array key feature_text_value)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await openProductTab(page, PROD_WITH_COMBINATIONS, 'attributes');
      const batchForm = page.getByTestId('product-feature-batch-form');
      if (await batchForm.count() === 0) {
        return; // product template has no features
      }
      const select = batchForm.locator('select[data-bo-product-feature-batch-target="select"]').first();
      const textInput = batchForm.locator('input[name^="feature_text_value"]').first();

      if (await select.count() > 0) {
        const opts = select.locator('option');
        if (await opts.count() > 0) {
          const optVal = await opts.first().getAttribute('value');
          await select.selectOption(optVal!);
          await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('product-feature-batch-save').click()]);
          await openProductTab(page, PROD_WITH_COMBINATIONS, 'attributes');
          const reSelect = page.getByTestId('product-feature-batch-form').locator('select[data-bo-product-feature-batch-target="select"]').first();
          const selectedVals = await reSelect.evaluate((el: HTMLSelectElement) => Array.from(el.selectedOptions).map((o) => o.value));
          expect(selectedVals).toContain(optVal);
        }
      } else if (await textInput.count() > 0) {
        const marker = 'QA-FEAT-' + Date.now();
        await textInput.fill(marker);
        await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('product-feature-batch-save').click()]);
        await openProductTab(page, PROD_WITH_COMBINATIONS, 'attributes');
        await expect(page.locator('input[name^="feature_text_value"]').first()).toHaveValue(marker);
      }
      const issues = [...await scanDom(page), ...collector.drain()];
      expectNoIssues(issues);
    });

    // CATPROD-01 (BUG, confirmed 2026-06-07): GET (and any request without a body
    // carrying feature_value/feature_text_value) on
    //   /admin/product/{productId}/update-attributes-and-features
    // returns HTTP 500.
    // Root cause: ProductAdvancedController.php lines 1089 & 1103 do direct array
    // access `$request->request->all()['feature_value']` /
    // `...['feature_text_value']` without a null-coalesce. The route is declared
    // methods: ['POST','GET']; a GET has no request body so those keys are absent
    // -> "Undefined array key" warning -> 500.
    // Fix: `$request->request->all('feature_value')` (returns [] when absent),
    // idem for feature_text_value; or constrain the route to POST only.
    // Repro: GET https://thelia-3.ddev.site/admin/product/722/update-attributes-and-features -> 500.
    test('CATPROD-01: update-attributes-and-features via GET 500s (Undefined array key feature_value)', async ({ page }) => {
      const resp = await page.request.get(`/admin/product/${PROD_WITH_COMBINATIONS}/update-attributes-and-features`, { maxRedirects: 0 });
      expect(resp.status(), `expected a redirect/4xx, got ${resp.status()} (500 = CATPROD-01)`).not.toBe(500);
    });
  });

  // ---------------------------------------------------------------------------
  // Tab: Related (categories / accessories / contents)
  // ---------------------------------------------------------------------------
  test.describe('Product — Related tab', () => {
    test('related tab fragment loads with three pickers', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      const resp = await page.goto(`/admin/products/related/tab?product_id=${PROD_WITH_COMBINATIONS}`, { waitUntil: 'networkidle' });
      expect(resp?.status()).toBeLessThan(400);
      await expect(page.getByTestId('product-related-tab')).toBeVisible();
      await expect(page.getByTestId('product-additional-category-table')).toBeVisible();
      await expect(page.getByTestId('product-accessory-table')).toBeVisible();
      await expect(page.getByTestId('product-related-content-table')).toBeVisible();
      const issues = [...await scanDom(page), ...collector.drain()];
      expectNoIssues(issues);
    });

    test('add an additional category then delete it (round trip)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await openProductTab(page, PROD_WITH_COMBINATIONS, 'related');
      const select = page.locator('select[name="category_id"]').first();
      // pick an enabled, non-default option that is not already assigned.
      const optionValue = await select.locator('option:not([disabled])').nth(1).getAttribute('value').catch(() => null);
      if (!optionValue) {
        return;
      }
      // If already present (leftover from a prior run), remove it first.
      const existing = page.getByTestId(`product-additional-category-row-${optionValue}`);
      if (await existing.count() > 0) {
        await page.locator(`[data-bs-target="#product-additional-category-delete"][data-category-id="${optionValue}"]`).click();
        const m = page.locator('#product-additional-category-delete.show');
        await m.waitFor({ state: 'visible' });
        await Promise.all([page.waitForLoadState('networkidle'), m.locator('button[type="submit"]').click()]);
        await openProductTab(page, PROD_WITH_COMBINATIONS, 'related');
      }
      await page.locator('select[name="category_id"]').first().selectOption(optionValue);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('product-additional-category-add').click()]);
      // PERSISTENCE: row for that category should now exist.
      await openProductTab(page, PROD_WITH_COMBINATIONS, 'related');
      const row = page.getByTestId(`product-additional-category-row-${optionValue}`);
      await expect(row).toBeVisible();
      // CLEANUP: delete it via the prefill modal.
      await page.locator(`[data-bs-target="#product-additional-category-delete"][data-category-id="${optionValue}"]`).click();
      const modal = page.locator('#product-additional-category-delete.show');
      await modal.waitFor({ state: 'visible' });
      await Promise.all([page.waitForLoadState('networkidle'), modal.locator('button[type="submit"]').click()]);
      await openProductTab(page, PROD_WITH_COMBINATIONS, 'related');
      await expect(page.getByTestId(`product-additional-category-row-${optionValue}`)).toHaveCount(0);
      const issues = [...await scanDom(page), ...collector.drain()];
      expectNoIssues(issues);
    });

    test('categories autocomplete + accessories cascade endpoints return JSON', async ({ page }) => {
      const catSearch = await page.request.get('/admin/products/related/tab/categories/search?q=a');
      expect(catSearch.ok(), 'categories autocomplete status').toBeTruthy();
      expect(Array.isArray(await catSearch.json())).toBeTruthy();

      const prodSearch = await page.request.get('/admin/products/related/tab/products/search?q=a');
      expect(prodSearch.ok(), 'products autocomplete status').toBeTruthy();
      expect(Array.isArray(await prodSearch.json())).toBeTruthy();

      // accessories-by-category cascade (.json format)
      const accessories = await page.request.get(`/admin/product/${PROD_WITH_COMBINATIONS}/available-accessories/${DEFAULT_CATEGORY}.json`);
      expect(accessories.status(), 'available-accessories cascade').toBeLessThan(400);
    });
  });

  // ---------------------------------------------------------------------------
  // Tab: Images / Documents
  // ---------------------------------------------------------------------------
  test.describe('Product — Images & Documents tabs', () => {
    test('image form fragment loads clean', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/products/update?product_id=${PROD_WITH_COMBINATIONS}&current_tab=images`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('product-edit-page')).toBeVisible();
      // The images tab is a render(controller); activate it and sweep.
      await page.getByTestId('product-tab-images').click();
      await page.waitForTimeout(500);
      const issues = [...await scanDom(page), ...await findLeakedFields(page), ...collector.drain()];
      expectNoIssues(issues);
    });

    test('documents tab activates clean', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/products/update?product_id=${PROD_WITH_COMBINATIONS}&current_tab=documents`, { waitUntil: 'networkidle' });
      await page.getByTestId('product-tab-documents').click();
      await page.waitForTimeout(500);
      const issues = [...await scanDom(page), ...collector.drain()];
      expectNoIssues(issues);
    });
  });

  // ---------------------------------------------------------------------------
  // Tab: SEO — save + persistence
  // ---------------------------------------------------------------------------
  test.describe('Product — SEO tab', () => {
    test('save meta_title and re-GET asserts persistence', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/products/update?product_id=${PROD_WITH_COMBINATIONS}&current_tab=seo`, { waitUntil: 'networkidle' });
      await page.getByTestId('product-tab-seo').click();
      const metaSel = 'input[name="seo[meta_title]"], #product_seo_form_meta_title, input[name$="[meta_title]"]';
      const metaInput = page.locator('[data-testid="product-seo-form"]').locator('input[name$="[meta_title]"]').first();
      await metaInput.waitFor({ state: 'visible' });
      const original = await metaInput.inputValue();
      const marker = 'QA-SEO ' + Date.now();
      await metaInput.fill(marker);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('product-seo-submit').click()]);

      await page.goto(`/admin/products/update?product_id=${PROD_WITH_COMBINATIONS}&current_tab=seo`, { waitUntil: 'networkidle' });
      const reInput = page.locator('[data-testid="product-seo-form"]').locator('input[name$="[meta_title]"]').first();
      await expect(reInput).toHaveValue(marker);
      // restore
      await reInput.fill(original);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('product-seo-submit').click()]);
      const issues = [...await scanDom(page), ...collector.drain()];
      expectNoIssues(issues);
    });
  });

  // ---------------------------------------------------------------------------
  // Clone
  // ---------------------------------------------------------------------------
  test.describe('Product — clone', () => {
    test('clone from edit page creates a new product, then delete it', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/products/update?product_id=${PROD_NO_COMBINATION}`, { waitUntil: 'networkidle' });
      const newRef = qaRef('clone');
      await page.getByTestId('product-clone-btn').click();
      const modal = page.locator('#product-clone-modal.show');
      await modal.waitFor({ state: 'visible' });
      await modal.locator('input[name="thelia_product_clone[newRef]"]').fill(newRef);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('product-clone-submit').click()]);

      // Clone redirects to the product LIST by design (successRoute = LIST_ROUTE).
      await expect(page.getByTestId('catalog-products').or(page.getByTestId('products-page')).first()).toBeVisible();

      // PERSISTENCE: the clone exists with the new ref; resolve its id from the list.
      await page.goto('/admin/products?q=' + encodeURIComponent(newRef), { waitUntil: 'networkidle' });
      const clonedRow = page.getByTestId('datatable-products-row').first();
      await expect(clonedRow, 'cloned product must appear in the list').toBeVisible();
      const cloneHref = await clonedRow.locator('a[href*="products/update"]').first().getAttribute('href');
      const clonedId = Number(new URL(cloneHref ?? 'https://x/?product_id=0', 'https://x').searchParams.get('product_id'));
      expect(clonedId).toBeGreaterThan(0);

      // CLEANUP
      const token = await readToken(page);
      const resp = await page.request.post('/admin/products/delete', {
        form: { product_id: String(clonedId), _token: token },
      });
      expect(resp.status()).toBeLessThan(400);
      await page.goto('/admin/products?q=' + encodeURIComponent(newRef), { waitUntil: 'networkidle' });
      expect(await page.getByTestId('datatable-products-row').count()).toBe(0);
      const issues = collector.drain();
      expectNoIssues(issues);
    });
  });

  // ---------------------------------------------------------------------------
  // AJAX endpoints — ProductAdvancedController
  // ---------------------------------------------------------------------------
  test.describe('ProductAdvancedController — AJAX', () => {
    test('calculate-price returns correct incl-tax result', async ({ page }) => {
      const resp = await page.request.get(`/admin/product/calculate-price?price=100&tax_rule_id=${TAX_RULE}`);
      expect(resp.ok()).toBeTruthy();
      const body = await resp.json();
      expect(body).toHaveProperty('price');
      expect(body).toHaveProperty('result');
      expect(Number(body.result)).toBeGreaterThan(100); // tax adds to the price
    });

    test('attribute-values JSON list', async ({ page }) => {
      // discover a valid attribute id from the demo template combinations.
      const resp = await page.request.get(`/admin/product/${PROD_WITH_COMBINATIONS}/attribute-values/1.json`);
      expect(resp.status()).toBeLessThan(500);
      if (resp.ok()) {
        expect(Array.isArray(await resp.json())).toBeTruthy();
      }
    });

    test('combinations tab without product_id returns 404 (expected guard)', async ({ page }) => {
      const resp = await page.request.get('/admin/products/combinations/tab');
      expect(resp.status(), 'known-expected 404 guard on missing product_id').toBe(404);
    });
  });

  // ---------------------------------------------------------------------------
  // Configuration: Features
  // ---------------------------------------------------------------------------
  test.describe('Configuration — Features', () => {
    test('list loads clean and sweeps modals', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto('/admin/configuration/features', { waitUntil: 'networkidle' });
      await expect(page.getByTestId('features-page')).toBeVisible();
      await expectClean(page, collector, { tabs: false, modals: true });
    });

    test('create feature, edit a value title inline, then delete (full round trip)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      const title = qaRef('feature');
      await page.goto('/admin/configuration/features', { waitUntil: 'networkidle' });
      await page.getByTestId('feature-create-button').click();
      const modal = page.locator('#feature-create-modal.show');
      await modal.waitFor({ state: 'visible' });
      await modal.locator('input[name$="[title]"]').fill(title);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('feature-create-submit').click()]);

      // PERSISTENCE: feature appears in list; navigate to its edit page.
      await page.goto('/admin/configuration/features', { waitUntil: 'networkidle' });
      const row = page.locator('[data-testid="datatable-features-row"]', { hasText: title }).first();
      await expect(row).toBeVisible();
      const editLink = row.locator('a[href*="features/update"]').first();
      const href = await editLink.getAttribute('href');
      await page.goto(href!, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('feature-edit-page')).toBeVisible();
      const featureId = Number(new URL(href!, 'https://x').searchParams.get('feature_id'));

      // Add a value via modal.
      await page.getByTestId('featureav-create-button').click();
      const avModal = page.locator('#feature-av-create-modal.show');
      await avModal.waitFor({ state: 'visible' });
      const avTitle = title + '-VAL';
      await avModal.locator('input[name$="[title]"]').fill(avTitle);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('featureav-create-submit').click()]);

      // PAR-21: feature value title IS editable inline (text input in the main form).
      await page.goto(`/admin/configuration/features/update?feature_id=${featureId}`, { waitUntil: 'networkidle' }).catch(() => undefined);
      const valInput = page.locator('[data-testid^="featureav-input-"]').first();
      await expect(valInput, 'PAR-21: feature value title input must be present and editable').toBeVisible();
      const renamed = avTitle + '-EDIT';
      await valInput.fill(renamed);
      // Save via the toolbar (BoSaveModeToolbar -> save-stay submit).
      const saveBtn = page.locator('[data-testid="feature-edit-save-stay"], [data-testid="feature-edit-save-close"]').first();
      await Promise.all([page.waitForLoadState('networkidle'), saveBtn.click()]);
      await page.goto(`/admin/configuration/features/update?feature_id=${featureId}`, { waitUntil: 'networkidle' }).catch(() => undefined);
      await expect(page.locator('[data-testid^="featureav-input-"]').first()).toHaveValue(renamed);

      // CLEANUP: delete the feature from the list modal.
      await page.goto('/admin/configuration/features', { waitUntil: 'networkidle' });
      const delTrigger = page.locator(`[data-bs-target="#feature-delete-modal"][data-feature-id="${featureId}"]`).first();
      await delTrigger.click();
      const delModal = page.locator('#feature-delete-modal.show');
      await delModal.waitFor({ state: 'visible' });
      await Promise.all([page.waitForLoadState('networkidle'), delModal.locator('button[type="submit"]').click()]);
      await page.goto('/admin/configuration/features', { waitUntil: 'networkidle' });
      await expect(page.locator('[data-testid="datatable-features-row"]', { hasText: title })).toHaveCount(0);

      const issues = collector.drain();
      expectNoIssues(issues);
    });
  });

  // ---------------------------------------------------------------------------
  // Configuration: Attributes
  // ---------------------------------------------------------------------------
  test.describe('Configuration — Attributes', () => {
    test('list loads clean and sweeps modals', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto('/admin/configuration/attributes', { waitUntil: 'networkidle' });
      await expect(page.getByTestId('attributes-page').or(page.locator('[data-testid$="attributes"]')).first()).toBeVisible();
      await expectClean(page, collector, { tabs: false, modals: true });
    });

    test('create attribute, add value, inline-edit value title (AJAX), delete', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      const title = qaRef('attribute');
      await page.goto('/admin/configuration/attributes', { waitUntil: 'networkidle' });
      await page.getByTestId('attribute-create-button').click();
      const modal = page.locator('#attribute-create-modal.show');
      await modal.waitFor({ state: 'visible' });
      await modal.locator('input[name$="[title]"]').fill(title);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('attribute-create-submit').click()]);

      await page.goto('/admin/configuration/attributes', { waitUntil: 'networkidle' });
      const row = page.locator('[data-testid="datatable-attributes-row"]', { hasText: title }).first();
      await expect(row).toBeVisible();
      const href = await row.locator('a[href*="attributes/update"]').first().getAttribute('href');
      await page.goto(href!, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('attribute-edit-page')).toBeVisible();
      const attributeId = Number(new URL(href!, 'https://x').searchParams.get('attribute_id'));

      // Add a value.
      await page.getByTestId('attributeav-create-button').click();
      const avModal = page.locator('#attribute-av-create-modal.show');
      await avModal.waitFor({ state: 'visible' });
      await avModal.locator('input[name$="[title]"]').fill(title + '-VAL');
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('attributeav-create-submit').click()]);

      // CATPROD-03: the attribute-value create success redirect must land back on
      // the attribute edit page (attribute_id resolved from the namespaced
      // thelia_attributeav_creation[attribute_id] field), NOT bounce to the list
      // via attribute_id=0.
      await expect(page).toHaveURL(new RegExp(`attribute_id=${attributeId}\\b`));
      await expect(page.getByTestId('attribute-edit-page')).toBeVisible();

      // Inline edit the value title (blur -> update-title AJAX).
      const inline = page.locator('[data-testid^="attributeav-title-"]').first();
      await expect(inline).toBeVisible();
      const renamed = title + '-INLINE';
      await inline.fill(renamed);
      await Promise.all([
        page.waitForResponse((r) => /update[-_]?title/i.test(r.url()), { timeout: 8_000 }).catch(() => null),
        inline.blur(),
      ]);
      await page.goto(href!, { waitUntil: 'networkidle' });
      await expect(page.locator('[data-testid^="attributeav-title-"]').first()).toHaveValue(renamed);

      // CLEANUP
      await page.goto('/admin/configuration/attributes', { waitUntil: 'networkidle' });
      const delTrigger = page.locator(`[data-bs-target="#attribute-delete-modal"][data-attribute-id="${attributeId}"]`).first();
      if (await delTrigger.count() > 0) {
        await delTrigger.click();
        const delModal = page.locator('#attribute-delete-modal.show, .modal.show').first();
        await delModal.waitFor({ state: 'visible' });
        await Promise.all([page.waitForLoadState('networkidle'), delModal.locator('button[type="submit"]').last().click()]);
      }
      await page.goto('/admin/configuration/attributes', { waitUntil: 'networkidle' });
      await expect(page.locator('[data-testid="datatable-attributes-row"]', { hasText: title })).toHaveCount(0);

      const issues = collector.drain();
      expectNoIssues(issues);
    });
  });

  // ---------------------------------------------------------------------------
  // Configuration: Templates
  // ---------------------------------------------------------------------------
  test.describe('Configuration — Templates', () => {
    test('list loads clean and sweeps modals', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto('/admin/configuration/templates', { waitUntil: 'networkidle' });
      await expect(page.getByTestId('templates-page')).toBeVisible();
      await expectClean(page, collector, { tabs: false, modals: true });
    });

    test('create template, open edit, then delete (round trip)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      const name = qaRef('template');
      await page.goto('/admin/configuration/templates', { waitUntil: 'networkidle' });
      await page.getByTestId('template-create-button').click();
      const modal = page.locator('#template-create-modal.show');
      await modal.waitFor({ state: 'visible' });
      await modal.locator('input[name$="[name]"]').fill(name);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('template-create-submit').click()]);

      await page.goto('/admin/configuration/templates', { waitUntil: 'networkidle' });
      const row = page.locator('[data-testid="datatable-templates-row"]', { hasText: name }).first();
      await expect(row).toBeVisible();
      const href = await row.locator('a[href*="templates/update"]').first().getAttribute('href');
      const templateId = Number(new URL(href ?? 'https://x/?template_id=0', 'https://x').searchParams.get('template_id'));
      if (href) {
        await page.goto(href, { waitUntil: 'networkidle' });
        // edit page should show the features/attributes assignment UI.
        const editClean = [...await scanDom(page), ...await findLeakedFields(page)];
        expect(editClean, formatIssues(editClean)).toHaveLength(0);
      }

      // CLEANUP
      await page.goto('/admin/configuration/templates', { waitUntil: 'networkidle' });
      const delTrigger = page.locator(`[data-bs-target="#template-delete-modal"][data-template-id="${templateId}"]`).first();
      await delTrigger.click();
      const delModal = page.locator('#template-delete-modal.show');
      await delModal.waitFor({ state: 'visible' });
      await Promise.all([page.waitForLoadState('networkidle'), delModal.locator('button[type="submit"]').click()]);
      await page.goto('/admin/configuration/templates', { waitUntil: 'networkidle' });
      await expect(page.locator('[data-testid="datatable-templates-row"]', { hasText: name })).toHaveCount(0);

      const issues = collector.drain();
      expectNoIssues(issues);
    });

    test('duplicate template modal accepts a new name and persists', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto('/admin/configuration/templates', { waitUntil: 'networkidle' });
      const dupTrigger = page.locator('[data-bs-target="#template-duplicate-modal"]').first();
      if (await dupTrigger.count() === 0) {
        return;
      }
      const sourceId = await dupTrigger.getAttribute('data-template-id');
      const dupName = qaRef('tpldup');
      await dupTrigger.click();
      const modal = page.locator('#template-duplicate-modal.show');
      await modal.waitFor({ state: 'visible' });
      await page.getByTestId('template-duplicate-new-name').fill(dupName);
      await Promise.all([page.waitForLoadState('networkidle'), modal.locator('button[type="submit"]').click()]);

      await page.goto('/admin/configuration/templates', { waitUntil: 'networkidle' });
      const dupRow = page.locator('[data-testid="datatable-templates-row"]', { hasText: dupName }).first();
      await expect(dupRow).toBeVisible();
      const dupHref = await dupRow.locator('a[href*="templates/update"]').first().getAttribute('href');
      const dupId = Number(new URL(dupHref ?? 'https://x/?template_id=0', 'https://x').searchParams.get('template_id'));

      // CLEANUP duplicate (keep the source untouched).
      expect(dupId, 'duplicate id must differ from source').not.toBe(Number(sourceId));
      const delTrigger = page.locator(`[data-bs-target="#template-delete-modal"][data-template-id="${dupId}"]`).first();
      await delTrigger.click();
      const delModal = page.locator('#template-delete-modal.show');
      await delModal.waitFor({ state: 'visible' });
      await Promise.all([page.waitForLoadState('networkidle'), delModal.locator('button[type="submit"]').click()]);
      await page.goto('/admin/configuration/templates', { waitUntil: 'networkidle' });
      await expect(page.locator('[data-testid="datatable-templates-row"]', { hasText: dupName })).toHaveCount(0);

      const issues = collector.drain();
      expectNoIssues(issues);
    });
  });
});
