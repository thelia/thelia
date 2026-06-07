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
 * QA campaign — domain "content-folders" (BO default-twig).
 *
 * Coverage:
 *  - /admin/folders
 *      root list — sweep DOM/modals/leaks, create folder via modal + DB persistence,
 *      toggle online round-trip, bo-sortable reorder via the update-position endpoint
 *      (UI drag is flaky), delete (folder-delete-modal orphan message).
 *  - /admin/folders?folder_id=83 (inside a folder)
 *      contents section (BoDataTable), create content via modal + persistence, content
 *      toggle online round-trip, content reorder endpoint, content delete.
 *      The testplan hypothesised the contents list should be ABSENT inside a folder;
 *      the Smarty reference (templates/backOffice/default/folders.html, "Contents in %fold"
 *      + "Add a new content") PROVES the list and the create/toggle/reorder controls are
 *      intended here. The BO Twig matches that behaviour, so we test the present (correct)
 *      contents UI. Documented as CF-NOTE-CONTENTS (note, not a bug).
 *  - /admin/folders/update/{id} — 6 tabs: General, Children, Images, Documents, SEO, Modules.
 *      General save + persistence, SEO save + persistence, tab sweep.
 *  - /admin/content/update/{id} — 6 tabs: General, Images, Documents, SEO, Associations, Modules.
 *      General save (title + visible + default_folder) + persistence, SEO save + persistence,
 *      Associations: add then remove an additional folder + persistence.
 *
 * Persistence is verified by re-GET + asserting the rendered value, never a bare 200.
 * Every created entity is qaRef-prefixed and deleted at the end of its describe block.
 *
 * Anchors (demo data, verified 2026-06-07):
 *  - root folders: 83 (Information, 5 contents, 0 subfolders), 84 (Blog), 85 (Advice).
 *  - folder 83 default contents: 251 (About us) .. 255 (Warranty).
 *  - no folder has subfolders, so the subfolder list inside folder 83 is empty by design.
 */

const ROOT_FOLDER_WITH_CONTENT = 83; // "Information", 5 default contents
const CONTENT_ID = 251; // "About us", default folder 83
const SECOND_ROOT_FOLDER = 84; // "Blog" — used as the additional folder to attach

/**
 * Environmental noise that is NOT a content-folders defect:
 *  - Missing flag SVG (`svgFlags/<code>.svg` 404) emitted by BoLanguageSwitcher for
 *    custom/QA languages with no bundled flag asset (shared across the whole BO).
 *  Never drops 5xx or JS errors.
 */
function dropNoise(issues: PageIssue[]): PageIssue[] {
  return issues.filter((i) => {
    if (i.kind === 'pageerror') return true;
    if (/5\d\d/.test(i.detail)) return true;
    if (i.kind === 'console' && /Failed to load resource.*404/i.test(i.detail)) return false;
    if (/svgFlags\/[^/]+\.svg/i.test(i.detail)) return false;
    return true;
  });
}

async function expectClean(
  page: Page,
  collector: IssueCollector,
  opts: { tabs?: boolean; modals?: boolean; allowDanger?: boolean } = {},
): Promise<void> {
  const issues = dropNoise(await sweepScreen(page, collector, opts));
  expect(issues, formatIssues(issues)).toHaveLength(0);
}

function expectNoIssues(issues: PageIssue[]): void {
  const clean = dropNoise(issues);
  expect(clean, formatIssues(clean)).toHaveLength(0);
}

/**
 * Remove a content<->additional-folder link via a well-formed POST. The Associations tab
 * has no _token hidden input — the only CSRF token lives in the (malformed, see CF-01)
 * delete link href, so we extract it from there and send a correct request.
 */
async function removeAdditionalFolder(page: Page, contentId: number, folderId: number): Promise<void> {
  const link = page.getByTestId(`content-additional-folder-delete-${folderId}`);
  if (await link.count() === 0) return;
  const href = (await link.getAttribute('href')) ?? '';
  const token = href.split('_token=')[1] ?? '';
  await page.request.post('/admin/content/additional-folder/delete', {
    form: { content_id: String(contentId), additional_folder_id: String(folderId), _token: token },
  });
}

/** Read the CSRF token Thelia embeds in BO forms / sortable controllers. */
async function readToken(page: Page): Promise<string> {
  return page.evaluate(() => {
    const input = document.querySelector<HTMLInputElement>('input[name="_token"]');
    if (input?.value) return input.value;
    const sortable = document.querySelector<HTMLElement>('[data-bo-sortable-token-value]');
    if (sortable) return sortable.getAttribute('data-bo-sortable-token-value') ?? '';
    return '';
  });
}

test.describe('content-folders', () => {
  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  // ===========================================================================
  // Screen: Folders — root list
  // ===========================================================================
  test.describe('Folders — root list', () => {
    test('root list loads clean, sweep DOM/modals, no leaked fields', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto('/admin/folders', { waitUntil: 'networkidle' });
      await expect(page.getByTestId('folders-page')).toBeVisible();
      await expect(page.getByTestId('folder-create-button')).toBeVisible();
      // Root list (parent_id=0) must NOT expose a contents section.
      await expect(page.getByTestId('folder-contents-section')).toHaveCount(0);
      const tbody = page.locator('tbody[data-controller="bo-sortable"]').first();
      await expect(tbody).toHaveAttribute('data-bo-sortable-param-name-value', 'folder_id');
      await expectClean(page, collector, { tabs: false, modals: true });
    });

    test('demo dataset has folder rows with data-row-id (bo-sortable wired)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto('/admin/folders', { waitUntil: 'networkidle' });
      const rows = page.getByTestId('datatable-folders-row');
      expect(await rows.count()).toBeGreaterThan(0);
      const firstId = await rows.first().getAttribute('data-row-id');
      expect(Number(firstId)).toBeGreaterThan(0);
      expectNoIssues(collector.drain());
    });

    test('toggle online persists (column_toggle round-trip)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto('/admin/folders', { waitUntil: 'networkidle' });
      const firstRow = page.getByTestId('datatable-folders-row').first();
      const rowId = await firstRow.getAttribute('data-row-id');
      const toggle = firstRow.locator('a[data-testid="datatable-visible-toggle"]').first();
      await expect(toggle).toBeVisible();
      const before = await toggle.getAttribute('data-state');
      await Promise.all([page.waitForLoadState('networkidle'), toggle.click()]);
      await page.goto('/admin/folders', { waitUntil: 'networkidle' });
      const after = page
        .locator(`[data-testid="datatable-folders-row"][data-row-id="${rowId}"] a[data-testid="datatable-visible-toggle"]`)
        .first();
      expect(await after.getAttribute('data-state'), 'folder toggle online should flip data-state').not.toBe(before);
      // Restore demo data.
      await Promise.all([page.waitForLoadState('networkidle'), after.click()]);
      expectNoIssues(collector.drain());
    });

    test('create folder via modal persists with visible flag, then delete', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      const title = qaRef('folder');

      await page.goto('/admin/folders', { waitUntil: 'networkidle' });
      await page.getByTestId('folder-create-button').click();
      const form = page.getByTestId('folder-create-form');
      await expect(form).toBeVisible();
      await form.locator('input[name$="[title]"]').fill(title);
      const visible = form.locator('input[type="checkbox"][name$="[visible]"]');
      if (await visible.count() > 0 && !(await visible.isChecked())) {
        await visible.check();
      }
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('folder-create-submit').click()]);

      // PERSISTENCE: created folder appears at root; resolve its id from the edit link.
      await page.goto('/admin/folders', { waitUntil: 'networkidle' });
      const row = page.locator('[data-testid="datatable-folders-row"]', { hasText: title }).first();
      await expect(row, 'created folder must appear in the root list').toBeVisible();
      const href = await row.locator('a[href*="folders/update"]').first().getAttribute('href');
      const folderId = Number((href ?? '').split('/').pop());
      expect(folderId).toBeGreaterThan(0);

      // Re-GET edit page: title persisted, visible checkbox checked.
      await page.goto(`/admin/folders/update/${folderId}`, { waitUntil: 'networkidle' });
      await expect(page.locator('[data-testid="folder-edit-form"] input[name$="[title]"]')).toHaveValue(title);
      await expect(page.locator('[data-testid="folder-edit-form"] input[type="checkbox"][name$="[visible]"]')).toBeChecked();

      // DELETE via list modal — assert the orphan warning message (matches template copy).
      await page.goto('/admin/folders', { waitUntil: 'networkidle' });
      const delTrigger = page.locator(`[data-bs-target="#folder-delete-modal"][data-folder-id="${folderId}"]`).first();
      await delTrigger.click();
      const modal = page.locator('#folder-delete-modal.show');
      await modal.waitFor({ state: 'visible' });
      const msg = ((await modal.locator('.modal-body').textContent()) ?? '').toLowerCase();
      expect(msg, 'folder delete message must warn about subfolders/orphaning').toMatch(/subfolder|orphan/);
      await Promise.all([page.waitForLoadState('networkidle'), modal.locator('button[type="submit"]').click()]);

      // PERSISTENCE: gone from list.
      await page.goto('/admin/folders', { waitUntil: 'networkidle' });
      await expect(page.locator('[data-testid="datatable-folders-row"]', { hasText: title })).toHaveCount(0);
      expectNoIssues(collector.drain());
    });

    test('reorder via update-position endpoint persists (bo-sortable backend)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto('/admin/folders', { waitUntil: 'networkidle' });
      const rows = page.getByTestId('datatable-folders-row');
      if (await rows.count() < 2) {
        return; // not enough rows
      }
      const token = await readToken(page);
      expect(token, 'sortable token present').not.toBe('');
      const firstRow = rows.first();
      const folderId = await firstRow.getAttribute('data-row-id');
      const resp = await page.request.post('/admin/folders/update-position', {
        form: { folder_id: String(folderId), position: '2', _token: token },
      });
      expect(resp.status(), 'folder update-position status').toBeLessThan(400);
      await page.goto('/admin/folders', { waitUntil: 'networkidle' });
      const movedPos = page
        .locator(`[data-testid="datatable-folders-row"][data-row-id="${folderId}"] [data-testid="datatable-folders-cell-position"]`);
      await expect(movedPos).toHaveText(/2/);
      // Restore to position 1.
      const token2 = await readToken(page);
      const restore = await page.request.post('/admin/folders/update-position', {
        form: { folder_id: String(folderId), position: '1', _token: token2 },
      });
      expect(restore.status()).toBeLessThan(400);
      expectNoIssues(collector.drain());
    });
  });

  // ===========================================================================
  // Screen: Folders — inside a folder (contents section)
  // ===========================================================================
  test.describe('Folders — inside a folder (contents)', () => {
    // CF-NOTE-CONTENTS: the testplan expected NO contents list inside a folder.
    // The Smarty reference exposes "Contents in %fold" + "Add a new content", so the
    // BO Twig contents section IS correct. We test the present behaviour.
    test('inside folder shows contents section + edit-this-folder button, sweep clean', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/folders?folder_id=${ROOT_FOLDER_WITH_CONTENT}`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('folders-page')).toBeVisible();
      await expect(page.getByTestId('folder-contents-section')).toBeVisible();
      await expect(page.getByTestId('content-create-button')).toBeVisible();
      // parent_id > 0 -> the "Edit this folder" button appears.
      await expect(page.getByTestId('folder-edit-parent')).toBeVisible();
      const contentRows = page.getByTestId('datatable-folder-contents-row');
      expect(await contentRows.count(), 'folder 83 must list its contents').toBeGreaterThan(0);
      await expectClean(page, collector, { tabs: false, modals: true });
    });

    test('content toggle online round-trip inside a folder', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/folders?folder_id=${ROOT_FOLDER_WITH_CONTENT}`, { waitUntil: 'networkidle' });
      const firstRow = page.getByTestId('datatable-folder-contents-row').first();
      const rowId = await firstRow.getAttribute('data-row-id');
      const toggle = firstRow.locator('a[data-testid="datatable-visible-toggle"]').first();
      await expect(toggle).toBeVisible();
      const before = await toggle.getAttribute('data-state');
      await Promise.all([page.waitForLoadState('networkidle'), toggle.click()]);
      await page.goto(`/admin/folders?folder_id=${ROOT_FOLDER_WITH_CONTENT}`, { waitUntil: 'networkidle' });
      const after = page
        .locator(`[data-testid="datatable-folder-contents-row"][data-row-id="${rowId}"] a[data-testid="datatable-visible-toggle"]`)
        .first();
      expect(await after.getAttribute('data-state'), 'content toggle online should flip').not.toBe(before);
      await Promise.all([page.waitForLoadState('networkidle'), after.click()]);
      expectNoIssues(collector.drain());
    });

    // CF-02 (FIXED): content reorder inside a folder. Position is stored per-folder in the
    // content_folder pivot, so the move needs the folder id as the UpdatePositionEvent
    // referrer. The folder/list.html.twig sortable now embeds folder_id in the endpoint URL
    // and ContentController::updatePosition() passes it as the event referrer; the listing
    // reads the pivot position virtual column so the new order is visible.
    test('CF-02: content reorder via update-position endpoint persists', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/folders?folder_id=${ROOT_FOLDER_WITH_CONTENT}`, { waitUntil: 'networkidle' });
      const rows = page.getByTestId('datatable-folder-contents-row');
      if (await rows.count() < 2) {
        return;
      }
      const token = await page.evaluate(() => {
        const el = document.querySelector<HTMLElement>('[data-bo-sortable-param-name-value="content_id"]');
        return el?.getAttribute('data-bo-sortable-token-value') ?? '';
      });
      expect(token, 'content sortable token present').not.toBe('');
      const contentId = await rows.first().getAttribute('data-row-id');
      // The current endpoint ignores the move because the folder referrer is missing.
      const resp = await page.request.post('/admin/content/update-position', {
        form: { content_id: String(contentId), folder_id: String(ROOT_FOLDER_WITH_CONTENT), position: '2', _token: token },
      });
      expect(resp.status(), 'content update-position status').toBeLessThan(400);
      await page.goto(`/admin/folders?folder_id=${ROOT_FOLDER_WITH_CONTENT}`, { waitUntil: 'networkidle' });
      const movedPos = page
        .locator(`[data-testid="datatable-folder-contents-row"][data-row-id="${contentId}"] [data-testid="datatable-folder-contents-cell-position"]`);
      await expect(movedPos).toHaveText(/2/);
      expectNoIssues(collector.drain());
    });

    test('create content via modal inside a folder persists + default_folder, then delete', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      const title = qaRef('content');

      await page.goto(`/admin/folders?folder_id=${ROOT_FOLDER_WITH_CONTENT}`, { waitUntil: 'networkidle' });
      await page.getByTestId('content-create-button').click();
      const form = page.getByTestId('content-create-form');
      await expect(form).toBeVisible();
      await form.locator('input[name$="[title]"]').fill(title);
      const visible = form.locator('input[type="checkbox"][name$="[visible]"]');
      if (await visible.count() > 0 && !(await visible.isChecked())) {
        await visible.check();
      }
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('content-create-submit').click()]);

      // PERSISTENCE: the new content appears in the folder's contents list.
      await page.goto(`/admin/folders?folder_id=${ROOT_FOLDER_WITH_CONTENT}`, { waitUntil: 'networkidle' });
      const row = page.locator('[data-testid="datatable-folder-contents-row"]', { hasText: title }).first();
      await expect(row, 'created content must appear in the folder contents list').toBeVisible();
      const href = await row.locator('a[href*="content/update"]').first().getAttribute('href');
      const contentId = Number((href ?? '').split('/').pop());
      expect(contentId).toBeGreaterThan(0);

      // Re-GET the content edit page: title persisted, default folder is 83.
      await page.goto(`/admin/content/update/${contentId}`, { waitUntil: 'networkidle' });
      await expect(page.locator('[data-testid="content-edit-form"] input[name$="[title]"]')).toHaveValue(title);
      await expect(page.getByTestId('content-default-folder')).toHaveValue(String(ROOT_FOLDER_WITH_CONTENT));

      // DELETE via the folder contents list modal.
      await page.goto(`/admin/folders?folder_id=${ROOT_FOLDER_WITH_CONTENT}`, { waitUntil: 'networkidle' });
      const delTrigger = page.locator(`[data-bs-target="#content-delete-modal"][data-content-id="${contentId}"]`).first();
      await delTrigger.click();
      const modal = page.locator('#content-delete-modal.show');
      await modal.waitFor({ state: 'visible' });
      await Promise.all([page.waitForLoadState('networkidle'), modal.locator('button[type="submit"]').click()]);

      // PERSISTENCE: gone from the contents list.
      await page.goto(`/admin/folders?folder_id=${ROOT_FOLDER_WITH_CONTENT}`, { waitUntil: 'networkidle' });
      await expect(page.locator('[data-testid="datatable-folder-contents-row"]', { hasText: title })).toHaveCount(0);
      expectNoIssues(collector.drain());
    });
  });

  // ===========================================================================
  // Screen: Folder — edit (6 tabs)
  // ===========================================================================
  test.describe('Folder — edit (6 tabs)', () => {
    test('edit page loads, all tabs render clean, no console/network/leak', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/folders/update/${ROOT_FOLDER_WITH_CONTENT}`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('folder-edit-page')).toBeVisible();
      await expect(page.getByTestId('folder-tab-general')).toBeVisible();
      await expect(page.getByTestId('folder-tab-children')).toBeVisible();
      await expect(page.getByTestId('folder-tab-images')).toBeVisible();
      await expect(page.getByTestId('folder-tab-documents')).toBeVisible();
      await expect(page.getByTestId('folder-tab-seo')).toBeVisible();
      await expect(page.getByTestId('folder-tab-modules')).toBeVisible();
      await expectClean(page, collector, { tabs: true, modals: true });
    });

    test('General tab: edit title + description + save, re-GET asserts persistence (then restore)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/folders/update/${ROOT_FOLDER_WITH_CONTENT}`, { waitUntil: 'networkidle' });
      const titleSel = '[data-testid="folder-edit-form"] input[name$="[title]"]';
      const original = await page.locator(titleSel).inputValue();
      const marker = original + ' QA' + String(Date.now()).slice(-5);
      await page.locator(titleSel).fill(marker);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('folder-edit-submit').click()]);

      await page.goto(`/admin/folders/update/${ROOT_FOLDER_WITH_CONTENT}`, { waitUntil: 'networkidle' });
      await expect(page.locator(titleSel)).toHaveValue(marker);
      // Restore.
      await page.locator(titleSel).fill(original);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('folder-edit-submit').click()]);
      const issues = [...await scanDom(page), ...await findLeakedFields(page), ...collector.drain()];
      expectNoIssues(issues);
    });

    test('SEO tab: edit meta_title + save, re-GET asserts persistence (then restore)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/folders/update/${ROOT_FOLDER_WITH_CONTENT}?current_tab=seo`, { waitUntil: 'networkidle' });
      await page.getByTestId('folder-tab-seo').click();
      const metaSel = '[data-testid="folder-seo-form"] input[name$="[meta_title]"]';
      const metaInput = page.locator(metaSel).first();
      await metaInput.waitFor({ state: 'visible' });
      const original = await metaInput.inputValue();
      const marker = 'QA-FSEO ' + Date.now();
      await metaInput.fill(marker);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('folder-seo-submit').click()]);

      await page.goto(`/admin/folders/update/${ROOT_FOLDER_WITH_CONTENT}?current_tab=seo`, { waitUntil: 'networkidle' });
      await expect(page.locator(metaSel).first()).toHaveValue(marker);
      await page.locator(metaSel).first().fill(original);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('folder-seo-submit').click()]);
      const issues = [...await scanDom(page), ...collector.drain()];
      expectNoIssues(issues);
    });
  });

  // ===========================================================================
  // Screen: Content — edit (6 tabs)
  // ===========================================================================
  test.describe('Content — edit (6 tabs)', () => {
    test('edit page loads, all tabs render clean, no console/network/leak', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/content/update/${CONTENT_ID}`, { waitUntil: 'networkidle' });
      await expect(page.getByTestId('content-edit-page')).toBeVisible();
      await expect(page.getByTestId('content-tab-general')).toBeVisible();
      await expect(page.getByTestId('content-tab-images')).toBeVisible();
      await expect(page.getByTestId('content-tab-documents')).toBeVisible();
      await expect(page.getByTestId('content-tab-seo')).toBeVisible();
      await expect(page.getByTestId('content-tab-associations')).toBeVisible();
      await expect(page.getByTestId('content-tab-modules')).toBeVisible();
      await expectClean(page, collector, { tabs: true, modals: true });
    });

    test('General tab: edit title + save, re-GET asserts persistence (then restore)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/content/update/${CONTENT_ID}`, { waitUntil: 'networkidle' });
      const titleSel = '[data-testid="content-edit-form"] input[name$="[title]"]';
      const original = await page.locator(titleSel).inputValue();
      const marker = original + ' QA' + String(Date.now()).slice(-5);
      await page.locator(titleSel).fill(marker);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('content-edit-submit').click()]);

      await page.goto(`/admin/content/update/${CONTENT_ID}`, { waitUntil: 'networkidle' });
      await expect(page.locator(titleSel)).toHaveValue(marker);
      // default_folder must remain 83 (not silently reset).
      await expect(page.getByTestId('content-default-folder')).toHaveValue(String(ROOT_FOLDER_WITH_CONTENT));
      // Restore.
      await page.locator(titleSel).fill(original);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('content-edit-submit').click()]);
      const issues = [...await scanDom(page), ...await findLeakedFields(page), ...collector.drain()];
      expectNoIssues(issues);
    });

    test('SEO tab: edit meta_title + save, re-GET asserts persistence (then restore)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/content/update/${CONTENT_ID}?current_tab=seo`, { waitUntil: 'networkidle' });
      await page.getByTestId('content-tab-seo').click();
      const metaSel = '[data-testid="content-seo-form"] input[name$="[meta_title]"]';
      const metaInput = page.locator(metaSel).first();
      await metaInput.waitFor({ state: 'visible' });
      const original = await metaInput.inputValue();
      const marker = 'QA-CSEO ' + Date.now();
      await metaInput.fill(marker);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('content-seo-submit').click()]);

      await page.goto(`/admin/content/update/${CONTENT_ID}?current_tab=seo`, { waitUntil: 'networkidle' });
      await expect(page.locator(metaSel).first()).toHaveValue(marker);
      await page.locator(metaSel).first().fill(original);
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('content-seo-submit').click()]);
      const issues = [...await scanDom(page), ...collector.drain()];
      expectNoIssues(issues);
    });

    // The "add" half works: associate folder 84, verify it persists, then clean up directly
    // via a well-formed POST request (the UI remove link is broken — see CF-01 below).
    test('Associations tab: add an additional folder persists (CF-01: UI remove link broken)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto(`/admin/content/update/${CONTENT_ID}?current_tab=associations`, { waitUntil: 'networkidle' });
      await page.getByTestId('content-tab-associations').click();
      await page.waitForTimeout(300);

      const select = page.getByTestId('content-additional-folder-select');
      // Defensive: if a leftover link exists, drop it through a well-formed POST first.
      const stray = page.getByTestId(`content-additional-folder-row-${SECOND_ROOT_FOLDER}`);
      if (await stray.count() > 0) {
        await removeAdditionalFolder(page, CONTENT_ID, SECOND_ROOT_FOLDER);
        await page.goto(`/admin/content/update/${CONTENT_ID}?current_tab=associations`, { waitUntil: 'networkidle' });
        await page.getByTestId('content-tab-associations').click();
      }

      const hasOption = await select.locator(`option[value="${SECOND_ROOT_FOLDER}"]`).count();
      if (hasOption === 0) {
        return; // all folders already linked
      }
      await select.selectOption(String(SECOND_ROOT_FOLDER));
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('content-additional-folder-add').click()]);

      // PERSISTENCE: the additional folder row appears after re-GET.
      await page.goto(`/admin/content/update/${CONTENT_ID}?current_tab=associations`, { waitUntil: 'networkidle' });
      await page.getByTestId('content-tab-associations').click();
      await expect(page.getByTestId(`content-additional-folder-row-${SECOND_ROOT_FOLDER}`)).toBeVisible();

      // CLEANUP via a well-formed POST (the UI link is the buggy path, exercised in CF-01).
      await removeAdditionalFolder(page, CONTENT_ID, SECOND_ROOT_FOLDER);
      await page.goto(`/admin/content/update/${CONTENT_ID}?current_tab=associations`, { waitUntil: 'networkidle' });
      await page.getByTestId('content-tab-associations').click();
      await expect(page.getByTestId(`content-additional-folder-row-${SECOND_ROOT_FOLDER}`)).toHaveCount(0);

      const issues = [...await scanDom(page), ...collector.drain()];
      expectNoIssues(issues);
    });

    // CF-01 (FIXED): the "remove additional folder" link in the Associations tab. The href
    // used to append `?_token=...` to a router URL that already carried a query string,
    // producing a double `?` so additional_folder_id swallowed the token and the CSRF check
    // failed silently. The separator is now `&`, so the token is a real query param and the
    // removal goes through.
    test('CF-01: UI remove additional folder link actually removes the association', async ({ page }) => {
      await page.goto(`/admin/content/update/${CONTENT_ID}?current_tab=associations`, { waitUntil: 'networkidle' });
      await page.getByTestId('content-tab-associations').click();
      await page.waitForTimeout(300);
      const select = page.getByTestId('content-additional-folder-select');
      if (await select.locator(`option[value="${SECOND_ROOT_FOLDER}"]`).count() > 0) {
        await select.selectOption(String(SECOND_ROOT_FOLDER));
        await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId('content-additional-folder-add').click()]);
        await page.goto(`/admin/content/update/${CONTENT_ID}?current_tab=associations`, { waitUntil: 'networkidle' });
        await page.getByTestId('content-tab-associations').click();
      }
      // Click the (malformed) UI delete link.
      await Promise.all([page.waitForLoadState('networkidle'), page.getByTestId(`content-additional-folder-delete-${SECOND_ROOT_FOLDER}`).click()]);
      await page.goto(`/admin/content/update/${CONTENT_ID}?current_tab=associations`, { waitUntil: 'networkidle' });
      await page.getByTestId('content-tab-associations').click();
      // Expect the row to be gone — currently it is NOT (the link is a no-op).
      await expect(page.getByTestId(`content-additional-folder-row-${SECOND_ROOT_FOLDER}`)).toHaveCount(0);
    });
  });
});
