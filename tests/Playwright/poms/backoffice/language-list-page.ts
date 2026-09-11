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

  get defaultRadios(): Locator {
    return this.page.locator('input[name="lang_default"]');
  }

  defaultRadioFor(langId: number): Locator {
    return this.page.locator(`input[name="lang_default"][value="${langId}"]`);
  }

  /** Value of the radio the server rendered as checked, i.e. the language currently by default. */
  async checkedDefaultValue(): Promise<string | null> {
    return this.page.locator('input[name="lang_default"]:checked').first().getAttribute('value');
  }
}
