import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class ProductListPage extends BaseAdminPage {
  readonly path = '/admin/products';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('products-table');
  }

  get rows(): Locator {
    return this.page.getByTestId('products-row');
  }

  get addButton(): Locator {
    return this.page.getByTestId('products-add-btn');
  }

  /** Category sidebar selector (links to /admin/catalog?category_id=…). */
  categoryLink(name: RegExp | string): Locator {
    return this.page.getByRole('link', { name });
  }
}
