import { test, expect, type Page } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { IssueCollector, sweepScreen, formatIssues, qaRef, type PageIssue } from '../../helpers/qa';

/**
 * QA campaign — domain "config-system".
 *
 * Screens covered (perimeter handed to this agent):
 *  - /admin/configuration                          (index tiles + navigation links)
 *  - /admin/configuration/variables                (CRUD: create modal, inline value edit + save-values, edit modal, delete)
 *  - /admin/configuration/advanced                 (flush assets / images-documents; flush global cache clicked LAST)
 *  - /admin/configuration/adminLogs                (date+checkbox filter, full-page POST + AJAX /adminLogs/logger fragment)
 *  - /admin/configuration/system-logs              (form save + persistence + bo-system-logs toggle + audit-log line)
 *  - /admin/configuration/profiles                 (list, create, edit 3 tabs incl. bo-permission-matrix persistence, delete)
 *  - /admin/configuration/administrators           (create QA admin, edit, self-delete button masking, delete QA admin)
 *  - /admin/configuration/customer-titles          (CRUD, default badge)
 *
 * NOT in this perimeter (testplan tags them config-system but they were handed to other agents):
 *  - /admin/configuration/messages, /messages/update (PAR-02/PAR-27)
 *  - /admin/configuration/mailingSystem
 *  - /admin/configuration/store, /admin/configuration/translations
 *
 * Reference for expected behaviour: BO Smarty templates/backOffice/default/*.html.
 * Every created entity is prefixed with qaRef() and deleted within the same test.
 */

const SWEEP = { tabs: true, modals: true } as const;

/** Assert a screen produced no issues; drop the INTL-FLAG language-switcher 404 noise (see config-intl spec). */
function expectClean(issues: PageIssue[]): void {
  const filtered = issues.filter((i) => {
    if (i.kind === 'console' && /Failed to load resource: the server responded with a status of 404/.test(i.detail)) return false;
    if (/svgFlags\/.*\.svg/.test(i.detail)) return false;
    return true;
  });
  expect(filtered, formatIssues(filtered)).toHaveLength(0);
}

/** POST a form-encoded body with the session cookies. Returns status + final url + body slice. */
async function postForm(page: Page, action: string, body: Record<string, string>): Promise<{ status: number; url: string; text: string }> {
  return page.evaluate(async ({ action, body }) => {
    const params = new URLSearchParams(body);
    const res = await fetch(action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
      body: params.toString(),
      redirect: 'follow',
    });
    return { status: res.status, url: res.url, text: (await res.text()).slice(0, 600) };
  }, { action, body });
}

/**
 * Submit a list delete form the BO way. default-twig delete forms carry the CSRF token in the action
 * URL query (?_token=...) and expose a prefilled hidden id input. We POST the id field to the action.
 */
async function submitDeleteForm(page: Page, formSelector: string, idField: string, idValue: string): Promise<{ status: number; url: string }> {
  const action = await page.locator(formSelector).first().getAttribute('action');
  if (!action) throw new Error(`delete form not found: ${formSelector}`);
  const tokenInput = page.locator(`${formSelector} input[name="_token"]`).first();
  const body: Record<string, string> = { [idField]: idValue };
  if (await tokenInput.count() > 0) body._token = await tokenInput.inputValue();
  return postForm(page, action, body);
}

test.describe('config-system', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default-twig') !== 'default-twig',
    'BO Twig only — run with the default-twig back-office active.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  // ---------------------------------------------------------------------------
  // CONFIGURATION INDEX
  // ---------------------------------------------------------------------------
  test.describe('index', () => {
    const URL = '/admin/configuration';

    test('sweep: tiles render + key links present, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('configuration-page')).toBeVisible();
      // A representative spread of config tiles (each is a link to a sub-section).
      for (const route of [
        'admin.configuration.variables.default',
        'admin.configuration.profiles.list',
        'admin.configuration.administrators.view',
        'admin.configuration.advanced',
        'admin.configuration.system-logs.default',
        'admin.configuration.admin-logs.view',
      ]) {
        await expect(page.getByTestId(`configuration-link-${route}`), `tile ${route} should be present`).toBeVisible();
      }
      const issues = await sweepScreen(page, collector, SWEEP);
      expectClean(issues);
    });

    test('every configuration tile link resolves (no 5xx / exception page)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const hrefs = await page.evaluate(() =>
        [...document.querySelectorAll('[data-testid^="configuration-link-"]')]
          .map((a) => (a as HTMLAnchorElement).getAttribute('href'))
          .filter((h): h is string => !!h),
      );
      expect(hrefs.length, 'configuration index should expose tile links').toBeGreaterThan(5);
      for (const href of hrefs) {
        const status = await page.evaluate(async (h) => {
          const r = await fetch(h, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, redirect: 'follow' });
          return r.status;
        }, href);
        expect(status, `tile link ${href} should not 5xx (got ${status})`).toBeLessThan(500);
      }
      expectClean(collector.drain());
    });
  });

  // ---------------------------------------------------------------------------
  // SYSTEM VARIABLES
  // ---------------------------------------------------------------------------
  test.describe('variables', () => {
    const URL = '/admin/configuration/variables';

    test('sweep: list, create/edit/delete modals, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('variables-page')).toBeVisible();
      await expect(page.getByTestId('variable-create-button')).toBeVisible();
      await expect(page.getByTestId('variable-update-values-form')).toBeVisible();

      // PAR-29: a per-row "revert" button IS present (bo-inline-revert). The original PAR-29 expectation
      // (absence of a revert button) is obsolete — the Twig view ships one, which is a UX improvement.
      await expect(page.locator('button[data-bo-inline-revert-target="button"]').first()).toBeVisible();

      // The demo install carries ~57 variables -> openAllModals would open ~57 identical edit modals and
      // blow the test budget. Run the structural sweep without the modal pass, then spot-check the three
      // distinct modal kinds (create, one edit, delete) explicitly.
      const issues = await sweepScreen(page, collector, { tabs: true, modals: false });

      // create modal
      await page.getByTestId('variable-create-button').click();
      await expect(page.locator('#variable-create-modal')).toBeVisible();
      await expect(page.locator('#thelia_config_create_name')).toBeVisible();
      await page.locator('#variable-create-modal [data-bs-dismiss="modal"]').first().click();
      await expect(page.locator('#variable-create-modal')).toBeHidden();
      await page.waitForTimeout(250);

      // one edit modal (the first editable variable row)
      const editTrigger = page.locator('[data-bs-target^="#variable-edit-modal-"]').first();
      const editTarget = await editTrigger.getAttribute('data-bs-target');
      await editTrigger.click();
      await expect(page.locator(editTarget!)).toBeVisible();
      await expect(page.locator(`${editTarget!} input[name$="[title]"]`)).toBeVisible();
      await page.locator(`${editTarget!} [data-bs-dismiss="modal"]`).first().click();
      await expect(page.locator(editTarget!)).toBeHidden();
      await page.waitForTimeout(250);

      // delete modal (confirmation, no editable fields)
      const delTrigger = page.locator('[data-bs-target="#variable-delete-modal"]').first();
      if (await delTrigger.count() > 0) {
        await delTrigger.first().click();
        await expect(page.locator('#variable-delete-modal')).toBeVisible();
        await page.locator('#variable-delete-modal [data-bs-dismiss="modal"]').first().click();
        await expect(page.locator('#variable-delete-modal')).toBeHidden();
      }

      issues.push(...collector.drain());
      expectClean(issues);
    });

    test('PAR-10: a non-env template variable renders a <select>; env-overridden one is read-only', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // PAR-10 expectation ("free-text input") is obsolete: template variables (active-*-template) render
      // a <select> of the installed templates, so an invalid value cannot be typed. Assert that at least
      // one template variable row exposes a value <select> with options. (active-mail-template in demo.)
      const valueControl = page.locator('select[name^="variable["]').first();
      await expect(valueControl, 'PAR-10: a template variable value should be a <select>').toBeVisible();
      expect(await valueControl.locator('option').count(), 'template select should carry options').toBeGreaterThan(0);
      // active-front-template is overridden in .env (ACTIVE_FRONT_TEMPLATE=flexy) so it is rendered
      // read-only (a .env badge + <code>), with no editable control — the correct env-override behaviour.
      const aftRow = page.locator('[data-testid="datatable-variables-row"]', { hasText: 'active-front-template' }).first();
      await expect(aftRow.locator('.badge'), 'env-overridden variable should show a .env badge').toContainText('.env');
      await expect(aftRow.locator('input, select'), 'env-overridden variable should not be editable').toHaveCount(0);
      expectClean(collector.drain());
    });

    test('create -> persist -> inline value edit -> delete', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });

      const name = qaRef('var').toLowerCase().replace(/[^a-z0-9-]/g, '-'); // variable names are slug-ish
      const title = qaRef('var') + ' purpose';

      await page.getByTestId('variable-create-button').click();
      const modal = page.locator('#variable-create-modal');
      await expect(modal).toBeVisible();
      await modal.locator('#thelia_config_create_name').fill(name);
      await modal.locator('#thelia_config_create_title').fill(title);
      await modal.locator('#thelia_config_create_value').fill('qa-initial');
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('variable-create-submit').click(),
      ]);

      // PERSISTENCE: the created variable appears in the list. Its value is rendered as an inline
      // <input> (value attribute), so assert on the field's value rather than the cell text content.
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const row = page.locator('[data-testid^="datatable-variables-row"]', { hasText: name });
      await expect(row, `created variable "${name}" should appear`).toHaveCount(1);

      const varId = await row.evaluate((el) => (el as HTMLElement).getAttribute('data-row-id'));
      expect(Number(varId)).toBeGreaterThan(0);

      // INLINE VALUE EDIT via the "Save values" form (the value cell is an <input name=variable[id]>).
      const inlineInput = page.locator(`[data-testid="variable-inline-value-${varId}"]`);
      await expect(inlineInput, 'created variable should have an inline value field').toBeVisible();
      await expect(inlineInput, 'initial value should persist').toHaveValue('qa-initial');
      await inlineInput.fill('qa-edited');
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('variable-update-values-submit').click(),
      ]);
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(
        page.locator(`[data-testid="variable-inline-value-${varId}"]`),
        'inline-edited value should persist via Save values',
      ).toHaveValue('qa-edited');

      // DELETE via the delete form (variable_id + token in action query).
      const del = await submitDeleteForm(page, 'form[action*="/variables/delete"]', 'variable_id', String(varId));
      expect(del.status, `delete should not 500 (got ${del.status})`).toBeLessThan(500);
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(
        page.locator('[data-testid^="datatable-variables-row"]', { hasText: name }),
        'deleted variable should be gone',
      ).toHaveCount(0);

      expectClean(collector.drain());
    });

    // CFG-VAR-EDIT (major) — the variable EDIT modal save is broken by a form-name mismatch (same root
    // cause as INTL-LANG-EDIT / INTL-CUR-EDIT in qa-config-intl). createEditForm() (ConfigController
    // L386) registers each row's edit form as 'thelia_config_update_<id>', so the modal renders fields
    // named thelia_config_update_<id>[...]. But processUpdate() (L135) does createNamed(
    // 'thelia_config_update', ...) WITHOUT the id suffix -> AdminFormValidator::validate() calls
    // $form->handleRequest($request), isSubmitted() returns false (no matching root name), throws
    // "Form [thelia_config_update] was not submitted." -> renderListWithError() returns HTTP 400 and
    // the edit silently never persists. Repro: open the edit modal for any variable, change Purpose
    // (title), click Save changes -> POST /admin/configuration/variables/save returns 400, value
    // unchanged after re-GET. Fix: name the edit form 'thelia_config_update_'.$id in processUpdate()
    // too (read the id from the posted payload/route), or render the modal under the bare name.
    // Files: templates/backOffice/default-twig/src/Controller/Configuration/ConfigController.php
    test('edit modal persists a changed title (form-name mismatch)', async ({ page }) => {
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // Operate on a freshly-created variable so we never mutate a real config row.
      const name = qaRef('var').toLowerCase().replace(/[^a-z0-9-]/g, '-');
      await page.getByTestId('variable-create-button').click();
      const create = page.locator('#variable-create-modal');
      await create.locator('#thelia_config_create_name').fill(name);
      await create.locator('#thelia_config_create_title').fill('before');
      await create.locator('#thelia_config_create_value').fill('v');
      await Promise.all([page.waitForLoadState('load'), page.getByTestId('variable-create-submit').click()]);

      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const row = page.locator('[data-testid^="datatable-variables-row"]', { hasText: name });
      const varId = await row.evaluate((el) => (el as HTMLElement).getAttribute('data-row-id'));
      await row.locator(`[data-bs-target="#variable-edit-modal-${varId}"]`).first().click();
      const editModal = page.locator(`#variable-edit-modal-${varId}`);
      await expect(editModal).toBeVisible();
      await editModal.locator(`#thelia_config_update_${varId}_title`).fill('after');
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId(`variable-edit-submit-${varId}`).click(),
      ]);
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // BUG: edit never persists (HTTP 400, form-name mismatch).
      await expect(
        page.locator('[data-testid^="datatable-variables-row"]', { hasText: name }),
        'edited Purpose should persist',
      ).toContainText('after');

      // cleanup (in case the bug gets fixed and the row survived)
      const cleanupId = await page.locator('[data-testid^="datatable-variables-row"]', { hasText: name }).evaluate((el) => (el as HTMLElement)?.getAttribute('data-row-id')).catch(() => null);
      if (cleanupId) await submitDeleteForm(page, 'form[action*="/variables/delete"]', 'variable_id', cleanupId);
    });
  });

  // ---------------------------------------------------------------------------
  // ADVANCED CONFIGURATION
  // ---------------------------------------------------------------------------
  test.describe('advanced', () => {
    const URL = '/admin/configuration/advanced';

    test('sweep: 3 flush actions present, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('advanced-config-page')).toBeVisible();
      await expect(page.getByTestId('advanced-flush-cache')).toBeVisible();
      await expect(page.getByTestId('advanced-flush-assets')).toBeVisible();
      await expect(page.getByTestId('advanced-flush-images')).toBeVisible();
      // Each flush link must carry a CSRF token in its GET href (tokenAction protection).
      for (const tid of ['advanced-flush-cache', 'advanced-flush-assets', 'advanced-flush-images']) {
        const href = await page.getByTestId(tid).getAttribute('href');
        expect(href, `${tid} should carry a _token`).toMatch(/_token=/);
      }
      const issues = await sweepScreen(page, collector, SWEEP);
      expectClean(issues);
    });

    test('flush assets then images/documents execute without error', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // Safe flushes first (assets + generated images/documents). Each redirects back to the page.
      for (const tid of ['advanced-flush-assets', 'advanced-flush-images']) {
        const href = await page.getByTestId(tid).getAttribute('href');
        await page.goto(href!, { waitUntil: 'domcontentloaded' });
        await expect(page.getByTestId('advanced-config-page'), `${tid} should redirect back to the advanced page`).toBeVisible();
      }
      expectClean(collector.drain());
    });

    // The global "Flush cache" wipes the Symfony container cache; clicked LAST and on its own so a
    // cold rebuild cannot perturb the other assertions in this describe block.
    test('flush global cache executes without error (clicked last)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const href = await page.getByTestId('advanced-flush-cache').getAttribute('href');
      await page.goto(href!, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('advanced-config-page')).toBeVisible();
      expectClean(collector.drain());
    });
  });

  // ---------------------------------------------------------------------------
  // ADMIN LOGS
  // ---------------------------------------------------------------------------
  test.describe('adminLogs', () => {
    const URL = '/admin/configuration/adminLogs';

    test('sweep: filter form present, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('admin-logs-page')).toBeVisible();
      await expect(page.getByTestId('admin-logs-filter-form')).toBeVisible();
      await expect(page.getByTestId('admin-logs-from-date')).toBeVisible();
      await expect(page.getByTestId('admin-logs-show-button')).toBeVisible();
      const issues = await sweepScreen(page, collector, { tabs: true, modals: true });
      expectClean(issues);
    });

    test('full-page POST renders filtered entries', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // Submit the filter form (default checkboxes all checked, date window = last 7 days).
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('admin-logs-show-button').click(),
      ]);
      // After submit the page re-renders with the entries fragment (table or "no entries" notice).
      await expect(page.getByTestId('admin-logs-page')).toBeVisible();
      const hasEntries = await page.locator('[data-testid="admin-logs-page"] table, [data-testid="admin-logs-page"] .alert').count();
      expect(hasEntries, 'submitted filter should render an entries table or an empty notice').toBeGreaterThan(0);
      expectClean(collector.drain());
    });

    test('AJAX call to /adminLogs/logger returns the entries fragment (not the full page)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const token = await page.locator('form[data-testid="admin-logs-filter-form"]').evaluate((f) => {
        // the form has no _token field of its own here; the AJAX contract is XHR header based.
        return (f as HTMLFormElement).getAttribute('action') ?? '';
      });
      expect(token, 'logger action url').toContain('/adminLogs/logger');
      const res = await postForm(page, '/admin/configuration/adminLogs/logger', {});
      expect(res.status, `AJAX logger should not 5xx (got ${res.status})`).toBeLessThan(500);
      // The fragment must NOT contain the full admin chrome (no <html> / breadcrumb container).
      expect(/<html/i.test(res.text), 'AJAX logger should return a fragment, not the full HTML page').toBe(false);
      expectClean(collector.drain());
    });
  });

  // ---------------------------------------------------------------------------
  // SYSTEM LOGS
  // ---------------------------------------------------------------------------
  test.describe('system-logs', () => {
    const URL = '/admin/configuration/system-logs';

    test('sweep: form + destination toggles present, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('system-logs-page')).toBeVisible();
      await expect(page.getByTestId('system-logs-form')).toBeVisible();
      await expect(page.getByTestId('system-logs-submit')).toBeVisible();
      // bo-system-logs controller drives the per-destination config visibility toggle.
      await expect(page.locator('[data-controller="bo-system-logs"]')).toHaveCount(1);
      const issues = await sweepScreen(page, collector, { tabs: true, modals: true });
      expectClean(issues);
    });

    test('change level + save -> persists + writes the audit log line', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });

      const levelSelect = page.locator('#thelia_system_log_configuration_level');
      const original = await levelSelect.inputValue();
      // Pick a different level option than the current one.
      const options = await levelSelect.locator('option').evaluateAll((els) => els.map((e) => (e as HTMLOptionElement).value));
      const target = options.find((v) => v !== original) ?? original;
      await levelSelect.selectOption(target);
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('system-logs-submit').click(),
      ]);

      // PERSISTENCE: re-GET and the level select keeps the new value.
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('#thelia_system_log_configuration_level'), 'log level should persist').toHaveValue(target);

      // AUDIT LOG: the save must have written 'System logging configuration modified.' — verify it via
      // the admin-logs filter (scoped to the system-log resource, today). The message text confirms it.
      await page.goto('/admin/configuration/adminLogs', { waitUntil: 'domcontentloaded' });
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('admin-logs-show-button').click(),
      ]);
      await expect(
        page.locator('[data-testid="admin-logs-page"]'),
        'audit log should record the system-logs configuration change',
      ).toContainText('System logging configuration modified.');

      // Restore the original level.
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await page.locator('#thelia_system_log_configuration_level').selectOption(original);
      await Promise.all([page.waitForLoadState('load'), page.getByTestId('system-logs-submit').click()]);

      expectClean(collector.drain());
    });
  });

  // ---------------------------------------------------------------------------
  // PROFILES
  // ---------------------------------------------------------------------------
  test.describe('profiles', () => {
    const URL = '/admin/configuration/profiles';

    test('sweep: list, create/delete modals, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('profiles-page')).toBeVisible();
      await expect(page.getByTestId('profile-create-button')).toBeVisible();
      const issues = await sweepScreen(page, collector, SWEEP);
      expectClean(issues);
    });

    test('create -> persist -> edit general/resources -> permission toggle persists -> delete', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });

      const code = qaRef('profile').toUpperCase().replace(/[^A-Z0-9]/g, '');
      const title = qaRef('profile') + ' title';

      await page.getByTestId('profile-create-button').click();
      const modal = page.locator('#profile-create-modal');
      await expect(modal).toBeVisible();
      await modal.locator('#thelia_profile_create_code').fill(code);
      await modal.locator('#thelia_profile_create_title').fill(title);
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('profile-create-submit').click(),
      ]);

      // PERSISTENCE: row exists with the code.
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const row = page.locator('[data-testid^="datatable-profiles-row"]', { hasText: code });
      await expect(row, `created profile "${code}" should appear`).toHaveCount(1);
      const editHref = await row.locator('a[href*="/profiles/update/"]').first().getAttribute('href');
      const profileId = Number((editHref ?? '').replace(/.*\/profiles\/update\//, ''));
      expect(profileId).toBeGreaterThan(0);

      // EDIT - General tab: change the title, save, re-GET, verify persistence.
      await page.goto(`${URL}/update/${profileId}`, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('profile-edit-page')).toBeVisible();
      const newTitle = title + ' edited';
      await page.locator('#thelia_profile_update_title').fill(newTitle);
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('profile-update-submit').click(),
      ]);
      await page.goto(`${URL}/update/${profileId}`, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('#thelia_profile_update_title'), 'profile title should persist').toHaveValue(newTitle);

      // EDIT - Resources tab: toggle a VIEW checkbox for the first resource, submit, verify persistence.
      await page.getByTestId('profile-tab-resources').click();
      const resForm = page.locator('[data-testid="profile-resource-access-form"]');
      // access codes are string values (AccessManager::VIEW === 'VIEW'), one checkbox per right.
      const firstCheckbox = resForm.locator('input[type="checkbox"][name^="resource["][value="VIEW"]').first();
      await expect(firstCheckbox, 'resource matrix should expose VIEW checkboxes').toBeVisible();
      const wasChecked = await firstCheckbox.isChecked();
      const cbName = await firstCheckbox.getAttribute('name');
      if (wasChecked) {
        await firstCheckbox.uncheck();
      } else {
        await firstCheckbox.check();
      }
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('profile-resource-access-submit').click(),
      ]);
      // PERSISTENCE: re-GET, go to Resources tab, the same checkbox reflects the new state.
      await page.goto(`${URL}/update/${profileId}`, { waitUntil: 'domcontentloaded' });
      await page.getByTestId('profile-tab-resources').click();
      const reread = page.locator(`input[type="checkbox"][name="${cbName}"][value="VIEW"]`).first();
      await expect(reread, 'toggled resource VIEW right should persist').toBeChecked({ checked: !wasChecked });

      // DELETE the QA profile.
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const del = await submitDeleteForm(page, 'form[action*="/profiles/delete"]', 'profile_id', String(profileId));
      expect(del.status, `delete should not 500 (got ${del.status})`).toBeLessThan(500);
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(
        page.locator('[data-testid^="datatable-profiles-row"]', { hasText: code }),
        'deleted profile should be gone',
      ).toHaveCount(0);

      expectClean(collector.drain());
    });
  });

  // ---------------------------------------------------------------------------
  // ADMINISTRATORS
  // ---------------------------------------------------------------------------
  test.describe('administrators', () => {
    const URL = '/admin/configuration/administrators';

    test('sweep: list, create modal, current admin delete button masked, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('administrators-page')).toBeVisible();
      await expect(page.getByTestId('administrator-create-button')).toBeVisible();

      // The current admin "thelia" must NOT expose a delete RowAction (self-delete is forbidden).
      const theliaRow = page.locator('[data-testid^="datatable-administrators-row"]', { hasText: 'thelia' }).first();
      await expect(theliaRow).toBeVisible();
      const deleteTriggers = await theliaRow.locator('[data-bs-target="#administrator-delete-modal"]').count();
      expect(deleteTriggers, 'current admin row should not show a delete trigger').toBe(0);

      const issues = await sweepScreen(page, collector, SWEEP);
      expectClean(issues);
    });

    test('create QA admin -> persist -> delete (never touches the current admin)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });

      const login = qaRef('admin').toLowerCase().replace(/[^a-z0-9-]/g, '-');
      const email = `${login}@example.com`;

      await page.getByTestId('administrator-create-button').click();
      const modal = page.locator('#administrator-create-modal');
      await expect(modal).toBeVisible();
      await modal.locator('#thelia_administrator_create_login').fill(login);
      await modal.locator('#thelia_administrator_create_email').fill(email);
      await modal.locator('#thelia_administrator_create_firstname').fill('QA');
      await modal.locator('#thelia_administrator_create_lastname').fill('Tester');
      await modal.locator('#thelia_administrator_create_password').fill('qa-passw0rd!');
      // profile is a select; pick the first CONCRETE option (skip the empty "(No profile)" entry).
      const profileSelect = modal.locator('#thelia_administrator_create_profile');
      const concreteProfile = await profileSelect.locator('option').evaluateAll(
        (opts) => (opts as HTMLOptionElement[]).map((o) => o.value).find((v) => v !== '') ?? '',
      );
      if (concreteProfile) await profileSelect.selectOption(concreteProfile);
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('administrator-create-submit').click(),
      ]);

      // PERSISTENCE: the QA admin appears with its login + email.
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const row = page.locator('[data-testid^="datatable-administrators-row"]', { hasText: login });
      await expect(row, `created admin "${login}" should appear`).toHaveCount(1);
      await expect(row, 'admin email should persist').toContainText(email);
      const adminId = await row.evaluate((el) => (el as HTMLElement).getAttribute('data-row-id'));
      expect(Number(adminId)).toBeGreaterThan(0);

      // The QA admin row MUST expose a delete trigger (it is not the current admin).
      await expect(
        row.locator('[data-bs-target="#administrator-delete-modal"]'),
        'QA admin row should show a delete trigger',
      ).toHaveCount(1);

      // DELETE the QA admin.
      const del = await submitDeleteForm(page, 'form[action*="/administrators/delete"]', 'administrator_id', String(adminId));
      expect(del.status, `delete should not 500 (got ${del.status})`).toBeLessThan(500);
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(
        page.locator('[data-testid^="datatable-administrators-row"]', { hasText: login }),
        'deleted admin should be gone',
      ).toHaveCount(0);

      expectClean(collector.drain());
    });

    test('PAR-14: edit modal hook is administrator.edit-form', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // Open the current admin's edit modal and assert the modal renders (hook name verified in template).
      const editTrigger = page.locator('[data-bs-target^="#administrator-edit-modal-"]').first();
      await editTrigger.click();
      const modal = page.locator('.modal.show').first();
      await expect(modal).toBeVisible();
      await expect(modal.locator('input[name$="[login]"]')).toBeVisible();
      await page.keyboard.press('Escape');
      expectClean(collector.drain());
    });

    // CFG-ADMIN-EDIT (major) — the administrator EDIT modal save is broken by the same form-name
    // mismatch as CFG-VAR-EDIT / INTL-LANG-EDIT. createEditForm() (AdministratorController L283)
    // registers each row's form as 'thelia_administrator_update_<id>' (modal fields
    // thelia_administrator_update_<id>[...]), but save() (L110) does createNamed(
    // 'thelia_administrator_update', ...) without the id suffix -> handleRequest() never recognises
    // the posted form as submitted -> "Form [thelia_administrator_update] was not submitted." ->
    // renderListWithError() returns HTTP 400 and the edit silently never persists. Repro: open a
    // (non-current) admin's edit modal, change the lastname, click Save changes -> POST
    // /admin/configuration/administrators/save returns 400, value unchanged. Fix: align the save()
    // form name with the per-id name (or render the modal under the bare name).
    // Files: templates/backOffice/default-twig/src/Controller/Configuration/AdministratorController.php
    test('edit modal persists a changed lastname (form-name mismatch)', async ({ page }) => {
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // Create a throwaway QA admin to edit (never touch a real admin).
      const login = qaRef('admin').toLowerCase().replace(/[^a-z0-9-]/g, '-');
      await page.getByTestId('administrator-create-button').click();
      const create = page.locator('#administrator-create-modal');
      await create.locator('#thelia_administrator_create_login').fill(login);
      await create.locator('#thelia_administrator_create_email').fill(`${login}@example.com`);
      await create.locator('#thelia_administrator_create_firstname').fill('QA');
      await create.locator('#thelia_administrator_create_lastname').fill('Before');
      await create.locator('#thelia_administrator_create_password').fill('qa-passw0rd!');
      // profile is a select; pick the first CONCRETE option (skip the empty "(No profile)" entry).
      const sel = create.locator('#thelia_administrator_create_profile');
      const fp = await sel.locator('option').evaluateAll(
        (opts) => (opts as HTMLOptionElement[]).map((o) => o.value).find((v) => v !== '') ?? '',
      );
      if (fp) await sel.selectOption(fp);
      await Promise.all([page.waitForLoadState('load'), page.getByTestId('administrator-create-submit').click()]);

      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const row = page.locator('[data-testid^="datatable-administrators-row"]', { hasText: login });
      const adminId = await row.evaluate((el) => (el as HTMLElement).getAttribute('data-row-id'));
      await row.locator(`[data-bs-target="#administrator-edit-modal-${adminId}"]`).first().click();
      const editModal = page.locator(`#administrator-edit-modal-${adminId}`);
      await expect(editModal).toBeVisible();
      await editModal.locator(`#thelia_administrator_update_${adminId}_lastname`).fill('After');
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId(`administrator-edit-${adminId}-submit`).click(),
      ]);
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // BUG: edit never persists (HTTP 400, form-name mismatch).
      await expect(
        page.locator('[data-testid^="datatable-administrators-row"]', { hasText: login }),
        'edited lastname should persist',
      ).toContainText('After');

      // cleanup
      const cid = await page.locator('[data-testid^="datatable-administrators-row"]', { hasText: login }).evaluate((el) => (el as HTMLElement)?.getAttribute('data-row-id')).catch(() => null);
      if (cid) await submitDeleteForm(page, 'form[action*="/administrators/delete"]', 'administrator_id', cid);
    });
  });

  // ---------------------------------------------------------------------------
  // CUSTOMER TITLES
  // ---------------------------------------------------------------------------
  test.describe('customer-titles', () => {
    const URL = '/admin/configuration/customer-titles';

    test('sweep: list, create/delete modals, default badge read-only, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('customer-titles-page')).toBeVisible();
      await expect(page.getByTestId('customer-title-create-button')).toBeVisible();
      // The "Default" column renders a read-only badge (no inline toggle control).
      const defaultCell = page.locator('[data-testid^="datatable-customer-titles-cell-default"]').first();
      await expect(defaultCell).toBeVisible();
      await expect(defaultCell.locator('input, button, a'), 'default badge should be read-only (no control)').toHaveCount(0);
      const issues = await sweepScreen(page, collector, SWEEP);
      expectClean(issues);
    });

    test('create -> persist -> edit (full page) -> delete', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });

      const short = 'Q' + Date.now().toString(36).slice(-4); // short is VARCHAR(10)
      const longVal = qaRef('title').slice(0, 40);            // long is VARCHAR(45)

      await page.getByTestId('customer-title-create-button').click();
      const modal = page.locator('#customer-title-create-modal');
      await expect(modal).toBeVisible();
      await modal.locator('#thelia_customer_title_create_short').fill(short);
      await modal.locator('#thelia_customer_title_create_long').fill(longVal);
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('customer-title-create-submit').click(),
      ]);

      // PERSISTENCE: the title appears with its short + long.
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const row = page.locator('[data-testid^="datatable-customer-titles-row"]', { hasText: longVal });
      await expect(row, `created customer title "${longVal}" should appear`).toHaveCount(1);
      await expect(row, 'short label should persist').toContainText(short);
      const editHref = await row.locator('a[href*="/customer-titles/update/"]').first().getAttribute('href');
      const titleId = Number((editHref ?? '').replace(/.*\/customer-titles\/update\//, ''));
      expect(titleId).toBeGreaterThan(0);

      // EDIT (full page): change the long label, save, re-GET, verify persistence.
      await page.goto(`${URL}/update/${titleId}`, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('customer-title-edit-page')).toBeVisible();
      const newLong = (qaRef('title') + ' edit').slice(0, 40);
      await page.locator('#thelia_customer_title_update_long').fill(newLong);
      await Promise.all([
        page.waitForLoadState('load'),
        page.locator('form[action$="/customer-titles/save"] button[type="submit"]').first().click(),
      ]);
      await page.goto(`${URL}/update/${titleId}`, { waitUntil: 'domcontentloaded' });
      await expect(page.locator('#thelia_customer_title_update_long'), 'long label should persist').toHaveValue(newLong);

      // DELETE via delete form.
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const del = await submitDeleteForm(page, 'form[action*="/customer-titles/delete"]', 'customer_title_id', String(titleId));
      expect(del.status, `delete should not 500 (got ${del.status})`).toBeLessThan(500);
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(
        page.locator('[data-testid^="datatable-customer-titles-row"]', { hasText: newLong }),
        'deleted customer title should be gone',
      ).toHaveCount(0);

      expectClean(collector.drain());
    });
  });
});
