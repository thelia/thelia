import { expect, type Locator, type Page } from '@playwright/test';

/**
 * Readers of what the Flexy front prints about prices, shared by the customer-group specs.
 * Amounts are read from the `<span dir="ltr">` the theme paints each figure in, the mention
 * from `.TaxMention[data-tax-display]` the theme draws beside every price (`taxed` / `untaxed`).
 */
export type TaxDisplay = 'taxed' | 'untaxed';

export type DisplayedPrice = { amount: number; display: TaxDisplay | null };

/** "€1,104.15" -> 1104.15 (the specs run in en-US). */
export function parseAmount(text: string): number {
  const cleaned = text.replace(/[^\d.,-]/g, '').replace(/,/g, '');
  const value = Number.parseFloat(cleaned);
  if (Number.isNaN(value)) throw new Error(`No amount in "${text}"`);
  return value;
}

export function round2(value: number): number {
  return Math.round(value * 100) / 100;
}

export async function readPrice(scope: Locator): Promise<DisplayedPrice> {
  await expect(scope.first()).toBeVisible();
  const amount = parseAmount(await scope.locator('span[dir="ltr"]').first().innerText());
  const mention = scope.locator('.TaxMention').first();
  const display = (await mention.count()) > 0 ? ((await mention.getAttribute('data-tax-display')) as TaxDisplay) : null;
  return { amount, display };
}

/** The price block of a product card of a listing, found by the slug of the product it links to. */
export function cardPriceBlock(page: Page, slug: string): Locator {
  return page
    .locator('.ProductCard')
    .filter({ has: page.locator(`a.ProductCard-title[href$="/${slug}.html"]`) })
    .locator('.ProductCard-price')
    .first();
}

/** The price block of a product page. */
export function productPagePriceBlock(page: Page): Locator {
  return page.locator('.PseSelector-price').first();
}

export async function readCardPrice(page: Page, listingPath: string, slug: string): Promise<DisplayedPrice> {
  await page.goto(listingPath);
  return readPrice(cardPriceBlock(page, slug));
}

export async function readProductPagePrice(page: Page, slug: string): Promise<DisplayedPrice> {
  await page.goto(`/${slug}.html`);
  return readPrice(productPagePriceBlock(page));
}

export type CartSummary = {
  /** Present only when the cart is shown without taxes. */
  totalWithoutTaxes: number | null;
  vat: number | null;
  /** "Total" (taxed display) or "Total incl. tax to pay" (untaxed display). */
  total: number;
  totalLabel: string;
};

/**
 * Reads the totals box of the cart, of a checkout step or of an order page (the `Summary`
 * components share the `Summary-line` / `Summary-total` markup).
 */
export async function readSummary(page: Page): Promise<CartSummary> {
  const totalBlock = page.locator('.Summary-total').first();
  await expect(totalBlock).toBeVisible();
  const amountOfLine = async (label: RegExp): Promise<number | null> => {
    const line = page.locator('.Summary-line').filter({ hasText: label }).first();
    return (await line.count()) > 0 ? parseAmount(await line.locator('span[dir="ltr"]').first().innerText()) : null;
  };
  return {
    totalWithoutTaxes: await amountOfLine(/^\s*Total excl\. tax/i),
    vat: await amountOfLine(/^\s*VAT/i),
    total: parseAmount(await totalBlock.locator('span[dir="ltr"]').first().innerText()),
    totalLabel: (await totalBlock.innerText()).replace(/\s+/g, ' ').trim(),
  };
}
