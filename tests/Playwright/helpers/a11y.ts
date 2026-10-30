import { readFileSync } from 'node:fs';
import AxeBuilder from '@axe-core/playwright';
import { expect, type Page, type TestInfo } from '@playwright/test';

// RGAA 4.1 rests on WCAG 2.1 level AA: these are the axe rule tags that cover it.
const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'];

// What a visitor is never served: the Symfony debug toolbar, whose own defects would drown
// the theme's.
const EXCLUDED = ['.sf-toolbar', '[id^="sfwdt"]', '.sf-minitoolbar'];

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

export type AuditSummary = {
  page: string;
  rulesChecked: number;
  rulesInapplicable: number;
  violations: number;
  blocking: string[];
};

/**
 * Runs axe on the current page and fails on any serious or critical violation that is not a
 * documented derogation. Prints how many rules really applied: an audit that checked nothing
 * must not read as a pass.
 */
export async function auditPage(page: Page, name: string, testInfo: TestInfo): Promise<AuditSummary> {
  await page.waitForLoadState('networkidle').catch(() => {});

  let builder = new AxeBuilder({ page }).withTags(WCAG_TAGS);
  for (const selector of EXCLUDED) {
    builder = builder.exclude(selector);
  }
  const results = await builder.analyze();

  const rulesChecked = results.passes.length + results.violations.length + results.incomplete.length;
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
    rulesInapplicable: results.inapplicable.length,
    violations: results.violations.length,
    blocking,
  };

  console.log(
    `a11y ${name} (${page.url()}): ${rulesChecked} rules checked, ${summary.rulesInapplicable} not applicable, ` +
      `${summary.violations} rules violated, ${blocking.length} blocking node(s)`,
  );
  await testInfo.attach(`axe-${name}`, { body: JSON.stringify(results.violations, null, 2), contentType: 'application/json' });

  expect(rulesChecked, `axe checked no rule on ${name}`).toBeGreaterThan(0);
  expect.soft(blocking, `serious or critical accessibility violations on ${name}`).toEqual([]);

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
