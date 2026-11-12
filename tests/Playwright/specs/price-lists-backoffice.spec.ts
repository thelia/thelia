import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { registerMember, signedInContext, customerIdOf } from '../helpers/customer-group';
import type { Customer } from '../helpers/customer';
import { loginAdmin } from '../helpers/admin';
import { ddevMysql } from '../helpers/db';
import {
  PL_PREFIX,
  PRODUCTS,
  addPriceListLine,
  assignPriceListToCustomer,
  cardAmount,
  createPriceList,
  pageAmount,
  priceListIdByCode,
  priceListLineCount,
  purgePriceListData,
  remainingPriceListRows,
  taxed,
} from '../helpers/price-list';

/**
 * Price lists, back-office: building a list, importing its lines, the effective price panel.
 *
 *  2. the merchant creates a list in the back-office, adds three lines, assigns it to a customer, and the
 *     customer sees the three negotiated prices on the shop
 *  5. import of a CSV file of two hundred lines: preview with counters and one reason per refused line,
 *     confirmation, report; abandon writes nothing; a file of the wrong format is refused
 *  9. the combinations tab of the product sheet shows the effective prices for a customer picked there
 *
 * Data: customers (e2e-cg- prefix), lists (e2e-pl- prefix); everything is removed in afterAll.
 */
const OWEN = PRODUCTS.owen;
const NIGEL = PRODUCTS.nigel;
const HEATHCLIFF = PRODUCTS.heathcliff;

function newListCode(label: string): string {
  return `${PL_PREFIX}${label}-${Date.now().toString(36)}`;
}

/** Creates a list through the "Add a price list" dialog of the list screen and returns its id. */
async function createListInBackOffice(page: Page, code: string): Promise<number> {
  await page.goto('/admin/pricing/price-lists');
  await page.getByTestId('price-list-create-button').click();
  const dialog = page.locator('#price-list-create-modal');
  await expect(dialog).toBeVisible();
  await dialog.locator('input[name$="[code]"]').fill(code);
  await dialog.locator('input[name$="[title]"]').fill(`E2E ${code}`);
  // The dialog preselects no currency: the first of the alphabetical list is taken unless one is picked.
  await dialog.locator('select[name$="[currency_id]"]').selectOption({ label: 'EUR' });
  await Promise.all([
    page.waitForURL(/\/admin\/pricing\/price-lists\/\d+/),
    dialog.getByTestId('price-list-create-submit').click(),
  ]);
  const id = await priceListIdByCode(code);
  expect(id, `the list ${code} exists`).not.toBeNull();
  return id as number;
}

test.describe('Price lists: back-office', () => {
  test.describe.configure({ mode: 'serial' });

  let merchantCustomer: Customer;
  let panelCustomer: Customer;
  let customerContext: BrowserContext | undefined;
  let adminPage: Page;
  let adminContext: BrowserContext;

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(240_000);
    await purgePriceListData();
    merchantCustomer = await registerMember(browser, null);
    panelCustomer = await registerMember(browser, null);
    adminContext = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport: { width: 1440, height: 900 } });
    adminPage = await adminContext.newPage();
    await loginAdmin(adminPage);
  });

  test.afterAll(async () => {
    await customerContext?.close();
    await adminContext?.close();
    await purgePriceListData();
    expect(await remainingPriceListRows()).toEqual({ lists: 0, lines: 0, assignments: 0, restrictions: 0, overrides: 0, customers: 0, shopMode: 0 });
  });

  test('2. the merchant builds a list of three lines, assigns it to a customer, and the customer sees the three prices', async ({ browser }) => {
    test.setTimeout(240_000);
    const code = newListCode('bo');
    const listId = await createListInBackOffice(adminPage, code);
    const sheet = `/admin/pricing/price-lists/${listId}`;

    // Three lines, one form submission each.
    const lines = [
      { product: OWEN, price: '300' },
      { product: NIGEL, price: '500' },
      { product: HEATHCLIFF, price: '900' },
    ];
    for (const { product, price } of lines) {
      await adminPage.goto(`${sheet}?tab=lines`);
      const form = adminPage.getByTestId('price-list-line-add-form');
      await form.locator('input[name="pse_ref"]').fill(product.pseRef);
      await form.locator('input[name="price"]').fill(price);
      await Promise.all([
        adminPage.waitForURL(/tab=lines|price-lists\/\d+/),
        form.getByTestId('price-list-line-add-submit').click(),
      ]);
    }
    expect(await priceListLineCount(listId)).toBe(3);
    await adminPage.goto(`${sheet}?tab=lines`);
    await expect(adminPage.getByTestId('price-list-lines-total')).toContainText('3');
    await expect(adminPage.getByTestId('price-list-lines-table')).toContainText(OWEN.pseRef);

    // The list goes to the customer: searched by e-mail, then assigned.
    const customerId = await customerIdOf(merchantCustomer.email);
    await adminPage.goto(`${sheet}?tab=assignments`);
    await adminPage.locator('input[name="customer_search"]').fill(merchantCustomer.email);
    await adminPage.getByTestId('price-list-customer-search').locator('button[type="submit"]').click();
    await Promise.all([
      adminPage.waitForURL(/price-lists\/\d+/),
      adminPage.getByTestId(`price-list-customer-add-${customerId}`).click(),
    ]);
    await expect(adminPage.getByTestId(`price-list-customer-${customerId}`)).toBeVisible();

    // The customer on the shop.
    const signed = await signedInContext(browser, merchantCustomer);
    customerContext = signed.context;
    for (const { product, price } of lines) {
      expect(await cardAmount(signed.page, product.listing, product.slug), `${product.slug} listing`).toBe(taxed(Number(price)));
      expect(await pageAmount(signed.page, product.slug), `${product.slug} page`).toBe(taxed(Number(price)));
    }
    // The catalog stays for the rest of the shop.
    expect(await pageAmount(signed.page, PRODUCTS.tina.slug)).toBe(taxed(PRODUCTS.tina.price));
  });

  test.describe('5. import of a CSV file of two hundred lines', () => {
    const REFUSED: Record<number, string> = {
      // data line (1-based, header excluded) -> what is wrong with it
      7: 'unknown reference',
      19: 'negative price',
      33: 'price not a number',
      48: 'minimum quantity zero',
      64: 'discount above one hundred',
      80: 'price and discount together',
      97: 'neither price nor discount',
      123: 'minimum quantity not a number',
      151: 'empty reference',
      188: 'unknown reference again',
    };
    let refs: string[] = [];
    let listId = 0;

    function csvOfTwoHundredLines(): string {
      const quantities = [1, 5, 10, 20];
      const rows: string[] = ['pse_ref,min_quantity,price,discount_percent'];
      for (let index = 1; index <= 200; index++) {
        const ref = refs[Math.floor((index - 1) / quantities.length)];
        const quantity = quantities[(index - 1) % quantities.length];
        const price = (50 + index).toFixed(2);
        switch (REFUSED[index]) {
          case 'unknown reference':
          case 'unknown reference again':
            rows.push(`NOPE-${index},${quantity},${price},`);
            break;
          case 'negative price':
            rows.push(`${ref},${quantity},-5,`);
            break;
          case 'price not a number':
            rows.push(`${ref},${quantity},abc,`);
            break;
          case 'minimum quantity zero':
            rows.push(`${ref},0,${price},`);
            break;
          case 'discount above one hundred':
            rows.push(`${ref},${quantity},,150`);
            break;
          case 'price and discount together':
            rows.push(`${ref},${quantity},${price},10`);
            break;
          case 'neither price nor discount':
            rows.push(`${ref},${quantity},,`);
            break;
          case 'minimum quantity not a number':
            rows.push(`${ref},many,${price},`);
            break;
          case 'empty reference':
            rows.push(`,${quantity},${price},`);
            break;
          default:
            // One valid line in four is a discount of the catalog price, the others a price.
            rows.push(index % 4 === 0 ? `${ref},${quantity},,15` : `${ref},${quantity},${price},`);
        }
      }
      return `${rows.join('\n')}\n`;
    }

    test.beforeAll(async () => {
      refs = (await ddevMysql('SELECT ref FROM product_sale_elements ORDER BY id LIMIT 50')).split('\n');
      expect(refs).toHaveLength(50);
      listId = await createListInBackOffice(adminPage, newListCode('csv'));
    });

    async function uploadCsv(page: Page, name: string, content: string): Promise<void> {
      await page.goto(`/admin/pricing/price-lists/${listId}?tab=lines`);
      await page.locator('#price-list-import-file').setInputFiles({ name, mimeType: 'text/csv', buffer: Buffer.from(content) });
      await page.getByTestId('price-list-import-submit').click();
    }

    test('the preview counts the lines to create and lists each refused line with its reason, and writes nothing', async () => {
      await uploadCsv(adminPage, 'lines.csv', csvOfTwoHundredLines());
      await expect(adminPage).toHaveURL(/\/import\/[0-9a-f]{32}$/);
      await expect(adminPage.getByTestId('price-list-import-notice')).toBeVisible();

      const refusedCount = Object.keys(REFUSED).length;
      await expect(adminPage.getByTestId('price-list-import-created')).toHaveText(String(200 - refusedCount));
      await expect(adminPage.getByTestId('price-list-import-updated')).toHaveText('0');
      await expect(adminPage.getByTestId('price-list-import-errors')).toHaveText(String(refusedCount));

      // One row per refused line, each with a reason; the line number is the one of the file (the header is line 1).
      const table = adminPage.getByTestId('price-list-import-error-table');
      await expect(table.locator('tbody tr')).toHaveCount(refusedCount);
      for (const dataLine of Object.keys(REFUSED).map(Number)) {
        const row = adminPage.getByTestId(`price-list-import-error-${dataLine + 1}`);
        await expect(row, `refused line ${dataLine + 1} (${REFUSED[dataLine]})`).toBeVisible();
        expect((await row.locator('td').nth(1).innerText()).trim().length, `reason of line ${dataLine + 1}`).toBeGreaterThan(5);
      }
      expect(await priceListLineCount(listId), 'nothing is written by the preview').toBe(0);
    });

    test('confirming writes the accepted lines and the report says so', async () => {
      await Promise.all([
        adminPage.waitForURL((url) => !/\/confirm/.test(url.pathname)),
        adminPage.getByTestId('price-list-import-confirm').click(),
      ]);
      const refusedCount = Object.keys(REFUSED).length;
      await expect(adminPage.getByRole('heading', { name: /import report/i })).toBeVisible();
      await expect(adminPage.getByTestId('price-list-import-created')).toHaveText(String(200 - refusedCount));
      await expect(adminPage.getByTestId('price-list-import-errors')).toHaveText(String(refusedCount));
      await expect(adminPage.getByTestId('price-list-import-error-table').locator('tbody tr')).toHaveCount(refusedCount);

      expect(await priceListLineCount(listId)).toBe(200 - refusedCount);
      // A price line and a discount line, as the file said.
      expect(Number(await ddevMysql(`SELECT COUNT(*) FROM price_list_item WHERE price_list_id = ${listId} AND discount_percent IS NOT NULL AND price IS NULL`))).toBeGreaterThan(0);
      expect(Number(await ddevMysql(`SELECT COUNT(*) FROM price_list_item WHERE price_list_id = ${listId} AND price IS NOT NULL AND discount_percent IS NULL`))).toBeGreaterThan(0);

      await adminPage.getByTestId('price-list-import-back').click();
      await expect(adminPage.getByTestId('price-list-lines-total')).toContainText(String(200 - refusedCount));
    });

    test('the same file read again updates the lines instead of creating them', async () => {
      await uploadCsv(adminPage, 'again.csv', csvOfTwoHundredLines());
      await expect(adminPage.getByTestId('price-list-import-created')).toHaveText('0');
      await expect(adminPage.getByTestId('price-list-import-updated')).toHaveText(String(200 - Object.keys(REFUSED).length));
      await adminPage.getByTestId('price-list-import-abandon').click();
      expect(await priceListLineCount(listId), 'abandoning writes nothing').toBe(200 - Object.keys(REFUSED).length);
    });

    test('a file that is not a price list is refused with a message, and nothing is written', async () => {
      const before = await priceListLineCount(listId);
      await uploadCsv(adminPage, 'wrong.csv', 'name,colour\nOwen,red\n');
      await expect(adminPage.locator('body')).toContainText(/unknown column/i);
      await expect(adminPage).not.toHaveURL(/\/import\/[0-9a-f]{32}\/confirm/);
      await expect(adminPage.getByTestId('price-list-import-confirm')).toHaveCount(0);
      expect(await priceListLineCount(listId)).toBe(before);
    });
  });

  test('9. the product sheet shows the effective prices for the customer picked there', async () => {
    const list = await createPriceList({ title: 'E2E panel' });
    await addPriceListLine(list.id, OWEN.pseId, 300, 1);
    await addPriceListLine(list.id, OWEN.pseId, 270, 10);
    await assignPriceListToCustomer(list.id, await customerIdOf(panelCustomer.email));

    await adminPage.goto(`/admin/products/update?product_id=${OWEN.productId}&current_tab=pse`);
    const panel = adminPage.getByTestId('effective-prices-section');
    await expect(panel).toBeVisible();

    // The customer is searched (3 characters at least), then picked.
    await adminPage.getByTestId('effective-prices-search').fill(panelCustomer.email);
    await adminPage.getByTestId('effective-prices-search-form').locator('button[type="submit"]').click();
    await adminPage.getByTestId('effective-prices-results').locator('a').first().click();

    await expect(adminPage).toHaveURL(/effective_customer_id=\d+/);
    await expect(adminPage.getByTestId('effective-prices-subject')).toBeVisible();
    const row = `effective-prices-row-${OWEN.pseId}`;
    await expect(adminPage.getByTestId(row)).toBeVisible();
    await expect(adminPage.getByTestId(`effective-prices-catalog-${OWEN.pseId}`)).toContainText('395');
    await expect(adminPage.getByTestId(`effective-prices-price-${OWEN.pseId}`)).toContainText('300');
    await expect(adminPage.getByTestId(`effective-prices-source-${OWEN.pseId}`)).toContainText(list.code);
    // The admin account speaks its own language ("Yes" / "Sì" / "Oui"): the cell is neither "No" nor the dash.
    const purchasable = adminPage.getByTestId(`effective-prices-purchasable-${OWEN.pseId}`);
    await expect(purchasable).not.toHaveText(/^\s*(no|-)?\s*$/i);
    const tiers = adminPage.getByTestId(`effective-prices-tiers-${OWEN.pseId}`);
    await expect(tiers).toContainText('300');
    await expect(tiers).toContainText('270');

    // A visitor, for comparison: the catalog price.
    await adminPage.getByTestId('effective-prices-visitor').click();
    await expect(adminPage).toHaveURL(/effective_visitor=1/);
    await expect(adminPage.getByTestId('effective-prices-subject')).not.toContainText(panelCustomer.email.slice(0, 20));
    await expect(adminPage.getByTestId(`effective-prices-price-${OWEN.pseId}`)).toContainText('395');
    await expect(adminPage.getByTestId(`effective-prices-tiers-${OWEN.pseId}`)).not.toContainText('270');
  });
});
