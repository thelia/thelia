import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class TaxRuleEditPage extends BaseAdminPage {
  readonly readyLocator: Locator;
  readonly path: string;

  constructor(page: Page, taxRuleId: number) {
    super(page);
    this.readyLocator = page.getByTestId('tax-rule-edit-page');
    this.path = `/admin/configuration/taxes_rules/update/${taxRuleId}`;
  }

  get form(): Locator {
    return this.page.getByTestId('tax-rule-edit-form');
  }

  get titleHeading(): Locator {
    return this.page.getByTestId('tax-rule-edit-title');
  }

  get matrixSection(): Locator {
    return this.page.getByTestId('tax-rule-matrix');
  }

  get taxesTab(): Locator {
    return this.page.locator('#tax-rule-tab-taxes');
  }

  get submitButton(): Locator {
    return this.page.getByTestId('tax-rule-edit-submit');
  }

  get cancelLink(): Locator {
    return this.page.getByTestId('tax-rule-edit-cancel');
  }
}
