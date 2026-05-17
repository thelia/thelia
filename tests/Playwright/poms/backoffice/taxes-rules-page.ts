import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class TaxesRulesPage extends BaseAdminPage {
  readonly path = '/admin/configuration/taxes_rules';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('taxes-rules-page');
  }

  get taxesSection(): Locator {
    return this.page.getByTestId('taxes-section');
  }

  get taxRulesSection(): Locator {
    return this.page.getByTestId('tax-rules-section');
  }

  get createTaxButton(): Locator {
    return this.page.getByTestId('tax-create-button');
  }

  get createTaxForm(): Locator {
    return this.page.getByTestId('tax-create-form');
  }

  get createTaxSubmit(): Locator {
    return this.page.getByTestId('tax-create-submit');
  }

  get deleteTaxForm(): Locator {
    return this.page.getByTestId('tax-delete-form');
  }

  get createTaxRuleButton(): Locator {
    return this.page.getByTestId('tax-rule-create-button');
  }

  get createTaxRuleForm(): Locator {
    return this.page.getByTestId('tax-rule-create-form');
  }

  get createTaxRuleSubmit(): Locator {
    return this.page.getByTestId('tax-rule-create-submit');
  }

  get deleteTaxRuleForm(): Locator {
    return this.page.getByTestId('tax-rule-delete-form');
  }
}
