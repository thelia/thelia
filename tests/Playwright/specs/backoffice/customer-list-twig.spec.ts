import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { CustomerListTwigPage } from '../../poms/backoffice/customer-list-twig-page';

test.describe('Back-office — customer list (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('customer list page loads with DataTable and create button', async ({ page }) => {
    const listPage = new CustomerListTwigPage(page);
    await listPage.goto();
    await listPage.expectLoaded();

    await expect(listPage.count).toBeVisible();
    await expect(listPage.createButton).toBeVisible();
    await expect(listPage.searchInput).toBeVisible();
  });

  test('demo dataset contains at least one customer row', async ({ page }) => {
    const listPage = new CustomerListTwigPage(page);
    await listPage.goto();
    await expect(listPage.rows.first()).toBeVisible({ timeout: 10_000 });
    expect(await listPage.rows.count()).toBeGreaterThan(0);
  });

  test('search filters the customer list', async ({ page }) => {
    const listPage = new CustomerListTwigPage(page);
    await listPage.goto();

    await listPage.searchInput.fill('zzz-non-existent-needle');
    await listPage.searchSubmit.click();
    await expect(page).toHaveURL(/q=zzz/);
    expect(await listPage.rows.count()).toBe(0);

    await listPage.searchClear.click();
    await expect(page).toHaveURL(/\/admin\/customers$/);
    expect(await listPage.rows.count()).toBeGreaterThan(0);
  });

  test('clicking create customer opens modale with form', async ({ page }) => {
    const listPage = new CustomerListTwigPage(page);
    await listPage.goto();

    await listPage.createButton.click();

    await expect(listPage.createForm).toBeVisible();
    await expect(listPage.createSubmit).toBeVisible();
  });

  test('customer delete modale is rendered on page (CSRF-protected form)', async ({ page }) => {
    const listPage = new CustomerListTwigPage(page);
    await listPage.goto();

    await expect(listPage.deleteForm).toBeAttached();
  });
});
