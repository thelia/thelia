import { test, expect, type Page } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { ddevMysql } from '../../helpers/db';

const PREFIX = 'E2E-STK-';
const FIRST = `${PREFIX}A`;
const SECOND = `${PREFIX}B`;

async function createPromotion(
  page: Page,
  code: string,
  options: { mode: 'cumulative' | 'stop' | 'alone'; priority: string; excludedLabel?: string },
): Promise<void> {
  await page.goto('/admin/coupon/create');
  await page.locator('#coupon-type').selectOption('thelia.coupon.type.remove_x_amount');
  await page.locator('input[name="coupon_specific[amount]"]').fill('5');
  await page.locator('#coupon-code').fill(code);
  await page.locator('#coupon-title').fill(`Stacking ${code}`);
  await page.getByTestId(`coupon-stacking-${options.mode}`).check();
  await page.getByTestId('coupon-priority').fill(options.priority);
  if (options.excludedLabel) {
    await page.getByTestId('coupon-excluded-select').selectOption({ label: options.excludedLabel });
  }
  await page.getByTestId('coupon-save-close').click();
  await expect(page.getByTestId('coupons-page')).toBeVisible();
}

async function openByCode(page: Page, code: string): Promise<void> {
  const id = await ddevMysql(`SELECT id FROM coupon WHERE code = '${code}' LIMIT 1`);
  expect(id, `promotion ${code} must exist`).not.toBe('');
  await page.goto(`/admin/coupon/update/${id}`);
  await expect(page.getByTestId('coupon-stacking')).toBeVisible();
}

test.describe('Back-office, promotion stacking (BO Twig)', () => {
  test.skip((process.env.BO_TEMPLATE ?? 'default-twig') !== 'default-twig', 'BO Twig only.');

  test.beforeAll(async () => {
    await ddevMysql(`DELETE FROM coupon WHERE code LIKE '${PREFIX}%'`);
  });

  test.afterAll(async () => {
    await ddevMysql(`DELETE FROM coupon WHERE code LIKE '${PREFIX}%'`);
  });

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  test('priority, the three choices, exclusions, symmetry and the sorted list', async ({ page }) => {
    await createPromotion(page, FIRST, { mode: 'cumulative', priority: '20' });
    await createPromotion(page, SECOND, { mode: 'stop', priority: '-5', excludedLabel: `${FIRST} (Stacking ${FIRST})` });

    // Reopened: what was typed is what comes back.
    await openByCode(page, SECOND);
    await expect(page.getByTestId('coupon-stacking-stop')).toBeChecked();
    await expect(page.getByTestId('coupon-priority')).toHaveValue('-5');
    await expect(page.getByTestId('coupon-excluded-select').locator('option:checked')).toHaveCount(1);

    // Symmetry: the first promotion shows the second as incompatible without anyone touching it.
    await openByCode(page, FIRST);
    await expect(page.getByTestId('coupon-stacking-cumulative')).toBeChecked();
    await expect(page.getByTestId('coupon-priority')).toHaveValue('20');
    const selected = page.getByTestId('coupon-excluded-select').locator('option:checked');
    await expect(selected).toHaveCount(1);
    await expect(selected).toContainText(SECOND);

    // The filter hides the non-matching options.
    await page.getByTestId('coupon-excluded-filter').fill('zzz-no-match');
    await expect(page.getByTestId('coupon-excluded-select').locator('option:not([hidden])')).toHaveCount(1);
    await page.getByTestId('coupon-excluded-filter').fill('');

    // Switch each of the three choices and read it back.
    for (const mode of ['alone', 'stop', 'cumulative'] as const) {
      await page.getByTestId(`coupon-stacking-${mode}`).check();
      await page.getByTestId('coupon-save-stay').click();
      await expect(page.getByTestId(`coupon-stacking-${mode}`)).toBeChecked();
    }

    // Clearing the list on this side clears the other one.
    await page.getByTestId('coupon-excluded-select').selectOption([]);
    await page.getByTestId('coupon-save-stay').click();
    await expect(page.getByTestId('coupon-excluded-select').locator('option:checked')).toHaveCount(0);
    await openByCode(page, SECOND);
    await expect(page.getByTestId('coupon-excluded-select').locator('option:checked')).toHaveCount(0);

    // The list sorts on the priority, ascending then descending.
    for (const [direction, expectedFirst] of [['ASC', SECOND], ['DESC', FIRST]] as const) {
      await page.goto(`/admin/coupon?order=priority&direction=${direction}`);
      const rows = page.locator('[data-testid="coupons-page"] tbody tr').filter({ hasText: PREFIX });
      await expect(rows.first()).toContainText(expectedFirst);
    }
  });

  test('a priority that is not a number is refused on the screen and comes back as typed', async ({ page }) => {
    await createPromotion(page, `${PREFIX}C`, { mode: 'alone', priority: '1' });
    await openByCode(page, `${PREFIX}C`);
    // The browser would block a non-number itself: lift that to prove the back end refuses it too.
    await page.evaluate(() => {
      (document.querySelector('[data-testid="coupon-priority"]') as HTMLInputElement).type = 'text';
    });
    await page.getByTestId('coupon-priority').fill('abc');
    await page.getByTestId('coupon-save-stay').click();
    await expect(page.locator('.alert-danger, [role="alert"]').first()).toBeVisible();
    // A number input shows nothing for a non-number: the attribute is what came back.
    await expect(page.getByTestId('coupon-priority')).toHaveAttribute('value', 'abc');
    await expect(page.getByTestId('coupon-stacking-alone')).toBeChecked();
  });
});
