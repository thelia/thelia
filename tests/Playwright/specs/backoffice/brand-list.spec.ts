import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { BrandListPage } from '../../poms/backoffice/brand-list-page';

test.describe('Back-office — brand list', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default',
    'Legacy Smarty markup; not applicable under default-twig.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  test('brand list page loads', async ({ page }) => {
    const brandList = new BrandListPage(page);
    await brandList.goto();
    await brandList.expectLoaded();
  });

  test('demo dataset contains at least one brand row', async ({ page }) => {
    const brandList = new BrandListPage(page);
    await brandList.goto();
    await expect(brandList.rows.first()).toBeVisible({ timeout: 10_000 });
    expect(await brandList.rows.count()).toBeGreaterThan(0);
  });
});
