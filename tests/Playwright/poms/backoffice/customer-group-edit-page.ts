import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

/** Back-office customer group sheet: settings, whitelists, warnings and registration link. */
export class CustomerGroupEditPage extends BaseAdminPage {
  readonly path: string;
  readonly readyLocator: Locator;

  constructor(page: Page, groupId: number) {
    super(page);
    this.path = `/admin/configuration/customer-groups/update/${groupId}`;
    this.readyLocator = page.getByTestId('customer-group-edit-page');
  }

  field(name: string): Locator {
    return this.page.locator(`#thelia_customer_group_modification_${name}`);
  }

  get discount(): Locator {
    return this.field('discount');
  }

  get displayTaxedPrice(): Locator {
    return this.field('display_taxed_price');
  }

  get paymentModules(): Locator {
    return this.page.getByTestId('customer-group-payment-modules');
  }

  get categories(): Locator {
    return this.page.getByTestId('customer-group-categories');
  }

  get submit(): Locator {
    return this.page.getByTestId('customer-group-edit-submit');
  }

  get categoriesEmptiedWarning(): Locator {
    return this.page.getByTestId('customer-group-categories-emptied');
  }

  get categoriesLift(): Locator {
    return this.page.getByTestId('customer-group-categories-lift');
  }

  get registrationUrl(): Locator {
    return this.page.getByTestId('customer-group-registration-url');
  }

  get registrationOpen(): Locator {
    return this.page.getByTestId('customer-group-registration-open');
  }

  get registrationRegenerate(): Locator {
    return this.page.getByTestId('customer-group-registration-regenerate');
  }

  get registrationClose(): Locator {
    return this.page.getByTestId('customer-group-registration-close');
  }

  async save(): Promise<void> {
    await Promise.all([this.page.waitForLoadState('domcontentloaded'), this.submit.click()]);
  }
}
