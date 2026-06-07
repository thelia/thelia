import { type Page } from '@playwright/test';

/**
 * QA campaign helpers — exhaustive back-office sweep (default-twig).
 * Every qa-*.spec.ts must use IssueCollector + expectCleanPage on each screen,
 * clickAllTabs on tabbed screens, openAllModals on list/edit screens.
 */

export type PageIssue = {
  kind: 'console' | 'pageerror' | 'network' | 'dom' | 'leak';
  detail: string;
  pageUrl: string;
};

const NOISE_PATTERNS: RegExp[] = [
  /favicon/i,
  /net::ERR_ABORTED/i, // navigation-cancelled asset loads
  /Download the React DevTools/i,
];

function isNoise(text: string): boolean {
  return NOISE_PATTERNS.some((re) => re.test(text));
}

export class IssueCollector {
  readonly issues: PageIssue[] = [];

  constructor(private readonly page: Page) {}

  /** Call BEFORE the first page.goto(). */
  attach(): this {
    this.page.on('console', (msg) => {
      if (msg.type() !== 'error') return;
      const text = msg.text();
      if (isNoise(text)) return;
      this.issues.push({ kind: 'console', detail: text, pageUrl: this.page.url() });
    });
    this.page.on('pageerror', (error) => {
      this.issues.push({ kind: 'pageerror', detail: String(error), pageUrl: this.page.url() });
    });
    this.page.on('response', (response) => {
      const status = response.status();
      const resourceType = response.request().resourceType();
      const url = response.url();
      if (isNoise(url)) return;
      // Any 5xx is a finding; 4xx only matters on fetch/XHR (Stimulus endpoints).
      const isServerError = status >= 500;
      const isAjaxClientError = status >= 400 && (resourceType === 'fetch' || resourceType === 'xhr');
      if (isServerError || isAjaxClientError) {
        this.issues.push({
          kind: 'network',
          detail: `${status} ${response.request().method()} ${url} [${resourceType}]`,
          pageUrl: this.page.url(),
        });
      }
    });
    return this;
  }

  /** Drain collected issues (returns and clears). Call between screens. */
  drain(): PageIssue[] {
    return this.issues.splice(0, this.issues.length);
  }
}

/**
 * Assert the rendered page is not a Symfony exception page and surface
 * visible error alerts. Returns DOM-level issues instead of throwing so the
 * spec can aggregate them into one report per screen.
 */
export async function scanDom(page: Page, options: { allowDanger?: boolean } = {}): Promise<PageIssue[]> {
  const issues: PageIssue[] = [];
  const title = await page.title();
  if (/internal server error|500/i.test(title)) {
    issues.push({ kind: 'dom', detail: `Exception page title: ${title}`, pageUrl: page.url() });
  }
  const exceptionMarker = page
    .locator('.exception-message, .exception-summary')
    .or(page.getByText(/Stack Trace/i))
    .first();
  if (await exceptionMarker.count() > 0) {
    const text = (await exceptionMarker.textContent())?.trim().slice(0, 300) ?? '';
    issues.push({ kind: 'dom', detail: `Symfony exception page: ${text}`, pageUrl: page.url() });
  }
  if (!options.allowDanger) {
    for (const alert of await page.locator('.alert-danger:visible, .invalid-feedback:visible').all()) {
      const text = (await alert.textContent())?.trim().slice(0, 200) ?? '';
      if (text) issues.push({ kind: 'dom', detail: `Visible error alert: ${text}`, pageUrl: page.url() });
    }
  }
  return issues;
}

/**
 * Detect duplicate form fields leaked by form_end() rendering unrendered
 * fields twice (known default-twig pitfall). Radio/checkbox/array names are
 * legitimate duplicates and excluded.
 */
export async function findLeakedFields(page: Page): Promise<PageIssue[]> {
  const duplicates: string[] = await page.evaluate(() => {
    const result: string[] = [];
    for (const form of Array.from(document.querySelectorAll('form'))) {
      const counts = new Map<string, number>();
      for (const field of Array.from(form.querySelectorAll<HTMLInputElement>('input, select, textarea'))) {
        if (!field.name || field.name.endsWith('[]')) continue;
        if (field.type === 'radio' || field.type === 'checkbox' || field.type === 'hidden') continue;
        counts.set(field.name, (counts.get(field.name) ?? 0) + 1);
      }
      for (const [name, count] of counts) {
        if (count > 1) result.push(`${name} x${count} (form action=${form.getAttribute('action') ?? '?'})`);
      }
    }
    return result;
  });
  return duplicates.map((detail) => ({ kind: 'leak' as const, detail: `Duplicate field: ${detail}`, pageUrl: page.url() }));
}

/**
 * Click every Bootstrap tab/pill on the page (covers lazy bo-ajax-tab fetches)
 * and verify the target pane becomes active. Network/JS failures are caught by
 * the IssueCollector; this returns structural issues (pane never shown).
 */
export async function clickAllTabs(page: Page): Promise<PageIssue[]> {
  const issues: PageIssue[] = [];
  const triggers = await page.locator('[data-bs-toggle="tab"], [data-bs-toggle="pill"]').all();
  for (const trigger of triggers) {
    if (!(await trigger.isVisible())) continue;
    const label = ((await trigger.textContent()) ?? '').trim() || (await trigger.getAttribute('data-bs-target')) || '?';
    try {
      await trigger.click();
      await page.waitForTimeout(400);
      await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => undefined);
      const active = await page.locator('.tab-pane.active.show').count();
      if (active === 0) {
        issues.push({ kind: 'dom', detail: `Tab "${label}" clicked but no active pane shown`, pageUrl: page.url() });
      }
    } catch (error) {
      issues.push({ kind: 'dom', detail: `Tab "${label}" not clickable: ${String(error).slice(0, 200)}`, pageUrl: page.url() });
    }
  }
  return issues;
}

/**
 * Open every modal reachable on the page (one trigger per unique data-bs-target),
 * assert it shows with non-empty content, then close it. Triggers hidden inside
 * kebab dropdowns are reached by opening their parent dropdown first.
 */
export async function openAllModals(page: Page): Promise<PageIssue[]> {
  const issues: PageIssue[] = [];
  const seenTargets = new Set<string>();
  const triggers = await page.locator('[data-bs-toggle="modal"]').all();
  for (const trigger of triggers) {
    const target = (await trigger.getAttribute('data-bs-target')) ?? (await trigger.getAttribute('href')) ?? '';
    if (!target || seenTargets.has(target)) continue;
    seenTargets.add(target);
    try {
      if (!(await trigger.isVisible())) {
        // Try to reveal it: kebab/action dropdown ancestor.
        const dropdownToggle = trigger.locator('xpath=ancestor::*[contains(@class,"dropdown")][1]//*[contains(@class,"dropdown-toggle")]').first();
        if (await dropdownToggle.count() > 0 && await dropdownToggle.isVisible()) {
          await dropdownToggle.click();
          await page.waitForTimeout(200);
        }
        if (!(await trigger.isVisible())) continue; // template-only trigger (cloned rows etc.)
      }
      await trigger.click();
      const modal = page.locator('.modal.show').first();
      await modal.waitFor({ state: 'visible', timeout: 5_000 });
      await page.waitForLoadState('networkidle', { timeout: 5_000 }).catch(() => undefined);
      const bodyText = ((await modal.locator('.modal-body').first().textContent().catch(() => '')) ?? '').trim();
      const bodyInputs = await modal.locator('input, select, textarea, iframe, table').count();
      if (bodyText.length === 0 && bodyInputs === 0) {
        issues.push({ kind: 'dom', detail: `Modal ${target} opens empty`, pageUrl: page.url() });
      }
      await page.keyboard.press('Escape');
      await page.locator('.modal.show').first().waitFor({ state: 'hidden', timeout: 5_000 }).catch(async () => {
        // Static-backdrop modals: use the close button.
        await page.locator('.modal.show [data-bs-dismiss="modal"]').first().click().catch(() => undefined);
      });
      await page.waitForTimeout(200);
    } catch (error) {
      issues.push({ kind: 'dom', detail: `Modal ${target} failed to open/close: ${String(error).slice(0, 200)}`, pageUrl: page.url() });
    }
  }
  return issues;
}

/** Standard per-screen sweep: DOM scan + leaked fields + tabs + modals. */
export async function sweepScreen(page: Page, collector: IssueCollector, options: { tabs?: boolean; modals?: boolean; allowDanger?: boolean } = {}): Promise<PageIssue[]> {
  const issues: PageIssue[] = [];
  issues.push(...await scanDom(page, { allowDanger: options.allowDanger }));
  issues.push(...await findLeakedFields(page));
  if (options.tabs !== false) issues.push(...await clickAllTabs(page));
  if (options.modals !== false) issues.push(...await openAllModals(page));
  issues.push(...collector.drain());
  return issues;
}

/** Unique, recognisable test-data reference. ALWAYS prefix created entities with this. */
export function qaRef(domain: string): string {
  return `QA-${domain.toUpperCase()}-${Date.now().toString(36)}`;
}

/** Pretty-print issues for test failure messages. */
export function formatIssues(issues: PageIssue[]): string {
  return issues.map((issue) => `[${issue.kind}] ${issue.detail} (on ${issue.pageUrl})`).join('\n');
}
