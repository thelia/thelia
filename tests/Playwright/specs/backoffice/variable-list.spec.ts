import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { VariableListPage } from '../../poms/backoffice/variable-list-page';

test.describe('Back-office — variable list (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only — switch the back-office template to default-twig and re-run with BO_TEMPLATE=default-twig.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  test('variable list page loads', async ({ page }) => {
    const list = new VariableListPage(page);
    await list.goto();
    await list.expectLoaded();
  });

  test('demo dataset shows at least one variable row', async ({ page }) => {
    const list = new VariableListPage(page);
    await list.goto();
    await expect(list.rows.first()).toBeVisible({ timeout: 10_000 });
    expect(await list.rows.count()).toBeGreaterThan(0);
  });

  test('update-values form wraps the datatable', async ({ page }) => {
    const list = new VariableListPage(page);
    await list.goto();
    await expect(list.updateValuesForm).toBeVisible();
    await expect(list.updateValuesSubmit).toBeVisible();
  });

  test('create button opens the create modal', async ({ page }) => {
    const list = new VariableListPage(page);
    await list.goto();
    await list.createButton.click();
    await expect(list.createForm).toBeVisible();
    await expect(list.createSubmit).toBeVisible();
  });

  test('inline value input is editable when row is not secured/env-overridden', async ({ page }) => {
    const list = new VariableListPage(page);
    await list.goto();
    const firstInlineInput = page.locator('[data-testid^="variable-inline-value-"]').first();
    await expect(firstInlineInput).toBeVisible();
    await expect(firstInlineInput).toBeEditable();
  });
});
