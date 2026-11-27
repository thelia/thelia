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

namespace Thelia\Domain\Quote\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Lang;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleI18nQuery;

/**
 * Reads the products behind the lines of a quote, and writes lines many at a time.
 *
 * A revision of a hundred lines is read in four queries and written in one insert, never one
 * statement per line.
 */
final readonly class QuoteLineWriter
{
    private const int ROWS_PER_INSERT = 200;

    /**
     * What each sale element keeps on a line; a sale element that does not exist, or whose
     * product does not, is absent.
     *
     * @param list<int> $saleElementIds
     *
     * @return array<int, QuoteLineSnapshot> by sale element id
     */
    public function snapshots(array $saleElementIds, string $locale, ?ConnectionInterface $connection = null): array
    {
        $saleElementIds = array_values(array_unique($saleElementIds));

        if ([] === $saleElementIds) {
            return [];
        }

        $saleElements = [];

        foreach (ProductSaleElementsQuery::create()->filterById($saleElementIds)->find($connection) as $saleElement) {
            $saleElements[(int) $saleElement->getId()] = $saleElement;
        }

        $productIds = array_values(array_unique(array_map(static fn ($saleElement): int => (int) $saleElement->getProductId(), $saleElements)));
        $products = [];

        foreach (ProductQuery::create()->filterById($productIds)->find($connection) as $product) {
            $products[(int) $product->getId()] = $product;
        }

        $titles = $this->productTitles($productIds, $locale, $connection);
        $taxRuleTitles = $this->taxRuleTitles(array_values(array_filter(array_map(static fn ($product): ?int => $product->getTaxRuleId(), $products))), $locale, $connection);
        $snapshots = [];

        foreach ($saleElements as $saleElementId => $saleElement) {
            $product = $products[(int) $saleElement->getProductId()] ?? null;

            if (null === $product) {
                continue;
            }

            $taxRuleId = $product->getTaxRuleId();

            $snapshots[$saleElementId] = new QuoteLineSnapshot(
                (int) $product->getId(),
                $saleElementId,
                (string) $product->getRef(),
                (string) $saleElement->getRef(),
                $titles[(int) $product->getId()] ?? (string) $product->getRef(),
                null === $taxRuleId ? null : (int) $taxRuleId,
                null === $taxRuleId ? null : ($taxRuleTitles[(int) $taxRuleId] ?? null),
            );
        }

        return $snapshots;
    }

    /**
     * One multi-row insert per two hundred lines.
     *
     * @param list<array{position: int, snapshot: QuoteLineSnapshot, quantity: float, catalogPrice: string, price: string}> $lines
     */
    public function insert(int $revisionId, array $lines, ConnectionInterface $connection): void
    {
        foreach (array_chunk($lines, self::ROWS_PER_INSERT) as $chunk) {
            $placeholders = [];
            $values = [];

            foreach ($chunk as $line) {
                $snapshot = $line['snapshot'];
                $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
                array_push(
                    $values,
                    $revisionId,
                    $line['position'],
                    $snapshot->productId,
                    $snapshot->productSaleElementsId,
                    $snapshot->productRef,
                    $snapshot->productSaleElementsRef,
                    $snapshot->title,
                    $line['quantity'],
                    $line['catalogPrice'],
                    $line['price'],
                    $snapshot->taxRuleId,
                    $snapshot->taxRuleTitle,
                );
            }

            $statement = $connection->prepare(
                'INSERT INTO `quote_item` (`quote_revision_id`, `position`, `product_id`, `product_sale_elements_id`, `product_ref`, `product_sale_elements_ref`, `title`, `quantity`, `catalog_price`, `price`, `tax_rule_id`, `tax_rule_title`, `created_at`, `updated_at`) VALUES '
                .implode(', ', $placeholders),
            );
            $statement->execute($values);
        }
    }

    /**
     * @param list<int> $productIds
     *
     * @return array<int, string> title by product id, in the locale or else in the default language
     */
    private function productTitles(array $productIds, string $locale, ?ConnectionInterface $connection): array
    {
        $titles = [];
        $defaultLocale = Lang::getDefaultLanguage()->getLocale();

        foreach ([$defaultLocale, $locale] as $wanted) {
            foreach (ProductI18nQuery::create()->filterById($productIds)->filterByLocale($wanted)->find($connection) as $translation) {
                if ('' !== (string) $translation->getTitle()) {
                    $titles[(int) $translation->getId()] = (string) $translation->getTitle();
                }
            }
        }

        return $titles;
    }

    /**
     * @param list<int> $taxRuleIds
     *
     * @return array<int, string>
     */
    private function taxRuleTitles(array $taxRuleIds, string $locale, ?ConnectionInterface $connection): array
    {
        if ([] === $taxRuleIds) {
            return [];
        }

        $titles = [];
        $defaultLocale = Lang::getDefaultLanguage()->getLocale();

        foreach ([$defaultLocale, $locale] as $wanted) {
            foreach (TaxRuleI18nQuery::create()->filterById($taxRuleIds)->filterByLocale($wanted)->find($connection) as $translation) {
                if ('' !== (string) $translation->getTitle()) {
                    $titles[(int) $translation->getId()] = (string) $translation->getTitle();
                }
            }
        }

        return $titles;
    }
}
