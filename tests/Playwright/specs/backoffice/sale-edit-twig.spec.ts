import { test, expect, type Locator, type Page } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { ddevMysql } from '../../helpers/db';

/**
 * Reserved sales and countdown (US #153) on the sale edit screen.
 *
 * The test works on a sale it creates itself and deletes at the end, so it never
 * changes the audience of the demo sales the front-office specs rely on.
 */
test.describe('Back-office — reserved sale and countdown (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.beforeEach(async ({ page }) => { await loginAdmin(page); });

  test('a sale can be reserved for named customers and given a countdown', async ({ page }) => {
    const title = `E2E reserved sale ${Date.now()}`;

    await test.step('create a sale to work on', async () => {
      await page.goto('/admin/sales');
      await page.getByTestId('sale-create-btn').click();
      await page.getByTestId('sale-create-form').locator('input[name="thelia_sale_creation[title]"]').fill(title);
      await Promise.all([
        page.waitForURL(/\/admin\/sale\/update\/\d+/),
        page.getByTestId('sale-create-submit').click(),
      ]);
    });

    const saleId = Number(/\/admin\/sale\/update\/(\d+)/.exec(page.url())?.[1]);
    expect(saleId).toBeGreaterThan(0);

    await expect(page.getByTestId('sale-edit-page')).toBeVisible();
    await expect(page.getByTestId('sale-targeting-card')).toBeVisible();
    await expect(page.getByTestId('sale-countdown-card')).toBeVisible();

    await test.step('a public sale hides the settings that only apply to a reserved one', async () => {
      await expect(page.getByTestId('sale-audience-0')).toBeChecked();
      await expect(page.getByTestId('sale-customers-select')).toBeHidden();
      await expect(page.getByTestId('sale-hide-products')).toBeHidden();
      await expect(page.getByTestId('sale-countdown-lead-hours')).toBeHidden();
    });

    await test.step('an end date is needed before a countdown can be asked for', async () => {
      await page.getByTestId('sale-start-date').fill('2026-01-01 00:00:00');
      await page.getByTestId('sale-end-date').fill('2026-12-31 23:59:59');
    });

    const select = page.getByTestId('sale-customers-select');
    let pickedCustomer = '';

    await test.step('reserving the sale reveals the customer picker', async () => {
      await page.getByTestId('sale-audience-1').check();
      await expect(select).toBeVisible();
      await expect(page.getByTestId('sale-hide-products')).toBeVisible();

      const options = select.locator('option');
      const total = await options.count();
      expect(total).toBeGreaterThan(0);

      const label = ((await options.first().textContent()) ?? '').trim();
      pickedCustomer = (await options.first().getAttribute('value')) ?? '';
      expect(pickedCustomer).not.toBe('');

      // The filter hides the options that do not match, client-side.
      await page.getByTestId('sale-customers-filter').fill(label.split(' ')[0]);
      await expect(select.locator('option:not([hidden])')).not.toHaveCount(total);
      await expect(select.locator(`option[value="${pickedCustomer}"]`)).not.toHaveAttribute('hidden', /.*/);

      await select.selectOption([pickedCustomer]);
      await page.getByTestId('sale-hide-products').check();
    });

    await test.step('the lead hours only show up for the mode that uses them', async () => {
      await page.getByTestId('sale-countdown-mode').selectOption('1');
      await expect(page.getByTestId('sale-countdown-lead-hours')).toBeVisible();
      await page.getByTestId('sale-countdown-lead-hours').fill('48');
    });

    await test.step('saving keeps every setting', async () => {
      await Promise.all([
        page.waitForURL(new RegExp(`/admin/sale/update/${saleId}$`)),
        page.getByTestId('sale-edit-submit').click(),
      ]);

      await expect(page.getByTestId('bo-flash-danger')).toHaveCount(0);
      await expect(page.getByTestId('sale-audience-1')).toBeChecked();
      await expect(page.getByTestId('sale-hide-products')).toBeChecked();
      await expect(page.getByTestId('sale-countdown-mode')).toHaveValue('1');
      await expect(page.getByTestId('sale-countdown-lead-hours')).toHaveValue('48');
      await expect(page.getByTestId('sale-customers-select')).toHaveValues([pickedCustomer]);
    });

    await test.step('switching the countdown mode clears the number of hours', async () => {
      await page.getByTestId('sale-countdown-mode').selectOption('2');
      await expect(page.getByTestId('sale-countdown-lead-hours')).toBeHidden();
      await Promise.all([
        page.waitForURL(new RegExp(`/admin/sale/update/${saleId}$`)),
        page.getByTestId('sale-edit-submit').click(),
      ]);

      await expect(page.getByTestId('bo-flash-danger')).toHaveCount(0);
      await expect(page.getByTestId('sale-countdown-mode')).toHaveValue('2');
      expect(await ddevMysql(`SELECT countdown_lead_hours FROM sale WHERE id = ${saleId}`)).toBe('NULL');

      // Back to the hours mode for the refusal check below.
      await page.getByTestId('sale-countdown-mode').selectOption('1');
      await page.getByTestId('sale-countdown-lead-hours').fill('48');
      await Promise.all([
        page.waitForURL(new RegExp(`/admin/sale/update/${saleId}$`)),
        page.getByTestId('sale-edit-submit').click(),
      ]);
      await expect(page.getByTestId('sale-countdown-lead-hours')).toHaveValue('48');
    });

    await test.step('the list flags the sale as reserved, and for how many customers', async () => {
      await page.goto('/admin/sales');
      const badge = page.getByTestId(`sale-reserved-badge-${saleId}`);
      await expect(badge).toBeVisible();
      await expect(badge).toHaveAttribute('data-reserved-count', '1');
      await expect(badge).toContainText('1');
      // The delete action must carry the row id the confirm dialog copies into its form.
      await expect(page.locator(`tr[data-row-id="${saleId}"] [data-testid="datatable-action-delete"]`).first())
        .toHaveAttribute('data-sale-id', String(saleId));
    });

    await test.step('a sale left reserved for nobody is flagged as an alert', async () => {
      // sale_customer.customer_id cascades on delete, so purging a customer empties the
      // audience of a reserved sale without touching the sale: the shop owner has no
      // other way of noticing that the operation went invisible. Reproduce it at the
      // source rather than through the back office, which refuses to save that state.
      await ddevMysql(`DELETE FROM sale_customer WHERE sale_id = ${saleId}`);

      await page.goto('/admin/sales');
      const badge = page.getByTestId(`sale-reserved-badge-${saleId}`);
      await expect(badge).toBeVisible();
      await expect(badge).toHaveAttribute('data-reserved-count', '0');
      await expect(badge).toHaveClass(/bg-danger/);
      await expect(badge).toContainText(/no customer|aucun client/i);

      await page.goto(`/admin/sale/update/${saleId}`);
      await expect(page.getByTestId('sale-reserved-empty')).toBeVisible();
      await expect(page.getByTestId('sale-customers-count')).toHaveCount(0);

      // Give the sale its audience back: the steps below save it, which a sale reserved
      // for nobody is not allowed to do.
      await ddevMysql(
        `INSERT INTO sale_customer (sale_id, customer_id) VALUES (${saleId}, ${pickedCustomer})`,
      );
      await page.goto(`/admin/sale/update/${saleId}`);
      await expect(page.getByTestId('sale-customers-count')).toHaveAttribute('data-reserved-count', '1');
    });

    await test.step('a countdown without an end date is refused, and says why', async () => {
      await page.goto(`/admin/sale/update/${saleId}`);
      await page.getByTestId('sale-end-date').fill('');
      await page.getByTestId('sale-countdown-mode').selectOption('2');
      await Promise.all([
        page.waitForURL(new RegExp(`/admin/sale/update/${saleId}$`)),
        page.getByTestId('sale-edit-submit').click(),
      ]);

      const flash = page.getByTestId('bo-flash-danger');
      await expect(flash).toBeVisible();
      await expect(flash).toContainText(/countdown|compte à rebours/i);
      await expect(page.getByTestId('sale-countdown-mode')).toHaveValue('1');
    });

    await test.step('delete the sale it created', async () => {
      await page.goto('/admin/sales');
      // The confirm dialog is always in the page: reuse its tokenized action instead of
      // driving the modal, which the overflow menu may hold at narrower widths.
      const action = await page.getByTestId('sale-delete-form').getAttribute('action');
      await page.goto(`${action}&sale_id=${saleId}`);
      await expect(page.locator(`tr[data-row-id="${saleId}"]`)).toHaveCount(0);
    });
  });
});

/**
 * Product selection of the sale edit screen: client-side filter, live count,
 * select / deselect over what the filter shows, and the guarantee that loading
 * a category never overwrites a manual tick.
 *
 * Read-only by design: every behaviour under test happens in the browser, so the
 * test never saves the sale it inspects and leaves the demo data untouched — it
 * asserts that at the end.
 */
test.describe('Back-office — sale product selection (BO Twig)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default-twig',
    'BO Twig only.',
  );

  test.use({ viewport: { width: 1440, height: 1000 } });

  const shotsDir = process.env.SHOTS_DIR ?? '';
  const shoot = async (page: Page, name: string): Promise<void> => {
    if (shotsDir === '') {
      return;
    }
    await page.getByTestId('sale-product-filter').scrollIntoViewIfNeeded();
    await page.screenshot({ path: `${shotsDir}/${name}.png`, fullPage: false });
  };

  // The row marker, not the product id: the attributes button of a row carries that
  // id too.
  const rows = (page: Page): Locator => page.getByTestId('sale-product-zone').locator('[data-sale-product-row]');
  const visibleRows = (page: Page): Locator =>
    page.getByTestId('sale-product-zone').locator('[data-sale-product-row]:not(.d-none)');
  const selectedCount = async (page: Page): Promise<string | null> =>
    page.getByTestId('sale-product-count').getAttribute('data-selected-count');

  test('the loaded products can be filtered, counted and ticked in bulk without losing a manual choice', async ({ page }) => {
    // The sale that already covers the most products: the screen has to tell what
    // it covers from what a load has just added.
    const saleId = Number(
      await ddevMysql('SELECT sale_id FROM sale_product GROUP BY sale_id ORDER BY COUNT(*) DESC, sale_id LIMIT 1'),
    );
    expect(saleId).toBeGreaterThan(0);

    const covered = (await ddevMysql(`SELECT product_id FROM sale_product WHERE sale_id = ${saleId} ORDER BY product_id`))
      .split('\n')
      .map((id) => Number(id.trim()))
      .filter((id) => id > 0);
    expect(covered.length).toBeGreaterThan(1);

    await loginAdmin(page);
    await page.goto(`/admin/sale/update/${saleId}`);
    await expect(page.getByTestId('sale-product-zone')).toBeVisible();

    const firstCovered = covered[0];
    const secondCovered = covered[1];
    const firstCheck = page.getByTestId(`sale-product-check-${firstCovered}`);
    const secondCheck = page.getByTestId(`sale-product-check-${secondCovered}`);

    await test.step('the persisted products are flagged as already covered, and counted', async () => {
      await expect(rows(page)).toHaveCount(covered.length);
      await expect(page.getByTestId(`sale-product-covered-${firstCovered}`)).toBeVisible();
      await expect(page.getByTestId('sale-product-covered-' + firstCovered)).toContainText(/already covered|déjà couvert/i);
      expect(await selectedCount(page)).toBe(String(covered.length));
      await expect(page.getByTestId('sale-product-count')).toContainText(String(covered.length));
      await shoot(page, 'covered-badge');
    });

    const filter = page.getByTestId('sale-product-filter');
    const firstRef = ((await rows(page).filter({ has: firstCheck }).locator('code').textContent()) ?? '').trim();
    expect(firstRef).not.toBe('');

    await test.step('the filter narrows the list without dropping anything from the form', async () => {
      await filter.fill(firstRef);
      await expect(visibleRows(page)).toHaveCount(1);
      // Hidden rows keep their checkbox: the count stays the total, and says how
      // many the filter shows.
      expect(await selectedCount(page)).toBe(String(covered.length));
      await expect(page.getByTestId('sale-product-count')).toHaveAttribute('data-shown-count', '1');
      await expect(page.getByTestId('sale-product-no-match')).toBeHidden();
      await shoot(page, 'filter-active');

      // The box sits inside the sale form: Enter must narrow the list, not save.
      const before = page.url();
      await filter.press('Enter');
      await expect(page.getByTestId('sale-product-count')).toHaveAttribute('data-shown-count', '1');
      expect(page.url()).toBe(before);

      await filter.fill('zzz-no-such-product');
      await expect(visibleRows(page)).toHaveCount(0);
      await expect(page.getByTestId('sale-product-no-match')).toBeVisible();

      await filter.fill('');
      await expect(visibleRows(page)).toHaveCount(covered.length);
      await expect(page.getByTestId('sale-product-no-match')).toBeHidden();
    });

    await test.step('deselect and select all only touch the rows the filter shows', async () => {
      await filter.fill(firstRef);
      await page.getByTestId('sale-product-deselect-all').click();
      await expect(firstCheck).not.toBeChecked();
      await expect(secondCheck).toBeChecked();
      expect(await selectedCount(page)).toBe(String(covered.length - 1));
      await shoot(page, 'deselect-all-filtered');

      await page.getByTestId('sale-product-select-all').click();
      await expect(firstCheck).toBeChecked();
      expect(await selectedCount(page)).toBe(String(covered.length));

      // Everything visible: the actions then carry the whole list.
      await filter.fill('');
      await page.getByTestId('sale-product-deselect-all').click();
      expect(await selectedCount(page)).toBe('0');
      await expect(page.getByTestId('sale-product-count')).toContainText('0');
      await shoot(page, 'all-deselected');

      await page.getByTestId('sale-product-select-all').click();
      expect(await selectedCount(page)).toBe(String(covered.length));
    });

    await test.step('loading a category merges: a manual untick survives it', async () => {
      await firstCheck.uncheck();
      expect(await selectedCount(page)).toBe(String(covered.length - 1));

      await page.getByTestId('sale-load-products').click();
      await expect(rows(page)).not.toHaveCount(covered.length);
      const loaded = await rows(page).count();
      expect(loaded).toBeGreaterThan(covered.length);

      // The row was already there, so the load left it exactly as it was found.
      await expect(firstCheck).not.toBeChecked();
      expect(await selectedCount(page)).toBe(String(loaded - 1));
      // The newly loaded products are not flagged as covered by the sale.
      await expect(page.getByTestId('sale-product-zone').locator('[data-persisted="1"]')).toHaveCount(covered.length);
      await expect(page.getByTestId('sale-product-zone').locator('.badge.text-bg-light')).toHaveCount(covered.length);

      // Untick one of the freshly loaded rows, then load a further category: both
      // manual choices have to come back untouched.
      const freshCheck = page
        .getByTestId('sale-product-zone')
        .locator('[data-sale-product-row]:not([data-persisted])')
        .first()
        .locator('input[type="checkbox"]');
      await freshCheck.uncheck();
      expect(await selectedCount(page)).toBe(String(loaded - 2));

      const categories = page.getByTestId('sale-categories');
      const already = await categories.evaluate(
        (select) => Array.from((select as HTMLSelectElement).selectedOptions).map((option) => option.value),
      );
      const extra = await categories.evaluate(
        (select, picked) => Array.from((select as HTMLSelectElement).options)
          .map((option) => option.value)
          .filter((value) => !(picked as string[]).includes(value)),
        already,
      );
      expect(extra.length).toBeGreaterThan(0);

      await categories.selectOption([...already, ...extra]);
      await page.getByTestId('sale-load-products').click();
      await expect(rows(page)).not.toHaveCount(loaded);
      const merged = await rows(page).count();
      expect(merged).toBeGreaterThan(loaded);

      await expect(firstCheck).not.toBeChecked();
      await expect(freshCheck).not.toBeChecked();
      expect(await selectedCount(page)).toBe(String(merged - 2));
      // No row was rebuilt, so nothing is there twice either.
      await expect(rows(page).filter({ has: firstCheck })).toHaveCount(1);
      await shoot(page, 'merged-load');
    });

    await test.step('nothing was persisted: the sale still covers what it covered', async () => {
      expect(await ddevMysql(`SELECT COUNT(*) FROM sale_product WHERE sale_id = ${saleId}`))
        .toBe(String(covered.length));
      await page.goto(`/admin/sale/update/${saleId}`);
      await expect(rows(page)).toHaveCount(covered.length);
    });
  });
});
