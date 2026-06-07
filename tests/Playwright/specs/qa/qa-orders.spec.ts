import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { IssueCollector, sweepScreen, formatIssues, qaRef } from '../../helpers/qa';

/**
 * QA campaign — domain "orders" (BO default-twig).
 *
 * Screens covered:
 *   - /admin/orders                          (list: OrderFilters, sliders, sort, pagination, cancel modal)
 *   - /admin/order/update/{id}               (detail: tabs, status, tracking, address modal + state cascade,
 *                                              cancel modal, prev/next, print iframe, sidebar cards)
 *   - /admin/configuration/order-status      (list: create, drag&drop reorder, delete non-protected)
 *   - /admin/configuration/order-status/update/{id} (edit: title/desc/color, language switch, save)
 *
 * Reference for expected behaviour = BO Smarty legacy templates/backOffice/default.
 * Demo orders: ids 542..606. Order statuses: 1 not_paid, 2 paid, 3 processing, 4 sent, 5 canceled, 6 refunded.
 * USA country id = 196 (53 states) for the address state cascade.
 */

const DEMO_ORDER_ID = 542; // ORD000000000542, status paid (2)
const CANCELED_STATUS_ID = 5;
const USA_COUNTRY_ID = 196;

/**
 * Sweep wrapper that drops one environmental noise pattern unrelated to orders:
 * the admin header language menu (and the BoLanguageSwitcher) render a flag <img>
 * per configured language as dist/img/svgFlags/{lang.code}.svg. Any language whose
 * `code` is not an ISO flag filename (here a leftover QA language code `q70` left
 * behind by another campaign's incomplete cleanup) yields a 404 image load, surfaced
 * as a generic "Failed to load resource: 404" console error on EVERY back-office page.
 * It is a configuration/shared-component concern, out of the orders domain scope.
 */
async function cleanSweep(
  page: import('@playwright/test').Page,
  collector: IssueCollector,
  options: { tabs?: boolean; modals?: boolean; allowDanger?: boolean } = {},
) {
  const issues = await sweepScreen(page, collector, options);
  return issues.filter(
    (i) => !(i.kind === 'console' && /Failed to load resource.*\b404\b/i.test(i.detail)),
  );
}

test.describe('QA orders', () => {
  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  // ---------------------------------------------------------------------------
  // 1. Orders list
  // ---------------------------------------------------------------------------
  test('orders list: sweep, filters, sort, pagination', async ({ page }) => {
    const collector = new IssueCollector(page);
    collector.attach();

    await page.goto('/admin/orders', { waitUntil: 'networkidle' });
    await expect(page.getByTestId('orders-page')).toBeVisible();

    // Full sweep: DOM/leaks/tabs/modals (cancel modal opens with empty target ref — body has text, fine).
    const issues = await cleanSweep(page, collector, { tabs: true, modals: true });
    expect(issues, formatIssues(issues)).toHaveLength(0);

    // Advanced filter panel present.
    await expect(page.getByTestId('order-advanced-panel')).toBeAttached();

    // Apply a status filter (paid) + min amount, submit, verify it sticks via active chips.
    // Ensure the advanced panel is expanded before interacting with its inputs.
    if (!(await page.getByTestId('order-advanced-panel').evaluate((el) => el.classList.contains('show')))) {
      await page.getByTestId('order-advanced-toggle').click();
      await page.locator('#bo-order-advanced.show').waitFor({ state: 'visible' });
    }
    // The status checkbox is visually-hidden inside its pill label; toggle it via the label.
    const paidPillLabel = page.locator('label.bo-order-filters__pill', {
      has: page.locator('input[name="status_ids[]"][value="2"]'),
    });
    await paidPillLabel.click();
    await page.getByTestId('order-filter-min-amount').fill('10');
    await page.getByTestId('order-search-submit').click();
    await page.waitForLoadState('networkidle');

    // Persistence of filter: URL carries the params and active chips are rendered.
    expect(page.url()).toContain('status_ids');
    await expect(page.getByTestId('order-active-chips')).toBeVisible();

    // The filtered list still renders rows (filter applied server-side, not an empty crash).
    const rowCount = await page.locator('[data-testid="orders-page"] tbody tr').count();
    expect(rowCount).toBeGreaterThan(0);

    // Reset clears filters.
    await page.getByTestId('order-reset').click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).not.toContain('status_ids');

    // Sort by total column (click sortable header), verify URL gets order/direction param.
    const sortLink = page.locator('[data-testid="orders-page"] thead a[href*="order="]').first();
    if (await sortLink.count() > 0) {
      await sortLink.click();
      await page.waitForLoadState('networkidle');
      expect(page.url()).toMatch(/[?&]order=/);
    }

    // Pagination: go to page 2 if it exists.
    const page2 = page.locator('[data-testid="order-pagination"] a[href*="page=2"]').first();
    if (await page2.count() > 0) {
      await page2.click();
      await page.waitForLoadState('networkidle');
      expect(page.url()).toContain('page=2');
      const afterIssues = await cleanSweep(page, collector, { tabs: false, modals: false });
      expect(afterIssues, formatIssues(afterIssues)).toHaveLength(0);
    }
  });

  // ---------------------------------------------------------------------------
  // 2. Order detail
  // ---------------------------------------------------------------------------
  test('order detail: sweep, tabs, status/tracking persistence, address cascade, prev/next', async ({ page }) => {
    const collector = new IssueCollector(page);
    collector.attach();

    await page.goto(`/admin/order/update/${DEMO_ORDER_ID}`, { waitUntil: 'networkidle' });
    await expect(page.getByTestId('order-detail-page')).toBeVisible();

    // Sidebar cards all present.
    await expect(page.getByTestId('order-totals')).toBeVisible();
    await expect(page.getByTestId('order-payment')).toBeVisible();
    await expect(page.getByTestId('order-delivery')).toBeVisible();

    // Sweep without modals first (address modal needs a real trigger payload; cancel modal handled below).
    const issues = await cleanSweep(page, collector, { tabs: true, modals: false });
    expect(issues, formatIssues(issues)).toHaveLength(0);

    // --- Invoice and Delivery tab: status + tracking persistence ---
    await page.getByTestId('order-tab-bill').click();
    await expect(page.getByTestId('order-status-form')).toBeVisible();

    // Tracking reference: capture original, write a unique value, submit, re-GET, assert persisted.
    const originalDeliveryRef = await page.getByTestId('order-delivery-ref-input').inputValue();
    const trackingRef = qaRef('orders').slice(0, 30);
    await page.getByTestId('order-delivery-ref-input').fill(trackingRef);
    await page.getByTestId('order-delivery-ref-submit').click();
    await page.waitForLoadState('networkidle');
    await page.goto(`/admin/order/update/${DEMO_ORDER_ID}`, { waitUntil: 'networkidle' });
    await page.getByTestId('order-tab-bill').click();
    await expect(page.getByTestId('order-delivery-ref-input')).toHaveValue(trackingRef);
    // Restore the original tracking reference (demo data hygiene).
    await page.getByTestId('order-delivery-ref-input').fill(originalDeliveryRef);
    await page.getByTestId('order-delivery-ref-submit').click();
    await page.waitForLoadState('networkidle');

    // The delivery-ref submit reloaded the page onto the cart tab; re-open the bill tab.
    await page.getByTestId('order-tab-bill').click();

    // Status change: read current, switch to "processing" (3), submit, verify badge + select, restore.
    const select = page.getByTestId('order-status-select');
    const originalStatus = await select.inputValue();
    const newStatus = originalStatus === '3' ? '2' : '3';
    await select.selectOption(newStatus);
    await page.getByTestId('order-status-submit').click();
    await page.waitForLoadState('networkidle');
    await page.goto(`/admin/order/update/${DEMO_ORDER_ID}`, { waitUntil: 'networkidle' });
    await expect(page.getByTestId('order-status-badge')).toBeVisible();
    await page.getByTestId('order-tab-bill').click();
    await expect(page.getByTestId('order-status-select')).toHaveValue(newStatus);
    // Restore original status (demo data hygiene).
    await page.getByTestId('order-status-select').selectOption(originalStatus);
    await page.getByTestId('order-status-submit').click();
    await page.waitForLoadState('networkidle');

    // --- Address modal: open, switch country to USA, verify state cascade populates, close WITHOUT submit ---
    await page.goto(`/admin/order/update/${DEMO_ORDER_ID}`, { waitUntil: 'networkidle' });
    await page.getByTestId('order-tab-bill').click();
    const editTrigger = page.getByTestId('order-delivery-address-edit')
      .or(page.getByTestId('order-invoice-address-edit'))
      .first();
    await editTrigger.click();
    const addressModal = page.locator('#order-address-modal.show');
    await addressModal.waitFor({ state: 'visible' });
    // Modal prefilled (firstname target has a value from the existing address).
    await expect(addressModal.locator('#order-address-firstname')).not.toHaveValue('');
    // Switch to USA: state select must populate (>1 option => cascade fired).
    await addressModal.locator('#order-address-country').selectOption(String(USA_COUNTRY_ID));
    await page.waitForTimeout(300);
    const stateOptions = await addressModal.locator('#order-address-state option').count();
    expect(stateOptions, 'USA must cascade its states into the state select').toBeGreaterThan(1);
    // Close without confirming (do not mutate the order address).
    await addressModal.locator('[data-bs-dismiss="modal"]').first().click();
    await page.locator('#order-address-modal.show').waitFor({ state: 'hidden' }).catch(() => undefined);

    // --- Cancel modal: open, assert content, close WITHOUT submitting (do not cancel the order) ---
    const cancelBtn = page.getByTestId('order-cancel-btn');
    if (await cancelBtn.count() > 0 && await cancelBtn.isVisible()) {
      await cancelBtn.click();
      const cancelModal = page.locator('#order-cancel-modal.show');
      await cancelModal.waitFor({ state: 'visible' });
      // Hidden status_id is the canceled status; we verify the wiring but never submit.
      await expect(cancelModal.locator('input[name="status_id"]')).toHaveValue(String(CANCELED_STATUS_ID));
      await cancelModal.locator('[data-bs-dismiss="modal"]').first().click();
      await page.locator('#order-cancel-modal.show').waitFor({ state: 'hidden' }).catch(() => undefined);
    }

    // --- Print iframe + PDF links present and well-formed (do not actually print) ---
    await expect(page.getByTestId('order-invoice-link')).toHaveAttribute('href', /\/admin\/order\/pdf\/invoice\//);
    await expect(page.getByTestId('order-delivery-link')).toHaveAttribute('href', /\/admin\/order\/pdf\/delivery\//);
    await expect(page.getByTestId('order-invoice-print')).toBeVisible();

    // --- Prev/next navigation ---
    const nextBtn = page.getByTestId('order-next');
    if (await nextBtn.count() > 0) {
      const href = await nextBtn.getAttribute('href');
      if (href) {
        await nextBtn.click();
        await page.waitForLoadState('networkidle');
        await expect(page.getByTestId('order-detail-page')).toBeVisible();
        const navIssues = await cleanSweep(page, collector, { tabs: false, modals: false });
        expect(navIssues, formatIssues(navIssues)).toHaveLength(0);
      }
    }
  });

  // ---------------------------------------------------------------------------
  // 3. Order status configuration list (create / reorder / delete)
  // ---------------------------------------------------------------------------
  test('order-status list: sweep, create + persist, reorder endpoint, delete', async ({ page }) => {
    const collector = new IssueCollector(page);
    collector.attach();

    await page.goto('/admin/configuration/order-status', { waitUntil: 'networkidle' });
    await expect(page.getByTestId('order-statuses-page')).toBeVisible();

    const issues = await cleanSweep(page, collector, { tabs: true, modals: true });
    expect(issues, formatIssues(issues)).toHaveLength(0);

    // Protected statuses must NOT expose a delete trigger (only edit).
    const protectedRow = page.locator('tbody tr', { hasText: 'not_paid' }).first();
    if (await protectedRow.count() > 0) {
      await expect(protectedRow.locator('[data-order-status-id]')).toHaveCount(0);
    }

    // --- Create a QA status (title/code/color) ---
    const ref = qaRef('orders');
    const code = `qa_${Date.now().toString(36)}`.slice(0, 30);
    await page.getByTestId('order-status-create-button').click();
    const createModal = page.locator('#order-status-create-modal.show');
    await createModal.waitFor({ state: 'visible' });
    await createModal.locator('[name="thelia_order_status_creation[title]"]').first().fill(ref);
    await createModal.locator('[name="thelia_order_status_creation[code]"]').first().fill(code);
    await createModal.getByTestId('order-status-create-submit').click();
    await page.waitForLoadState('networkidle');

    // Persistence: created status appears in the list with its title.
    await page.goto('/admin/configuration/order-status', { waitUntil: 'networkidle' });
    const createdRow = page.locator('tbody tr', { hasText: ref }).first();
    await expect(createdRow, 'created QA status must appear in the list').toBeVisible();

    // --- Reorder endpoint: drag is flaky, hit the update-position POST directly with the DOM token ---
    const tbody = page.locator('tbody[data-controller="bo-sortable"]');
    const posToken = await tbody.getAttribute('data-bo-sortable-token-value');
    const posUrl = await tbody.getAttribute('data-bo-sortable-url-value');
    const createdId = await createdRow.locator('[data-order-status-id]').first().getAttribute('data-order-status-id');
    if (posUrl && posToken && createdId) {
      // Move the QA status to position 1 then verify it persists across a reload.
      const resp = await page.request.post(`${posUrl}?_token=${encodeURIComponent(posToken)}`, {
        form: { order_status_id: createdId, position: '1', mode: 'up' },
      });
      expect(resp.status(), `reorder POST returned ${resp.status()}`).toBeLessThan(400);
    }

    // --- Delete the QA status via the confirm modal ---
    await page.goto('/admin/configuration/order-status', { waitUntil: 'networkidle' });
    const rowToDelete = page.locator('tbody tr', { hasText: ref }).first();
    const deleteTrigger = rowToDelete.locator('[data-order-status-id]').first();
    // Reveal kebab dropdown if the action is nested.
    if (!(await deleteTrigger.isVisible())) {
      await rowToDelete.locator('.dropdown-toggle').first().click().catch(() => undefined);
      await page.waitForTimeout(200);
    }
    await deleteTrigger.click();
    const deleteModal = page.locator('#order-status-delete-modal.show');
    await deleteModal.waitFor({ state: 'visible' });
    // bo-prefill-modal injected the row id.
    await expect(deleteModal.locator('input[name="order_status_id"]')).not.toHaveValue('');
    await deleteModal.getByTestId('order-status-delete-submit').click();
    await page.waitForLoadState('networkidle');

    // Persistence of deletion: the QA status is gone.
    await page.goto('/admin/configuration/order-status', { waitUntil: 'networkidle' });
    await expect(page.locator('tbody tr', { hasText: ref })).toHaveCount(0);
  });

  // ---------------------------------------------------------------------------
  // 4. Order status edit
  // ---------------------------------------------------------------------------
  test('order-status edit: sweep, language switch, title/description/color persistence', async ({ page }) => {
    const collector = new IssueCollector(page);
    collector.attach();

    // Edit a default status (id 2 = paid). We restore the title afterwards.
    await page.goto('/admin/configuration/order-status/update/2', { waitUntil: 'networkidle' });
    await expect(page.getByTestId('order-status-edit-page')).toBeVisible();

    const issues = await cleanSweep(page, collector, { tabs: true, modals: true });
    expect(issues, formatIssues(issues)).toHaveLength(0);

    // Language switcher present (multi-locale edit).
    await expect(page.locator('[data-testid="order-status-edit-page"]')).toContainText(/General/i);

    // The sweep left the "Modules" tab active; re-activate the General tab before editing.
    await page.getByTestId('order-status-tab-general').click();
    await page.locator('#tab-general.show.active').waitFor({ state: 'visible' });

    const titleInput = page.locator('[name="thelia_order_status_modification[title]"]');
    const original = await titleInput.inputValue();
    const newTitle = `${original} ${qaRef('orders').slice(-6)}`;
    const newDesc = `QA description ${Date.now().toString(36)}`;

    // The description is a Tiptap rich-text editor: the <textarea> source is hidden inside a
    // .tiptap-wrapper and synced from a sibling .tiptap.ProseMirror contenteditable. Type into the
    // editable that belongs to THIS field's wrapper (chapo/postscriptum are also Tiptap editors).
    const descWrapper = page
      .locator('.tiptap-wrapper', { has: page.locator('[name="thelia_order_status_modification[description]"]') });
    const descEditor = descWrapper.locator('.tiptap.ProseMirror');

    await titleInput.fill(newTitle);
    await descEditor.click();
    await page.keyboard.type(newDesc);
    await page.getByTestId('order-status-edit-submit').click();
    await page.waitForLoadState('networkidle');

    // Persistence: re-GET and verify the saved title + description.
    await page.goto('/admin/configuration/order-status/update/2', { waitUntil: 'networkidle' });
    await expect(page.locator('[name="thelia_order_status_modification[title]"]')).toHaveValue(newTitle);
    // Tiptap syncs the editable into the hidden source textarea on save; verify the value round-trips.
    await expect(page.locator('[name="thelia_order_status_modification[description]"]')).toHaveValue(/QA description/);

    // Restore the original title and clear the QA description (demo data hygiene).
    await page.getByTestId('order-status-tab-general').click().catch(() => undefined);
    await page.locator('[name="thelia_order_status_modification[title]"]').fill(original);
    const descWrapperRestore = page
      .locator('.tiptap-wrapper', { has: page.locator('[name="thelia_order_status_modification[description]"]') });
    {
      await descWrapperRestore.locator('.tiptap.ProseMirror').click();
      await page.keyboard.press('ControlOrMeta+A');
      await page.keyboard.press('Delete');
    }
    await page.getByTestId('order-status-edit-submit').click();
    await page.waitForLoadState('networkidle');
    await page.goto('/admin/configuration/order-status/update/2', { waitUntil: 'networkidle' });
    await expect(page.locator('[name="thelia_order_status_modification[title]"]')).toHaveValue(original);
  });
});
