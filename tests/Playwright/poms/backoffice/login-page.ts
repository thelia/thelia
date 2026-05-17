import { type Page, type Locator, expect } from '@playwright/test';
import { BaseAdminPage } from './base-page';
import { DEFAULT_ADMIN, type AdminCredentials } from '../../helpers/admin';

export class LoginPage extends BaseAdminPage {
  readonly path = '/admin/login';
  readonly readyLocator: Locator;

  constructor(page: Page) {
    super(page);
    this.readyLocator = page.getByTestId('login-form');
  }

  get usernameInput(): Locator {
    return this.page.getByTestId('login-username');
  }

  get passwordInput(): Locator {
    return this.page.getByTestId('login-password');
  }

  get submitButton(): Locator {
    return this.page.getByTestId('login-submit');
  }

  get errorMessage(): Locator {
    return this.page.getByTestId('login-error');
  }

  get lostPasswordLink(): Locator {
    return this.page.getByRole('link', { name: /forgot|password/i });
  }

  async login(credentials: AdminCredentials = DEFAULT_ADMIN): Promise<void> {
    await this.goto();
    await this.usernameInput.fill(credentials.username);
    await this.passwordInput.fill(credentials.password);
    await Promise.all([
      this.page.waitForURL((url) => !url.pathname.includes('/admin/login'), { timeout: 15_000 }),
      this.submitButton.click(),
    ]);
  }

  async expectLoginFailed(): Promise<void> {
    await expect(this.errorMessage).toBeVisible();
    await expect(this.page).toHaveURL(/\/admin\/login/);
  }
}
