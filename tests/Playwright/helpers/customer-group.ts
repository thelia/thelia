import { test, type Browser, type BrowserContext, type Page } from '@playwright/test';
import { ddevMysql } from './db';
import { newCustomer, registerCustomer, login, type Customer } from './customer';

/**
 * Data of the customer-group specs.
 *
 * Every row the specs create carries the `e2e-cg-` prefix (group codes, customer e-mails) so
 * `purgeCustomerGroupData()` can remove them without ever touching the demo data or the groups
 * a person made by hand (`default`, `pro`). Rows are written with SQL: the groups are fresh
 * (a new id never reused by the auto-increment), so no cross-request cache holds an older
 * answer for them.
 */
export const E2E_PREFIX = 'e2e-cg-';

/** Demo catalog (bin/install --with-demo): P = Living Room > S = Sofas ; Armchairs is a sibling of S. */
export const DEMO = {
  categories: { livingRoom: 1, diningRoom: 2, chairs: 3, stools: 4, armchairs: 5, sofas: 6 },
  /** Sofa, no promotion, 638.00 untaxed, filed in Sofas only. */
  sofa: { slug: 'nigel', productId: 6, pseId: 23, price: 638 },
  /** Armchair, no promotion, 395.00 untaxed, filed in Armchairs only. */
  armchair: { slug: 'owen', productId: 5, pseId: 17, price: 395 },
  /** Stool on promotion: 25.00 struck, 19.00 paid, untaxed, filed in Stools only. */
  promoStool: { slug: 'travis', productId: 2, pseId: 6, price: 25, promoPrice: 19 },
  /** Chair filed in Armchairs only (no Sofas). */
  otherCategoryProduct: { slug: 'stacy', productId: 3 },
  vatRate: 0.2,
  currencyId: 1,
} as const;

export type GroupOptions = {
  discount?: number;
  /** false = prices shown without taxes. */
  taxed?: boolean;
  categoryIds?: number[];
  paymentModuleCodes?: string[];
  registrationToken?: string | null;
  title?: string;
};

export type CustomerGroup = { id: number; code: string; title: string };

const sqlString = (value: string): string => `'${value.replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;

function uniqueSuffix(): string {
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
}

export async function createGroup(options: GroupOptions = {}): Promise<CustomerGroup> {
  const code = `${E2E_PREFIX}${uniqueSuffix()}`;
  const title = options.title ?? `E2E group ${code}`;
  const discount = options.discount ?? 0;
  const taxed = options.taxed === false ? 0 : 1;
  const restrictsCategories = options.categoryIds && options.categoryIds.length > 0 ? 1 : 0;
  const restrictsPayments = options.paymentModuleCodes && options.paymentModuleCodes.length > 0 ? 1 : 0;
  const token = options.registrationToken ? sqlString(options.registrationToken) : 'NULL';

  await ddevMysql(`
    INSERT INTO customer_group (code, position, is_default, discount, display_taxed_price,
      restricts_categories, restricts_payment_modules, registration_token, created_at, updated_at)
    VALUES (${sqlString(code)}, 99, 0, ${discount}, ${taxed}, ${restrictsCategories}, ${restrictsPayments}, ${token}, NOW(), NOW());
  `);
  const id = Number(await ddevMysql(`SELECT id FROM customer_group WHERE code = ${sqlString(code)}`));
  await ddevMysql(`INSERT INTO customer_group_i18n (id, locale, title) VALUES (${id}, 'en_US', ${sqlString(title)})`);
  for (const categoryId of options.categoryIds ?? []) {
    await ddevMysql(`INSERT INTO customer_group_category (customer_group_id, category_id) VALUES (${id}, ${categoryId})`);
  }
  for (const moduleCode of options.paymentModuleCodes ?? []) {
    await ddevMysql(`
      INSERT INTO customer_group_payment_module (customer_group_id, module_id)
      SELECT ${id}, id FROM module WHERE code = ${sqlString(moduleCode)}`);
  }
  return { id, code, title };
}

/** A root category of the specs' own (never a demo one), visible, with an English title carrying the prefix. */
export async function createCategory(titleSuffix: string): Promise<number> {
  await ddevMysql(`
    INSERT INTO category (parent, visible, position, default_template_id, created_at, updated_at, version)
    VALUES (0, 1, 99, NULL, NOW(), NOW(), 1);
  `);
  const id = Number(await ddevMysql('SELECT MAX(id) FROM category'));
  await ddevMysql(`INSERT INTO category_i18n (id, locale, title) VALUES (${id}, 'en_US', ${sqlString(`${E2E_PREFIX}${titleSuffix}`)})`);
  return id;
}

/** Deletes a category the way the database does when the shop drops it: the links of the groups go with it (ON DELETE CASCADE). */
export async function deleteCategory(categoryId: number): Promise<void> {
  await ddevMysql(`DELETE FROM category WHERE id = ${categoryId}`);
}

export async function setGroupPrice(groupId: number, pseId: number, price: number | null, promoPrice: number | null = null): Promise<void> {
  await ddevMysql(`
    INSERT INTO customer_group_product_price (customer_group_id, product_sale_elements_id, currency_id, price, promo_price, from_default_currency, created_at, updated_at)
    VALUES (${groupId}, ${pseId}, ${DEMO.currencyId}, ${price ?? 'NULL'}, ${promoPrice ?? 'NULL'}, 0, NOW(), NOW())`);
}

export async function groupIdOfCustomer(email: string): Promise<number | null> {
  const out = await ddevMysql(`SELECT customer_group_id FROM customer WHERE email = ${sqlString(email)}`);
  return out === '' ? null : Number(out);
}

export async function moveCustomerToGroup(email: string, groupId: number): Promise<void> {
  await ddevMysql(`UPDATE customer SET customer_group_id = ${groupId} WHERE email = ${sqlString(email)}`);
}

export async function defaultGroupId(): Promise<number> {
  return Number(await ddevMysql('SELECT id FROM customer_group WHERE is_default = 1'));
}

export async function customerIdOf(email: string): Promise<number> {
  return Number(await ddevMysql(`SELECT id FROM customer WHERE email = ${sqlString(email)}`));
}

export function newGroupCustomer(): Customer {
  return newCustomer({ email: `${E2E_PREFIX}${uniqueSuffix()}@example.test` });
}

/**
 * Registers a customer through the public form (a real account, address included), then moves it
 * into `groupId` with SQL. The group is read back from the database on every request, so the move
 * is seen without signing in again.
 */
export async function registerMember(browser: Browser, groupId: number | null): Promise<Customer> {
  const customer = newGroupCustomer();
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();
  await registerCustomer(page, customer);
  await context.close();
  if (groupId !== null) await moveCustomerToGroup(customer.email, groupId);
  return customer;
}

/** A browser context already signed in as `customer`, in the viewport of the running project (1440 desktop, 390 mobile). */
export async function signedInContext(browser: Browser, customer: Customer): Promise<{ context: BrowserContext; page: Page }> {
  const viewport = test.info().project.use.viewport ?? { width: 1440, height: 900 };
  const context = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport });
  const page = await context.newPage();
  await login(page, customer.email, customer.password);
  return { context, page };
}

/** Highest cart id before the specs of this worker browsed; set by its first purge. */
let cartWatermark: number | null = null;

/**
 * Removes everything the specs created: orders (and their addresses), customers (addresses, carts,
 * versions cascade), groups (their lists, i18n and group prices cascade). Idempotent.
 */
export async function purgeCustomerGroupData(): Promise<void> {
  const likeEmail = `'${E2E_PREFIX}%'`;
  // A visitor's cart carries nothing the prefix could mark: the anonymous carts created since the
  // first purge of this worker (the start of the run, before any spec has browsed) are the specs' own.
  const anonymousCarts = cartWatermark === null ? '' : `DELETE FROM cart WHERE customer_id IS NULL AND id > ${cartWatermark};`;
  if (cartWatermark === null) cartWatermark = Number(await ddevMysql('SELECT IFNULL(MAX(id), 0) FROM cart'));
  await ddevMysql(`
    ${anonymousCarts}
    CREATE TEMPORARY TABLE tmp_e2e_order_address AS
      SELECT o.invoice_order_address_id AS id FROM \`order\` o JOIN customer c ON c.id = o.customer_id WHERE c.email LIKE ${likeEmail}
      UNION SELECT o.delivery_order_address_id FROM \`order\` o JOIN customer c ON c.id = o.customer_id WHERE c.email LIKE ${likeEmail};
    DELETE o FROM \`order\` o JOIN customer c ON c.id = o.customer_id WHERE c.email LIKE ${likeEmail};
    DELETE FROM order_address WHERE id IN (SELECT id FROM tmp_e2e_order_address);
    DELETE FROM customer WHERE email LIKE ${likeEmail};
    DELETE FROM customer WHERE customer_group_id IN (SELECT id FROM customer_group WHERE code LIKE ${likeEmail});
    DELETE FROM customer_group WHERE code LIKE ${likeEmail} AND is_default = 0;
    DELETE FROM category WHERE id IN (SELECT id FROM category_i18n WHERE title LIKE ${likeEmail});
  `);
}

/** Counts what the purge must leave at zero (rows named by the prefix, or hanging off them). */
export async function remainingE2eRows(): Promise<Record<string, number>> {
  const likeEmail = `'${E2E_PREFIX}%'`;
  const rows: Record<string, string> = {
    groups: `SELECT COUNT(*) FROM customer_group WHERE code LIKE ${likeEmail}`,
    customers: `SELECT COUNT(*) FROM customer WHERE email LIKE ${likeEmail}`,
    groupPrices: `SELECT COUNT(*) FROM customer_group_product_price WHERE customer_group_id NOT IN (SELECT id FROM customer_group)`,
    groupCategories: `SELECT COUNT(*) FROM customer_group_category WHERE customer_group_id NOT IN (SELECT id FROM customer_group)`,
    categories: `SELECT COUNT(*) FROM category_i18n WHERE title LIKE ${likeEmail}`,
    groupModules: `SELECT COUNT(*) FROM customer_group_payment_module WHERE customer_group_id NOT IN (SELECT id FROM customer_group)`,
  };
  const result: Record<string, number> = {};
  for (const [name, sql] of Object.entries(rows)) result[name] = Number(await ddevMysql(sql));
  return result;
}

export async function setRegistrationToken(groupId: number, token: string | null): Promise<void> {
  await ddevMysql(`UPDATE customer_group SET registration_token = ${token === null ? 'NULL' : sqlString(token)} WHERE id = ${groupId}`);
}

export async function registrationTokenOf(groupId: number): Promise<string | null> {
  const out = await ddevMysql(`SELECT registration_token FROM customer_group WHERE id = ${groupId}`);
  return out === '' || out === 'NULL' ? null : out;
}

/** Facts about a group as the database holds them, for the back-office specs to verify what a screen saved. */
export type StoredGroup = {
  discount: number;
  displayTaxedPrice: boolean;
  restrictsCategories: boolean;
  restrictsPaymentModules: boolean;
  categoryIds: number[];
  paymentModuleCodes: string[];
  registrationToken: string | null;
};

export async function storedGroup(groupId: number): Promise<StoredGroup> {
  const [discount, taxed, restrictsCategories, restrictsPayments, token] = (
    await ddevMysql(`SELECT discount, display_taxed_price, restricts_categories, restricts_payment_modules, IFNULL(registration_token, '') FROM customer_group WHERE id = ${groupId}`)
  ).split('\t');
  const categoryIds = (await ddevMysql(`SELECT category_id FROM customer_group_category WHERE customer_group_id = ${groupId} ORDER BY category_id`)).split('\n').filter(Boolean).map(Number);
  const paymentModuleCodes = (
    await ddevMysql(`SELECT m.code FROM customer_group_payment_module l JOIN module m ON m.id = l.module_id WHERE l.customer_group_id = ${groupId} ORDER BY m.code`)
  ).split('\n').filter(Boolean);
  return {
    discount: Number(discount),
    displayTaxedPrice: taxed === '1',
    restrictsCategories: restrictsCategories === '1',
    restrictsPaymentModules: restrictsPayments === '1',
    categoryIds,
    paymentModuleCodes,
    // `ddevMysql` trims the output: an empty last column is missing, not an empty string.
    registrationToken: token === undefined || token === '' ? null : token,
  };
}

export async function groupIdByCode(code: string): Promise<number | null> {
  const out = await ddevMysql(`SELECT id FROM customer_group WHERE code = ${sqlString(code)}`);
  return out === '' ? null : Number(out);
}

export async function groupPriceOf(groupId: number, pseId: number): Promise<number | null> {
  const out = await ddevMysql(`SELECT IFNULL(price, '') FROM customer_group_product_price WHERE customer_group_id = ${groupId} AND product_sale_elements_id = ${pseId} AND currency_id = ${DEMO.currencyId}`);
  return out === '' ? null : Number(out);
}

export function newGroupCode(): string {
  return `${E2E_PREFIX}${uniqueSuffix()}`;
}
