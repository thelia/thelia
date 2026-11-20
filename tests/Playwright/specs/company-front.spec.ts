import { expect, test, type BrowserContext, type Page } from '@playwright/test';
import { loginAdmin } from '../helpers/admin';
import { fillAddressForm } from '../helpers/account';
import {
  addressCompanyOf,
  addressIdOf,
  cancelOrder,
  companyIsActive,
  customerIdOf,
  LEGAL_IDENTIFIERS,
  membershipOf,
  newCompanyCustomer,
  newCompanyName,
  orderThroughTunnel,
  ordersOf,
  ordersOfCompany,
  purgeCompanyData,
  registerBuyerAccount,
  remainingCompanyRows,
  setCompanyStateInBackOffice,
  setUpCompany,
  signedInAs,
} from '../helpers/company';
import { ddevMysql } from '../helpers/db';
import { PRODUCTS } from '../helpers/price-list';
import { mailpitDeleteMatching, mailpitMessage, mailpitSearch } from '../helpers/stock';
import type { Customer } from '../helpers/customer';

/**
 * Companies, shop front (Flexy): the "My company" screens by role, the invitation of a buyer, the
 * address book shared with the company, an order placed by a buyer, the spending limits of the
 * tunnel, who sees which order, and a suspended company.
 *
 *   S1: A administrator, B buyer, V approver (reads the orders of the company); invited: I buyer
 *   S2: X administrator
 *   N : an account that belongs to no company
 *
 * Companies are made through the back-office, accounts through the sign-up form; every name
 * carries the e2e-co- prefix and everything is purged in afterAll. What the shop saved is checked
 * in the database, not only on the page.
 */
const TINA = PRODUCTS.tina;

type Signed = { context: BrowserContext; page: Page };

test.describe('Companies: shop front', () => {
  test.describe.configure({ mode: 'serial' });

  let adminContext: BrowserContext;
  let admin: Page;
  let companyOne = 0;
  let companyTwo = 0;
  let nameOne = '';
  let a: Customer;
  let b: Customer;
  let v: Customer;
  let x: Customer;
  let n: Customer;
  let sessions: Record<'a' | 'b' | 'v' | 'x' | 'n', Signed>;
  let invited: Customer;
  let invitedSession: Signed | undefined;

  test.beforeAll(async ({ browser }) => {
    test.setTimeout(360_000);
    await purgeCompanyData();
    a = await registerBuyerAccount(browser, 'a');
    b = await registerBuyerAccount(browser, 'b');
    v = await registerBuyerAccount(browser, 'v');
    x = await registerBuyerAccount(browser, 'x');
    n = await registerBuyerAccount(browser, 'n');

    adminContext = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport: { width: 1440, height: 900 } });
    admin = await adminContext.newPage();
    await loginAdmin(admin);
    nameOne = newCompanyName('s1');
    companyOne = await setUpCompany(
      admin,
      nameOne,
      [
        { customer: a, role: 'company_admin' },
        { customer: b, role: 'buyer' },
        { customer: v, role: 'approver' },
      ],
      LEGAL_IDENTIFIERS,
    );
    companyTwo = await setUpCompany(admin, newCompanyName('s2'), [{ customer: x, role: 'company_admin' }]);

    sessions = {
      a: await signedInAs(browser, a),
      b: await signedInAs(browser, b),
      v: await signedInAs(browser, v),
      x: await signedInAs(browser, x),
      n: await signedInAs(browser, n),
    };
  });

  test.afterAll(async () => {
    for (const session of [...Object.values(sessions ?? {}), invitedSession]) await session?.context.close();
    if (invited) await mailpitDeleteMatching(`to:${invited.email}`).catch(() => {});
    await adminContext?.close();
    await purgeCompanyData();
    expect(await remainingCompanyRows()).toEqual({ companies: 0, customers: 0, memberships: 0, ownRoles: 0 });
  });

  test('a customer without company has no "My company" entry and no company screen', async () => {
    const { page } = sessions.n;
    await page.goto('/account');
    await expect(page.locator('a[href="/account/company"]')).toHaveCount(0);

    expect((await page.goto('/account/company'))?.status(), 'company screen').toBe(404);
    expect((await page.goto('/account/company/buyers'))?.status(), 'buyers screen').toBe(404);
    expect((await page.goto('/account/orders?scope=company'))?.status(), 'orders of the company').toBe(403);
    // The ordinary account is untouched: addresses and orders answer as before.
    expect((await page.goto('/account/addresses'))?.status()).toBe(200);
    await expect(page.locator('.AddressCard')).toHaveCount(1);
    expect((await page.goto('/account/orders'))?.status()).toBe(200);
    await expect(page.locator('a[href*="scope=company"]')).toHaveCount(0);
  });

  test('the administrator sees the three screens and edits the details of the company', async () => {
    const { page } = sessions.a;
    await page.goto('/account');
    await expect(page.locator('a[href="/account/company"]:visible').first()).toBeVisible();

    await page.goto('/account/company');
    await expect(page.getByTestId('company-form')).toBeVisible();
    await expect(page.locator('#company-name')).toHaveValue(nameOne);
    await expect(page.locator('#company-siret')).toHaveValue(LEGAL_IDENTIFIERS.siret);
    await expect(page.locator('a[href="/account/company/buyers"]:visible').first()).toBeVisible();
    await expect(page.locator('a[href*="scope=company"]:visible').first()).toBeVisible();

    await page.locator('#company-notification-email').fill(`e2e-co-copy-${Date.now().toString(36)}@example.test`);
    const typed = await page.locator('#company-notification-email').inputValue();
    await Promise.all([page.waitForURL(/success=company_updated/), page.getByTestId('company-form').locator('button[type="submit"]').click()]);
    expect(await ddevMysql(`SELECT notification_email FROM company WHERE id = ${companyOne}`)).toBe(typed);
    await expect(page.locator('#company-notification-email')).toHaveValue(typed);

    await page.goto('/account/company/buyers');
    await expect(page.getByTestId('company-buyers')).toBeVisible();
    await expect(page.getByTestId('company-buyer')).toHaveCount(3);
    await expect(page.getByTestId('company-invitation-form')).toBeVisible();
    expect((await page.goto('/account/orders?scope=company'))?.status()).toBe(200);
  });

  test('a simple buyer reads the company and nothing more; the approver reads the orders of the company', async () => {
    const buyer = sessions.b.page;
    await buyer.goto('/account');
    await expect(buyer.locator('a[href="/account/company"]:visible').first()).toBeVisible();
    await buyer.goto('/account/company');
    await expect(buyer.getByTestId('company-details')).toContainText(nameOne);
    await expect(buyer.getByTestId('company-form'), 'no form for a buyer').toHaveCount(0);
    await expect(buyer.locator('a[href="/account/company/buyers"]:visible')).toHaveCount(0);
    await expect(buyer.locator('a[href*="scope=company"]')).toHaveCount(0);
    expect((await buyer.goto('/account/company/buyers'))?.status(), 'the route refuses what the screen hides').toBe(403);
    expect((await buyer.goto('/account/orders?scope=company'))?.status()).toBe(403);

    const approver = sessions.v.page;
    await approver.goto('/account/company');
    await expect(approver.getByTestId('company-form')).toHaveCount(0);
    await expect(approver.locator('a[href="/account/company/buyers"]')).toHaveCount(0);
    await expect(approver.locator('a[href*="scope=company"]:visible').first()).toBeVisible();
    expect((await approver.goto('/account/company/buyers'))?.status()).toBe(403);
    expect((await approver.goto('/account/orders?scope=company'))?.status()).toBe(200);
  });

  test('the administrator invites a buyer by e-mail: the link creates the account and joins, a replayed link is refused', async ({ browser }) => {
    test.setTimeout(180_000);
    invited = newCompanyCustomer('inv');
    const { page } = sessions.a;
    await page.goto('/account/company/buyers');
    const form = page.getByTestId('company-invitation-form');
    await form.locator('input[name="email"]').fill(invited.email);
    await form.locator('select[name="role"]').selectOption('buyer');
    await form.locator('input[name="order_limit"]').fill('300');
    await Promise.all([page.waitForURL(/success=invitation_sent|error=/), form.locator('button[type="submit"]').click()]);
    await expect(page).toHaveURL(/success=invitation_sent/);
    expect(await membershipOf(invited.email), 'nobody is attached before the link is used').toBeNull();

    // The link is in the e-mail the invited person receives.
    let messages = await mailpitSearch(`to:${invited.email}`);
    for (let attempt = 0; attempt < 20 && 0 === messages.length; attempt++) {
      await page.waitForTimeout(500);
      messages = await mailpitSearch(`to:${invited.email}`);
    }
    expect(messages, 'the invitation e-mail').toHaveLength(1);
    const body = await mailpitMessage(messages[0].ID);
    const link = body.Text.match(/https?:\/\/\S+\/account\/company\/invitation\/\S+/)?.[0];
    expect(link, 'the e-mail carries the link').toBeTruthy();

    // A visitor opens it: the address is the invitation's, the person chooses their password.
    const guest = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US', viewport: { width: 1440, height: 900 } });
    try {
      const visitor = await guest.newPage();
      expect((await visitor.goto(link as string))?.status()).toBe(200);
      await expect(visitor.locator('#flexybundle_form_customer_register_form_email')).toHaveValue(invited.email);
      await expect(visitor.locator('#flexybundle_form_customer_register_form_email')).toBeDisabled();
      await visitor.locator('#flexybundle_form_customer_register_form_firstname').fill('Invited');
      await visitor.locator('#flexybundle_form_customer_register_form_lastname').fill('Buyer');
      await visitor.locator('#flexybundle_form_customer_register_form_password').fill(invited.password);
      await Promise.all([
        visitor.waitForURL(/\/account\/company\?success=joined/, { timeout: 30_000 }),
        visitor.locator('form[name="flexybundle_form_customer_register_form"] button[type="submit"]').click(),
      ]);
      await expect(visitor.getByTestId('company-details')).toContainText(nameOne);
    } finally {
      await guest.close();
    }
    expect(await membershipOf(invited.email)).toMatchObject({ companyId: companyOne, roleCode: 'buyer', orderLimit: 300 });

    // The same link again, from another browser: the invitation page of every link that stands for nothing.
    const replay = await browser.newContext({ ignoreHTTPSErrors: true, locale: 'en-US' });
    try {
      const again = await replay.newPage();
      expect((await again.goto(link as string))?.status()).toBe(404);
      await expect(again.getByTestId('company-invitation-invalid')).toBeVisible();
      await expect(again.locator('form[name="flexybundle_form_customer_register_form"]')).toHaveCount(0);
    } finally {
      await replay.close();
    }
    expect(await ddevMysql(`SELECT COUNT(*) FROM customer WHERE email = '${invited.email}'`), 'no second account').toBe('1');

    // From now on the buyer signs in with their own password.
    invitedSession = await signedInAs(browser, invited);
    await invitedSession.page.goto('/account/company');
    await expect(invitedSession.page.getByTestId('company-details')).toContainText(nameOne);
  });

  test('a shared address reaches the other buyers, who cannot change it; the administrator can change what anyone shared', async () => {
    const admins = sessions.a.page;
    const buyer = sessions.b.page;

    // A makes a shared warehouse and a personal home address.
    for (const { label, street, shared } of [
      { label: 'Entrepôt', street: '1 rue de l’Entrepôt', shared: true },
      { label: 'Domicile', street: '8 rue du Domicile', shared: false },
    ]) {
      await admins.goto('/account/address/new');
      await fillAddressForm(admins, { address1: street, zipcode: '44000', city: 'Nantes', countryCode: 'FR', countryLabel: 'France metropolitan', phone: '0240000000', label });
      if (shared) await admins.locator('label:has(input[name$="[share_with_company]"])').click();
      await Promise.all([admins.waitForURL(/\/account\/addresses/), admins.locator('form button[type="submit"]').first().click()]);
    }
    expect(await addressCompanyOf(a.email, 'Entrepôt'), 'shared').toBe(companyOne);
    expect(await addressCompanyOf(a.email, 'Domicile'), 'personal').toBeNull();

    // A's own book has the two groups.
    await expect(admins.getByTestId('company-addresses')).toBeVisible();
    await expect(admins.getByTestId('personal-addresses')).toBeVisible();

    // B sees the shared one, in the company group, and not the personal one.
    await buyer.goto('/account/addresses');
    await expect(buyer.getByTestId('company-addresses')).toBeVisible();
    const sharedCard = buyer.locator('.AddressCard').filter({ hasText: 'Entrepôt' });
    await expect(sharedCard).toHaveCount(1);
    await expect(sharedCard.getByTestId('address-shared')).toBeVisible();
    await expect(buyer.locator('.AddressCard').filter({ hasText: 'Domicile' })).toHaveCount(0);
    await expect(buyer.locator('main')).not.toContainText('8 rue du Domicile');
    // No way to change or delete it from the card, and the route refuses it too.
    await expect(sharedCard.locator('a[href*="/account/address/"], button[data-modal]')).toHaveCount(0);
    const sharedId = await addressIdOf(a.email, 'Entrepôt');
    const personalId = await addressIdOf(a.email, 'Domicile');
    expect((await buyer.goto(`/account/address/${sharedId}`))?.status(), 'B edits the shared address').toBe(404);
    expect((await buyer.goto(`/account/address/${personalId}`))?.status(), 'B edits the personal address of A').toBe(404);
    expect(await ddevMysql(`SELECT city FROM address WHERE id = ${sharedId}`)).toBe('Nantes');

    // B shares an address of their own; the administrator, who did not make it, changes it.
    await buyer.goto('/account/address/new');
    await fillAddressForm(buyer, { address1: '2 rue du Bureau', zipcode: '44000', city: 'Nantes', countryCode: 'FR', countryLabel: 'France metropolitan', phone: '0240000000', label: 'Bureau B' });
    await buyer.locator('label:has(input[name$="[share_with_company]"])').click();
    await Promise.all([buyer.waitForURL(/\/account\/addresses/), buyer.locator('form button[type="submit"]').first().click()]);
    expect(await addressCompanyOf(b.email, 'Bureau B')).toBe(companyOne);
    const officeId = await addressIdOf(b.email, 'Bureau B');

    await admins.goto(`/account/address/${officeId}`);
    await expect(admins.locator('input[name$="[city]"]').first()).toHaveValue('Nantes');
    await admins.locator('input[name$="[city]"]').first().fill('Rennes');
    await Promise.all([admins.waitForURL(/\/account\/addresses\?update_success/), admins.locator('form button[type="submit"]').last().click()]);
    expect(await ddevMysql(`SELECT city FROM address WHERE id = ${officeId}`)).toBe('Rennes');
    expect(await addressCompanyOf(b.email, 'Bureau B'), 'still shared after the change').toBe(companyOne);

    // The other company sees none of it, not even by its address.
    const other = sessions.x.page;
    await other.goto('/account/addresses');
    await expect(other.locator('.AddressCard')).toHaveCount(1);
    await expect(other.locator('main')).not.toContainText('Entrepôt');
    expect((await other.goto(`/account/address/${sharedId}`))?.status(), 'X edits the shared address of S1').toBe(404);
    expect(companyTwo).not.toBe(companyOne);
  });

  test('a buyer orders with the shared address: the order belongs to the company and is invoiced to it', async () => {
    test.setTimeout(240_000);
    const sharedId = await addressIdOf(a.email, 'Entrepôt');

    // The merchant makes the warehouse the billing address of the company: its name and identifiers are copied onto it.
    await admin.goto(`/admin/companies/${companyOne}`);
    await admin.getByTestId('company-billing-select').selectOption(String(sharedId));
    await admin.getByTestId('company-edit-submit').click();
    await expect(admin.getByTestId('bo-flash-success')).toBeVisible();
    expect(await ddevMysql(`SELECT billing_address_id FROM company WHERE id = ${companyOne}`)).toBe(String(sharedId));

    const outcome = await orderThroughTunnel(sessions.b.page, b.email, TINA, 1, sharedId);
    expect(outcome.placed, 'an order without limit is placed').toBe(true);

    const [order] = await ordersOf(b.email);
    expect(order.companyId).toBe(companyOne);
    expect(order.placedByCustomerId).toBe(await customerIdOf(b.email));

    const addresses = await ddevMysql(`
      SELECT delivery.address1, delivery.city, invoice.company, invoice.siret, invoice.vat_number
      FROM \`order\` o JOIN order_address delivery ON delivery.id = o.delivery_order_address_id JOIN order_address invoice ON invoice.id = o.invoice_order_address_id
      WHERE o.id = ${order.id}`);
    const [deliveryStreet, deliveryCity, invoiceCompany, invoiceSiret, invoiceVat] = addresses.split('\t');
    expect(deliveryStreet, 'delivered to the shared address').toBe('1 rue de l’Entrepôt');
    expect(deliveryCity).toBe('Nantes');
    expect(invoiceCompany, 'invoiced to the company').toBe(nameOne);
    expect(invoiceSiret).toBe(LEGAL_IDENTIFIERS.siret);
    expect(invoiceVat).toBe(LEGAL_IDENTIFIERS.vatNumber);

    // The buyer finds it in their own orders.
    await sessions.b.page.goto('/account/orders');
    await expect(sessions.b.page.locator('main')).toContainText(order.ref);
  });

  test('the payment step offers to edit the billing address only to who may change it', async () => {
    test.setTimeout(240_000);
    const sharedId = await addressIdOf(a.email, 'Entrepôt');
    const editAction = (page: Page) => page.getByRole('button', { name: 'Edit the address' });

    // B may use the warehouse A shared, not change it: the card is shown without the action.
    const asBuyer = await orderThroughTunnel(sessions.b.page, b.email, TINA, 1, sharedId, async (page) => {
      await expect(page.locator('.InvoiceCard')).toContainText('Entrepôt');
      await expect(editAction(page)).toHaveCount(0);
      return 'stop';
    });
    expect(asBuyer.placed).toBe(false);

    // A made it: the action is there.
    const asCreator = await orderThroughTunnel(sessions.a.page, a.email, TINA, 1, sharedId, async (page) => {
      await expect(page.locator('.InvoiceCard')).toContainText('Entrepôt');
      await expect(editAction(page)).toBeVisible();
      return 'stop';
    });
    expect(asCreator.placed).toBe(false);
    expect(await ordersOf(a.email), 'the creator ordered nothing').toHaveLength(0);
  });

  test('identifiers the shop refuses on an address are refused on the company too, in the shop and in the back-office', async () => {
    const wrongSiret = '12345678900011';
    const stored = LEGAL_IDENTIFIERS.siret;

    // Shop: the screen says what is wrong on the field, keeps what was typed, and saves nothing.
    const { page } = sessions.a;
    await page.goto('/account/company');
    await page.locator('#company-siret').fill(wrongSiret);
    await page.getByTestId('company-form').locator('button[type="submit"]').click();
    await expect(page.locator('#company-siret-error')).toContainText(/checksum/i);
    await expect(page.locator('#company-siret')).toHaveValue(wrongSiret);
    expect(await ddevMysql(`SELECT siret FROM company WHERE id = ${companyOne}`)).toBe(stored);

    // Back-office: the same refusal, shown as a message on the sheet.
    await admin.goto(`/admin/companies/${companyOne}`);
    await admin.locator('[data-testid="company-edit-form"] input[name="siret"]').fill(wrongSiret);
    await admin.getByTestId('company-edit-submit').click();
    await expect(admin.getByTestId('bo-flash-danger')).toBeVisible();
    expect(await ddevMysql(`SELECT siret FROM company WHERE id = ${companyOne}`)).toBe(stored);
    expect(await ddevMysql(`SELECT siret FROM address WHERE id = ${await addressIdOf(a.email, 'Entrepôt')}`)).toBe(stored);
  });

  test('the spending limits turn an order away with a message, and a cancelled order stops counting', async () => {
    test.setTimeout(300_000);
    const buyer = sessions.b.page;
    const [first] = await ordersOf(b.email);
    const single = Number(await ddevMysql(`SELECT ROUND(postage + (SELECT SUM(quantity * price * 1.2) FROM order_product WHERE order_id = ${first.id}), 2) FROM \`order\` WHERE id = ${first.id}`));
    expect(single, 'one chair and its delivery cost 99.90').toBeCloseTo(99.9, 1);

    // The administrator sets 150 per order and 250 over the year on the buyer.
    const admins = sessions.a.page;
    await admins.goto('/account/company/buyers');
    const row = admins.getByTestId('company-buyer').filter({ hasText: b.email });
    await row.locator('input[name="order_limit"]').fill('150');
    await row.locator('input[name="period_limit"]').fill('250');
    await row.locator('select[name="period"]').selectOption('year');
    await Promise.all([admins.waitForURL(/success=buyer_updated/), row.locator('button[type="submit"]').first().click()]);
    expect(await membershipOf(b.email)).toMatchObject({ orderLimit: 150, periodLimit: 250, period: 'year' });

    // The screen shows the limits as amounts, and the fields hold them without the zeros of the column.
    await admins.goto('/account/company/buyers');
    const limited = admins.getByTestId('company-buyer').filter({ hasText: b.email });
    await expect(limited.getByTestId('company-buyer-limits')).toContainText('€150.00');
    await expect(limited.getByTestId('company-buyer-limits')).toContainText('€250.00');
    await expect(limited.locator('input[name="order_limit"]')).toHaveValue('150');
    await expect(admins.locator('main')).not.toContainText('150.000000');

    // Two chairs and the delivery: 189.90 > 150, refused whatever was ordered before.
    const tooBig = await orderThroughTunnel(buyer, b.email, TINA, 2);
    expect(tooBig.placed).toBe(false);
    if (!tooBig.placed) expect(tooBig.message).toContain('This order of €189.90 exceeds the €150.00 your company allows for a single order');
    expect(await ordersOf(b.email), 'a refused order leaves no order').toHaveLength(1);

    // One chair: 99.90 <= 150, and 99.90 already ordered + 99.90 = 199.80 <= 250: accepted.
    const accepted = await orderThroughTunnel(buyer, b.email, TINA, 1);
    expect(accepted.placed).toBe(true);
    expect(await ordersOf(b.email)).toHaveLength(2);

    // A third one: 199.80 + 99.90 = 299.70 > 250, refused over the period.
    const overPeriod = await orderThroughTunnel(buyer, b.email, TINA, 1);
    expect(overPeriod.placed).toBe(false);
    if (!overPeriod.placed) expect(overPeriod.message).toMatch(/already ordered over the period.*exceeds the .*250/i);
    expect(await ordersOf(b.email)).toHaveLength(2);

    // The second order is cancelled: only 99.90 counts, the same third order now fits.
    const [, second] = await ordersOf(b.email);
    await cancelOrder(second.id);
    const afterCancel = await orderThroughTunnel(buyer, b.email, TINA, 1);
    expect(afterCancel.placed, 'a cancelled order is not counted').toBe(true);
    expect(await ordersOf(b.email)).toHaveLength(3);
  });

  test('who sees which order: the buyer, the approver and the administrator of the company; never a colleague or another company', async () => {
    const orders = await ordersOf(b.email);
    expect(orders.length).toBeGreaterThanOrEqual(1);
    const target = orders[0];
    const forCompany = await ordersOfCompany(companyOne);
    expect(forCompany.map((order) => order.id)).toEqual(expect.arrayContaining(orders.map((order) => order.id)));

    const detail = (id: number): string => `/account/order/${id}`;

    // The buyer who placed it.
    expect((await sessions.b.page.goto(detail(target.id)))?.status()).toBe(200);

    // The approver and the administrator read the list of the company and open the order.
    for (const who of ['v', 'a'] as const) {
      const { page } = sessions[who];
      await page.goto('/account/orders?scope=company');
      await expect(page.locator('main'), `${who}: the company list`).toContainText(target.ref);
      await expect(page.getByTestId('order-placed-by').first(), `${who}: says who placed it`).toContainText('Cob Buyer');
      expect((await page.goto(detail(target.id)))?.status(), `${who}: the order`).toBe(200);
    }
    // The administrator's own list is theirs: B's order is not in it.
    await sessions.a.page.goto('/account/orders');
    await expect(sessions.a.page.locator('main')).not.toContainText(target.ref);

    // A colleague with the buyer role: no list of the company, and the order itself is a 404.
    const colleague = (invitedSession as Signed).page;
    expect((await colleague.goto('/account/orders?scope=company'))?.status()).toBe(403);
    await colleague.goto('/account/orders');
    await expect(colleague.locator('main')).not.toContainText(target.ref);
    expect((await colleague.goto(detail(target.id)))?.status(), 'colleague, direct address').toBe(404);

    // The administrator of the other company, in both directions: S2 sees nothing of S1.
    const other = sessions.x.page;
    expect((await other.goto(detail(target.id)))?.status(), 'X reads an order of S1').toBe(404);
    await other.goto('/account/orders?scope=company');
    await expect(other.locator('main')).not.toContainText(target.ref);
    await other.goto('/account/company/buyers');
    await expect(other.locator('main')).not.toContainText(b.email);
    await expect(other.getByTestId('company-buyer')).toHaveCount(1);
    expect((await other.goto('/account/company'))?.status()).toBe(200);
    await expect(other.locator('main')).not.toContainText(nameOne);

    // And the other way: S1 sees nothing of S2.
    await sessions.a.page.goto('/account/company/buyers');
    await expect(sessions.a.page.locator('main')).not.toContainText(x.email);
  });

  test('a suspended company keeps its buyers signed in, shows a banner, closes the tunnel and keeps the history', async () => {
    test.setTimeout(180_000);
    await setCompanyStateInBackOffice(admin, companyOne, 'suspend');
    try {
      expect(await companyIsActive(companyOne)).toBe(false);
      const banner = /company account is suspended/i;

      const admins = sessions.a.page;
      await admins.goto('/account/company');
      await expect(admins.locator('main').getByText(banner).first(), 'banner on the company screen').toBeVisible();
      await admins.goto('/account/orders?scope=company');
      await expect(admins.locator('main').getByText(banner).first(), 'banner on the history').toBeVisible();
      await expect(admins.locator('main')).toContainText((await ordersOf(b.email))[0].ref);

      // The buyer is still signed in and can fill a cart, but the tunnel goes no further than the cart.
      const buyer = sessions.b.page;
      await buyer.goto('/account');
      await expect(buyer.getByRole('link', { name: /company/i }).first()).toBeVisible();
      await ddevMysql(`DELETE ci FROM cart_item ci JOIN cart ca ON ca.id = ci.cart_id JOIN customer cu ON cu.id = ca.customer_id WHERE cu.email = '${b.email}'`);
      await buyer.goto(`/${TINA.slug}.html`);
      await buyer.locator('form[name="thelia_cart_add"] button[type="submit"]').first().click();
      await buyer.waitForLoadState('networkidle').catch(() => {});
      await buyer.goto('/checkout/cart');
      const orderLink = buyer.locator('a:has-text("Order")').last();
      await expect(orderLink).toBeVisible();
      await orderLink.click();
      await expect(buyer).toHaveURL(/\/checkout\/cart/);
      await expect(buyer.locator('main').getByText(banner).first()).toBeVisible();
      for (const step of ['/checkout/delivery', '/checkout/payment']) {
        await buyer.goto(step);
        await expect(buyer, `${step} is closed`).toHaveURL(/\/checkout\/cart/);
      }
      const orderCount = (await ordersOf(b.email)).length;
      expect(orderCount, 'nothing was ordered').toBe(3);
      await buyer.goto('/account/orders');
      await expect(buyer.locator('main')).toContainText((await ordersOf(b.email))[0].ref);
    } finally {
      await setCompanyStateInBackOffice(admin, companyOne, 'reactivate');
    }
    expect(await companyIsActive(companyOne)).toBe(true);

    // Reactivated, the banner is gone and the tunnel opens again.
    await sessions.a.page.goto('/account/company');
    await expect(sessions.a.page.locator('main').getByText(/company account is suspended/i)).toHaveCount(0);
    await sessions.b.page.goto('/checkout/delivery');
    await expect(sessions.b.page).toHaveURL(/\/checkout\/delivery/);
  });
});
