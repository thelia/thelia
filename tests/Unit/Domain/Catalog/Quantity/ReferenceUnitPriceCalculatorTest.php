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

namespace Thelia\Tests\Unit\Domain\Catalog\Quantity;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Catalog\Quantity\QuantityRule;
use Thelia\Domain\Catalog\Quantity\ReferenceUnitPriceCalculator;
use Thelia\Domain\Catalog\Quantity\UnitCode;
use Thelia\Model\ProductSaleElements;

final class ReferenceUnitPriceCalculatorTest extends TestCase
{
    public function testABottleOfSeventyFiveCentilitresIsPricedPerLitre(): void
    {
        $price = (new ReferenceUnitPriceCalculator())->compute(9.99, new QuantityRule(unitQuantity: 0.75, referenceUnitQuantity: 1, unitCode: UnitCode::LITRE));

        self::assertNotNull($price);
        self::assertSame(13.32, $price->price);
        self::assertSame(1.0, $price->referenceUnitQuantity);
        self::assertSame(UnitCode::LITRE, $price->unitCode);
    }

    public function testAPackOfTwoHundredAndFiftyGramsIsPricedPerKilogram(): void
    {
        $price = (new ReferenceUnitPriceCalculator())->compute(3.49, new QuantityRule(unitQuantity: 250, referenceUnitQuantity: 1000, unitCode: UnitCode::GRAM));

        self::assertNotNull($price);
        self::assertSame(13.96, $price->price);
        self::assertSame(1000.0, $price->referenceUnitQuantity);
        self::assertSame(UnitCode::GRAM, $price->unitCode);
    }

    public function testTheReferenceMayBeSmallerThanTheUnitSold(): void
    {
        $price = (new ReferenceUnitPriceCalculator())->compute(9.99, new QuantityRule(unitQuantity: 0.75, referenceUnitQuantity: 0.5, unitCode: UnitCode::LITRE));

        self::assertSame(6.66, $price?->price);
    }

    public function testTheReferencePriceIsRoundedToTheCent(): void
    {
        $price = (new ReferenceUnitPriceCalculator())->compute(2.99, new QuantityRule(unitQuantity: 0.33, referenceUnitQuantity: 1, unitCode: UnitCode::LITRE));

        self::assertSame(9.06, $price?->price);
    }

    public function testACombinationThatDoesNotSayWhatItContainsHasNoReferencePrice(): void
    {
        $calculator = new ReferenceUnitPriceCalculator();

        self::assertNull($calculator->compute(9.99, new QuantityRule()));
        self::assertNull($calculator->compute(9.99, new QuantityRule(unitQuantity: 0.75, referenceUnitQuantity: 1)));
        self::assertNull($calculator->compute(9.99, new QuantityRule(unitQuantity: 0.75, unitCode: UnitCode::LITRE)));
        self::assertNull($calculator->compute(9.99, new QuantityRule(referenceUnitQuantity: 1, unitCode: UnitCode::LITRE)));
    }

    public function testTheUnitsAreReadFromTheCombination(): void
    {
        $combination = (new ProductSaleElements())->setUnitQuantity('0.750000')->setReferenceUnitQuantity('1.000000')->setUnitCode('l');

        self::assertSame(13.32, (new ReferenceUnitPriceCalculator())->compute(9.99, $combination)?->price);
    }
}
