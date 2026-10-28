import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

/** Back-office > Configuration > Customer groups (list and creation dialog). */
export class CustomerGroupListPage extends BaseAdminPage {
  readonly path = '/admin/configuration/customer-groups';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('customer-groups-page');
  }

  get rows(): Locator {
    return this.page.getByTestId('datatable-customer-groups-row');
  }

  /** The row of a group, found by its code. */
  rowOf(code: string): Locator {
    return this.rows.filter({ has: this.page.getByTestId('datatable-customer-groups-cell-code').filter({ hasText: code }) });
  }

  get createButton(): Locator {
    return this.page.getByTestId('customer-group-create-button');
  }

  get createDialog(): Locator {
    return this.page.locator('#customer-group-create-modal');
  }

  async create(input: { code: string; title: string; discount: number; taxed: boolean }): Promise<void> {
    await this.createButton.click();
    const dialog = this.createDialog;
    await dialog.locator('input[name$="[code]"]').fill(input.code);
    await dialog.locator('input[name$="[title]"]').fill(input.title);
    await dialog.locator('input[name$="[discount]"]').fill(String(input.discount));
    const taxed = dialog.locator('input[name$="[display_taxed_price]"]');
    if ((await taxed.isChecked()) !== input.taxed) await taxed.setChecked(input.taxed);
    await dialog.locator('button[type="submit"]').click();
  }
}
