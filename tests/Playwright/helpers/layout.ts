import { expect, type Page } from '@playwright/test';

/**
 * Layout checks read off the bounding boxes of the elements, not off their visibility: an element
 * can be "visible" to Playwright and still be cut by a clipping ancestor, pushed out of the screen
 * or laid under its neighbour.
 *
 * For every block matching `scope` the checks run on the elements that own text or that can be
 * activated (links, buttons, fields, images) and on the block itself:
 *  - the box stays inside the viewport, on both sides;
 *  - no ancestor that clips its content (`overflow: hidden | clip`) cuts the box;
 *  - a text box that clips its own overflow does not hold a text wider than itself;
 *  - two boxes that do not contain each other do not intersect (each line of a wrapped inline
 *    element counts as a box of its own);
 *  - once scrolled to the middle of the screen, an actionable element is the one found under its
 *    own centre: nothing is laid over it.
 */
export type LayoutReport = { blocks: number; boxes: number; problems: string[] };

const INTERACTIVE = 'a[href], button, input:not([type="hidden"]), select, textarea, img';

// What a visitor is never served: the Symfony debug toolbar.
const EXCLUDED = '.sf-toolbar, [id^="sfwdt"], .sf-minitoolbar';

export async function measureLayout(page: Page, scope: string, ignored: string[] = []): Promise<LayoutReport> {
  return await page.evaluate(
    async ({ scope, interactive, excluded, toolbar }) => {
      // The debug toolbar of the dev kernel sits over the bottom of the screen: a visitor is never served it.
      document.querySelectorAll<HTMLElement>(toolbar).forEach((element) => {
        element.style.display = 'none';
      });
      const viewportWidth = document.documentElement.clientWidth;
      const problems: string[] = [];
      let boxes = 0;

      const describe = (element: Element): string => {
        const first = String(element.getAttribute('class') ?? '').trim().split(/\s+/)[0];
        const text = (element.textContent ?? '').trim().replace(/\s+/g, ' ').slice(0, 30);
        return `${element.tagName.toLowerCase()}${first ? `.${first}` : ''}${text ? ` "${text}"` : ''}`;
      };
      const isShown = (element: Element): boolean => {
        const box = element.getBoundingClientRect();
        const style = getComputedStyle(element);
        return box.width >= 2 && box.height >= 2 && style.visibility !== 'hidden' && style.display !== 'none' && Number(style.opacity) > 0;
      };
      const ownsText = (element: Element): boolean =>
        [...element.childNodes].some((node) => node.nodeType === Node.TEXT_NODE && (node.textContent ?? '').trim() !== '');
      const rectsOf = (element: Element): DOMRect[] => [...element.getClientRects()].filter((rect) => rect.width >= 2 && rect.height >= 2);
      const clips = (style: CSSStyleDeclaration): boolean => ['hidden', 'clip'].includes(style.overflowX) || ['hidden', 'clip'].includes(style.overflowY);

      const roots = [...document.querySelectorAll(scope)].filter((root) => isShown(root) && !root.closest(excluded));

      for (const root of roots) {
        const elements = [root, ...root.querySelectorAll('*')]
          .filter((element) => isShown(element) && !element.closest(excluded))
          .filter((element) => element === root || ownsText(element) || element.matches(interactive));
        boxes += elements.length;

        for (const element of elements) {
          const box = element.getBoundingClientRect();
          if (box.left < -1 || box.right > viewportWidth + 1) {
            problems.push(`${describe(element)} leaves the screen (${Math.round(box.left)} to ${Math.round(box.right)} of ${viewportWidth})`);
          }

          for (let ancestor = element.parentElement; ancestor && ancestor !== document.body; ancestor = ancestor.parentElement) {
            if (!clips(getComputedStyle(ancestor))) continue;
            const ancestorBox = ancestor.getBoundingClientRect();
            if (box.left < ancestorBox.left - 1 || box.right > ancestorBox.right + 1 || box.top < ancestorBox.top - 1 || box.bottom > ancestorBox.bottom + 1) {
              problems.push(`${describe(element)} is cut by ${describe(ancestor)}`);
              break;
            }
          }

          const style = getComputedStyle(element);
          if (ownsText(element) && clips(style) && element.scrollWidth > element.clientWidth + 1) {
            problems.push(`${describe(element)} clips a text wider than itself (${element.scrollWidth} in ${element.clientWidth})`);
          }
        }

        for (let first = 0; first < elements.length; first++) {
          for (let second = first + 1; second < elements.length; second++) {
            const one = elements[first];
            const other = elements[second];
            if (one.contains(other) || other.contains(one)) continue;
            const overlap = rectsOf(one).some((a) =>
              rectsOf(other).some((b) => Math.min(a.right, b.right) - Math.max(a.left, b.left) > 1 && Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top) > 1),
            );
            if (overlap) problems.push(`${describe(one)} overlaps ${describe(other)}`);
          }
        }

        for (const element of elements.filter((candidate) => candidate.matches(interactive))) {
          // The page may still be moving (an image that loads, a live component that redraws): look again before judging.
          let top: Element | null = null;
          for (let attempt = 0; attempt < 5; attempt++) {
            element.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' });
            await new Promise((resolve) => setTimeout(resolve, 100));
            const box = element.getBoundingClientRect();
            top = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
            if (top !== null && (element.contains(top) || top.contains(element))) break;
          }
          if (top === null || !(element.contains(top) || top.contains(element))) {
            problems.push(`${describe(element)} is covered by ${top ? describe(top) : 'nothing'}`);
          }
        }
      }

      return { blocks: roots.length, boxes, problems: [...new Set(problems)] };
    },
    { scope, interactive: INTERACTIVE, excluded: [EXCLUDED, ...ignored].join(', '), toolbar: EXCLUDED },
  );
}

/**
 * Fails when a block of `scope` is cut, off screen, covered or overlapped; also fails when nothing was measured.
 * `ignored` names what is not on the screen to begin with (a closed drawer) and is left out.
 */
export async function expectBlocksLaidOut(page: Page, scope: string, context: string, ignored: string[] = []): Promise<LayoutReport> {
  await page.waitForLoadState('networkidle').catch(() => {});
  const report = await measureLayout(page, scope, ignored);
  console.log(`layout ${context} (${page.viewportSize()?.width}px): ${report.blocks} block(s), ${report.boxes} box(es), ${report.problems.length} problem(s)`);
  expect(report.blocks, `no block matches ${scope} on ${context}`).toBeGreaterThan(0);
  expect(report.boxes, `no box measured in ${scope} on ${context}`).toBeGreaterThan(report.blocks);
  expect.soft(report.problems, `layout of ${context}`).toEqual([]);

  return report;
}
