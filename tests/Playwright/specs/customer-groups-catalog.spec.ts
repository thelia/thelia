import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { DEMO, createGroup, purgeCustomerGroupData, registerMember, signedInContext, type CustomerGroup } from '../helpers/customer-group';
import type { Customer } from '../helpers/customer';

/**
 * Customer groups, catalog whitelist: a group restricted to the leaf category S (Sofas, under
 * P = Living Room) sees S and its products, P as a way through, and nothing else. A visitor of
 * the default group (no list) sees the whole catalog.
 *
 * Demo data read, never written: Living Room (P) > Sofas (S); Armchairs, Dining Room > Chairs and
 * Stools are the other categories; Nigel is a sofa, Stacy and Travis are filed outside Sofas.
 */
const CATEGORY_PAGES = {
  open: ['/sofas.html'],
  passage: ['/living-room.html'],
  closed: ['/armchairs.html', '/dining-room.html', '/chairs.html', '/stools.html'],
};
const PRODUCT_IN_S = `/${DEMO.sofa.slug}.html`;
const PRODUCTS_OUTSIDE_S = [`/${DEMO.otherCategoryProduct.slug}.html`, `/${DEMO.promoStool.slug}.html`, `/${DEMO.armchair.slug}.html`];
// The header menu links the leaf categories; the top-level ones (Living Room, Dining Room) are its entries, not .html links.
const OUTSIDE_CATEGORY_SLUGS = ['armchairs', 'chairs', 'stools'];

async function statusOf(page: Page, path: string): Promise<number> {
  const response = await page.goto(path);
  return response?.status() ?? 0;
}

/** Category links the header menu renders (desktop dropdown or mobile drawer: attached, visible or not). */
async function menuCategorySlugs(page: Page): Promise<string[]> {
  await page.goto('/');
  const hrefs = await page.locator('header a[href$=".html"]').evaluateAll((anchors) => anchors.map((anchor) => (anchor as HTMLAnchorElement).pathname));
  const known = ['sofas', ...OUTSIDE_CATEGORY_SLUGS];
  return known.filter((slug) => hrefs.includes(`/${slug}.html`));
}

test.describe('Customer groups: catalog whitelist', () => {
  test.describe.configure({ mode: 'serial' });

  let group: CustomerGroup;
  let member: Customer;
  let memberContext: BrowserContext;
  let memberPage: Page;

  test.beforeAll(async ({ browser }) => {
    await purgeCustomerGroupData();
    group = await createGroup({ categoryIds: [DEMO.categories.sofas] });
    member = await registerMember(browser, group.id);
    ({ context: memberContext, page: memberPage } = await signedInContext(browser, member));
  });

  test.afterAll(async () => {
    await memberContext?.close();
    await purgeCustomerGroupData();
  });

  test('the default group sees every category and product', async ({ page }) => {
    for (const path of [...CATEGORY_PAGES.open, ...CATEGORY_PAGES.passage, ...CATEGORY_PAGES.closed, PRODUCT_IN_S, ...PRODUCTS_OUTSIDE_S]) {
      expect(await statusOf(page, path), path).toBe(200);
    }
  });

  test('the default group menu lists every category', async ({ page }) => {
    const slugs = await menuCategorySlugs(page);
    expect(slugs).toEqual(expect.arrayContaining(['sofas', ...OUTSIDE_CATEGORY_SLUGS]));
  });

  test('the menu of the restricted member lists the whitelisted category and none of the others', async () => {
    const slugs = await menuCategorySlugs(memberPage);
    expect(slugs).toContain('sofas');
    for (const outside of OUTSIDE_CATEGORY_SLUGS) expect(slugs, outside).not.toContain(outside);
  });

  test('the whitelisted category and its products open', async () => {
    expect(await statusOf(memberPage, '/sofas.html')).toBe(200);
    await expect(memberPage.locator('.ProductCard').first()).toBeVisible();
    expect(await statusOf(memberPage, PRODUCT_IN_S)).toBe(200);
  });

  test('the parent of the whitelisted category stays a way through: it opens and leads to the category only', async () => {
    expect(await statusOf(memberPage, '/living-room.html')).toBe(200);
    await expect(memberPage.locator('main a[href$="/sofas.html"]').first()).toBeAttached();
    for (const outside of OUTSIDE_CATEGORY_SLUGS) {
      await expect(memberPage.locator(`main a[href$="/${outside}.html"]`), outside).toHaveCount(0);
    }
    // Products filed directly in the way through are not shown: no card of the sibling Armchairs.
    await expect(memberPage.locator(`.ProductCard a[href$="/${DEMO.armchair.slug}.html"]`)).toHaveCount(0);
  });

  test('every other category answers 404', async () => {
    for (const path of CATEGORY_PAGES.closed) {
      expect(await statusOf(memberPage, path), path).toBe(404);
    }
  });

  test('a product filed only in another category answers 404', async () => {
    for (const path of PRODUCTS_OUTSIDE_S) {
      expect(await statusOf(memberPage, path), path).toBe(404);
    }
  });

  test('the home page does not list the closed categories', async () => {
    await memberPage.goto('/');
    for (const outside of OUTSIDE_CATEGORY_SLUGS) {
      await expect(memberPage.locator(`main a[href$="/${outside}.html"]`), outside).toHaveCount(0);
    }
  });
});
