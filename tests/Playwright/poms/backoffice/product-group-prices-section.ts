import { type Page, type Locator } from '@playwright/test';

/** "Prices per customer group" grid of the combinations tab of a product sheet. */
export class ProductGroupPricesSection {
  constructor(private readonly page: Page, private readonly productId: number) {}

  async goto(): Promise<void> {
    await this.page.goto(`/admin/products/update?product_id=${this.productId}&current_tab=pse`);
  }

  get section(): Locator {
    return this.page.getByTestId('group-prices-section');
  }

  groupHeader(groupId: number): Locator {
    return this.page.getByTestId(`group-prices-group-${groupId}`);
  }

  priceInput(pseId: number, groupId: number): Locator {
    return this.page.getByTestId(`group-prices-price-${pseId}-${groupId}`);
  }

  get submit(): Locator {
    return this.page.getByTestId('group-prices-submit');
  }
}
