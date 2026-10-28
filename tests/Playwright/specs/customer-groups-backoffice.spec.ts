import { expect, test, type Locator } from '@playwright/test';
import { loginAdmin } from '../helpers/admin';
import {
  DEMO,
  createCategory,
  createGroup,
  customerIdOf,
  deleteCategory,
  groupIdByCode,
  groupIdOfCustomer,
  groupPriceOf,
  newGroupCode,
  purgeCustomerGroupData,
  registerMember,
  signedInContext,
  storedGroup,
} from '../helpers/customer-group';
import { CustomerEditPage } from '../poms/backoffice/customer-edit-page';
import { CustomerGroupEditPage } from '../poms/backoffice/customer-group-edit-page';
import { CustomerGroupListPage } from '../poms/backoffice/customer-group-list-page';
import { CustomerListTwigPage } from '../poms/backoffice/customer-list-twig-page';
import { ProductGroupPricesSection } from '../poms/backoffice/product-group-prices-section';

/**
 * Customer groups, back office (default-twig): the groups screen, the whitelists and the warning
 * of an emptied list, the group on the customer sheet and its filter on the customer list, the
 * group prices of the product sheet and the registration link.
 *
 * Every row is created with the e2e-cg- prefix (SQL or the screens under test) and purged in
 * afterAll. What a screen saved is checked in the database, not only on the page that saved it.
 */
async function selectOptionByText(select: Locator, text: RegExp): Promise<void> {
  const options = await select.locator('option').all();
  for (const option of options) {
    if (text.test((await option.innerText()).trim())) {
      await select.selectOption({ value: (await option.getAttribute('value')) ?? '' });
      return;
    }
  }
  throw new Error(`No option matching ${text} in the select`);
}

test.describe('Customer groups: back office', () => {
  test.describe.configure({ mode: 'serial' });
  test.skip((process.env.BO_TEMPLATE ?? 'default-twig') !== 'default-twig', 'BO Twig only.');

  test.beforeAll(async () => {
    await purgeCustomerGroupData();
  });

  test.afterAll(async () => {
    await purgeCustomerGroupData();
  });

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  test('creates a group with a discount, shown without taxes', async ({ page }) => {
    const list = new CustomerGroupListPage(page);
    await list.goto();
    await list.expectLoaded();

    const code = newGroupCode();
    await list.create({ code, title: `E2E ${code}`, discount: 12, taxed: false });

    await expect.poll(() => groupIdByCode(code), { message: 'the group must exist in the database' }).not.toBeNull();
    const id = (await groupIdByCode(code))!;
    const stored = await storedGroup(id);
    expect(stored.discount).toBe(12);
    expect(stored.displayTaxedPrice).toBe(false);
    expect(stored.restrictsCategories).toBe(false);
    expect(stored.restrictsPaymentModules).toBe(false);

    await list.goto();
    const row = list.rowOf(code);
    await expect(row).toHaveCount(1);
    await expect(row).toContainText('12');
  });

  test('the group sheet saves the discount, the display and the two whitelists', async ({ page }) => {
    const group = await createGroup({ discount: 0, taxed: true });
    const sheet = new CustomerGroupEditPage(page, group.id);
    await sheet.goto();
    await sheet.expectLoaded();

    await sheet.discount.fill('20');
    await sheet.displayTaxedPrice.uncheck();
    await selectOptionByText(sheet.categories, /Sofas$/);
    await selectOptionByText(sheet.paymentModules, /cheque/i);
    await sheet.save();

    const stored = await storedGroup(group.id);
    expect(stored.discount).toBe(20);
    expect(stored.displayTaxedPrice).toBe(false);
    expect(stored.categoryIds).toEqual([DEMO.categories.sofas]);
    expect(stored.paymentModuleCodes).toEqual(['Cheque']);
    expect(stored.restrictsCategories).toBe(true);
    expect(stored.restrictsPaymentModules).toBe(true);

    // Read back on the sheet.
    await sheet.goto();
    await expect(sheet.discount).toHaveValue(/^20/);
    await expect(sheet.displayTaxedPrice).not.toBeChecked();
    await expect(sheet.categories.locator('option:checked')).toHaveCount(1);
    await expect(sheet.categoriesEmptiedWarning).toHaveCount(0);
  });

  test('deleting the only category of the list keeps the group closed and warns on its sheet', async ({ page, browser }) => {
    test.setTimeout(180_000);
    const ownCategory = await createCategory('only-open');
    const group = await createGroup({ categoryIds: [ownCategory] });
    const member = await registerMember(browser, group.id);
    const { context, page: memberPage } = await signedInContext(browser, member);

    // The category goes (its link goes with it, the database cascades): the list is now empty.
    await deleteCategory(ownCategory);

    const sheet = new CustomerGroupEditPage(page, group.id);
    await sheet.goto();
    await expect(sheet.categoriesEmptiedWarning).toBeVisible();
    const afterDeletion = await storedGroup(group.id);
    expect(afterDeletion.categoryIds).toEqual([]);
    expect(afterDeletion.restrictsCategories).toBe(true);

    // The group stays closed: it is not opened to the whole catalog by the deletion.
    expect((await memberPage.goto('/sofas.html'))?.status()).toBe(404);
    expect((await memberPage.goto(`/${DEMO.sofa.slug}.html`))?.status()).toBe(404);

    // Lifting the restriction on purpose opens the catalog again.
    await Promise.all([page.waitForLoadState('domcontentloaded'), sheet.categoriesLift.click()]);
    await expect(sheet.categoriesEmptiedWarning).toHaveCount(0);
    expect((await storedGroup(group.id)).restrictsCategories).toBe(false);
    expect((await memberPage.goto('/sofas.html'))?.status()).toBe(200);

    await context.close();
  });

  test('the customer sheet moves a customer to another group', async ({ page, browser }) => {
    test.setTimeout(120_000);
    const group = await createGroup({ title: 'E2E target group' });
    const member = await registerMember(browser, null);
    const customerId = await customerIdOf(member.email);

    const sheet = new CustomerEditPage(page, customerId);
    await sheet.goto();
    await sheet.expectLoaded();
    const select = page.locator('#thelia_customer_update_customer_group_id');
    await expect(select).toBeVisible();
    await select.selectOption({ value: String(group.id) });
    await Promise.all([page.waitForLoadState('domcontentloaded'), sheet.submitButton.click()]);

    expect(await groupIdOfCustomer(member.email)).toBe(group.id);

    await sheet.goto();
    await expect(select).toHaveValue(String(group.id));
  });

  test('the customer list filters by group', async ({ page, browser }) => {
    test.setTimeout(180_000);
    const group = await createGroup({ title: 'E2E filter group' });
    const inGroup = await registerMember(browser, group.id);
    const outside = await registerMember(browser, null);
    const inGroupId = await customerIdOf(inGroup.email);
    const outsideId = await customerIdOf(outside.email);

    const list = new CustomerListTwigPage(page);
    await list.goto();
    await list.expectLoaded();

    // The filter as a person uses it: open the advanced filters, tick the group, apply.
    await page.locator('.bo-customer-filters__toggle').click();
    await page.locator(`label:has([data-testid="customer-filter-group-${group.id}"])`).click();
    await page.locator('form[action$="/admin/customers"]').first().evaluate((form) => (form as HTMLFormElement).requestSubmit());
    await page.waitForLoadState('domcontentloaded');

    await expect(page).toHaveURL(new RegExp(`group_ids(%5B|\\[)(%5D|\\])?=${group.id}`));
    const editLinks = list.rows.locator('[data-testid="datatable-action-edit"]');
    await expect(editLinks).toHaveCount(1);
    await expect(editLinks.first()).toHaveAttribute('href', new RegExp(`customer_id=${inGroupId}\\b`));
    await expect(page.locator(`a[href*="customer_id=${outsideId}"]`)).toHaveCount(0);
  });

  test('the product sheet saves a price of its own for a group', async ({ page }) => {
    const group = await createGroup({ title: 'E2E product price group' });
    const grid = new ProductGroupPricesSection(page, DEMO.sofa.productId);
    await grid.goto();
    await expect(grid.section).toBeVisible();
    await expect(grid.groupHeader(group.id)).toBeVisible();

    await grid.priceInput(DEMO.sofa.pseId, group.id).fill('321');
    await Promise.all([page.waitForLoadState('domcontentloaded'), grid.submit.click()]);

    expect(await groupPriceOf(group.id, DEMO.sofa.pseId)).toBe(321);
    await grid.goto();
    await expect(grid.priceInput(DEMO.sofa.pseId, group.id)).toHaveValue(/^321/);
  });

  test('the registration link: open, regenerate, close', async ({ page }) => {
    const group = await createGroup({ title: 'E2E registration group' });
    const sheet = new CustomerGroupEditPage(page, group.id);
    await sheet.goto();

    // Closed at first: nothing displayed, nothing stored.
    await expect(sheet.registrationUrl).toHaveCount(0);
    await expect(sheet.registrationOpen).toBeVisible();
    expect((await storedGroup(group.id)).registrationToken).toBeNull();

    // Open: the URL appears and carries the stored token.
    await Promise.all([page.waitForLoadState('domcontentloaded'), sheet.registrationOpen.click()]);
    await expect(sheet.registrationUrl).toBeVisible();
    const firstToken = (await storedGroup(group.id)).registrationToken;
    expect(firstToken).toMatch(/^[0-9a-f]{64}$/);
    expect(await sheet.registrationUrl.inputValue()).toContain(`/register/${firstToken}`);

    // The link opens the sign-up form; it is not the front of the group sheet.
    expect((await page.request.get(`/register/${firstToken}`)).status()).toBe(200);

    // Regenerate: a new token, the old link stops working.
    await Promise.all([page.waitForLoadState('domcontentloaded'), sheet.registrationRegenerate.click()]);
    const secondToken = (await storedGroup(group.id)).registrationToken;
    expect(secondToken).toMatch(/^[0-9a-f]{64}$/);
    expect(secondToken).not.toBe(firstToken);
    expect(await sheet.registrationUrl.inputValue()).toContain(`/register/${secondToken}`);
    expect((await page.request.get(`/register/${firstToken}`)).status()).toBe(404);
    expect((await page.request.get(`/register/${secondToken}`)).status()).toBe(200);

    // Close: no URL, no token, the link is a 404.
    await Promise.all([page.waitForLoadState('domcontentloaded'), sheet.registrationClose.click()]);
    await expect(sheet.registrationUrl).toHaveCount(0);
    await expect(sheet.registrationOpen).toBeVisible();
    expect((await storedGroup(group.id)).registrationToken).toBeNull();
    expect((await page.request.get(`/register/${secondToken}`)).status()).toBe(404);
  });
});
