import { spawnSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { expect, type Browser, type BrowserContext, type Page } from '@playwright/test';
import { addCurrentProductToCart, gotoProduct, setQuantity } from './cart';
import { ddevMysql } from './db';
import { login, newCustomer, registerCustomer, type Customer } from './customer';
import { emptyCartOf } from './price-list';

/**
 * Data and readers of the quote specs.
 *
 * Every account the specs register has an e-mail address with the `e2e-qt-` prefix, so
 * `purgeQuoteData()` removes the accounts (their quotes, revisions and carts go with them), the
 * orders and the order addresses they made, without touching the demo data. The only shop-wide
 * setting the specs touch, `quote_mode`, is written through the console (`thelia:config`, which
 * drops the shared configuration snapshot the way the back-office does) and put back by the specs.
 * What a screen saved is read back from the database.
 */
export const QUOTE_PREFIX = 'e2e-qt-';

export type QuoteMode = 'disabled' | 'alongside' | 'only';

const PROJECT_ROOT = new URL('../../../', import.meta.url).pathname;
const CONSOLE_COMMAND = process.env.CONSOLE_COMMAND?.split(' ') ?? ['ddev', 'exec', 'php', 'bin/console'];

/** Demo products, 20 % VAT, untaxed prices. */
export const QUOTE_PRODUCTS = {
  chair: { slug: 'tina', productRef: 'PROD011', pseRef: 'PROD011-0', search: 'Tina', price: 75 },
  armchair: { slug: 'owen', productRef: 'PROD005', pseRef: 'PROD005-0', search: 'Owen', price: 395 },
  sofa: { slug: 'nigel', productRef: 'PROD006', pseRef: 'PROD006-0', search: 'Nigel', price: 638 },
} as const;

export const VAT_RATE = 0.2;

const sqlString = (value: string): string => `'${value.replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;

export function uniqueSuffix(): string {
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`;
}

function runConsole(args: string[]): string {
  const [command, ...base] = CONSOLE_COMMAND;
  const result = spawnSync(command, [...base, ...args, '--env=dev'], { cwd: PROJECT_ROOT, encoding: 'utf8' });
  if (0 !== result.status) throw new Error(`console ${args.join(' ')} failed: ${result.stderr || result.stdout}`);
  return result.stdout;
}

// ---------------------------------------------------------------- the shop setting

export function readQuoteMode(): QuoteMode {
  const match = runConsole(['thelia:config', 'get', 'quote_mode']).match(/Value:\s*(\S*)/);
  return (match ? match[1] : 'disabled') as QuoteMode;
}

export function setQuoteMode(mode: QuoteMode): void {
  runConsole(['thelia:config', 'set', 'quote_mode', mode]);
}

/** Every row of the configuration table, as the database prints it, by name. */
export async function configSnapshot(): Promise<Map<string, string>> {
  const out = await ddevMysql('SELECT name, value FROM config');
  return new Map(
    out
      .split('\n')
      .filter(Boolean)
      .map((line) => {
        const [name, ...value] = line.split('\t');
        return [name, value.join('\t')] as [string, string];
      }),
  );
}

/**
 * Puts the configuration table back to a snapshot as far as the console can: the rows a save of the
 * store screen added are deleted. Returns the rows whose value differs, which the console cannot
 * rewrite faithfully (the caller reports them).
 */
export async function removeAddedConfigRows(before: Map<string, string>): Promise<{ added: string[]; changed: string[] }> {
  const after = await configSnapshot();
  const added = [...after.keys()].filter((name) => !before.has(name));
  const changed = [...after.keys()].filter((name) => before.has(name) && before.get(name) !== after.get(name));
  for (const name of added) runConsole(['thelia:config', 'delete', name]);
  return { added, changed };
}

/**
 * The "Quotes" card of the store configuration screen of the back-office. The dev shop has no zip
 * code in its store settings, which the form demands (the browser, then the server): the screen is
 * given one, and the caller puts the configuration back with `removeAddedConfigRows`.
 */
export async function setQuoteModeInBackOffice(page: Page, mode: QuoteMode): Promise<void> {
  await page.goto('/admin/configuration/store');
  await page.getByTestId('config-store-quote-mode').selectOption(mode);
  const zipcode = page.locator('#thelia_configuration_store_store_zipcode');
  if ('' === (await zipcode.inputValue())) await zipcode.fill('44000');
  // The form posts to its own address: the URL does not change, so the wait is on the answer.
  const [saved] = await Promise.all([
    page.waitForResponse((response) => 'POST' === response.request().method() && response.url().includes('/admin/configuration/store')),
    page.getByTestId('config-store-save-stay').click(),
  ]);
  expect(saved.status(), 'saving the store configuration').toBeLessThan(400);
  await page.waitForLoadState('load');
  // Read back from a fresh load of the screen, not from the page the post answered.
  await page.goto('/admin/configuration/store');
  await expect(page.getByTestId('config-store-quote-mode')).toHaveValue(mode);
}

// ---------------------------------------------------------------- accounts and data

export function newQuoteCustomer(label: string): Customer {
  return newCustomer({ firstname: `Qt${label}`, lastname: 'Buyer', email: `${QUOTE_PREFIX}${label}-${uniqueSuffix()}@example.test` });
}

/** Registers an account through the public form (address included) and signs it out again. */
export async function registerQuoteCustomer(browser: Browser, label: string): Promise<Customer> {
  const customer = newQuoteCustomer(label);
  const context = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US' });
  const page = await context.newPage();
  await registerCustomer(page, customer);
  await context.close();
  return customer;
}

export async function signedInAs(
  browser: Browser,
  customer: Customer,
  viewport: { width: number; height: number } = { width: 1440, height: 900 },
): Promise<{ context: BrowserContext; page: Page }> {
  const context = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport });
  const page = await context.newPage();
  await login(page, customer.email, customer.password);
  return { context, page };
}

/** Removes everything the specs created: orders and their addresses, then the accounts (quotes and carts go with them). Idempotent. */
export async function purgeQuoteData(): Promise<void> {
  const like = `'${QUOTE_PREFIX}%'`;
  await ddevMysql(`
    CREATE TEMPORARY TABLE tmp_e2e_quote_order_address AS
      SELECT o.invoice_order_address_id AS id FROM \`order\` o WHERE o.customer_id IN (SELECT id FROM customer WHERE email LIKE ${like})
      UNION SELECT o.delivery_order_address_id FROM \`order\` o WHERE o.customer_id IN (SELECT id FROM customer WHERE email LIKE ${like});
    DELETE FROM \`order\` WHERE customer_id IN (SELECT id FROM customer WHERE email LIKE ${like});
    DELETE FROM order_address WHERE id IN (SELECT id FROM tmp_e2e_quote_order_address);
    DELETE FROM customer WHERE email LIKE ${like};
  `);
}

export async function remainingQuoteRows(): Promise<Record<string, number>> {
  const like = `'${QUOTE_PREFIX}%'`;
  const rows: Record<string, string> = {
    customers: `SELECT COUNT(*) FROM customer WHERE email LIKE ${like}`,
    quotes: 'SELECT COUNT(*) FROM quote WHERE customer_id NOT IN (SELECT id FROM customer)',
    revisions: 'SELECT COUNT(*) FROM quote_revision WHERE quote_id NOT IN (SELECT id FROM quote)',
  };
  const result: Record<string, number> = {};
  for (const [name, sql] of Object.entries(rows)) result[name] = Number(await ddevMysql(sql));
  return result;
}

// ---------------------------------------------------------------- the shop front

export type CartLine = { product: { slug: string }; quantity: number };

/** Empties the cart of the account and fills it with the lines, through the product pages. */
export async function fillCart(page: Page, email: string, lines: CartLine[]): Promise<void> {
  await emptyCartOf(email);
  for (const { product, quantity } of lines) {
    await gotoProduct(page, product.slug);
    await setQuantity(page, quantity);
    await addCurrentProductToCart(page);
  }
  await page.goto('/checkout/cart');
  await expect(page.locator('[data-live-name-value="Organisms:Cart:Base"] .CartItem')).toHaveCount(lines.length, { timeout: 15_000 });
}

export function quoteIdFromUrl(url: string): number {
  const match = url.match(/\/account\/quote\/(\d+)/);
  if (!match) throw new Error(`no quote id in ${url}`);
  return Number(match[1]);
}

/** From the cart: the request form, filled and sent; returns the id of the quote the shop answers on. */
export async function requestQuoteFromCart(page: Page, comment = '', neededBy = ''): Promise<number> {
  await page.goto('/checkout/cart');
  await Promise.all([page.waitForURL('**/account/quote/request'), page.getByTestId('quote-request-button').first().click()]);
  if ('' !== comment) await page.locator('#quote-comment').fill(comment);
  if ('' !== neededBy) await page.locator('#quote-needed-by').fill(neededBy);
  await Promise.all([page.waitForURL(/\/account\/quote\/\d+/), page.getByTestId('quote-request-submit').click()]);
  return quoteIdFromUrl(page.url());
}

// ---------------------------------------------------------------- amounts

/** "€1,836.00" (shop, en-US) or "1 836,00 €" (back-office, French) -> 1836; a missing amount reads as NaN. */
export function amountOf(text: string): number {
  const cleaned = text.replace(/[^\d.,-]/g, '');
  const french = /,\d{1,2}$/.test(cleaned);
  return Number.parseFloat(french ? cleaned.replace(/\./g, '').replace(',', '.') : cleaned.replace(/,/g, ''));
}

export function round2(value: number): number {
  return Math.round(value * 100) / 100;
}

/**
 * Total with taxes of a quote: the untaxed lines taxed, plus the postage taxed at `postageVat` (the
 * postage is taxed only when the shop names a tax rule for delivery), less the discount (a taxed amount).
 */
export function quoteTotalTaxed(lines: { price: number; quantity: number }[], postage: number, discount: number, postageVat = 0): number {
  const items = lines.reduce((sum, line) => sum + line.price * line.quantity, 0);
  return round2(items * (1 + VAT_RATE) + postage * (1 + postageVat) - discount);
}

/** The VAT the shop puts on a postage: 20 % when a tax rule is set for delivery modules, none otherwise (`taxrule_id_delivery_module`). */
export async function postageVatRate(): Promise<number> {
  const rule = await ddevMysql("SELECT IFNULL(MAX(value), '') FROM config WHERE name = 'taxrule_id_delivery_module'");
  return '' === rule || '0' === rule ? 0 : VAT_RATE;
}

export function isoDay(offsetDays: number): string {
  const day = new Date();
  day.setDate(day.getDate() + offsetDays);
  return `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
}

// ---------------------------------------------------------------- database readers

export type StoredQuote = { id: number; ref: string; state: string; currentRevision: number; validUntil: string; orderId: number | null };

export async function quoteOf(id: number): Promise<StoredQuote> {
  const out = await ddevMysql(`
    SELECT q.id, q.ref, q.state, q.current_revision, IFNULL(q.valid_until, ''), IFNULL((SELECT o.id FROM \`order\` o WHERE o.quote_id = q.id), '')
    FROM quote q WHERE q.id = ${id}`);
  const [qid, ref, state, revision, validUntil, orderId] = out.split('\t');
  return { id: Number(qid), ref, state, currentRevision: Number(revision), validUntil, orderId: orderId ? Number(orderId) : null };
}

export async function revisionCountOf(id: number): Promise<number> {
  return Number(await ddevMysql(`SELECT COUNT(*) FROM quote_revision WHERE quote_id = ${id}`));
}

export async function quoteIdsOf(email: string): Promise<number[]> {
  const out = await ddevMysql(`SELECT q.id FROM quote q JOIN customer c ON c.id = q.customer_id WHERE c.email = ${sqlString(email)} ORDER BY q.id`);
  return out.split('\n').filter(Boolean).map(Number);
}

export type StoredOrderLine = { productRef: string; price: number; quantity: number };
export type StoredQuoteOrder = { id: number; discount: number; postage: number; quoteId: number | null; lines: StoredOrderLine[] };

export async function orderOfQuote(quoteId: number): Promise<StoredQuoteOrder | null> {
  const head = await ddevMysql(`SELECT id, discount, postage, IFNULL(quote_id, '') FROM \`order\` WHERE quote_id = ${quoteId}`);
  if ('' === head) return null;
  const [id, discount, postage, linkedQuote] = head.split('\t');
  const lines = await ddevMysql(`SELECT product_ref, price, quantity FROM order_product WHERE order_id = ${Number(id)} ORDER BY id`);
  return {
    id: Number(id),
    discount: Number(discount),
    postage: Number(postage),
    quoteId: linkedQuote ? Number(linkedQuote) : null,
    lines: lines
      .split('\n')
      .filter(Boolean)
      .map((row) => {
        const [productRef, price, quantity] = row.split('\t');
        return { productRef, price: Number(price), quantity: Number(quantity) };
      }),
  };
}

/** The title, in the language of the shop (English), of the carrier the sent revision of a quote names. */
export async function quoteCarrierTitle(quoteId: number): Promise<string> {
  return ddevMysql(`
    SELECT IFNULL(mi.title, '') FROM quote q
    JOIN quote_revision r ON r.quote_id = q.id AND r.number = q.current_revision
    JOIN module_i18n mi ON mi.id = r.delivery_module_id AND mi.locale = 'en_US'
    WHERE q.id = ${quoteId}`);
}

export async function orderCountOf(email: string): Promise<number> {
  return Number(await ddevMysql(`SELECT COUNT(*) FROM \`order\` o JOIN customer c ON c.id = o.customer_id WHERE c.email = ${sqlString(email)}`));
}

export async function orderRefOf(orderId: number): Promise<string> {
  return ddevMysql(`SELECT ref FROM \`order\` WHERE id = ${orderId}`);
}

/** The ids of the carts the buyer holds that are not quote carts, with their line count: the cart put aside must come back. */
export async function cartLinesOf(email: string): Promise<number> {
  return Number(
    await ddevMysql(`
      SELECT COUNT(*) FROM cart_item ci JOIN cart ca ON ca.id = ci.cart_id JOIN customer cu ON cu.id = ca.customer_id
      WHERE cu.email = ${sqlString(email)} AND ca.quote_id IS NULL`),
  );
}

/** Sets the last day of a quote straight in the database, for the dates the screens refuse to pick (the past). */
export async function setValidUntil(quoteId: number, day: string): Promise<void> {
  await ddevMysql(`UPDATE quote SET valid_until = ${sqlString(day)} WHERE id = ${quoteId}`);
}

// ---------------------------------------------------------------- the back-office

export async function openQuoteSheet(admin: Page, quoteId: number): Promise<void> {
  await admin.goto(`/admin/quotes/${quoteId}`);
  await expect(admin.getByTestId('quote-page')).toBeVisible();
}

/**
 * "Price the quote" on the sheet opens the draft in one gesture: the negotiation form is there as
 * soon as the page that follows has loaded, with no intermediate "new revision" screen.
 */
export async function openNegotiation(admin: Page, quoteId: number): Promise<void> {
  await openQuoteSheet(admin, quoteId);
  await Promise.all([admin.waitForURL(/\/admin\/quotes\/\d+\/edit/), admin.getByTestId('quote-negotiate').click()]);
  await expect(admin.getByTestId('quote-negotiation-form')).toBeVisible();
  await expect(admin.getByTestId('quote-new-revision')).toHaveCount(0);
}

export function isAutosave(url: string, method: string): boolean {
  return 'POST' === method && /\/admin\/quotes\/\d+\/draft\/autosave/.test(url);
}

/**
 * Types in a field of the negotiation form and leaves it: the form saves the draft in the
 * background, and the totals of the screen answer. Resolves with the response of the save.
 */
export async function typeAndSave(admin: Page, field: ReturnType<Page['locator']>, value: string) {
  const saved = admin.waitForResponse((response) => isAutosave(response.url(), response.request().method()), { timeout: 20_000 });
  await field.fill(value);
  await field.press('Tab');
  const response = await saved;
  expect(response.status(), 'the background save of the draft').toBe(200);
  return response;
}

/**
 * The merchant answers a request in the shortest way: opens a draft, prices every line (`prices`
 * by title), picks the first delivery module, sets the last day and sends. The sheet then shows
 * the revision that was sent.
 */
export async function priceAndSendQuote(admin: Page, quoteId: number, prices: Record<string, string>, validDays = 15): Promise<void> {
  await openNegotiation(admin, quoteId);
  for (const [title, price] of Object.entries(prices)) {
    await typeAndSave(admin, admin.locator('[data-testid="quote-lines-table"] tbody tr').filter({ hasText: title }).getByTestId('quote-line-price-input'), price);
  }
  const picked = admin.waitForResponse((response) => isAutosave(response.url(), response.request().method()), { timeout: 20_000 });
  await admin.getByTestId('quote-delivery-module-input').selectOption({ index: 1 });
  await picked;
  await typeAndSave(admin, admin.getByTestId('quote-valid-until-input'), isoDay(validDays));
  await admin.getByTestId('quote-send-open').click();
  await expect(admin.getByTestId('quote-send-modal')).toBeVisible();
  await Promise.all([admin.waitForURL((url) => !url.pathname.endsWith('/edit') || url.searchParams.has('tab')), admin.getByTestId('quote-send').click()]);
  await expect.poll(async () => (await quoteOf(quoteId)).state, { timeout: 15_000 }).toBe('quoted');
}

// ---------------------------------------------------------------- captures

/** Where the captures go: `QUOTE_CAPTURE_DIR` (absolute, or relative to the project), by default under `test-results/`. */
export const CAPTURE_DIR = resolve(PROJECT_ROOT, process.env.QUOTE_CAPTURE_DIR ?? 'test-results/quote-captures');

/** A full-page capture of a key step, without the Symfony debug toolbar (not part of the page a visitor is served). */
export async function capture(page: Page, name: string, options: { withoutFixed?: boolean } = {}): Promise<void> {
  mkdirSync(CAPTURE_DIR, { recursive: true });
  await page.addStyleTag({ content: '.sf-toolbar, [id^="sfwdt"], .sf-minitoolbar { display: none !important; }' });
  if (options.withoutFixed) {
    // A full-page capture stretches the viewport: the bar and the drawers pinned to it would land in the middle of the page.
    await page.evaluate(() => {
      for (const element of document.body.querySelectorAll<HTMLElement>('*')) {
        if ('fixed' === getComputedStyle(element).position) element.style.setProperty('display', 'none', 'important');
      }
    });
  }
  await page.evaluate(() => window.scrollTo(0, 0));
  await page.waitForTimeout(300);
  await page.screenshot({ path: join(CAPTURE_DIR, `${name}.png`), fullPage: true, animations: 'disabled' });
}
