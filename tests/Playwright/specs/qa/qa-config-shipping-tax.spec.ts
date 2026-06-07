import { test, expect, type Page } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { IssueCollector, sweepScreen, formatIssues, qaRef } from '../../helpers/qa';

/**
 * QA campaign — domain "config-shipping-tax".
 *
 * Screens covered:
 *  - /admin/configuration/shipping_configuration                 (areas list: create / edit / delete modal, bo-area-edit fetch)
 *  - /admin/configuration/shipping_configuration/update/{id}     (area edit: add/remove countries, bo-area-edit)
 *  - /admin/configuration/shipping_zones                         (delivery module ↔ zones list)
 *  - /admin/configuration/shipping_zones/update/{delivery_module_id}  (associate / dissociate areas)
 *  - /admin/configuration/taxes_rules                            (taxes + tax rules list, create modals, set-default, delivery tax rule config)
 *  - /admin/configuration/taxes_rules/update/{id}                (tax rule edit: 2 tabs, matrix bo-tax-rule-matrix, saveTaxes JSON)
 *  - /admin/configuration/taxes/update/{id}                      (tax edit: type → dynamic requirements)
 *
 * Reference for expected behaviour: BO Smarty templates/backOffice/default/*.html.
 * Created entities are prefixed with qaRef() and deleted at the end of their test.
 *
 * Known back-office facts (verified against DB / controllers, NOT bugs):
 *  - Demo has areas 1..14, delivery modules id 6 (CustomDelivery) & 15 (VirtualProductDelivery),
 *    taxes 1 & 2 (both PricePercent), tax rules 1 (default) & 2.
 *  - ShippingZoneController::updateView() does NOT filter the module by type, so update/{id}
 *    works for any module id; we use a real delivery module (6) for persistence.
 */

const SWEEP = { tabs: true, modals: true } as const;

const DELIVERY_MODULE_ID = 6; // CustomDelivery (type=2), present in demo.
const DEFAULT_TAX_RULE_ID = 1; // is_default=1 in demo.

function expectClean(issues: import('../../helpers/qa').PageIssue[]): void {
  const filtered = issues.filter((i) => {
    // INTL-FLAG noise: top-nav language switcher 404s on missing svgFlags on every admin page.
    if (i.kind === 'console' && /Failed to load resource: the server responded with a status of 404/.test(i.detail)) return false;
    if (/svgFlags\/.*\.svg/.test(i.detail)) return false;
    return true;
  });
  expect(filtered, formatIssues(filtered)).toHaveLength(0);
}

/** POST a form-encoded body using the page session. Returns status + final url + body slice. */
async function postForm(
  page: Page,
  action: string,
  body: Record<string, string | string[]>,
): Promise<{ status: number; url: string; text: string }> {
  return page.evaluate(async ({ action, body }) => {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(body)) {
      if (Array.isArray(value)) {
        for (const v of value) params.append(key, v);
      } else {
        params.append(key, value);
      }
    }
    const res = await fetch(action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json, text/html' },
      body: params.toString(),
      redirect: 'follow',
    });
    return { status: res.status, url: res.url, text: (await res.text()).slice(0, 800) };
  }, { action, body });
}

/**
 * Submit a BoConfirmDialog delete form. These forms carry the CSRF token in the action URL query
 * (?_token=...) and expose only the prefilled id field — there is NO hidden _token input. We must NOT
 * pass an empty _token in the body, because AdminFormAction::tokenAction() reads the body token FIRST
 * (request->get('_token') ?? query->get('_token')) and an empty-string body value would shadow the
 * valid query token, silently aborting the delete.
 */
async function submitConfirmDelete(
  page: Page,
  formTestId: string,
  idField: string,
  idValue: string | number,
): Promise<{ status: number; url: string; text: string }> {
  const form = page.getByTestId(formTestId);
  const action = await form.getAttribute('action');
  if (!action) throw new Error(`delete form ${formTestId} has no action`);
  // Body carries only the id; the token stays in the action URL query.
  return postForm(page, action, { [idField]: String(idValue) });
}

/**
 * BUG SHIP-TAX-01 (see test.fixme below) — AreaController::create and TaxRuleController::add use
 * AdminFormAction::submit() with a success route that has a required {id} placeholder
 * (admin.configuration.shipping-configuration.update.view / admin.configuration.taxes-rules.update)
 * but pass successParameters: []. submit() never injects the created id, so the post-create redirect
 * throws "Some mandatory parameters are missing" and the user lands on the LIST with a danger alert.
 *
 * The entity IS created though (the event fires before the broken redirect is generated). This helper
 * submits the create modal, tolerates the broken redirect, and resolves the new entity id from the
 * list by matching the unique qaRef title — so persistence/edit/delete flows can still be exercised.
 *
 * @param updatePathNeedle  substring of the edit-route path (e.g. '/taxes_rules/update/') used to
 *                          extract the new id from the matching row's edit link.
 * Returns the new entity id (resolved from the list).
 */
async function submitCreateAndResolveId(
  page: Page,
  listUrl: string,
  triggerTestId: string,
  formTestId: string,
  titleSelector: string,
  title: string,
  updatePathNeedle: string,
): Promise<number> {
  await page.goto(listUrl, { waitUntil: 'networkidle' });
  await page.getByTestId(triggerTestId).click();
  const form = page.getByTestId(formTestId);
  await expect(form).toBeVisible();
  await form.locator(titleSelector).fill(title);
  await form.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
  // Re-GET the list and resolve the id from the edit link in the row carrying our unique title.
  await page.goto(listUrl, { waitUntil: 'networkidle' });
  const row = page.locator('tr', { hasText: title }).first();
  await expect(row, `created entity "${title}" not found in list`).toBeVisible({ timeout: 10_000 });
  const href = await row.locator(`a[href*="${updatePathNeedle}"]`).first().getAttribute('href');
  const id = Number(new RegExp(`${updatePathNeedle.replace(/[/]/g, '\\/')}(\\d+)`).exec(href ?? '')?.[1]);
  expect(id, `could not parse id from edit link (${href})`).toBeGreaterThan(0);
  return id;
}

test.describe('config-shipping-tax', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default-twig') !== 'default-twig',
    'BO Twig only — run with the default-twig back-office active.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  // ===========================================================================
  // SHIPPING ZONES (areas) — /admin/configuration/shipping_configuration
  // ===========================================================================
  test.describe('shipping configuration (areas)', () => {
    const URL = '/admin/configuration/shipping_configuration';

    test('sweep: list, create modal, delete modal — no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('shipping-configuration-page')).toBeVisible();
      await expect(page.getByTestId('shipping-configuration-create-btn')).toBeVisible();
      // The demo areas must be listed.
      await expect(page.getByText('France', { exact: true }).first()).toBeVisible();
      expectClean(await sweepScreen(page, collector, SWEEP));
    });

    // BUG SHIP-TAX-01 — after a successful area creation the controller redirect to the edit route
    // fails ("Some mandatory parameters are missing (area_id)") because AdminFormAction::submit()
    // does not inject the created id into successParameters. Repro:
    //   1. /admin/configuration/shipping_configuration
    //   2. Click "Add a new shipping zone", fill name, submit
    //   3. EXPECTED: land on /shipping_configuration/update/{newId}
    //      ACTUAL: land on /shipping_configuration with a red alert "Some mandatory parameters are
    //      missing (area_id) to generate a URL for route admin.configuration.shipping-configuration.update.view".
    // The area IS created (event fires before the broken redirect). Fix: pass the new id, e.g.
    // AreaController::create successParameters via a describe-driven id, or generate the redirect
    // after reading $event->getModel()->getId().
    test('create area redirects to edit page (SHIP-TAX-01: missing area_id in redirect)', async ({ page }) => {
      const name = qaRef('area');
      await page.goto(URL, { waitUntil: 'networkidle' });
      await page.getByTestId('shipping-configuration-create-btn').click();
      const createForm = page.getByTestId('shipping-configuration-create-form');
      await createForm.locator('input[name$="[name]"]').fill(name);
      await Promise.all([
        page.waitForURL(/\/shipping_configuration\/update\/\d+/, { timeout: 15_000 }),
        createForm.locator('button[type="submit"]').click(),
      ]);
      await expect(page.getByTestId('shipping-configuration-edit-page')).toBeVisible();
    });

    test('create → edit (add/remove country) → delete an area with persistence', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      const name = qaRef('area');

      // CREATE via modal — redirect is broken (SHIP-TAX-01) so we resolve the new id from the list.
      const areaId = await submitCreateAndResolveId(
        page,
        URL,
        'shipping-configuration-create-btn',
        'shipping-configuration-create-form',
        'input[name$="[name]"]',
        name,
        '/shipping_configuration/update/',
      );

      // Open the edit page of the freshly-created area.
      await page.goto(`${URL}/update/${areaId}`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('shipping-configuration-edit-page')).toBeVisible();
      // bo-area-edit fetches the country data on connect; assert the add-select got populated.
      const addSelect = page.getByTestId('shipping-configuration-countries-to-add');
      await expect(addSelect.locator('option').first()).toBeAttached({ timeout: 10_000 });
      expectClean(await sweepScreen(page, collector, { tabs: false, modals: false }));

      // --- ADD a country (France id 64 is the default country, always present & unassigned-or-not) ---
      // Use a direct POST to the country.add endpoint (the UI add is a multiselect submit).
      const addRes = await postForm(page, '/admin/configuration/shipping_configuration/country/add', {
        area_id: String(areaId),
        'country_id[]': ['64'],
      });
      expect(addRes.status, addRes.text).toBeLessThan(400);

      // --- PERSISTENCE: re-GET the edit page, the assigned country must appear in the added table ---
      // bo-area-edit renders the table from the server-side "initial" data; empty state renders ONE
      // placeholder <tr> with no checkbox, so we count the actual country checkboxes (input.country-selection).
      await page.goto(`${URL}/update/${areaId}`, { waitUntil: 'networkidle' });
      const addedBody = page.getByTestId('shipping-configuration-countries-added');
      await expect(addedBody.locator('input.country-selection[value="64-0"]')).toHaveCount(1, { timeout: 10_000 });

      // --- REMOVE the country via the bulk countries/remove endpoint (UPDATE access, no CSRF token) ---
      // The UI "Delete selected countries" form posts country_id[] as "{countryId}-{stateId}" tokens.
      const removeRes = await postForm(page, '/admin/configuration/shipping_configuration/countries/remove', {
        area_id: String(areaId),
        'country_id[]': ['64-0'],
      });
      expect(removeRes.status, removeRes.text).toBeLessThan(400);

      // --- PERSISTENCE: re-GET, the country 64 checkbox must be gone from the added table ---
      await page.goto(`${URL}/update/${areaId}`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('shipping-configuration-countries-added').locator('input.country-selection[value="64-0"]'))
        .toHaveCount(0, { timeout: 10_000 });

      // --- DELETE the area (token in the confirm-dialog form action URL) ---
      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('shipping-configuration-delete-form')).toBeAttached();
      const delRes = await submitConfirmDelete(page, 'shipping-configuration-delete-form', 'area_id', areaId);
      expect(delRes.status, delRes.text).toBeLessThan(400);

      // --- PERSISTENCE: the deleted area must be gone from the list ---
      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.getByText(name, { exact: true })).toHaveCount(0);
      expectClean(collector.drain());
    });

    test('area edit screen sweep (demo area 1) — no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(`${URL}/update/1`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('shipping-configuration-edit-page')).toBeVisible();
      // bo-area-edit should populate the added table for an area that has countries (France).
      await expect(page.getByTestId('shipping-configuration-add-country-form')).toBeVisible();
      expectClean(await sweepScreen(page, collector, { tabs: false, modals: false }));
    });
  });

  // ===========================================================================
  // SHIPPING ZONES per delivery module — /admin/configuration/shipping_zones
  // ===========================================================================
  test.describe('shipping zones (per delivery module)', () => {
    const URL = '/admin/configuration/shipping_zones';

    test('sweep: list of delivery modules — no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('shipping-zones-page')).toBeVisible();
      // Delivery modules row must be present (CustomDelivery id 6).
      await expect(page.getByTestId(`shipping-zones-row-${DELIVERY_MODULE_ID}`)).toBeVisible();
      expectClean(await sweepScreen(page, collector, SWEEP));
    });

    test('associate then dissociate an area to a delivery module with persistence', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      const editUrl = `${URL}/update/${DELIVERY_MODULE_ID}`;

      await page.goto(editUrl, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('shipping-zones-edit-page')).toBeVisible();
      expectClean(await sweepScreen(page, collector, { tabs: false, modals: true }));

      const addForm = page.getByTestId('shipping-zones-add-area-form');
      await expect(addForm).toBeVisible();
      // Pick the first available area in the select (skip any empty placeholder).
      const areaOption = addForm.locator('select[name="area_id"] option:not([value=""])').first();
      const areaId = await areaOption.getAttribute('value');
      const areaLabel = (await areaOption.textContent())?.trim() ?? '';
      expect(areaId).toBeTruthy();
      const token = await addForm.locator('input[name="_token"]').inputValue();

      // --- ASSOCIATE (token-protected, _token read in query by controller) ---
      const addRes = await postForm(page, `/admin/configuration/shipping_zones/area/add?_token=${encodeURIComponent(token)}`, {
        _token: token,
        shipping_zone_id: String(DELIVERY_MODULE_ID),
        area_id: areaId!,
      });
      expect(addRes.status, addRes.text).toBeLessThan(400);

      // --- PERSISTENCE: re-GET, the area must now be in the associated table ---
      await page.goto(editUrl, { waitUntil: 'networkidle' });
      const assocRow = page.getByTestId(`shipping-zones-area-${areaId}`);
      await expect(assocRow, `area ${areaLabel} should be associated`).toBeVisible({ timeout: 10_000 });

      // --- DISSOCIATE via the remove form ---
      const removeForm = page.getByTestId('shipping-zones-remove-area-form');
      const removeToken = await removeForm.locator('input[name="_token"]').inputValue();
      const removeRes = await postForm(page, `/admin/configuration/shipping_zones/area/remove?_token=${encodeURIComponent(removeToken)}`, {
        _token: removeToken,
        shipping_zone_id: String(DELIVERY_MODULE_ID),
        area_id: areaId!,
      });
      expect(removeRes.status, removeRes.text).toBeLessThan(400);

      // --- PERSISTENCE: gone from the associated table ---
      await page.goto(editUrl, { waitUntil: 'networkidle' });
      await expect(page.getByTestId(`shipping-zones-area-${areaId}`)).toHaveCount(0);

      // Back link to the list must exist.
      await expect(page.locator(`a[href$="${URL}"]`).first()).toBeVisible();
      expectClean(collector.drain());
    });
  });

  // ===========================================================================
  // TAXES RULES — /admin/configuration/taxes_rules
  // ===========================================================================
  test.describe('taxes rules', () => {
    const URL = '/admin/configuration/taxes_rules';

    test('sweep: list, create-tax modal, create-tax-rule modal, delete modals — no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('taxes-rules-page')).toBeVisible();
      await expect(page.getByTestId('taxes-section')).toBeVisible();
      await expect(page.getByTestId('tax-rules-section')).toBeVisible();
      await expect(page.getByTestId('tax-rule-delivery-form')).toBeVisible();
      expectClean(await sweepScreen(page, collector, SWEEP));
    });

    test('create-tax modal: changing type toggles requirement fields', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'networkidle' });
      await page.getByTestId('tax-create-button').click();
      const form = page.getByTestId('tax-create-form');
      await expect(form).toBeVisible();

      const typeSelect = form.locator('#thelia_tax_creation_type');
      await expect(typeSelect).toBeVisible();
      const optionValues = await typeSelect.locator('option').evaluateAll((opts) =>
        opts.map((o) => (o as HTMLOptionElement).value),
      );
      expect(optionValues.length).toBeGreaterThan(0);

      // For each type, the matching requirement group(s) must be the only visible/enabled ones.
      for (const value of optionValues) {
        await typeSelect.selectOption(value);
        await page.waitForTimeout(150);
        const mismatchedEnabled = await form.locator('.tax-requirement').evaluateAll((groups, current) => {
          let bad = 0;
          for (const g of groups) {
            const matches = (g as HTMLElement).dataset.taxType === current;
            const inputs = g.querySelectorAll('input, select, textarea');
            for (const input of Array.from(inputs)) {
              const disabled = (input as HTMLInputElement).disabled;
              if (matches && disabled) bad++;       // should be enabled
              if (!matches && !disabled) bad++;      // should be disabled
            }
          }
          return bad;
        }, value);
        expect(mismatchedEnabled, `requirement toggle mismatch for type ${value}`).toBe(0);
      }
      await page.keyboard.press('Escape');
      expectClean(collector.drain());
    });

    // BUG SHIP-TAX-02 (BLOCKER) — creating a tax through the modal UI always fails with
    // "The CSRF token is invalid". Root cause: in configuration/tax-rule/_tax_create_modal.html.twig
    // the `{% for child in create_tax_form %}` loop excludes only ['locale','title','description','type'],
    // so the form's `_token` hidden child is rendered INSIDE a `.tax-requirement` wrapper (data-tax-type="").
    // The inline sync() script then does `input.disabled = !matches` on every `.tax-requirement` input for
    // the selected type — and since the token's group has an empty tax-type, the token is ALWAYS disabled,
    // hence never submitted. Confirmed: the posted body carries no `thelia_tax_creation[_token]`, and a
    // direct POST WITH the token to /admin/configuration/taxes/add succeeds (302 → /taxes/update/{id}).
    // Repro:
    //   1. /admin/configuration/taxes_rules → click "Create a new tax"
    //   2. Fill title, pick any type, fill the percent/amount requirement, submit
    //   3. EXPECTED: tax created, redirect to /taxes/update/{id}
    //      ACTUAL: redirect to /taxes_rules with red alert "The CSRF token is invalid. Please try to resubmit the form."
    // Fix: exclude '_token' (and 'id') from the requirement loop, e.g.
    //   {% if field_name not in ['locale','title','description','type','_token','id'] %}
    test('create a tax via the modal UI (SHIP-TAX-02: _token disabled → CSRF invalid)', async ({ page }) => {
      const title = qaRef('tax');
      await page.goto(URL, { waitUntil: 'networkidle' });
      await page.getByTestId('tax-create-button').click();
      const form = page.getByTestId('tax-create-form');
      await form.locator('#thelia_tax_creation_title').fill(title);
      const typeSelect = form.locator('#thelia_tax_creation_type');
      const pp = (await typeSelect.locator('option').evaluateAll((o) => o.map((x) => (x as HTMLOptionElement).value))).find((v) => v.includes('PricePercent'))!;
      await typeSelect.selectOption(pp);
      await page.waitForTimeout(200);
      await form.locator('input[name$="PricePercentTaxType:percent]"]').fill('10');
      await Promise.all([
        page.waitForURL(/\/taxes\/update\/\d+/, { timeout: 15_000 }),
        form.locator('button[type="submit"]').click(),
      ]);
    });

    // Server-side tax creation works (the bug is purely template/JS), so we exercise persistence + edit +
    // delete by creating the tax through a direct authenticated POST that includes the token.
    test('tax create (server) → edit → delete with persistence', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      const title = qaRef('tax');

      await page.goto(URL, { waitUntil: 'networkidle' });
      await page.getByTestId('tax-create-button').click();
      const form = page.getByTestId('tax-create-form');
      await expect(form).toBeVisible();
      const token = await form.locator('input[name="thelia_tax_creation[_token]"]').inputValue();
      const ppType = (await form.locator('#thelia_tax_creation_type option').evaluateAll((o) =>
        o.map((x) => (x as HTMLOptionElement).value),
      )).find((v) => v.includes('PricePercent'))!;

      const createRes = await postForm(page, '/admin/configuration/taxes/add', {
        'thelia_tax_creation[locale]': 'en_US',
        'thelia_tax_creation[title]': title,
        'thelia_tax_creation[type]': ppType,
        [`thelia_tax_creation[${ppType}:percent]`]: '10',
        'thelia_tax_creation[_token]': token,
      });
      expect(createRes.status, createRes.text).toBeLessThan(400);
      const taxId = Number(/\/taxes\/update\/(\d+)/.exec(createRes.url)?.[1]);
      expect(taxId, createRes.url).toBeGreaterThan(0);

      // --- PERSISTENCE: edit page reached, title pre-filled ---
      await page.goto(`/admin/configuration/taxes/update/${taxId}`, { waitUntil: 'networkidle' });
      await expect(page.locator('input[name$="[title]"]').first()).toHaveValue(title);
      expectClean(await sweepScreen(page, collector, { tabs: false, modals: false }));

      // --- PERSISTENCE: appears in the taxes list ---
      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.getByText(title, { exact: true }).first()).toBeVisible();

      // --- DELETE via the tax delete modal form ---
      await expect(page.getByTestId('tax-delete-form')).toBeAttached();
      const delRes = await submitConfirmDelete(page, 'tax-delete-form', 'tax_id', taxId);
      expect(delRes.status, delRes.text).toBeLessThan(400);

      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.getByText(title, { exact: true })).toHaveCount(0);
      expectClean(collector.drain());
    });

    // BUG SHIP-TAX-01 (same root cause as area create) — after a successful tax-rule creation the redirect
    // to admin.configuration.taxes-rules.update fails with "Some mandatory parameters are missing
    // (tax_rule_id)", because AdminFormAction::submit() does not inject the created id into
    // successParameters. The rule IS created. Repro:
    //   1. /admin/configuration/taxes_rules → "Create a new tax rule" → fill title → submit
    //   2. EXPECTED: land on /taxes_rules/update/{newId}
    //      ACTUAL: land on /taxes_rules with red alert "Some mandatory parameters are missing (tax_rule_id) ...".
    test('create tax rule redirects to edit page (SHIP-TAX-01: missing tax_rule_id in redirect)', async ({ page }) => {
      const title = qaRef('taxrule');
      await page.goto(URL, { waitUntil: 'networkidle' });
      await page.getByTestId('tax-rule-create-button').click();
      const form = page.getByTestId('tax-rule-create-form');
      await form.locator('#thelia_tax_rule_creation_title').fill(title);
      await Promise.all([
        page.waitForURL(/\/taxes_rules\/update\/\d+/, { timeout: 15_000 }),
        form.locator('button[type="submit"]').click(),
      ]);
      await expect(page.getByTestId('tax-rule-edit-title')).toContainText(title);
    });

    test('create a tax rule (modal submit) with persistence, set as default, then delete it', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      const title = qaRef('taxrule');

      // CREATE via modal — redirect is broken (SHIP-TAX-01) so resolve the new id from the list.
      const taxRuleId = await submitCreateAndResolveId(
        page,
        URL,
        'tax-rule-create-button',
        'tax-rule-create-form',
        '#thelia_tax_rule_creation_title',
        title,
        '/taxes_rules/update/',
      );

      // --- PERSISTENCE: edit page opens with the created title ---
      await page.goto(`${URL}/update/${taxRuleId}`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('tax-rule-edit-title')).toContainText(title);
      expectClean(await sweepScreen(page, collector, { tabs: true, modals: false }));

      // --- PERSISTENCE: appears in the tax rules list ---
      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.getByText(title, { exact: true }).first()).toBeVisible();

      // --- SET AS DEFAULT (the set-default RowAction href carries a valid ?_token=) ---
      const setDefaultHref = await page
        .locator(`tr:has-text("${title}") a[href*="/update/set_default/${taxRuleId}"]`)
        .first()
        .getAttribute('href');
      expect(setDefaultHref, 'set-default action href not found').toContain('_token=');
      const setDefaultRes = await postForm(page, setDefaultHref!, {});
      expect(setDefaultRes.status, setDefaultRes.text).toBeLessThan(400);

      // --- PERSISTENCE: row now carries the Default badge ---
      await page.goto(URL, { waitUntil: 'networkidle' });
      const defaultRow = page.locator('tr', { hasText: title }).first();
      await expect(defaultRow.locator('.badge')).toContainText(/Default/i, { timeout: 10_000 });

      // Restore the original default (tax rule 1) — its set-default href appears once it is non-default.
      const restoreHref = await page
        .locator(`a[href*="/update/set_default/${DEFAULT_TAX_RULE_ID}?"]`)
        .first()
        .getAttribute('href');
      if (restoreHref) {
        await postForm(page, restoreHref, {});
      }

      // --- DELETE the created rule (no longer default, delete modal form present) ---
      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('tax-rule-delete-form')).toBeAttached();
      const delRes = await submitConfirmDelete(page, 'tax-rule-delete-form', 'tax_rule_id', taxRuleId);
      expect(delRes.status, delRes.text).toBeLessThan(400);

      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.getByText(title, { exact: true })).toHaveCount(0);
      expectClean(collector.drain());
    });

    test('tax rule for delivery modules: save ConfigQuery taxrule_id_delivery_module with persistence', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'networkidle' });
      const form = page.getByTestId('tax-rule-delivery-form');
      await expect(form).toBeVisible();

      const select = form.locator('#delivery-module-tax-rule');
      const previous = await select.inputValue();
      // Pick a concrete tax rule (first non-empty option).
      const targetOption = select.locator('option:not([value=""])').first();
      const targetValue = await targetOption.getAttribute('value');
      expect(targetValue).toBeTruthy();

      await select.selectOption(targetValue!);
      await Promise.all([
        page.waitForURL(/\/taxes_rules$/, { timeout: 15_000 }),
        form.locator('button[type="submit"]').click(),
      ]);

      // --- PERSISTENCE: re-GET, the select must keep the saved value ---
      await page.goto(URL, { waitUntil: 'networkidle' });
      await expect(page.locator('#delivery-module-tax-rule')).toHaveValue(targetValue!);

      // Restore the previous value to avoid mutating demo config.
      if (previous !== targetValue) {
        const restoreSelect = page.locator('#delivery-module-tax-rule');
        await restoreSelect.selectOption(previous === '' ? { value: '' } : previous);
        await Promise.all([
          page.waitForURL(/\/taxes_rules$/, { timeout: 15_000 }),
          page.getByTestId('tax-rule-delivery-save').click(),
        ]);
      }
      expectClean(collector.drain());
    });
  });

  // ===========================================================================
  // TAX RULE EDIT + MATRIX — /admin/configuration/taxes_rules/update/{id}
  // ===========================================================================
  test.describe('tax rule edit (matrix)', () => {
    const editUrl = `/admin/configuration/taxes_rules/update/${DEFAULT_TAX_RULE_ID}`;

    test('sweep: edit page, both tabs, matrix section, apply modal — no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(editUrl, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('tax-rule-edit-page')).toBeVisible();
      await expect(page.getByTestId('tax-rule-edit-form')).toBeVisible();

      // Switch to the Taxes tab and confirm the matrix renders.
      await page.locator('#tax-rule-tab-taxes').click();
      await expect(page.getByTestId('tax-rule-matrix')).toBeVisible();
      await expect(page.getByTestId('tax-rule-matrix-available')).toBeVisible();
      await expect(page.getByTestId('tax-rule-matrix-groups')).toBeVisible();
      // The matrix bootstraps the available-taxes pool from the taxes JSON map.
      await expect(page.getByTestId('tax-rule-matrix-available').locator('[draggable="true"]').first()).toBeAttached({ timeout: 10_000 });

      expectClean(await sweepScreen(page, collector, SWEEP));
    });

    test('JSON specs endpoint returns taxRules + specifications', async ({ page }) => {
      const res = await page.request.get(`/admin/configuration/taxes_rules/specs/${DEFAULT_TAX_RULE_ID}`);
      expect(res.status()).toBe(200);
      const payload = await res.json();
      expect(payload).toHaveProperty('taxRules');
      expect(payload).toHaveProperty('specifications');
      expect(Array.isArray(payload.taxRules)).toBe(true);
      expect(Array.isArray(payload.specifications)).toBe(true);
    });

    test('matrix saveTaxes: persist a config via direct POST then verify via specs endpoint', async ({ page }) => {
      // The drag&drop matrix is flaky to drive headless; per campaign rules we test the
      // saveTaxes endpoint directly (token + JSON payloads from the apply form) and verify persistence.
      const collector = new IssueCollector(page).attach();

      // Create a throwaway tax rule so we don't mutate the demo default rule's bindings.
      // (create redirect is broken — SHIP-TAX-01 — so resolve the id from the list).
      const title = qaRef('matrix');
      const taxRuleId = await submitCreateAndResolveId(
        page,
        '/admin/configuration/taxes_rules',
        'tax-rule-create-button',
        'tax-rule-create-form',
        '#thelia_tax_rule_creation_title',
        title,
        '/taxes_rules/update/',
      );

      // Read matrix data from the page: token, an available tax id, the default country id.
      await page.goto(`/admin/configuration/taxes_rules/update/${taxRuleId}`, { waitUntil: 'networkidle' });
      await page.locator('#tax-rule-tab-taxes').click();
      await expect(page.getByTestId('tax-rule-matrix')).toBeVisible();
      const token = await page.locator('#tax-rule-apply-modal input[name="_token"]').inputValue();
      const taxesMap: Record<string, string> = await page.evaluate(() => {
        const el = document.querySelector('[data-bo-tax-rule-matrix-tax-rule-id-value]') as HTMLElement | null;
        return el ? JSON.parse(el.getAttribute('data-bo-tax-rule-matrix-taxes-value') || '{}') : {};
      });
      const defaultCountryId = await page.evaluate(() => {
        const el = document.querySelector('[data-bo-tax-rule-matrix-default-country-id-value]') as HTMLElement | null;
        return el ? Number(el.getAttribute('data-bo-tax-rule-matrix-default-country-id-value')) : 0;
      });
      const firstTaxId = Number(Object.keys(taxesMap)[0]);
      expect(firstTaxId).toBeGreaterThan(0);
      expect(defaultCountryId).toBeGreaterThan(0);

      // saveTaxes contract (mirrors the Stimulus apply form):
      //   tax_list = [[taxId]]  one group with one tax
      //   country_list = [[countryId, stateId]]
      const saveRes = await postForm(page, '/admin/configuration/taxes_rules/saveTaxes', {
        _token: token,
        id: String(taxRuleId),
        tax_list: JSON.stringify([[firstTaxId]]),
        country_list: JSON.stringify([[defaultCountryId, 0]]),
        country_deleted_list: '[]',
      });
      expect(saveRes.status, saveRes.text).toBe(200);
      const savePayload = JSON.parse(saveRes.text);
      expect(savePayload.success, saveRes.text).toBe(true);

      // --- PERSISTENCE: specs endpoint must now report the binding ---
      const specsRes = await page.request.get(`/admin/configuration/taxes_rules/specs/${taxRuleId}`);
      expect(specsRes.status()).toBe(200);
      const specs = await specsRes.json();
      expect(specs.specifications.length, JSON.stringify(specs)).toBeGreaterThan(0);
      expect(
        specs.specifications.some((s: { country: number }) => Number(s.country) === defaultCountryId),
        JSON.stringify(specs),
      ).toBe(true);

      // --- CLEANUP: delete the throwaway rule ---
      await page.goto('/admin/configuration/taxes_rules', { waitUntil: 'networkidle' });
      await submitConfirmDelete(page, 'tax-rule-delete-form', 'tax_rule_id', taxRuleId);
      await page.goto('/admin/configuration/taxes_rules', { waitUntil: 'networkidle' });
      await expect(page.getByText(title, { exact: true })).toHaveCount(0);

      expectClean(collector.drain());
    });

    test('tax rule edit: change title + save with persistence', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      // Use a throwaway rule so we don't rename the demo default rule (create redirect broken → SHIP-TAX-01).
      const initial = qaRef('rule-title');
      const taxRuleId = await submitCreateAndResolveId(
        page,
        '/admin/configuration/taxes_rules',
        'tax-rule-create-button',
        'tax-rule-create-form',
        '#thelia_tax_rule_creation_title',
        initial,
        '/taxes_rules/update/',
      );

      const renamed = `${initial}-EDIT`;
      await page.goto(`/admin/configuration/taxes_rules/update/${taxRuleId}`, { waitUntil: 'networkidle' });
      const editForm = page.getByTestId('tax-rule-edit-form');
      await editForm.locator('input[name$="[title]"]').fill(renamed);
      await Promise.all([
        page.waitForURL(/\/taxes_rules\/update\/\d+/, { timeout: 15_000 }),
        page.getByTestId('tax-rule-edit-submit').click(),
      ]);

      // --- PERSISTENCE: re-GET the edit page, title field holds the new value ---
      await page.goto(`/admin/configuration/taxes_rules/update/${taxRuleId}`, { waitUntil: 'networkidle' });
      await expect(page.locator('input[name$="[title]"]').first()).toHaveValue(renamed);
      expectClean(await sweepScreen(page, collector, { tabs: true, modals: false }));

      // --- CLEANUP ---
      await page.goto('/admin/configuration/taxes_rules', { waitUntil: 'networkidle' });
      await submitConfirmDelete(page, 'tax-rule-delete-form', 'tax_rule_id', taxRuleId);
      await page.goto('/admin/configuration/taxes_rules', { waitUntil: 'networkidle' });
      await expect(page.getByText(renamed, { exact: true })).toHaveCount(0);
      expectClean(collector.drain());
    });
  });

  // ===========================================================================
  // TAX EDIT — /admin/configuration/taxes/update/{id}
  // ===========================================================================
  test.describe('tax edit', () => {
    test('sweep: demo tax 1 edit — type select + requirement toggling, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto('/admin/configuration/taxes/update/1', { waitUntil: 'networkidle' });
      await expect(page.locator('input[name$="[title]"]').first()).toBeVisible();
      // type select drives the dynamic requirements (bo-tax-edit controller).
      const typeSelect = page.locator('select[name$="[type]"]').first();
      await expect(typeSelect).toBeVisible();
      expectClean(await sweepScreen(page, collector, { tabs: false, modals: false }));
    });
  });
});
