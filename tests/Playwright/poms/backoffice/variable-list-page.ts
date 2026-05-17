import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class VariableListPage extends BaseAdminPage {
  readonly path = '/admin/configuration/variables';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('datatable-variables-table');
  }

  get rows(): Locator {
    return this.page.getByTestId('datatable-variables-row');
  }

  get createButton(): Locator {
    return this.page.getByTestId('variable-create-button');
  }

  get createForm(): Locator {
    return this.page.getByTestId('variable-create-form');
  }

  get createSubmit(): Locator {
    return this.page.getByTestId('variable-create-submit');
  }

  get updateValuesForm(): Locator {
    return this.page.getByTestId('variable-update-values-form');
  }

  get updateValuesSubmit(): Locator {
    return this.page.getByTestId('variable-update-values-submit');
  }
}
