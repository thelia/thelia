import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { TaxEditPage } from '../../poms/backoffice/tax-edit-page';

const PRICE_PERCENT = 'Thelia-Domain-Taxation-TaxEngine-TaxType-PricePercentTaxType';
const FIX_AMOUNT = 'Thelia-Domain-Taxation-TaxEngine-TaxType-FixAmountTaxType';

test.describe('Back-office — Tax edition (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('tax edit page loads with title and type select', async ({ page }) => {
    const taxPage = new TaxEditPage(page, 1);
    await taxPage.goto();
    await taxPage.expectLoaded();

    await expect(taxPage.form).toBeVisible();
    await expect(taxPage.titleHeading).toContainText(/French.*VAT/i);
    await expect(taxPage.typeSelect).toBeVisible();
    await expect(taxPage.typeSelect).toHaveValue(PRICE_PERCENT);
    await expect(taxPage.titleInput).toHaveValue(/French.*VAT/i);
  });

  test('changing type select toggles requirement groups visibility', async ({ page }) => {
    const taxPage = new TaxEditPage(page, 1);
    await taxPage.goto();

    await expect(taxPage.requirementGroup(PRICE_PERCENT)).toBeVisible();
    await expect(taxPage.requirementGroup(FIX_AMOUNT)).toBeHidden();

    await taxPage.typeSelect.selectOption(FIX_AMOUNT);

    await expect(taxPage.requirementGroup(FIX_AMOUNT)).toBeVisible();
    await expect(taxPage.requirementGroup(PRICE_PERCENT)).toBeHidden();
  });

  test('unknown tax id redirects to taxes list', async ({ page }) => {
    await page.goto('/admin/configuration/taxes/update/99999');
    await expect(page).toHaveURL(/.*\/admin\/configuration\/taxes_rules/);
  });
});
