import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class OrderListPage extends BaseAdminPage {
  readonly path = '/admin/orders';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('orders-table');
  }

  get rows(): Locator {
    return this.page.getByTestId('orders-row');
  }
}
