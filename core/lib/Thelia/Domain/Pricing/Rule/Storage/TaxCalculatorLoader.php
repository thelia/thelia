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

namespace Thelia\Domain\Pricing\Rule\Storage;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorFactoryInterface;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorInterface;
use Thelia\Model\Country;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;

/**
 * One tax calculator per product of a batch, all products loaded at once, on the
 * shop location - the country Thelia\Action\Sale writes a public promo price with,
 * which is what makes a rule and a flash sale with the same offset agree to the cent.
 */
class TaxCalculatorLoader
{
    public function __construct(private readonly TaxCalculatorFactoryInterface $taxCalculatorFactory)
    {
    }

    /**
     * @param list<int> $productIds
     *
     * @return array<int, TaxCalculatorInterface> keyed by product id
     */
    public function forProducts(array $productIds): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));

        if ([] === $productIds) {
            return [];
        }

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
