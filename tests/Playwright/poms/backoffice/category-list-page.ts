import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class CategoryListPage extends BaseAdminPage {
  readonly path = '/admin/catalog';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('categories-table');
  }

  get rows(): Locator {
    return this.page.getByTestId('categories-row');
  }

  get addButton(): Locator {
    return this.page.getByTestId('categories-add-btn');
  }
}
