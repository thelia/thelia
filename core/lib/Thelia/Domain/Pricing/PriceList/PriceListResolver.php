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

namespace Thelia\Domain\Pricing\PriceList;

use Propel\Runtime\Propel;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Pricing\Rule\Storage\BasePriceLoader;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Map\PriceListItemTableMap;

/**
 * Resolves what the price lists of a customer say about a batch of sale elements.
 *
 * The lists apply in rank order ({@see ApplicablePriceLists}). The first one that has a
 * line for the sale element at the quantity asked wins, and its line is the one with the
 * greatest minimum quantity not above the quantity. A price list is final: nothing the
 * catalog, a group or the customer holds is applied to it. A line in % applies to the
 * catalog price, never to the promo price.
 *
 * An exclusive list that does not hold the sale element, with no list of higher rank
 * pricing it, closes it: the answer is a match that cannot be bought. A list ranked after
 * the exclusive one does not open it again. The sale elements an exclusive list holds are the
 * ones its customer may buy, in every currency: the list prices them in its own, and in
 * another one the lists below it, then the catalog, price them. A sale element the list holds
 * for a quantity above the one asked is still held: it is bought at the price below.
 *
 * Two statements for the whole batch, whatever its size: the lists of the customer, then
 * every line of those lists for the sale elements asked; a third one for the catalog prices
 * when a line in % has to be applied. Each answer is remembered for the request.
 */
final class PriceListResolver implements PriceListResolverInterface, ResetInterface
{
    /** @var array<string, array<int, array<int, array<int, array{price: string|null, discount: string|null}>>>> lines by lists, sale element, list and minimum quantity */
    private array $lines = [];

    /** @var array<int, array<int, float|null>> catalog price by currency and sale element, null when there is none */
    private array $catalogPrices = [];

    public function __construct(
        private readonly ApplicablePriceLists $applicablePriceLists,
        private readonly BasePriceLoader $basePriceLoader,
    ) {
    }

    public function resolve(array $productSaleElementsIds, Currency $currency, ?Customer $customer, array $quantities = []): array
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds) {
            return [];
        }

        $lists = $this->applicablePriceLists->for($customer, $currency);

        if ([] === $lists) {
            return [];
        }

        $linesBySaleElement = $this->linesOf($lists, $productSaleElementsIds);
        $this->warmCatalogPrices($lists, $linesBySaleElement, (int) $currency->getId());

        $matches = [];

        foreach ($productSaleElementsIds as $pseId) {
            $match = $this->matchOf($lists, $linesBySaleElement[$pseId] ?? [], (int) $currency->getId(), $pseId, $this->quantityOf($quantities, $pseId));

            if (null !== $match) {
                $matches[$pseId] = $match;
            }
        }

        return $matches;
    }

    public function tiersFor(array $productSaleElementsIds, Currency $currency, ?Customer $customer): array
    {
        $productSaleElementsIds = array_values(array_unique(array_map('intval', $productSaleElementsIds)));

        if ([] === $productSaleElementsIds) {
            return [];
        }

        $lists = $this->applicablePriceLists->for($customer, $currency);

        if ([] === $lists) {
            return [];
        }

        $linesBySaleElement = $this->linesOf($lists, $productSaleElementsIds);
        $this->warmCatalogPrices($lists, $linesBySaleElement, (int) $currency->getId());

        $tiersBySaleElement = [];

        foreach ($productSaleElementsIds as $pseId) {
            // What the lists above have already priced, from this quantity up: a list ranked
            // after them prices what is below it, as matchOf() does.
            $ceiling = null;
            $published = [];

            foreach ($lists as $list) {
                $lines = $linesBySaleElement[$pseId][$list->id] ?? [];
                $tiers = $this->tiersOf($list, $lines, (int) $currency->getId(), $pseId);

                if ([] !== $tiers) {
                    $below = null === $ceiling
                        ? $tiers
                        : array_values(array_filter($tiers, static fn (array $tier): bool => $tier['minQuantity'] < $ceiling));

                    if ([] !== $below) {
                        $published = [...$below, ...$published];
                        $ceiling = $below[0]['minQuantity'];
                    }
                }

                // The exclusive list that does not hold the sale element closes it: no list
                // of a lower rank opens it again.
                if ($list->exclusive && [] === $lines) {
                    break;
                }
            }

            if ([] !== $published) {
                $tiersBySaleElement[$pseId] = $published;
            }
        }

        return $tiersBySaleElement;
    }

    public function reset(): void
    {
        $this->lines = [];
        $this->catalogPrices = [];
    }

    /**
     * @param list<ApplicablePriceList>                                                $lists
     * @param array<int, array<int, array{price: string|null, discount: string|null}>> $linesBySaleElement lines of one sale element, by list then minimum quantity
     */
    private function matchOf(array $lists, array $linesBySaleElement, int $currencyId, int $pseId, int $quantity): ?PriceListMatch
    {
        foreach ($lists as $list) {
            $lines = $linesBySaleElement[$list->id] ?? [];
            $tiers = $this->tiersOf($list, $lines, $currencyId, $pseId);
            $retained = null;

            foreach ($tiers as $tier) {
                if ($tier['minQuantity'] <= $quantity) {
                    $retained = $tier;
                }
            }

            if (null !== $retained) {
                return new PriceListMatch($list->id, $list->code, $retained['untaxedPrice'], true, $retained['minQuantity'], $tiers);
            }

            // The exclusive list closes what it does not hold, in whatever currency it is read.
            if ($list->exclusive && [] === $lines) {
                return new PriceListMatch($list->id, $list->code, null, false, 0, []);
            }
        }

        return null;
    }

    /**
     * The tiers of one list for one sale element, the smallest minimum quantity first. A line in
     * % of a sale element the catalog does not price in the currency is no line, and a list in
     * another currency has no price to give: an exclusive one is read for what it holds only.
     *
     * @param array<int, array{price: string|null, discount: string|null}> $linesByMinimumQuantity
     *
     * @return list<array{minQuantity: int, untaxedPrice: string}>
     */
    private function tiersOf(ApplicablePriceList $list, array $linesByMinimumQuantity, int $currencyId, int $pseId): array
    {
        if ($list->currencyId !== $currencyId) {
            return [];
        }

        ksort($linesByMinimumQuantity);
        $tiers = [];

        foreach ($linesByMinimumQuantity as $minQuantity => $line) {
            if (null !== $line['price']) {
                $price = (float) $line['price'];
            } else {
                $catalogPrice = $this->catalogPrices[$currencyId][$pseId] ?? null;

                if (null === $catalogPrice || null === $line['discount']) {
                    continue;
                }

                $price = $catalogPrice * (1 - (float) $line['discount'] / 100);
            }

            $tiers[] = ['minQuantity' => $minQuantity, 'untaxedPrice' => number_format(round($price, 6), 6, '.', '')];
        }

        return $tiers;
    }

    /**
     * @param list<ApplicablePriceList> $lists
     * @param list<int>                 $productSaleElementsIds
     *
     * @return array<int, array<int, array<int, array{price: string|null, discount: string|null}>>> sale element, then list, then minimum quantity
     */
    private function linesOf(array $lists, array $productSaleElementsIds): array
    {
        $key = implode(',', array_map(static fn (ApplicablePriceList $list): int => $list->id, $lists));
        $missing = array_values(array_filter(
            $productSaleElementsIds,
            fn (int $pseId): bool => !isset($this->lines[$key][$pseId]),
        ));

        if ([] !== $missing) {
            foreach ($missing as $pseId) {
                $this->lines[$key][$pseId] = [];
            }

            $statement = Propel::getConnection(PriceListItemTableMap::DATABASE_NAME)->query(\sprintf(
                'SELECT price_list_id, product_sale_elements_id, min_quantity, price, discount_percent
                 FROM `price_list_item`
                 WHERE price_list_id IN (%s) AND product_sale_elements_id IN (%s)',
                implode(', ', array_map(static fn (ApplicablePriceList $list): int => $list->id, $lists)),
                implode(', ', $missing),
            ));

            foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                $this->lines[$key][(int) $row['product_sale_elements_id']][(int) $row['price_list_id']][(int) $row['min_quantity']] = [
                    'price' => null === $row['price'] ? null : (string) $row['price'],
                    'discount' => null === $row['discount_percent'] ? null : (string) $row['discount_percent'],
                ];
            }
        }

        return array_intersect_key($this->lines[$key], array_flip($productSaleElementsIds));
    }

    /**
     * Reads the catalog price of the sale elements that hold a line in % of a list in the currency, once.
     *
     * @param list<ApplicablePriceList>                                                            $lists
     * @param array<int, array<int, array<int, array{price: string|null, discount: string|null}>>> $linesBySaleElement
     */
    private function warmCatalogPrices(array $lists, array $linesBySaleElement, int $currencyId): void
    {
        $needed = [];
        $listsInTheCurrency = [];

        foreach ($lists as $list) {
            if ($list->currencyId === $currencyId) {
                $listsInTheCurrency[$list->id] = true;
            }
        }

        foreach ($linesBySaleElement as $pseId => $linesByList) {
            if (\array_key_exists($pseId, $this->catalogPrices[$currencyId] ?? [])) {
                continue;
            }

            foreach ($linesByList as $listId => $linesByMinimumQuantity) {
                if (!isset($listsInTheCurrency[$listId])) {
                    continue;
                }

                foreach ($linesByMinimumQuantity as $line) {
                    if (null === $line['price']) {
                        $needed[$pseId] = $pseId;

                        continue 3;
                    }
                }
            }
        }

        if ([] === $needed) {
            return;
        }

        $basePrices = $this->basePriceLoader->load(array_values($needed));

        foreach ($needed as $pseId) {
            $this->catalogPrices[$currencyId][$pseId] = ($basePrices[$pseId] ?? null)?->in($currencyId);
        }
    }

    /**
     * @param array<int, int> $quantities
     */
    private function quantityOf(array $quantities, int $pseId): int
    {
        return max(1, (int) floor((float) ($quantities[$pseId] ?? 1)));
    }
}
