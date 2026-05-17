import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class ProfileListPage extends BaseAdminPage {
  readonly path = '/admin/configuration/profiles';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('datatable-profiles-table');
  }

  get rows(): Locator { return this.page.getByTestId('datatable-profiles-row'); }
  get createButton(): Locator { return this.page.getByTestId('profile-create-button'); }
  get createForm(): Locator { return this.page.getByTestId('profile-create-form'); }
}
