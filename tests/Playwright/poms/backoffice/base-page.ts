import { type Page, type Locator, expect } from '@playwright/test';

/**
 * Base class for all back-office Page Objects.
 *
 * Convention:
 * - Locators are exposed as getters (lazy).
 * - Actions return Promise<void>; assertions return Promise<void>.
 * - Selectors use data-testid first, role/label second; CSS structural last.
 */
export abstract class BaseAdminPage {
  constructor(protected readonly page: Page) {}

  /** Absolute or relative URL of this page. Override in subclasses. */
  abstract readonly path: string;

  /** Marker locator used to assert the page is loaded. */
  abstract readonly readyLocator: Locator;

  async goto(): Promise<void> {
    await this.page.goto(this.path, { waitUntil: 'domcontentloaded' });
  }

  async expectLoaded(): Promise<void> {
    await expect(this.readyLocator).toBeVisible();
  }

  get mainNav(): Locator {
    return this.page.getByTestId('bo-main-nav');
  }

  get pageTitle(): Locator {
    return this.page.locator('title');
  }

  /** Generic flash/alert messages. */
  get flashError(): Locator {
    return this.page.locator('.alert-danger').first();
  }

  get flashSuccess(): Locator {
    return this.page.locator('.alert-success').first();
  }
}
