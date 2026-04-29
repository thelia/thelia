import { expect, test } from '@playwright/test';
import { loginAdmin } from '../helpers/admin';
import { crawlBackofficeGet, crawlBackofficeForms } from '../helpers/backoffice-crawler';

/**
 * Backoffice smoke crawl.
 *
 * Two passes:
 *   1) Authenticated GET crawl (link-following) — catches regressions surfacing
 *      on page render, hooks, loops, listeners, …
 *   2) Form re-submission on edit pages — catches regressions in save paths
 *      (controllers, events, action listeners, model setters).
 *
 * Tunable through env vars (handy for CI fast-mode vs nightly deep crawl):
 *   BACKOFFICE_MAX_PAGES (default 300)
 *   BACKOFFICE_MAX_FORMS (default 100)
 *   BASE_URL             (default https://thelia-3.ddev.site)
 *
 * Both passes share the same authenticated context so session cookies match.
 */

const MAX_PAGES = Number(process.env.BACKOFFICE_MAX_PAGES ?? 300);
const MAX_FORMS = Number(process.env.BACKOFFICE_MAX_FORMS ?? 100);

test.describe('Backoffice smoke', () => {
  test.describe.configure({ mode: 'serial' });

  // The crawl can take a few minutes when MAX_PAGES is high; raise the timeout
  // so it doesn't fail spuriously in the middle of a run.
  test.setTimeout(20 * 60_000);

  test('no 5xx on backoffice navigation and form re-submission', async ({ page, context, baseURL }) => {
    expect(baseURL, 'BASE_URL must be set').toBeTruthy();

    await loginAdmin(page);

    const getResult = await crawlBackofficeGet(page, {
      baseURL: baseURL!,
      maxPages: MAX_PAGES,
    });

    if (getResult.errors.length > 0) {
      // Fail with a readable summary listing every failing URL once.
      const lines = getResult.errors.map((e) => `  ${e.status} ${e.method} ${e.path} — ${e.excerpt}`);
      throw new Error(`GET crawl: ${getResult.errors.length} server error(s):\n${lines.join('\n')}`);
    }

    const formResult = await crawlBackofficeForms(page, {
      baseURL: baseURL!,
      pages: getResult.visited,
      request: context.request,
      maxForms: MAX_FORMS,
    });

    if (formResult.errors.length > 0) {
      const lines = formResult.errors.map((e) => `  ${e.status} ${e.method} ${e.path} (from ${e.source}) — ${e.excerpt}`);
      throw new Error(`Form re-submission: ${formResult.errors.length} server error(s):\n${lines.join('\n')}`);
    }

    // Log scope so failures and CI artifacts give context without parsing logs.
    test.info().annotations.push(
      { type: 'crawl-stats', description: `GET ${getResult.visited.length} pages` },
      { type: 'crawl-stats', description: `POST ${formResult.submitted} forms` },
    );
  });
});
