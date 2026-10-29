import { expect, test, type Page } from '@playwright/test';
import { loginAdmin } from '../helpers/admin';
import {
  AdminApi,
  VIEWPORTS,
  cleanupBundleWorld,
  createBundleWorld,
  createExtraProduct,
  firstMoney,
  purgeLeftovers,
  setProductVisible,
  setStock,
  type BundleWorld,
  type Component,
} from '../helpers/bundle';
import { ddevMysql } from '../helpers/db';

/**
 * Back-office of the product bundles: the sheet of the product, its Composition tab, the list and
 * the dashboard. The pack is created as a plain product by the API and made a bundle by hand, in
 * the screens, with the example of the plan (bike 1 x 500, helmet 2 x 30, optional lock 0..3 x 20,
 * 10 % off, TTC 20 %).
 */

const editUrl = (world: BundleWorld) => `/admin/products/update?product_id=${world.pack.productId}`;

async function openComposition(page: Page, world: BundleWorld): Promise<void> {
  await page.goto(`${editUrl(world)}&current_tab=bundle`);
  await expect(page.getByTestId('product-bundle-tab')).toBeVisible();
}

async function addComponent(
  page: Page,
  world: BundleWorld,
  component: Component,
  fields: { quantity: string; min?: string; max?: string; required: boolean },
): Promise<void> {
  const product = page.getByTestId('product-bundle-product');
  const combination = page.getByTestId('product-bundle-combination');
  // The Stimulus controller of the picker may not be connected yet right after a page load: a change
  // fired before that is lost, so the category is picked again until the products come.
  await expect(async () => {
    await page.getByTestId('product-bundle-category').selectOption('');
    await page.getByTestId('product-bundle-category').selectOption(String(world.categoryId));
    await expect(product.locator(`option[value="${component.productId}"]`)).toBeAttached({ timeout: 2_000 });
  }).toPass({ timeout: 15_000 });
  await product.selectOption(String(component.productId));
  await expect(combination.locator(`option[value="${component.saleElementsId}"]`)).toBeAttached();
  await combination.selectOption(String(component.saleElementsId));
  await page.getByTestId('product-bundle-quantity').fill(fields.quantity);
  await page.getByTestId('product-bundle-min').fill(fields.min ?? '');
  await page.getByTestId('product-bundle-max').fill(fields.max ?? '');
  const required = page.getByTestId('product-bundle-required');
  if ((await required.isChecked()) !== fields.required) await required.setChecked(fields.required);
  await Promise.all([
    page.waitForURL(/\/admin\/products\/update/),
    page.getByTestId('product-bundle-add').click(),
  ]);
  await expect(page.getByTestId('product-bundle-tab')).toBeVisible();
}

async function itemRow(page: Page, component: Component) {
  return page.locator('[data-testid="datatable-product-bundle-items-row"]').filter({ hasText: component.title });
}

for (const { name, viewport } of VIEWPORTS) {
  test.describe(`Back-office bundles, ${name}`, () => {
    test.describe.configure({ mode: 'serial' });
    test.use({ viewport });

    let world: BundleWorld;
    let strangerBundle: Component;

    test.beforeAll(async () => {
      await purgeLeftovers().catch(() => 0);
      world = await createBundleWorld(`BO${name === 'desktop' ? 'D' : 'M'}`, { assembled: false });
      strangerBundle = await createExtraProduct(world, 'PACK2', 'Other pack', 1, 0);
      const api = await AdminApi.open();
      await api.patch(`/api/admin/products/${strangerBundle.productId}`, { isBundle: true });
      await api.dispose();
    });

    test.afterAll(async () => {
      await cleanupBundleWorld(world);
    });

    test.beforeEach(async ({ page }) => {
      await loginAdmin(page);
    });

    test('product sheet: bundle box, computed mode, 10 % discount, Composition tab', async ({ page }) => {
      await page.goto(editUrl(world));
      await expect(page.getByTestId('product-edit-bundle')).toBeVisible();
      await expect(page.getByTestId('product-tab-bundle')).toHaveCount(0);

      await page.getByTestId('product-edit-is-bundle').check();
      await page.getByTestId('product-edit-bundle-price-mode').selectOption('computed');
      await expect(page.getByTestId('product-edit-bundle-discount')).toBeVisible();
      await page.getByTestId('product-edit-bundle-discount').fill('10');
      await page.getByTestId('product-edit-bundle-discount-type').selectOption('percent');
      await Promise.all([
        page.waitForURL(/\/admin\/products\/update/),
        page.getByTestId('product-edit-save-stay').click(),
      ]);

      await expect(page.getByTestId('product-tab-bundle')).toBeVisible();
      const api = await AdminApi.open();
      const stored = await (await api['context'].get(`/api/admin/products/${world.pack.productId}`)).json();
      await api.dispose();
      expect(stored.isBundle).toBe(true);
      expect(stored.bundlePriceMode).toBe('computed');
      expect(Number(stored.bundleDiscount)).toBe(10);
      expect(stored.bundleDiscountType).toBe('percent');
    });

    test('composition: empty bundle, then the three components through the picker', async ({ page }) => {
      await openComposition(page, world);
      await expect(page.getByTestId('product-bundle-state-empty')).toBeVisible();

      // The picker never offers a bundle: the other pack is not in the list of the category.
      await page.getByTestId('product-bundle-category').selectOption(String(world.categoryId));
      const product = page.getByTestId('product-bundle-product');
      await expect(product.locator(`option[value="${world.bike.productId}"]`)).toBeAttached();
      await expect(product.locator(`option[value="${strangerBundle.productId}"]`)).toHaveCount(0);
      await expect(product.locator(`option[value="${world.pack.productId}"]`)).toHaveCount(0);

      await addComponent(page, world, world.bike, { quantity: '1', required: true });
      await addComponent(page, world, world.helmet, { quantity: '2', required: true });
      await addComponent(page, world, world.lock, { quantity: '1', min: '0', max: '3', required: false });

      const rows = page.locator('[data-testid="datatable-product-bundle-items-row"]');
      await expect(rows).toHaveCount(3);
      await expect(rows.nth(0)).toContainText(world.bike.title);
      await expect(rows.nth(1)).toContainText(world.helmet.title);
      await expect(rows.nth(2)).toContainText(world.lock.title);
      await expect(rows.nth(2)).toContainText(/Optional/i);
    });

    test('summary box: default composition 560 struck, 504, availability 2', async ({ page }) => {
      await openComposition(page, world);
      await expect(page.getByTestId('product-bundle-state-sellable')).toBeVisible();
      const full = firstMoney(await page.getByTestId('product-bundle-full-price').innerText());
      const price = firstMoney(await page.getByTestId('product-bundle-price').innerText());
      // Lock left out by default (minimum 0): 500 + 2 x 30 = 560, less 10 % = 504.
      expect(full).toBe(560);
      expect(price).toBe(504);
      await expect(page.getByTestId('product-bundle-discount')).toContainText('10');
      // min(floor(7 / 1), floor(5 / 2)) = 2
      await expect(page.getByTestId('product-bundle-availability')).toContainText(/^\s*2\b/);
      await page.getByTestId('product-bundle-tab').screenshot({ path: new URL(`../../../var/t204/recette/bo-composition-${name}.png`, import.meta.url).pathname });
    });

    test('edit a component: combination read-only, bounds saved, then restored', async ({ page }) => {
      await openComposition(page, world);
      const row = await itemRow(page, world.lock);
      await row.locator('[data-item-id]').first().click();
      const modal = page.locator('#product-bundle-edit');
      await expect(modal).toBeVisible();
      await expect(page.getByTestId('product-bundle-edit-combination')).toHaveAttribute('readonly', '');
      await expect(page.getByTestId('product-bundle-edit-max')).toHaveValue('3');
      await page.getByTestId('product-bundle-edit-max').fill('5');
      await Promise.all([
        page.waitForURL(/\/admin\/products\/update/),
        page.getByTestId('product-bundle-edit-submit').click(),
      ]);
      expect(await ddevMysql(`SELECT max_quantity FROM product_bundle_item WHERE product_sale_elements_id = ${world.lock.saleElementsId};`)).toMatch(/^5(\.0+)?$/);

      // back to the plan's 0..3
      await openComposition(page, world);
      await (await itemRow(page, world.lock)).locator('[data-item-id]').first().click();
      await page.getByTestId('product-bundle-edit-max').fill('3');
      await Promise.all([
        page.waitForURL(/\/admin\/products\/update/),
        page.getByTestId('product-bundle-edit-submit').click(),
      ]);
      expect(await ddevMysql(`SELECT max_quantity FROM product_bundle_item WHERE product_sale_elements_id = ${world.lock.saleElementsId};`)).toMatch(/^3(\.0+)?$/);
    });

    test('sort: dragging the lock above the bike reorders the components', async ({ page }) => {
      await openComposition(page, world);
      const rows = page.locator('[data-testid="datatable-product-bundle-items-row"]');
      await (await itemRow(page, world.lock)).dragTo(await itemRow(page, world.bike));
      await page.waitForLoadState('networkidle');
      await expect(rows.first()).toContainText(world.lock.title, { timeout: 10_000 });
      const order = await ddevMysql(
        `SELECT p.ref FROM product_bundle_item i JOIN product_sale_elements s ON s.id = i.product_sale_elements_id JOIN product p ON p.id = s.product_id WHERE i.bundle_product_id = ${world.pack.productId} ORDER BY i.position, i.id;`,
      );
      expect(order.split('\n')[0]).toBe(world.lock.ref);
    });

    test('a bundle cannot be a component: refused with a readable message', async ({ page }) => {
      await openComposition(page, world);
      // The picker does not list it: the server refusal is reached by a combination the page never offers.
      await page.getByTestId('product-bundle-category').selectOption(String(world.categoryId));
      const combination = page.getByTestId('product-bundle-combination');
      await combination.evaluate((select, value) => {
        const option = document.createElement('option');
        option.value = String(value);
        option.textContent = 'forced';
        (select as HTMLSelectElement).appendChild(option);
        (select as HTMLSelectElement).value = String(value);
        select.closest('.d-none')?.classList.remove('d-none');
      }, strangerBundle.saleElementsId);
      await Promise.all([
        page.waitForURL(/\/admin\/products\/update/),
        page.getByTestId('product-bundle-add').click(),
      ]);
      const refusal = page.locator('.alert-danger, [role="alert"].alert-danger, .toast-danger').first();
      await expect(refusal).toContainText(/bundle/i);
      // The product is named by its reference, the one printed in the catalogue, not by its id.
      await expect(refusal).toContainText(`Product ${strangerBundle.ref} is a bundle: a bundle cannot hold another bundle.`);
      expect(await ddevMysql(`SELECT COUNT(*) FROM product_bundle_item WHERE bundle_product_id = ${world.pack.productId};`)).toBe('3');
    });

    test('product list: Bundle badge and filter', async ({ page }) => {
      await page.goto(`/admin/products?q=PW-BUNDLE-${world.tag}`);
      const badges = page.getByTestId('bo-product-bundle-badge');
      // The pack and the other pack carry the badge, the bike, helmet and lock do not.
      await expect(badges).toHaveCount(2);

      await page.goto(`/admin/products?q=PW-BUNDLE-${world.tag}`);
      await page.getByTestId('product-advanced-toggle').click();
      await page.getByTestId('product-filter-bundle').selectOption('with');
      await Promise.all([
        page.waitForURL(/bundle=with/),
        page.getByTestId('product-search-submit').click(),
      ]);
      const rows = page.getByTestId('datatable-products-row');
      await expect(rows).toHaveCount(2);
      await expect(rows.filter({ hasText: world.bike.ref })).toHaveCount(0);

      await page.goto(`/admin/products?q=PW-BUNDLE-${world.tag}&bundle=without`);
      await expect(page.getByTestId('datatable-products-row')).toHaveCount(3);
      await expect(page.getByTestId('bo-product-bundle-badge')).toHaveCount(0);
    });

    test('dashboard: Unsellable bundles line when the helmet is out of stock, then offline', async ({ page }) => {
      const read = async (): Promise<number> => {
        await page.goto('/admin');
        const line = page.getByTestId('dashboard-unsellable-bundles');
        return (await line.count()) === 0 ? 0 : Number((await line.locator('.badge').innerText()).trim());
      };
      const baseline = await read();
      try {
        await setStock(world.helmet, 0);
        expect(await read()).toBeGreaterThan(baseline);
        await expect(page.getByTestId('dashboard-unsellable-bundles')).toContainText(/Unsellable bundles/i);
        await setStock(world.helmet, 5);
        expect(await read()).toBe(baseline);

        await setProductVisible(world.helmet, false);
        expect(await read()).toBeGreaterThan(baseline);
        await page.goto(`${editUrl(world)}&current_tab=bundle`);
        await expect(page.getByTestId('product-bundle-state-component-unavailable')).toBeVisible();
      } finally {
        await setStock(world.helmet, 5);
        await setProductVisible(world.helmet, true);
      }
      expect(await read()).toBe(baseline);
    });
  });
}
