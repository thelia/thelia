import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { loginAdmin } from '../helpers/admin';
import { addCurrentProductToCart, gotoProduct } from '../helpers/cart';
import { placeOrderWithCheque } from '../helpers/checkout';
import {
  attachBuyerInBackOffice,
  buyerCountOf,
  companyIdByName,
  createCompanyInBackOffice,
  customerIdOf,
  LEGAL_IDENTIFIERS,
  membershipOf,
  newCompanyName,
  ordersOfCompany,
  purgeCompanyData,
  registerBuyerAccount,
  remainingCompanyRows,
  signedInAs,
} from '../helpers/company';
import { PRODUCTS } from '../helpers/price-list';
import type { Customer } from '../helpers/customer';

/**
 * Companies, back-office (default-twig): the menu, the company sheet, the buyers (attached by the
 * exact e-mail address, role and limits), the rule of the last administrator, the company on the
 * customer sheet and on the customer list, on the order sheet, the roles of the buyers and the
 * refusal to delete a company that has orders.
 *
 * Companies are named with the e2e-co- prefix, accounts have an e-mail address with it, and
 * everything is purged in afterAll. What a screen saved is checked in the database, not only on
 * the page that saved it.
 */
const ERROR = 'bo-flash-danger';
const SUCCESS = 'bo-flash-success';

test.describe('Companies: back-office', () => {
  test.describe.configure({ mode: 'serial' });
  test.skip((process.env.BO_TEMPLATE ?? 'default-twig') !== 'default-twig', 'BO Twig only.');

  let adminContext: BrowserContext;
  let admin: Page;
  let nameOne = '';
  let nameTwo = '';
  let companyOne = 0;
  let companyTwo = 0;
  let boss: Customer;
  let buyer: Customer;
  let drifter: Customer;
  let secondBoss: Customer;
  let secondBossBis: Customer;

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(300_000);
    await purgeCompanyData();
    boss = await registerBuyerAccount(browser, 'boss');
    buyer = await registerBuyerAccount(browser, 'buyer');
    drifter = await registerBuyerAccount(browser, 'drifter');
    secondBoss = await registerBuyerAccount(browser, 'second');
    secondBossBis = await registerBuyerAccount(browser, 'secondbis');
    adminContext = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport: { width: 1440, height: 900 } });
    admin = await adminContext.newPage();
    await loginAdmin(admin);
  });

  test.afterAll(async () => {
    await adminContext?.close();
    await purgeCompanyData();
    expect(await remainingCompanyRows()).toEqual({ companies: 0, customers: 0, memberships: 0, ownRoles: 0 });
  });

  test('the Customers menu leads to the companies screen, empty or not', async () => {
    await admin.goto('/admin/customers');
    const menu = admin.getByTestId('bo-nav-customer');
    await menu.locator('> a').first().click();
    const entry = admin.getByTestId('bo-nav-customer-companies');
    await expect(entry).toBeVisible();
    await Promise.all([admin.waitForURL(/\/admin\/companies/), entry.click()]);
    await expect(admin.getByTestId('companies-page')).toBeVisible();
    await expect(admin.getByRole('heading', { level: 1 })).toBeVisible();
  });

  test('creates two companies with their legal identifiers and finds them in the list', async () => {
    nameOne = newCompanyName('s1');
    nameTwo = newCompanyName('s2');
    companyOne = await createCompanyInBackOffice(admin, nameOne, LEGAL_IDENTIFIERS);
    companyTwo = await createCompanyInBackOffice(admin, nameTwo, { siret: '98765432100022' });

    await expect(admin).toHaveURL(new RegExp(`/admin/companies/${companyTwo}`));
    await expect(admin.getByTestId('company-state')).toHaveClass(/text-bg-success/);
    await expect(admin.locator('#company-siret')).toHaveValue('98765432100022');

    await admin.goto(`/admin/companies?q=${encodeURIComponent(nameOne)}`);
    await expect(admin.getByTestId('companies-total')).toContainText('1');
    await expect(admin.getByTestId('companies-page')).toContainText(nameOne);
    await expect(admin.getByTestId('companies-page')).not.toContainText(nameTwo);
    await admin.goto(`/admin/companies?q=${LEGAL_IDENTIFIERS.siret}`);
    await expect(admin.getByTestId('companies-page')).toContainText(nameOne);
  });

  test('refuses to attach an account by a fragment of its address, attaches it by the exact address', async () => {
    await admin.goto(`/admin/companies/${companyOne}?tab=buyers`);
    const form = admin.getByTestId('company-buyer-attach-form');

    // A part of the address finds nothing: the field is not a directory of the customers.
    const fragment = boss.email.slice(0, boss.email.indexOf('@') - 3);
    await form.locator('input[name="lookup"]').fill(fragment);
    await form.locator('select[name="role_code"]').selectOption('company_admin');
    await form.getByTestId('company-buyer-attach-submit').click();
    await expect(admin.getByTestId(ERROR)).toContainText(/no account has exactly/i);
    expect(await membershipOf(boss.email)).toBeNull();

    // The account of another domain is not found by its domain either.
    await admin.goto(`/admin/companies/${companyOne}?tab=buyers`);
    await admin.getByTestId('company-buyer-attach-form').locator('input[name="lookup"]').fill('@example.test');
    await admin.getByTestId('company-buyer-attach-submit').click();
    await expect(admin.getByTestId(ERROR)).toContainText(/no account has exactly/i);
    expect(await buyerCountOf(companyOne)).toBe(0);

    // The exact address, in the case the account was typed with.
    await attachBuyerInBackOffice(admin, companyOne, boss.email.toUpperCase(), 'company_admin');
    const bossId = await customerIdOf(boss.email);
    await expect(admin.getByTestId(`company-buyer-${bossId}`)).toContainText(boss.email);
    expect(await membershipOf(boss.email)).toMatchObject({ companyId: companyOne, roleCode: 'company_admin', orderLimit: null });

    await attachBuyerInBackOffice(admin, companyOne, buyer.email, 'buyer');
    expect(await membershipOf(buyer.email)).toMatchObject({ companyId: companyOne, roleCode: 'buyer' });
    await expect(admin.getByTestId('company-buyers-total')).toContainText('2');

    // An account that already belongs to a company cannot be attached to another one.
    await admin.goto(`/admin/companies/${companyTwo}?tab=buyers`);
    await admin.getByTestId('company-buyer-attach-form').locator('input[name="lookup"]').fill(buyer.email);
    await admin.getByTestId('company-buyer-attach-submit').click();
    await expect(admin.getByTestId(ERROR)).toContainText(/already belongs to a company/i);
    expect((await membershipOf(buyer.email))?.companyId).toBe(companyOne);
  });

  test('changes the role and the limits of a buyer, and the database follows', async () => {
    const buyerId = await customerIdOf(buyer.email);
    await admin.goto(`/admin/companies/${companyOne}?tab=buyers`);
    const row = admin.getByTestId(`company-buyer-${buyerId}`);
    await row.locator('select[name="role_code"]').selectOption('approver');
    await row.locator('input[name="order_limit"]').fill('500');
    await row.locator('input[name="period_limit"]').fill('2000');
    await row.locator('select[name="period"]').selectOption('month');
    await row.getByTestId(`company-buyer-save-${buyerId}`).click();
    await expect(admin.getByTestId(SUCCESS)).toBeVisible();
    expect(await membershipOf(buyer.email)).toMatchObject({ roleCode: 'approver', orderLimit: 500, periodLimit: 2000, period: 'month' });

    // The screen reads the values back.
    await admin.goto(`/admin/companies/${companyOne}?tab=buyers`);
    const reread = admin.getByTestId(`company-buyer-${buyerId}`);
    await expect(reread.locator('select[name="role_code"]')).toHaveValue('approver');
    await expect(reread.locator('input[name="order_limit"]')).toHaveValue(/^500/);
    await expect(reread.locator('select[name="period"]')).toHaveValue('month');

    // A limit that is not an amount is refused, the stored one is kept.
    await reread.locator('input[name="order_limit"]').fill('plenty');
    await reread.getByTestId(`company-buyer-save-${buyerId}`).click();
    await expect(admin.getByTestId(ERROR)).toBeVisible();
    expect((await membershipOf(buyer.email))?.orderLimit).toBe(500);

    // Back to the plain buyer, without limits.
    await admin.goto(`/admin/companies/${companyOne}?tab=buyers`);
    const plain = admin.getByTestId(`company-buyer-${buyerId}`);
    await plain.locator('select[name="role_code"]').selectOption('buyer');
    await plain.locator('input[name="order_limit"]').fill('');
    await plain.locator('input[name="period_limit"]').fill('');
    await plain.locator('select[name="period"]').selectOption('');
    await plain.getByTestId(`company-buyer-save-${buyerId}`).click();
    await expect(admin.getByTestId(SUCCESS)).toBeVisible();
    expect(await membershipOf(buyer.email)).toMatchObject({ roleCode: 'buyer', orderLimit: null, periodLimit: null, period: null });
  });

  test('refuses to detach the last administrator with a message, and accepts it once another one exists', async () => {
    const bossId = await customerIdOf(boss.email);
    await admin.goto(`/admin/companies/${companyOne}?tab=buyers`);
    await admin.getByTestId(`company-buyer-detach-${bossId}`).click();
    await expect(admin.getByTestId(ERROR)).toContainText(/without an administrator/i);
    expect((await membershipOf(boss.email))?.companyId, 'the last administrator stays').toBe(companyOne);

    // Demoting the last administrator is refused the same way.
    await admin.goto(`/admin/companies/${companyOne}?tab=buyers`);
    const row = admin.getByTestId(`company-buyer-${bossId}`);
    await row.locator('select[name="role_code"]').selectOption('buyer');
    await row.getByTestId(`company-buyer-save-${bossId}`).click();
    await expect(admin.getByTestId(ERROR)).toContainText(/without an administrator/i);
    expect((await membershipOf(boss.email))?.roleCode).toBe('company_admin');

    // With two administrators, one can leave.
    await attachBuyerInBackOffice(admin, companyTwo, secondBoss.email, 'company_admin');
    await attachBuyerInBackOffice(admin, companyTwo, secondBossBis.email, 'company_admin');
    const secondId = await customerIdOf(secondBoss.email);
    await admin.goto(`/admin/companies/${companyTwo}?tab=buyers`);
    await admin.getByTestId(`company-buyer-detach-${secondId}`).click();
    await expect(admin.getByTestId(SUCCESS)).toBeVisible();
    expect(await membershipOf(secondBoss.email)).toBeNull();
    expect(await buyerCountOf(companyTwo)).toBe(1);
  });

  test('the customer sheet shows the company, the role and the limits; a customer without company says so', async () => {
    const buyerId = await customerIdOf(buyer.email);
    // The title of a role is translated: the sheet says what the buyers tab offers for the same role.
    await admin.goto(`/admin/companies/${companyOne}?tab=buyers`);
    const roleTitle = ((await admin.getByTestId(`company-buyer-role-${buyerId}`).locator('option[value="buyer"]').innerText()) ?? '').trim();
    expect(roleTitle.length).toBeGreaterThan(0);

    await admin.goto(`/admin/customer/update?customer_id=${buyerId}`);
    const section = admin.getByTestId('customer-company-section');
    await expect(section).toBeVisible();
    await expect(admin.getByTestId('customer-company-link')).toHaveText(nameOne);
    await expect(admin.getByTestId('customer-company-role')).toHaveText(roleTitle);
    await Promise.all([admin.waitForURL(new RegExp(`/admin/companies/${companyOne}`)), admin.getByTestId('customer-company-link').click()]);

    const driftId = await customerIdOf(drifter.email);
    await admin.goto(`/admin/customer/update?customer_id=${driftId}`);
    await expect(admin.getByTestId('customer-company-none')).toBeVisible();
  });

  test('the customer list filters on the company and shows it in a column', async () => {
    await admin.goto('/admin/customers');
    await admin.locator('.bo-customer-filters__toggle').click();
    const filter = admin.getByTestId('customer-filter-company');
    await expect(filter).toBeVisible();

    // A company: only its buyers.
    await filter.getByTestId('customer-filter-company-id').selectOption(String(companyOne));
    await admin.locator('.bo-customer-filters button[type="submit"], form:has([data-testid="customer-filter-company"]) button[type="submit"]').first().click();
    await expect(admin).toHaveURL(new RegExp(`company_id=${companyOne}`));
    const rows = admin.getByTestId('datatable-customers-row');
    await expect(rows).toHaveCount(2);
    await expect(admin.getByTestId('customers-page')).toContainText(buyer.email);
    await expect(admin.getByTestId('customers-page')).toContainText(boss.email);
    await expect(admin.getByTestId('customers-page')).not.toContainText(drifter.email);
    await expect(rows.first()).toContainText(nameOne);

    // "Belongs to no company": the drifter is there, the buyers are not.
    await admin.goto(`/admin/customers?in_company=without&q=${encodeURIComponent(drifter.email)}`);
    await expect(admin.getByTestId('customers-page')).toContainText(drifter.email);
    await admin.goto(`/admin/customers?in_company=without&q=${encodeURIComponent(buyer.email)}`);
    await expect(admin.getByTestId('datatable-customers-row')).toHaveCount(0);
  });

  test('an order placed by a buyer shows its company and its buyer on the order sheet', async ({ browser }) => {
    test.setTimeout(240_000);
    const signed = await signedInAs(browser, buyer);
    try {
      await gotoProduct(signed.page, PRODUCTS.tina.slug);
      await addCurrentProductToCart(signed.page);
      await placeOrderWithCheque(signed.page, buyer.email);
    } finally {
      await signed.context.close();
    }
    const orders = await ordersOfCompany(companyOne);
    expect(orders, 'the order is attached to the company').toHaveLength(1);
    expect(orders[0].placedByCustomerId).toBe(await customerIdOf(buyer.email));

    await admin.goto(`/admin/order/update/${orders[0].id}`);
    // The card sits in the billing tab, with the addresses.
    await admin.getByTestId('order-tab-bill').click();
    const card = admin.getByTestId('order-company-card');
    await expect(card).toBeVisible();
    await expect(admin.getByTestId('order-company-link')).toHaveText(nameOne);
    await expect(admin.getByTestId('order-company-placed-by')).toContainText(`Cobuyer`);
    await Promise.all([admin.waitForURL(new RegExp(`/admin/companies/${companyOne}`)), admin.getByTestId('order-company-link').click()]);

    // The orders tab of the company lists it.
    await admin.goto(`/admin/companies/${companyOne}?tab=orders`);
    await expect(admin.getByTestId('company-page')).toContainText(orders[0].ref);
  });

  test('the roles of the buyers: the three installed roles are shown read only', async () => {
    await admin.goto('/admin/configuration');
    await expect(admin.locator('a[href$="/admin/configuration/company-roles"]').first()).toBeVisible();
    await admin.goto('/admin/configuration/company-roles');
    await expect(admin.getByTestId('company-roles-page')).toBeVisible();
    for (const code of ['company_admin', 'buyer', 'approver']) {
      const row = admin.getByTestId(`company-role-row-${code}`);
      await expect(row).toBeVisible();
      await expect(row.locator('.badge.text-bg-secondary'), `${code} is marked as installed`).toHaveCount(1);
      await expect(admin.getByTestId(`company-role-delete-${code}`), `${code} cannot be deleted`).toHaveCount(0);
    }

    // An installed role opens read only: no save button, every box disabled.
    await admin.getByTestId('company-role-row-buyer').locator('a[href*="/company-roles/"]').click();
    await expect(admin.getByTestId('company-role-shipped-note')).toBeVisible();
    await expect(admin.getByTestId('company-role-submit')).toHaveCount(0);
    const boxes = admin.locator('[data-testid^="company-role-permission-"]');
    expect(await boxes.count()).toBeGreaterThan(3);
    for (const box of await boxes.all()) await expect(box).toBeDisabled();

    // A role posted for a code that is installed is refused by the server too, whatever the screen shows.
    const before = await roleRows();
    const token = await admin.locator('meta[name="bo-token"]').getAttribute('content');
    const refused = await admin.request.post('/admin/configuration/company-roles/save', {
      form: { _token: token ?? '', role_id: before.buyer, title: 'Hacked', 'permissions[]': 'company.edit', edit_language_id: '1' },
      maxRedirects: 0,
    });
    expect([200, 302, 303, 403]).toContain(refused.status());
    expect((await roleRows()).buyerPermissions, 'the installed role is untouched').toBe(before.buyerPermissions);
  });

  test('a company that has orders cannot be deleted, the message offers to suspend it; one without orders can', async () => {
    await admin.goto(`/admin/companies/${companyOne}`);
    await admin.getByTestId('company-delete-submit').click();
    await expect(admin.getByTestId(ERROR)).toContainText(/has orders and cannot be deleted/i);
    expect(await companyIdByName(nameOne), 'the company is still there').toBe(companyOne);
    expect(await buyerCountOf(companyOne), 'its buyers too').toBe(2);

    await admin.goto(`/admin/companies/${companyTwo}`);
    await admin.getByTestId('company-delete-submit').click();
    await expect(admin.getByTestId(SUCCESS)).toBeVisible();
    await expect(admin).toHaveURL(/\/admin\/companies(\?|$)/);
    expect(await companyIdByName(nameTwo)).toBeNull();
    expect(await membershipOf(secondBossBis.email), 'the buyers of a deleted company are released').toBeNull();
  });
});

async function roleRows(): Promise<{ buyer: string; buyerPermissions: string }> {
  const { ddevMysql } = await import('../helpers/db');
  const out = await ddevMysql(`SELECT id, permissions FROM company_role WHERE code = 'buyer' AND company_id IS NULL`);
  const [id, permissions] = out.split('\t');
  return { buyer: id, buyerPermissions: permissions };
}
