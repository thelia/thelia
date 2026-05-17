import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { ModuleListPage } from '../../poms/backoffice/module-list-page';

test.describe('Back-office — module list', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default',
    'Legacy Smarty markup; not applicable under default-twig.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  test('module list page loads', async ({ page }) => {
    const moduleList = new ModuleListPage(page);
    await moduleList.goto();
    await moduleList.expectLoaded();
  });

  test('three module type blocks are rendered', async ({ page }) => {
    const moduleList = new ModuleListPage(page);
    await moduleList.goto();
    await expect(moduleList.classicBlock).toBeVisible();
    await expect(moduleList.paymentBlock).toBeVisible();
    await expect(moduleList.deliveryBlock).toBeVisible();
  });
});
