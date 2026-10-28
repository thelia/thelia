import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { addCurrentProductToCart, gotoProduct } from '../helpers/cart';
import { goToPaymentStep } from '../helpers/checkout';
import { DEMO, createGroup, purgeCustomerGroupData, registerMember, signedInContext, type CustomerGroup } from '../helpers/customer-group';
import { ensureCustomDeliveryConfigured } from '../helpers/delivery';
import type { Customer } from '../helpers/customer';

/**
 * Customer groups, payment whitelist: a group restricted to Cheque is offered Cheque and nothing
 * else at the checkout: the gift card and the instant payment parts ("Pay part of your order
 * with") are gone. A member of the default group (no list) is offered the others as before.
 */
async function openPaymentStep(page: Page): Promise<void> {
  await gotoProduct(page, DEMO.sofa.slug);
  await addCurrentProductToCart(page);
  await goToPaymentStep(page);
  await expect(page.locator('.PaymentCard').first()).toBeVisible();
}

test.describe('Customer groups: payment whitelist', () => {
  test.describe.configure({ mode: 'serial' });

  let restrictedGroup: CustomerGroup;
  let restricted: Customer;
  let restrictedContext: BrowserContext;
  let restrictedPage: Page;
  let regular: Customer;
  let regularContext: BrowserContext;
  let regularPage: Page;

  test.beforeAll(async ({ browser }) => {
    await ensureCustomDeliveryConfigured();
    await purgeCustomerGroupData();
    restrictedGroup = await createGroup({ paymentModuleCodes: ['Cheque'] });
    restricted = await registerMember(browser, restrictedGroup.id);
    regular = await registerMember(browser, null);
    ({ context: restrictedContext, page: restrictedPage } = await signedInContext(browser, restricted));
    ({ context: regularContext, page: regularPage } = await signedInContext(browser, regular));
  });

  test.afterAll(async () => {
    await restrictedContext?.close();
    await regularContext?.close();
    await purgeCustomerGroupData();
  });

  test('a member of the default group is offered Cheque and the gift card', async () => {
    await openPaymentStep(regularPage);
    const cards = regularPage.locator('.PaymentCard');
    await expect(cards.filter({ hasText: /cheque/i })).toHaveCount(1);
    await expect(cards.filter({ hasText: /gift card/i })).toHaveCount(1);
    await expect(regularPage.getByText(/Pay part of your order with/i)).toBeVisible();
  });

  test('a member restricted to Cheque is offered Cheque only', async () => {
    await openPaymentStep(restrictedPage);
    const cards = restrictedPage.locator('.PaymentCard');
    await expect(cards).toHaveCount(1);
    await expect(cards.first()).toContainText(/cheque/i);
  });

  test('a member restricted to Cheque sees no gift card and no instant payment part', async () => {
    await restrictedPage.goto('/checkout/payment');
    await expect(restrictedPage.locator('.PaymentCard').first()).toBeVisible();
    await expect(restrictedPage.getByText(/gift card/i)).toHaveCount(0);
    await expect(restrictedPage.getByText(/Pay part of your order with/i)).toHaveCount(0);
  });
});
