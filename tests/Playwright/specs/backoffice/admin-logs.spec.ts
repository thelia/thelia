import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { AdminLogsPage } from '../../poms/backoffice/admin-logs-page';

test.describe('Back-office — admin logs (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only — switch the back-office template to default-twig and re-run with BO_TEMPLATE=default-twig.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  test('admin logs page loads with filter form', async ({ page }) => {
    const logs = new AdminLogsPage(page);
    await logs.goto();
    await logs.expectLoaded();
    await expect(logs.showButton).toBeVisible();
  });

  test('default date range is pre-filled (last 7 days)', async ({ page }) => {
    const logs = new AdminLogsPage(page);
    await logs.goto();
    const fromValue = await logs.fromDate.inputValue();
    const toValue = await logs.toDate.inputValue();
    expect(fromValue).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(toValue).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(fromValue < toValue || fromValue === toValue).toBeTruthy();
  });

  test('filter sections render admins / resources / modules checkboxes', async ({ page }) => {
    const logs = new AdminLogsPage(page);
    await logs.goto();
    await expect(logs.adminsBlock).toBeVisible();
    await expect(logs.resourcesBlock).toBeVisible();
    await expect(logs.modulesBlock).toBeVisible();
  });

  test('submitting the form renders the results block', async ({ page }) => {
    const logs = new AdminLogsPage(page);
    await logs.goto();
    await logs.showButton.click();
    await expect(logs.results).toBeVisible();
  });
});
