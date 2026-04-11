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

namespace Thelia\Tests\Unit\Taxation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Taxation\TaxEngine\TaxType\PricePercentTaxType;
use Thelia\Model\Product;

final class PricePercentTaxTypeTest extends TestCase
{
    public function testSetPercentageStoresRequirement(): void
    {
        $type = (new PricePercentTaxType())->setPercentage(20);

        self::assertSame(20, $type->getRequirement('percent'));
    }

    public function testPricePercentRetrieverConvertsPercentToFactor(): void
    {
        $type = (new PricePercentTaxType())->setPercentage(20);

        self::assertEqualsWithDelta(0.20, $type->pricePercentRetriever(), 1e-9);
    }

    public function testFixAmountRetrieverReturnsZeroForPercentTypes(): void
    {
        $type = (new PricePercentTaxType())->setPercentage(20);

        self::assertSame(0.0, $type->fixAmountRetriever(new Product()));
    }

    #[DataProvider('percentAndExpectedTax')]
    public function testCalculateMultipliesUntaxedPriceByFactor(
        float $percent,
        float $untaxedPrice,
        float $expected,
    ): void {
        $type = (new PricePercentTaxType())->setPercentage($percent);

        self::assertEqualsWithDelta($expected, $type->calculate(new Product(), $untaxedPrice), 1e-6);
    }

    /**
     * @return iterable<string, array{float, float, float}>
     */
    public static function percentAndExpectedTax(): iterable
    {
        yield 'zero percent' => [0.0, 100.0, 0.0];
        yield '20 on 100' => [20.0, 100.0, 20.0];
        yield '5.5 on 100' => [5.5, 100.0, 5.5];
        yield '10 on 49.90' => [10.0, 49.90, 4.99];
        yield 'zero untaxed' => [20.0, 0.0, 0.0];
    }
}
