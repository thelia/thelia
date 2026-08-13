import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { TaxesRulesPage } from '../../poms/backoffice/taxes-rules-page';

test.describe('Back-office — Taxes rules list (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('taxes rules page loads with both sections', async ({ page }) => {
    const listPage = new TaxesRulesPage(page);
    await listPage.goto();
    await listPage.expectLoaded();

    await expect(listPage.taxesSection).toBeVisible();
    await expect(listPage.taxRulesSection).toBeVisible();
    await expect(listPage.createTaxButton).toBeVisible();
  });

  test('clicking create tax button opens modale with form', async ({ page }) => {
    const listPage = new TaxesRulesPage(page);
    await listPage.goto();

    await listPage.createTaxButton.click();

    await expect(listPage.createTaxForm).toBeVisible();
    await expect(listPage.createTaxSubmit).toBeVisible();
  });

  test('tax delete modale is rendered on page (CSRF-protected form)', async ({ page }) => {
    const listPage = new TaxesRulesPage(page);
    await listPage.goto();

    await expect(listPage.deleteTaxForm).toBeAttached();
  });

  test('clicking create tax rule button opens modale with form', async ({ page }) => {
    const listPage = new TaxesRulesPage(page);
    await listPage.goto();

    await listPage.createTaxRuleButton.click();

    await expect(listPage.createTaxRuleForm).toBeVisible();
    await expect(listPage.createTaxRuleSubmit).toBeVisible();
  });

  test('tax rule delete modale is rendered on page (CSRF-protected form)', async ({ page }) => {
    const listPage = new TaxesRulesPage(page);
    await listPage.goto();

    await expect(listPage.deleteTaxRuleForm).toBeAttached();
  });

  test('every activated delivery module gets a postage tax rule selector', async ({ page }) => {
    const listPage = new TaxesRulesPage(page);
    await listPage.goto();

    await expect(listPage.postageTaxRuleCard).toBeVisible();
    await expect(listPage.postageTaxRuleForm).toBeVisible();

    const row = listPage.postageTaxRuleRow('CustomDelivery');
    await expect(row).toBeVisible();
    await expect(row.locator('select')).toBeVisible();
  });

  test('a postage tax rule survives its save', async ({ page }) => {
    const listPage = new TaxesRulesPage(page);
    await listPage.goto();

    const select = listPage.postageTaxRuleRow('CustomDelivery').locator('select');
    const taxRule = await select.locator('option:not([value=""])').first().getAttribute('value');
    expect(taxRule).not.toBeNull();

    await select.selectOption(taxRule as string);
    await listPage.postageTaxRuleSave.click();
    await listPage.expectLoaded();

    await expect(listPage.postageTaxRuleRow('CustomDelivery').locator('select')).toHaveValue(
      taxRule as string,
    );

    // Back to the shop-wide rule, so the run leaves no setting behind.
    await listPage.postageTaxRuleRow('CustomDelivery').locator('select').selectOption('');
    await listPage.postageTaxRuleSave.click();
    await listPage.expectLoaded();
  });
});
