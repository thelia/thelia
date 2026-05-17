import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';

test.describe('Back-office — feature list (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('feature list page loads with DataTable and create button', async ({ page }) => {
    await page.goto('/admin/configuration/features');
    await expect(page.getByTestId('features-page')).toBeVisible();
    await expect(page.getByTestId('feature-create-button')).toBeVisible();
  });

  test('clicking create feature opens modale with form', async ({ page }) => {
    await page.goto('/admin/configuration/features');
    await page.getByTestId('feature-create-button').click();
    await expect(page.getByTestId('feature-create-form')).toBeVisible();
    await expect(page.getByTestId('feature-create-submit')).toBeVisible();
  });

  test('feature edit page loads with form and values section', async ({ page }) => {
    await page.goto('/admin/configuration/features/update?feature_id=1');
    await expect(page.getByTestId('feature-edit-page')).toBeVisible();
    await expect(page.getByTestId('feature-edit-form')).toBeVisible();
    await expect(page.getByTestId('featureav-create-button')).toBeVisible();
  });
});
