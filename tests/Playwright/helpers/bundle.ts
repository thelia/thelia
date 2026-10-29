import { request, type APIRequestContext, type Page, type ConsoleMessage } from '@playwright/test';
import { ddevMysql } from './db';

/**
 * Shared set-up of the product bundle acceptance specs (#204).
 *
 * The world of the plan, built through the admin API on products created for the run (every ref
 * starts with `PW-BUNDLE-`): a bike (quantity 1, 500 excl. tax, stock 7), a helmet (quantity 2, 30,
 * stock 5), an optional lock 0..3 (20, stock 10) and a bundle priced by its components with 10 %
 * off. Taxes are the 20 % of the default tax rule. Nothing that existed before the run is read for
 * its data or changed; `cleanupBundleWorld` removes what the run created.
 */

export const REF_PREFIX = 'PW-BUNDLE-';

export const VIEWPORTS = [
  { name: 'desktop', viewport: { width: 1440, height: 900 } },
  { name: 'mobile', viewport: { width: 390, height: 844 } },
] as const;

const DEFAULT_TAX_RULE = '/api/admin/tax_rules/1';
const EURO = '/api/admin/currencies/1';

export type Component = {
  productId: number;
  saleElementsId: number;
  ref: string;
  title: string;
  slug: string;
};

export type BundleWorld = {
  tag: string;
  categoryId: number;
  bike: Component;
  helmet: Component;
  lock: Component;
  pack: Component;
  itemIds: { bike: number; helmet: number; lock: number };
};

export class AdminApi {
  private constructor(private readonly context: APIRequestContext) {}

  static async open(): Promise<AdminApi> {
    const baseURL = process.env.BASE_URL ?? 'https://thelia-3.ddev.site';
    const anonymous = await request.newContext({ baseURL, ignoreHTTPSErrors: true });
    const login = await anonymous.post('/api/admin/login', {
      data: {
        username: process.env.ADMIN_USER ?? 'thelia',
        password: process.env.ADMIN_PASSWORD ?? 'thelia',
      },
    });
    if (!login.ok()) {
      throw new Error(`admin API login refused: ${login.status()}`);
    }
    const { token } = (await login.json()) as { token: string };
    await anonymous.dispose();

    return new AdminApi(
      await request.newContext({
        baseURL,
        ignoreHTTPSErrors: true,
        extraHTTPHeaders: { Authorization: `Bearer ${token}`, Accept: 'application/ld+json' },
      }),
    );
  }

  async post(path: string, data: unknown): Promise<any> {
    const response = await this.context.post(path, { data });
    if (!response.ok()) {
      throw new Error(`POST ${path} -> ${response.status()}: ${(await response.text()).slice(0, 400)}`);
    }
    return await response.json();
  }

  async patch(path: string, data: unknown): Promise<any> {
    const response = await this.context.patch(path, {
      data,
      headers: { 'Content-Type': 'application/merge-patch+json' },
    });
    if (!response.ok()) {
      throw new Error(`PATCH ${path} -> ${response.status()}: ${(await response.text()).slice(0, 400)}`);
    }
    return await response.json();
  }

  /** Answers the raw response, to assert a refusal. */
  async tryPost(path: string, data: unknown) {
    return await this.context.post(path, { data });
  }

  async delete(path: string): Promise<number> {
    return (await this.context.delete(path)).status();
  }

  async dispose(): Promise<void> {
    await this.context.dispose();
  }
}

async function createSimpleProduct(
  api: AdminApi,
  categoryId: number,
  tag: string,
  key: string,
  title: string,
  price: number,
  stock: number,
  weight: number,
): Promise<Component> {
  const ref = `${REF_PREFIX}${tag}-${key}`;
  // The title makes the rewritten URL of the product: it carries the tag so two runs never collide.
  const fullTitle = `PW ${title} ${tag}`;
  const product = await api.post('/api/admin/products', {
    ref,
    visible: true,
    virtual: false,
    taxRule: DEFAULT_TAX_RULE,
    i18ns: { en_US: { title: fullTitle, locale: 'en_US' } },
    productCategories: [{ category: `/api/admin/categories/${categoryId}`, defaultCategory: true }],
  });
  const saleElements = await api.post('/api/admin/product_sale_elements', {
    product: `/api/admin/products/${product.id}`,
    ref: `${ref}-0`,
    quantity: stock,
    visible: true,
    isDefault: true,
    weight,
    productPrices: [{ currency: EURO, price, promoPrice: 0 }],
  });

  return {
    productId: product.id,
    saleElementsId: saleElements.id,
    ref,
    title: fullTitle,
    slug: new URL(product.publicUrl).pathname.replace(/^\//, '').replace(/\.html$/, ''),
  };
}

export type WorldOptions = {
  /** Compose the pack (3 items) and flag it as a bundle. Off for the back-office run, which does it by hand. */
  assembled?: boolean;
  priceMode?: 'computed' | 'fixed';
};

export async function createBundleWorld(tag: string, options: WorldOptions = {}): Promise<BundleWorld> {
  const assembled = options.assembled ?? true;
  const api = await AdminApi.open();
  try {
    const category = await api.post('/api/admin/categories', {
      visible: true,
      parent: 0,
      i18ns: { en_US: { title: `PW Bundle ${tag}`, locale: 'en_US' } },
    });
    const categoryId: number = category.id;
    const bike = await createSimpleProduct(api, categoryId, tag, 'BIKE', 'Bike', 500, 7, 0.5);
    const helmet = await createSimpleProduct(api, categoryId, tag, 'HELMET', 'Helmet', 30, 5, 0.25);
    const lock = await createSimpleProduct(api, categoryId, tag, 'LOCK', 'Lock', 20, 10, 0.25);
    // The technical sale element of a bundle: its price is ignored in computed mode, its stock never read.
    const pack = await createSimpleProduct(api, categoryId, tag, 'PACK', 'Pack', 1, 0, 0);

    const itemIds = { bike: 0, helmet: 0, lock: 0 };
    if (assembled) {
      await api.patch(`/api/admin/products/${pack.productId}`, {
        isBundle: true,
        bundlePriceMode: options.priceMode ?? 'computed',
        bundleDiscount: 10,
        bundleDiscountType: 'percent',
      });
      const addItem = async (component: Component, fields: Record<string, unknown>): Promise<number> => {
        const item = await api.post('/api/admin/product_bundle_items', {
          bundleProduct: `/api/admin/products/${pack.productId}`,
          productSaleElements: `/api/admin/product_sale_elements/${component.saleElementsId}`,
          ...fields,
        });
        return item.id;
      };
      itemIds.bike = await addItem(bike, { quantity: 1, required: true, position: 1 });
      itemIds.helmet = await addItem(helmet, { quantity: 2, required: true, position: 2 });
      itemIds.lock = await addItem(lock, { quantity: 1, minQuantity: 0, maxQuantity: 3, required: false, position: 3 });
    }

    return { tag, categoryId, bike, helmet, lock, pack, itemIds };
  } finally {
    await api.dispose();
  }
}

/**
 * Removes what the run created: the cart lines of its products, the products (their sale elements,
 * prices and bundle items go with them) and the category. A bundle product is deleted before its
 * components. Orders the tunnel placed stay, with their `PW-BUNDLE-` references: they are history.
 */
export async function cleanupBundleWorld(world: BundleWorld): Promise<void> {
  const api = await AdminApi.open();
  try {
    const pattern = `${REF_PREFIX}${world.tag}-%`;
    await ddevMysql(`DELETE ci FROM cart_item ci JOIN product p ON p.id = ci.product_id WHERE p.ref LIKE '${pattern}';`);
    const ids = (await ddevMysql(`SELECT id FROM product WHERE ref LIKE '${pattern}' ORDER BY is_bundle DESC, id;`))
      .split('\n')
      .filter(Boolean);
    for (const id of ids) await api.delete(`/api/admin/products/${id}`);
    await api.delete(`/api/admin/categories/${world.categoryId}`);
  } finally {
    await api.dispose();
  }
  await dropRewritingLeftovers();
}

/**
 * Deleting a product or a category through the admin API leaves its rewriting rows behind as
 * `obsolete-rewritten-url`, and the next product with the same title then gets a `-2` suffix. The titles of the run
 * all start with "PW ", so their urls start with `pw-`: those of products and categories that no longer exist go.
 */
async function dropRewritingLeftovers(): Promise<void> {
  await ddevMysql(
    `DELETE FROM rewriting_url WHERE url LIKE 'pw-%' AND (
       (view IN ('product', 'obsolete-rewritten-url') AND view_id NOT IN (SELECT id FROM product) AND view_id NOT IN (SELECT id FROM category))
       OR (view = 'category' AND view_id NOT IN (SELECT id FROM category))
     );`,
  );
}

/** A further product of the world, for the cases that need one (a second bundle, a stranger). */
export async function createExtraProduct(
  world: BundleWorld,
  key: string,
  title: string,
  price: number,
  stock: number,
): Promise<Component> {
  const api = await AdminApi.open();
  try {
    return await createSimpleProduct(api, world.categoryId, world.tag, key, title, price, stock, 0);
  } finally {
    await api.dispose();
  }
}

/**
 * A second pack of the world, made of the bike (1) and the helmet (2), both required, at the same
 * price rule: a test that breaks the composition of a pack gets one of its own.
 */
export async function createExtraPack(
  world: BundleWorld,
  key: string,
): Promise<{ pack: Component; itemIds: { bike: number; helmet: number } }> {
  const api = await AdminApi.open();
  try {
    const pack = await createSimpleProduct(api, world.categoryId, world.tag, key, `Pack ${key}`, 1, 0, 0);
    await api.patch(`/api/admin/products/${pack.productId}`, {
      isBundle: true,
      bundlePriceMode: 'computed',
      bundleDiscount: 10,
      bundleDiscountType: 'percent',
    });
    const addItem = async (component: Component, quantity: number, position: number): Promise<number> =>
      (
        await api.post('/api/admin/product_bundle_items', {
          bundleProduct: `/api/admin/products/${pack.productId}`,
          productSaleElements: `/api/admin/product_sale_elements/${component.saleElementsId}`,
          quantity,
          required: true,
          position,
        })
      ).id;

    return { pack, itemIds: { bike: await addItem(world.bike, 1, 1), helmet: await addItem(world.helmet, 2, 2) } };
  } finally {
    await api.dispose();
  }
}

/** Every product a previous, interrupted run left behind. */
export async function purgeLeftovers(): Promise<number> {
  const ids = (await ddevMysql(`SELECT id FROM product WHERE ref LIKE '${REF_PREFIX}%' ORDER BY is_bundle DESC, id;`))
    .split('\n')
    .filter(Boolean);
  const api = await AdminApi.open();
  try {
    await ddevMysql(
      `DELETE ci FROM cart_item ci JOIN product p ON p.id = ci.product_id WHERE p.ref LIKE '${REF_PREFIX}%';`,
    );
    for (const id of ids) await api.delete(`/api/admin/products/${id}`);
    const categories = (await ddevMysql(`SELECT id FROM category_i18n WHERE title LIKE 'PW Bundle %';`))
      .split('\n')
      .filter(Boolean);
    for (const id of categories) await api.delete(`/api/admin/categories/${id}`);
  } finally {
    await api.dispose();
  }
  await dropRewritingLeftovers();
  return ids.length;
}

export async function stockOf(component: Component): Promise<number> {
  return Number(await ddevMysql(`SELECT quantity FROM product_sale_elements WHERE id = ${component.saleElementsId};`));
}

export async function setStock(component: Component, quantity: number): Promise<void> {
  await ddevMysql(`UPDATE product_sale_elements SET quantity = ${quantity} WHERE id = ${component.saleElementsId};`);
}

export async function setProductVisible(component: Component, visible: boolean): Promise<void> {
  await ddevMysql(`UPDATE product SET visible = ${visible ? 1 : 0} WHERE id = ${component.productId};`);
}

/** The JS errors of a page: `console.error` messages and uncaught exceptions. */
export function watchConsole(page: Page): { errors: string[] } {
  const errors: string[] = [];
  page.on('console', (message: ConsoleMessage) => {
    if (message.type() === 'error') errors.push(`console: ${message.text()}`);
  });
  page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));
  return { errors };
}

/** "604,80 €" or "€604.80" or "1 234,50" -> 604.8: whatever the locale paints. */
export function parseMoney(text: string): number {
  const digits = text.replace(/[^\d.,-]/g, '');
  const lastSeparator = Math.max(digits.lastIndexOf(','), digits.lastIndexOf('.'));
  if (lastSeparator < 0) return Number(digits);
  const integer = digits.slice(0, lastSeparator).replace(/[.,]/g, '');
  return Number(`${integer}.${digits.slice(lastSeparator + 1)}`);
}

/** The first amount written in a text ("€504.00 excl. tax, with the default composition" -> 504). */
export function firstMoney(text: string): number {
  const match = text.match(/\d[\d\s  .,]*\d|\d/);
  if (!match) throw new Error(`no amount in "${text}"`);
  return parseMoney(match[0].replace(/[\s  ]/g, ''));
}

export const TEST_PASSWORD = 'CorrectHorseBattery!9';
// bcrypt of TEST_PASSWORD, what `Customer::setPassword()` stores (`PASSWORD_BCRYPT`).
const TEST_PASSWORD_HASH = '$2y$10$sx5hbMjRg5EBnsMqtCpLwO4OkF3/wdqdX2JgQZzgMy24HfJw8xAZe';

export type TestCustomer = { id: number; email: string; password: string };

/**
 * A customer of the run, inserted by SQL with one default address in metropolitan France.
 *
 * The registration form of the shop is not used on purpose: on a database that carries
 * `customer.customer_group_id` (NOT NULL, no default) ahead of the code under test, the form answers
 * a 500, which has nothing to do with the bundles. The column is filled when it exists, with the
 * default group. Rows are told apart by the `pw-bundle.` prefix of the e-mail.
 */
export async function createTestCustomer(tag: string): Promise<TestCustomer> {
  const email = `pw-bundle.${tag.toLowerCase()}.${Date.now().toString(36)}@example.test`;
  const hasGroup =
    Number(
      await ddevMysql(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'customer' AND COLUMN_NAME = 'customer_group_id';",
      ),
    ) > 0;
  const groupColumn = hasGroup ? ', customer_group_id' : '';
  const groupValue = hasGroup ? ", (SELECT id FROM customer_group WHERE is_default = 1 LIMIT 1)" : '';
  const id = Number(
    await ddevMysql(
      `INSERT INTO customer (title_id, lang_id, ref, firstname, lastname, email, password, algo, reseller, discount, enable, is_guest${groupColumn}, created_at, updated_at, version)
       VALUES (1, (SELECT id FROM lang WHERE locale = 'en_US' LIMIT 1), CONCAT('PWB', FLOOR(RAND() * 1000000000)), 'PW', 'Bundle', '${email}', '${TEST_PASSWORD_HASH}', 'PASSWORD_BCRYPT', 0, 0, 1, 0${groupValue}, NOW(), NOW(), 1);
       SELECT LAST_INSERT_ID();`,
    ),
  );
  await ddevMysql(
    `INSERT INTO address (label, customer_id, title_id, firstname, lastname, address1, address2, address3, zipcode, city, country_id, phone, cellphone, is_default, created_at, updated_at)
     VALUES ('Home', ${id}, 1, 'PW', 'Bundle', '12 rue du Test', '', '', '44000', 'Nantes', 64, '0240000000', '0240000000', 1, NOW(), NOW());`,
  );
  return { id, email, password: TEST_PASSWORD };
}

/** Removes the cart lines and the addresses of the customers of the runs; their orders stay as history. */
export async function dropTestCustomer(customer: TestCustomer): Promise<void> {
  await ddevMysql(
    `DELETE ci FROM cart_item ci JOIN cart c ON c.id = ci.cart_id WHERE c.customer_id = ${customer.id};
     DELETE FROM cart WHERE customer_id = ${customer.id};`,
  );
  // A customer who ordered is kept, orders point at it.
  const orders = Number(await ddevMysql(`SELECT COUNT(*) FROM \`order\` WHERE customer_id = ${customer.id};`));
  if (orders === 0) {
    await ddevMysql(`DELETE FROM address WHERE customer_id = ${customer.id}; DELETE FROM customer WHERE id = ${customer.id};`);
  }
}

/** Empties the rate limiter pool of the dev shop (same gesture as the global set-up of the other journeys). */
export async function clearRateLimiters(): Promise<void> {
  const { spawnSync } = await import('node:child_process');
  const root = new URL('../../../', import.meta.url).pathname;
  spawnSync('ddev', ['exec', 'php', 'bin/console', 'cache:pool:clear', 'cache.rate_limiter', '--env=dev'], { cwd: root });
}
