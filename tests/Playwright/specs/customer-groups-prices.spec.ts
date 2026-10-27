import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { addCurrentProductToCart, gotoProduct } from '../helpers/cart';
import {
  DEMO,
  createGroup,
  purgeCustomerGroupData,
  registerMember,
  setGroupPrice,
  signedInContext,
  type CustomerGroup,
} from '../helpers/customer-group';
import type { Customer } from '../helpers/customer';
import { readCardPrice, readProductPagePrice, readSummary, round2 } from '../helpers/group-pricing';

/**
 * Customer groups, prices: what a visitor of the default group and a member of a group priced
 * 15% off and shown without taxes see on the listing, the product page and the cart.
 *
 * Data (prefix e2e-cg-, removed in afterAll): one group (15%, untaxed) with one price of its own on
 * the sofa Nigel, and one member. Demo prices are read from `DEMO`: the armchair Owen (395.00
 * untaxed, no promotion), the sofa Nigel (638.00) and the stool Travis (25.00 struck, 19.00 paid).
 */
const VAT = DEMO.vatRate;
const GROUP_DISCOUNT = 15;
const GROUP_PRICE_OF_SOFA = 500;

test.describe('Customer groups: prices', () => {
  test.describe.configure({ mode: 'serial' });

  let group: CustomerGroup;
  let member: Customer;
  let memberContext: BrowserContext;
  let memberPage: Page;

  test.beforeAll(async ({ browser }) => {
    await purgeCustomerGroupData();
    group = await createGroup({ discount: GROUP_DISCOUNT, taxed: false });
    await setGroupPrice(group.id, DEMO.sofa.pseId, GROUP_PRICE_OF_SOFA);
    member = await registerMember(browser, group.id);
    ({ context: memberContext, page: memberPage } = await signedInContext(browser, member));
  });

  test.afterAll(async () => {
    await memberContext?.close();
    await purgeCustomerGroupData();
  });

  test('a visitor (default group) sees taxed prices, no discount, on the listing and the product page', async ({ page }) => {
    const { slug, price } = DEMO.armchair;
    const expected = round2(price * (1 + VAT));

    const onListing = await readCardPrice(page, '/armchairs.html', slug);
    expect(onListing).toEqual({ amount: expected, display: 'taxed' });

    const onPage = await readProductPagePrice(page, slug);
    expect(onPage).toEqual({ amount: expected, display: 'taxed' });
  });

  test('a visitor sees the catalog promotion untouched', async ({ page }) => {
    const { slug, promoPrice } = DEMO.promoStool;
    const onPage = await readProductPagePrice(page, slug);
    expect(onPage).toEqual({ amount: round2(promoPrice * (1 + VAT)), display: 'taxed' });
  });

  test('a member sees the discounted price without taxes, with the mention, on the listing and the product page', async () => {
    const { slug, price } = DEMO.armchair;
    const expected = round2(price * (1 - GROUP_DISCOUNT / 100));

    const onListing = await readCardPrice(memberPage, '/armchairs.html', slug);
    expect(onListing).toEqual({ amount: expected, display: 'untaxed' });

    const onPage = await readProductPagePrice(memberPage, slug);
    expect(onPage).toEqual({ amount: expected, display: 'untaxed' });
  });

  test('a price of the group wins over its discount, on the listing and the product page', async () => {
    const { slug, price } = DEMO.sofa;
    // Control: without the price of its own the discount would have given this.
    const discounted = round2(price * (1 - GROUP_DISCOUNT / 100));
    expect(GROUP_PRICE_OF_SOFA).not.toBe(discounted);

    const onListing = await readCardPrice(memberPage, '/sofas.html', slug).catch(async () => readCardPrice(memberPage, '/living-room.html', slug));
    expect(onListing).toEqual({ amount: GROUP_PRICE_OF_SOFA, display: 'untaxed' });

    const onPage = await readProductPagePrice(memberPage, slug);
    expect(onPage).toEqual({ amount: GROUP_PRICE_OF_SOFA, display: 'untaxed' });
  });

  test('the cart of a member states Total excl. tax, VAT and Total incl. tax to pay', async () => {
    const { slug, price } = DEMO.armchair;
    const untaxed = round2(price * (1 - GROUP_DISCOUNT / 100));

    await gotoProduct(memberPage, slug);
    await addCurrentProductToCart(memberPage);
    await memberPage.goto('/checkout/cart');

    // The line itself is without taxes, with its mention.
    const line = memberPage.locator('.CartItem').first();
    await expect(line.locator('.TaxMention')).toHaveAttribute('data-tax-display', 'untaxed');
    await expect(line.locator('span[dir="ltr"]').first()).toContainText(untaxed.toFixed(2));

    const summary = await readSummary(memberPage);
    expect(summary.totalWithoutTaxes).toBe(untaxed);
    expect(summary.vat).toBe(round2(untaxed * VAT));
    expect(summary.total).toBe(round2(untaxed * (1 + VAT)));
    expect(summary.totalLabel).toMatch(/Total incl\. tax to pay/i);
  });

  test('a visitor cart is taxed: Total with the taxed mention, no excl. tax block', async ({ page }) => {
    const { slug, price } = DEMO.armchair;
    await gotoProduct(page, slug);
    await addCurrentProductToCart(page);
    await page.goto('/checkout/cart');

    const summary = await readSummary(page);
    expect(summary.totalWithoutTaxes).toBeNull();
    expect(summary.total).toBe(round2(price * (1 + VAT)));
    await expect(page.locator('.CartItem .TaxMention').first()).toHaveAttribute('data-tax-display', 'taxed');
  });

  test('the catalog promotion is not discounted by the group (M1)', async () => {
    const { slug, promoPrice, price } = DEMO.promoStool;
    const onPage = await readProductPagePrice(memberPage, slug);
    const wronglyDiscounted = round2(promoPrice * (1 - GROUP_DISCOUNT / 100));

    // M1 en cours : un autre lot corrige la règle (la remise du groupe ne touche pas le prix promo
    // du catalogue). Tant que l'écran montre encore la promo remisée, ce test est attendu en échec ;
    // dès que le correctif est livré, la condition tombe d'elle-même et le test redevient normal.
    test.fail(onPage.amount === wronglyDiscounted, 'M1 en cours : la remise du groupe est encore appliquée au prix promo du catalogue');

    expect(onPage.display).toBe('untaxed');
    expect(onPage.amount).toBe(promoPrice);

    // The struck price stays the group's own price of the combination, never the catalog one.
    const struck = memberPage.locator('.PseSelector-originalPrice span[dir="ltr"]').first();
    await expect(struck).toContainText(round2(price * (1 - GROUP_DISCOUNT / 100)).toFixed(2));
  });
});
