import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class CurrencyListPage extends BaseAdminPage {
  readonly path = '/admin/configuration/currencies';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('datatable-currencies-table');
  }

  get rows(): Locator {
    return this.page.getByTestId('datatable-currencies-row');
  }

  get createButton(): Locator {
    return this.page.getByTestId('currency-create-button');
  }

  get createForm(): Locator {
    return this.page.getByTestId('currency-create-form');
  }

  get createSubmit(): Locator {
    return this.page.getByTestId('currency-create-submit');
  }

  get updateRatesButton(): Locator {
    return this.page.getByTestId('currency-update-rates-button');
  }
}
