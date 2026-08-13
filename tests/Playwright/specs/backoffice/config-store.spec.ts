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
    await expect(storePage.siretInput()).toBeVisible();
    await expect(storePage.vatIntracomInput()).toBeVisible();
    await expect(storePage.apeCodeInput()).toBeVisible();
    await expect(storePage.eoriInput()).toBeVisible();
    await expect(storePage.vatExemptCheckbox()).toBeVisible();
    await expect(storePage.registrationExemptCheckbox()).toBeVisible();
    await expect(storePage.legalMentionsTextarea()).toBeVisible();
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

  test('company identifiers are normalized then persisted', async ({ page }) => {
    const storePage = new ConfigStorePage(page);
    await storePage.goto();

    await storePage.siretInput().fill('732 829 320 00074');
    await storePage.vatIntracomInput().fill('fr 40 303 265 045');
    await storePage.apeCodeInput().fill('47.91A');
    await storePage.eoriInput().fill('fr 732 829 320 00074');
    await storePage.vatExemptCheckbox().uncheck();
    await storePage.registrationExemptCheckbox().check();
    await storePage.legalMentionsTextarea().fill('SAS au capital de 10 000 EUR');

    await storePage.saveAndStayButton.click();
    await page.waitForURL('**/admin/configuration/store');

    // Spaces, dots and case are dropped on the way in, so the stored value is canonical.
    await expect(storePage.siretInput()).toHaveValue('73282932000074');
    await expect(storePage.vatIntracomInput()).toHaveValue('FR40303265045');
    await expect(storePage.apeCodeInput()).toHaveValue('4791A');
    await expect(storePage.eoriInput()).toHaveValue('FR73282932000074');
    await expect(storePage.vatExemptCheckbox()).not.toBeChecked();
    await expect(storePage.registrationExemptCheckbox()).toBeChecked();
    await expect(storePage.legalMentionsTextarea()).toHaveValue('SAS au capital de 10 000 EUR');

    await storePage.siretInput().fill('');
    await storePage.vatIntracomInput().fill('');
    await storePage.apeCodeInput().fill('');
    await storePage.eoriInput().fill('');
    await storePage.registrationExemptCheckbox().uncheck();
    await storePage.legalMentionsTextarea().fill('');
    await storePage.saveAndStayButton.click();
    await page.waitForURL('**/admin/configuration/store');
  });

  test('a SIRET with a wrong checksum is rejected', async ({ page }) => {
    const storePage = new ConfigStorePage(page);
    await storePage.goto();

    await storePage.siretInput().fill('73282932000075');
    await storePage.saveAndStayButton.click();

    await expect(storePage.flashError).toBeVisible();
  });

  test('VAT exemption conflicts with an intra-community VAT number', async ({ page }) => {
    const storePage = new ConfigStorePage(page);
    await storePage.goto();

    await storePage.vatIntracomInput().fill('FR40303265045');
    await storePage.vatExemptCheckbox().check();
    await storePage.saveAndStayButton.click();

    await expect(storePage.flashError).toBeVisible();
  });
});
