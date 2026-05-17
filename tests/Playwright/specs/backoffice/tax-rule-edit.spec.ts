import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { TaxRuleEditPage } from '../../poms/backoffice/tax-rule-edit-page';

test.describe('Back-office — Tax rule edition (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('tax rule edit page loads with form and matrix section', async ({ page }) => {
    const editPage = new TaxRuleEditPage(page, 1);
    await editPage.goto();
    await editPage.expectLoaded();

    await expect(editPage.form).toBeVisible();
    await expect(editPage.titleHeading).toBeVisible();
    await expect(editPage.submitButton).toBeVisible();
    await editPage.taxesTab.click();
    await expect(editPage.matrixSection).toBeVisible();
  });

  test('unknown tax rule id redirects to list', async ({ page }) => {
    await page.goto('/admin/configuration/taxes_rules/update/99999');
    await expect(page).toHaveURL(/\/admin\/configuration\/taxes_rules$/);
  });

  test('JSON specs endpoint returns taxRules + specifications', async ({ page }) => {
    const response = await page.request.get('/admin/configuration/taxes_rules/specs/1');
    expect(response.status()).toBe(200);
    const payload = await response.json();
    expect(payload).toHaveProperty('taxRules');
    expect(payload).toHaveProperty('specifications');
    expect(Array.isArray(payload.taxRules)).toBe(true);
    expect(Array.isArray(payload.specifications)).toBe(true);
  });
});
