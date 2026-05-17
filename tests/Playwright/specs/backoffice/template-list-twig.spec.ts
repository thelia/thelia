import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';

test.describe('Back-office — product template list (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('template list page loads with DataTable and create button', async ({ page }) => {
    await page.goto('/admin/configuration/templates');
    await expect(page.getByTestId('templates-page')).toBeVisible();
    await expect(page.getByTestId('template-create-button')).toBeVisible();
  });

  test('clicking create template opens modale with form', async ({ page }) => {
    await page.goto('/admin/configuration/templates');
    await page.getByTestId('template-create-button').click();
    await expect(page.getByTestId('template-create-form')).toBeVisible();
    await expect(page.getByTestId('template-create-submit')).toBeVisible();
  });

  test('template edit page loads with general form + features/attributes panels', async ({ page }) => {
    await page.goto('/admin/configuration/templates/update?template_id=1');
    await expect(page.getByTestId('template-edit-page')).toBeVisible();
    await expect(page.getByTestId('template-edit-form')).toBeVisible();
    await expect(page.getByTestId('template-features-table')).toBeVisible();
    await expect(page.getByTestId('template-attributes-table')).toBeVisible();
  });
});
