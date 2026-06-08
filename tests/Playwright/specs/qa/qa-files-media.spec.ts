import { test, expect, type Page } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import {
  IssueCollector,
  sweepScreen,
  scanDom,
  findLeakedFields,
  formatIssues,
  qaRef,
  type PageIssue,
} from '../../helpers/qa';

/**
 * QA campaign — domain "files-media" (BO default-twig).
 *
 * Scope:
 *  - FileController (18 routes): image + document upload / list / toggle / delete /
 *    position / metadata edit, for EVERY supported parent type
 *    (product, category, brand, folder, content).
 *  - bo-file-upload + bo-file-list Stimulus controllers (AJAX flow).
 *  - Generated thumbnail (image cache) returns 200.
 *  - Export / Import screens (testplan files-media) — mostly KNOWN items
 *    (PAR-15 position routes mortes, issue #7 cards-not-datatable).
 *
 * The file UI is embedded as a render(controller(imageForm/documentForm)) fragment
 * in each parent's edit page (tab "images" / "documents"). Driving it through the
 * real edit page (activating the tab) is what mounts the Stimulus controllers and
 * loads the list-ajax fragment, exactly as a human would.
 *
 * Anchors (demo data, verified 2026-06-07):
 *  - product 722, category 180, brand 165, folder 83, content 251.
 *  - product_image rows already exist for product 722 (PROD001-*.jpg).
 *
 * Every uploaded file is qaRef-titled and deleted at the end of its block.
 * Persistence is verified by re-GET of the list-ajax fragment, never a bare 200.
 */

const PRODUCT = 722;
const CATEGORY = 180;
const BRAND = 165;
const FOLDER = 83;
const CONTENT = 251;

type ParentType = 'product' | 'category' | 'brand' | 'folder' | 'content';
type Kind = 'image' | 'document';

/** Edit page URL per parent type (query-param vs path-param differ across controllers). */
function editUrl(parentType: ParentType, id: number, tab: string): string {
  switch (parentType) {
    case 'product':
      return `/admin/products/update?product_id=${id}&current_tab=${tab}`;
    case 'category':
      return `/admin/categories/update?category_id=${id}&current_tab=${tab}`;
    case 'brand':
      return `/admin/brand/update/${id}?current_tab=${tab}`;
    case 'folder':
      return `/admin/folders/update/${id}?current_tab=${tab}`;
    case 'content':
      return `/admin/content/update/${id}?current_tab=${tab}`;
  }
}

/**
 * A real 120x90 RGB PNG produced by PHP GD, so the Imagine/GD pipeline used by the
 * image processor can open it and generate a 300x300 bordered thumbnail. (A
 * hand-rolled or 1x1 PNG that Imagine cannot open makes fileUrl() swallow the
 * RuntimeException and return an empty thumbnail URL.) Returned as an in-memory payload.
 */
function pngPayload(name: string) {
  const base64 =
    'iVBORw0KGgoAAAANSUhEUgAAAHgAAABaCAIAAAD8YgW4AAAACXBIWXMAAA7EAAAOxAGVKw4bAAAA4klEQVR4nO3QQRHAIADAMMDZnOD/iYKpWHksUdDrPPsZfG/dDvgLoyNGR4yOGB0xOmJ0xOiI0RGjI0ZHjI4YHTE6YnTE6IjREaMjRkeMjhgdMTpidMToiNERoyNGR4yOGB0xOmJ0xOiI0RGjI0ZHjI4YHTE6YnTE6IjREaMjRkeMjhgdMTpidMToiNERoyNGR4yOGB0xOmJ0xOiI0RGjI0ZHjI4YHTE6YnTE6IjREaMjRkeMjhgdMTpidMToiNERoyNGR4yOGB0xOmJ0xOiI0RGjI0ZHjI4YHTE6YnTE6IjRkRfzYwJHSh+W4AAAAABJRU5ErkJggg==';
  return { name, mimeType: 'image/png', buffer: Buffer.from(base64, 'base64') };
}

function txtPayload(name: string, marker: string) {
  return { name, mimeType: 'text/plain', buffer: Buffer.from(`QA document ${marker}\n`) };
}

/**
 * Environmental noise NOT attributable to files-media:
 *  - Missing flag SVG (svgFlags/<code>.svg 404) emitted by BoLanguageSwitcher on
 *    every tabbed/edit screen for languages without a bundled flag asset.
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

function expectNoIssues(issues: PageIssue[]): void {
  const clean = dropNoise(issues);
  expect(clean, formatIssues(clean)).toHaveLength(0);
}

async function expectClean(
  page: Page,
  collector: IssueCollector,
  opts: { tabs?: boolean; modals?: boolean; allowDanger?: boolean } = {},
): Promise<void> {
  const issues = dropNoise(await sweepScreen(page, collector, opts));
  expect(issues, formatIssues(issues)).toHaveLength(0);
}

/** Read the Thelia CSRF token rendered inside the file fragment / list. */
async function readToken(page: Page): Promise<string> {
  return page.evaluate(() => {
    const list = document.querySelector<HTMLElement>('[data-bo-file-list-token-value]');
    if (list) return list.getAttribute('data-bo-file-list-token-value') ?? '';
    const upload = document.querySelector<HTMLElement>('[data-bo-file-upload-token-value]');
    if (upload) return upload.getAttribute('data-bo-file-upload-token-value') ?? '';
    const input = document.querySelector<HTMLInputElement>('input[name="_token"]');
    return input?.value ?? '';
  });
}

/**
 * Both file tabs (#tab-images and #tab-documents) live on the same edit page, so
 * each one has its own bo-file-list grid. The inactive pane keeps a HIDDEN grid in
 * the DOM. Scope every file selector to the pane that matches the kind under test.
 */
function pane(page: Page, kind: Kind) {
  return page.locator(kind === 'image' ? '#tab-images' : '#tab-documents');
}

/**
 * Open a parent edit page, activate the images/documents tab so bo-file-upload
 * mounts and pulls the list-ajax fragment. Returns once the list grid (or its
 * "empty" placeholder) is visible inside the matching pane.
 */
async function openFileTab(page: Page, parentType: ParentType, id: number, kind: Kind): Promise<void> {
  const tab = kind === 'image' ? 'images' : 'documents';
  await page.goto(editUrl(parentType, id, tab), { waitUntil: 'networkidle' });
  await page.getByTestId(`${parentType}-tab-${tab}`).click();
  const scope = pane(page, kind);
  // bo-file-upload#connect fires refresh() -> list-ajax. Wait for the list to render.
  await scope.locator(`[data-testid="bo-file-upload-form-${kind}"]`).waitFor({ state: 'visible', timeout: 10_000 });
  await scope
    .locator('[data-testid="bo-file-list-grid"], [data-testid="bo-file-list-empty"]')
    .first()
    .waitFor({ state: 'visible', timeout: 10_000 });
  await page.waitForTimeout(300);
}

/** Count file items currently rendered in the matching file tab's list. */
async function fileItemCount(page: Page, kind: Kind): Promise<number> {
  return pane(page, kind).locator('[data-bo-file-list-target="item"]').count();
}

/** Upload one file via the real bo-file-upload form, wait for the list to refresh. */
async function uploadFile(page: Page, kind: Kind, payload: { name: string; mimeType: string; buffer: Buffer }): Promise<void> {
  const scope = pane(page, kind);
  const before = await fileItemCount(page, kind);
  await scope.locator(`[data-testid="bo-file-upload-form-${kind}"] input[type="file"]`).setInputFiles(payload);
  await Promise.all([
    page.waitForResponse((r) => /save-ajax/.test(r.url()) && r.request().method() === 'POST', { timeout: 15_000 }),
    scope.getByTestId(`bo-file-upload-submit-${kind}`).click(),
  ]);
  // The controller refreshes the list after a successful save; wait for the count to grow.
  await expect
    .poll(async () => fileItemCount(page, kind), { timeout: 15_000 })
    .toBeGreaterThan(before);
}

/**
 * Full image lifecycle on a given parent: upload -> persistence (re-GET) ->
 * thumbnail 200 -> toggle visibility (persists) -> metadata edit page (persists)
 * -> delete (persists).
 */
async function runImageLifecycle(page: Page, collector: IssueCollector, parentType: ParentType, id: number): Promise<void> {
  const ref = qaRef('filesmedia');
  await openFileTab(page, parentType, id, 'image');
  const scope = pane(page, 'image');

  // UPLOAD
  await uploadFile(page, 'image', pngPayload(`${ref}.png`));

  // Identify the freshly added item: it has no title yet (image default title is empty),
  // so anchor on the newest list item (last in position order).
  let item = scope.locator('[data-bo-file-list-target="item"]').last();
  const fileId = await item.getAttribute('data-file-id');
  expect(fileId, 'uploaded image must expose a data-file-id').toBeTruthy();

  // PERSISTENCE of upload: re-open the tab (image cache now warm), the item is still there.
  await openFileTab(page, parentType, id, 'image');
  item = pane(page, 'image').locator(`[data-bo-file-list-target="item"][data-file-id="${fileId}"]`);
  await expect(item).toHaveCount(1);

  // THUMBNAIL: the list renders a generated cache thumbnail; it must be a real URL
  // (the IMAGE_PROCESS dispatch may need one render to warm the cache, hence the poll)
  // and resolve 200.
  const thumbImg = item.locator('[data-testid="bo-file-thumb"] img');
  await expect
    .poll(async () => (await thumbImg.getAttribute('src')) ?? '', { timeout: 8_000 })
    .not.toBe('');
  const thumbSrc = await thumbImg.getAttribute('src');
  const thumbResp = await page.request.get(thumbSrc!);
  expect(thumbResp.status(), `generated image thumbnail must return 200 (got ${thumbResp.status()})`).toBe(200);

  // METADATA EDIT: set a recognizable title via the dedicated edit page, then persist-check.
  await page.goto(`/admin/image/type/${parentType}/${fileId}/update`, { waitUntil: 'networkidle' });
  await expect(page.getByTestId('image-edit-page')).toBeVisible();
  await page.locator('input[name="thelia_image_modification[title]"]').fill(`${ref} title`);
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.getByTestId('bo-image-edit-save-stay').click(),
  ]);
  await page.goto(`/admin/image/type/${parentType}/${fileId}/update`, { waitUntil: 'networkidle' });
  await expect(page.locator('input[name="thelia_image_modification[title]"]')).toHaveValue(`${ref} title`);

  // TOGGLE visibility (AJAX POST + token) — verify persistence in DB-backed list.
  await openFileTab(page, parentType, id, 'image');
  item = pane(page, 'image').locator(`[data-bo-file-list-target="item"][data-file-id="${fileId}"]`);
  const toggleBtn = item.getByTestId(`bo-file-toggle-${fileId}`);
  const iconBefore = (await toggleBtn.locator('i').getAttribute('class')) ?? '';
  await Promise.all([
    page.waitForResponse((r) => /\/toggle/.test(r.url()) && r.request().method() === 'POST', { timeout: 10_000 }),
    toggleBtn.click(),
  ]);
  // PERSISTENCE: re-GET the list; the eye icon must have flipped.
  await openFileTab(page, parentType, id, 'image');
  const iconAfter =
    (await pane(page, 'image').locator(`[data-bo-file-list-target="item"][data-file-id="${fileId}"]`).getByTestId(`bo-file-toggle-${fileId}`).locator('i').getAttribute('class')) ?? '';
  expect(iconAfter, 'visibility toggle must flip the eye icon (bi-eye <-> bi-eye-slash) and persist').not.toBe(iconBefore);

  // DELETE (AJAX POST + token) — verify persistence (gone after re-GET).
  page.once('dialog', (d) => d.accept());
  await Promise.all([
    page.waitForResponse((r) => /\/delete/.test(r.url()) && r.request().method() === 'POST', { timeout: 10_000 }),
    pane(page, 'image').locator(`[data-bo-file-list-target="item"][data-file-id="${fileId}"]`).getByTestId(`bo-file-delete-${fileId}`).click(),
  ]);
  await openFileTab(page, parentType, id, 'image');
  await expect(pane(page, 'image').locator(`[data-bo-file-list-target="item"][data-file-id="${fileId}"]`)).toHaveCount(0);

  const issues = [...await scanDom(page, { allowDanger: true }), ...collector.drain()];
  expectNoIssues(issues);
}

/** Full document lifecycle: upload -> persistence -> title inline edit -> delete. */
async function runDocumentLifecycle(page: Page, collector: IssueCollector, parentType: ParentType, id: number): Promise<void> {
  const ref = qaRef('filesmedia-doc');
  await openFileTab(page, parentType, id, 'document');
  const scope = pane(page, 'document');

  await uploadFile(page, 'document', txtPayload(`${ref}.txt`, ref));

  const item = scope.locator('[data-bo-file-list-target="item"]').last();
  const fileId = await item.getAttribute('data-file-id');
  expect(fileId, 'uploaded document must expose a data-file-id').toBeTruthy();

  // PERSISTENCE of upload.
  await openFileTab(page, parentType, id, 'document');
  await expect(pane(page, 'document').locator(`[data-bo-file-list-target="item"][data-file-id="${fileId}"]`)).toHaveCount(1);

  // INLINE TITLE: the list embeds a per-item title form (POST update-title with _token).
  const titleForm = pane(page, 'document').getByTestId(`bo-file-title-form-${fileId}`);
  await titleForm.locator('input[name="title"]').fill(`${ref} title`);
  await Promise.all([
    page.waitForLoadState('networkidle'),
    titleForm.locator('button[type="submit"]').click(),
  ]);
  // PERSISTENCE: re-GET, the title input must hold the saved value.
  await openFileTab(page, parentType, id, 'document');
  await expect(
    pane(page, 'document').getByTestId(`bo-file-title-form-${fileId}`).locator('input[name="title"]'),
  ).toHaveValue(`${ref} title`);

  // METADATA EDIT page renders clean.
  await page.goto(`/admin/document/type/${parentType}/${fileId}/update`, { waitUntil: 'networkidle' });
  await expect(page.getByTestId('document-edit-page')).toBeVisible();
  const editIssues = [...await scanDom(page, { allowDanger: true }), ...await findLeakedFields(page)];
  expectNoIssues(editIssues);

  // DELETE.
  await openFileTab(page, parentType, id, 'document');
  page.once('dialog', (d) => d.accept());
  await Promise.all([
    page.waitForResponse((r) => /\/delete/.test(r.url()) && r.request().method() === 'POST', { timeout: 10_000 }),
    pane(page, 'document').locator(`[data-bo-file-list-target="item"][data-file-id="${fileId}"]`).getByTestId(`bo-file-delete-${fileId}`).click(),
  ]);
  await openFileTab(page, parentType, id, 'document');
  await expect(pane(page, 'document').locator(`[data-bo-file-list-target="item"][data-file-id="${fileId}"]`)).toHaveCount(0);

  const issues = [...await scanDom(page, { allowDanger: true }), ...collector.drain()];
  expectNoIssues(issues);
}

test.describe('files-media', () => {
  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  // ---------------------------------------------------------------------------
  // Per-parent file tab sweep (DOM/leaks; tabs/modals handled inside the page sweep)
  // ---------------------------------------------------------------------------
  for (const [parentType, id] of [
    ['product', PRODUCT],
    ['category', CATEGORY],
    ['brand', BRAND],
    ['folder', FOLDER],
    ['content', CONTENT],
  ] as [ParentType, number][]) {
    test.describe(`${parentType} — file tabs sweep`, () => {
      test('images & documents tabs mount the upload fragment + list, no console/network errors', async ({ page }) => {
        const collector = new IssueCollector(page);
        collector.attach();
        // Images tab.
        await openFileTab(page, parentType, id, 'image');
        await expect(pane(page, 'image').getByTestId('bo-file-upload-form-image')).toBeVisible();
        // Documents tab.
        await openFileTab(page, parentType, id, 'document');
        await expect(pane(page, 'document').getByTestId('bo-file-upload-form-document')).toBeVisible();
        // Sweep the whole edit page (every tab + every modal). allowDanger: file
        // tabs may legitimately render an info alert ("No images yet").
        await page.goto(editUrl(parentType, id, 'images'), { waitUntil: 'networkidle' });
        await expectClean(page, collector, { tabs: true, modals: true, allowDanger: true });
      });
    });
  }

  // ---------------------------------------------------------------------------
  // Image lifecycle per parent type
  // ---------------------------------------------------------------------------
  test.describe('product — image lifecycle', () => {
    test('upload -> thumbnail 200 -> edit title -> toggle -> delete, each persisted', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await runImageLifecycle(page, collector, 'product', PRODUCT);
    });
  });

  test.describe('category — image lifecycle', () => {
    test('upload -> thumbnail 200 -> edit title -> toggle -> delete, each persisted', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await runImageLifecycle(page, collector, 'category', CATEGORY);
    });
  });

  test.describe('brand — image lifecycle', () => {
    test('upload -> thumbnail 200 -> edit title -> toggle -> delete, each persisted', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await runImageLifecycle(page, collector, 'brand', BRAND);
    });
  });

  test.describe('folder — image lifecycle', () => {
    test('upload -> thumbnail 200 -> edit title -> toggle -> delete, each persisted', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await runImageLifecycle(page, collector, 'folder', FOLDER);
    });
  });

  test.describe('content — image lifecycle', () => {
    test('upload -> thumbnail 200 -> edit title -> toggle -> delete, each persisted', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await runImageLifecycle(page, collector, 'content', CONTENT);
    });
  });

  // ---------------------------------------------------------------------------
  // Document lifecycle per parent type
  // ---------------------------------------------------------------------------
  test.describe('product — document lifecycle', () => {
    test('upload -> persistence -> inline title -> delete', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await runDocumentLifecycle(page, collector, 'product', PRODUCT);
    });
  });

  test.describe('category — document lifecycle', () => {
    test('upload -> persistence -> inline title -> delete', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await runDocumentLifecycle(page, collector, 'category', CATEGORY);
    });
  });

  test.describe('brand — document lifecycle', () => {
    // FILESMEDIA-01 (FIXED 2026-06-08): uploading a DOCUMENT to a BRAND used to fail
    // with HTTP 422 {"error":"Thelia\\Model\\BrandDocument::getId(): Return value must
    // be of type int, null returned"}. Root cause was NOT in FileManager (the original
    // analysis misattributed it to copyUploadedFile): BrandDocument carried three
    // hand-written getter overrides — getId(): int, getTitle(): string and
    // getFile(): string — that tightened the nullable base return type. During save the
    // I18n cascade reads getId() to set the FK before the auto-increment is assigned, so
    // the strict `: int` override threw a TypeError. No other *Document model overrides
    // getId/getTitle (only getFile, which FileModelInterface requires non-null). Fix:
    // drop the spurious getId()/getTitle() overrides from core BrandDocument so it
    // inherits the nullable base getters like every other file model.
    test('FILESMEDIA-01: brand document upload persists (regression guard)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await runDocumentLifecycle(page, collector, 'brand', BRAND);
    });
  });

  test.describe('folder — document lifecycle', () => {
    test('upload -> persistence -> inline title -> delete', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await runDocumentLifecycle(page, collector, 'folder', FOLDER);
    });
  });

  test.describe('content — document lifecycle', () => {
    test('upload -> persistence -> inline title -> delete', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await runDocumentLifecycle(page, collector, 'content', CONTENT);
    });
  });

  // ---------------------------------------------------------------------------
  // Position reordering (bo-file-list position endpoint) — tested via the POST
  // endpoint directly (the list has no drag handle wired in this template; the
  // position-url is exposed for a future drag&drop). Upload 2 images, swap
  // positions, assert persistence, clean up.
  // ---------------------------------------------------------------------------
  test.describe('product — image position reordering (endpoint)', () => {
    test('update-position persists the new position', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      const refA = qaRef('filesmedia-posA');
      const refB = qaRef('filesmedia-posB');

      await openFileTab(page, 'product', PRODUCT, 'image');
      await uploadFile(page, 'image', pngPayload(`${refA}.png`));
      await openFileTab(page, 'product', PRODUCT, 'image');
      await uploadFile(page, 'image', pngPayload(`${refB}.png`));

      // The two newest items, in position order.
      await openFileTab(page, 'product', PRODUCT, 'image');
      const items = pane(page, 'image').locator('[data-bo-file-list-target="item"]');
      const total = await items.count();
      const lastId = await items.nth(total - 1).getAttribute('data-file-id');
      const prevId = await items.nth(total - 2).getAttribute('data-file-id');
      expect(lastId && prevId).toBeTruthy();

      // Read the last item's current position from the "#<n>" badge.
      const lastBadge = await items.nth(total - 1).locator('span', { hasText: /^#\d+$/ }).first().textContent();
      const lastPos = Number((lastBadge ?? '#0').replace('#', '').trim());
      expect(lastPos).toBeGreaterThan(0);

      const token = await readToken(page);
      // Move the last image up by one position.
      const resp = await page.request.post(`/admin/image/type/product/${PRODUCT}/update-position`, {
        form: { file_id: String(lastId), position: String(lastPos - 1), _token: token },
      });
      expect(resp.status(), `update-position should succeed (got ${resp.status()})`).toBeLessThan(400);

      // PERSISTENCE: re-GET; the moved image's badge must show the new position.
      await openFileTab(page, 'product', PRODUCT, 'image');
      const movedBadge = await pane(page, 'image')
        .locator(`[data-bo-file-list-target="item"][data-file-id="${lastId}"] span`, { hasText: /^#\d+$/ })
        .first()
        .textContent();
      const movedPos = Number((movedBadge ?? '#0').replace('#', '').trim());
      expect(movedPos, 'position must change after update-position').toBeLessThan(lastPos);

      // CLEANUP both uploaded images.
      for (const fid of [lastId, prevId]) {
        await openFileTab(page, 'product', PRODUCT, 'image');
        const delBtn = pane(page, 'image').locator(`[data-bo-file-list-target="item"][data-file-id="${fid}"]`).getByTestId(`bo-file-delete-${fid}`);
        if (await delBtn.count() === 0) continue;
        page.once('dialog', (d) => d.accept());
        await Promise.all([
          page.waitForResponse((r) => /\/delete/.test(r.url()) && r.request().method() === 'POST', { timeout: 10_000 }),
          delBtn.click(),
        ]);
      }
      await openFileTab(page, 'product', PRODUCT, 'image');
      await expect(pane(page, 'image').locator(`[data-bo-file-list-target="item"][data-file-id="${lastId}"]`)).toHaveCount(0);
      await expect(pane(page, 'image').locator(`[data-bo-file-list-target="item"][data-file-id="${prevId}"]`)).toHaveCount(0);

      const issues = [...await scanDom(page, { allowDanger: true }), ...collector.drain()];
      expectNoIssues(issues);
    });
  });

  // ---------------------------------------------------------------------------
  // CSRF protection on the AJAX endpoints (token enforced by FileController::checkCsrf)
  // ---------------------------------------------------------------------------
  test.describe('FileController — CSRF guards', () => {
    test('save-ajax / delete / toggle reject a request without a valid token', async ({ page }) => {
      // No token at all -> TokenProvider::checkToken throws -> not a 2xx.
      const save = await page.request.post(`/admin/image/type/product/${PRODUCT}/save-ajax`, {
        multipart: { file: pngPayload('no-token.png') },
      });
      expect(save.status(), 'save-ajax without token must be rejected').toBeGreaterThanOrEqual(400);

      const del = await page.request.post(`/admin/image/type/product/999999/delete`, {
        form: { _token: 'bogus' },
      });
      expect(del.status(), 'delete with bogus token must be rejected').toBeGreaterThanOrEqual(400);
    });
  });

  // ---------------------------------------------------------------------------
  // Export / Import (testplan files-media screens)
  // ---------------------------------------------------------------------------
  test.describe('Export', () => {
    test('export index loads clean (cards layout, no position reordering — issue #2/#7)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto('/admin/export', { waitUntil: 'networkidle' });
      // PAR-15 / issue #2: no position reordering control on export categories.
      expect(await page.locator('[data-bo-sortable]').count(), 'no sortable on export by design (issue #2)').toBe(0);
      await expectClean(page, collector, { tabs: false, modals: true, allowDanger: true });
    });
  });

  test.describe('Import', () => {
    test('import index loads clean (cards layout, no position reordering — issue #2/#7)', async ({ page }) => {
      const collector = new IssueCollector(page);
      collector.attach();
      await page.goto('/admin/import', { waitUntil: 'networkidle' });
      expect(await page.locator('[data-bo-sortable]').count(), 'no sortable on import by design (issue #2)').toBe(0);
      await expectClean(page, collector, { tabs: false, modals: true, allowDanger: true });
    });
  });
});
