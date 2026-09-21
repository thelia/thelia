import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';

test.describe('Back-office — modules + module hooks (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('modules list loads, and offers the install form', async ({ page }) => {
    await page.goto('/admin/modules');
    await expect(page.getByTestId('modules-page')).toBeVisible();

    // The form lives in a dialog: what the page offers is the button that opens it.
    await page.getByTestId('module-install-open').click();
    await expect(page.getByTestId('module-install-form')).toBeVisible();
  });

  test('module hooks page loads with create form and table', async ({ page }) => {
    await page.goto('/admin/module-hooks');
    await expect(page.getByTestId('module-hooks-page')).toBeVisible();
    await expect(page.getByTestId('module-hook-create-form')).toBeVisible();
    await expect(page.getByTestId('module-hooks-table')).toBeVisible();
  });
});
