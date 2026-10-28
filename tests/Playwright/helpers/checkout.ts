import { expect, type Page } from '@playwright/test';
import { ddevMysql } from './db';

export async function gotoCheckoutDelivery(page: Page): Promise<void> {
  await page.goto('/checkout/delivery');
  await expect(page).toHaveURL(/\/checkout\/delivery/);
}

export async function selectDeliveryByLabel(page: Page, label: RegExp | string): Promise<void> {
  // Each delivery method renders as a `.DeliveryMode` block — click the radio inside
  // the one whose title matches the label.
  const matcher = typeof label === 'string' ? new RegExp(label, 'i') : label;
  const card = page.locator('.DeliveryMode').filter({ hasText: matcher }).first();
  await card.locator('.RadioButton').click();
  await page.waitForLoadState('networkidle').catch(() => {});
}

export async function selectFirstDeliveryMethod(page: Page): Promise<void> {
  // Click the radio that emits SET_DELIVERY_MODULE_OPTION. Cards expose the radio at the
  // top-level wrapper so the LiveComponent intercepts the event reliably.
  const radio = page
    .locator('button[data-live-event-param="SET_DELIVERY_MODULE_OPTION"]')
    .first();
  await radio.click();
  await page.waitForLoadState('networkidle').catch(() => {});
  // Live components round-trip — give the NextButton a moment to re-evaluate isValid.
  await page.waitForTimeout(1500);
}

export async function clickNextOrder(page: Page, expectedNextUrl: RegExp): Promise<void> {
  // The "Order" CTA is rendered as a <button disabled> while the step is invalid and as an
  // <a href> once the LiveComponent has accepted the previous step. If the link still hasn't
  // shown up after the live round-trip, force a reload — the server-rendered NextButton then
  // reflects the persisted cart state.
  let orderLink = page.locator('a:has-text("Order")').last();
  try {
    await expect(orderLink).toBeVisible({ timeout: 8_000 });
  } catch {
    await page.reload();
    orderLink = page.locator('a:has-text("Order")').last();
    await expect(orderLink).toBeVisible({ timeout: 12_000 });
  }
  await Promise.all([
    page.waitForURL(expectedNextUrl, { timeout: 20_000 }),
    orderLink.click(),
  ]);
}

export async function gotoCheckoutPayment(page: Page): Promise<void> {
  await page.goto('/checkout/payment');
  await expect(page).toHaveURL(/\/checkout\/payment/);
}

export async function selectPaymentByLabel(page: Page, label: RegExp | string): Promise<void> {
  const matcher = typeof label === 'string' ? new RegExp(label, 'i') : label;
  const card = page.locator('.PaymentCard').filter({ hasText: matcher }).first();
  const radio = card.locator('button[data-live-event-param="SET_PAYMENT_MODULE_ID"]').first();
  await radio.click();
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(1500);
}

/**
 * Ticks every mandatory consent box of the payment step.
 *
 * The shop refuses an order until each active mandatory consent is accepted
 * (`ConsentGuard::checkMandatoryConsentsAccepted`), and `Organisms:NextButton` greys the
 * "Order" CTA out on the very same rule — so without this the payment step never offers a
 * link to click. The demo dataset ships `terms_and_conditions` and `age_over_18` mandatory.
 *
 * The raw input is hidden behind the `.Checkbox` label styling, so the label is the click
 * target; each answer is a live action that re-renders the step, hence the re-query per box.
 */
export async function acceptMandatoryConsents(page: Page): Promise<void> {
  const boxes = page.locator('input[id^="consent-"][required]');
  const codes: string[] = [];
  for (const box of await boxes.all()) {
    const id = await box.getAttribute('id');
    if (id) codes.push(id);
  }

  for (const id of codes) {
    const input = page.locator(`#${id}`);
    if (await input.isChecked()) continue;
    await page.locator(`label:has(#${id})`).click();
    await expect(input).toBeChecked({ timeout: 10_000 });
  }

  // The CTA re-evaluates isValid on the live round-trip each answer triggers.
  await page.waitForLoadState('networkidle').catch(() => {});
}

export async function selectFirstInvoiceAddress(page: Page): Promise<void> {
  const radio = page
    .locator('button[data-live-event-param="SET_INVOICE_ORDER_ADDRESS_ID"]')
    .first();
  if (await radio.count() > 0) {
    await radio.click();
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(800);
  }
}

/** Walks a signed-in customer with a filled cart from /checkout/cart to the payment step (delivery picked, invoice address set). */
export async function goToPaymentStep(page: Page): Promise<void> {
  await page.goto('/checkout/cart');
  await clickNextOrder(page, /\/checkout\/delivery/);
  await selectFirstDeliveryMethod(page);
  await clickNextOrder(page, /\/checkout\/payment/);
  await selectFirstInvoiceAddress(page);
}

/**
 * Walks a signed-in customer with a filled cart from /checkout/cart to a placed order paid by
 * Cheque (the demo payment module that needs no gateway), and returns the order reference read
 * back from the database.
 *
 * `clickNextOrder` is not used for the last step on purpose: its `/checkout/(pay|confirm|invoice)`
 * pattern also matches `/checkout/payment`, so it would return before the order exists. The wait
 * here is on leaving the payment step, then on the order row itself.
 */
export async function placeOrderWithCheque(page: Page, customerEmail: string): Promise<string> {
  await goToPaymentStep(page);
  await selectPaymentByLabel(page, /cheque|check/i);
  await acceptMandatoryConsents(page);

  let orderLink = page.locator('a:has-text("Order")').last();
  try {
    await expect(orderLink).toBeVisible({ timeout: 8_000 });
  } catch {
    await page.reload();
    orderLink = page.locator('a:has-text("Order")').last();
    await expect(orderLink).toBeVisible({ timeout: 12_000 });
  }
  await Promise.all([
    page.waitForURL((url) => !/\/checkout\/payment/.test(url.pathname), { timeout: 30_000 }),
    orderLink.click(),
  ]);

  const email = customerEmail.replace(/'/g, "''");
  await expect
    .poll(
      async () => ddevMysql(`SELECT o.ref FROM \`order\` o JOIN customer c ON c.id = o.customer_id WHERE c.email = '${email}' ORDER BY o.id DESC LIMIT 1`),
      { timeout: 15_000, message: 'the order must exist in the database' },
    )
    .not.toBe('');
  return ddevMysql(`SELECT o.ref FROM \`order\` o JOIN customer c ON c.id = o.customer_id WHERE c.email = '${email}' ORDER BY o.id DESC LIMIT 1`);
}
