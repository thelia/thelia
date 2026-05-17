import { test, expect } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';

test.describe('Back-office — navigation (smoke)', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default') !== 'default',
    'Legacy Smarty markup; not applicable under default-twig.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
    await page.goto('/admin/home', { waitUntil: 'domcontentloaded' });
  });

  // We use href-based locators in the main side-nav: the link text is wrapped
  // inside <span class="item-text"> + icon, which makes name-based accessible
  // role queries flaky depending on how the role is resolved. Stable approach:
  // grab the visible link in the side-nav by its href prefix.
  const sections: Array<{ key: string; href: RegExp; url: RegExp }> = [
    { key: 'home',          href: /\/admin\/home$/,           url: /\/admin\/home$/ },
    { key: 'customers',     href: /\/admin\/customers$/,      url: /\/admin\/customers/ },
    { key: 'orders',        href: /\/admin\/orders$/,         url: /\/admin\/orders/ },
    { key: 'catalog',       href: /\/admin\/catalog$/,        url: /\/admin\/catalog/ },
    { key: 'folders',       href: /\/admin\/folders$/,        url: /\/admin\/folders/ },
    { key: 'modules',       href: /\/admin\/modules$/,        url: /\/admin\/modules/ },
    { key: 'configuration', href: /\/admin\/configuration$/,  url: /\/admin\/configuration/ },
  ];

  for (const section of sections) {
    test(`reaches "${section.key}" section`, async ({ page }) => {
      // Pick the first link in the main side-nav matching this href.
      const link = page
        .getByTestId('bo-main-nav')
        .locator(`a[href$="${section.key === 'home' ? '/admin/home' : `/admin/${section.key}`}"]`)
        .first();
      await expect(link).toBeAttached();
      // Some entries (e.g. Customers, Orders) are inside collapsed submenus;
      // navigate via URL rather than relying on click+visibility.
      await link.evaluate((el: HTMLElement) => el.click());
      await expect(page).toHaveURL(section.url, { timeout: 10_000 });
    });
  }

  test('main side nav has expected data-testid', async ({ page }) => {
    await expect(page.getByTestId('bo-main-nav')).toBeVisible();
    await expect(page.getByTestId('bo-nav-home')).toBeVisible();
  });
});
