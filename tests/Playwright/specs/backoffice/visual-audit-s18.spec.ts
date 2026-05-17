import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import * as fs from 'fs';

const SCREENS_DIR = 'test-results/visual-audit-s18';

test.describe.configure({ mode: 'serial' });

test.describe('Visual audit S18 — BO Twig screens', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeAll(async () => {
    if (!fs.existsSync(SCREENS_DIR)) {
      fs.mkdirSync(SCREENS_DIR, { recursive: true });
    }
  });

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
    await page.setViewportSize({ width: 1440, height: 900 });
  });

  const pages = [
    { name: 'home', url: '/admin' },
    { name: 'languages', url: '/admin/configuration/languages' },
    { name: 'currencies', url: '/admin/configuration/currencies' },
    { name: 'variables', url: '/admin/configuration/variables' },
    { name: 'profiles', url: '/admin/configuration/profiles' },
    { name: 'administrators', url: '/admin/configuration/administrators' },
    { name: 'configuration-index', url: '/admin/configuration' },
    { name: 'store', url: '/admin/configuration/store' },
    { name: 'taxes', url: '/admin/configuration/taxes' },
    { name: 'tax-rules', url: '/admin/configuration/taxes_rules' },
    { name: 'tax-rule-edit', url: '/admin/configuration/taxes_rules/update/1?tab=taxes' },
    { name: 'customers', url: '/admin/customers' },
    { name: 'customer-edit', url: '/admin/customer/update?customer_id=1' },
    { name: 'brands', url: '/admin/brand' },
    { name: 'categories', url: '/admin/categories' },
    { name: 'category-edit', url: '/admin/categories/update?category_id=2' },
    { name: 'features', url: '/admin/configuration/features' },
    { name: 'feature-edit', url: '/admin/configuration/features/update?feature_id=1' },
    { name: 'attributes', url: '/admin/configuration/attributes' },
    { name: 'attribute-edit', url: '/admin/configuration/attributes/update?attribute_id=1' },
    { name: 'templates', url: '/admin/configuration/templates' },
    { name: 'template-edit', url: '/admin/configuration/templates/update?template_id=1' },
    { name: 'products', url: '/admin/products' },
    { name: 'product-edit', url: '/admin/products/update?product_id=1' },
    { name: 'product-edit-attributes', url: '/admin/products/update?product_id=1&current_tab=attributes' },
    { name: 'orders', url: '/admin/orders' },
    { name: 'order-statuses', url: '/admin/configuration/order-status' },
    { name: 'modules', url: '/admin/modules' },
    { name: 'module-hooks', url: '/admin/module-hooks' },
    { name: 'folders', url: '/admin/folders' },
    { name: 'coupons', url: '/admin/coupon' },
    { name: 'coupon-create', url: '/admin/coupon/create' },
    { name: 'coupon-edit', url: '/admin/coupon/update/1' },
    { name: 'mailing-system', url: '/admin/configuration/mailingSystem' },
    { name: 'messages', url: '/admin/configuration/messages' },
    { name: 'message-edit', url: '/admin/configuration/messages/update/1' },
    { name: 'shipping-configuration', url: '/admin/configuration/shipping_configuration' },
    { name: 'shipping-configuration-edit', url: '/admin/configuration/shipping_configuration/update/1' },
    { name: 'shipping-zones', url: '/admin/configuration/shipping_zones' },
    { name: 'shipping-zones-edit', url: '/admin/configuration/shipping_zones/update/1' },
    { name: 'export-list', url: '/admin/export' },
    { name: 'export-view', url: '/admin/export/1' },
    { name: 'import-list', url: '/admin/import' },
    { name: 'import-view', url: '/admin/import/1' },
    { name: 'translations', url: '/admin/configuration/translations' },
    { name: 'translations-fo', url: '/admin/configuration/translations?item_to_translate=fo&item_name=flexy' },
    { name: 'translations-customer-title', url: '/admin/configuration/translations-customers-title' },
    { name: 'hooks-list', url: '/admin/hooks' },
    { name: 'hook-edit', url: '/admin/hook/update/1' },
  ];

  for (const p of pages) {
    test(`screenshot ${p.name}`, async ({ page }) => {
      await page.goto(p.url);
      await page.waitForLoadState('networkidle').catch(() => undefined);
      // Hide Symfony web debug toolbar for cleaner screenshots
      await page.addStyleTag({ content: '#sfToolbar-clear-control, .sf-toolbar, .sf-minitoolbar { display: none !important; }' }).catch(() => undefined);
      await page.screenshot({
        path: `${SCREENS_DIR}/${p.name}.png`,
        fullPage: true,
      });
    });
  }
});
