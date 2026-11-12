import { spawnSync } from 'node:child_process';
import { expect, type Locator, type Page } from '@playwright/test';
import { DEMO, E2E_PREFIX, purgeCustomerGroupData } from './customer-group';
import { ddevMysql } from './db';
import { parseAmount } from './group-pricing';

/**
 * Data and readers of the price-list specs (price lists, reserved assortment, conditional
 * display of prices).
 *
 * Every price list the specs create carries the `e2e-pl-` code prefix; customers and groups come
 * from `helpers/customer-group.ts` (`e2e-cg-` prefix), so `purgePriceListData()` removes the lists
 * by their code and lets the customers' and groups' own purge cascade the assignments and the
 * assortment rules hanging off them. Rows are written with SQL, like the customer-group specs: the
 * ids are fresh, no cross-request cache holds an older answer for them. The only shop-wide setting
 * the specs touch, `price_display_mode`, is written through the console (`thelia:config`), which
 * drops the shared configuration snapshot the way the back-office does, and is put back to
 * `visible` by the purge whatever happened before.
 */
export const PL_PREFIX = 'e2e-pl-';

export type PriceDisplayMode = 'visible' | 'logged_in' | 'on_request';

/** Demo products the specs read, by the category they are filed in (see `DEMO`). */
export const PRODUCTS = {
  /** Armchairs, 395.00 untaxed, no promotion, stock 32. */
  owen: { slug: 'owen', productId: 5, pseId: 17, pseRef: 'PROD005-0', productRef: 'PROD005', price: 395, listing: '/armchairs.html' },
  /** Sofas, 638.00. */
  nigel: { slug: 'nigel', productId: 6, pseId: 23, pseRef: 'PROD006-0', productRef: 'PROD006', price: 638, listing: '/sofas.html' },
  /** Sofas, 1120.00. */
  heathcliff: { slug: 'heathcliff', productId: 7, pseId: 29, pseRef: 'PROD007-0', productRef: 'PROD007', price: 1120, listing: '/sofas.html' },
  /** Dining room > Chairs, 75.00. */
  tina: { slug: 'tina', productId: 11, pseId: 45, pseRef: 'PROD011-0', productRef: 'PROD011', price: 75, listing: '/chairs.html' },
  /** Dining room > Stools, on promotion (25.00 struck, 19.00 paid). */
  travis: { slug: 'travis', productId: 2, pseId: 6, pseRef: 'PROD002-0', productRef: 'PROD002', price: 25, listing: '/stools.html' },
} as const;

export const VAT = DEMO.vatRate;

/** What a visitor of the default group pays: the untaxed amount plus the VAT of the demo tax rule, to the cent. */
export function taxed(untaxed: number): number {
  return Math.round(untaxed * (1 + VAT) * 100) / 100;
}

const PROJECT_ROOT = new URL('../../../', import.meta.url).pathname;
const CONSOLE_COMMAND = process.env.CONSOLE_COMMAND?.split(' ') ?? ['ddev', 'exec', 'php', 'bin/console'];

function sqlString(value: string): string {
  return `'${value.replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}

function uniqueSuffix(): string {
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
}

function runConsole(args: string[]): string {
  const [command, ...base] = CONSOLE_COMMAND;
  const result = spawnSync(command, [...base, ...args, '--env=dev'], { cwd: PROJECT_ROOT, encoding: 'utf8' });
  if (0 !== result.status) {
    throw new Error(`console ${args.join(' ')} failed: ${result.stderr || result.stdout}`);
  }
  return result.stdout;
}

/** The shop setting, written through the console so the shared configuration snapshot is dropped. */
export function setShopPriceDisplayMode(mode: PriceDisplayMode): void {
  runConsole(['thelia:config', 'set', 'price_display_mode', mode]);
}

export function shopPriceDisplayMode(): string {
  const match = runConsole(['thelia:config', 'get', 'price_display_mode']).match(/Value:\s*(\S*)/);
  return match ? match[1] : '';
}

/** Overrides the setting for one product (`null` = follows the shop). */
export async function setProductPriceDisplayMode(productId: number, mode: PriceDisplayMode | null): Promise<void> {
  await ddevMysql(`UPDATE product SET price_display_mode = ${mode === null ? 'NULL' : sqlString(mode)} WHERE id = ${productId}`);
}

export type PriceList = { id: number; code: string };

export type PriceListOptions = {
  priority?: number;
  exclusive?: boolean;
  currencyId?: number;
  active?: boolean;
  title?: string;
};

export async function createPriceList(options: PriceListOptions = {}): Promise<PriceList> {
  const code = `${PL_PREFIX}${uniqueSuffix()}`;
  await ddevMysql(`
    INSERT INTO price_list (code, currency_id, priority, exclusive, active, created_at, updated_at)
    VALUES (${sqlString(code)}, ${options.currencyId ?? DEMO.currencyId}, ${options.priority ?? 100}, ${options.exclusive ? 1 : 0}, ${options.active === false ? 0 : 1}, NOW(), NOW());
  `);
  const id = Number(await ddevMysql(`SELECT id FROM price_list WHERE code = ${sqlString(code)}`));
  await ddevMysql(`INSERT INTO price_list_i18n (id, locale, title) VALUES (${id}, 'en_US', ${sqlString(options.title ?? `E2E list ${code}`)})`);
  return { id, code };
}

/** One line: a price (untaxed, in the currency of the list) from `minQuantity` units on. */
export async function addPriceListLine(listId: number, pseId: number, price: number, minQuantity = 1): Promise<void> {
  await ddevMysql(`
    INSERT INTO price_list_item (price_list_id, product_sale_elements_id, min_quantity, price, discount_percent, created_at, updated_at)
    VALUES (${listId}, ${pseId}, ${minQuantity}, ${price}, NULL, NOW(), NOW());
  `);
}

export async function assignPriceListToGroup(listId: number, groupId: number): Promise<void> {
  await ddevMysql(`INSERT INTO price_list_assignment (price_list_id, customer_group_id, customer_id) VALUES (${listId}, ${groupId}, NULL)`);
}

export async function assignPriceListToCustomer(listId: number, customerId: number): Promise<void> {
  await ddevMysql(`INSERT INTO price_list_assignment (price_list_id, customer_group_id, customer_id) VALUES (${listId}, NULL, ${customerId})`);
}

export async function priceListLineCount(listId: number): Promise<number> {
  return Number(await ddevMysql(`SELECT COUNT(*) FROM price_list_item WHERE price_list_id = ${listId}`));
}

export async function priceListIdByCode(code: string): Promise<number | null> {
  const out = await ddevMysql(`SELECT id FROM price_list WHERE code = ${sqlString(code)}`);
  return out === '' ? null : Number(out);
}

export type RestrictionTarget = { categoryId: number } | { brandId: number } | { productId: number };

/** An assortment rule of a group or of one customer: `allow` opens the target (and closes the rest), `deny` closes it. */
export async function createRestriction(scope: { groupId: number } | { customerId: number }, target: RestrictionTarget, mode: 'allow' | 'deny' = 'allow'): Promise<void> {
  const groupId = 'groupId' in scope ? scope.groupId : 'NULL';
  const customerId = 'customerId' in scope ? scope.customerId : 'NULL';
  const categoryId = 'categoryId' in target ? target.categoryId : 'NULL';
  const brandId = 'brandId' in target ? target.brandId : 'NULL';
  const productId = 'productId' in target ? target.productId : 'NULL';
  await ddevMysql(`
    INSERT INTO catalog_restriction (customer_group_id, customer_id, category_id, brand_id, product_id, mode)
    VALUES (${groupId}, ${customerId}, ${categoryId}, ${brandId}, ${productId}, ${sqlString(mode)});
  `);
}

/** What a customer's untaxed discount (percent, on the customer sheet) does to the catalog. */
export async function setCustomerDiscount(email: string, percent: number): Promise<void> {
  await ddevMysql(`UPDATE customer SET discount = ${percent} WHERE email = ${sqlString(email)}`);
}

/** Empties the cart of a customer (its lines), the cart itself staying the one the session refers to. */
export async function emptyCartOf(email: string): Promise<void> {
  await ddevMysql(`
    DELETE ci FROM cart_item ci JOIN cart ca ON ca.id = ci.cart_id JOIN customer cu ON cu.id = ca.customer_id
    WHERE cu.email = ${sqlString(email)};
  `);
}

export type StockSnapshot = Record<number, number>;

/** Stock of the sale elements an order will take from, to put back at the end of the run. */
export async function snapshotStock(pseIds: number[]): Promise<StockSnapshot> {
  const snapshot: StockSnapshot = {};
  for (const id of pseIds) snapshot[id] = Number(await ddevMysql(`SELECT quantity FROM product_sale_elements WHERE id = ${id}`));
  return snapshot;
}

export async function restoreStock(snapshot: StockSnapshot): Promise<void> {
  for (const [id, quantity] of Object.entries(snapshot)) {
    await ddevMysql(`UPDATE product_sale_elements SET quantity = ${quantity} WHERE id = ${id}`);
  }
}

/** Removes the price lists of the specs (lines, assignments and i18n go with them), customers and groups staying. */
export async function dropPriceLists(): Promise<void> {
  await ddevMysql(`
    DELETE FROM price_list_i18n WHERE id IN (SELECT id FROM price_list WHERE code LIKE '${PL_PREFIX}%');
    DELETE FROM price_list WHERE code LIKE '${PL_PREFIX}%';
  `);
}

/** Removes the assortment rules of the customers and groups of the specs. */
export async function dropRestrictions(): Promise<void> {
  await ddevMysql(`
    DELETE FROM catalog_restriction
      WHERE customer_id IN (SELECT id FROM customer WHERE email LIKE '${E2E_PREFIX}%')
         OR customer_group_id IN (SELECT id FROM customer_group WHERE code LIKE '${E2E_PREFIX}%');
  `);
}

/**
 * Removes everything the price-list specs created and puts the shop settings back: the lists (lines,
 * assignments and i18n cascade), the rules of the assortment of the e-mails and groups of the specs, the
 * per-product overrides of the demo products the specs read, then the customers, orders and groups (the
 * customer-group purge). The shop setting returns to `visible` first, so a run that broke off cannot
 * leave the shop masking its prices.
 */
export async function purgePriceListData(): Promise<void> {
  setShopPriceDisplayMode('visible');
  const productIds = Object.values(PRODUCTS).map((product) => product.productId).join(', ');
  await ddevMysql(`UPDATE product SET price_display_mode = NULL WHERE id IN (${productIds})`);
  await dropRestrictions();
  await dropPriceLists();
  await purgeCustomerGroupData();
}

/** Counts what the purge must leave at zero. */
export async function remainingPriceListRows(): Promise<Record<string, number>> {
  const rows: Record<string, string> = {
    lists: `SELECT COUNT(*) FROM price_list WHERE code LIKE '${PL_PREFIX}%'`,
    lines: `SELECT COUNT(*) FROM price_list_item WHERE price_list_id NOT IN (SELECT id FROM price_list)`,
    assignments: `SELECT COUNT(*) FROM price_list_assignment WHERE price_list_id NOT IN (SELECT id FROM price_list)`,
    restrictions: `SELECT COUNT(*) FROM catalog_restriction`,
    overrides: `SELECT COUNT(*) FROM product WHERE price_display_mode IS NOT NULL`,
    customers: `SELECT COUNT(*) FROM customer WHERE email LIKE '${E2E_PREFIX}%'`,
  };
  const result: Record<string, number> = {};
  for (const [name, sql] of Object.entries(rows)) result[name] = Number(await ddevMysql(sql));
  result.shopMode = shopPriceDisplayMode() === 'visible' ? 0 : 1;
  return result;
}

/** The sale-element price the theme paints on a product card of a listing, `null` when the card shows none. */
export async function cardAmount(page: Page, listingPath: string, slug: string): Promise<number | null> {
  await page.goto(listingPath);
  const card = page.locator('.ProductCard').filter({ has: page.locator(`a.ProductCard-title[href$="/${slug}.html"]`) }).first();
  await expect(card).toBeVisible();
  const price = card.locator('.ProductCard-price span[dir="ltr"]').first();
  return (await price.count()) === 0 ? null : parseAmount(await price.innerText());
}

export function pageAmountBlock(page: Page): Locator {
  return page.locator('.PseSelector-price').first();
}

/** The price of the product page (the one charged, not the struck catalog one), `null` when the sheet shows none. */
export async function pageAmount(page: Page, slug: string): Promise<number | null> {
  await page.goto(`/${slug}.html`);
  const block = pageAmountBlock(page);
  await expect(block).toBeVisible();
  const price = block.locator('strong span[dir="ltr"]').first();
  return (await price.count()) === 0 ? null : parseAmount(await price.innerText());
}

export const ADD_TO_CART = 'form[name="thelia_cart_add"] button[type="submit"]';

/** The unit price printed on the cart line of the product whose title matches. */
export async function cartLineAmount(page: Page, title: RegExp): Promise<number> {
  const line = page.locator('.CartItem').filter({ hasText: title }).first();
  await expect(line).toBeVisible();
  return parseAmount(await line.locator('.CartItem-price span[dir="ltr"]').first().innerText());
}

/** The price block of a product page, by its state (`price`, `hidden`, `on_request`, `unavailable`). */
export function priceState(page: Page): Locator {
  return page.locator('[data-price-state]').first();
}
