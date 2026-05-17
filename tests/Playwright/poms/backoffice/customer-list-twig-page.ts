import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class CustomerListTwigPage extends BaseAdminPage {
  readonly path = '/admin/customers';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('customers-page');
  }

  get rows(): Locator {
    return this.page.getByTestId('datatable-customers-row');
  }

  get count(): Locator {
    return this.page.getByTestId('customers-count');
  }

  get searchInput(): Locator {
    return this.page.getByTestId('customers-search-input');
  }

  get searchSubmit(): Locator {
    return this.page.getByTestId('customers-search-submit');
  }

  get searchClear(): Locator {
    return this.page.getByTestId('customers-search-clear');
  }

  get createButton(): Locator {
    return this.page.getByTestId('customer-create-button');
  }

  get createForm(): Locator {
    return this.page.getByTestId('customer-create-form');
  }

  get createSubmit(): Locator {
    return this.page.getByTestId('customer-create-submit');
  }

  get deleteForm(): Locator {
    return this.page.getByTestId('customer-delete-form');
  }
}
