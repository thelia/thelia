import { expect, type Locator, type Page } from '@playwright/test';

/**
 * Arabic is the only right-to-left language installed on the shop; French is the
 * left-to-right reference the same journey is replayed in.
 */
export const RTL_LANG = 'ar';
export const LTR_LANG = 'fr';

export const WIDE_VIEWPORT = { width: 1440, height: 900 };
export const MOBILE_VIEWPORT = { width: 390, height: 844 };

export type ShopStop = { name: string; url: string };

export type ReadingOrder = {
  /** The characters in the order the markup holds them. */
  logical: string;
  /** The same characters sorted by the x they are painted at, left to right. */
  visual: string;
};

/**
 * Switches the session to `lang` and collects the stops of a buying journey.
 *
 * The category and product URLs are read off the home page rather than hard-coded:
 * a language with rewritten URLs serves slugs (`/fauteuils.html`) where a language
 * without them serves `?view=category&…`, and the journey must not care which.
 */
export async function shopStops(page: Page, lang: string): Promise<ShopStop[]> {
  await page.goto(`/?lang=${lang}`);

  return [
    { name: 'home', url: page.url() },
    { name: 'category', url: await hrefOf(page.locator('.CategoryCard-imgLink').first(), `category link on the ${lang} home page`) },
    { name: 'product', url: await hrefOf(page.locator('.ProductCard-imgLink').first(), `product link on the ${lang} home page`) },
    { name: 'cart', url: '/checkout/cart' },
  ];
}

export function stopNamed(stops: ShopStop[], name: string): string {
  const stop = stops.find((candidate) => candidate.name === name);
  if (!stop) throw new Error(`No "${name}" stop in the journey.`);
  return stop.url;
}

/**
 * The document must fit its own scrolling box. No tolerance: a few pixels of
 * overflow is exactly the defect a right-to-left layout regresses into.
 */
export async function expectNoHorizontalOverflow(page: Page, context: string): Promise<void> {
  const { scrollWidth, clientWidth } = await page.evaluate(() => {
    const element = document.scrollingElement ?? document.documentElement;
    return { scrollWidth: element.scrollWidth, clientWidth: element.clientWidth };
  });

  expect(
    scrollWidth,
    `${context} scrolls horizontally: scrollWidth ${scrollWidth} > clientWidth ${clientWidth}`,
  ).toBeLessThanOrEqual(clientWidth);
}

/** Horizontal centre of the header logo, in viewport pixels. */
export async function headerLogoCenter(page: Page): Promise<number> {
  const box = await page.locator('.Header-logo').first().boundingBox();
  if (!box) throw new Error('The header logo has no box — the header did not render.');
  return box.x + box.width / 2;
}

/** Reading order of every element matching `selector`. */
export async function readingOrders(page: Page, selector: string): Promise<ReadingOrder[]> {
  return collect(page, selector);
}

/**
 * Reading order of every element that renders a price, anywhere on the page.
 *
 * Only the deepest element holding a whole amount is measured, so it does not
 * matter whether the theme isolates the value on a `<span dir="ltr">`, on the
 * `<strong>` around it, or with a `<bdi>` — what is checked is the painted result.
 */
export async function priceReadingOrders(page: Page): Promise<ReadingOrder[]> {
  return collect(page, null);
}

export function expectReadsInOrder(order: ReadingOrder, context: string): void {
  expect(
    order.visual,
    `${context} is painted as "${order.visual}" instead of "${order.logical}"`,
  ).toBe(order.logical);
}

/**
 * Runs in the browser. The DOM only ever hands back the logical order of a text,
 * so each character is located on its own with a `Range` and the painted order is
 * rebuilt from the rects. Zero-width marks and whitespace are dropped so the two
 * strings stay comparable.
 *
 * `selector` null means "every element rendering an amount followed by its
 * currency sign", keeping only the deepest one of a nested pair.
 */
async function collect(page: Page, selector: string | null): Promise<ReadingOrder[]> {
  return page.evaluate((wanted) => {
    const isPrice = (element: Element): boolean => /\d[\d.,\s]*€$/.test((element.textContent ?? '').trim());

    const elements = wanted === null
      ? Array.from(document.body.querySelectorAll('*'))
        .filter((element) => isPrice(element) && !Array.from(element.children).some(isPrice))
      : Array.from(document.querySelectorAll(wanted));

    return elements.map((element) => {
      const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
      const characters: { character: string; x: number }[] = [];

      for (let node = walker.nextNode(); node !== null; node = walker.nextNode()) {
        const text = node.nodeValue ?? '';
        for (let index = 0; index < text.length; index++) {
          const character = text[index];
          if (character.trim() === '') continue;
          const range = document.createRange();
          range.setStart(node, index);
          range.setEnd(node, index + 1);
          const rect = range.getBoundingClientRect();
          if (rect.width === 0) continue;
          characters.push({ character, x: rect.left });
        }
      }

      return {
        logical: characters.map((item) => item.character).join(''),
        visual: [...characters].sort((left, right) => left.x - right.x).map((item) => item.character).join(''),
      };
    });
  }, selector);
}

async function hrefOf(locator: Locator, context: string): Promise<string> {
  const href = await locator.getAttribute('href');
  if (!href) throw new Error(`No ${context}.`);
  return href;
}
