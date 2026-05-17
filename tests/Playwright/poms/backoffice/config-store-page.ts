import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class ConfigStorePage extends BaseAdminPage {
  readonly path = '/admin/configuration/store';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('config-store-page');
  }

  get form(): Locator {
    return this.page.getByTestId('config-store-form');
  }

  get saveAndStayButton(): Locator {
    return this.page.getByTestId('config-store-save-stay');
  }

  get saveAndCloseButton(): Locator {
    return this.page.getByTestId('config-store-save-close');
  }

  get cancelLink(): Locator {
    return this.page.getByTestId('config-store-cancel');
  }

  storeNameInput(): Locator {
    return this.page.locator('#thelia_configuration_store_store_name');
  }

  storeEmailInput(): Locator {
    return this.page.locator('#thelia_configuration_store_store_email');
  }

  storeCountrySelect(): Locator {
    return this.page.locator('#thelia_configuration_store_store_country');
  }
}
