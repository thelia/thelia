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

namespace Thelia\Tests\Unit\Domain\Payment;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Payment\Service\CurrencyMinorUnit;

final class CurrencyMinorUnitTest extends TestCase
{
    public function testTheDecimalsOfACurrencyComeFromItsIsoCode(): void
    {
        self::assertSame(2, CurrencyMinorUnit::decimalsOf('EUR'));
        self::assertSame(0, CurrencyMinorUnit::decimalsOf('JPY'));
        self::assertSame(3, CurrencyMinorUnit::decimalsOf('KWD'));
    }

    public function testAnUnknownOrMissingCodeFallsBackOnTwoDecimals(): void
    {
        self::assertSame(2, CurrencyMinorUnit::decimalsOf(null));
        self::assertSame(2, CurrencyMinorUnit::decimalsOf(''));
    }

    public function testAnAmountFitsWhenItHasNoMoreDecimalsThanTheCurrency(): void
    {
        self::assertTrue(CurrencyMinorUnit::fits(10.01, 'EUR'));
        self::assertTrue(CurrencyMinorUnit::fits('10.010000', 'EUR'));
        self::assertFalse(CurrencyMinorUnit::fits(10.005, 'EUR'));
        self::assertFalse(CurrencyMinorUnit::fits(10.5, 'JPY'));
        self::assertTrue(CurrencyMinorUnit::fits(1000, 'JPY'));
    }

    public function testARemainderBelowTheSmallestCoinIsNothingLeft(): void
    {
        self::assertTrue(CurrencyMinorUnit::isBelowSmallestCoin('0.005000', 'EUR'));
        self::assertFalse(CurrencyMinorUnit::isBelowSmallestCoin('0.010000', 'EUR'));
        self::assertTrue(CurrencyMinorUnit::isBelowSmallestCoin('0.000000', 'EUR'));
        self::assertTrue(CurrencyMinorUnit::isBelowSmallestCoin('0.900000', 'JPY'));
    }
}
