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
use Thelia\Domain\Payment\Exception\InvalidPaymentAmountException;
use Thelia\Domain\Payment\Service\PaymentAmount;

final class PaymentAmountTest extends TestCase
{
    public function testADecimalStringAndAFloatOfTheSameValueCompareEqual(): void
    {
        self::assertSame(0, PaymentAmount::compare('120.000000', 120.0));
        self::assertSame(0, PaymentAmount::compare(120, '120'));
    }

    public function testAFloatAHairUnderItsDecimalValueIsNotSmaller(): void
    {
        // 0.1 + 0.2 is 0.30000000000000004 as a float; at six decimals it is 0.3.
        self::assertSame(0, PaymentAmount::compare(0.1 + 0.2, '0.300000'));
        self::assertSame(0, PaymentAmount::compare(70.0 - 50.0 - 20.0, '0.000000'));
    }

    public function testNormalizeWritesSixDecimals(): void
    {
        self::assertSame('50.000000', PaymentAmount::normalize(50));
        self::assertSame('12.345679', PaymentAmount::normalize('12.3456789'));
        self::assertSame('0.000000', PaymentAmount::normalize(0.0));
    }

    public function testSubtractAndAddKeepTheColumnPrecision(): void
    {
        self::assertSame('70.000000', PaymentAmount::subtract('120.000000', 50.0));
        self::assertSame('0.000000', PaymentAmount::subtract('50.000000', '50.000000'));
        self::assertSame('-5.000000', PaymentAmount::subtract(45, 50));
        self::assertSame('120.000000', PaymentAmount::add('100.000000', 20));
    }

    public function testAnAmountBeyondWhatTheColumnHoldsIsRefusedRatherThanWrappedAround(): void
    {
        // Read as millionths in a 64-bit integer, this figure wraps around to 5.005312
        // and would pass any ceiling.
        $this->expectException(InvalidPaymentAmountException::class);

        PaymentAmount::compare(18446744073714.553, '100.000000');
    }

    public function testANonFiniteAmountIsRefused(): void
    {
        $this->expectException(InvalidPaymentAmountException::class);

        PaymentAmount::normalize(\INF);
    }

    public function testTheLargestAmountTheColumnHoldsIsAccepted(): void
    {
        self::assertSame('9999999999.999999', PaymentAmount::normalize('9999999999.999999'));
    }

    public function testIsPositiveIgnoresNoiseBelowAMillionth(): void
    {
        self::assertTrue(PaymentAmount::isPositive('0.000001'));
        self::assertFalse(PaymentAmount::isPositive(0.0000001));
        self::assertFalse(PaymentAmount::isPositive('-1'));
        self::assertFalse(PaymentAmount::isPositive(0));
    }
}
