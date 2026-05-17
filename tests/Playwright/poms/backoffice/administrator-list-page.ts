import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class AdministratorListPage extends BaseAdminPage {
  readonly path = '/admin/configuration/administrators';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('datatable-administrators-table');
  }

  get rows(): Locator { return this.page.getByTestId('datatable-administrators-row'); }
  get createButton(): Locator { return this.page.getByTestId('administrator-create-button'); }
  get createForm(): Locator { return this.page.getByTestId('administrator-create-form'); }
}
