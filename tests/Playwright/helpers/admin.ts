import { type Page } from '@playwright/test';

export type AdminCredentials = {
  username: string;
  password: string;
};

export const DEFAULT_ADMIN: AdminCredentials = {
  username: process.env.ADMIN_USER ?? 'thelia',
  password: process.env.ADMIN_PASSWORD ?? 'thelia',
};

export async function loginAdmin(page: Page, credentials: AdminCredentials = DEFAULT_ADMIN): Promise<void> {
  await page.goto('/admin/login', { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="thelia_admin_login[username]"]', credentials.username);
  await page.fill('input[name="thelia_admin_login[password]"]', credentials.password);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.includes('/admin/login'), { timeout: 15_000 }),
    page.locator('form[action$="/admin/checklogin"] button[type="submit"]').click(),
  ]);
}
