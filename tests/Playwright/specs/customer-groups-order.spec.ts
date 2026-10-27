import { expect, test } from '@playwright/test';
import { addCurrentProductToCart, gotoProduct } from '../helpers/cart';
import { placeOrderWithCheque } from '../helpers/checkout';
import {
  DEMO,
  createGroup,
  defaultGroupId,
  moveCustomerToGroup,
  purgeCustomerGroupData,
  registerMember,
  signedInContext,
  type CustomerGroup,
} from '../helpers/customer-group';
import { ensureCustomDeliveryConfigured } from '../helpers/delivery';
import { ddevMysql } from '../helpers/db';
import { readProductPagePrice, readSummary, round2 } from '../helpers/group-pricing';

/**
 * Customer groups, frozen order: an order placed by a member of a group shown without taxes keeps
 * that display, the group and its discount rate when the customer is moved to another group
 * afterwards: the confirmation, the order list and the order page of the account stay without taxes.
 */
const VAT = DEMO.vatRate;
const DISCOUNT = 15;

test.describe('Customer groups: order frozen at placement', () => {
  test.describe.configure({ mode: 'serial' });

  let group: CustomerGroup;

  test.beforeAll(async () => {
    await ensureCustomDeliveryConfigured();
    await purgeCustomerGroupData();
    group = await createGroup({ discount: DISCOUNT, taxed: false });
  });

  test.afterAll(async () => {
    await purgeCustomerGroupData();
  });

  test('the order keeps the untaxed display, the group and the rate after the customer changes group', async ({ browser }) => {
    test.setTimeout(180_000);
    const member = await registerMember(browser, group.id);
    const { context, page } = await signedInContext(browser, member);

    const untaxedLine = round2(DEMO.sofa.price * (1 - DISCOUNT / 100));

    await gotoProduct(page, DEMO.sofa.slug);
    await addCurrentProductToCart(page);
    const reference = await placeOrderWithCheque(page, member.email);

    // What the database froze on the order.
    const frozen = await ddevMysql(`
      SELECT customer_group_id, customer_group_discount_rate, customer_group_display_taxed_price
      FROM \`order\` WHERE ref = '${reference}'`);
    const [frozenGroupId, frozenRate, frozenTaxed] = frozen.split('\t');
    expect(Number(frozenGroupId)).toBe(group.id);
    expect(Number(frozenRate)).toBe(DISCOUNT);
    expect(Number(frozenTaxed)).toBe(0);

    // The untaxed postage the order froze (the delivery slice of the demo dataset carries no tax:
    // the VAT is the one of the line).
    const postage = Number(await ddevMysql(`SELECT postage FROM \`order\` WHERE ref = '${reference}'`));
    const untaxedTotal = round2(untaxedLine + postage);
    const vat = round2(untaxedLine * VAT);

    // The confirmation page right after the order.
    await expect(page.locator('main')).toContainText(reference);
    await expect(page.locator('main')).toContainText(/excl\. tax/i);

    // The administrator moves the customer to the default group.
    await moveCustomerToGroup(member.email, await defaultGroupId());

    // Control: the catalog is now shown taxed to this customer.
    const nowOnTheProductPage = await readProductPagePrice(page, DEMO.sofa.slug);
    expect(nowOnTheProductPage).toEqual({ amount: round2(DEMO.sofa.price * (1 + VAT)), display: 'taxed' });

    // The order list of the account still shows the order without taxes.
    await page.goto('/account/orders');
    const card = page.locator('main').filter({ hasText: reference });
    await expect(card).toContainText(/excl\. tax/i);
    await expect(card).toContainText(untaxedTotal.toFixed(2));

    // And its page: lines, Total excl. tax, VAT, Total incl. tax to pay.
    const orderLink = page.locator('main a[href^="/account/order/"]').first();
    await orderLink.click();
    await expect(page).toHaveURL(/\/account\/order\/\d+/);
    const summary = await readSummary(page);
    expect(summary.totalWithoutTaxes).toBe(untaxedTotal);
    expect(summary.vat).toBe(vat);
    expect(summary.total).toBe(round2(untaxedTotal + vat));
    expect(summary.totalLabel).toMatch(/Total incl\. tax to pay/i);
    await expect(page.locator('main .TaxMention').first()).toHaveAttribute('data-tax-display', 'untaxed');

    await context.close();
  });
});
