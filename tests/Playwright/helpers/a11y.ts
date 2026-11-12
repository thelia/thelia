import { readFileSync } from 'node:fs';
import AxeBuilder from '@axe-core/playwright';
import { expect, type Locator, type Page, type TestInfo } from '@playwright/test';

// RGAA 4.1 rests on WCAG 2.1 level AA: these are the axe rule tags that cover it.
const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];

// What a visitor is never served: the Symfony debug toolbar, whose own defects would drown
// the theme's.
const EXCLUDED = ['.sf-toolbar', '[id^="sfwdt"]', '.sf-minitoolbar'];

const DOCUMENT_TARGET = /^(html|head|title|meta|link)\b/;

const BLOCKING_IMPACTS = ['serious', 'critical'];

// Documented derogations (RGAA "dérogation"): rule id → selector fragment it is allowed on.
// Each entry must be listed in the accessibility statement of the documentation.
const DEROGATIONS: Array<{ rule: string; target: RegExp }> = [];

const AUDIT_CONFIG = new URL('../../../setup/audit/lighthouserc.default.yaml', import.meta.url);

/** Paths of the storefront sample the shipped audit config describes. */
export function auditedPaths(): string[] {
  const config = readFileSync(AUDIT_CONFIG, 'utf8');
  const paths = [...config.matchAll(/^\s*-\s*"https?:\/\/__DOMAIN__([^"]*)"/gm)].map((match) => match[1] || '/');
  expect(paths.length, `no URL found in ${AUDIT_CONFIG.pathname}`).toBeGreaterThan(0);

  return paths;
}

/**
 * `networkidle` is not enough: Turbo keeps `aria-busy` on `<html>` until its visit is over, and a
 * live component stays faded (`data-loading="addClass(opacity-50)"`) until it has re-rendered.
 * axe would read the half-transparent text as a contrast failure and the busy state as a page defect.
 */
async function waitForSettledPage(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle').catch(() => {});
  await expect(page.locator('html[aria-busy="true"]'), `${page.url()} is still busy`).toHaveCount(0, { timeout: 15_000 });
  await expect(page.locator('main [data-loading].opacity-50'), `a live component of ${page.url()} is still loading`).toHaveCount(0, { timeout: 15_000 });
}

/** Opens a page of the audit sample, failing when it no longer exists or sends elsewhere. */
export async function gotoAuditedPath(page: Page, path: string): Promise<void> {
  const response = await page.goto(path);
  expect(response?.status(), `${path} is listed in setup/audit/lighthouserc.default.yaml`).toBeLessThan(400);
  // A redirect would audit another page under this one's name.
  expect(new URL(page.url()).pathname, `${path} redirected`).toBe(new URL(path, page.url()).pathname);
}

/** Moves the focus with Tab only until it reaches `target`, the way a keyboard user gets there. */
export async function tabTo(page: Page, target: Locator, what: string, maxTabStops = 200): Promise<void> {
  const element = target.first();
  await expect(element, `${what} is not on the page`).toBeVisible({ timeout: 15_000 });
  for (let stop = 0; stop < maxTabStops; stop++) {
    await page.keyboard.press('Tab');
    if (await element.evaluate((node) => node === document.activeElement || node.contains(document.activeElement))) return;
  }
  throw new Error(`${what} is not reachable with Tab within ${maxTabStops} stops on ${page.url()}`);
}

export type AuditSummary = {
  page: string;
  rulesChecked: number;
  rulesOnContent: number;
  rulesInapplicable: number;
  violations: number;
  blocking: string[];
};

/**
 * Runs axe on the current page and fails on any serious or critical violation that is not a
 * documented derogation. Prints how many rules really applied: an audit that checked nothing
 * must not read as a pass. `include` narrows the audit to the regions it names.
 */
export async function auditPage(page: Page, name: string, testInfo: TestInfo, exclude: string[] = [], include: string[] = []): Promise<AuditSummary> {
  await waitForSettledPage(page);

  let builder = new AxeBuilder({ page }).withTags(WCAG_TAGS);
  // Limits the audit to a region of the page (a component): the document-level rules do not run then.
  for (const selector of include) {
    builder = builder.include(selector);
  }
  for (const selector of [...EXCLUDED, ...exclude]) {
    builder = builder.exclude(selector);
  }
  const results = await builder.analyze();

  const rulesChecked = results.passes.length + results.violations.length + results.incomplete.length;
  // The document-level rules (lang, title, viewport) run on <html> and <head> whatever is
  // excluded: only a rule that reached an element of the body shows the content was audited.
  const rulesOnContent = [...results.passes, ...results.violations, ...results.incomplete]
    .filter((rule) => rule.nodes.some((node) => !DOCUMENT_TARGET.test(node.target.join(' ')))).length;
  const blocking = results.violations
    .filter((violation) => BLOCKING_IMPACTS.includes(violation.impact ?? ''))
    .flatMap((violation) =>
      violation.nodes
        .filter((node) => !DEROGATIONS.some((derogation) => derogation.rule === violation.id && derogation.target.test(node.target.join(' '))))
        .map((node) => `${violation.id} (${violation.impact}) on ${node.target.join(' ')}: ${node.failureSummary?.split('\n').slice(1).join(' ').trim()}`),
    );

  const summary: AuditSummary = {
    page: name,
    rulesChecked,
    rulesOnContent,
    rulesInapplicable: results.inapplicable.length,
    violations: results.violations.length,
    blocking,
  };

  console.log(
    `a11y ${name} (${page.url()}): ${rulesChecked} rules checked (${rulesOnContent} on the content), ${summary.rulesInapplicable} not applicable, ` +
      `${summary.violations} rules violated, ${blocking.length} blocking node(s)`,
  );
  await testInfo.attach(`axe-${name}`, { body: JSON.stringify(results.violations, null, 2), contentType: 'application/json' });

  expect(rulesOnContent, `axe checked no rule on the content of ${name}`).toBeGreaterThan(0);
  expect.soft(blocking, `serious or critical accessibility violations on ${name}`).toEqual([]);
  // RGAA 9.1, which axe only checks as a best practice: the outline starts with a level 1 heading.
  expect.soft(await page.locator('h1').count(), `no h1 on ${name}`).toBeGreaterThan(0);

  return summary;
}

/**
 * RGAA 10.11: nothing may stick out of the viewport. Read off the elements rather than the
 * document's scroll width: the theme clips the body horizontally, so an overflowing element is
 * cut off without the page ever scrolling. Content inside its own scrolling or clipping box (a
 * carousel) is that box's business and is skipped.
 */
export async function expectContentFitsViewport(page: Page, context: string): Promise<void> {
  const overflowing = await page.evaluate((excluded) => {
    const viewportWidth = document.documentElement.clientWidth;
    const clippedByAncestor = (element: Element): boolean => {
      for (let parent = element.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
        if (getComputedStyle(parent).overflowX !== 'visible') return true;
      }
      return false;
    };

    return [...document.body.querySelectorAll('*')]
      .filter((element) => !excluded.some((selector) => element.closest(selector)))
      .filter((element) => {
        const box = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        return box.width > 0 && box.height > 0 && style.visibility !== 'hidden' && style.position !== 'fixed'
          && (box.right > viewportWidth + 1 || box.left < -1);
      })
      .filter((element) => !clippedByAncestor(element))
      .slice(0, 5)
      .map((element) => {
        const box = element.getBoundingClientRect();
        return `${element.tagName.toLowerCase()}.${String(element.getAttribute('class') ?? '').split(' ')[0]} from ${Math.round(box.left)} to ${Math.round(box.right)}`;
      });
  }, EXCLUDED);

  expect.soft(overflowing, `${context} sticks out of a ${page.viewportSize()?.width}px viewport`).toEqual([]);
}
