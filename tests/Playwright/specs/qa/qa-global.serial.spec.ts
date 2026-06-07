import { test, expect, type Page } from '@playwright/test';
import { loginAdmin } from '../../helpers/admin';
import { IssueCollector, sweepScreen, formatIssues, type PageIssue } from '../../helpers/qa';

/**
 * QA campaign — domain "global".
 *
 * GLOBAL MUTATIONS: every screen here flips state that affects the WHOLE store
 * (default language, default currency, a module's activation, the store name,
 * the Symfony container cache, the admin UI language). This spec is the ONLY one
 * allowed to touch these — and EVERY mutation is restored immediately after the
 * assertion, in a finally/afterEach so a mid-test failure cannot leave the store
 * in a mutated state. A DB snapshot exists but we never rely on it.
 *
 * Order (matches the task brief), strictly serial (config.workers=1):
 *  1. Default language switch  (en -> fr -> restore to en)
 *  2. Default currency switch  (EUR -> USD -> restore to EUR)
 *  3. Module deactivate/reactivate (HookAdminHome) + list stays usable in between
 *  4. Store config: rename store -> persist -> restore
 *  5. Advanced config: global cache flush LAST, then re-GET 3 screens (cold render)
 *  6. Admin UI language switch (fr/en) and back
 *
 * Reference for expected behaviour: BO Smarty templates/backOffice/default/*.html.
 */

test.describe.configure({ mode: 'serial' });

/** Drop the INTL-FLAG language-switcher 404 noise (missing svgFlags/<code>.svg). */
function expectClean(issues: PageIssue[]): void {
  const filtered = issues.filter((i) => {
    if (i.kind === 'console' && /Failed to load resource: the server responded with a status of 404/.test(i.detail)) return false;
    if (/svgFlags\/.*\.svg/.test(i.detail)) return false;
    return true;
  });
  expect(filtered, formatIssues(filtered)).toHaveLength(0);
}

/** Navigate a tokenized GET action url (toggle / set-default / flush) and report the resulting status. */
async function followGet(page: Page, href: string): Promise<number> {
  const res = await page.goto(href, { waitUntil: 'domcontentloaded' });
  return res?.status() ?? 0;
}

// Known fixtures from the demo install (verified against the dev DB at authoring time):
//   default language = en (id 2), other active language = fr (id 1)
//   default currency = EUR (id 1), other visible currency = USD (id 2)
//   HookAdminHome module = id 9, type classic, mandatory=0, activated.
const LANG = { defaultCode: 'en', defaultId: 2, otherCode: 'fr', otherId: 1 } as const;
const CURRENCY = { defaultId: 1, otherId: 2 } as const;
const MODULE = { code: 'HookAdminHome' } as const;

test.describe('global', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default-twig') !== 'default-twig',
    'BO Twig only — run with the default-twig back-office active.',
  );

  test.beforeEach(async ({ page }) => {
    await loginAdmin(page);
  });

  // ---------------------------------------------------------------------------
  // 1. DEFAULT LANGUAGE
  // ---------------------------------------------------------------------------
  test('default language: switch en -> fr, verify, restore to en', async ({ page }) => {
    const collector = new IssueCollector(page).attach();
    const URL = '/admin/configuration/languages';

    /** Read the toggle-default url for a given language id (the radio's data-bo-default-radio-url-value). */
    const defaultUrlFor = (id: number): Promise<string | null> =>
      page.evaluate((langId) => {
        const input = document.querySelector(
          `input[type="radio"][name="lang_default"][value="${langId}"]`,
        ) as HTMLInputElement | null;
        return input?.getAttribute('data-bo-default-radio-url-value') ?? null;
      }, id);

    /** Which language id currently carries the checked default radio? */
    const checkedDefaultId = (): Promise<number | null> =>
      page.evaluate(() => {
        const checked = document.querySelector('input[type="radio"][name="lang_default"]:checked') as HTMLInputElement | null;
        return checked ? Number(checked.value) : null;
      });

    let mutated = false;
    try {
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('languages-page')).toBeVisible();

      // Sanity: en is the current default.
      expect(await checkedDefaultId(), 'precondition: en (id 2) is the default language').toBe(LANG.defaultId);

      // SWITCH default to fr.
      const toFr = await defaultUrlFor(LANG.otherId);
      expect(toFr, 'fr row should expose a toggle-default url').toBeTruthy();
      const status = await followGet(page, toFr!);
      expect(status, `set-default(fr) should not 5xx (got ${status})`).toBeLessThan(500);
      mutated = true;

      // VERIFY persistence: re-GET, fr is now the checked default.
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      expect(await checkedDefaultId(), 'fr should now be the default language').toBe(LANG.otherId);

      // RESTORE to en.
      const backToEn = await defaultUrlFor(LANG.defaultId);
      expect(backToEn, 'en row should expose a toggle-default url').toBeTruthy();
      await followGet(page, backToEn!);
      mutated = false;

      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      expect(await checkedDefaultId(), 'en should be restored as the default language').toBe(LANG.defaultId);

      expectClean(collector.drain());
    } finally {
      // Belt-and-braces restore in case an assertion above threw while mutated.
      if (mutated) {
        await page.goto(URL, { waitUntil: 'domcontentloaded' }).catch(() => undefined);
        const backToEn = await defaultUrlFor(LANG.defaultId).catch(() => null);
        if (backToEn) await followGet(page, backToEn).catch(() => undefined);
      }
    }
  });

  // ---------------------------------------------------------------------------
  // 2. DEFAULT CURRENCY
  // ---------------------------------------------------------------------------
  test('default currency: switch EUR -> USD, verify, restore to EUR', async ({ page }) => {
    const collector = new IssueCollector(page).attach();
    const URL = '/admin/configuration/currencies';

    const defaultUrlFor = (id: number): Promise<string | null> =>
      page.evaluate((curId) => {
        const input = document.querySelector(
          `input[type="radio"][name="currency_default"][value="${curId}"]`,
        ) as HTMLInputElement | null;
        return input?.getAttribute('data-bo-default-radio-url-value') ?? null;
      }, id);

    const checkedDefaultId = (): Promise<number | null> =>
      page.evaluate(() => {
        const checked = document.querySelector('input[type="radio"][name="currency_default"]:checked') as HTMLInputElement | null;
        return checked ? Number(checked.value) : null;
      });

    let mutated = false;
    try {
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('currencies-page')).toBeVisible();

      expect(await checkedDefaultId(), 'precondition: EUR (id 1) is the default currency').toBe(CURRENCY.defaultId);

      // SWITCH default to USD.
      const toUsd = await defaultUrlFor(CURRENCY.otherId);
      expect(toUsd, 'USD row should expose a toggle-default url').toBeTruthy();
      const status = await followGet(page, toUsd!);
      expect(status, `set-default(USD) should not 5xx (got ${status})`).toBeLessThan(500);
      mutated = true;

      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      expect(await checkedDefaultId(), 'USD should now be the default currency').toBe(CURRENCY.otherId);

      // RESTORE to EUR.
      const backToEur = await defaultUrlFor(CURRENCY.defaultId);
      expect(backToEur, 'EUR row should expose a toggle-default url').toBeTruthy();
      await followGet(page, backToEur!);
      mutated = false;

      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      expect(await checkedDefaultId(), 'EUR should be restored as the default currency').toBe(CURRENCY.defaultId);

      expectClean(collector.drain());
    } finally {
      if (mutated) {
        await page.goto(URL, { waitUntil: 'domcontentloaded' }).catch(() => undefined);
        const backToEur = await defaultUrlFor(CURRENCY.defaultId).catch(() => null);
        if (backToEur) await followGet(page, backToEur).catch(() => undefined);
      }
    }
  });

  // ---------------------------------------------------------------------------
  // 3. MODULE DEACTIVATE / REACTIVATE
  // ---------------------------------------------------------------------------
  test('module HookAdminHome: deactivate -> list still usable -> reactivate', async ({ page }) => {
    const collector = new IssueCollector(page).attach();
    const URL = '/admin/modules';

    /** Locate the module row by code; return {rowSelector, toggleHref, state}. */
    const readModule = (): Promise<{ id: string; toggleHref: string | null; state: string | null } | null> =>
      page.evaluate((code) => {
        const rows = [...document.querySelectorAll('[data-testid$="-row"][data-row-id]')];
        for (const row of rows) {
          const codeCell = row.querySelector('[data-testid$="-cell-code"]');
          if (!codeCell || codeCell.textContent?.trim() !== code) continue;
          const toggle = row.querySelector('a.bo-toggle[href*="/toggle-activation/"]') as HTMLAnchorElement | null;
          return {
            id: (row as HTMLElement).dataset.rowId ?? '',
            toggleHref: toggle?.getAttribute('href') ?? null,
            state: toggle?.getAttribute('data-state') ?? null,
          };
        }
        return null;
      }, MODULE.code);

    let mutated = false;
    try {
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('modules-page')).toBeVisible();

      const before = await readModule();
      expect(before, `${MODULE.code} should be present in the module list`).not.toBeNull();
      expect(before!.state, `${MODULE.code} precondition: activated`).toBe('on');
      expect(before!.toggleHref, 'activated non-mandatory module should expose a toggle url').toBeTruthy();

      // DEACTIVATE.
      const status = await followGet(page, before!.toggleHref!);
      expect(status, `toggle-activation(off) should not 5xx (got ${status})`).toBeLessThan(500);
      mutated = true;

      // LIST STILL USABLE while the module is off: re-GET, page renders, the module reads "off".
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('modules-page'), 'module list must stay usable while a module is off').toBeVisible();
      const off = await readModule();
      expect(off!.state, `${MODULE.code} should read off after deactivation`).toBe('off');
      // The list sweep must still be clean (deactivating a hook module must not break the page).
      const midIssues = await sweepScreen(page, collector, { tabs: false, modals: false });
      expectClean(midIssues);

      // REACTIVATE.
      const reactivateStatus = await followGet(page, off!.toggleHref!);
      expect(reactivateStatus, `toggle-activation(on) should not 5xx (got ${reactivateStatus})`).toBeLessThan(500);
      mutated = false;

      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const restored = await readModule();
      expect(restored!.state, `${MODULE.code} should be reactivated`).toBe('on');

      expectClean(collector.drain());
    } finally {
      // Restore activation if a failure left the module off.
      if (mutated) {
        await page.goto(URL, { waitUntil: 'domcontentloaded' }).catch(() => undefined);
        const m = await readModule().catch(() => null);
        if (m && m.state === 'off' && m.toggleHref) await followGet(page, m.toggleHref).catch(() => undefined);
      }
    }
  });

  // ---------------------------------------------------------------------------
  // 4. STORE CONFIG — rename
  // ---------------------------------------------------------------------------
  test('store config: rename store -> persist -> restore original name', async ({ page }) => {
    const collector = new IssueCollector(page).attach();
    const URL = '/admin/configuration/store';
    const field = page.locator('[name="thelia_configuration_store[store_name]"]');

    let original: string | null = null;
    try {
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('config-store-page')).toBeVisible();
      await expect(field).toBeVisible();
      original = await field.inputValue();
      expect(original, 'store_name should have a current value').toBeTruthy();

      const newName = `${original} QA-GLOBAL-${Date.now().toString(36)}`;
      await field.fill(newName);
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('config-store-save-stay').click(),
      ]);

      // PERSISTENCE: re-GET and the field carries the new value.
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(
        page.locator('[name="thelia_configuration_store[store_name]"]'),
        'renamed store_name should persist',
      ).toHaveValue(newName);

      // RESTORE the original name.
      await page.locator('[name="thelia_configuration_store[store_name]"]').fill(original);
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('config-store-save-stay').click(),
      ]);
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(
        page.locator('[name="thelia_configuration_store[store_name]"]'),
        'original store_name should be restored',
      ).toHaveValue(original);
      original = null; // restored cleanly

      expectClean(collector.drain());
    } finally {
      if (original !== null) {
        await page.goto(URL, { waitUntil: 'domcontentloaded' }).catch(() => undefined);
        const f = page.locator('[name="thelia_configuration_store[store_name]"]');
        await f.fill(original).catch(() => undefined);
        await Promise.all([
          page.waitForLoadState('load').catch(() => undefined),
          page.getByTestId('config-store-save-stay').click().catch(() => undefined),
        ]).catch(() => undefined);
      }
    }
  });

  // ---------------------------------------------------------------------------
  // 6. ADMIN UI LANGUAGE SWITCH  (run before the cache flush so a cold rebuild
  //    cannot perturb it; the cache flush is intentionally LAST.)
  // ---------------------------------------------------------------------------
  test('admin UI language: switch to fr then back to en (top-nav dropdown)', async ({ page }) => {
    const collector = new IssueCollector(page).attach();
    // The interface-language switcher rewrites the current page url with ?lang=<code>;
    // the chosen dropdown item gets the .active class. We exercise it on the languages page.
    const URL = '/admin/configuration/languages';

    const activeLangCode = (): Promise<string | null> =>
      page.evaluate(() => {
        const active = document.querySelector('[data-testid="bo-top-nav-langs"] .dropdown-item.active') as HTMLAnchorElement | null;
        // the flag img alt is empty; derive the code from the href ?lang= param.
        if (!active) return null;
        const href = active.getAttribute('href') ?? '';
        const m = href.match(/[?&]lang=([^&]+)/);
        return m ? decodeURIComponent(m[1]) : null;
      });

    const itemHref = (code: string): Promise<string | null> =>
      page.evaluate((c) => {
        const items = [...document.querySelectorAll('[data-testid="bo-top-nav-langs"] .dropdown-item')];
        for (const a of items) {
          const href = (a as HTMLAnchorElement).getAttribute('href') ?? '';
          if (new RegExp(`[?&]lang=${c}(&|$)`).test(href)) return href;
        }
        return null;
      }, code);

    try {
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('bo-top-nav-langs')).toBeVisible();

      // SWITCH UI language to fr.
      const toFr = await itemHref(LANG.otherCode);
      expect(toFr, 'top-nav should expose a fr interface-language link').toBeTruthy();
      await page.goto(toFr!, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('languages-page'), 'page should still render after UI lang switch').toBeVisible();
      expect(await activeLangCode(), 'fr should be the active interface language').toBe(LANG.otherCode);

      // SWITCH BACK to en.
      const toEn = await itemHref(LANG.defaultCode);
      expect(toEn, 'top-nav should expose an en interface-language link').toBeTruthy();
      await page.goto(toEn!, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('languages-page')).toBeVisible();
      expect(await activeLangCode(), 'en should be the active interface language again').toBe(LANG.defaultCode);

      expectClean(collector.drain());
    } finally {
      // Restore the en UI locale (per-session, harmless if already en).
      const toEn = await itemHref(LANG.defaultCode).catch(() => null);
      if (toEn) await page.goto(toEn, { waitUntil: 'domcontentloaded' }).catch(() => undefined);
    }
  });

  // ---------------------------------------------------------------------------
  // 5. ADVANCED CONFIG — global cache flush (LAST), then re-GET 3 screens cold.
  // ---------------------------------------------------------------------------
  test('advanced config: flush global cache LAST, then 3 screens re-render cold', async ({ page }) => {
    const collector = new IssueCollector(page).attach();
    const URL = '/admin/configuration/advanced';

    await page.goto(URL, { waitUntil: 'domcontentloaded' });
    await expect(page.getByTestId('advanced-config-page')).toBeVisible();
    const flushHref = await page.getByTestId('advanced-flush-cache').getAttribute('href');
    expect(flushHref, 'global cache flush link should carry a _token').toMatch(/_token=/);

    // FLUSH the container cache (redirects back to the advanced page).
    const flushStatus = await followGet(page, flushHref!);
    expect(flushStatus, `flush cache should not 5xx (got ${flushStatus})`).toBeLessThan(500);
    await expect(page.getByTestId('advanced-config-page'), 'flush should redirect back to the advanced page').toBeVisible();

    // COLD RENDER: the first requests after a cache wipe rebuild the container. Hit three
    // representative screens and assert each renders cleanly (no 5xx, no exception page).
    const coldScreens: Array<{ url: string; testId: string }> = [
      { url: '/admin/home', testId: 'dashboard' },
      { url: '/admin/categories', testId: 'categories-page' },
      { url: '/admin/configuration', testId: 'configuration-page' },
    ];
    for (const { url, testId } of coldScreens) {
      const status = await followGet(page, url);
      expect(status, `cold re-GET ${url} should not 5xx (got ${status})`).toBeLessThan(500);
      // Most BO pages carry a page-level testid; tolerate a missing one but never an exception page.
      const marker = page.getByTestId(testId);
      if (await marker.count() > 0) {
        await expect(marker.first(), `${url} should re-render after cold cache`).toBeVisible();
      }
      // allowDanger: the dashboard surfaces danger-styled BUSINESS alerts (e.g. "N unpaid orders
      // over 48h") that are legitimate KPI content, not application errors. We only want to catch
      // 5xx / exception pages on the cold render, which the IssueCollector + scanDom title check do.
      const issues = await sweepScreen(page, collector, { tabs: false, modals: false, allowDanger: true });
      expectClean(issues);
    }
  });
});
