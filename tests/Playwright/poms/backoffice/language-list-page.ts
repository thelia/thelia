import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class LanguageListPage extends BaseAdminPage {
  readonly path = '/admin/configuration/languages';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('datatable-languages-table');
  }

  get rows(): Locator {
    return this.page.getByTestId('datatable-languages-row');
  }

  get createButton(): Locator {
    return this.page.getByTestId('lang-create-button');
  }

  get createForm(): Locator {
    return this.page.getByTestId('lang-create-form');
  }

  get createSubmit(): Locator {
    return this.page.getByTestId('lang-create-submit');
  }

  get defaultBehaviorForm(): Locator {
    return this.page.getByTestId('lang-default-behavior-form');
  }

  get urlForm(): Locator {
    return this.page.getByTestId('lang-url-form');
  }
}
