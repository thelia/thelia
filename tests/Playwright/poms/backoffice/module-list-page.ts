import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class ModuleListPage extends BaseAdminPage {
  readonly path = '/admin/modules';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('modules-list');
  }

  get classicBlock(): Locator {
    return this.page.getByTestId('modules-block-classic');
  }

  get paymentBlock(): Locator {
    return this.page.getByTestId('modules-block-payment');
  }

  get deliveryBlock(): Locator {
    return this.page.getByTestId('modules-block-delivery');
  }
}
