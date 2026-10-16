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

namespace Thelia\Domain\SaleUnit\Pricing;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\SaleUnit\SaleUnitContext;
use Thelia\Model\Currency;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\SaleUnitProductPriceQuery;

/**
 * The price the current sale unit sells a batch of sale elements at, when it has
 * one of its own: a row in `sale_unit_product_price` replaces the catalog price
 * for that unit, and a sale element without a row keeps the national price — the
 * fallback the whole feature stands on.
 *
 * A row is read per currency with the exact semantics of `product_price`: a row
 * for the asked currency wins; a missing row, or one flagged from_default_currency,
 * is computed from the default-currency row and the currency rates. Whether the
 * promo or the plain price applies follows `product_sale_elements.promo`, the same
 * flag the national prices obey.
 */
class SaleUnitPriceResolver
{
    public function __construct(private readonly SaleUnitContext $saleUnitContext)
    {
    }

    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, float> untaxed price by sale element id; a sale element the
     *                           unit does not price is absent, its catalog price stands
     */
    public function resolve(array $productSaleElementsIds, Currency $currency): array
    {
        $saleUnit = $this->saleUnitContext->getSaleUnit();

        if (null === $saleUnit || [] === $productSaleElementsIds) {
            return [];
        }

        $defaultCurrency = Currency::getDefaultCurrency();

        $rows = SaleUnitProductPriceQuery::create()
            ->filterBySaleUnitId($saleUnit->getId())
            ->filterByProductSaleElementsId($productSaleElementsIds, Criteria::IN)
            ->filterByCurrencyId(array_unique([$currency->getId(), $defaultCurrency->getId()]), Criteria::IN)
            ->find();

        if (0 === $rows->count()) {
            return [];
        }

        $promoFlags = $this->promoFlagsOf($productSaleElementsIds);

        /** @var array<int, \Thelia\Model\SaleUnitProductPrice> $asked keyed by sale element id */
        $asked = [];
        /** @var array<int, \Thelia\Model\SaleUnitProductPrice> $default keyed by sale element id */
        $default = [];

        foreach ($rows as $row) {
            if ($row->getCurrencyId() === $currency->getId()) {
                $asked[$row->getProductSaleElementsId()] = $row;
            }

            if ($row->getCurrencyId() === $defaultCurrency->getId()) {
                $default[$row->getProductSaleElementsId()] = $row;
            }
        }

        $prices = [];

        foreach (array_unique(array_map('intval', $productSaleElementsIds)) as $pseId) {
            $row = $asked[$pseId] ?? null;
            $isPromo = $promoFlags[$pseId] ?? false;

            if (null !== $row && !$row->getFromDefaultCurrency()) {
                $prices[$pseId] = (float) ($isPromo ? $row->getPromoPrice() : $row->getPrice());

                continue;
            }

            $defaultRow = $default[$pseId] ?? null;

            if (null === $defaultRow) {
                // The unit prices this sale element in some other currency only:
                // for the asked one, the national price stands.
                continue;
            }

            $value = (float) ($isPromo ? $defaultRow->getPromoPrice() : $defaultRow->getPrice());
            $prices[$pseId] = $value * (float) $currency->getRate() / (float) $defaultCurrency->getRate();
        }

        return $prices;
    }

    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, bool>
     */
    private function promoFlagsOf(array $productSaleElementsIds): array
    {
        $flags = [];

        foreach (ProductSaleElementsQuery::create()
            ->filterById($productSaleElementsIds, Criteria::IN)
            ->select(['Id', 'Promo'])
            ->find() as $row) {
            $flags[(int) $row['Id']] = 1 === (int) $row['Promo'];
        }

        return $flags;
    }
}
