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

  // Guards the wiring of the "default" radio: the Stimulus values it reads must
  // sit on the element carrying data-controller, not on the input below it. When
  // they drift apart the radio still ticks under the cursor and nothing is saved.
  test('picking another language as default saves it', async ({ page }) => {
    const list = new LanguageListPage(page);
    await list.goto();
    await list.expectLoaded();

    const initialDefault = await list.checkedDefaultValue();
    expect(initialDefault, 'a language must be flagged as default').not.toBeNull();

    const values = await list.defaultRadios.evaluateAll((inputs) =>
      inputs.map((input) => (input as HTMLInputElement).value),
    );
    const other = values.find((value) => value !== initialDefault);
    expect(other, 'the demo dataset must ship more than one language').toBeTruthy();

    // Read the outcome from a fresh render: right after the click the ticked
    // radio only reflects what the cursor did, saved or not.
    await list.defaultRadioFor(Number(other)).check();
    await list.goto();
    expect(await list.checkedDefaultValue()).toBe(other);

    await list.defaultRadioFor(Number(initialDefault)).check();
    await list.goto();
    expect(await list.checkedDefaultValue()).toBe(initialDefault);
  });

  test('create button opens the create modal', async ({ page }) => {
    const list = new LanguageListPage(page);
    await list.goto();
    await list.createButton.click();
    await expect(list.createForm).toBeVisible();
    await expect(list.createSubmit).toBeVisible();
  });
});
