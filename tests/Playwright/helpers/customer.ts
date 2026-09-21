import { expect, type Page } from '@playwright/test';

export type Customer = {
  firstname: string;
  lastname: string;
  email: string;
  password: string;
  address: {
    address1: string;
    zipcode: string;
    city: string;
    countryCode: string; // ISO alpha-2 (e.g. 'FR')
    countryLabel: string;
    phone: string;
  };
};

export function newCustomer(overrides: Partial<Customer> = {}): Customer {
  const id = `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
  return {
    firstname: 'E2E',
    lastname: 'Tester',
    email: `e2e.${id}@example.test`,
    password: 'CorrectHorseBattery!9',
    address: {
      address1: '12 rue du Test',
      zipcode: '44000',
      city: 'Nantes',
      countryCode: 'FR',
      countryLabel: 'France metropolitan',
      phone: '0240000000',
    },
    ...overrides,
  };
}

export async function registerCustomer(page: Page, customer: Customer): Promise<void> {
  await page.goto('/customer/register');
  await page.fill('#flexybundle_form_customer_register_form_firstname', customer.firstname);
  await page.fill('#flexybundle_form_customer_register_form_lastname', customer.lastname);
  await page.fill('#flexybundle_form_customer_register_form_email', customer.email);
  await page.fill('#flexybundle_form_customer_register_form_password', customer.password);
  await Promise.all([
    page.waitForURL('**/customer/informations'),
    page.locator('form[name="flexybundle_form_customer_register_form"] button[type="submit"]').click(),
  ]);

  // Step 2 — full address (title, address1, city, zipcode, country, phones, privacy).
  await selectFirstChoice(page, 'select[name$="[title]"]');
  await page.fill('input[name$="[address1]"]', customer.address.address1).catch(() => {});
  await page.fill('input[name$="[city]"]', customer.address.city).catch(() => {});
  await page.fill('input[name$="[zipcode]"]', customer.address.zipcode).catch(() => {});
  await selectCountry(page, customer.address);
  // DynamicForms may inject a state select after country pick — wait then fill if required.
  await page.waitForTimeout(700);
  await selectFirstChoice(page, 'select[name$="[state]"]', /* optional */ true);
  await page.fill('input[name$="[phone]"]', customer.address.phone).catch(() => {});
  await page.fill('input[name$="[cellphone]"]', customer.address.phone).catch(() => {});
  // The privacy checkbox is hidden by CSS; the wrapping <label> is the visible click target.
  await page.locator('label:has(input[name$="[accept_privacy_policy]"])').click();

  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/customer/informations'), { timeout: 30_000 }),
    page.locator('button[type="submit"]').last().click(),
  ]);
  // Step 2 carries no success_url: an enabled customer is signed in by step 1 and comes back
  // to the page that asked them to register, /account by default. An account still waiting for
  // its activation code stays signed out and lands on /customer/activation instead.
}

export async function login(page: Page, email: string, password: string): Promise<void> {
  await page.goto('/customer/login');
  await page.fill('#thelia_customer_login_email', email);
  await page.fill('#thelia_customer_login_password', password);
  await Promise.all([
    page.waitForURL('**/account', { timeout: 15_000 }),
    page.locator('form[name="thelia_customer_login"] button[type="submit"]').click(),
  ]);
}

export async function logout(page: Page): Promise<void> {
  await page.goto('/customer/logout');
  await expect(page).toHaveURL(/\/$|\/customer\/login/);
}

export async function selectCountry(page: Page, addr: { countryCode: string; countryLabel: string }): Promise<void> {
  const select = page.locator('select[name$="[country]"]');
  if (await select.count() === 0) return;

  const byCode = select.locator(`option[data-iso-code="${addr.countryCode}"]`);
  if (await byCode.count() > 0) {
    const value = await byCode.first().getAttribute('value');
    if (value) {
      await select.selectOption({ value });
      return;
    }
  }

  // Fallback: pick the option whose visible text contains the human-readable label.
  try {
    await select.selectOption({ label: addr.countryLabel });
    return;
  } catch {
    /* fall through */
  }
  await selectFirstChoice(page, 'select[name$="[country]"]');
}

async function selectFirstChoice(page: Page, selector: string, optional = false): Promise<void> {
  const select = page.locator(selector);
  if (await select.count() === 0) {
    if (!optional) {
      // Some required selects might still be missing — ignore silently.
    }
    return;
  }
  const firstOption = select.locator('option:not([value=""])').first();
  if (await firstOption.count() === 0) return;
  const value = await firstOption.getAttribute('value');
  if (value !== null) await select.selectOption({ value });
}
