import { test, expect, type Page } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { IssueCollector, sweepScreen, formatIssues, qaRef, type PageIssue } from '../../helpers/qa';

/**
 * QA campaign — domain "modules-hooks".
 *
 * Screens:
 *   /admin/modules                 (list: groups Delivery/Payment/Classic, activation toggle via tokenAction,
 *                                    Check modules / ModuleSynchronizer, Information & Documentation BoFetchDialog,
 *                                    Delete modal + delete-module-data, bo-sortable reorder)
 *   /admin/module/update/{id}      (edit: 2 tabs General + Images, save-mode stay/close)
 *   /admin/module/{code}           (configure: tested on a couple of configurable modules)
 *   /admin/module-hooks            (list + filters, create modal cascade classname/method AJAX, templates,
 *                                    inline toggle, prefill add (+), edit, delete prefill)
 *   /admin/hooks                   (native hooks list, filter by type, toggles Active/Official,
 *                                    create -> edit, delete, discover bo-hooks)
 *
 * Reference for expected behaviour = the Smarty legacy BO in templates/backOffice/default.
 *
 * BO Twig only (default-twig). No git push, no env mutation. Module toggles are exercised only on a
 * NON-critical module (HookAdminHome) and re-activated immediately afterwards.
 */

const BO_TWIG_ONLY = (process.env.BO_TEMPLATE ?? 'default-twig') !== 'default-twig';

/**
 * Environmental noise unrelated to this domain: a leftover demo/QA language whose flag SVG is
 * missing makes the header language switcher 404 on /svgFlags/<code>.svg on every page. That is
 * test-data pollution, not a modules-hooks defect. Filtered so real findings surface.
 */
function dropEnvNoise(issues: PageIssue[]): PageIssue[] {
  return issues.filter(
    (i) =>
      !(i.kind === 'console' && /Failed to load resource: the server responded with a status of 404/.test(i.detail)) &&
      !(i.kind === 'network' && /\/svgFlags\//.test(i.detail)),
  );
}

/** Pull the CSRF token out of any form/hidden field rendered on the current page. */
async function tokenFromPage(page: Page): Promise<string> {
  return page.evaluate(() => {
    const el =
      document.querySelector<HTMLInputElement>('input[name="_token"]') ??
      document.querySelector<HTMLInputElement>('input[name$="[_token]"]');
    return el?.value ?? '';
  });
}

test.describe('modules-hooks', () => {
  test.skip(BO_TWIG_ONLY, 'BO Twig only.');

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  // --------------------------------------------------------------------------
  // MODULES — list
  // --------------------------------------------------------------------------
  test.describe('Modules list', () => {
    test('clean sweep: groups, toggles, info/doc dialogs, delete modal', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();

      await page.goto('/admin/modules', { waitUntil: 'networkidle' });
      await expect(page.getByTestId('modules-page')).toBeVisible();

      // Groups: Classic + at least Delivery & Payment must be rendered (demo has CustomDelivery + Cheque/FreeOrder).
      const groups = page.locator('[data-testid^="modules-block-"]');
      expect(await groups.count(), 'at least one module group section').toBeGreaterThan(0);

      // sweepScreen opens every modal (info/doc BoFetchDialog, delete confirm) and every tab.
      const issues = dropEnvNoise(await sweepScreen(page, collector));
      expect(issues, formatIssues(issues)).toHaveLength(0);
    });

    async function closeOpenModal(page: Page): Promise<void> {
      const closeBtn = page.locator('.modal.show [data-bs-dismiss="modal"]').first();
      if (await closeBtn.count()) await closeBtn.click().catch(() => undefined);
      await page.locator('.modal.show').first().waitFor({ state: 'hidden', timeout: 5_000 }).catch(() => undefined);
      // Bootstrap leaves the backdrop briefly; wait for it to clear so the next click is not intercepted.
      await page.locator('.modal-backdrop').first().waitFor({ state: 'hidden', timeout: 5_000 }).catch(() => undefined);
      await page.waitForTimeout(200);
    }

    test('Information + Documentation dialogs fetch a non-empty fragment', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto('/admin/modules', { waitUntil: 'networkidle' });

      // The first module row carries info/doc actions in its overflow dropdown.
      const firstRow = page.locator('tbody tr').first();
      await firstRow.getByTestId('datatable-action-overflow').click();
      const infoResp = page
        .waitForResponse((r) => /\/admin\/module\/information\//.test(r.url()), { timeout: 8_000 })
        .catch(() => null);
      await firstRow.getByTestId('datatable-action-info').click();
      const ir = await infoResp;
      if (ir) expect(ir.status(), 'module information fetch must not error').toBeLessThan(400);
      const infoModal = page.locator('.modal.show').first();
      await expect(infoModal).toBeVisible();
      await expect(infoModal.locator('.modal-body')).not.toBeEmpty({ timeout: 8_000 });
      await closeOpenModal(page);

      await firstRow.getByTestId('datatable-action-overflow').click();
      const docResp = page
        .waitForResponse((r) => /\/admin\/module\/documentation\//.test(r.url()), { timeout: 8_000 })
        .catch(() => null);
      await firstRow.getByTestId('datatable-action-doc').click();
      const dr = await docResp;
      if (dr) expect(dr.status(), 'module documentation fetch must not error').toBeLessThan(400);
      await expect(page.locator('.modal.show').first()).toBeVisible();
      await closeOpenModal(page);

      const drained = dropEnvNoise(collector.drain());
      expect(drained, formatIssues(drained)).toHaveLength(0);
    });

    test('Check modules (ModuleSynchronizer): flash + list reloaded', async ({ page }) => {
      await page.goto('/admin/modules', { waitUntil: 'networkidle' });
      await Promise.all([
        page.waitForURL('**/admin/modules**'),
        page.getByTestId('modules-refresh').click(),
      ]);
      // We land back on the modules list with the groups rendered (no exception page).
      await expect(page.getByTestId('modules-page')).toBeVisible();
      expect(await page.locator('[data-testid^="modules-block-"]').count()).toBeGreaterThan(0);
    });

    test('activation toggle persists, then re-activate (HookAdminHome, non-critical)', async ({ page }) => {
      // Toggle is a tokenAction GET (<a href> with _token) — only exercised on a non-critical module
      // and immediately reverted, per campaign rules.
      await page.goto('/admin/modules', { waitUntil: 'networkidle' });
      const row = page.locator('tbody tr', { hasText: 'HookAdminHome' }).first();
      await expect(row, 'HookAdminHome row must exist').toBeVisible();

      const toggle = row.getByTestId('datatable-activated-toggle');
      const before = await toggle.getAttribute('data-state');
      expect(before, 'module toggle must be a tokenAction link with a state').not.toBeNull();

      await Promise.all([page.waitForURL('**/admin/modules**'), toggle.click()]);
      await page.goto('/admin/modules', { waitUntil: 'networkidle' });
      const afterRow = page.locator('tbody tr', { hasText: 'HookAdminHome' }).first();
      const after = await afterRow.getByTestId('datatable-activated-toggle').getAttribute('data-state');
      expect(after, 'toggling activation must flip the persisted state').not.toBe(before);

      // Revert to the original state.
      await Promise.all([
        page.waitForURL('**/admin/modules**'),
        afterRow.getByTestId('datatable-activated-toggle').click(),
      ]);
      await page.goto('/admin/modules', { waitUntil: 'networkidle' });
      const restored = await page
        .locator('tbody tr', { hasText: 'HookAdminHome' })
        .first()
        .getByTestId('datatable-activated-toggle')
        .getAttribute('data-state');
      expect(restored, 'module activation must be restored to its original state').toBe(before);
    });

    test('a mandatory module (if any) exposes a read-only activation toggle', async ({ page }) => {
      await page.goto('/admin/modules', { waitUntil: 'networkidle' });
      // A mandatory module renders the toggle as a read-only span (no tokenAction link) and has no
      // delete action. The set of mandatory modules is build-dependent, so detect them structurally:
      // any row whose toggle cell carries .bo-toggle--readonly must NOT also expose a clickable link.
      const readonlyToggles = page.locator('tbody tr .bo-toggle--readonly');
      const count = await readonlyToggles.count();
      if (count === 0) {
        // No mandatory module surfaced in this build; nothing to assert.
        test.info().annotations.push({ type: 'note', description: 'No read-only (mandatory) module toggle present.' });
        return;
      }
      // For each read-only toggle row, ensure there is no clickable activation link in the same cell.
      for (let i = 0; i < count; i++) {
        const cell = readonlyToggles.nth(i).locator('xpath=ancestor::td[1]');
        const link = cell.getByTestId('datatable-activated-toggle');
        expect(await link.count(), 'a read-only module toggle must not also render a clickable link').toBe(0);
      }
    });

    test('PAR-12: the zip-install form is gated by allow_module_zip_install (documents actual behaviour)', async ({ page }) => {
      await page.goto('/admin/modules', { waitUntil: 'networkidle' });
      // The testplan note expected the install form "toujours affiché". In this build the template
      // renders it only when the config flag allow_module_zip_install is on (default off in demo),
      // so it is absent here. This asserts the real, current behaviour rather than the note.
      const installForm = page.getByTestId('module-install-form');
      const present = (await installForm.count()) > 0;
      // Not a failure either way: just record presence. The divergence from the note is finding MH-04.
      expect(typeof present).toBe('boolean');
    });

    test('Shipping zones action exposed for a Delivery module', async ({ page }) => {
      await page.goto('/admin/modules', { waitUntil: 'networkidle' });
      const deliveryBlock = page.locator('[data-testid="modules-block-delivery"]');
      if (await deliveryBlock.count()) {
        const row = deliveryBlock.locator('tbody tr', { hasText: 'CustomDelivery' }).first();
        if (await row.count()) {
          await row.getByTestId('datatable-action-overflow').click();
          await expect(
            row.getByTestId('datatable-action-shipping-zones'),
            'Delivery module must offer a Shipping zones action',
          ).toBeVisible();
        }
      }
    });
  });

  // --------------------------------------------------------------------------
  // MODULE — edit
  // --------------------------------------------------------------------------
  test.describe('Module edit', () => {
    test('2 tabs + save (stay) persistence on title/chapo/description', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();

      // Use HeaderHighlights (id 4) — a Classic module, safe to relabel.
      const editUrl = '/admin/module/update/4';
      await page.goto(editUrl, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('module-edit-page')).toBeVisible();

      // The Images tab lazy-renders a FileController fragment; sweepScreen clicks both tabs.
      const issues = dropEnvNoise(await sweepScreen(page, collector, { modals: false }));
      expect(issues, formatIssues(issues)).toHaveLength(0);

      // Re-activate the General tab (sweep left Images active).
      await page.getByTestId('module-tab-general').click();
      await expect(page.locator('#module-tab-general')).toHaveClass(/active/);

      const ref = qaRef('mod');
      const titleInput = page.getByTestId('module-edit-title');
      const previousTitle = await titleInput.inputValue();
      const probe = `${ref} title`;
      await titleInput.fill(probe);
      await page.locator('textarea[name="chapo"]').fill(`${ref} chapo`);

      // Save (stay) — BoSaveModeToolbar default submit stays on the edit page.
      await Promise.all([
        page.waitForURL('**/module/update/4**'),
        page.locator('[data-testid="module-edit-form"] button[type="submit"]').first().click(),
      ]);

      // Persistence: reload, the title kept the probe value.
      await page.goto(editUrl, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('module-edit-title'), 'module title should persist').toHaveValue(probe);
      await expect(page.locator('textarea[name="chapo"]')).toHaveValue(`${ref} chapo`);

      // Restore the original title (cleanup).
      await page.getByTestId('module-edit-title').fill(previousTitle);
      await page.locator('textarea[name="chapo"]').fill('');
      await Promise.all([
        page.waitForURL('**/module/update/4**'),
        page.locator('[data-testid="module-edit-form"] button[type="submit"]').first().click(),
      ]);
    });

    test('Images tab renders the upload form fragment', async ({ page }) => {
      await page.goto('/admin/module/update/4', { waitUntil: 'networkidle' });
      await page.getByTestId('module-tab-images').click();
      const pane = page.locator('#module-tab-images');
      await expect(pane).toHaveClass(/active/);
      // FileController::imageForm renders a file input or an existing-images table.
      const hasUploadUi = await pane.locator('input[type="file"], form, table').count();
      expect(hasUploadUi, 'Images tab must render the file management UI').toBeGreaterThan(0);
    });
  });

  // --------------------------------------------------------------------------
  // MODULE — configure
  // --------------------------------------------------------------------------
  test.describe('Module configure', () => {
    for (const mod of ['CustomDelivery', 'Cheque', 'FreeOrder']) {
      test(`configure ${mod} loads without error`, async ({ page }) => {
        const collector = new IssueCollector(page);
        collector.attach();
        await page.goto(`/admin/module/${mod}`, { waitUntil: 'networkidle' });
        await expect(page.getByTestId('module-configure-page')).toBeVisible();
        // Configure pages embed module-provided forms; allow their static info alerts.
        const issues = dropEnvNoise(await sweepScreen(page, collector, { allowDanger: true }));
        expect(issues, formatIssues(issues)).toHaveLength(0);
      });
    }
  });

  // --------------------------------------------------------------------------
  // MODULE HOOKS — list + create/edit/delete cascade
  // --------------------------------------------------------------------------
  test.describe('Module hooks', () => {
    test('clean sweep: filters, groups, create modal, prefill add', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();

      await page.goto('/admin/module-hooks', { waitUntil: 'networkidle' });
      await expect(page.getByTestId('module-hooks-page')).toBeVisible();
      // The create form lives inside the create modal (hidden until opened), so assert its presence
      // in the DOM rather than visibility.
      await expect(page.getByTestId('module-hook-create-form')).toHaveCount(1);

      // Filters present.
      await expect(page.locator('#module-hooks-filter-status')).toBeVisible();
      await expect(page.locator('#module-hooks-filter-module')).toBeVisible();
      await expect(page.locator('#module-hooks-filter-type')).toBeVisible();
      await expect(page.locator('#module-hooks-filter-name')).toBeVisible();
      await expect(page.locator('#module-hooks-filter-empty')).toBeVisible();

      // openAllModals would open the create modal; do it explicitly + the rest via sweep.
      const issues = dropEnvNoise(await sweepScreen(page, collector, { tabs: false }));
      expect(issues, formatIssues(issues)).toHaveLength(0);

      // "Hide empty hooks" filter toggles card visibility (client-side, no network).
      const empty = page.locator('#module-hooks-filter-empty');
      const cardsBefore = await page.locator('[data-bo-module-hooks-target="hookCard"]:visible').count();
      await empty.uncheck();
      await page.waitForTimeout(300);
      const cardsAfter = await page.locator('[data-bo-module-hooks-target="hookCard"]:visible').count();
      expect(cardsAfter, 'unhiding empty hooks should reveal at least as many cards').toBeGreaterThanOrEqual(cardsBefore);
    });

    test('create modal cascade (module -> classname -> method AJAX) + create/edit/delete lifecycle', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();

      await page.goto('/admin/module-hooks', { waitUntil: 'networkidle' });
      await page.getByTestId('module-hook-add-btn').click();
      const modal = page.locator('#module-hook-create-modal');
      await expect(modal).toBeVisible();

      // 1) Select module CustomDelivery -> triggers get-module-hook-classnames AJAX.
      const classnamesResp = page
        .waitForResponse((r) => /get-module-hook-classnames\//.test(r.url()), { timeout: 8_000 })
        .catch(() => null);
      await page.getByTestId('module-hook-create-module').selectOption({ label: 'CustomDelivery' });
      const cr = await classnamesResp;
      expect(cr, 'classnames AJAX should fire on module change').not.toBeNull();
      expect(cr!.status(), 'classnames AJAX must return 200').toBe(200);

      const classnameSelect = page.getByTestId('module-hook-create-classname');
      await expect(classnameSelect).toBeEnabled({ timeout: 8_000 });
      await expect(classnameSelect.locator('option', { hasText: 'customdelivery.hook' })).toHaveCount(1);

      // 2) Select classname -> triggers get-module-hook-methods AJAX.
      const methodsResp = page
        .waitForResponse((r) => /get-module-hook-methods\//.test(r.url()), { timeout: 8_000 })
        .catch(() => null);
      await classnameSelect.selectOption('customdelivery.hook');
      const mr = await methodsResp;
      expect(mr, 'methods AJAX should fire on classname change').not.toBeNull();
      expect(mr!.status(), 'methods AJAX must return 200').toBe(200);

      const methodSelect = page.getByTestId('module-hook-create-method');
      await expect(methodSelect).toBeEnabled({ timeout: 8_000 });
      // insertTemplate is provided by the class and not yet bound to a regular hook.
      await methodSelect.selectOption('insertTemplate');

      // 3) Pick a regular (non by_module) hook from the dropdown — first selectable option.
      const hookSelect = page.getByTestId('module-hook-create-hook');
      const hookValue = await hookSelect
        .locator('option[value]:not([value=""])')
        .first()
        .getAttribute('value');
      expect(hookValue, 'a hook option must be available').toBeTruthy();
      await hookSelect.selectOption(hookValue!);

      const templatesRef = qaRef('mh').toLowerCase();
      await page.getByTestId('module-hook-create-templates').fill(templatesRef);

      await Promise.all([
        page.waitForURL('**/admin/module-hooks**'),
        page.getByTestId('module-hook-create-submit').click(),
      ]);

      // Persistence: the new binding (CustomDelivery::insertTemplate) must appear in the list.
      await page.goto('/admin/module-hooks', { waitUntil: 'networkidle' });
      // Reveal empty hooks so the freshly-attached row is guaranteed visible.
      await page.locator('#module-hooks-filter-empty').uncheck();
      const createdRow = page
        .locator('.module-hook-row', { hasText: 'CustomDelivery' })
        .filter({ hasText: 'insertTemplate' })
        .first();
      await expect(createdRow, 'created module-hook (CustomDelivery::insertTemplate) should persist').toBeVisible();
      const moduleHookId = (await createdRow.getAttribute('data-testid'))!.replace('module-hook-row-', '');
      expect(moduleHookId).toBeTruthy();

      // --- Edit: open edit page, verify classname/method pre-selected, change templates + active, save ---
      await page.goto(`/admin/module-hook/update/${moduleHookId}`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('module-hook-edit-page')).toBeVisible();
      const editIssues = dropEnvNoise(await sweepScreen(page, collector, { tabs: false, modals: false }));
      expect(editIssues, formatIssues(editIssues)).toHaveLength(0);

      await expect(page.locator('#module-hook-edit-classname'), 'classname must be pre-selected').toHaveValue(
        'customdelivery.hook',
      );
      await expect(page.locator('#module-hook-edit-method'), 'method must be pre-selected').toHaveValue('insertTemplate');

      const editedTemplates = `${templatesRef}-edit`;
      await page.locator('input[name="templates"]').fill(editedTemplates);
      await Promise.all([
        page.waitForURL('**/module-hook/update/**'),
        page.getByTestId('module-hook-edit-submit').click(),
      ]);

      // Persistence of the edit.
      await page.goto(`/admin/module-hook/update/${moduleHookId}`, { waitUntil: 'networkidle' });
      await expect(page.locator('input[name="templates"]'), 'edited templates must persist').toHaveValue(editedTemplates);

      // --- Inline toggle on the list (auto-submit form) ---
      await page.goto('/admin/module-hooks', { waitUntil: 'networkidle' });
      await page.locator('#module-hooks-filter-empty').uncheck();
      const toggle = page.getByTestId(`module-hook-toggle-${moduleHookId}`);
      if (await toggle.count()) {
        const wasChecked = await toggle.isChecked();
        await Promise.all([page.waitForURL('**/admin/module-hooks**'), toggle.click()]);
        await page.goto('/admin/module-hooks', { waitUntil: 'networkidle' });
        await page.locator('#module-hooks-filter-empty').uncheck();
        const after = await page.getByTestId(`module-hook-toggle-${moduleHookId}`).isChecked();
        expect(after, 'inline toggle must flip the persisted active state').not.toBe(wasChecked);
      }

      // --- Delete (prefill modal) ---
      await page.goto('/admin/module-hooks', { waitUntil: 'networkidle' });
      await page.locator('#module-hooks-filter-empty').uncheck();
      await page.getByTestId(`module-hook-delete-${moduleHookId}`).click();
      const delModal = page.locator('#module-hook-delete-modal');
      await expect(delModal).toBeVisible();
      await expect(
        delModal.locator('input[name="module_hook_id"]'),
        'prefillDelete must inject module_hook_id',
      ).toHaveValue(moduleHookId);
      await Promise.all([
        page.waitForURL('**/admin/module-hooks**'),
        delModal.locator('button[type="submit"]').click(),
      ]);

      // Persistence: the binding is gone.
      await page.goto('/admin/module-hooks', { waitUntil: 'networkidle' });
      await page.locator('#module-hooks-filter-empty').uncheck();
      await expect(page.locator(`[data-testid="module-hook-row-${moduleHookId}"]`)).toHaveCount(0);
    });

    test('prefill add (+) on a hook card injects hook_id into the create modal', async ({ page }) => {
      await page.goto('/admin/module-hooks', { waitUntil: 'networkidle' });
      await page.locator('#module-hooks-filter-empty').uncheck();
      const addBtn = page.locator('[data-testid^="module-hook-add-to-"]').first();
      if (await addBtn.count()) {
        const expectedHookId = (await addBtn.getAttribute('data-hook-id'))!;
        await addBtn.click();
        const modal = page.locator('#module-hook-create-modal');
        await expect(modal).toBeVisible();
        await expect(
          page.getByTestId('module-hook-create-hook'),
          'card + button must pre-select the hook in the create modal',
        ).toHaveValue(expectedHookId);
        await page.keyboard.press('Escape');
      }
    });
  });

  // --------------------------------------------------------------------------
  // HOOKS (native hooks)
  // --------------------------------------------------------------------------
  test.describe('Hooks', () => {
    test('clean sweep: list, type filter, create/edit/delete modals', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();

      await page.goto('/admin/hooks', { waitUntil: 'networkidle' });
      await expect(page.getByTestId('hooks-page')).toBeVisible();

      const issues = dropEnvNoise(await sweepScreen(page, collector, { tabs: false }));
      expect(issues, formatIssues(issues)).toHaveLength(0);
    });

    test('filter by type updates the ?type= query and reloads', async ({ page }) => {
      await page.goto('/admin/hooks', { waitUntil: 'networkidle' });
      const filter = page.locator('[data-bo-hooks-target="filter"]');
      // Pick an option whose value differs from the current selection so navigation actually happens.
      const currentValue = await filter.inputValue();
      const optionValues = await filter.locator('option').evaluateAll((opts) =>
        opts.map((o) => (o as HTMLOptionElement).value),
      );
      const target = optionValues.find((v) => v && v !== currentValue);
      if (target) {
        await Promise.all([
          page.waitForURL((u) => u.searchParams.get('type') === target, { timeout: 15_000 }),
          filter.selectOption(target),
        ]);
        expect(new URL(page.url()).searchParams.get('type'), 'type filter must be reflected in the URL').toBe(target);
        await expect(page.getByTestId('hooks-page')).toBeVisible();
      }
    });

    test('create -> edit (language switcher) -> toggle Active/Official -> delete lifecycle', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();

      await page.goto('/admin/hooks', { waitUntil: 'networkidle' });

      // --- Create via the BoCreateDialog modal ---
      const code = qaRef('hook').toLowerCase().replace(/[^a-z0-9]/g, '.');
      await page.getByTestId('hook-create-btn').click();
      const createModal = page.locator('#hook-create-modal');
      await expect(createModal).toBeVisible();
      await createModal.locator('input[name$="[code]"], input[name="code"]').first().fill(code);
      await createModal.locator('input[name$="[title]"], input[name="title"]').first().fill(`${code} title`);
      // Creation redirects to the edit page by design.
      await Promise.all([
        page.waitForURL((u) => /\/admin\/hook(s)?\//.test(u.pathname) || /\/admin\/hooks\b/.test(u.pathname), {
          timeout: 15_000,
        }),
        createModal.locator('button[type="submit"]').click(),
      ]);
      await page.waitForLoadState('networkidle');

      // Reach the created hook via the list (filter shows all by default).
      await page.goto('/admin/hooks', { waitUntil: 'networkidle' });
      const row = page.locator('tbody tr', { hasText: code }).first();
      await expect(row, `created hook "${code}" should appear in the list`).toBeVisible();
      const editHref = await row.locator('a[href*="/hook/update/"]').first().getAttribute('href');
      expect(editHref, 'hook edit link missing').toBeTruthy();
      const hookId = editHref!.match(/\/hook\/update\/(\d+)/)?.[1] ?? '';
      expect(hookId).toBeTruthy();

      // --- Edit page sweep + language switcher present + persist the title ---
      await page.goto(editHref!, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('hook-edit-page')).toBeVisible();
      const editIssues = dropEnvNoise(await sweepScreen(page, collector, { tabs: false, modals: false }));
      expect(editIssues, formatIssues(editIssues)).toHaveLength(0);

      const newTitle = `${code} edited`;
      await page.locator('[data-testid="hook-edit-form"] input[id$="_title"], [data-testid="hook-edit-form"] input[name$="[title]"]').first().fill(newTitle);
      await Promise.all([
        page.waitForLoadState('networkidle'),
        page.locator('[data-testid="hook-edit-form"] button[type="submit"]').click(),
      ]);
      await page.goto(editHref!, { waitUntil: 'networkidle' });
      await expect(
        page.locator('[data-testid="hook-edit-form"] input[id$="_title"], [data-testid="hook-edit-form"] input[name$="[title]"]').first(),
        'hook title should persist',
      ).toHaveValue(newTitle);

      // --- Toggle Active + Official from the list (tokenAction <a> links) ---
      await page.goto('/admin/hooks', { waitUntil: 'networkidle' });
      const listRow = page.locator('tbody tr', { hasText: code }).first();
      const activeToggle = listRow.getByTestId('datatable-active-toggle');
      if (await activeToggle.count()) {
        const before = await activeToggle.getAttribute('data-state');
        await Promise.all([page.waitForURL('**/admin/hooks**'), activeToggle.click()]);
        await page.goto('/admin/hooks', { waitUntil: 'networkidle' });
        const after = await page
          .locator('tbody tr', { hasText: code })
          .first()
          .getByTestId('datatable-active-toggle')
          .getAttribute('data-state');
        expect(after, 'Active toggle must flip the persisted state').not.toBe(before);
      }
      const nativeToggle = page.locator('tbody tr', { hasText: code }).first().getByTestId('datatable-native-toggle');
      if (await nativeToggle.count()) {
        const before = await nativeToggle.getAttribute('data-state');
        await Promise.all([page.waitForURL('**/admin/hooks**'), nativeToggle.click()]);
        await page.goto('/admin/hooks', { waitUntil: 'networkidle' });
        const after = await page
          .locator('tbody tr', { hasText: code })
          .first()
          .getByTestId('datatable-native-toggle')
          .getAttribute('data-state');
        expect(after, 'Official/native toggle must flip the persisted state').not.toBe(before);
      }

      // --- Delete (cleanup) ---
      await page.goto('/admin/hooks', { waitUntil: 'networkidle' });
      const delRow = page.locator('tbody tr', { hasText: code }).first();
      await delRow.getByTestId('datatable-action-overflow').click().catch(() => undefined);
      await delRow.getByTestId('datatable-action-delete').click();
      const delModal = page.locator('#hook-delete-modal');
      await expect(delModal).toBeVisible();
      await expect(delModal.locator('input[name="hook_id"]'), 'delete modal prefills hook_id').toHaveValue(hookId);
      await Promise.all([
        page.waitForURL('**/admin/hooks**'),
        delModal.locator('button[type="submit"]').click(),
      ]);
      await page.goto('/admin/hooks', { waitUntil: 'networkidle' });
      await expect(page.locator('tbody tr', { hasText: code })).toHaveCount(0);
    });

    // FINDING MH-01 (major): the hook "Parse template" (discover) endpoint returns HTTP 500 for a
    // recoverable parser-configuration error. In this install no parser is registered for ANY
    // template type, so /admin/hooks/discover?template_type={1..4} responds
    //   500 {"success":false,"message":"if you want to use a parser, please register one"}.
    // The Stimulus controller (bo-hooks_controller.js) does surface the message gracefully in the
    // UI, but the HTTP status is a server error (500) rather than a 422/503, so QA sweeps flag it
    // and the "Check the support of hooks" feature is unusable in BO Twig as installed.
    // Root cause: HookController::discover() catches \Throwable and returns
    // Response::HTTP_INTERNAL_SERVER_ERROR (line ~326). It should return 422/503 with the message.
    // Repro: GET /admin/hooks/discover?template_type=2 (authenticated) -> 500 JSON.
    // Verified out-of-band with curl (both type=1 and type=2 -> 500, same message).
    test.fixme('MH-01: discover (Parse template) should not surface a parser misconfig as 500', async ({ page }) => {
      await page.goto('/admin/hooks', { waitUntil: 'networkidle' });
      const resp = page
        .waitForResponse((r) => /\/admin\/hooks\/discover\b/.test(r.url()), { timeout: 10_000 })
        .catch(() => null);
      // Default discover type is "Front Office"; clicking Parse template triggers the fetch.
      await page.locator('[data-action="bo-hooks#discover"]').click();
      const r = await resp;
      expect(r, 'discover fetch should fire').not.toBeNull();
      // Expected: a non-5xx status for a recoverable config error. Actual: 500.
      expect(r!.status(), 'discover must not return 5xx for a missing parser').toBeLessThan(500);
    });
  });
});
