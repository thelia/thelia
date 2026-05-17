import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { CurrencyListPage } from '../../poms/backoffice/currency-list-page';

test.describe('Back-office — currency list (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only — switch the back-office template to default-twig and re-run with BO_TEMPLATE=default-twig.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  test('currency list page loads', async ({ page }) => {
    const list = new CurrencyListPage(page);
    await list.goto();
    await list.expectLoaded();
  });

  test('demo dataset shows at least one currency row', async ({ page }) => {
    const list = new CurrencyListPage(page);
    await list.goto();
    await expect(list.rows.first()).toBeVisible({ timeout: 10_000 });
    expect(await list.rows.count()).toBeGreaterThan(0);
  });

  test('update rates + create buttons are present', async ({ page }) => {
    const list = new CurrencyListPage(page);
    await list.goto();
    await expect(list.updateRatesButton).toBeVisible();
    await expect(list.createButton).toBeVisible();
  });

  test('create button opens the create modal', async ({ page }) => {
    const list = new CurrencyListPage(page);
    await list.goto();
    await list.createButton.click();
    await expect(list.createForm).toBeVisible();
    await expect(list.createSubmit).toBeVisible();
  });

  test('rows are draggable for reorder', async ({ page }) => {
    const list = new CurrencyListPage(page);
    await list.goto();
    const first = list.rows.first();
    await expect(first).toHaveAttribute('draggable', 'true');
    await expect(first).toHaveClass(/bo-sortable-row/);
  });
});
