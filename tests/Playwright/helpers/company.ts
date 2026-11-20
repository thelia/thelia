import { expect, test, type Browser, type BrowserContext, type Page } from '@playwright/test';
import { addCurrentProductToCart, gotoProduct } from './cart';
import { acceptMandatoryConsents, clickNextOrder, selectFirstDeliveryMethod, selectFirstInvoiceAddress, selectPaymentByLabel } from './checkout';
import { ddevMysql } from './db';
import { login, newCustomer, registerCustomer, type Customer } from './customer';
import { emptyCartOf } from './price-list';

/**
 * Data and readers of the company specs.
 *
 * Every company the specs create is named with the `e2e-co-` prefix and every account they
 * register has an e-mail address with that prefix, so `purgeCompanyData()` removes them without
 * touching the demo data. Companies and buyers are made through the screens of the back-office
 * (the way a merchant makes them), what a screen saved is read back from the database.
 */
export const COMPANY_PREFIX = 'e2e-co-';

/** A French SIRET and the VAT number built on the same SIREN, both with a valid checksum: the tunnel checks them on the billing address. */
export const LEGAL_IDENTIFIERS = { siret: '73282932000074', vatNumber: 'FR44732829320' } as const;

export type RoleCode = 'company_admin' | 'buyer' | 'approver';

const sqlString = (value: string): string => `'${value.replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;

export function uniqueSuffix(): string {
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
}

export function newCompanyName(label: string): string {
  return `${COMPANY_PREFIX}${label}-${uniqueSuffix()}`;
}

export function newCompanyCustomer(label: string): Customer {
  return newCustomer({
    firstname: `Co${label}`,
    lastname: 'Buyer',
    email: `${COMPANY_PREFIX}${label}-${uniqueSuffix()}@example.test`,
  });
}

/** Registers an account through the public form (address included) and signs it out again. */
export async function registerBuyerAccount(browser: Browser, label: string): Promise<Customer> {
  const customer = newCompanyCustomer(label);
  const context = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US' });
  const page = await context.newPage();
  await registerCustomer(page, customer);
  await context.close();
  return customer;
}

/** A browser context signed in as `customer`, in the viewport of the running project unless one is given. */
export async function signedInAs(
  browser: Browser,
  customer: Customer,
  viewport: { width: number; height: number } = test.info().project.use.viewport ?? { width: 1440, height: 900 },
): Promise<{ context: BrowserContext; page: Page }> {
  const context = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport });
  const page = await context.newPage();
  await login(page, customer.email, customer.password);
  return { context, page };
}

export async function customerIdOf(email: string): Promise<number> {
  return Number(await ddevMysql(`SELECT id FROM customer WHERE email = ${sqlString(email)}`));
}

export async function companyIdByName(name: string): Promise<number | null> {
  const out = await ddevMysql(`SELECT id FROM company WHERE name = ${sqlString(name)}`);
  return '' === out ? null : Number(out);
}

export type StoredMembership = {
  companyId: number;
  roleCode: string;
  orderLimit: number | null;
  periodLimit: number | null;
  period: string | null;
};

/** What the database holds of the membership of an account, or null for an account without a company. */
export async function membershipOf(email: string): Promise<StoredMembership | null> {
  const out = await ddevMysql(`
    SELECT cc.company_id, r.code, IFNULL(cc.order_limit, ''), IFNULL(cc.period_limit, ''), IFNULL(cc.period, '')
    FROM company_customer cc
    JOIN customer c ON c.id = cc.customer_id
    JOIN company_role r ON r.id = cc.company_role_id
    WHERE c.email = ${sqlString(email)}`);
  if ('' === out) return null;
  const [companyId, roleCode, orderLimit, periodLimit, period] = out.split('\t');
  return {
    companyId: Number(companyId),
    roleCode,
    orderLimit: orderLimit ? Number(orderLimit) : null,
    periodLimit: periodLimit ? Number(periodLimit) : null,
    period: period || null,
  };
}

export async function companyIsActive(companyId: number): Promise<boolean> {
  return '1' === (await ddevMysql(`SELECT active FROM company WHERE id = ${companyId}`));
}

export async function buyerCountOf(companyId: number): Promise<number> {
  return Number(await ddevMysql(`SELECT COUNT(*) FROM company_customer WHERE company_id = ${companyId}`));
}

export type StoredOrder = { id: number; ref: string; companyId: number | null; placedByCustomerId: number | null };

/** The orders of an account, oldest first, with the company and the buyer the order is attached to. */
export async function ordersOf(email: string): Promise<StoredOrder[]> {
  const out = await ddevMysql(`
    SELECT o.id, o.ref, IFNULL(o.company_id, ''), IFNULL(o.placed_by_customer_id, '')
    FROM \`order\` o JOIN customer c ON c.id = o.customer_id
    WHERE c.email = ${sqlString(email)} ORDER BY o.id`);
  return out
    .split('\n')
    .filter(Boolean)
    .map((line) => {
      const [id, ref, companyId, placedBy] = line.split('\t');
      return { id: Number(id), ref, companyId: companyId ? Number(companyId) : null, placedByCustomerId: placedBy ? Number(placedBy) : null };
    });
}

/** Orders attached to a company, whoever placed them. */
export async function ordersOfCompany(companyId: number): Promise<{ id: number; ref: string; placedByCustomerId: number | null }[]> {
  const out = await ddevMysql(`SELECT id, ref, IFNULL(placed_by_customer_id, '') FROM \`order\` WHERE company_id = ${companyId} ORDER BY id`);
  return out
    .split('\n')
    .filter(Boolean)
    .map((line) => {
      const [id, ref, placedBy] = line.split('\t');
      return { id: Number(id), ref, placedByCustomerId: placedBy ? Number(placedBy) : null };
    });
}

/** The company flag of an address, null for a personal one. */
export async function addressCompanyOf(customerEmail: string, label: string): Promise<number | null | undefined> {
  const out = await ddevMysql(`
    SELECT IFNULL(a.company_id, 'NULL') FROM address a JOIN customer c ON c.id = a.customer_id
    WHERE c.email = ${sqlString(customerEmail)} AND a.label = ${sqlString(label)} ORDER BY a.id DESC LIMIT 1`);
  if ('' === out) return undefined;
  return 'NULL' === out ? null : Number(out);
}

/** Highest cart id before the specs browsed; set by the first purge of the worker. */
let cartWatermark: number | null = null;

/**
 * Removes everything the specs created: orders and their addresses, accounts (memberships,
 * addresses and carts go with them), companies (roles of their own, invitations used). Idempotent.
 */
export async function purgeCompanyData(): Promise<void> {
  const likeEmail = `'${COMPANY_PREFIX}%'`;
  const anonymousCarts = null === cartWatermark ? '' : `DELETE FROM cart WHERE customer_id IS NULL AND id > ${cartWatermark};`;
  if (null === cartWatermark) cartWatermark = Number(await ddevMysql('SELECT IFNULL(MAX(id), 0) FROM cart'));
  await ddevMysql(`
    ${anonymousCarts}
    CREATE TEMPORARY TABLE tmp_e2e_company_order_address AS
      SELECT o.invoice_order_address_id AS id FROM \`order\` o
        WHERE o.customer_id IN (SELECT id FROM customer WHERE email LIKE ${likeEmail}) OR o.company_id IN (SELECT id FROM company WHERE name LIKE ${likeEmail})
      UNION SELECT o.delivery_order_address_id FROM \`order\` o
        WHERE o.customer_id IN (SELECT id FROM customer WHERE email LIKE ${likeEmail}) OR o.company_id IN (SELECT id FROM company WHERE name LIKE ${likeEmail});
    DELETE FROM \`order\` WHERE customer_id IN (SELECT id FROM customer WHERE email LIKE ${likeEmail}) OR company_id IN (SELECT id FROM company WHERE name LIKE ${likeEmail});
    DELETE FROM order_address WHERE id IN (SELECT id FROM tmp_e2e_company_order_address);
    DELETE FROM customer WHERE email LIKE ${likeEmail};
    DELETE FROM company WHERE name LIKE ${likeEmail};
  `);
}

/** Counts what the purge must leave at zero. */
export async function remainingCompanyRows(): Promise<Record<string, number>> {
  const likeEmail = `'${COMPANY_PREFIX}%'`;
  const rows: Record<string, string> = {
    companies: `SELECT COUNT(*) FROM company WHERE name LIKE ${likeEmail}`,
    customers: `SELECT COUNT(*) FROM customer WHERE email LIKE ${likeEmail}`,
    memberships: 'SELECT COUNT(*) FROM company_customer WHERE company_id NOT IN (SELECT id FROM company) OR customer_id NOT IN (SELECT id FROM customer)',
    ownRoles: 'SELECT COUNT(*) FROM company_role WHERE company_id IS NOT NULL AND company_id NOT IN (SELECT id FROM company)',
  };
  const result: Record<string, number> = {};
  for (const [name, sql] of Object.entries(rows)) result[name] = Number(await ddevMysql(sql));
  return result;
}

// ---------------------------------------------------------------- back-office screens

export type Limits = { orderLimit?: string; periodLimit?: string; period?: 'week' | 'month' | 'year' | '' };

/** The "Add a company" dialog of the company list; returns the id of the company it made. */
export async function createCompanyInBackOffice(
  page: Page,
  name: string,
  identifiers: { siret?: string; vatNumber?: string } = {},
): Promise<number> {
  await page.goto('/admin/companies');
  await page.getByTestId('company-create-button').first().click();
  const dialog = page.getByTestId('company-create');
  await expect(dialog).toBeVisible();
  await dialog.locator('input[name="name"]').fill(name);
  if (identifiers.siret) await dialog.locator('input[name="siret"]').fill(identifiers.siret);
  if (identifiers.vatNumber) await dialog.locator('input[name="vat_number"]').fill(identifiers.vatNumber);
  await Promise.all([page.waitForURL(/\/admin\/companies\/\d+/), dialog.getByTestId('company-create-submit').click()]);
  await expect.poll(() => companyIdByName(name), { message: `the company ${name} exists in the database` }).not.toBeNull();
  return (await companyIdByName(name)) as number;
}

/** Attaches an existing account to a company from the buyers tab, by its exact e-mail address. */
export async function attachBuyerInBackOffice(page: Page, companyId: number, email: string, role: RoleCode, limits: Limits = {}): Promise<void> {
  await page.goto(`/admin/companies/${companyId}?tab=buyers`);
  const form = page.getByTestId('company-buyer-attach-form');
  await form.locator('input[name="lookup"]').fill(email);
  await form.locator('select[name="role_code"]').selectOption(role);
  if (limits.orderLimit) await form.locator('input[name="order_limit"]').fill(limits.orderLimit);
  if (limits.periodLimit) await form.locator('input[name="period_limit"]').fill(limits.periodLimit);
  if (limits.period) await form.locator('select[name="period"]').selectOption(limits.period);
  await Promise.all([page.waitForURL(/tab=buyers/), form.getByTestId('company-buyer-attach-submit').click()]);
  await expect(page.getByTestId('bo-flash-success')).toBeVisible();
}

/** A company with its buyers, made through the back-office. */
export async function setUpCompany(
  adminPage: Page,
  name: string,
  buyers: { customer: Customer; role: RoleCode; limits?: Limits }[],
  identifiers: { siret?: string; vatNumber?: string } = {},
): Promise<number> {
  const companyId = await createCompanyInBackOffice(adminPage, name, identifiers);
  for (const { customer, role, limits } of buyers) {
    await attachBuyerInBackOffice(adminPage, companyId, customer.email, role, limits);
    expect((await membershipOf(customer.email))?.companyId, `${customer.email} is attached`).toBe(companyId);
  }
  return companyId;
}

export async function setCompanyStateInBackOffice(page: Page, companyId: number, state: 'suspend' | 'reactivate'): Promise<void> {
  await page.goto(`/admin/companies/${companyId}`);
  await Promise.all([page.waitForURL(/\/admin\/companies\/\d+/), page.getByTestId(`company-${state}`).click()]);
  await expect(page.getByTestId('bo-flash-success')).toBeVisible();
}

// ---------------------------------------------------------------- shop journeys

export async function addressIdOf(ownerEmail: string, label: string): Promise<number> {
  const out = await ddevMysql(`
    SELECT a.id FROM address a JOIN customer c ON c.id = a.customer_id
    WHERE c.email = ${sqlString(ownerEmail)} AND a.label = ${sqlString(label)} ORDER BY a.id DESC LIMIT 1`);
  return Number(out);
}

/** Cancels an order the way the merchant does when the customer withdraws, so that it stops counting against the limits. */
export async function cancelOrder(orderId: number): Promise<void> {
  await ddevMysql(`UPDATE \`order\` SET status_id = (SELECT id FROM order_status WHERE code = 'canceled') WHERE id = ${orderId}`);
}

export type TunnelOutcome = { placed: true; ref: string } | { placed: false; message: string };

/**
 * A signed-in buyer puts `quantity` of a product in a fresh cart and walks the tunnel up to the
 * payment by cheque. The tunnel either takes the order, or sends the buyer back with a message:
 * that message is returned. `deliveryAddressId` picks one of the addresses of the delivery step
 * (the default address otherwise). `atPayment` runs once the payment step shows; answering 'stop'
 * leaves the tunnel there without ordering.
 */
export async function orderThroughTunnel(
  page: Page,
  email: string,
  product: { slug: string },
  quantity = 1,
  deliveryAddressId?: number,
  atPayment?: (page: Page) => Promise<'stop' | void>,
): Promise<TunnelOutcome> {
  await emptyCartOf(email);
  await gotoProduct(page, product.slug);
  for (let unit = 0; unit < quantity; unit++) await addCurrentProductToCart(page);
  await page.goto('/checkout/cart');
  await clickNextOrder(page, /\/checkout\/delivery/);

  if (undefined !== deliveryAddressId) {
    await page.locator(`button[data-live-event-param="SET_DELIVERY_ORDER_ADDRESS_ID"][data-live-address-id-param="${deliveryAddressId}"]`).click();
    await page.waitForLoadState('networkidle').catch(() => {});
    await expect(page.locator(`button.RadioButton.checked[data-live-address-id-param="${deliveryAddressId}"]`)).toBeVisible();
  }
  await selectFirstDeliveryMethod(page);

  const before = (await ordersOf(email)).length;
  const orderLink = page.locator('a:has-text("Order")').last();
  await expect(orderLink).toBeVisible({ timeout: 12_000 });
  await orderLink.click();

  // Either the payment step shows, or the tunnel comes back to the delivery step with the reason.
  const outcome = await Promise.race([
    page.locator('.PaymentCard').first().waitFor({ timeout: 25_000 }).then(() => 'payment' as const),
    page.locator('.SnackBar--error').first().waitFor({ timeout: 25_000 }).then(() => 'refused' as const),
  ]);
  if ('refused' === outcome) {
    return { placed: false, message: (await page.locator('.SnackBar--error').first().innerText()).replace(/\s+/g, ' ').trim() };
  }

  if ('stop' === (await atPayment?.(page))) return { placed: false, message: 'left at the payment step' };

  await selectFirstInvoiceAddress(page);
  await selectPaymentByLabel(page, /cheque|check/i);
  await acceptMandatoryConsents(page);
  const pay = page.locator('a:has-text("Order")').last();
  await expect(pay).toBeVisible({ timeout: 12_000 });
  await Promise.all([page.waitForURL((url) => !/\/checkout\/payment/.test(url.pathname), { timeout: 30_000 }), pay.click()]);
  await expect.poll(async () => (await ordersOf(email)).length, { timeout: 20_000, message: 'the order must exist in the database' }).toBe(before + 1);
  const orders = await ordersOf(email);
  return { placed: true, ref: orders[orders.length - 1].ref };
}
