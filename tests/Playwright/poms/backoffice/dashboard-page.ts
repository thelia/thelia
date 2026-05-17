import { type Page, type Locator } from '@playwright/test';
import { BaseAdminPage } from './base-page';

export class DashboardPage extends BaseAdminPage {
  readonly path = '/admin/home';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('dashboard');
  }

  /** Wrapper around the home.block hookblock fragments. */
  get homeBlocks(): Locator {
    return this.page.getByTestId('dashboard-home-block');
  }

  /** Side nav link to a given module/section (uses bo-nav-<key> testids on main-menu). */
  navLink(key: 'home' | 'customer' | 'catalog' | 'folder' | 'order' | 'tools' | 'modules' | 'configuration'): Locator {
    return this.page.getByTestId(`bo-nav-${key}`);
  }
}
