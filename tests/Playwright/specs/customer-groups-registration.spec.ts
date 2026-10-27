import { randomBytes } from 'node:crypto';
import { expect, test } from '@playwright/test';
import { registerCustomer } from '../helpers/customer';
import {
  createGroup,
  defaultGroupId,
  groupIdOfCustomer,
  newGroupCustomer,
  purgeCustomerGroupData,
  setRegistrationToken,
  type CustomerGroup,
} from '../helpers/customer-group';

/**
 * Customer groups, registration by secret link: `/register/{token}` is the standard sign-up form
 * whose new account joins the group the link opens. A token that opens nothing (regenerated,
 * closed, never issued) is a 404 and creates nothing; the standard `/customer/register` files the
 * account under the default group.
 */
// The core only opens a link for a token of 64 hexadecimal characters (32 random bytes).
const randomToken = (): string => randomBytes(32).toString('hex');

test.describe('Customer groups: registration link', () => {
  test.describe.configure({ mode: 'serial' });

  let group: CustomerGroup;
  let token: string;

  test.beforeAll(async () => {
    await purgeCustomerGroupData();
    token = randomToken();
    group = await createGroup({ registrationToken: token });
  });

  test.afterAll(async () => {
    await purgeCustomerGroupData();
  });

  test('the link of an open group registers the account in that group', async ({ page }) => {
    test.setTimeout(120_000);
    const customer = newGroupCustomer();
    await registerCustomer(page, customer, `/register/${token}`);
    expect(await groupIdOfCustomer(customer.email)).toBe(group.id);
  });

  test('the standard form registers the account in the default group', async ({ page }) => {
    test.setTimeout(120_000);
    const customer = newGroupCustomer();
    await registerCustomer(page, customer);
    expect(await groupIdOfCustomer(customer.email)).toBe(await defaultGroupId());
  });

  test('a regenerated token: the old link is a 404, the new one opens the form', async ({ page }) => {
    const previous = token;
    token = randomToken();
    await setRegistrationToken(group.id, token);

    expect((await page.goto(`/register/${previous}`))?.status()).toBe(404);
    const reopened = await page.goto(`/register/${token}`);
    expect(reopened?.status()).toBe(200);
    await expect(page.locator('form[name="flexybundle_form_customer_register_form"]')).toBeVisible();
  });

  test('a closed link is a 404', async ({ page }) => {
    await setRegistrationToken(group.id, null);
    expect((await page.goto(`/register/${token}`))?.status()).toBe(404);
  });

  test('a token that was never issued is a 404', async ({ page }) => {
    expect((await page.goto(`/register/${randomToken()}`))?.status()).toBe(404);
  });
});
