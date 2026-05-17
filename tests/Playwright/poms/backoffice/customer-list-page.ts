import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class CustomerListPage extends BaseAdminPage {
  readonly path = '/admin/customers';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('customers-table');
  }

  get rows(): Locator {
    return this.page.getByTestId('customers-row');
  }

  get addButton(): Locator {
    return this.page.getByTestId('customers-add-btn');
  }
}
