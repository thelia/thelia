import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { ConfigStorePage } from '../../poms/backoffice/config-store-page';

test.describe('Back-office — Store configuration (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('store configuration page loads with all sections', async ({ page }) => {
    const storePage = new ConfigStorePage(page);
    await storePage.goto();
    await storePage.expectLoaded();

    await expect(storePage.form).toBeVisible();
    await expect(storePage.storeNameInput()).toBeVisible();
    await expect(storePage.storeEmailInput()).toBeVisible();
    await expect(storePage.storeCountrySelect()).toBeVisible();
    await expect(storePage.saveAndStayButton).toBeVisible();
    await expect(storePage.saveAndCloseButton).toBeVisible();
  });

  test('saving with "stay" mode keeps the user on the store page', async ({ page }) => {
    const storePage = new ConfigStorePage(page);
    await storePage.goto();

    const previousName = await storePage.storeNameInput().inputValue();
    const newName = `${previousName} — Playwright`;
    await storePage.storeNameInput().fill(newName);

    await storePage.saveAndStayButton.click();
    await page.waitForURL('**/admin/configuration/store');

    await expect(storePage.storeNameInput()).toHaveValue(newName);

    await storePage.storeNameInput().fill(previousName);
    await storePage.saveAndStayButton.click();
    await page.waitForURL('**/admin/configuration/store');
  });

  test('blank store name triggers a server-side validation error', async ({ page }) => {
    const storePage = new ConfigStorePage(page);
    await storePage.goto();

    const previousName = await storePage.storeNameInput().inputValue();
    await storePage.form.evaluate((form: HTMLFormElement) => { form.noValidate = true; });
    await storePage.storeNameInput().fill('');
    await storePage.saveAndStayButton.click();

    await expect(storePage.flashError).toBeVisible();

    await storePage.storeNameInput().fill(previousName);
    await storePage.saveAndStayButton.click();
  });
});
