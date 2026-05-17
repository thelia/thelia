import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { LanguageListPage } from '../../poms/backoffice/language-list-page';

test.describe('Back-office — language list (BO Twig)', () => {
  // Active back-office template is set via `bin/console template:set backOffice <name>`.
  // Pass `BO_TEMPLATE=default-twig` when running Playwright against the new template; default skips this suite.
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only — switch the back-office template to default-twig and re-run with BO_TEMPLATE=default-twig.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  test('language list page loads', async ({ page }) => {
    const list = new LanguageListPage(page);
    await list.goto();
    await list.expectLoaded();
  });

  test('demo dataset shows at least one language row', async ({ page }) => {
    const list = new LanguageListPage(page);
    await list.goto();
    await expect(list.rows.first()).toBeVisible({ timeout: 10_000 });
    expect(await list.rows.count()).toBeGreaterThan(0);
  });

  test('default behavior + url forms are present', async ({ page }) => {
    const list = new LanguageListPage(page);
    await list.goto();
    await expect(list.defaultBehaviorForm).toBeVisible();
    await expect(list.urlForm).toBeVisible();
  });

  test('create button opens the create modal', async ({ page }) => {
    const list = new LanguageListPage(page);
    await list.goto();
    await list.createButton.click();
    await expect(list.createForm).toBeVisible();
    await expect(list.createSubmit).toBeVisible();
  });
});
