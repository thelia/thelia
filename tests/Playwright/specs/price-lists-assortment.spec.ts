import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { DEMO, createGroup, registerMember, signedInContext, type CustomerGroup } from '../helpers/customer-group';
import type { Customer } from '../helpers/customer';
import { PRODUCTS, createRestriction, purgePriceListData, remainingPriceListRows } from '../helpers/price-list';

/**
 * Price lists, assortment, in the browser. An assortment limited to
 * two categories, Armchairs and Stools: the customer sees these two categories (and the
 * Living Room and Dining Room above them as ways through), the search brings back nothing else, and
 * the address of a product outside answers 404. A visitor of the default group, with no rule, sees
 * the whole catalog.
 *
 * Data (removed in afterAll): one group with two `allow` rules, one member (e2e-cg- prefix). Demo data
 * read, never written: Armchairs (Owen) and Stools (Travis) are open; Sofas (Nigel, Heathcliff) and
 * Chairs (Tina) are closed.
 */
const OPEN_CATEGORIES = [DEMO.categories.armchairs, DEMO.categories.stools];
const CLOSED_MENU_SLUGS = ['sofas', 'chairs'];
const OPEN_MENU_SLUGS = ['armchairs', 'stools'];

async function statusOf(page: Page, path: string): Promise<number> {
  const response = await page.goto(path);
  return response?.status() ?? 0;
}

/** Category links the header menu renders (desktop dropdown or mobile drawer: attached, visible or not). */
async function menuCategorySlugs(page: Page): Promise<string[]> {
  await page.goto('/');
  const hrefs = await page.locator('header a[href$=".html"]').evaluateAll((anchors) => anchors.map((anchor) => (anchor as HTMLAnchorElement).pathname));
  return [...OPEN_MENU_SLUGS, ...CLOSED_MENU_SLUGS].filter((slug) => hrefs.includes(`/${slug}.html`));
}

async function searchResultSlugs(page: Page, query: string): Promise<string[]> {
  await page.goto(`/search?query=${encodeURIComponent(query)}`);
  await expect(page.locator('main')).toBeVisible();
  const hrefs = await page.locator('main a.ProductCard-title').evaluateAll((anchors) => anchors.map((anchor) => (anchor as HTMLAnchorElement).pathname));
  return hrefs.map((href) => href.replace(/^\//, '').replace(/\.html$/, ''));
}

test.describe('Price lists: assortment of two categories', () => {
  test.describe.configure({ mode: 'serial' });

  let group: CustomerGroup;
  let member: Customer;
  let memberContext: BrowserContext;
  let memberPage: Page;

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(180_000);
    await purgePriceListData();
    group = await createGroup();
    for (const categoryId of OPEN_CATEGORIES) await createRestriction({ groupId: group.id }, { categoryId }, 'allow');
    member = await registerMember(browser, group.id);
    ({ context: memberContext, page: memberPage } = await signedInContext(browser, member));
  });

  test.afterAll(async () => {
    await memberContext?.close();
    await purgePriceListData();
    expect(await remainingPriceListRows()).toEqual({ lists: 0, lines: 0, assignments: 0, restrictions: 0, overrides: 0, customers: 0, shopMode: 0 });
  });

  test('a visitor, with no rule, sees every category and product, and the search finds them', async ({ page }) => {
    for (const path of ['/armchairs.html', '/stools.html', '/sofas.html', '/chairs.html', `/${PRODUCTS.nigel.slug}.html`, `/${PRODUCTS.tina.slug}.html`]) {
      expect(await statusOf(page, path), path).toBe(200);
    }
    expect(await searchResultSlugs(page, 'Nigel')).toContain(PRODUCTS.nigel.slug);
    expect(await menuCategorySlugs(page)).toEqual(expect.arrayContaining([...OPEN_MENU_SLUGS, ...CLOSED_MENU_SLUGS]));
  });

  test('the menu of the customer lists the two categories and none of the others', async () => {
    const slugs = await menuCategorySlugs(memberPage);
    for (const open of OPEN_MENU_SLUGS) expect(slugs, open).toContain(open);
    for (const closed of CLOSED_MENU_SLUGS) expect(slugs, closed).not.toContain(closed);
  });

  test('the two categories open and list their products', async () => {
    for (const [path, slug] of [['/armchairs.html', PRODUCTS.owen.slug], ['/stools.html', PRODUCTS.travis.slug]]) {
      expect(await statusOf(memberPage, path), path).toBe(200);
      await expect(memberPage.locator(`.ProductCard a.ProductCard-title[href$="/${slug}.html"]`), `${path} lists ${slug}`).toBeVisible();
    }
  });

  test('the parents stay ways through: they open and lead to the open category only', async () => {
    expect(await statusOf(memberPage, '/living-room.html')).toBe(200);
    await expect(memberPage.locator('main a[href$="/armchairs.html"]').first()).toBeAttached();
    await expect(memberPage.locator('main a[href$="/sofas.html"]')).toHaveCount(0);

    expect(await statusOf(memberPage, '/dining-room.html')).toBe(200);
    await expect(memberPage.locator('main a[href$="/stools.html"]').first()).toBeAttached();
    await expect(memberPage.locator('main a[href$="/chairs.html"]')).toHaveCount(0);
  });

  test('the categories outside the assortment answer 404', async () => {
    for (const path of ['/sofas.html', '/chairs.html']) {
      expect(await statusOf(memberPage, path), path).toBe(404);
    }
  });

  test('the address of a product outside the assortment answers 404, the products inside open', async () => {
    for (const product of [PRODUCTS.nigel, PRODUCTS.heathcliff, PRODUCTS.tina]) {
      expect(await statusOf(memberPage, `/${product.slug}.html`), product.slug).toBe(404);
    }
    for (const product of [PRODUCTS.owen, PRODUCTS.travis]) {
      expect(await statusOf(memberPage, `/${product.slug}.html`), product.slug).toBe(200);
    }
  });

  test('the search brings back nothing outside the assortment', async () => {
    for (const closed of [PRODUCTS.nigel, PRODUCTS.heathcliff, PRODUCTS.tina]) {
      expect(await searchResultSlugs(memberPage, closed.slug), closed.slug).not.toContain(closed.slug);
    }
    await memberPage.goto('/search?query=Nigel');
    await expect(memberPage.locator('main')).toContainText(/no products found/i);
    // The control: what is inside is found.
    expect(await searchResultSlugs(memberPage, 'Owen')).toContain(PRODUCTS.owen.slug);
    expect(await searchResultSlugs(memberPage, 'Travis')).toContain(PRODUCTS.travis.slug);
  });

  test('the home page of the customer does not link a closed category or product', async () => {
    await memberPage.goto('/');
    for (const closed of CLOSED_MENU_SLUGS) {
      await expect(memberPage.locator(`main a[href$="/${closed}.html"]`), closed).toHaveCount(0);
    }
    for (const closed of [PRODUCTS.nigel, PRODUCTS.heathcliff, PRODUCTS.tina]) {
      await expect(memberPage.locator(`main a[href$="/${closed.slug}.html"]`), closed.slug).toHaveCount(0);
    }
  });

  test('a visitor is served the closed pages while the member is refused them', async ({ page }) => {
    // Nothing leaks from one to the other.
    expect(await statusOf(page, `/${PRODUCTS.nigel.slug}.html`)).toBe(200);
    expect(await statusOf(memberPage, `/${PRODUCTS.nigel.slug}.html`)).toBe(404);
    expect(await statusOf(page, '/sofas.html')).toBe(200);
  });
});
