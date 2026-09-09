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

namespace Thelia\Domain\Sale;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorInterface;
use Thelia\Model\Country;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Map\SaleTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\Sale;

/**
 * Works out what a reserved operation charges a given customer for a batch of
 * sale elements.
 *
 * A reserved operation writes nothing in the catalog — no `promo` flag, no
 * `promo_price` — because those two are what every visitor reads. Its price only
 * exists as the answer to "what does this customer pay", which is what this
 * resolves.
 *
 * It resolves a BATCH: a catalog page, a cart or an API collection asks once for
 * all the sale elements it is about to show, and the work costs the same whether
 * there is one of them or fifty. One statement joins the operations, their
 * audience, their offset and the prices; the products are then loaded in one more
 * so that each gets a tax calculator. Nothing is read per sale element.
 */
class ReservedSalePriceResolver
{
    public function __construct(
        private readonly SaleDiscountCalculator $saleDiscountCalculator,
        private readonly TaxCalculatorFactoryInterface $taxCalculatorFactory,
    ) {
    }

    /**
     * @param list<int> $productSaleElementsIds
     *
     * @return array<int, ReservedPrice> keyed by sale element ID; a sale element with
     *                                   no reserved price of its own is absent
     */
    public function resolve(
        array $productSaleElementsIds,
        Currency $currency,
        ?Customer $customer,
        ?\DateTimeInterface $now = null,
    ): array {
        // A visitor is entitled to no reserved operation, so there is nothing to
        // ask the database in the first place.
        if ([] === $productSaleElementsIds || null === $customer) {
            return [];
        }

        $rows = $this->fetchCandidateRows(
            array_values(array_unique(array_map('intval', $productSaleElementsIds))),
            $currency,
            $customer,
            $now ?? new \DateTime(),
        );

        if ([] === $rows) {
            return [];
        }

        $taxCalculators = $this->loadTaxCalculators($rows);
        $defaultCurrency = Currency::getDefaultCurrency();
        $conversionRate = $currency->getRate() / $defaultCurrency->getRate();

        $resolved = [];

        foreach ($rows as $row) {
            $prices = $this->basePricesOf($row, $conversionRate);

            if (null === $prices) {
                continue;
            }

            [$untaxedPrice, $untaxedPromoPrice] = $prices;

            $taxCalculator = $taxCalculators[(int) $row['product_id']] ?? null;

            if (null === $taxCalculator) {
                continue;
            }

            $reservedPrice = $this->saleDiscountCalculator->computeUntaxedPromoPrice(
                $untaxedPrice,
                (int) $row['price_offset_type'],
                (float) $row['price_offset_value'],
                $taxCalculator,
            );

            // Frozen decision: a reserved price only applies when it beats the price
            // the sale element is on sale for right now, public special offer
            // included. The customer sees the better of the two — the shop never
            // charges a named customer more than a passing visitor, and a reserved
            // operation never has to be coordinated with the public ones.
            $currentPrice = $row['pse_promo'] ? $untaxedPromoPrice : $untaxedPrice;

            if ($reservedPrice >= $currentPrice) {
                continue;
            }

            $saleElementsId = (int) $row['pse_id'];
            $alreadyResolved = $resolved[$saleElementsId] ?? null;

            // Two reserved operations can cover the same sale element: the lowest
            // price wins, not the row the database happened to return last.
            if (null !== $alreadyResolved && $alreadyResolved->untaxedPromoPrice <= $reservedPrice) {
                continue;
            }

            $resolved[$saleElementsId] = new ReservedPrice(
                $saleElementsId,
                $reservedPrice,
                (int) $row['sale_id'],
                null === $row['sale_end_date'] ? null : new \DateTime((string) $row['sale_end_date']),
                (bool) $row['display_initial_price'],
            );
        }

        return $resolved;
    }

    /**
     * Every (sale element, reserved operation) pair the customer is entitled to,
     * in one statement.
     *
     * The date window is evaluated here rather than trusted to `sale.active`: the
     * flag is only as fresh as the last run of the scheduled command, and an
     * operation that ended ten minutes ago must not price a cart. A missing bound
     * is an open one.
     *
     * @param list<int> $productSaleElementsIds
     *
     * @return list<array<string, mixed>>
     */
    private function fetchCandidateRows(
        array $productSaleElementsIds,
        Currency $currency,
        Customer $customer,
        \DateTimeInterface $now,
    ): array {
        $placeholders = [];
        $parameters = [
            ':publicAudienceMode' => Sale::AUDIENCE_MODE_PUBLIC,
            ':now' => $now->format('Y-m-d H:i:s'),
            ':customerId' => $customer->getId(),
            ':currencyId' => $currency->getId(),
            ':defaultCurrencyId' => Currency::getDefaultCurrency()->getId(),
        ];

        foreach ($productSaleElementsIds as $index => $productSaleElementsId) {
            $placeholders[] = ':pse'.$index;
            $parameters[':pse'.$index] = $productSaleElementsId;
        }

        // The selection of sale elements reproduces the one Action\Sale makes for a
        // public operation: a `sale_product` row without an attribute value covers
        // every combination of the product, one with an attribute value covers only
        // the combinations carrying it.
        $sql = <<<SQL
            SELECT
                pse.id AS pse_id,
                pse.product_id AS product_id,
                pse.promo AS pse_promo,
                s.id AS sale_id,
                s.end_date AS sale_end_date,
                s.display_initial_price AS display_initial_price,
                s.price_offset_type AS price_offset_type,
                soc.price_offset_value AS price_offset_value,
                pp.price AS currency_price,
                pp.promo_price AS currency_promo_price,
                pp.from_default_currency AS from_default_currency,
                dpp.price AS default_price,
                dpp.promo_price AS default_promo_price
            FROM product_sale_elements pse
            INNER JOIN sale_product sp
                    ON sp.product_id = pse.product_id
                   AND (
                        sp.attribute_av_id IS NULL
                        OR EXISTS (
                            SELECT 1
                            FROM attribute_combination ac
                            WHERE ac.product_sale_elements_id = pse.id
                              AND ac.attribute_av_id = sp.attribute_av_id
                        )
                   )
            INNER JOIN sale s
                    ON s.id = sp.sale_id
                   AND s.active = 1
                   AND s.audience_mode <> :publicAudienceMode
                   AND (s.start_date IS NULL OR s.start_date <= :now)
                   AND (s.end_date IS NULL OR s.end_date >= :now)
            INNER JOIN sale_customer sc
                    ON sc.sale_id = s.id
                   AND sc.customer_id = :customerId
            INNER JOIN sale_offset_currency soc
                    ON soc.sale_id = s.id
                   AND soc.currency_id = :currencyId
            LEFT JOIN product_price pp
                   ON pp.product_sale_elements_id = pse.id
                  AND pp.currency_id = :currencyId
            LEFT JOIN product_price dpp
                   ON dpp.product_sale_elements_id = pse.id
                  AND dpp.currency_id = :defaultCurrencyId
            WHERE pse.id IN (%s)
            SQL;

        $statement = Propel::getConnection(SaleTableMap::DATABASE_NAME)
            ->prepare(\sprintf($sql, implode(', ', $placeholders)));

        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value);
        }

        $statement->execute();

        return $statement->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * The untaxed catalog price and promo price of the sale element in the asked
     * currency, or null when it has no price at all.
     *
     * A currency with no row of its own — or a row flagged `from_default_currency`,
     * which means it was converted rather than typed in — is converted from the
     * default currency, exactly as ProductSaleElements::getPricesByCurrency() does.
     *
     * @param array<string, mixed> $row
     *
     * @return array{0: float, 1: float}|null
     */
    private function basePricesOf(array $row, float $conversionRate): ?array
    {
        if (null !== $row['currency_price'] && !$row['from_default_currency']) {
            return [(float) $row['currency_price'], (float) $row['currency_promo_price']];
        }

        if (null === $row['default_price']) {
            return null;
        }

        return [
            (float) $row['default_price'] * $conversionRate,
            (float) $row['default_promo_price'] * $conversionRate,
        ];
    }

    /**
     * One tax calculator per product of the batch, all products loaded at once.
     *
     * The calculator is loaded on the shop location, the same country
     * Thelia\Action\Sale uses to write a public promo price — which is what makes
     * the two paths agree to the cent.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array<int, TaxCalculatorInterface>
     */
    private function loadTaxCalculators(array $rows): array
    {
        $productIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['product_id'],
            $rows,
        )));

        $shopLocation = Country::getShopLocation();
        $calculators = [];

        /** @var Product $product */
        foreach (ProductQuery::create()->filterById($productIds, Criteria::IN)->find() as $product) {
            $calculators[(int) $product->getId()] = $this->taxCalculatorFactory
                ->createTaxCalculator()
                ->load($product, $shopLocation);
        }

        return $calculators;
    }
}
