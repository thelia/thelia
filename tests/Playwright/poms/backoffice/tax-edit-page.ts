import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class TaxEditPage extends BaseAdminPage {
  readonly readyLocator: Locator;
  readonly path: string;

  constructor(page: Page, taxId: number) {
    super(page);
    this.readyLocator = page.getByTestId('tax-edit-page');
    this.path = `/admin/configuration/taxes/update/${taxId}`;
  }

  get form(): Locator {
    return this.page.getByTestId('tax-edit-form');
  }

  get titleHeading(): Locator {
    return this.page.getByTestId('tax-edit-title');
  }

  get typeSelect(): Locator {
    return this.page.locator('#thelia_tax_modification_type');
  }

  get submitButton(): Locator {
    return this.page.getByTestId('tax-edit-submit');
  }

  get cancelLink(): Locator {
    return this.page.getByTestId('tax-edit-cancel');
  }

  get titleInput(): Locator {
    return this.page.locator('#thelia_tax_modification_title');
  }

  requirementGroup(taxType: string): Locator {
    return this.page.locator(`.tax-requirement[data-tax-type="${taxType}"]`);
  }
}
