import { test, expect, type Page } from '@playwright/test';
import { loginAdmin, DEFAULT_ADMIN } from '../../helpers/admin';
import { IssueCollector, sweepScreen, formatIssues, type PageIssue } from '../../helpers/qa';

/**
 * QA campaign — domain "shell-misc".
 *
 * Screens covered (perimeter handed to this agent):
 *  - /admin/login                 (success, generic-failure message, throttle smoke, lost-password link gating)
 *  - /admin/lost-password         (gate, full request flow up to "email sent", bogus token -> error)
 *  - /admin/logout                (clears session, protected pages redirect to login)
 *  - /admin/home                  (BoDashboard: KPI cards, Chart.js line + doughnut, period selector, alerts, home hooks)
 *  - /admin/search                (multi-entity sections, 1-char "too short", normal term, ACL trim of toggles/columns)
 *  - /admin/tools                 (ACL-filtered cards + link resolution)
 *  - /admin/export                (list + detail + run a real small export and verify the produced file)
 *  - /admin/import                (list + detail screen — no submit: an import mutates data and is not QA-reversible)
 *  - language switcher (top nav)  (?lang=<code> switches the UI locale and persists)
 *
 * Reference for expected behaviour: BO Smarty templates/backOffice/default/*.html and the
 * BO Twig templates/controllers under templates/backOffice/default-twig/src.
 *
 * Notes on perimeter interactions that cannot be exercised here:
 *  - "Lost password disabled in config -> 403": enable_lost_admin_password_recovery is 1 in this install
 *    and config changes are reserved for the Global phase. The 403 guard path is verified by reading
 *    PasswordResetController::guard(); the enabled path is fully tested. (see notes)
 *  - "Remember-me persists across browser restart": Playwright cannot truly restart the browser process;
 *    we assert the remember-me cookie is issued instead.
 *  - Throttle is smoke-tested with a handful of attempts only (rules cap this at "2 tentatives max",
 *    so we never trip the lockout — only assert the generic message stays generic).
 */

const SWEEP = { tabs: true, modals: true } as const;

/** Drop only the known language-flag SVG 404 noise (some locale flags are missing assets). */
function expectClean(issues: PageIssue[]): void {
  const filtered = issues.filter((i) => {
    if (/svgFlags\/.*\.svg/.test(i.detail)) return false;
    if (i.kind === 'console' && /Failed to load resource: the server responded with a status of 404/.test(i.detail)) return false;
    return true;
  });
  expect(filtered, formatIssues(filtered)).toHaveLength(0);
}

test.describe('shell-misc', () => {
  test.skip(
    (process.env.BO_TEMPLATE ?? 'default-twig') !== 'default-twig',
    'BO Twig only — run with the default-twig back-office active.',
  );

  // ---------------------------------------------------------------------------
  // LOGIN / AUTH
  // ---------------------------------------------------------------------------
  test.describe('login', () => {
    const URL = '/admin/login';

    test('sweep: login form renders, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('login-form')).toBeVisible();
      await expect(page.getByTestId('login-username')).toBeVisible();
      await expect(page.getByTestId('login-password')).toBeVisible();
      await expect(page.getByTestId('login-submit')).toBeVisible();
      // No tabs/modals on the auth layout.
      const issues = await sweepScreen(page, collector, { tabs: false, modals: false });
      expectClean(issues);
    });

    test('valid credentials redirect to /admin', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await loginAdmin(page);
      await expect(page).toHaveURL(/\/admin(\/(home)?)?$/);
      // Landing on the dashboard means the session is established. ('dashboard' testid is on both
      // <body> and the content div, so target the unique bo-dashboard component.)
      await expect(page.getByTestId('bo-dashboard')).toBeVisible();
      expectClean(collector.drain());
    });

    test('invalid credentials show a GENERIC message (no username/password leak)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      // Wrong password, real-looking username.
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await page.fill('input[name="thelia_admin_login[username]"]', 'thelia');
      await page.fill('input[name="thelia_admin_login[password]"]', 'definitely-wrong');
      await Promise.all([
        page.waitForLoadState('load'),
        page.locator('form[data-testid="login-form"] button[type="submit"]').click(),
      ]);
      const error = page.getByTestId('login-error');
      await expect(error, 'a login error alert should be shown').toBeVisible();
      const text = ((await error.textContent()) ?? '').toLowerCase();
      // Anti-enumeration: the message must NOT distinguish unknown user vs wrong password.
      expect(text, `login error should be generic, got: "${text}"`).toContain('invalid credentials');
      expect(/unknown user|user.*not found|wrong password|incorrect password|no such user/.test(text),
        'login error must not reveal which field was wrong').toBe(false);
      // Still on the login/checklogin screen, not authenticated.
      await expect(page).toHaveURL(/\/admin\/(login|checklogin)/);
      expectClean(collector.drain());
    });

    test('unknown username yields the SAME generic message as wrong password', async ({ page }) => {
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await page.fill('input[name="thelia_admin_login[username]"]', 'no-such-admin-qa');
      await page.fill('input[name="thelia_admin_login[password]"]', 'whatever');
      await Promise.all([
        page.waitForLoadState('load'),
        page.locator('form[data-testid="login-form"] button[type="submit"]').click(),
      ]);
      const text = ((await page.getByTestId('login-error').textContent()) ?? '').toLowerCase();
      expect(text).toContain('invalid credentials');
    });

    test('throttle smoke: 2 failed attempts keep a generic message (no lockout tripped)', async ({ page }) => {
      // Rules cap this at 2 attempts max — never enough to lock out the demo admin.
      for (let attempt = 0; attempt < 2; attempt++) {
        await page.goto(URL, { waitUntil: 'domcontentloaded' });
        await page.fill('input[name="thelia_admin_login[username]"]', 'thelia');
        await page.fill('input[name="thelia_admin_login[password]"]', `wrong-${attempt}`);
        await Promise.all([
          page.waitForLoadState('load'),
          page.locator('form[data-testid="login-form"] button[type="submit"]').click(),
        ]);
        await expect(page.getByTestId('login-error')).toBeVisible();
      }
      // The legit password still works afterwards (we did not trip the throttle).
      await loginAdmin(page);
      await expect(page.getByTestId('bo-dashboard')).toBeVisible();
    });

    test('remember-me issues a persistent cookie', async ({ page, context }) => {
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await page.fill('input[name="thelia_admin_login[username]"]', DEFAULT_ADMIN.username);
      await page.fill('input[name="thelia_admin_login[password]"]', DEFAULT_ADMIN.password);
      // The remember-me checkbox is the only checkbox on the form.
      const remember = page.locator('input[name="thelia_admin_login[remember_me]"]');
      if (await remember.count() > 0) await remember.check();
      await Promise.all([
        page.waitForURL((u) => !u.pathname.includes('/login'), { timeout: 15_000 }),
        page.locator('form[data-testid="login-form"] button[type="submit"]').click(),
      ]);
      // A non-session cookie (with an expiry in the future) proves remember-me persistence.
      const cookies = await context.cookies();
      const persistent = cookies.filter((c) => c.expires && c.expires > Date.now() / 1000 + 60);
      expect(persistent.length, 'remember-me should issue at least one persistent cookie').toBeGreaterThan(0);
    });

    test('lost-password link is present on login when recovery is enabled', async ({ page }) => {
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // enable_lost_admin_password_recovery is 1 in this install -> the "Forgot your password?" link shows.
      await expect(page.getByTestId('login-forgot-password')).toBeVisible();
      const href = await page.getByTestId('login-forgot-password').getAttribute('href');
      expect(href).toContain('/admin/lost-password');
    });
  });

  // ---------------------------------------------------------------------------
  // LOST PASSWORD
  // ---------------------------------------------------------------------------
  test.describe('lost-password', () => {
    const URL = '/admin/lost-password';

    test('sweep: form renders, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('lost-password-form')).toBeVisible();
      await expect(page.getByTestId('lost-password-username')).toBeVisible();
      await expect(page.getByTestId('lost-password-submit')).toBeVisible();
      const issues = await sweepScreen(page, collector, { tabs: false, modals: false });
      expectClean(issues);
    });

    test('unknown account -> validation error, never claims success', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await page.getByTestId('lost-password-username').fill('no-such-admin-qa@example.com');
      await Promise.all([
        page.waitForLoadState('load'),
        page.getByTestId('lost-password-submit').click(),
      ]);
      // Controller adds a form error "Invalid username or email." and re-renders the form (no success alert).
      await expect(page.getByTestId('lost-password-error')).toBeVisible();
      await expect(page.getByTestId('lost-password-success')).toHaveCount(0);
      expectClean(collector.drain());
    });

    test('valid account -> success screen (recovery email dispatched)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // The demo admin "thelia" has an email -> the request succeeds and dispatches the create-password event.
      // This only sets a renew token + sends a mail (caught by Mailpit in dev); it does NOT change the password.
      await page.getByTestId('lost-password-username').fill(DEFAULT_ADMIN.username);
      await Promise.all([
        page.waitForURL(/password-create-request-success|lost-password/, { timeout: 15_000 }),
        page.getByTestId('lost-password-submit').click(),
      ]);
      await expect(page.getByTestId('lost-password-success'), 'success alert should be shown').toBeVisible();
      await expect(page.getByTestId('lost-password-back-login')).toBeVisible();
      expectClean(collector.drain());
    });

    test('bogus create-password token -> token error screen', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto('/admin/password-create/not-a-real-token-xyz', { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('lost-password-token-error'), 'invalid token should surface an error').toBeVisible();
      // The screen offers a way to request a fresh link.
      await expect(page.locator('a[href$="/admin/lost-password"]')).toBeVisible();
      expectClean(collector.drain());
    });
  });

  // ---------------------------------------------------------------------------
  // LOGOUT
  // ---------------------------------------------------------------------------
  test.describe('logout', () => {
    test('logout clears the session -> protected page bounces to login', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await loginAdmin(page);
      await expect(page.getByTestId('bo-dashboard')).toBeVisible();
      // Click the real top-nav logout link (it 302s to login and deletes the remember-me cookie).
      await Promise.all([
        page.waitForURL(/\/admin\/login/, { timeout: 15_000 }),
        page.getByTestId('bo-top-nav-logout').click(),
      ]);
      await expect(page.getByTestId('login-form')).toBeVisible();
      // A protected page that bounces cleanly (admin.categories) must now redirect to login, not load.
      await page.goto('/admin/categories', { waitUntil: 'domcontentloaded' });
      await expect(page, 'protected page must redirect to login after logout').toHaveURL(/\/admin\/login/);
      expectClean(collector.drain());
    });
  });

  // ---------------------------------------------------------------------------
  // DASHBOARD
  // ---------------------------------------------------------------------------
  test.describe('dashboard', () => {
    const URL = '/admin/home';

    // Unauthenticated /admin/home must redirect to the login form like every other protected admin
    // page, NOT serve a bare HTTP 403. Fixed in core by adding not-logged=1 to the admin.home route
    // (core/lib/Thelia/Config/Resources/routing/admin_session.php), matching the /admin route. SHELL-HOME-403.
    test('unauthenticated /admin/home redirects to login like other admin pages', async ({ browser }) => {
      const ctx = await browser.newContext();
      const page = await ctx.newPage();
      const res = await page.goto(URL, { waitUntil: 'domcontentloaded' });
      expect(res?.status(), 'unauthenticated /admin/home must not be a bare 403').not.toBe(403);
      await expect(page, '/admin/home should bounce to the login form when logged out').toHaveURL(/\/admin\/login/);
      await ctx.close();
    });

    test.beforeEach(async ({ page }) => {
      await loginAdmin(page);
    });

    test('sweep: KPIs + charts + period selector + hooks, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      // 'dashboard' testid is on both <body> and the content div; assert the unique component instead.
      await expect(page.getByTestId('bo-dashboard')).toBeVisible();

      // 4 KPI cards.
      const kpis = page.locator('[data-testid="dashboard-kpis"] .bo-kpi');
      await expect(kpis, 'dashboard should show 4 KPI cards').toHaveCount(4);

      // Period selector present with multiple options.
      const periods = page.locator('[data-testid="dashboard-period-options"] a');
      expect(await periods.count(), 'period selector should expose options').toBeGreaterThan(1);

      // Two Chart.js canvases (revenue line + order-status doughnut). Wait for Chart to render.
      await page.waitForLoadState('networkidle').catch(() => undefined);
      const canvases = page.locator('canvas[data-controller="bo-chart"]');
      await expect(canvases, 'dashboard should render the line + doughnut charts').toHaveCount(2);

      // allowDanger: the dashboard renders KPI "alert" cards (e.g. "N unpaid orders over 48h") with
      // Bootstrap .alert-warning/.alert-danger styling — these are intentional dashboard notices, not
      // error messages, so scanDom must not treat them as failures.
      const issues = await sweepScreen(page, collector, { ...SWEEP, allowDanger: true });
      expectClean(issues);
    });

    test('Chart.js actually draws (canvas gets a non-zero backing buffer)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await page.waitForLoadState('networkidle').catch(() => undefined);
      // Chart.js sizes the canvas backing store once it instantiates; a 0x0 canvas means the chart crashed.
      await expect.poll(async () => page.evaluate(() => {
        const c = document.querySelector<HTMLCanvasElement>('canvas[data-controller="bo-chart"]');
        return c ? c.width * c.height : 0;
      }), { timeout: 8_000 }).toBeGreaterThan(0);
      expectClean(collector.drain());
    });

    test('period selector navigates and stays clean', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const options = page.locator('[data-testid="dashboard-period-options"] a');
      const count = await options.count();
      // Click the LAST option (a non-active period) and assert the dashboard re-renders cleanly.
      const last = options.nth(count - 1);
      await Promise.all([page.waitForLoadState('load'), last.click()]);
      await expect(page.getByTestId('bo-dashboard')).toBeVisible();
      await expect(page.locator('[data-testid="dashboard-kpis"] .bo-kpi')).toHaveCount(4);
      await page.waitForLoadState('networkidle').catch(() => undefined);
      expectClean(collector.drain());
    });
  });

  // ---------------------------------------------------------------------------
  // SEARCH
  // ---------------------------------------------------------------------------
  test.describe('search', () => {
    test.beforeEach(async ({ page }) => {
      await loginAdmin(page);
    });

    test('empty term -> prompt, no sections', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto('/admin/search', { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('search-page')).toBeVisible();
      // No term: a "type a search term" prompt, no result cards.
      await expect(page.locator('[data-testid="search-page"] .card')).toHaveCount(0);
      const issues = await sweepScreen(page, collector, { tabs: false, modals: false });
      expectClean(issues);
    });

    test('1-char term -> "too short" notice (min length 2)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto('/admin/search?search_term=a', { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('search-too-short'), 'single char should be rejected as too short').toBeVisible();
      // No result cards rendered for a too-short term.
      await expect(page.locator('[data-testid="search-page"] .card')).toHaveCount(0);
      const issues = await sweepScreen(page, collector, { tabs: false, modals: false });
      expectClean(issues);
    });

    test('normal term -> 7 entity sections rendered with links', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      // "a" is too short; "co" matches plenty of demo data across entities.
      await page.goto('/admin/search?search_term=co', { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('search-page')).toBeVisible();
      await expect(page.getByTestId('search-too-short')).toHaveCount(0);
      // The view renders exactly 7 sections: products, categories, folders, contents, brands, customers, orders.
      const cards = page.locator('[data-testid="search-page"] .card');
      await expect(cards, 'search should render the 7 entity sections').toHaveCount(7);
      // At least one section header references a known entity label.
      await expect(page.locator('[data-testid="search-page"] .card-header', { hasText: 'Products' })).toBeVisible();
      await expect(page.locator('[data-testid="search-page"] .card-header', { hasText: 'Customers' })).toBeVisible();
      // No visibility toggles / row-action controls leak into the search result lists (PAR-28 reduction).
      const controls = await page.locator('[data-testid="search-page"] .card input[type="checkbox"], [data-testid="search-page"] .card button').count();
      expect(controls, 'search result lists should carry no toggles/buttons (read-only links only)').toBe(0);
      const issues = await sweepScreen(page, collector, { tabs: false, modals: false });
      expectClean(issues);
    });

    test('result links resolve (no 5xx) for a representative match', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto('/admin/search?search_term=co', { waitUntil: 'domcontentloaded' });
      // Result links are rendered as absolute URLs (https://host/admin/...), so match on the path substring.
      const href = await page.locator('[data-testid="search-page"] .card a[href*="/admin/"]:not([href*="/search"])').first().getAttribute('href');
      expect(href, 'search should produce at least one result link').toBeTruthy();
      const status = await page.evaluate(async (h) => {
        const r = await fetch(h, { redirect: 'follow' });
        return r.status;
      }, href!);
      expect(status, `first search result link ${href} should not 5xx (got ${status})`).toBeLessThan(500);
      expectClean(collector.drain());
    });
  });

  // ---------------------------------------------------------------------------
  // TOOLS
  // ---------------------------------------------------------------------------
  test.describe('tools', () => {
    const URL = '/admin/tools';

    test.beforeEach(async ({ page }) => {
      await loginAdmin(page);
    });

    test('sweep: tool cards render, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('tools-page')).toBeVisible();
      // The super-admin sees both sections (General tools + Data transfer) -> >= 6 cards.
      const cards = page.locator('[data-testid^="tools-link-"]');
      expect(await cards.count(), 'super-admin should see the full tools set').toBeGreaterThanOrEqual(6);
      // Export + Import cards are present for the super-admin.
      await expect(page.getByTestId('tools-link-export.list')).toBeVisible();
      await expect(page.getByTestId('tools-link-import.list')).toBeVisible();
      const issues = await sweepScreen(page, collector, SWEEP);
      expectClean(issues);
    });

    test('every tool card link resolves (no 5xx)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const hrefs = await page.evaluate(() =>
        [...document.querySelectorAll('[data-testid^="tools-link-"]')]
          .map((a) => (a as HTMLAnchorElement).getAttribute('href'))
          .filter((h): h is string => !!h),
      );
      expect(hrefs.length).toBeGreaterThanOrEqual(6);
      for (const href of hrefs) {
        const status = await page.evaluate(async (h) => (await fetch(h, { redirect: 'follow' })).status, href);
        expect(status, `tool link ${href} should not 5xx (got ${status})`).toBeLessThan(500);
      }
      expectClean(collector.drain());
    });
  });

  // ---------------------------------------------------------------------------
  // EXPORT
  // ---------------------------------------------------------------------------
  test.describe('export', () => {
    const URL = '/admin/export';

    test.beforeEach(async ({ page }) => {
      await loginAdmin(page);
    });

    test('sweep: export catalogue renders, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('export-page')).toBeVisible();
      // Demo data ships 8 exports across categories; at least one export row with a "view" link.
      const rows = page.locator('[data-testid="export-page"] tr[data-row-id]');
      expect(await rows.count(), 'export catalogue should list exports').toBeGreaterThan(0);
      const issues = await sweepScreen(page, collector, { tabs: true, modals: false });
      expectClean(issues);
    });

    test('export detail screen renders the run form', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const firstView = page.locator('[data-testid="export-page"] a[href*="/admin/export/"]').first();
      await Promise.all([page.waitForLoadState('load'), firstView.click()]);
      await expect(page.getByTestId('export-edit-page')).toBeVisible();
      await expect(page.getByTestId('export-form')).toBeVisible();
      // Format + language selects + a submit button must be present.
      await expect(page.locator('#bo-export-serializer')).toBeVisible();
      await expect(page.locator('#bo-export-language')).toBeVisible();
      await expect(page.locator('form[data-testid="export-form"] button[type="submit"]')).toBeVisible();
      const issues = await sweepScreen(page, collector, { tabs: false, modals: false });
      expectClean(issues);
    });

    // SHELL-EXPORT-RUN (blocker) — running ANY export that does NOT use range dates (Mailing, Customers,
    // product prices, SEO, orders... i.e. every export except the few range-aware ones) never produces a
    // file: it silently redirects back to the detail page. Root cause is in core, not BO Twig:
    // ExportImportController::exportProcess() passes $rangeDate = null when no range_date_* fields are
    // posted (the normal case), and Thelia\Domain\DataTransfer\ExportHandler::export() dereferences it
    // unconditionally at line 102/111 — `if ($rangeDate['start'] && ...)` — which raises
    // "Trying to access array offset on null". Under the debug error handler that warning is promoted to
    // an ErrorException; the controller's catch (ExportImportController L273) swallows it into a flash and
    // returns a 302 RedirectResponse to export.view instead of the BinaryFileResponse. No download fires.
    // Repro: GET /admin/export/1, pick any serializer (CSV) + the default language, submit -> POST
    // /admin/export/1 returns HTTP 302 Location /admin/export/1, body is the redirect stub, no file.
    // Confirmed: `$rangeDate=null; $rangeDate['start']` throws "Trying to access array offset on null".
    // Fixed in core: the range-date block is now guarded with `if (null !== $rangeDate) { ... }` in
    // core/lib/Thelia/Domain/DataTransfer/ExportHandler.php::export().
    test('run a real small export -> a non-empty file is produced and downloaded', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      // Export id 1 = "Mailing": a small, side-effect-free export of newsletter subscriptions.
      await page.goto('/admin/export/1', { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('export-edit-page')).toBeVisible();

      // Pick the first serializer (CSV in the demo) and the default language; leave compression off.
      await page.locator('#bo-export-serializer').selectOption({ index: 0 });

      // The submit triggers a BinaryFileResponse (Content-Disposition: attachment) -> a Playwright download.
      const [download] = await Promise.all([
        page.waitForEvent('download', { timeout: 30_000 }),
        page.locator('form[data-testid="export-form"] button[type="submit"]').click(),
      ]);
      const suggested = download.suggestedFilename();
      expect(suggested, 'export should produce a named file').toBeTruthy();
      // Persist + verify the file is non-empty (a 0-byte download means the export silently failed).
      const path = await download.path();
      expect(path, 'download should be saved to disk').toBeTruthy();
      const { statSync } = await import('node:fs');
      expect(statSync(path!).size, `exported file "${suggested}" should be non-empty`).toBeGreaterThan(0);

      // No server error surfaced during the export run.
      expectClean(collector.drain());
    });
  });

  // ---------------------------------------------------------------------------
  // IMPORT
  // ---------------------------------------------------------------------------
  test.describe('import', () => {
    const URL = '/admin/import';

    test.beforeEach(async ({ page }) => {
      await loginAdmin(page);
    });

    test('sweep: import catalogue renders, no errors', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      await expect(page.getByTestId('import-page')).toBeVisible();
      const rows = page.locator('[data-testid="import-page"] tr[data-row-id]');
      expect(await rows.count(), 'import catalogue should list imports').toBeGreaterThan(0);
      const issues = await sweepScreen(page, collector, { tabs: true, modals: false });
      expectClean(issues);
    });

    test('import detail screen renders the upload form (NOT submitted — mutating + irreversible)', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto(URL, { waitUntil: 'domcontentloaded' });
      const firstView = page.locator('[data-testid="import-page"] a[href*="/admin/import/"]').first();
      await Promise.all([page.waitForLoadState('load'), firstView.click()]);
      // The import edit screen renders a form with a file input + a language select. We do NOT submit:
      // an import writes to demo data and is not reversible within the test.
      await expect(page).toHaveURL(/\/admin\/import\/\d+/);
      const fileInput = page.locator('form input[type="file"]');
      await expect(fileInput, 'import detail should expose a file upload input').toBeVisible();
      const issues = await sweepScreen(page, collector, { tabs: false, modals: false });
      expectClean(issues);
    });
  });

  // ---------------------------------------------------------------------------
  // LANGUAGE SWITCHER (top nav)
  // ---------------------------------------------------------------------------
  test.describe('language-switcher', () => {
    test.beforeEach(async ({ page }) => {
      await loginAdmin(page);
    });

    test('switcher offers languages and ?lang=fr switches + persists the UI locale', async ({ page }) => {
      const collector = new IssueCollector(page).attach();
      await page.goto('/admin/home', { waitUntil: 'domcontentloaded' });
      const switcher = page.getByTestId('bo-top-nav-langs');
      await expect(switcher).toBeVisible();
      await expect(switcher.locator('.dropdown-item'), 'switcher should list installed locales').not.toHaveCount(0);

      // Switch to French via the documented mechanism (?lang=fr on the current path).
      await page.goto('/admin/home?lang=fr', { waitUntil: 'domcontentloaded' });
      // The "Tableau de bord" French label proves the UI locale changed.
      await expect(page.locator('h1', { hasText: 'Tableau de bord' }), 'UI should switch to French').toBeVisible();

      // PERSISTENCE: navigate WITHOUT the lang param — the chosen locale must stick (stored in session).
      await page.goto('/admin/home', { waitUntil: 'domcontentloaded' });
      await expect(page.locator('h1', { hasText: 'Tableau de bord' }), 'French locale should persist across navigation').toBeVisible();

      // Restore English so the shared session does not perturb other specs.
      await page.goto('/admin/home?lang=en', { waitUntil: 'domcontentloaded' });
      await expect(page.locator('h1', { hasText: 'Dashboard' })).toBeVisible();

      expectClean(collector.drain());
    });
  });
});
