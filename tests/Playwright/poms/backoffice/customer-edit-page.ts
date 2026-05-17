import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class CustomerEditPage extends BaseAdminPage {
  readonly readyLocator: Locator;
  readonly path: string;

  constructor(page: Page, customerId: number) {
    super(page);
    this.readyLocator = page.getByTestId('customer-edit-page');
    this.path = `/admin/customer/update?customer_id=${customerId}`;
  }

  get form(): Locator {
    return this.page.getByTestId('customer-edit-form');
  }

  get titleHeading(): Locator {
    return this.page.getByTestId('customer-edit-title');
  }

  get submitButton(): Locator {
    return this.page.getByTestId('customer-edit-submit');
  }

  get cancelLink(): Locator {
    return this.page.getByTestId('customer-edit-cancel');
  }

  firstNameInput(formName = 'thelia_customer_update'): Locator {
    return this.page.locator(`#${formName}_firstname`);
  }

  emailInput(formName = 'thelia_customer_update'): Locator {
    return this.page.locator(`#${formName}_email`);
  }
}
