<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Domain\Quote\Acceptance;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Domain\Quote\Exception\QuoteCurrencyUnavailableException;
use Thelia\Domain\Quote\Exception\QuoteLinesUnavailableException;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItem;

/**
 * Whether what a revision sells can still be sold: the currency it is written in, and every one of
 * its lines. Judged on the shop as it is now, never on what the lines said when they were written.
 */
final readonly class QuoteSellability
{
    /**
     * @param list<QuoteItem> $lines
     *
     * @throws QuoteCurrencyUnavailableException
     * @throws QuoteLinesUnavailableException    with every line that cannot be ordered, not the first
     */
    public function assert(Quote $quote, array $lines, ConnectionInterface $connection): void
    {
        if (!CurrencyQuery::create()->findPk($quote->getCurrencyId(), $connection) instanceof Currency) {
            throw new QuoteCurrencyUnavailableException();
        }

        $unavailable = $this->partition($lines, $connection)['unavailable'];

        if ([] !== $unavailable) {
            throw new QuoteLinesUnavailableException($unavailable);
        }
    }

    /**
     * The lines of a revision that can be ordered and, as the product reference and the title they
     * were written with, those that cannot: the same judgment as {@see assert()}, for a caller that
     * leaves the second kind out instead of refusing.
     *
     * @param list<QuoteItem> $lines
     *
     * @return array{sellable: list<QuoteItem>, unavailable: list<array{reference: string, title: string}>}
     */
    public function partition(array $lines, ConnectionInterface $connection): array
    {
        $saleElementIds = array_values(array_unique(array_filter(array_map(
            static fn (QuoteItem $line): ?int => $line->getProductSaleElementsId(),
            $lines,
        ))));

        $productOfSaleElement = [];

        if ([] !== $saleElementIds) {
            foreach (ProductSaleElementsQuery::create()->filterById($saleElementIds)->find($connection) as $saleElement) {
                $productOfSaleElement[(int) $saleElement->getId()] = (int) $saleElement->getProductId();
            }
        }

        // A bundle is ordered with the composition the buyer chose, which a line of a quote has none of.
        $bundleIds = [] === $productOfSaleElement ? [] : array_map(
            'intval',
            ProductQuery::create()->filterById(array_values(array_unique($productOfSaleElement)))->filterByIsBundle(1)->select('Id')->find($connection)->toArray(),
        );

        $sellable = [];
        $unavailable = [];

        foreach ($lines as $line) {
            $saleElementId = $line->getProductSaleElementsId();
            $productId = null === $saleElementId ? null : ($productOfSaleElement[(int) $saleElementId] ?? null);

            if (null === $productId || \in_array($productId, $bundleIds, true)) {
                $unavailable[] = ['reference' => (string) $line->getProductRef(), 'title' => (string) $line->getTitle()];

                continue;
            }

            $sellable[] = $line;
        }

        return ['sellable' => $sellable, 'unavailable' => $unavailable];
    }
}
