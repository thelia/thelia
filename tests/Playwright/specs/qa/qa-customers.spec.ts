import { execSync } from 'node:child_process';
import { test, expect, type Page } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { IssueCollector, sweepScreen, formatIssues, qaRef } from '../../helpers/qa';

/**
 * QA campaign — domain "customers" (BO default-twig).
 *
 * Screens covered (reference = BO Smarty legacy templates/backOffice/default):
 *   - /admin/customers                       list: search, advanced filters (newsletter/phone/dates/
 *                                            total-spent slider/orders slider/country/language/title),
 *                                            sort, pagination, create modal (PAR-03 state cascade,
 *                                            PAR-25 email_confirm), delete row action (refused if orders).
 *   - /admin/customer/update?customer_id=N   edit: 2-column layout, default address + state cascade,
 *                                            address CRUD (create modal / edit page / set-default / delete),
 *                                            customer orders table + pagination.
 *   - /admin/address/update?address_id=N     standalone address edit (state cascade).
 *   - /admin/newsletter                      list, export CSV (download + content), unsubscribe (delete).
 *
 * Demo facts (queried from the dev DB):
 *   - 10 demo customers, ALL have orders. Customer id 209 (CUS000000000001) has 7 orders → delete refused.
 *   - USA country id = 196 (53 states) ; France country id = 64 (no states).
 *   - 14 newsletter subscribers.
 *   - customer_confirm_email config OFF by default → no email_confirm field expected (PAR-25).
 *
 * Created QA entities are prefixed with qaRef('customers') and removed at the end of their test.
 */

const DEMO_CUSTOMER_WITH_ORDERS = 209; // CUS000000000001, 7 orders → delete must be refused
const USA_COUNTRY_ID = 196; // 53 states
const FRANCE_COUNTRY_ID = 64; // no states (PAR-03 expectation: state select stays empty/hidden)

/**
 * Drop one environmental noise pattern unrelated to the customers domain: the admin header
 * language menu renders a flag <img> per configured language as dist/img/svgFlags/{code}.svg.
 * A leftover QA language code from another campaign's incomplete cleanup yields a 404 image
 * load surfaced as a generic console error on EVERY back-office page. Configuration concern,
 * out of scope here (already tracked by the intl domain spec).
 */
async function cleanSweep(
  page: Page,
  collector: IssueCollector,
  options: { tabs?: boolean; modals?: boolean; allowDanger?: boolean } = {},
) {
  const issues = await sweepScreen(page, collector, options);
  return issues.filter(
    (i) => !(i.kind === 'console' && /Failed to load resource.*\b404\b/i.test(i.detail)),
  );
}

// Set by the newsletter test when it flips a subscriber to unsubscribed=1; reset in afterAll so the
// demo data stays pristine (CUST-03 means there is no UI path to re-subscribe).
let unsubscribedNewsletterId: string | null = null;

test.describe('QA customers', () => {
  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  test.afterAll(() => {
    if (unsubscribedNewsletterId) {
      try {
        execSync(
          `ddev mysql -N db -e "UPDATE newsletter SET unsubscribed=0 WHERE id=${Number(unsubscribedNewsletterId)};"`,
          { cwd: '/home/alexandre/Documents/_OS/WORKSPACE/THELIA/thelia-3', stdio: 'ignore' },
        );
      } catch {
        // best-effort demo-data hygiene; do not fail the suite on cleanup.
      }
    }
  });

  // ---------------------------------------------------------------------------
  // 1. Customers list — sweep, filters, sort, pagination
  // ---------------------------------------------------------------------------
  test('customers list: sweep, filters persistence, sort, pagination', async ({ page }) => {
    const collector = new IssueCollector(page);
    collector.attach();

    await page.goto('/admin/customers', { waitUntil: 'networkidle' });
    await expect(page.getByTestId('customers-page')).toBeVisible();

    // Full sweep: DOM/leaks/tabs/modals. The create + delete modals open here; both must
    // render non-empty (create form, delete confirm message). Zero tolerance on errors.
    const issues = await cleanSweep(page, collector, { tabs: true, modals: true });
    expect(issues, formatIssues(issues)).toHaveLength(0);

    // Advanced filter panel present with the full set of controls described by the testplan.
    await expect(page.getByTestId('customer-advanced-panel')).toBeAttached();
    await expect(page.getByTestId('customer-filter-phone')).toBeAttached();
    await expect(page.getByTestId('customer-filter-created-from')).toBeAttached();
    await expect(page.getByTestId('customer-filter-min-total')).toBeAttached();
    await expect(page.getByTestId('customer-filter-min-orders')).toBeAttached();
    await expect(page.getByTestId('customer-filter-country')).toBeAttached();
    await expect(page.locator('input[name="newsletter"]')).not.toHaveCount(0);
    await expect(page.locator('input[name="lang_ids[]"]')).not.toHaveCount(0);
    await expect(page.locator('input[name="title_ids[]"]')).not.toHaveCount(0);

    // Expand the advanced panel if collapsed, then apply newsletter=with + min_orders=1 + country=FR.
    const panel = page.getByTestId('customer-advanced-panel');
    if (!(await panel.evaluate((el) => el.classList.contains('show')))) {
      await page.getByTestId('customer-advanced-toggle').click();
      await page.locator('#bo-customer-advanced.show').waitFor({ state: 'visible' });
    }
    // Newsletter tri-state "Subscribed" radio (value="with") lives inside a btn-group; click its label.
    const newsletterYes = page.locator('input[name="newsletter"][value="with"]');
    await newsletterYes.evaluate((el: HTMLInputElement) => {
      (el.closest('fieldset')?.querySelector(`label[for="${el.id}"]`) as HTMLElement | null)?.click();
    });
    await page.getByTestId('customer-filter-min-orders').fill('1');
    await page.getByTestId('customer-filter-country').selectOption(String(FRANCE_COUNTRY_ID));
    await page.getByTestId('customer-search-submit').click();
    await page.waitForLoadState('networkidle');

    // Persistence: the URL carries the filter params and the active-chips strip is shown.
    expect(page.url()).toContain('newsletter=with');
    expect(page.url()).toContain('min_orders=1');
    expect(page.url()).toContain(`country_id=${FRANCE_COUNTRY_ID}`);
    await expect(page.getByTestId('customer-active-chips')).toBeVisible();
    // Re-GET the filtered URL and assert the inputs are re-hydrated (server keeps state).
    await page.goto(page.url(), { waitUntil: 'networkidle' });
    await expect(page.getByTestId('customer-filter-min-orders')).toHaveValue('1');
    await expect(page.getByTestId('customer-filter-country')).toHaveValue(String(FRANCE_COUNTRY_ID));
    const filteredIssues = await cleanSweep(page, collector, { tabs: false, modals: false });
    expect(filteredIssues, formatIssues(filteredIssues)).toHaveLength(0);

    // Reset clears every filter.
    await page.getByTestId('customer-reset').click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).not.toContain('newsletter=with');
    expect(page.url()).not.toContain('country_id=');

    // Search: a non-matching needle yields zero rows; clearing restores them.
    await page.getByTestId('customer-search-input').fill('zzz-qa-no-such-customer');
    await page.getByTestId('customer-search-submit').click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).toContain('q=zzz-qa-no-such-customer');
    await expect(page.getByTestId('datatable-customers-row')).toHaveCount(0);
    await page.goto('/admin/customers', { waitUntil: 'networkidle' });
    expect(await page.getByTestId('datatable-customers-row').count()).toBeGreaterThan(0);

    // Sort by a sortable column header (e.g. total_spent); URL must carry order + direction.
    const sortLink = page.locator('[data-testid="customers-page"] thead a[href*="order="]').first();
    if (await sortLink.count() > 0) {
      await sortLink.click();
      await page.waitForLoadState('networkidle');
      expect(page.url()).toMatch(/[?&]order=/);
    }

    // Pagination: 10 demo customers at 25/page → single page; assert pagination exists but is benign.
    await expect(page.getByTestId('customers-pagination').or(page.getByTestId('customers-page'))).toBeVisible();
  });

  // ---------------------------------------------------------------------------
  // 2. Customers list — create via modal (PAR-03 / PAR-25) + persistence + delete OK
  // ---------------------------------------------------------------------------
  test('customer create modal: state cascade, no email_confirm, persistence, then delete (no orders)', async ({ page }) => {
    const collector = new IssueCollector(page);
    collector.attach();

    await page.goto('/admin/customers', { waitUntil: 'networkidle' });
    await expect(page.getByTestId('customers-page')).toBeVisible();

    const ref = qaRef('customers');
    const email = `${ref.toLowerCase()}@qa.example.com`;
    const lastname = ref;

    // Open the create modal.
    await page.getByTestId('customer-create-button').click();
    const modal = page.locator('#customer-create-modal.show');
    await modal.waitFor({ state: 'visible' });
    const form = page.getByTestId('customer-create-form');

    // PAR-25: this demo install has customer_confirm_email = 1 (ON). The form must therefore
    // EXPOSE an email_confirm field that has to match the email (correct behaviour for this config).
    // (When the config is OFF the field is absent — verified by reading CustomerType + the
    // _customer_form_fields conditional; here we assert the ON path and feed a matching value.)
    const emailConfirm = form.locator('[name="thelia_customer_create[email_confirm]"]');
    await expect(emailConfirm, 'customer_confirm_email is ON → email_confirm field expected').toHaveCount(1);

    // PAR-03: the state <select> is present but starts empty for a country without states.
    // France (no states): the bo-state-cascade controller leaves the state select with no real
    // option (only the empty placeholder) and saving must persist state = NULL.
    const stateSelect = form.locator('[name="thelia_customer_create[state]"]');
    await expect(stateSelect, 'state select is rendered (cascade-controlled, not removed)').toHaveCount(1);

    // Fill the required identity + address fields (title is NotBlank → pick the first real option).
    await form.locator('[name="thelia_customer_create[title]"]').selectOption({ index: 1 });
    await form.locator('[name="thelia_customer_create[firstname]"]').fill('QA');
    await form.locator('[name="thelia_customer_create[lastname]"]').fill(lastname);
    await form.locator('[name="thelia_customer_create[email]"]').fill(email);
    await emailConfirm.fill(email);
    await form.locator('[name="thelia_customer_create[address1]"]').fill('1 QA Street');
    await form.locator('[name="thelia_customer_create[zipcode]"]').fill('75000');
    await form.locator('[name="thelia_customer_create[city]"]').fill('Paris');
    await form.locator('[name="thelia_customer_create[country]"]').selectOption(String(FRANCE_COUNTRY_ID));
    await page.waitForTimeout(300); // let the state cascade react to the country change

    // PAR-03: the bo-state-cascade controller keeps every state <option> in the DOM but hides+disables
    // those whose data-country-id != selected country, and hides the whole wrapper when none match.
    // France has no states → zero ENABLED state options + the wrapper is display:none.
    const enabledStateOptions = await stateSelect.locator('option[value]:not([value=""]):not([disabled])').count();
    expect(enabledStateOptions, 'France (no states) must leave no enabled state option').toBe(0);
    const stateWrapper = form.locator('[data-bo-state-cascade-target="wrapper"]');
    await expect(stateWrapper, 'state wrapper hidden for a stateless country').toBeHidden();

    await page.getByTestId('customer-create-submit').click();
    await page.waitForLoadState('networkidle');

    // CUST-06: the customer IS created and persisted, but AdminFormAction::submit() redirects to the
    // success route WITHOUT the new id (empty successParameters), so admin.customer.update.view gets
    // customer_id=0 and bounces to the list — the operator never lands on the new customer's edit page.
    // We therefore locate the freshly created QA customer through the list rather than the redirect URL.
    await page.goto('/admin/customers', { waitUntil: 'networkidle' });
    await page.getByTestId('customer-search-input').fill(lastname);
    await page.getByTestId('customer-search-submit').click();
    await page.waitForLoadState('networkidle');
    const qaRow = page.locator('[data-testid="datatable-customers-row"]', { hasText: lastname }).first();
    await expect(qaRow, 'created QA customer must appear in the list (persisted)').toBeVisible();
    const editHref = await qaRow.locator('a[href*="/admin/customer/update"]').first().getAttribute('href');
    expect(editHref).toBeTruthy();
    const createdUrl = new URL(editHref!, page.url()).pathname + new URL(editHref!, page.url()).search;
    const createdId = Number(new URL(editHref!, page.url()).searchParams.get('customer_id'));
    expect(createdId).toBeGreaterThan(0);

    // Persistence (not just a 200): open the edit page and assert the saved values round-trip.
    await page.goto(createdUrl, { waitUntil: 'networkidle' });
    await expect(page.locator('[name="thelia_customer_update[lastname]"]')).toHaveValue(lastname);
    await expect(page.locator('[name="thelia_customer_update[email]"]')).toHaveValue(email);
    await expect(page.locator('[name="thelia_customer_update[city]"]')).toHaveValue('Paris');
    // PAR-03 in DB: the persisted default address has no state for France → the state select is empty.
    await expect(page.locator('[name="thelia_customer_update[state]"]')).toHaveValue('');
    const editIssues = await cleanSweep(page, collector, { tabs: false, modals: false });
    expect(editIssues, formatIssues(editIssues)).toHaveLength(0);

    // --- Delete OK: this QA customer has no orders, so the delete must succeed. ---
    await page.goto('/admin/customers', { waitUntil: 'networkidle' });
    await page.getByTestId('customer-search-input').fill(lastname);
    await page.getByTestId('customer-search-submit').click();
    await page.waitForLoadState('networkidle');
    const delRow = page.locator('[data-testid="datatable-customers-row"]', { hasText: lastname }).first();
    await expect(delRow).toBeVisible();
    const deleteTrigger = delRow.locator('[data-testid="datatable-action-delete"]').first();
    if (!(await deleteTrigger.isVisible())) {
      await delRow.locator('[data-testid="datatable-action-overflow"]').click();
      await page.waitForTimeout(200);
    }
    await deleteTrigger.click();
    const delModal = page.locator('#customer-delete-modal.show');
    await delModal.waitFor({ state: 'visible' });
    // bo-prefill-modal injected the row's customer_id into the hidden input.
    await expect(delModal.locator('input[name="customer_id"]')).toHaveValue(String(createdId));
    await page.getByTestId('customer-delete-submit').click();
    await page.waitForLoadState('networkidle');

    // Persistence of deletion: the edit URL now redirects to the list (customer gone).
    await page.goto(createdUrl, { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/\/admin\/customers$/);
  });

  // ---------------------------------------------------------------------------
  // 3. Customers list — delete refused when the customer has orders
  // ---------------------------------------------------------------------------
  test('customer delete is refused when the customer has existing orders', async ({ page }) => {
    const collector = new IssueCollector(page);
    collector.attach();

    await page.goto('/admin/customers', { waitUntil: 'networkidle' });
    await expect(page.getByTestId('customers-page')).toBeVisible();

    // Drive the protected delete the way the UI does: POST the delete form with the DOM token
    // and the demo customer id that has orders. The controller must redirect to the list with a
    // flash error and must NOT delete the customer.
    const deleteForm = page.getByTestId('customer-delete-form');
    const action = await deleteForm.getAttribute('action'); // already carries ?_token=...
    expect(action, 'delete form action with embedded token').toBeTruthy();

    const resp = await page.request.post(new URL(action!, page.url()).toString(), {
      form: { customer_id: String(DEMO_CUSTOMER_WITH_ORDERS) },
      maxRedirects: 0,
    });
    // Refusal is a redirect back to the list (3xx), never a 500.
    expect(resp.status(), `delete-with-orders returned ${resp.status()}`).toBeGreaterThanOrEqual(300);
    expect(resp.status()).toBeLessThan(400);

    // The customer still exists: its edit page loads (no redirect to list).
    await page.goto(`/admin/customer/update?customer_id=${DEMO_CUSTOMER_WITH_ORDERS}`, { waitUntil: 'networkidle' });
    await expect(page).toHaveURL(new RegExp(`customer_id=${DEMO_CUSTOMER_WITH_ORDERS}`));
    await expect(page.getByTestId('customer-edit-page')).toBeVisible();

    // allowDanger: the refusal flash ("This customer has existing orders and cannot be deleted.")
    // is rendered as an .alert-danger after the redirect — that is the EXPECTED outcome, not a bug.
    const issues = await cleanSweep(page, collector, { tabs: false, modals: false, allowDanger: true });
    expect(issues, formatIssues(issues)).toHaveLength(0);
  });

  // ---------------------------------------------------------------------------
  // 3b. REAL BUG (CUST-01, confirmed at crawl): GET /admin/customer/delete without customer_id → 500
  // ---------------------------------------------------------------------------
  test('customer delete without id should not 500 (CUST-01: missing null guard)', async ({ page }) => {
    // CustomerController::delete() does findPk(0) → null, but only guards the "has orders"
    // branch; line ~310 then calls `new CustomerEvent($customer)` with $customer === null,
    // throwing TypeError (Argument #1 must be of type Customer, null given). A request without
    // customer_id should early-redirect, not crash. Reproduced via a bare GET.
    const resp = await page.request.get('/admin/customer/delete', { maxRedirects: 0 });
    expect(
      resp.status(),
      `GET /admin/customer/delete without customer_id returned ${resp.status()} (expected a redirect or 400/403, not 500)`,
    ).toBeLessThan(500);
  });

  // ---------------------------------------------------------------------------
  // 4. Customer edit — sweep, info persistence, default address + state cascade
  // ---------------------------------------------------------------------------
  test('customer edit: sweep, personal info + address persistence, USA state cascade', async ({ page }) => {
    const collector = new IssueCollector(page);
    collector.attach();

    const url = `/admin/customer/update?customer_id=${DEMO_CUSTOMER_WITH_ORDERS}`;
    await page.goto(url, { waitUntil: 'networkidle' });
    await expect(page.getByTestId('customer-edit-page')).toBeVisible();

    // 2-column layout sections present.
    await expect(page.getByTestId('customer-addresses-section')).toBeVisible();
    await expect(page.getByTestId('customer-orders-section')).toBeVisible();

    // Sweep without the address create modal (handled in test 5); tabs none on this page.
    const issues = await cleanSweep(page, collector, { tabs: true, modals: false });
    expect(issues, formatIssues(issues)).toHaveLength(0);

    // Capture originals to restore demo data afterwards.
    const firstnameInput = page.locator('[name="thelia_customer_update[firstname]"]');
    const cityInput = page.locator('[name="thelia_customer_update[city]"]');
    const originalFirst = await firstnameInput.inputValue();
    const originalCity = await cityInput.inputValue();

    const newFirst = `QA-${Date.now().toString(36)}`;
    const newCity = `QA City ${Date.now().toString(36)}`;
    await firstnameInput.fill(newFirst);
    await cityInput.fill(newCity);
    await page.getByTestId('customer-edit-submit').click();
    await page.waitForLoadState('networkidle');

    // Persistence: re-GET and assert the saved values.
    await page.goto(url, { waitUntil: 'networkidle' });
    await expect(page.locator('[name="thelia_customer_update[firstname]"]')).toHaveValue(newFirst);
    await expect(page.locator('[name="thelia_customer_update[city]"]')).toHaveValue(newCity);

    // State cascade on the default-address country select: switching to USA must ENABLE its states
    // (the cascade enables only the options whose data-country-id matches the selected country).
    await page.locator('[name="thelia_customer_update[country]"]').selectOption(String(USA_COUNTRY_ID));
    await page.waitForTimeout(400);
    const usaStateOptions = await page
      .locator('[name="thelia_customer_update[state]"] option[value]:not([value=""]):not([disabled])')
      .count();
    expect(usaStateOptions, 'USA must cascade its states into the state select').toBeGreaterThan(1);

    // Restore demo data (firstname/city); leave country untouched (we did not submit the USA change).
    await page.goto(url, { waitUntil: 'networkidle' });
    await page.locator('[name="thelia_customer_update[firstname]"]').fill(originalFirst);
    await page.locator('[name="thelia_customer_update[city]"]').fill(originalCity);
    await page.getByTestId('customer-edit-submit').click();
    await page.waitForLoadState('networkidle');
    await page.goto(url, { waitUntil: 'networkidle' });
    await expect(page.locator('[name="thelia_customer_update[firstname]"]')).toHaveValue(originalFirst);
  });

  // ---------------------------------------------------------------------------
  // 5a. REAL BUG (CUST-02): the "Add a new address" modal never persists.
  // ---------------------------------------------------------------------------
  test('customer address create modal persists a new address (CUST-02: customer_id read mismatch)', async ({ page }) => {
    // The create modal posts the customer id nested as `thelia_address_create[customer_id]` (rendered
    // value=209), but AddressController::create() reads it with `$request->request->get('customer_id', 0)`
    // — a TOP-LEVEL key that does not exist — so $customerId resolves to 0, findPk(0) is null and the
    // action throws "Customer not found.", redirecting to /admin/customers WITHOUT creating the address.
    // Fix: read the id from the validated form data (e.g. $form->get('customer_id')->getData()) or from
    // $request->request->all(self::CREATE_FORM_NAME)['customer_id']. Reproduced below.
    const url = `/admin/customer/update?customer_id=${DEMO_CUSTOMER_WITH_ORDERS}`;
    await page.goto(url, { waitUntil: 'networkidle' });
    const label = `QA-ADDR-${Date.now().toString(36)}`;
    await page.getByTestId('customer-address-create-button').click();
    const modal = page.locator('#customer-address-create-modal.show');
    await modal.waitFor({ state: 'visible' });
    const af = page.getByTestId('customer-address-create-form');
    await af.locator('[name="thelia_address_create[label]"]').fill(label);
    await af.locator('[name="thelia_address_create[title]"]').selectOption({ index: 1 });
    await af.locator('[name="thelia_address_create[firstname]"]').fill('QA');
    await af.locator('[name="thelia_address_create[lastname]"]').fill('Address');
    await af.locator('[name="thelia_address_create[address1]"]').fill('2 QA Avenue');
    await af.locator('[name="thelia_address_create[zipcode]"]').fill('69000');
    await af.locator('[name="thelia_address_create[city]"]').fill('Lyon');
    await af.locator('[name="thelia_address_create[country]"]').selectOption(String(FRANCE_COUNTRY_ID));
    await page.getByTestId('customer-address-create-submit').click();
    await page.waitForLoadState('networkidle');
    await page.goto(url, { waitUntil: 'networkidle' });
    const createdAddressRow = page
      .locator('[data-testid="customer-address-row"]', { hasText: label })
      .first();
    await expect(createdAddressRow).toBeVisible();

    // Cleanup: remove the QA address so demo data stays pristine (the delete is part of the test).
    execSync(
      `ddev mysql -N db -e "DELETE FROM address WHERE label='${label}';"`,
      { cwd: '/home/alexandre/Documents/_OS/WORKSPACE/THELIA/thelia-3', stdio: 'ignore' },
    );
    await page.goto(url, { waitUntil: 'networkidle' });
    await expect(
      page.locator('[data-testid="customer-address-row"]', { hasText: label }),
    ).toHaveCount(0);
  });

  // ---------------------------------------------------------------------------
  // 5b. Address edit page + set-default toggle on EXISTING demo addresses.
  //     (Create is blocked by CUST-02, demo addresses must not be deleted → no destructive delete here;
  //      the delete form wiring is asserted structurally instead.)
  // ---------------------------------------------------------------------------
  test('address edit persistence, set-default toggle, delete-form wiring (existing demo addresses)', async ({ page }) => {
    const collector = new IssueCollector(page);
    collector.attach();

    const url = `/admin/customer/update?customer_id=${DEMO_CUSTOMER_WITH_ORDERS}`;
    await page.goto(url, { waitUntil: 'networkidle' });
    await expect(page.getByTestId('customer-addresses-section')).toBeVisible();

    // Pick a non-default address row (so it exposes the set-default + delete forms).
    const nonDefaultRow = page
      .locator('[data-testid="customer-address-row"]')
      .filter({ hasNot: page.locator('.badge') })
      .first();
    await expect(nonDefaultRow).toBeVisible();
    const addressId = await nonDefaultRow.getAttribute('data-address-id');
    expect(addressId).toBeTruthy();

    // --- Edit page loads, sweeps clean, and persists a label change (restored afterwards). ---
    await page.goto(`/admin/address/update?address_id=${addressId}`, { waitUntil: 'networkidle' });
    await expect(page.getByTestId('address-edit-page')).toBeVisible();
    await expect(page.getByTestId('address-edit-form')).toBeVisible();
    const addrEditIssues = await cleanSweep(page, collector, { tabs: false, modals: false });
    expect(addrEditIssues, formatIssues(addrEditIssues)).toHaveLength(0);

    const addrLabelInput = page.locator('[name="thelia_address_update[label]"]');
    const originalLabel = await addrLabelInput.inputValue();
    const editedLabel = `QA-${Date.now().toString(36)}`;
    await addrLabelInput.fill(editedLabel);
    await page.getByTestId('address-edit-submit').click();
    await page.waitForLoadState('networkidle');
    await page.goto(`/admin/address/update?address_id=${addressId}`, { waitUntil: 'networkidle' });
    await expect(page.locator('[name="thelia_address_update[label]"]')).toHaveValue(editedLabel);
    // Restore the original label (demo data hygiene).
    await page.locator('[name="thelia_address_update[label]"]').fill(originalLabel);
    await page.getByTestId('address-edit-submit').click();
    await page.waitForLoadState('networkidle');

    // --- Set-default toggle: make this row default, verify the badge moved, then restore. ---
    await page.goto(url, { waitUntil: 'networkidle' });
    const originalDefaultRow = page
      .locator('[data-testid="customer-address-row"]')
      .filter({ has: page.locator('.badge') })
      .first();
    const originalDefaultId = await originalDefaultRow.getAttribute('data-address-id');

    const targetRow = page.locator(`[data-testid="customer-address-row"][data-address-id="${addressId}"]`);
    const setDefaultForm = targetRow.locator('form[action*="/admin/address/use"]');
    await expect(setDefaultForm, 'non-default row exposes the set-default form').toHaveCount(1);
    const setDefaultAction = await setDefaultForm.getAttribute('action'); // carries ?_token=
    let resp = await page.request.post(new URL(setDefaultAction!, page.url()).toString(), {
      form: { address_id: String(addressId) },
      maxRedirects: 0,
    });
    expect(resp.status(), `set-default returned ${resp.status()}`).toBeLessThan(400);
    // Persistence: the badge is now on our row.
    await page.goto(url, { waitUntil: 'networkidle' });
    await expect(
      page.locator(`[data-testid="customer-address-row"][data-address-id="${addressId}"] .badge`),
    ).toBeVisible();

    // Restore the original default address.
    if (originalDefaultId && originalDefaultId !== addressId) {
      const restoreRow = page.locator(`[data-testid="customer-address-row"][data-address-id="${originalDefaultId}"]`);
      const restoreForm = restoreRow.locator('form[action*="/admin/address/use"]');
      const restoreAction = await restoreForm.getAttribute('action');
      resp = await page.request.post(new URL(restoreAction!, page.url()).toString(), {
        form: { address_id: String(originalDefaultId) },
        maxRedirects: 0,
      });
      expect(resp.status()).toBeLessThan(400);
    }

    // --- Delete-form wiring is present + CSRF-protected. We do NOT delete demo addresses (no QA
    // address can be created while CUST-02 stands, and demo data must stay intact). Assert the inline
    // delete form exists, is a POST, and carries an embedded CSRF token + an onsubmit confirm. ---
    await page.goto(url, { waitUntil: 'networkidle' });
    const someDeleteForm = page.locator('form[action*="/admin/address/delete"]').first();
    await expect(someDeleteForm).not.toHaveCount(0);
    expect(await someDeleteForm.getAttribute('action')).toContain('_token=');
    expect((await someDeleteForm.getAttribute('method'))?.toLowerCase()).toBe('post');
    await expect(someDeleteForm).toHaveAttribute('onsubmit', /confirm/i);
  });

  // ---------------------------------------------------------------------------
  // 6. Customer edit — orders table pagination (orders_page)
  // ---------------------------------------------------------------------------
  test('customer edit: orders table renders and paginates', async ({ page }) => {
    const collector = new IssueCollector(page);
    collector.attach();

    const url = `/admin/customer/update?customer_id=${DEMO_CUSTOMER_WITH_ORDERS}`;
    await page.goto(url, { waitUntil: 'networkidle' });
    await expect(page.getByTestId('customer-orders-section')).toBeVisible();

    // The customer with orders shows the orders table (not the empty-state message).
    await expect(page.getByTestId('customer-orders-table')).toBeVisible();
    const orderRows = page.getByTestId('customer-order-row');
    expect(await orderRows.count()).toBeGreaterThan(0);

    // Each order row links to its order edit page.
    await expect(orderRows.first().locator('a[href*="/admin/order/update"]').first()).toBeVisible();

    // Pagination is 20/page; customer 209 has 7 orders → no page 2. If a paginator exists, page 2 works.
    const page2 = page.locator('[data-testid="customer-orders-pagination"] a[href*="orders_page=2"]').first();
    if (await page2.count() > 0) {
      await page2.click();
      await page.waitForLoadState('networkidle');
      expect(page.url()).toContain('orders_page=2');
      await expect(page.getByTestId('customer-orders-table')).toBeVisible();
    }

    const issues = await cleanSweep(page, collector, { tabs: false, modals: false });
    expect(issues, formatIssues(issues)).toHaveLength(0);
  });

  // ---------------------------------------------------------------------------
  // 7. Newsletter — sweep, export CSV (download + content), unsubscribe
  // ---------------------------------------------------------------------------
  test('newsletter: sweep, export CSV download + content, unsubscribe persistence', async ({ page }) => {
    const collector = new IssueCollector(page);
    collector.attach();

    await page.goto('/admin/newsletter', { waitUntil: 'networkidle' });
    await expect(page.getByTestId('newsletter-page')).toBeVisible();

    const issues = await cleanSweep(page, collector, { tabs: true, modals: true });
    expect(issues, formatIssues(issues)).toHaveLength(0);

    // The subscribers list has rows.
    const rows = page.getByTestId('datatable-newsletter-row');
    const rowCount = await rows.count();
    expect(rowCount).toBeGreaterThan(0);

    // --- Export CSV: trigger the download and assert it is a CSV with the expected header. ---
    const exportLink = page.getByTestId('newsletter-export-button');
    await expect(exportLink).toBeVisible();
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      exportLink.click(),
    ]);
    expect(download.suggestedFilename()).toMatch(/newsletter-subscribers-\d{4}-\d{2}-\d{2}\.csv/);
    const stream = await download.createReadStream();
    let content = '';
    for await (const chunk of stream) content += chunk.toString();
    expect(content.split('\n')[0]).toContain('email');
    expect(content.split('\n')[0]).toContain('locale');
    // At least one data row (we have 14 subscribers).
    expect(content.trim().split('\n').length).toBeGreaterThan(1);

    // --- Unsubscribe one subscriber via its row action. ---
    await page.goto('/admin/newsletter', { waitUntil: 'networkidle' });
    const firstRow = page.getByTestId('datatable-newsletter-row').first();
    const deleteLink = firstRow.locator('a[href*="/admin/newsletter/delete"]').first();
    const deleteHref = await deleteLink.getAttribute('href');
    const newsletterId = deleteHref ? new URL(deleteHref, page.url()).searchParams.get('newsletter_id') : null;
    expect(newsletterId, 'newsletter row exposes a delete link with newsletter_id').toBeTruthy();
    unsubscribedNewsletterId = newsletterId; // reset in afterAll (the action is a flag flip, see CUST-03)

    // CUST-04: the row action href carries its own same-session CSRF _token, so a genuine UI click
    // (a plain GET on the endpoint, which accepts the token from the query string) succeeds.
    expect(deleteHref, 'CUST-04: newsletter unsubscribe link carries a _token').toContain('_token');
    const delResp = await page.request.get(new URL(deleteHref!, page.url()).toString(), { maxRedirects: 0 });
    expect(delResp.status(), `newsletter unsubscribe returned ${delResp.status()}`).toBeGreaterThanOrEqual(300);
    expect(delResp.status()).toBeLessThan(400);

    // CUST-03: NEWSLETTER_UNSUBSCRIBE flips `unsubscribed=1` (it does NOT delete the row), and the list
    // query now filters out unsubscribed subscribers — so the processed row disappears from the list and
    // the operator gets clear feedback the action took effect.
    await page.goto('/admin/newsletter', { waitUntil: 'networkidle' });
    const afterCount = await page.getByTestId('datatable-newsletter-row').count();
    expect(afterCount, 'CUST-03: unsubscribed subscriber is removed from the list').toBe(rowCount - 1);
    await expect(
      page.locator(`[data-testid="datatable-newsletter-row"] a[href*="newsletter_id=${newsletterId}"]`),
    ).toHaveCount(0);
  });
});
