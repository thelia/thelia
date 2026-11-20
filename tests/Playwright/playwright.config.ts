import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.BASE_URL ?? 'https://thelia-3.ddev.site';

export default defineConfig({
  testDir: './specs',
  globalSetup: './global-setup.ts',
  outputDir: './test-results',
  timeout: 60_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'playwright-report', open: 'never' }],
  ],
  use: {
    baseURL,
    ignoreHTTPSErrors: true,
    locale: 'en-US',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
  },
  projects: [
    {
      name: 'chromium',
      // The customer-group, price-list and company specs have their own projects below (1440 desktop, 390 mobile).
      testIgnore: /(customer-groups|price-lists|company)-.*\.spec\.ts/,
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'customer-groups-desktop-1440',
      testMatch: /customer-groups-.*\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } },
    },
    {
      // Prices and catalog menu must also hold on a phone: same Chromium, 390x844, so the
      // theme's own breakpoints switch to the mobile layout.
      name: 'customer-groups-mobile-390',
      testMatch: /customer-groups-(prices|catalog)\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], viewport: { width: 390, height: 844 } },
    },
    {
      // Price lists, reserved assortment and conditional price display, one desktop viewport.
      name: 'price-lists-desktop-1440',
      testMatch: /price-lists-.*\.spec\.ts/,
      testIgnore: /price-lists-mobile-.*\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } },
    },
    {
      // The same displays on a 375 px phone: layout of the price blocks and accessibility scan.
      name: 'price-lists-mobile-375',
      testMatch: /price-lists-mobile-.*\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], viewport: { width: 375, height: 812 } },
    },
    {
      // Company accounts: the back-office screens and the buying journeys of the buyers of a company.
      name: 'company-desktop-1440',
      testMatch: /company-(backoffice|front)\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } },
    },
    {
      // The company screens at 1440 px and at 375 px (the spec opens both viewports itself): layout and accessibility scan.
      name: 'company-a11y',
      testMatch: /company-mobile-a11y\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } },
    },
  ],
});
