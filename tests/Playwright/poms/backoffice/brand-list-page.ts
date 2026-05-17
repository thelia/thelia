import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class BrandListPage extends BaseAdminPage {
  readonly path = '/admin/brand';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('brands-table');
  }

  get rows(): Locator {
    return this.page.getByTestId('brands-row');
  }

  get addButton(): Locator {
    return this.page.getByTestId('brands-add-btn');
  }
}
