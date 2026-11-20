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

namespace Thelia\Tests\Unit\Domain\Company\Checkout;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Company\Checkout\Service\SpendingAmount;

/**
 * Amounts are added as DECIMAL strings: a float would turn 0.1 + 0.2 into a different limit.
 */
final class SpendingAmountTest extends TestCase
{
    #[DataProvider('sums')]
    public function testAmountsAreAddedWithoutAFloat(string $left, string $right, string $sum): void
    {
        self::assertSame($sum, SpendingAmount::add($left, $right));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function sums(): iterable
    {
        yield 'as stored' => ['1800.000000', '150.000000', '1950.000000'];
        yield 'whole amounts' => ['1800', '300', '2100.000000'];
        yield 'cents' => ['0.1', '0.2', '0.300000'];
        yield 'carry over the decimals' => ['0.999999', '0.000001', '1.000000'];
        yield 'nothing spent' => ['0.000000', '600', '600.000000'];
        yield 'the widest the column holds' => ['9999999999.000000', '0.999999', '9999999999.999999'];
    }

    #[DataProvider('displays')]
    public function testAnAmountIsWrittenAsABuyerReadsIt(string $amount, string $written): void
    {
        self::assertSame($written, SpendingAmount::display($amount));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function displays(): iterable
    {
        yield 'whole, as stored' => ['500.000000', '500'];
        yield 'whole, as typed' => ['500', '500'];
        yield 'zero' => ['0.000000', '0'];
        yield 'cents' => ['12.500000', '12.50'];
        yield 'two decimals' => ['12.340000', '12.34'];
        yield 'a hundred stays a hundred' => ['100.000000', '100'];
        yield 'beyond the cents' => ['0.123456', '0.123456'];
    }

    public function testAFloatTotalIsWrittenAsTheColumnHoldsIt(): void
    {
        self::assertSame('599.990000', SpendingAmount::fromFloat(599.99));
        self::assertSame('0.300000', SpendingAmount::fromFloat(0.1 + 0.2));
    }
}
