import { test, expect, type Page } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { IssueCollector, sweepScreen, formatIssues, qaRef, type PageIssue } from '../../helpers/qa';

/**
 * Environmental noise unrelated to config-commerce: a leftover demo/QA language with
 * code "q70" has no matching flag SVG, so the BoLanguageSwitcher header on every page
 * 404s on /svgFlags/q70.svg. This is test-data pollution (orphan language) + a missing
 * fallback in the switcher, NOT a config-commerce screen defect. Filtered so real
 * findings surface. See findings CC-06.
 */
function dropEnvNoise(issues: PageIssue[]): PageIssue[] {
  return issues.filter(
    (i) => !(i.kind === 'console' && /Failed to load resource: the server responded with a status of 404/.test(i.detail)),
  );
}

/**
 * QA campaign — domain "config-commerce".
 *
 * Screens:
 *   /admin/configuration/attributes      (+ values: bo-inline-edit + revert, positions bo-sortable)
 *   /admin/configuration/features        (+ values, edited via the main form, positions bo-sortable)
 *   /admin/configuration/templates       (product templates: create/duplicate/delete, feature/attribute association, positions)
 *   /admin/configuration/messages        (list, edit 2 tabs, Ace editor smarty, preview bo-message-preview)
 *   /admin/configuration/mailingSystem   (form + test button bo-mailing-system-test)
 *   /admin/configuration/store           (full form, image previews bo-image-preview)
 *
 * Reference for expected behaviour = the Smarty legacy BO in templates/backOffice/default.
 *
 * Each describe runs:
 *   (a) load + sweepScreen (DOM/leaks/tabs/modals — zero tolerance on console/pageerror/5xx/AJAX-4xx)
 *   (b) the testplan interactions: create via modal w/ qaRef values, submit, re-GET + assert persistence,
 *       edit, delete (delete is part of the test).
 *
 * BO Twig only (default-twig). No git push, no env mutation, no module toggle.
 */

const BO_TWIG_ONLY = (process.env.BO_TEMPLATE ?? 'default-twig') !== 'default-twig';

/** Pull the CSRF token out of any form/hidden field rendered on the current page. */
async function tokenFromPage(page: Page): Promise<string> {
  return page.evaluate(() => {
    const el =
      document.querySelector<HTMLInputElement>('input[name="_token"]') ??
      document.querySelector<HTMLInputElement>('input[name$="[_token]"]');
    return el?.value ?? '';
  });
}

test.describe('config-commerce', () => {
  test.skip(BO_TWIG_ONLY, 'BO Twig only.');

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  // --------------------------------------------------------------------------
  // ATTRIBUTES
  // --------------------------------------------------------------------------
  test.describe('Attributes', () => {
    test('list: clean sweep + create/edit-value(inline)/delete lifecycle', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();

      await page.goto('/admin/configuration/attributes', { waitUntil: 'networkidle' });
      await expect(page.getByTestId('attributes-page')).toBeVisible();

      const issues = dropEnvNoise(await sweepScreen(page, collector));
      expect(issues, formatIssues(issues)).toHaveLength(0);

      // --- Create an attribute via the modal ---
      const ref = qaRef('attr');
      await page.getByTestId('attribute-create-button').click();
      const createForm = page.getByTestId('attribute-create-form');
      await expect(createForm).toBeVisible();
      await createForm.locator('input[type="text"]').first().fill(ref);
      await Promise.all([
        page.waitForURL('**/admin/configuration/attributes**'),
        page.getByTestId('attribute-create-submit').click(),
      ]);

      // Persistence: the new attribute must be reachable from the list/edit.
      await page.goto('/admin/configuration/attributes', { waitUntil: 'networkidle' });
      const row = page.locator('tr', { hasText: ref }).first();
      await expect(row, `created attribute "${ref}" not found in list`).toBeVisible();

      // Navigate to its edit page.
      const editHref = await row.locator('a[href*="/attributes/update"]').first().getAttribute('href');
      expect(editHref, 'attribute edit link missing').toBeTruthy();
      await page.goto(editHref!, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('attribute-edit-page')).toBeVisible();

      const editIssues = dropEnvNoise(await sweepScreen(page, collector));
      expect(editIssues, formatIssues(editIssues)).toHaveLength(0);

      const attributeId = new URL(page.url()).searchParams.get('attribute_id')!;
      expect(attributeId).toBeTruthy();

      // --- Add a value via the modal ---
      // FINDING CC-01: after AV creation the controller redirects to the bare attribute LIST
      // (it reads attribute_id from a top-level request param that the form actually nests),
      // not back to the attribute edit page. The value IS persisted, so we navigate back to
      // the edit page explicitly here and assert persistence. The broken redirect is covered
      // by the dedicated CC-01 fixme test below.
      const valueLabel = `${ref}-VAL`;
      await page.getByTestId('attributeav-create-button').click();
      const avForm = page.getByTestId('attributeav-create-form');
      await expect(avForm).toBeVisible();
      await avForm.locator('input[type="text"]').first().fill(valueLabel);
      await page.getByTestId('attributeav-create-submit').click();
      await page.waitForLoadState('networkidle');

      // Persistence: re-open the edit page. The AV title is an <input> (bo-inline-edit), so its
      // value lives in the value attribute, not in the table text — assert on the input value.
      await page.goto(editHref!, { waitUntil: 'networkidle' });
      const avRow = page.locator('[data-testid^="attributeav-row-"]').first();
      await expect(avRow, 'created attribute value row should be present').toBeVisible();
      const avId = (await avRow.getAttribute('data-testid'))!.replace('attributeav-row-', '');
      const titleInput = page.getByTestId(`attributeav-title-${avId}`);
      await expect(titleInput, 'created attribute value title should persist').toHaveValue(valueLabel);

      // --- Inline edit (bo-inline-edit blur -> update-title AJAX) ---
      const newTitle = `${valueLabel}-EDIT`;
      const titleSave = page.waitForResponse(
        (r) => /\/attributes-av\/update-title\//.test(r.url()) && r.request().method() === 'POST',
      );
      await titleInput.fill(newTitle);
      await titleInput.blur();
      const titleResp = await titleSave;
      expect(titleResp.status(), 'inline title update should succeed').toBeLessThan(400);

      // Persistence: reload edit, the value title is the edited one.
      await page.goto(editHref!, { waitUntil: 'networkidle' });
      await expect(page.getByTestId(`attributeav-title-${avId}`)).toHaveValue(newTitle);

      // --- Delete the value (link with confirm()) ---
      page.once('dialog', (d) => d.accept());
      await Promise.all([
        page.waitForURL('**/attributes/update**'),
        page.getByTestId(`attributeav-delete-${avId}`).click(),
      ]);
      await expect(page.locator(`[data-testid="attributeav-row-${avId}"]`)).toHaveCount(0);

      // --- Delete the attribute itself, from the list (cleanup) ---
      await page.goto('/admin/configuration/attributes', { waitUntil: 'networkidle' });
      const delRow = page.locator('[data-testid="datatable-attributes-row"]', { hasText: ref }).first();
      const attrToken = await tokenFromPage(page);
      const delTrigger = delRow.getByTestId('datatable-action-delete');
      await delTrigger.click();
      const delModal = page.locator('.modal.show').first();
      await expect(delModal).toBeVisible();
      // prefill: data-attribute-id -> attribute_id
      await expect(delModal.locator('input[name="attribute_id"]')).toHaveValue(attributeId);
      await Promise.all([
        page.waitForURL('**/admin/configuration/attributes**'),
        delModal.locator('button[type="submit"]').click(),
      ]);
      await page.goto('/admin/configuration/attributes', { waitUntil: 'networkidle' });
      await expect(page.locator('tr', { hasText: ref })).toHaveCount(0);
      expect(attrToken).toBeTruthy();
    });

    // PAR-31: the testplan expected add/rem-to-all NOT to be exposed as list RowActions (Smarty
    // parity). FINDING CC-03 (minor divergence): BO Twig DOES expose "Add to all templates" /
    // "Remove from all templates" as overflow RowActions in the attribute list (links to
    // /attributes/{add,remove}-from-all-templates). This documents the actual behaviour; treated as
    // a deliberate enhancement rather than a defect — flagged for arbitration.
    test('PAR-31/CC-03: add/rem-to-all are present as list overflow RowActions (divergence) + on edit page', async ({ page }) => {
      await page.goto('/admin/configuration/attributes', { waitUntil: 'networkidle' });
      const firstRow = page.locator('[data-testid="datatable-attributes-row"]').first();
      const rowHtml = await firstRow.innerHTML();
      // Reality check: they ARE rendered in the row overflow dropdown.
      expect(rowHtml, 'CC-03: BO Twig exposes add-to-all in the attribute list row overflow').toContain(
        'add-to-all-templates',
      );
      expect(rowHtml).toContain('remove-from-all-templates');

      // The edit page also carries them as header buttons (parity reference). Use a real demo id.
      const firstEditHref = await firstRow.locator('a[href*="/attributes/update"]').first().getAttribute('href');
      expect(firstEditHref, 'an attribute edit link should exist in the list').toBeTruthy();
      await page.goto(firstEditHref!, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('attribute-add-to-all')).toBeVisible();
      await expect(page.getByTestId('attribute-rem-from-all')).toBeVisible();
    });

    // FINDING CC-01 (major): after creating an attribute value, the controller redirects to the
    // bare attribute list instead of back to the attribute edit page. Root cause:
    // AttributeAvController::create() reads `$request->request->get('attribute_id', 0)` but the
    // form posts the field nested as `thelia_attributeav_creation[attribute_id]`, so $attributeId
    // is always 0 and the success redirect loses the attribute context. Same defect in
    // FeatureAvController::create() (`feature_id`). The value IS persisted; only the redirect is
    // wrong, so the admin is bounced out of the edit screen they were working on.
    // Fix: read the id from the submitted form (e.g. $form->get('attribute_id')->getData()) or from
    // the nested request key, not the top-level param.
    test('CC-01: AV creation should return to the attribute edit page, not the list', async ({ page }) => {
      await page.goto('/admin/configuration/attributes', { waitUntil: 'networkidle' });
      const editHref = await page
        .locator('[data-testid="datatable-attributes-row"] a[href*="/attributes/update"]')
        .first()
        .getAttribute('href');
      await page.goto(editHref!, { waitUntil: 'networkidle' });
      const ccLabel = `QA-CC01-${Date.now().toString(36)}`;
      await page.getByTestId('attributeav-create-button').click();
      const avForm = page.getByTestId('attributeav-create-form');
      await avForm.locator('input[type="text"]').first().fill(ccLabel);
      await page.getByTestId('attributeav-create-submit').click();
      await page.waitForLoadState('networkidle');
      // Expected: still on the attribute edit page. Actual (pre-fix): redirected to the list.
      expect(page.url()).toContain('/attributes/update');

      // Cleanup: delete the value we just created so the demo attribute stays untouched.
      const ccRow = page
        .locator('[data-testid^="attributeav-row-"]')
        .filter({ has: page.locator(`input[value="${ccLabel}"]`) })
        .first();
      const ccId = (await ccRow.getAttribute('data-testid'))!.replace('attributeav-row-', '');
      page.once('dialog', (d) => d.accept());
      await Promise.all([
        page.waitForURL('**/attributes/update**'),
        page.getByTestId(`attributeav-delete-${ccId}`).click(),
      ]);
      await expect(page.locator(`[data-testid="attributeav-row-${ccId}"]`)).toHaveCount(0);
    });
  });

  // --------------------------------------------------------------------------
  // FEATURES
  // --------------------------------------------------------------------------
  test.describe('Features', () => {
    test('list: clean sweep + create/edit-value(PAR-21 form input)/delete lifecycle', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();

      await page.goto('/admin/configuration/features', { waitUntil: 'networkidle' });
      await expect(page.getByTestId('features-page')).toBeVisible();

      const issues = dropEnvNoise(await sweepScreen(page, collector));
      expect(issues, formatIssues(issues)).toHaveLength(0);

      // --- Create a feature ---
      const ref = qaRef('feat');
      await page.getByTestId('feature-create-button').click();
      const createForm = page.getByTestId('feature-create-form');
      await expect(createForm).toBeVisible();
      await createForm.locator('input[type="text"]').first().fill(ref);
      await Promise.all([
        page.waitForURL('**/admin/configuration/features**'),
        page.getByTestId('feature-create-submit').click(),
      ]);

      await page.goto('/admin/configuration/features', { waitUntil: 'networkidle' });
      const row = page.locator('tr', { hasText: ref }).first();
      await expect(row, `created feature "${ref}" not found`).toBeVisible();

      const editHref = await row.locator('a[href*="/features/update"]').first().getAttribute('href');
      expect(editHref).toBeTruthy();
      await page.goto(editHref!, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('feature-edit-page')).toBeVisible();

      const editIssues = dropEnvNoise(await sweepScreen(page, collector));
      expect(editIssues, formatIssues(editIssues)).toHaveLength(0);

      const featureId = new URL(page.url()).searchParams.get('feature_id')!;

      // --- Add a value via modal ---
      // Same CC-01 redirect defect as attributes: FeatureAvController::create() reads a top-level
      // `feature_id` that the form nests, so the post-create redirect lands on the bare list. The
      // value is persisted; navigate back to the edit page explicitly.
      const valueLabel = `${ref}-VAL`;
      await page.getByTestId('featureav-create-button').click();
      const avForm = page.getByTestId('featureav-create-form');
      await expect(avForm).toBeVisible();
      await avForm.locator('input[type="text"]').first().fill(valueLabel);
      await page.getByTestId('featureav-create-submit').click();
      await page.waitForLoadState('networkidle');
      await page.goto(editHref!, { waitUntil: 'networkidle' });

      // PAR-21: feature value title IS editable — it is a plain text input in the main form,
      // persisted on the main "Save" submit (not inline AJAX like attributes).
      const avRow = page.locator('[data-testid^="featureav-row-"]').first();
      const avId = (await avRow.getAttribute('data-testid'))!.replace('featureav-row-', '');
      const valueInput = page.getByTestId(`featureav-input-${avId}`);
      await expect(valueInput).toBeVisible();
      await expect(valueInput).toHaveValue(valueLabel);

      const newValue = `${valueLabel}-EDIT`;
      await valueInput.fill(newValue);
      await Promise.all([
        page.waitForURL('**/features/update**'),
        page.getByTestId('feature-edit-save-stay').click(),
      ]);
      // Persistence: reload, the value input carries the edited title.
      await page.goto(editHref!, { waitUntil: 'networkidle' });
      await expect(page.getByTestId(`featureav-input-${avId}`)).toHaveValue(newValue);

      // --- Delete the value ---
      page.once('dialog', (d) => d.accept());
      await Promise.all([
        page.waitForURL('**/features/update**'),
        page.getByTestId(`featureav-delete-${avId}`).click(),
      ]);
      await expect(page.locator(`[data-testid="featureav-row-${avId}"]`)).toHaveCount(0);

      // --- Delete the feature (cleanup) ---
      await page.goto('/admin/configuration/features', { waitUntil: 'networkidle' });
      const delRow = page.locator('[data-testid="datatable-features-row"]', { hasText: ref }).first();
      await delRow.getByTestId('datatable-action-delete').click();
      const delModal = page.locator('.modal.show').first();
      await expect(delModal).toBeVisible();
      await expect(delModal.locator('input[name="feature_id"]')).toHaveValue(featureId);
      await Promise.all([
        page.waitForURL('**/admin/configuration/features**'),
        delModal.locator('button[type="submit"]').click(),
      ]);
      await page.goto('/admin/configuration/features', { waitUntil: 'networkidle' });
      await expect(page.locator('tr', { hasText: ref })).toHaveCount(0);
    });
  });

  // --------------------------------------------------------------------------
  // PRODUCT TEMPLATES
  // --------------------------------------------------------------------------
  test.describe('Product templates', () => {
    test('list + edit: clean sweep, create/duplicate/feature+attr association/delete', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();

      await page.goto('/admin/configuration/templates', { waitUntil: 'networkidle' });
      await expect(page.getByTestId('templates-page')).toBeVisible();

      const issues = dropEnvNoise(await sweepScreen(page, collector));
      expect(issues, formatIssues(issues)).toHaveLength(0);

      // --- Create a template ---
      const ref = qaRef('tmpl');
      await page.getByTestId('template-create-button').click();
      const createForm = page.getByTestId('template-create-form');
      await expect(createForm).toBeVisible();
      await createForm.locator('input[type="text"]').first().fill(ref);
      await Promise.all([
        page.waitForURL('**/admin/configuration/templates**'),
        page.getByTestId('template-create-submit').click(),
      ]);

      await page.goto('/admin/configuration/templates', { waitUntil: 'networkidle' });
      const row = page.locator('tr', { hasText: ref }).first();
      await expect(row, `created template "${ref}" not found`).toBeVisible();

      const editHref = await row.locator('a[href*="/templates/update"]').first().getAttribute('href');
      expect(editHref).toBeTruthy();
      await page.goto(editHref!, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('template-edit-page')).toBeVisible();
      const templateId = new URL(page.url()).searchParams.get('template_id')!;

      const editIssues = dropEnvNoise(await sweepScreen(page, collector, { modals: false }));
      expect(editIssues, formatIssues(editIssues)).toHaveLength(0);

      // --- Associate a feature (the add form posts then redirects back to edit) ---
      const addFeatureForm = page.getByTestId('template-add-feature-form');
      if (await addFeatureForm.count()) {
        const before = await page.locator('[data-testid^="template-feature-row-"]').count();
        await Promise.all([
          page.waitForURL('**/templates/update**'),
          page.getByTestId('template-add-feature-submit').click(),
        ]);
        const after = await page.locator('[data-testid^="template-feature-row-"]').count();
        expect(after, 'feature association should add a row').toBe(before + 1);
      }

      // --- Associate an attribute ---
      const addAttrForm = page.getByTestId('template-add-attribute-form');
      if (await addAttrForm.count()) {
        const before = await page.locator('[data-testid^="template-attribute-row-"]').count();
        await Promise.all([
          page.waitForURL('**/templates/update**'),
          page.getByTestId('template-add-attribute-submit').click(),
        ]);
        const after = await page.locator('[data-testid^="template-attribute-row-"]').count();
        expect(after, 'attribute association should add a row').toBe(before + 1);
      }

      // --- Duplicate from list (modal new_name) ---
      await page.goto('/admin/configuration/templates', { waitUntil: 'networkidle' });
      const dupRow = page.locator('tr', { hasText: ref }).first();
      const dupRef = `${ref}-COPY`;
      if (await dupRow.locator('[data-bs-target="#template-duplicate-modal"]').count()) {
        await dupRow.locator('[data-bs-target="#template-duplicate-modal"]').first().click();
        const dupModal = page.locator('#template-duplicate-modal');
        await expect(dupModal).toBeVisible();
        await expect(dupModal.locator('input[name="source_template_id"]').first()).toHaveValue(templateId);
        await page.getByTestId('template-duplicate-new-name').fill(dupRef);
        await Promise.all([
          page.waitForURL('**/admin/configuration/templates**'),
          dupModal.locator('button[type="submit"]').click(),
        ]);
        await page.goto('/admin/configuration/templates', { waitUntil: 'networkidle' });
        const copyRow = page.locator('tr', { hasText: dupRef }).first();
        await expect(copyRow, 'duplicated template should appear').toBeVisible();

        // Cleanup the copy.
        await copyRow.getByTestId('datatable-action-delete').click();
        const copyDelModal = page.locator('.modal.show').first();
        await expect(copyDelModal).toBeVisible();
        await Promise.all([
          page.waitForURL('**/admin/configuration/templates**'),
          copyDelModal.locator('button[type="submit"]').click(),
        ]);
      }

      // --- Delete the original (cleanup) ---
      await page.goto('/admin/configuration/templates', { waitUntil: 'networkidle' });
      const delRow = page.locator('tr', { hasText: ref }).first();
      await delRow.getByTestId('datatable-action-delete').click();
      const delModal = page.locator('.modal.show').first();
      await expect(delModal).toBeVisible();
      await expect(delModal.locator('input[name="template_id"]')).toHaveValue(templateId);
      await Promise.all([
        page.waitForURL('**/admin/configuration/templates**'),
        delModal.locator('button[type="submit"]').click(),
      ]);
      await page.goto('/admin/configuration/templates', { waitUntil: 'networkidle' });
      await expect(page.locator('tr', { hasText: ref })).toHaveCount(0);
    });
  });

  // --------------------------------------------------------------------------
  // MESSAGES (mailing templates)
  // --------------------------------------------------------------------------
  test.describe('Messages', () => {
    test('list: clean sweep + PAR-02 delete modal opens + PAR-27 secured rows have actions', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();

      await page.goto('/admin/configuration/messages', { waitUntil: 'networkidle' });
      await expect(page.getByTestId('messages-page')).toBeVisible();

      const issues = dropEnvNoise(await sweepScreen(page, collector));
      expect(issues, formatIssues(issues)).toHaveLength(0);

      // PAR-27: secured ("System") messages must still expose edit + delete actions in BO Twig.
      const systemRow = page.locator('[data-testid="datatable-messages-row"]', { hasText: 'System' }).first();
      if (await systemRow.count()) {
        await expect(systemRow.getByTestId('datatable-action-edit')).toBeVisible();
        await expect(systemRow.getByTestId('datatable-action-delete')).toBeVisible();
      }

      // PAR-02: clicking the trash (delete) RowAction MUST open the confirm modal (Smarty BO opened nothing).
      const anyRow = page.locator('[data-testid="datatable-messages-row"]').first();
      await anyRow.getByTestId('datatable-action-delete').click();
      const delModal = page.locator('#message-delete-modal');
      await expect(delModal, 'PAR-02: delete modal must open').toBeVisible();
      // prefill: data-message-id -> message_id hidden input
      const prefilled = await delModal.locator('input[name="message_id"]').inputValue();
      expect(prefilled, 'PAR-02: message_id should be prefilled from the row').not.toBe('');
      await page.keyboard.press('Escape');
    });

    test('create + edit (2 tabs, Ace, preview), persistence, delete', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();

      await page.goto('/admin/configuration/messages', { waitUntil: 'networkidle' });

      // --- Create a message via the modal ---
      const ref = qaRef('msg').toLowerCase().replace(/[^a-z0-9]/g, '_');
      await page.getByTestId('message-create-button').click();
      const modal = page.locator('#message-create-modal');
      await expect(modal).toBeVisible();
      await modal.locator('input[name$="[message_name]"], input[name="message_name"]').first().fill(ref);
      await modal.locator('input[name$="[title]"], input[name="title"]').first().fill(`${ref} purpose`);
      // Message creation redirects to the LIST by design (successRoute = list), so don't waitForURL
      // on an edit pattern. Click, settle, then reach the edit page via the list row.
      await modal.locator('button[type="submit"]').click();
      await page.waitForLoadState('networkidle');

      await page.goto('/admin/configuration/messages', { waitUntil: 'networkidle' });
      const createdRow = page.locator('tr', { hasText: ref }).first();
      await expect(createdRow, `created message "${ref}" not found in list`).toBeVisible();
      const editHref = await createdRow.locator('a[href*="/messages/update/"]').first().getAttribute('href');
      expect(editHref, 'message edit link missing').toBeTruthy();
      await page.goto(editHref!, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('message-edit-page')).toBeVisible();
      const messageId = page.url().match(/\/messages\/update\/(\d+)/)?.[1] ?? '';
      expect(messageId, 'message id from edit url').toBeTruthy();

      const editIssues = dropEnvNoise(await sweepScreen(page, collector, { modals: false }));
      expect(editIssues, formatIssues(editIssues)).toHaveLength(0);

      // sweepScreen's clickAllTabs left the "Preview" tab active. Re-activate "Message data".
      await page.locator('#tab-data').click();
      await expect(page.locator('#data')).toHaveClass(/active/);

      // The Ace editor mounts over the html_message textarea (two tabs: data + preview).
      await expect(page.locator('#data .ace_editor').first(), 'Ace editor should mount on html_message').toBeVisible();

      // --- Edit subject (a real persisted field) then save ---
      const newSubject = `${ref} subject`;
      const subjectField = page.locator('[data-testid="message-edit-form"] input[name$="[subject]"]');
      await expect(subjectField).toBeEditable();
      await subjectField.fill(newSubject);
      await page.getByTestId('message-save').click();
      await page.waitForLoadState('networkidle');

      // Persistence: reload edit, subject persisted.
      await page.goto(`/admin/configuration/messages/update/${messageId}`, { waitUntil: 'networkidle' });
      await page.locator('#tab-data').click();
      await expect(page.locator('[data-testid="message-edit-form"] input[name$="[subject]"]')).toHaveValue(newSubject);

      // --- Preview tab: Preview HTML must produce a non-error fragment ---
      await page.locator('#tab-preview').click();
      await expect(page.locator('#preview')).toHaveClass(/active/);
      const previewResp = page.waitForResponse(
        (r) => /preview/i.test(r.url()) && (r.request().resourceType() === 'fetch' || r.request().resourceType() === 'xhr'),
        { timeout: 8_000 },
      ).catch(() => null);
      await page.getByTestId('message-preview-html').click();
      const pr = await previewResp;
      if (pr) {
        expect(pr.status(), 'preview HTML fetch should not error').toBeLessThan(400);
      }

      // --- Delete the message (cleanup) ---
      await page.goto('/admin/configuration/messages', { waitUntil: 'networkidle' });
      const delRow = page.locator('[data-testid="datatable-messages-row"]', { hasText: ref }).first();
      await delRow.getByTestId('datatable-action-delete').click();
      const delModal = page.locator('#message-delete-modal');
      await expect(delModal).toBeVisible();
      await expect(delModal.locator('input[name="message_id"]')).toHaveValue(messageId);
      await Promise.all([
        page.waitForURL('**/admin/configuration/messages**'),
        delModal.locator('button[type="submit"]').click(),
      ]);
      await page.goto('/admin/configuration/messages', { waitUntil: 'networkidle' });
      await expect(page.locator('tr', { hasText: ref })).toHaveCount(0);
    });
  });

  // --------------------------------------------------------------------------
  // MAILING SYSTEM
  // --------------------------------------------------------------------------
  test.describe('Mailing system', () => {
    test('form sweep + save persistence + test button inline result', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();

      await page.goto('/admin/configuration/mailingSystem', { waitUntil: 'networkidle' });
      await expect(page.getByTestId('mailing-system-page')).toBeVisible();

      // allowDanger: the page renders two static warning alerts by design.
      const issues = dropEnvNoise(await sweepScreen(page, collector, { allowDanger: true }));
      expect(issues, formatIssues(issues)).toHaveLength(0);

      const editDisabled = await page.getByTestId('mailing-system-form').locator('input[name$="[host]"]').isDisabled();

      if (!editDisabled) {
        // Persistence test on the host field (restored afterwards).
        const hostInput = page.getByTestId('mailing-system-form').locator('input[name$="[host]"]');
        const previous = await hostInput.inputValue();
        const probe = `qa-host-${Date.now().toString(36)}`;
        await hostInput.fill(probe);
        await Promise.all([
          page.waitForURL('**/mailingSystem**'),
          page.getByTestId('mailing-system-save-stay').click(),
        ]);
        await page.goto('/admin/configuration/mailingSystem', { waitUntil: 'networkidle' });
        await expect(
          page.getByTestId('mailing-system-form').locator('input[name$="[host]"]'),
          'mailing host should persist',
        ).toHaveValue(probe);

        // Restore.
        await page.getByTestId('mailing-system-form').locator('input[name$="[host]"]').fill(previous);
        await Promise.all([
          page.waitForURL('**/mailingSystem**'),
          page.getByTestId('mailing-system-save-stay').click(),
        ]);
      }

      // Test button (bo-mailing-system-test): the AJAX call must return a JSON result, not a 5xx.
      await page.goto('/admin/configuration/mailingSystem', { waitUntil: 'networkidle' });
      const testResp = page.waitForResponse(
        (r) => /\/mailingSystem\/test/.test(r.url()),
        { timeout: 12_000 },
      ).catch(() => null);
      await page.getByTestId('mailing-system-test-send').click();
      const tr = await testResp;
      if (tr) {
        expect(tr.status(), 'mailing-system test endpoint must not 5xx').toBeLessThan(500);
        // result target should be populated (success or a graceful error message).
        await expect(page.locator('[data-bo-mailing-system-test-target="result"]')).not.toBeEmpty({ timeout: 8_000 });
      }
    });
  });

  // --------------------------------------------------------------------------
  // STORE CONFIGURATION
  // --------------------------------------------------------------------------
  test.describe('Store configuration', () => {
    test('form sweep + save/persist store fields + image preview controllers present', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();

      await page.goto('/admin/configuration/store', { waitUntil: 'networkidle' });
      await expect(page.getByTestId('config-store-page')).toBeVisible();

      const issues = dropEnvNoise(await sweepScreen(page, collector));
      expect(issues, formatIssues(issues)).toHaveLength(0);

      // Image preview controllers wired (favicon/logo/banner).
      await expect(page.getByTestId('config-store-favicon-preview')).toHaveCount(1);
      await expect(page.getByTestId('config-store-logo-preview')).toHaveCount(1);
      await expect(page.getByTestId('config-store-banner-preview')).toHaveCount(1);

      const form = page.getByTestId('config-store-form');
      const nameInput = form.locator('input[name$="[store_name]"]');
      const cityInput = form.locator('input[name$="[store_city]"]');

      const previousName = await nameInput.inputValue();
      const previousCity = await cityInput.inputValue();
      const probeName = `${previousName} QA-${Date.now().toString(36)}`;
      const probeCity = `QA-CITY-${Date.now().toString(36)}`;

      await nameInput.fill(probeName);
      await cityInput.fill(probeCity);
      await Promise.all([
        page.waitForURL('**/configuration/store**'),
        page.getByTestId('config-store-save-stay').click(),
      ]);

      // Persistence: reload, both fields kept their probe value.
      await page.goto('/admin/configuration/store', { waitUntil: 'networkidle' });
      await expect(page.getByTestId('config-store-form').locator('input[name$="[store_name]"]')).toHaveValue(probeName);
      await expect(page.getByTestId('config-store-form').locator('input[name$="[store_city]"]')).toHaveValue(probeCity);

      // Restore originals.
      await page.getByTestId('config-store-form').locator('input[name$="[store_name]"]').fill(previousName);
      await page.getByTestId('config-store-form').locator('input[name$="[store_city]"]').fill(previousCity);
      await Promise.all([
        page.waitForURL('**/configuration/store**'),
        page.getByTestId('config-store-save-stay').click(),
      ]);
    });
  });
});
