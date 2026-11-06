import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { request } from '@playwright/test';
import { ddevMysql } from './db';
import { expect, type Browser } from '@playwright/test';
import { loginAdmin } from './admin';

/**
 * Helpers of the stock specs: shop settings and sale element rows read and written straight in
 * the database, and the Mailpit of DDEV for the e-mails the shop sends.
 *
 * Every spec that changes a setting or a stock level takes a snapshot first and restores it
 * afterwards (`snapshotConfig`, `snapshotPse`, `snapshotCartItems`): the dev shop is shared.
 */

// DDEV serves Mailpit on port 8026 of the shop's own host.
const MAILPIT_URL = process.env.MAILPIT_URL ?? `${new URL(process.env.BASE_URL ?? 'https://thelia-3.ddev.site').origin}:8026`;

const sql = (value: string): string => `'${value.replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;

// ---------------------------------------------------------------- shop settings

export async function readConfig(name: string): Promise<string | null> {
  const out = await ddevMysql(`SELECT value FROM config WHERE name = ${sql(name)}`);
  return '' === out ? null : out;
}

/**
 * Written through the console of the shop, not by SQL: the configuration table is cached, and
 * only a write that goes through the model drops the cached entry. A raw UPDATE would leave
 * the shop reading the old value.
 */
export async function writeConfig(name: string, value: string): Promise<void> {
  const [command, ...args] = process.env.CONSOLE_COMMAND?.split(' ') ?? ['ddev', 'exec', 'php', 'bin/console'];
  const result = spawnSync(command, [...args, 'thelia:config', 'set', name, value, '--env=dev'], {
    cwd: new URL('../../../', import.meta.url).pathname,
    encoding: 'utf8',
  });
  if (0 !== result.status) {
    throw new Error(`Could not set the config "${name}": ${result.stderr || result.stdout}`);
  }
  if ((await readConfig(name)) !== value) {
    throw new Error(`The config "${name}" must now hold "${value}".`);
  }
}

export async function deleteConfig(name: string): Promise<void> {
  const [command, ...args] = process.env.CONSOLE_COMMAND?.split(' ') ?? ['ddev', 'exec', 'php', 'bin/console'];
  const result = spawnSync(command, [...args, 'thelia:config', 'delete', name, '--env=dev'], {
    cwd: new URL('../../../', import.meta.url).pathname,
    encoding: 'utf8',
  });
  if (0 !== result.status) {
    throw new Error(`Could not delete the config "${name}": ${result.stderr || result.stdout}`);
  }
}

/** Reads the settings now and returns the function that puts them back. */
export async function snapshotConfig(names: string[]): Promise<() => Promise<void>> {
  const saved = new Map<string, string | null>();
  for (const name of names) {
    saved.set(name, await readConfig(name));
  }
  return async () => {
    for (const [name, value] of saved) {
      if (null !== value) await writeConfig(name, value);
    }
  };
}

export type StockReservationSettings = {
  enabled: boolean;
  scope?: 'checkout' | 'cart';
  durationMinutes?: number;
};

export const RESERVATION_CONFIG_NAMES = [
  'stock_reservation_enabled',
  'stock_reservation_scope',
  'stock_reservation_duration',
];

export async function setReservation(settings: StockReservationSettings): Promise<void> {
  await writeConfig('stock_reservation_enabled', settings.enabled ? '1' : '0');
  if (settings.scope) await writeConfig('stock_reservation_scope', settings.scope);
  if (settings.durationMinutes) await writeConfig('stock_reservation_duration', String(settings.durationMinutes));
}

// ---------------------------------------------------------------- sale elements

export type PseStock = {
  quantity: number;
  outOfStockPolicy: 0 | 1 | 2;
  preorderLimit: number | null;
  restockDate: string | null;
  lowStockThreshold: number | null;
};

export const POLICY_REFUSE = 0;
export const POLICY_UNLIMITED = 1;
export const POLICY_LIMITED = 2;

const nullable = (value: number | string | null): string => (null === value ? 'NULL' : sql(String(value)));

export async function readPse(pseId: number): Promise<PseStock> {
  const row = await ddevMysql(
    `SELECT quantity, out_of_stock_policy, IFNULL(preorder_limit, 'NULL'), IFNULL(restock_date, 'NULL'), IFNULL(low_stock_threshold, 'NULL') FROM product_sale_elements WHERE id = ${pseId}`,
  );
  const [quantity, policy, limit, restock, threshold] = row.split('\t');
  return {
    quantity: Number(quantity),
    outOfStockPolicy: Number(policy) as 0 | 1 | 2,
    preorderLimit: 'NULL' === limit ? null : Number(limit),
    restockDate: 'NULL' === restock ? null : restock,
    lowStockThreshold: 'NULL' === threshold ? null : Number(threshold),
  };
}

export async function writePse(pseId: number, stock: Partial<PseStock>): Promise<void> {
  const sets: string[] = [];
  if (undefined !== stock.quantity) sets.push(`quantity = ${nullable(stock.quantity)}`);
  if (undefined !== stock.outOfStockPolicy) sets.push(`out_of_stock_policy = ${stock.outOfStockPolicy}`);
  if (undefined !== stock.preorderLimit) sets.push(`preorder_limit = ${nullable(stock.preorderLimit)}`);
  if (undefined !== stock.restockDate) sets.push(`restock_date = ${nullable(stock.restockDate)}`);
  if (undefined !== stock.lowStockThreshold) sets.push(`low_stock_threshold = ${nullable(stock.lowStockThreshold)}`);
  if (0 === sets.length) return;
  await ddevMysql(`UPDATE product_sale_elements SET ${sets.join(', ')}, updated_at = NOW() WHERE id = ${pseId}`);
}

export async function physicalStock(pseId: number): Promise<number> {
  return (await readPse(pseId)).quantity;
}

/** Reads the rows now and returns the function that puts them back. */
export async function snapshotPse(pseIds: number[]): Promise<() => Promise<void>> {
  const saved = new Map<number, PseStock>();
  for (const id of pseIds) {
    saved.set(id, await readPse(id));
  }
  return async () => {
    for (const [id, stock] of saved) {
      await writePse(id, stock);
    }
  };
}

// ---------------------------------------------------------------- cart lines

export type CartLine = { id: number; cartId: number; quantity: number; reservedUntil: string | null };

export async function cartLines(pseId: number): Promise<CartLine[]> {
  const out = await ddevMysql(
    `SELECT id, cart_id, quantity, IFNULL(reserved_until, 'NULL') FROM cart_item WHERE product_sale_elements_id = ${pseId} ORDER BY id`,
  );
  if ('' === out) return [];
  return out.split('\n').map((line) => {
    const [id, cartId, quantity, reservedUntil] = line.split('\t');
    return {
      id: Number(id),
      cartId: Number(cartId),
      quantity: Number(quantity),
      reservedUntil: 'NULL' === reservedUntil ? null : reservedUntil,
    };
  });
}

/**
 * Pushes the end of every reservation held on a sale element into the past. A reservation that
 * has run out is never counted, whether or not the purge command has removed it: this is what
 * a real wait of the configured duration would do, without the wait.
 */
export async function expireReservations(pseId: number): Promise<void> {
  await ddevMysql(
    `UPDATE cart_item SET reserved_until = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE product_sale_elements_id = ${pseId} AND reserved_until IS NOT NULL`,
  );
}

/** Remembers the cart lines of the sale elements; the returned function deletes the ones a test added. */
export async function snapshotCartLines(pseIds: number[]): Promise<() => Promise<void>> {
  const list = pseIds.join(',');
  const before = new Set<number>();
  const out = await ddevMysql(`SELECT id FROM cart_item WHERE product_sale_elements_id IN (${list})`);
  for (const id of out.split('\n').filter(Boolean)) before.add(Number(id));
  return async () => {
    const now = await ddevMysql(`SELECT id FROM cart_item WHERE product_sale_elements_id IN (${list})`);
    const added = now.split('\n').filter(Boolean).map(Number).filter((id) => !before.has(id));
    if (added.length > 0) {
      await ddevMysql(`DELETE FROM cart_item WHERE id IN (${added.join(',')})`);
    }
  };
}

export type OrderLineStock = { isPreorder: number; restockDate: string | null; quantity: number };

export async function orderLines(orderRef: string): Promise<OrderLineStock[]> {
  const out = await ddevMysql(
    `SELECT op.is_preorder, IFNULL(op.restock_date, 'NULL'), op.quantity FROM order_product op JOIN \`order\` o ON o.id = op.order_id WHERE o.ref = ${sql(orderRef)} ORDER BY op.id`,
  );
  if ('' === out) return [];
  return out.split('\n').map((line) => {
    const [isPreorder, restockDate, quantity] = line.split('\t');
    return { isPreorder: Number(isPreorder), restockDate: 'NULL' === restockDate ? null : restockDate, quantity: Number(quantity) };
  });
}

// ---------------------------------------------------------------- e-mail templates

/**
 * The shop reads its mail template from `templates/email/<name>`. `templates/email-dev` is the
 * clone the stock mails live in, reached from `templates/email/` by a relative name.
 *
 * The name cannot be switched by the `active-mail-template` setting of the dev shop: the
 * environment variable `ACTIVE_MAIL_TEMPLATE` (set in the dev environment files) wins over the
 * stored value. A rendering run from the command line can set the variable for its own process,
 * which is how a mail is checked against the clone.
 */
export const DEV_MAIL_TEMPLATE = '../email-dev';

const PROJECT_ROOT = new URL('../../../', import.meta.url).pathname;
/** Renders a mail message without sending it, with the template of the clone (or the shop's own when `template` is null). */
export function renderMail(code: string, options: { order?: string; template?: string | null } = {}): { status: number; output: string } {
  const template = undefined === options.template ? DEV_MAIL_TEMPLATE : options.template;
  const mailRender = ['mail:render', code, '--locale=en_US', '--env=dev'];
  if (options.order) mailRender.push(`--order=${options.order}`);
  const outside = process.env.CONSOLE_COMMAND?.split(' ');
  const [command, ...args] = outside
    ? [...outside, ...mailRender]
    : ['ddev', 'exec', `${null === template ? '' : `ACTIVE_MAIL_TEMPLATE=${template} `}php bin/console ${mailRender.join(' ')}`];
  const result = spawnSync(command, args, {
    cwd: PROJECT_ROOT,
    encoding: 'utf8',
    maxBuffer: 16 * 1024 * 1024,
    env: outside && null !== template ? { ...process.env, ACTIVE_MAIL_TEMPLATE: template } : process.env,
  });
  return { status: result.status ?? -1, output: `${result.stdout ?? ''}${result.stderr ?? ''}` };
}

/** True when the mail template the shop really uses ships the message (the stock mails need `low_stock_alert`). */
export function shopMailTemplateHas(code: string): boolean {
  return 0 === renderMail(code, { template: null }).status;
}

/** Number of lines of the application log that match; the log file is read straight from the project. */
export function countLogLines(pattern: RegExp, file = 'var/log/log-thelia.txt'): number {
  try {
    return readFileSync(`${PROJECT_ROOT}${file}`, 'utf8').split('\n').filter((line) => pattern.test(line)).length;
  } catch {
    return 0;
  }
}

export async function orderIdOf(orderRef: string): Promise<string> {
  return ddevMysql(`SELECT id FROM \`order\` WHERE ref = ${sql(orderRef)}`);
}

/**
 * Every row of the configuration table, and the function that puts back the rows a test changed:
 * for a form of the back-office that saves more than the setting under test.
 */
export async function snapshotConfigTable(): Promise<() => Promise<void>> {
  const read = async () => {
    const out = await ddevMysql('SELECT name, IFNULL(value, \'\') FROM config');
    return new Map(out.split('\n').filter(Boolean).map((line) => {
      const [name, ...rest] = line.split('\t');
      return [name, rest.join('\t')] as [string, string];
    }));
  };
  const before = await read();
  return async () => {
    const now = await read();
    for (const [name, value] of before) {
      if (now.has(name) && now.get(name) !== value) await writeConfig(name, value);
    }
    // A setting the form created (a shop that never saved it has no row): deleted again.
    for (const name of now.keys()) {
      if (!before.has(name)) await deleteConfig(name);
    }
  };
}

// ---------------------------------------------------------------- Mailpit

export type MailpitMessage = {
  ID: string;
  Subject: string;
  To: { Address: string }[];
  Created: string;
};

export type MailpitFullMessage = MailpitMessage & { HTML: string; Text: string };

async function mailpit<T>(method: 'GET' | 'DELETE', path: string, data?: object): Promise<T> {
  const context = await request.newContext({ baseURL: MAILPIT_URL, ignoreHTTPSErrors: true });
  try {
    const response = await context.fetch(path, { method, data });
    if (!response.ok()) throw new Error(`Mailpit ${method} ${path}: ${response.status()}`);
    return (await response.json().catch(() => ({}))) as T;
  } finally {
    await context.dispose();
  }
}

export async function mailpitSearch(query: string): Promise<MailpitMessage[]> {
  const result = await mailpit<{ messages: MailpitMessage[] | null }>('GET', `/api/v1/search?query=${encodeURIComponent(query)}&limit=50`);
  return result.messages ?? [];
}

export async function mailpitMessage(id: string): Promise<MailpitFullMessage> {
  return mailpit<MailpitFullMessage>('GET', `/api/v1/message/${id}`);
}

/** Deletes only the messages that match the query: other mail in the box is left alone. */
export async function mailpitDeleteMatching(query: string): Promise<void> {
  await mailpit('DELETE', `/api/v1/search?query=${encodeURIComponent(query)}`);
}

// ---------------------------------------------------------------- v2: what the carts hold, orders, clean-up

/** What the carts hold of a sale element right now, as the back-office tooltip should tell it. */
export async function heldNow(pseId: number): Promise<{ quantity: number; carts: number; nextExpiry: string | null }> {
  const out = await ddevMysql(
    `SELECT IFNULL(SUM(quantity), 0), COUNT(DISTINCT cart_id), IFNULL(DATE_FORMAT(MIN(reserved_until), '%Y-%m-%d %H:%i'), 'NULL') FROM cart_item WHERE product_sale_elements_id = ${pseId} AND reserved_until > NOW()`,
  );
  const [quantity, carts, expiry] = out.split('\t');
  return { quantity: Number(quantity), carts: Number(carts), nextExpiry: 'NULL' === expiry ? null : expiry };
}

/**
 * What the purge command does to a hold that has run out: the line keeps no date and no held quantity. The next hold of the
 * cart is then a new one (a line with a date, even a past one, is "told as held" and its next hold is free).
 */
export async function expireCartHolds(cartId: number): Promise<void> {
  await ddevMysql(`UPDATE cart_item SET reserved_until = NULL, reserved_quantity = NULL WHERE cart_id = ${cartId}`);
}

export async function latestCartIdOf(customerEmail: string): Promise<number> {
  return Number(await ddevMysql(`SELECT c.id FROM cart c JOIN customer cu ON cu.id = c.customer_id WHERE cu.email = ${sql(customerEmail)} ORDER BY c.id DESC LIMIT 1`));
}

/** The back-office moves an order to "paid" the way the merchant does when a cheque arrives (the stock of a Cheque order is taken then). */
export async function payOrderInBackOffice(browser: Browser, orderRef: string): Promise<void> {
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const admin = await context.newPage();
  await loginAdmin(admin);
  const orderId = await orderIdOf(orderRef);
  await admin.goto(`/admin/order/update/${orderId}`);
  await admin.getByTestId('order-status-select').selectOption({ label: 'Paid' }).catch(async () => {
    await admin.getByTestId('order-status-select').selectOption('2');
  });
  await Promise.all([admin.waitForLoadState('load'), admin.getByTestId('order-status-submit').click()]);
  await expect.poll(() => ddevMysql(`SELECT status_id FROM \`order\` WHERE id = ${orderId}`), { timeout: 15_000 }).toBe('2');
  await context.close();
}

/** Removes the orders a spec placed (lines, addresses) and the customers it registered, with their Mailpit mail. Nothing else is touched. */
export async function purgeOrdersAndCustomers(orderRefs: string[], emails: string[]): Promise<void> {
  if (orderRefs.length > 0) {
    const refs = orderRefs.map(sql).join(',');
    await ddevMysql(`
      CREATE TEMPORARY TABLE tmp_v2_order_address AS
        SELECT invoice_order_address_id AS id FROM \`order\` WHERE ref IN (${refs})
        UNION SELECT delivery_order_address_id FROM \`order\` WHERE ref IN (${refs});
      DELETE FROM \`order\` WHERE ref IN (${refs});
      DELETE FROM order_address WHERE id IN (SELECT id FROM tmp_v2_order_address);
    `);
  }
  for (const email of emails) {
    await ddevMysql(`DELETE FROM customer WHERE email = ${sql(email)}`);
    await mailpitDeleteMatching(`to:${email}`).catch(() => {});
  }
}
