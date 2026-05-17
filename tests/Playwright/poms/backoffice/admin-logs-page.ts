import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class AdminLogsPage extends BaseAdminPage {
  readonly path = '/admin/configuration/adminLogs';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('admin-logs-filter-form');
  }

  get filterForm(): Locator {
    return this.page.getByTestId('admin-logs-filter-form');
  }

  get showButton(): Locator {
    return this.page.getByTestId('admin-logs-show-button');
  }

  get fromDate(): Locator {
    return this.page.getByTestId('admin-logs-from-date');
  }

  get toDate(): Locator {
    return this.page.getByTestId('admin-logs-to-date');
  }

  get results(): Locator {
    return this.page.getByTestId('admin-logs-results');
  }

  get rows(): Locator {
    return this.page.getByTestId('admin-logs-row');
  }

  get adminsBlock(): Locator {
    return this.page.getByTestId('admin-logs-admins');
  }

  get resourcesBlock(): Locator {
    return this.page.getByTestId('admin-logs-resources');
  }

  get modulesBlock(): Locator {
    return this.page.getByTestId('admin-logs-modules');
  }
}
