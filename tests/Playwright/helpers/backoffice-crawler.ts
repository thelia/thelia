import { type APIRequestContext, type Page } from '@playwright/test';

/**
 * Routes whose mere visit (GET) or re-submission (POST) is destructive
 * or has side effects unrelated to a 500 hunt. Skipped both as crawl
 * targets and as form actions.
 */
const DANGER_PATTERN = /(delete|toggle|logout|disable|deactivate|change-position|update-position|password\/reset|empty|clear|destroy|export\/[^?]+\?|remove)/i;

const ASSET_PATTERN = /\.(jpg|jpeg|png|gif|webp|svg|css|js|ico|woff2?|ttf|eot|map)$/i;

export type CrawlOptions = {
  baseURL: string;
  startPaths?: string[];
  maxPages?: number;
  /** Skip URLs matching this regex (in addition to the defaults). */
  excludePattern?: RegExp;
};

export type CrawlVisit = { path: string; status: number };

export type CrawlResult = {
  visited: CrawlVisit[];
  errors: CrawlError[];
};

export type CrawlError = {
  status: number;
  method: 'GET' | 'POST';
  path: string;
  source?: string;
  excerpt: string;
};

export type FormCrawlOptions = {
  baseURL: string;
  /** Pages to attempt re-submission on (typically the GET visit list). */
  pages: CrawlVisit[];
  request: APIRequestContext;
  maxForms?: number;
  excludeActionPattern?: RegExp;
};

export type FormCrawlResult = {
  submitted: number;
  errors: CrawlError[];
};

/**
 * Best-effort identifier of the relevant text inside a backoffice 500 page.
 * Tries the production-mode error message, falls back to the dev exception preview.
 */
function summariseHtml(body: string): string {
  const m1 = body.match(/<p[^>]*>([^<]{20,300})<\/p>/);
  if (m1) return m1[1].trim();
  const m2 = body.match(/(TypeError|Error|Exception|Warning)[^<]{0,300}/);
  if (m2) return m2[0].replace(/\s+/g, ' ').trim();
  const m3 = body.match(/<title[^>]*>([^<]+)<\/title>/);
  if (m3) return m3[1].trim();
  return body.slice(0, 200);
}

function normalisePath(href: string, baseURL: string): string | null {
  try {
    const u = new URL(href, baseURL);
    if (u.origin !== new URL(baseURL).origin) return null;
    if (!u.pathname.startsWith('/admin')) return null;
    if (DANGER_PATTERN.test(u.pathname)) return null;
    if (ASSET_PATTERN.test(u.pathname)) return null;
    if (u.pathname.includes('/ajax/')) return null;
    u.hash = '';
    return u.pathname + u.search;
  } catch {
    return null;
  }
}

/**
 * Phase 1 — breadth-first GET crawl from the given seeds, following <a href> links.
 * Records every response and surfaces 5xx statuses.
 *
 * The page is expected to be already authenticated.
 */
export async function crawlBackofficeGet(page: Page, options: CrawlOptions): Promise<CrawlResult> {
  const { baseURL } = options;
  const seeds = options.startPaths && options.startPaths.length ? options.startPaths : ['/admin'];
  const maxPages = options.maxPages ?? 500;
  const customExclude = options.excludePattern;

  const seen = new Set<string>();
  const queue: string[] = [];
  const visited: CrawlVisit[] = [];
  const errors: CrawlError[] = [];

  const onResponse = async (resp: import('@playwright/test').Response) => {
    if (resp.status() < 500) return;
    let body = '';
    try { body = (await resp.text()).slice(0, 4000); } catch { /* ignore */ }
    errors.push({
      status: resp.status(),
      method: 'GET',
      path: resp.url().replace(baseURL, ''),
      excerpt: summariseHtml(body),
    });
  };
  page.on('response', onResponse);

  for (const seed of seeds) queue.push(seed);

  try {
    while (queue.length > 0 && visited.length < maxPages) {
      const path = queue.shift()!;
      if (seen.has(path)) continue;
      if (customExclude && customExclude.test(path)) continue;
      seen.add(path);

      let response;
      try {
        response = await page.goto(baseURL + path, { waitUntil: 'domcontentloaded', timeout: 30_000 });
      } catch {
        continue;
      }
      const status = response ? response.status() : 0;
      visited.push({ path, status });

      if (status >= 200 && status < 400) {
        const hrefs = await page.$$eval('a[href]', (as) => as.map((a) => a.getAttribute('href')).filter((v): v is string => !!v));
        for (const href of hrefs) {
          const next = normalisePath(href, baseURL);
          if (next && !seen.has(next)) queue.push(next);
        }
      }
    }
  } finally {
    page.off('response', onResponse);
  }

  return { visited, errors };
}

/**
 * Phase 2 — re-submit the first non-destructive POST form found on each page.
 * Uses APIRequestContext so cookies are shared with the authenticated browser context.
 *
 * The point is to exercise the *save* code paths the way an admin would, with
 * the form's own initial values, surfacing 500s caused by data-flow regressions
 * (typed setters, validators, listeners) without inserting fake data.
 */
export async function crawlBackofficeForms(page: Page, options: FormCrawlOptions): Promise<FormCrawlResult> {
  const { baseURL, pages, request } = options;
  const maxForms = options.maxForms ?? 200;
  const customExclude = options.excludeActionPattern;

  const errors: CrawlError[] = [];
  let submitted = 0;

  // Edit/update pages typically expose the relevant save form. Filter on URL
  // shape rather than a fixed list, to stay framework-friendly.
  const targets = pages.filter((v) => v.status === 200 && /\/(update|edit)\b|edit_language_id|update\?|view\b/.test(v.path));

  for (const target of targets) {
    if (submitted >= maxForms) break;

    try {
      await page.goto(baseURL + target.path, { waitUntil: 'domcontentloaded', timeout: 20_000 });
    } catch {
      continue;
    }

    const formInfo = await page.evaluate(() => {
      const forms = Array.from(document.querySelectorAll('form'));
      for (const form of forms) {
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'post') continue;
        const action = (form.getAttribute('action') || '').toLowerCase();
        if (/(delete|toggle|logout|disable|deactivate|export|empty|clear|destroy|password)/i.test(action)) continue;
        if (!form.querySelector('button[type="submit"], input[type="submit"]')) continue;
        return {
          action: form.action || location.href,
          fields: Array.from(form.elements as HTMLFormControlsCollection)
            .filter((el): el is HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement =>
              el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement
            )
            .filter((el) => el.name && !el.disabled)
            .map((el) => ({
              name: el.name,
              value: el.value ?? '',
              type: 'type' in el ? el.type : 'textarea',
              checked: 'checked' in el ? el.checked : false,
            })),
        };
      }
      return null;
    });

    if (!formInfo) continue;
    if (!formInfo.action.startsWith(baseURL)) continue;
    if (customExclude && customExclude.test(formInfo.action)) continue;

    const body: Record<string, string> = {};
    for (const f of formInfo.fields) {
      if (f.type === 'submit' || f.type === 'button' || f.type === 'reset' || f.type === 'file') continue;
      if ((f.type === 'checkbox' || f.type === 'radio') && !f.checked) continue;
      body[f.name] = f.value ?? '';
    }

    submitted++;
    let response;
    try {
      response = await request.post(formInfo.action, { form: body, maxRedirects: 0, timeout: 20_000 });
    } catch {
      continue;
    }
    if (response.status() >= 500) {
      let respBody = '';
      try { respBody = (await response.text()).slice(0, 4000); } catch { /* ignore */ }
      errors.push({
        status: response.status(),
        method: 'POST',
        path: formInfo.action.replace(baseURL, ''),
        source: target.path,
        excerpt: summariseHtml(respBody),
      });
    }
  }

  return { submitted, errors };
}
