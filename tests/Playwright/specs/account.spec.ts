import { expect } from '@playwright/test';
import { test } from '../fixtures/customer';
import { login } from '../helpers/customer';
import {
  countAddresses,
  createAddress,
  deleteFirstNonDefaultAddress,
  gotoAddresses,
  setNonDefaultAsDefault,
} from '../helpers/account';

test.describe('Account', () => {
  test('after register, addresses list shows the main address', async ({ authedPage }) => {
    await gotoAddresses(authedPage);
    expect(await countAddresses(authedPage)).toBe(1);
  });

  test('create a second address and delete it', async ({ authedPage, freshCustomer }) => {
    await createAddress(authedPage, {
      ...freshCustomer.address,
      label: 'Office',
      address1: '5 avenue du Test',
      city: 'Lyon',
      zipcode: '69000',
    });
    expect(await countAddresses(authedPage)).toBe(2);

    await deleteFirstNonDefaultAddress(authedPage);
    expect(await countAddresses(authedPage)).toBe(1);
  });

  test('set the new address as default', async ({ authedPage, freshCustomer }) => {
    await createAddress(authedPage, {
      ...freshCustomer.address,
      label: 'Secondary',
      address1: '99 boulevard Test',
      city: 'Lyon',
      zipcode: '69001',
    });
    await setNonDefaultAsDefault(authedPage);
    // Now the formerly-non-default is the default; one Favorite (selected) remains, no other.
    await gotoAddresses(authedPage);
    const favorites = authedPage.locator('.AddressCard .Favorite.selected');
    await expect(favorites).toHaveCount(1);
  });

  test('change password and re-login with the new one', async ({ authedPage, freshCustomer }) => {
    await authedPage.goto('/account');
    const newPassword = 'NewSecret!9';
    await authedPage.fill('input[name="thelia_customer_password_update[password_old]"]', freshCustomer.password);
    await authedPage.fill('input[name="thelia_customer_password_update[password]"]', newPassword);
    await authedPage.fill('input[name="thelia_customer_password_update[password_confirm]"]', newPassword);
    await Promise.all([
      authedPage.waitForURL(/\/account/),
      authedPage.locator('form[name="thelia_customer_password_update"] button[type="submit"]').click(),
    ]);

    // Logout, then login with the new password.
    await authedPage.goto('/customer/logout');
    await login(authedPage, freshCustomer.email, newPassword);
    await expect(authedPage).toHaveURL(/\/account/);
  });

  test('a rejected profile update keeps the email address and flags no error on it', async ({
    authedPage,
    freshCustomer,
  }) => {
    await authedPage.goto('/account');
    const email = '#flexybundle_form_customer_update_form_email';
    await expect(authedPage.locator(email)).toHaveValue(freshCustomer.email);

    // An empty first name makes the profile form invalid, which sends it back
    // through the parser context. The email is read-only: it must survive.
    // The browser would refuse to submit a required field left empty, so the
    // server-side validation is what we want to reach here.
    await authedPage.fill('#flexybundle_form_customer_update_form_firstname', '');
    await authedPage
      .locator('form[name="flexybundle_form_customer_update_form"]')
      .evaluate((form: HTMLFormElement) => {
        form.noValidate = true;
      });
    await Promise.all([
      authedPage.waitForResponse(
        (response) => response.url().includes('/customer/update') && response.request().method() === 'POST',
      ),
      authedPage.locator('form[name="flexybundle_form_customer_update_form"] button[type="submit"]').click(),
    ]);
    await authedPage.waitForLoadState('load');

    await expect(authedPage).toHaveURL(/\/account/);
    await expect(authedPage.locator(email)).toHaveValue(freshCustomer.email);
    const emailRow = authedPage.locator(`.FieldWrapper:has(${email})`);
    await expect(emailRow.locator('.FieldInput-error')).toHaveCount(0);
  });

  test('an accepted profile update saves the new name and leaves the email untouched', async ({
    authedPage,
    freshCustomer,
  }) => {
    await authedPage.goto('/account');
    await authedPage.fill('#flexybundle_form_customer_update_form_firstname', 'Renamed');
    await Promise.all([
      authedPage.waitForResponse(
        (response) => response.url().includes('/customer/update') && response.request().method() === 'POST',
      ),
      authedPage.locator('form[name="flexybundle_form_customer_update_form"] button[type="submit"]').click(),
    ]);
    await authedPage.waitForLoadState('load');

    await authedPage.goto('/account');
    await expect(authedPage.locator('#flexybundle_form_customer_update_form_firstname')).toHaveValue('Renamed');
    await expect(authedPage.locator('#flexybundle_form_customer_update_form_email')).toHaveValue(freshCustomer.email);
  });
});
