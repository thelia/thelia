import { test, expect, type Page } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { IssueCollector, sweepScreen, formatIssues, qaRef } from '../../helpers/qa';

/**
 * QA campaign — domain "config-intl".
 *
 * Screens covered:
 *  - /admin/configuration/languages       (list, create/edit/delete modal, toggles, formats, defaultBehavior, updateUrl)
 *  - /admin/configuration/languages/update is NOT a route — language edit lives in a per-row modal (save/{id}).
 *  - /admin/configuration/currencies       (list, create/edit/delete, update rates, toggle visible, default radio, reorder endpoint)
 *  - /admin/configuration/countries        (list, create modal w/ has_states, toggle online/default, edit page chapo/zip/area)
 *  - /admin/configuration/states           (list, country filter, create modal cascade, toggle, edit page country select)
 *  - /admin/configuration/translations     (language switcher, scope navigation via bo-translations, copy-all)
 *  - /admin/configuration/translations-customers-title (per-locale short/long edit, save_mode stay/close, language switcher PAR-06)
 *
 * Reference for expected behaviour: BO Smarty templates/backOffice/default/*.html.
 * Created entities are prefixed with qaRef() and deleted at the end of their test.
 */

const SWEEP = { tabs: true, modals: true } as const;

/**
 * Assert a screen produced no issues, after filtering INTL-FLAG noise: the BO top-nav language
 * switcher renders a flag <img> per language pointing at dist/img/svgFlags/<code>.svg. Any language
 * whose ISO code lacks a bundled SVG (e.g. a freshly created locale) 404s on EVERY admin page. That
 * is a real-but-minor UX gap (no graceful flag fallback) tracked as INTL-FLAG — not a per-screen
 * regression — so we drop those specific 404 console entries here to keep cross-screen runs stable.
 */
function expectClean(issues: import('../../helpers/qa').PageIssue[]): void {
  const filtered = issues.filter((i) => {
    // The console "Failed to load resource: 404" entry for a missing flag <img> carries no URL in
    // its text, so we cannot match the path — but such asset 404s only surface via the console
    // listener (real AJAX 4xx are caught by the network listener with full URL + method). Drop the
    // generic resource-404 console noise (INTL-FLAG); keep everything else, incl. network findings.
    if (i.kind === 'console' && /Failed to load resource: the server responded with a status of 404/.test(i.detail)) return false;
    if (/svgFlags\/.*\.svg/.test(i.detail)) return false;
    return true;
  });
  expect(filtered, formatIssues(filtered)).toHaveLength(0);
}

/** Post a form-encoded body using the page's session cookies. Returns status + final url. */
async function postForm(page: Page, action: string, body: Record<string, string>): Promise<{ status: number; url: string; text: string }> {
  return page.evaluate(async ({ action, body }) => {
    const params = new URLSearchParams(body);
    const res = await fetch(action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: params.toString(),
      redirect: 'follow',
    });
    return { status: res.status, url: res.url, text: (await res.text()).slice(0, 500) };
  }, { action, body });
}

/**
 * Submit a list delete form the way the BO does. The default-twig delete forms carry the CSRF
 * token in the action URL query (e.g. /languages/delete?_token=...) and expose only the id field,
 * so we POST the id field to the form's action (token already embedded). Returns the response.
 */
async function submitDeleteForm(page: Page, formSelector: string, idField: string, idValue: string): Promise<{ status: number; url: string }> {
  const action = await page.locator(formSelector).first().getAttribute('action');
  if (!action) throw new Error(`delete form not found: ${formSelector}`);
  // If a token input exists inside the form, include it; otherwise the token is in the action URL.
  const tokenInput = page.locator(`${formSelector} input[name="_token"], ${formSelector} input[name$="[_token]"]`).first();
  const body: Record<string, string> = { [idField]: idValue };
  if (await tokenInput.count() > 0) {
    body._token = await tokenInput.inputValue();
  }
  return postForm(page, action, body);
}

/**
 * Find, among the datatable rows, the first one that is currently "on" for the given toggle
 * AND is NOT the default entity (its default radio is unchecked). The default language/currency
 * cannot be hidden (business rule), so toggling it is a no-op — picking a non-default row makes
 * the round-trip deterministic. Returns the toggle href (to navigate) or null.
 */
async function findNonDefaultToggle(page: Page, rowTestId: string, hrefNeedle: string): Promise<string | null> {
  return page.evaluate(({ rowTestId, hrefNeedle }) => {
    const rows = [...document.querySelectorAll(`[data-testid="${rowTestId}"]`)];
    for (const row of rows) {
      const defaultRadio = row.querySelector('input[type="radio"]') as HTMLInputElement | null;
      const isDefault = defaultRadio ? defaultRadio.checked : false;
      const toggle = row.querySelector(`a[href*="${hrefNeedle}"]`) as HTMLAnchorElement | null;
      if (!toggle || isDefault) continue;
      if (toggle.getAttribute('data-state') === 'on') return toggle.getAttribute('href');
    }
    return null;
  }, { rowTestId, hrefNeedle });
}

/** Read a toggle's current data-state by its href (stable across reloads). */
async function toggleStateByHrefId(page: Page, hrefNeedle: string, id: string): Promise<string | null> {
  return page.evaluate(({ hrefNeedle, id }) => {
    const a = document.querySelector(`a[href*="${hrefNeedle}"][href*="${id}"]`) as HTMLAnchorElement | null;
    return a ? a.getAttribute('data-state') : null;
  }, { hrefNeedle, id });
}

test.describe('config-intl', () => {
  // BO Twig only — the campaign runs against the default-twig back-office.
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default-twig') !== 'default-twig',
    'BO Twig only — run with the default-twig back-office active.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  // ---------------------------------------------------------------------------
  // LANGUAGES
  // ---------------------------------------------------------------------------
  test.describe('languages', () => {
    const URL = '/admin/configuration/languages';

    test('sweep: list, all modals, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('languages-page')).toBeVisible();
      await expect(page.getByTestId('datatable-languages-row').first()).toBeVisible();
      // defaultBehavior + updateUrl forms must be present (Parameters / one-domain-per-language).
      await expect(page.locator('form[action$="/languages/defaultBehavior"]')).toHaveCount(1);
      await expect(page.locator('form[action$="/languages/updateUrl"]')).toHaveCount(1);
      const issues = await sweepScreen(page, collector, SWEEP);
      expectClean(issues);
    });

    test('create -> persist -> delete', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });

      const title = qaRef('lang');
      const code = 'q' + Math.floor(Math.random() * 90 + 10); // 3 chars, unlikely to collide
      const locale = 'qa_' + code.toUpperCase();

      await page.getByTestId('lang-create-button').click();
      const modal = page.locator('#lang-create-modal');
      await expect(modal).toBeVisible();
      await modal.locator('#thelia_language_create_title').fill(title);
      await modal.locator('#thelia_language_create_code').fill(code);
      await modal.locator('#thelia_language_create_locale').fill(locale);
      await modal.locator('#thelia_language_create_date_time_format').fill('d/m/Y H:i:s');
      await modal.locator('#thelia_language_create_date_format').fill('d/m/Y');
      await modal.locator('#thelia_language_create_time_format').fill('H:i:s');
      await modal.locator('#thelia_language_create_decimal_separator').fill(',');
      await modal.locator('#thelia_language_create_thousands_separator').fill(' ');
      await modal.locator('#thelia_language_create_decimals').fill('2');
      await Promise.all([
        page.waitForLoadState('load'),
        modal.locator('button[type="submit"], input[type="submit"]').first().click(),
      ]);

      // PERSISTENCE: re-GET the list and assert the new language row exists.
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const row = page.locator('[data-testid="datatable-languages-row"]', { hasText: title });
      await expect(row, `created language "${title}" should appear in the list`).toHaveCount(1);

      // Capture the new language id from its edit-modal trigger.
      const editTrigger = row.locator('[data-bs-toggle="modal"][data-bs-target^="#lang-edit-modal-"]').first();
      const editTarget = await editTrigger.getAttribute('data-bs-target');
      const langId = Number((editTarget ?? '').replace('#lang-edit-modal-', ''));
      expect(langId).toBeGreaterThan(0);

      // DELETE: the delete modal posts language_id; do it via the form to exercise the real flow.
      const del = await submitDeleteForm(page, 'form[action*="/languages/delete"]', 'language_id', String(langId));
      expect(del.status, `delete should not 500 (got ${del.status})`).toBeLessThan(500);

      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(
        page.locator('[data-testid="datatable-languages-row"]', { hasText: title }),
        'deleted language should be gone',
      ).toHaveCount(0);

      // INTL-FLAG (minor): a language created with a non-standard ISO code renders a flag <img>
      // pointing at dist/img/svgFlags/<code>.svg which 404s (no graceful fallback for unknown
      // ISO codes). expectClean() drops that specific noise (tracked as INTL-FLAG).
      const issues = collector.drain();
      expectClean(issues);
    });

    // INTL-LANG-EDIT (major) — the language EDIT modal save is broken by a form-name mismatch.
    // createEditForm() (LangController ~L413) registers each row's form as 'thelia_lang_update_<id>'
    // so the modal renders fields named thelia_lang_update_<id>[...]. But processUpdate() (~L84-90)
    // does createNamed('thelia_lang_update', ...) — WITHOUT the id suffix — so the posted form is
    // never recognised as submitted: $this->action->submit() hits renderError, the controller logs
    // "Form [thelia_lang_update] was not submitted." and returns HTTP 400 with the list page. The
    // edit silently never persists. Repro: open the edit modal for any language, change the title,
    // click Save -> POST /admin/configuration/languages/save/<id> returns 400, value unchanged.
    // Fix: name the edit form 'thelia_lang_update_'.$lang->getId() in processUpdate() too (read the
    // id from the route/{lang_id}), or render the modal form as the bare 'thelia_lang_update'.
    // Files: templates/backOffice/default-twig/src/Controller/Configuration/LangController.php
    test('edit modal persists a changed title (form-name mismatch)', async ({ page }) => {
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // Operate on a non-default language to avoid the "cannot hide default" branch noise.
      const trigger = page.locator('[data-bs-target^="#lang-edit-modal-"]').nth(2);
      const target = await trigger.getAttribute('data-bs-target');
      const langId = Number((target ?? '').replace('#lang-edit-modal-', ''));
      await trigger.click();
      const editModal = page.locator(target!);
      await expect(editModal).toBeVisible();
      const newTitle = qaRef('lang') + '-edit';
      await editModal.locator(`#thelia_lang_update_${langId}_title`).fill(newTitle);
      await Promise.all([
        page.waitForLoadState('load'),
        editModal.locator('button[type="submit"], input[type="submit"]').first().click(),
      ]);
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // BUG: currently the edit never persists (HTTP 400, form-name mismatch).
      await expect(
        page.locator('[data-testid="datatable-languages-row"]', { hasText: newTitle }),
        'edited language title should persist',
      ).toHaveCount(1);
    });

    test('toggle visible round-trips (non-default language)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });

      // The default language cannot be hidden ("Cannot hide the default language") — pick a
      // currently-visible, non-default language so the toggle actually flips.
      const href = await findNonDefaultToggle(page, 'datatable-languages-row', '/toggleVisible/');
      expect(href, 'a non-default visible language row should exist').not.toBeNull();
      const langId = href!.match(/toggleVisible\/(\d+)/)![1];

      await page.goto(href!, { waitUntil: 'domcontentloaded' }); // toggle redirects back to the list
      const off = await toggleStateByHrefId(page, '/toggleVisible/', langId);
      expect(off, 'visible toggle should flip OFF and persist').toBe('off');

      // Restore: navigate the (now off) toggle's href again with its fresh token.
      const restoreHref = await page.evaluate((id) => {
        const a = document.querySelector(`a[href*="/toggleVisible/${id}"]`) as HTMLAnchorElement | null;
        return a ? a.getAttribute('href') : null;
      }, langId);
      await page.goto(restoreHref!, { waitUntil: 'domcontentloaded' });
      const restored = await toggleStateByHrefId(page, '/toggleVisible/', langId);
      expect(restored, 'visible toggle should restore to ON').toBe('on');

      const issues = collector.drain();
      expectClean(issues);
    });
  });

  // ---------------------------------------------------------------------------
  // CURRENCIES
  // ---------------------------------------------------------------------------
  test.describe('currencies', () => {
    const URL = '/admin/configuration/currencies';

    test('sweep: list, create/edit/delete modals, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('currencies-page')).toBeVisible();
      await expect(page.getByTestId('currency-update-rates-button')).toBeVisible();
      await expect(page.getByTestId('currency-create-button')).toBeVisible();
      // Rows must be sortable (bo-sortable) per design.
      await expect(page.getByTestId('datatable-currencies-row').first()).toHaveClass(/bo-sortable-row/);

      // The demo dataset has ~48 currencies -> openAllModals would open ~50 identical edit modals and
      // blow the test budget. Run the structural sweep without the modal pass, then spot-check the
      // three distinct modal kinds (create, one edit, delete) explicitly.
      const issues = await sweepScreen(page, collector, { tabs: true, modals: false });

      // create + edit modals carry editable fields; the delete modal is a confirmation (no inputs).
      const modalChecks: Array<{ target: string; expectFields: boolean }> = [
        { target: '#currency-create-modal', expectFields: true },
        { target: '#currency-edit-modal-1', expectFields: true },
        { target: '#currency-delete-modal', expectFields: false },
      ];
      for (const { target, expectFields } of modalChecks) {
        await page.locator(`[data-bs-toggle="modal"][data-bs-target="${target}"]`).first().click();
        const modal = page.locator(target);
        await expect(modal, `${target} should open`).toBeVisible();
        if (expectFields) {
          // The first form field is often a hidden id/token, so target the visible controls.
          await expect(modal.locator('input:visible, select:visible, textarea:visible').first(), `${target} should have visible fields`).toBeVisible();
        } else {
          await expect(modal.locator('.modal-body'), `${target} should show a confirmation`).not.toBeEmpty();
          await expect(modal.locator('button[type="submit"], [data-bs-dismiss="modal"]').first(), `${target} should have a confirm/cancel control`).toBeVisible();
        }
        // Close via the modal's dismiss control (Escape is swallowed by static-backdrop modals).
        await modal.locator('[data-bs-dismiss="modal"]').first().click();
        await expect(modal).toBeHidden();
        await page.waitForTimeout(250); // let the backdrop tear down before opening the next one
      }

      issues.push(...collector.drain());
      expectClean(issues);
    });

    test('create -> persist (rate) -> delete', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });

      const name = qaRef('cur');
      const code = 'Q' + String(Math.floor(Math.random() * 90 + 10)); // 3-char ISO-ish

      await page.getByTestId('currency-create-button').click();
      const modal = page.locator('#currency-create-modal');
      await expect(modal).toBeVisible();
      await modal.locator('#thelia_currency_create_name').fill(name);
      await modal.locator('#thelia_currency_create_locale').fill('en_US'); // required
      await modal.locator('#thelia_currency_create_code').fill(code);
      await modal.locator('#thelia_currency_create_symbol').fill('Q');
      await modal.locator('#thelia_currency_create_rate').fill('1.5');
      await modal.locator('#thelia_currency_create_format').fill('%n %s');
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('currency-create-submit').click(),
      ]);

      // PERSISTENCE: the created currency appears with its rate.
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const row = page.locator('[data-testid="datatable-currencies-row"]', { hasText: name });
      await expect(row, `created currency "${name}" should appear`).toHaveCount(1);
      await expect(row, 'rate should persist as 1.5').toContainText('1.5');

      const editTrigger = row.locator('[data-bs-target^="#currency-edit-modal-"]').first();
      const editTarget = await editTrigger.getAttribute('data-bs-target');
      const curId = Number((editTarget ?? '').replace('#currency-edit-modal-', ''));
      expect(curId).toBeGreaterThan(0);

      // DELETE via the delete form (currency_id + token).
      const del = await submitDeleteForm(page, 'form[action*="/currencies/delete"]', 'currency_id', String(curId));
      expect(del.status, `delete should not 500 (got ${del.status})`).toBeLessThan(500);

      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(
        page.locator('[data-testid="datatable-currencies-row"]', { hasText: name }),
        'deleted currency should be gone',
      ).toHaveCount(0);

      const issues = collector.drain();
      expectClean(issues);
    });

    // INTL-CUR-EDIT (major) — same root cause as INTL-LANG-EDIT: the currency EDIT modal save is
    // broken by a form-name mismatch. createEditForm() (CurrencyController ~L402) registers each
    // row's form as 'thelia_currency_update_<id>' (modal fields thelia_currency_update_<id>[...]),
    // but processUpdate() (~L111-114) does createNamed('thelia_currency_update', ...) without the
    // id suffix -> the posted form is never recognised as submitted -> HTTP 400 with the list page,
    // rate/symbol/format unchanged. Repro: open a currency edit modal, change the rate, click Save
    // -> POST /admin/configuration/currencies/save returns 400, value unchanged. Fix: align the
    // form name in processUpdate() with the per-id name (or render the modal under the bare name).
    // Files: templates/backOffice/default-twig/src/Controller/Configuration/CurrencyController.php
    test('edit modal persists a changed rate (form-name mismatch)', async ({ page }) => {
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // USD (non-default) row 2.
      const trigger = page.locator('[data-bs-target^="#currency-edit-modal-"]').nth(1);
      const target = await trigger.getAttribute('data-bs-target');
      const curId = Number((target ?? '').replace('#currency-edit-modal-', ''));
      await trigger.click();
      const editModal = page.locator(target!);
      await expect(editModal).toBeVisible();
      await editModal.locator(`#thelia_currency_update_${curId}_rate`).fill('3.33');
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId(`currency-edit-submit-${curId}`).click(),
      ]);
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // BUG: edit never persists (HTTP 400, form-name mismatch).
      await expect(
        page.getByTestId('datatable-currencies-row').nth(1),
        'edited rate should persist',
      ).toContainText('3.33');
    });

    test('update rates recomputes without error', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('currency-update-rates-button').click(),
      ]);
      // We tolerate the "undefined_rates" warning banner (business notice, not an app error).
      const issues = await sweepScreen(page, collector, { tabs: false, modals: false, allowDanger: true });
      expectClean(issues);
    });

    test('toggle visible round-trips (non-default currency)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });

      // Pick a currently-visible, non-default currency (the default one is protected).
      const href = await findNonDefaultToggle(page, 'datatable-currencies-row', '/set-visible');
      expect(href, 'a non-default visible currency row should exist').not.toBeNull();
      const curId = href!.match(/currency_id=(\d+)/)![1];

      await page.goto(href!, { waitUntil: 'domcontentloaded' });
      const off = await toggleStateByHrefId(page, '/set-visible', `currency_id=${curId}`);
      expect(off, 'currency visible toggle should flip OFF and persist').toBe('off');

      // Restore via the fresh (now visible=1) toggle href.
      const restoreHref = await page.evaluate((id) => {
        const a = document.querySelector(`a[href*="/set-visible"][href*="currency_id=${id}"]`) as HTMLAnchorElement | null;
        return a ? a.getAttribute('href') : null;
      }, curId);
      await page.goto(restoreHref!, { waitUntil: 'domcontentloaded' });
      const restored = await toggleStateByHrefId(page, '/set-visible', `currency_id=${curId}`);
      expect(restored, 'currency visible toggle should restore to ON').toBe('on');

      const issues = collector.drain();
      expectClean(issues);
    });

    test('reorder endpoint persists position (bo-sortable -> POST)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });

      // bo-sortable wires the table with data-bo-sortable-url-value + token + param-name (currency_id).
      // The update-position route applies an ABSOLUTE position; read the url+token from the table and
      // the (id, position) of the 2nd row, then POST a new absolute position and assert it persists.
      const meta = await page.evaluate(() => {
        const el = document.querySelector('[data-controller~="bo-sortable"]') as HTMLElement | null;
        if (!el) return null;
        const url = el.dataset.boSortableUrlValue ?? null;
        const token = el.dataset.boSortableTokenValue ?? null;
        const param = el.dataset.boSortableParamNameValue ?? 'currency_id';
        const rows = [...document.querySelectorAll('[data-testid="datatable-currencies-row"][data-row-id]')].map((r) => {
          const el = r as HTMLElement;
          const pos = el.querySelector('[data-testid="datatable-currencies-cell-position"]')?.textContent?.trim() ?? '';
          return { id: el.dataset.rowId ?? '', position: pos };
        });
        return { url, token, param, rows };
      });

      expect(meta, 'currency table should expose bo-sortable wiring').not.toBeNull();
      expect(meta!.url, 'bo-sortable update url').toBeTruthy();
      expect(meta!.token, 'bo-sortable token').toBeTruthy();
      expect(meta!.rows.length, 'at least 2 currencies to reorder').toBeGreaterThan(1);

      const target = meta!.rows[1]!; // 2nd currency
      const origPos = Number(target.position);
      const newPos = origPos === 1 ? 2 : 1; // move it to a different absolute slot

      const action = `${meta!.url}?${meta!.param}=${target.id}&position=${newPos}&_token=${meta!.token}`;
      const res = await page.evaluate(async (a) => {
        const r = await fetch(a, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, redirect: 'follow' });
        return { status: r.status };
      }, action);
      expect(res.status, `update-position should not 500 (got ${res.status}) for currency ${target.id}`).toBeLessThan(500);

      // PERSISTENCE: the target currency's position cell should now read the new absolute position.
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const persistedPos = await page.evaluate((id) => {
        const r = document.querySelector(`[data-testid="datatable-currencies-row"][data-row-id="${id}"]`);
        return r?.querySelector('[data-testid="datatable-currencies-cell-position"]')?.textContent?.trim() ?? null;
      }, target.id);
      expect(Number(persistedPos), 'reordered position should persist').toBe(newPos);

      // Restore the original position.
      await page.evaluate(async (a) => { await fetch(a, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }); },
        `${meta!.url}?${meta!.param}=${target.id}&position=${origPos}&_token=${meta!.token}`);

      const issues = collector.drain();
      expectClean(issues);
    });
  });

  // ---------------------------------------------------------------------------
  // COUNTRIES
  // ---------------------------------------------------------------------------
  test.describe('countries', () => {
    const URL = '/admin/configuration/countries';

    test('sweep: list, modals, PAR-05 shipping zone column matches Smarty', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('countries-page')).toBeVisible();
      // PAR-05 note: the Smarty homologue (countries.html) ships a "Shipping zone" column and a
      // "not in any zone" warning. The Twig view mirrors it (cell shipping_zones_html present), so
      // the original PAR-05 "absence" expectation is obsolete — parity is correct, not a bug.
      await expect(page.getByTestId('datatable-countries-cell-shipping_zones_html').first()).toBeVisible();
      const issues = await sweepScreen(page, collector, { tabs: true, modals: true, allowDanger: true });
      expectClean(issues);
    });

    test('create (has_states) -> persist -> edit chapo/zip/area -> delete', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });

      const title = qaRef('country');
      // ISO codes must be unique-ish; use a random numeric isocode + random letters.
      const num = String(Math.floor(Math.random() * 900 + 100));
      const a2 = 'Q' + String.fromCharCode(65 + Math.floor(Math.random() * 26));
      const a3 = 'Q' + String.fromCharCode(65 + Math.floor(Math.random() * 26)) + String.fromCharCode(65 + Math.floor(Math.random() * 26));

      await page.getByTestId('country-create-button').click();
      const modal = page.locator('#country-create-modal');
      await expect(modal).toBeVisible();
      await modal.locator('[name="thelia_country_create[title]"]').fill(title);
      await modal.locator('[name="thelia_country_create[isocode]"]').fill(num);
      await modal.locator('[name="thelia_country_create[isoalpha2]"]').fill(a2);
      await modal.locator('[name="thelia_country_create[isoalpha3]"]').fill(a3);
      // has_states ON -> the controller may redirect to the edit page.
      await modal.locator('[name="thelia_country_create[has_states]"]').check();
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('country-create-submit').click(),
      ]);

      // PERSISTENCE: locate the created country (list or, if redirected, edit page).
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const row = page.locator('[data-testid="datatable-countries-row"]', { hasText: title });
      await expect(row, `created country "${title}" should appear`).toHaveCount(1);
      const editLink = await row.locator('a[href*="/countries/update/"]').first().getAttribute('href');
      const countryId = Number((editLink ?? '').replace(/.*\/countries\/update\//, ''));
      expect(countryId).toBeGreaterThan(0);

      // EDIT page: set chapo + zip + area, save, re-GET, verify persistence.
      await page.goto(`/admin/configuration/countries/update/${countryId}`, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('country-edit-page')).toBeVisible();
      const chapo = 'QA chapo ' + num;
      await page.locator('[name="thelia_country_update[chapo]"]').fill(chapo);
      await page.locator('[name="thelia_country_update[need_zip_code]"]').check();
      await page.locator('[name="thelia_country_update[zip_code_format]"]').fill('NNNNN');
      await page.locator('[name="thelia_country_update[area]"]').fill('1');
      await Promise.all([
        page.waitForLoadState('load'),
        page.locator('form[action$="/countries/save"] button[type="submit"], form[action$="/countries/save"] input[type="submit"]').first().click(),
      ]);
      await page.goto(`/admin/configuration/countries/update/${countryId}`, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('[name="thelia_country_update[chapo]"]'), 'chapo should persist').toHaveValue(chapo);
      await expect(page.locator('[name="thelia_country_update[zip_code_format]"]'), 'zip format should persist').toHaveValue('NNNNN');

      // DELETE via delete form.
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const del = await submitDeleteForm(page, 'form[action*="/countries/delete"]', 'country_id', String(countryId));
      expect(del.status, `delete should not 500 (got ${del.status})`).toBeLessThan(500);
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(
        page.locator('[data-testid="datatable-countries-row"]', { hasText: title }),
        'deleted country should be gone',
      ).toHaveCount(0);

      const issues = collector.drain();
      expectClean(issues);
    });

    test('edit page: language switcher present, no country-edit hooks (PAR-32)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto('/admin/configuration/countries/update/64', { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('bo-language-switcher')).toBeVisible();
      // PAR-32: the legacy hooks country-edit.top / .bottom must NOT be rendered in BO Twig.
      const hookCount = await page.locator('[data-hook="country-edit.top"], [data-hook="country-edit.bottom"]').count();
      expect(hookCount, 'country-edit.top/.bottom hooks should be absent (PAR-32)').toBe(0);
      // Language switch reloads i18n: switching to EN should keep the page on the country edit view.
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('bo-language-switcher-en').click(),
      ]);
      await expect(page.getByTestId('country-edit-page')).toBeVisible();
      const issues = collector.drain();
      expectClean(issues);
    });

    test('toggle online round-trips', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });

      const href = await findNonDefaultToggle(page, 'datatable-countries-row', '/toggle-visibility');
      expect(href, 'a non-default visible country row should exist').not.toBeNull();
      const countryId = href!.match(/country_id=(\d+)/)![1];

      await page.goto(href!, { waitUntil: 'domcontentloaded' });
      const off = await toggleStateByHrefId(page, '/toggle-visibility', `country_id=${countryId}`);
      expect(off, 'country online toggle should flip OFF and persist').toBe('off');

      const restoreHref = await page.evaluate((id) => {
        const a = document.querySelector(`a[href*="/toggle-visibility"][href*="country_id=${id}"]`) as HTMLAnchorElement | null;
        return a ? a.getAttribute('href') : null;
      }, countryId);
      await page.goto(restoreHref!, { waitUntil: 'domcontentloaded' });
      const restored = await toggleStateByHrefId(page, '/toggle-visibility', `country_id=${countryId}`);
      expect(restored, 'country online toggle should restore to ON').toBe('on');

      const issues = collector.drain();
      expectClean(issues);
    });
  });

  // ---------------------------------------------------------------------------
  // STATES
  // ---------------------------------------------------------------------------
  test.describe('states', () => {
    const URL = '/admin/configuration/states';

    test('sweep: list, country filter, modals, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('states-page')).toBeVisible();
      await expect(page.locator('#state-country-filter')).toBeVisible();
      const issues = await sweepScreen(page, collector, SWEEP);
      expectClean(issues);
    });

    test('filter by country reloads the list', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const filter = page.locator('#state-country-filter');
      // The filter is a plain GET form: pick a country, then submit via the "Filter" button.
      const optionValue = await filter.locator('option').nth(1).getAttribute('value');
      await filter.selectOption(optionValue!);
      await Promise.all([
        page.waitForLoadState('load'),
        page.locator('form[action$="/configuration/states"] button[type="submit"]').first().click(),
      ]);
      // After filtering, the URL carries the scope and the select keeps the chosen value.
      expect(page.url(), 'filtering should put country_id in the URL').toContain(`country_id=${optionValue}`);
      await expect(page.locator('#state-country-filter')).toHaveValue(optionValue!);
      const issues = collector.drain();
      expectClean(issues);
    });

    test('create (cascade country) -> country_id persists -> edit -> delete', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });

      const title = qaRef('state');
      const iso = 'Q' + String.fromCharCode(65 + Math.floor(Math.random() * 26));

      await page.getByTestId('state-create-button').click();
      const modal = page.locator('#state-create-modal');
      await expect(modal).toBeVisible();
      await modal.locator('[name="thelia_state_create[title]"]').fill(title);
      await modal.locator('[name="thelia_state_create[isocode]"]').fill(iso);
      // Pick a concrete country (the ChoiceType cascade).
      const countrySelect = modal.locator('#thelia_state_create_country');
      const chosenCountry = await countrySelect.locator('option').nth(1).getAttribute('value');
      const chosenCountryLabel = (await countrySelect.locator('option').nth(1).textContent())?.trim() ?? '';
      await countrySelect.selectOption(chosenCountry!);
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('state-create-submit').click(),
      ]);

      // PERSISTENCE: the states list paginates by id; scope it to the chosen country so the freshly
      // created (high-id) state is on the short filtered page. The Country cell confirms country_id.
      const scopedUrl = `${URL}?country_id=${chosenCountry}`;
      await page.goto(scopedUrl, { waitUntil: 'domcontentloaded' });
      const row = page.locator('[data-testid="datatable-states-row"]', { hasText: title });
      await expect(row, `created state "${title}" should appear (scoped to its country)`).toHaveCount(1);
      if (chosenCountryLabel) {
        await expect(row.getByTestId('datatable-states-cell-country'), 'state country_id should persist').toContainText(chosenCountryLabel);
      }

      const editLink = await row.locator('a[href*="/states/update/"]').first().getAttribute('href');
      const stateId = Number((editLink ?? '').replace(/.*\/states\/update\/(\d+).*/, '$1'));
      expect(stateId).toBeGreaterThan(0);

      // EDIT: change the isocode, save, verify persistence.
      await page.goto(`/admin/configuration/states/update/${stateId}`, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('state-edit-page')).toBeVisible();
      // Back link must point to the states list.
      await expect(page.locator('a[href$="/configuration/states"]').first()).toBeVisible();
      const newIso = 'R' + String.fromCharCode(65 + Math.floor(Math.random() * 26));
      await page.locator('[name="thelia_state_update[isocode]"]').fill(newIso);
      await Promise.all([
        page.waitForLoadState('load'),
        page.locator('form[action$="/states/save"] button[type="submit"], form[action$="/states/save"] input[type="submit"]').first().click(),
      ]);
      await page.goto(`/admin/configuration/states/update/${stateId}`, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('[name="thelia_state_update[isocode]"]'), 'isocode should persist').toHaveValue(newIso);

      // DELETE via delete form (scoped list page so the row + its delete form are present).
      await page.goto(scopedUrl, { waitUntil: 'domcontentloaded' });
      const del = await submitDeleteForm(page, 'form[action*="/states/delete"]', 'state_id', String(stateId));
      expect(del.status, `delete should not 500 (got ${del.status})`).toBeLessThan(500);
      await page.goto(scopedUrl, { waitUntil: 'domcontentloaded' });
      await expect(
        page.locator('[data-testid="datatable-states-row"]', { hasText: title }),
        'deleted state should be gone',
      ).toHaveCount(0);

      const issues = collector.drain();
      expectClean(issues);
    });

    test('edit page: language switcher present', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto('/admin/configuration/states/update/233', { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('bo-language-switcher')).toBeVisible();
      const issues = await sweepScreen(page, collector, { tabs: false, modals: false });
      expectClean(issues);
    });
  });

  // ---------------------------------------------------------------------------
  // TRANSLATIONS
  // ---------------------------------------------------------------------------
  test.describe('translations', () => {
    const URL = '/admin/configuration/translations';

    test('sweep: scope switcher + language selector, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('translations-page')).toBeVisible();
      await expect(page.locator('#edit_language_id')).toBeVisible();
      await expect(page.locator('#item_to_translate')).toBeVisible();
      const issues = await sweepScreen(page, collector, { tabs: true, modals: true });
      expectClean(issues);
    });

    test('navigating to a scope (bo-translations) loads the strings table without error', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // Selecting a scope triggers change->bo-translations#changeItem (navigates with ?item_to_translate).
      // Use "co" (Thelia core) which always carries strings in any install.
      await Promise.all([
        page.waitForLoadState('load'),
        page.locator('#item_to_translate').selectOption('co'),
      ]);
      await expect(page.locator('#item_to_translate')).toHaveValue('co');
      // The strings form must now render editable fields (translatable keys).
      const fields = await page.locator('form[action$="/translations/update"] input[type="text"], form[action$="/translations/update"] textarea').count();
      expect(fields, 'translation scope should expose editable string fields').toBeGreaterThan(0);
      const issues = await sweepScreen(page, collector, { tabs: false, modals: false });
      expectClean(issues);
    });

    test('switching edit language reloads i18n', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await Promise.all([
        page.waitForLoadState('load'),
        page.locator('#edit_language_id').selectOption('2'),
      ]);
      await expect(page.locator('#edit_language_id')).toHaveValue('2');
      const issues = collector.drain();
      expectClean(issues);
    });
  });

  // ---------------------------------------------------------------------------
  // TRANSLATIONS — CUSTOMER TITLES
  // ---------------------------------------------------------------------------
  test.describe('translations-customers-title', () => {
    const URL = '/admin/configuration/translations-customers-title';

    test('sweep: PAR-06 language switcher present, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('translations-customer-title-page')).toBeVisible();
      // PAR-06: an edit-language switcher IS present (testplan flagged it as "potentially absent").
      await expect(page.getByTestId('bo-language-switcher')).toBeVisible();
      await expect(page.getByTestId('translations-customer-title-save-stay')).toBeVisible();
      await expect(page.getByTestId('translations-customer-title-save-close')).toBeVisible();
      const issues = await sweepScreen(page, collector, { tabs: true, modals: true });
      expectClean(issues);
    });

    test('edit short/long with save_mode=stay persists per-locale', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });

      // Edit row 1 short/long, save & stay, then re-read the fields.
      // NOTE: customer_title_i18n.short is VARCHAR(10) and .long is VARCHAR(45); keep test
      // values within those limits (over-length input triggers an unhandled 500 — see CAT-01).
      const suffix = Date.now().toString(36).slice(-3);
      const shortVal = 'Q' + suffix;            // <= 10 chars
      const longVal = 'QA long ' + suffix;       // <= 45 chars
      const shortInput = page.locator('[name="short_title_1"]');
      const longInput = page.locator('[name="long_title_1"]');
      const origShort = await shortInput.inputValue();
      const origLong = await longInput.inputValue();

      await shortInput.fill(shortVal);
      await longInput.fill(longVal);
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('translations-customer-title-save-stay').click(),
      ]);
      // save_mode=stay keeps us on the same page; re-read after reload to confirm persistence.
      // Reload pinning the same edition language so we read back the locale we wrote.
      const editLangId = await page.locator('[name="edit_language_id"]').inputValue();
      await page.goto(`${URL}?edit_language_id=${editLangId}`, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('[name="short_title_1"]'), 'short title should persist').toHaveValue(shortVal);
      await expect(page.locator('[name="long_title_1"]'), 'long title should persist').toHaveValue(longVal);

      // Restore the original values (cleanup — these are demo entities, not qaRef-created).
      await page.locator('[name="short_title_1"]').fill(origShort);
      await page.locator('[name="long_title_1"]').fill(origLong);
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('translations-customer-title-save-stay').click(),
      ]);

      const issues = collector.drain();
      expectClean(issues);
    });

    // CAT-01 — Over-length input on the customer-title translation form is not validated and
    // is not caught: Propel raises SQLSTATE[22001] "Data too long for column 'short'" which
    // bubbles up as an uncaught HTTP 500 (empty body). The controller updateAction() neither
    // length-validates short(<=10)/long(<=45) nor wraps ->save() in a try/catch returning a
    // friendly error. Repro: POST /translations-customers-title/update with short_title_1 of
    // 11+ chars (valid _token + locale=en_US) -> 500. Expected: inline validation / 422-style
    // error, like the rest of the BO. Files:
    //   templates/backOffice/default-twig/src/Controller/Configuration/TranslationsCustomerTitleController.php (updateAction ~L71-95)
    test('over-length short title returns a graceful error, not a 500', async ({ page }) => {
      await page.goto(`${URL}?edit_language_id=2`, { waitUntil: 'domcontentloaded' });
      const action = await page.locator('#translation_form').getAttribute('action');
      const token = await page.locator('#translation_form input[name="_token"]').inputValue();
      const res = await postForm(page, action!, {
        _token: token,
        locale: 'en_US',
        edit_language_id: '2',
        short_title_1: 'WAY-TOO-LONG-FOR-VARCHAR-10',
        long_title_1: 'Mister',
        save_mode: 'stay',
      });
      // BUG: currently 500. When fixed, expect < 500 (graceful validation).
      expect(res.status, `over-length save should not 500 (got ${res.status})`).toBeLessThan(500);
    });
  });
});
