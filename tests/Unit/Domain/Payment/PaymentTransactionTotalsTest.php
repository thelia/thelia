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
use Thelia\Domain\Payment\DTO\PaymentTransactionTotals;

final class PaymentTransactionTotalsTest extends TestCase
{
    public function testWhatIsLeftToCaptureIsTheAuthorizationMinusWhatWasTakenOrReleased(): void
    {
        $totals = new PaymentTransactionTotals(authorized: '120.000000', captured: '50.000000', voided: '0.000000', refunded: '0.000000');

        self::assertTrue($totals->hasAuthorization());
        self::assertSame('70.000000', $totals->remainingToCapture);
        self::assertTrue($totals->hasSomethingLeftToCapture());
        self::assertTrue($totals->allows('70.000000'));
        self::assertFalse($totals->allows('70.000001'));
    }

    public function testAVoidReleasesTheRemainder(): void
    {
        $totals = new PaymentTransactionTotals(authorized: '120.000000', captured: '50.000000', voided: '70.000000', refunded: '0.000000');

        self::assertSame('0.000000', $totals->remainingToCapture);
        self::assertFalse($totals->hasSomethingLeftToCapture());
    }

    public function testAnOrderPaidAtOnceHasNothingLeftToCapture(): void
    {
        $totals = new PaymentTransactionTotals(authorized: '0.000000', captured: '120.000000', voided: '0.000000', refunded: '0.000000');

        self::assertFalse($totals->hasAuthorization());
        self::assertSame('0.000000', $totals->remainingToCapture);
        self::assertFalse($totals->hasSomethingLeftToCapture());
    }

    public function testTheRemainderNeverGoesBelowZero(): void
    {
        $totals = new PaymentTransactionTotals(authorized: '100.000000', captured: '120.000000', voided: '0.000000', refunded: '0.000000');

        self::assertSame('0.000000', $totals->remainingToCapture);
    }

    public function testNetCapturedIsWhatWasTakenAndNotGivenBack(): void
    {
        $totals = new PaymentTransactionTotals(authorized: '0.000000', captured: '120.000000', voided: '0.000000', refunded: '20.000000');

        self::assertSame('100.000000', $totals->netCaptured());
    }

    public function testEmptyTotalsReadZeroEverywhere(): void
    {
        $totals = PaymentTransactionTotals::empty();

        self::assertSame('0.000000', $totals->authorized);
        self::assertSame('0.000000', $totals->captured);
        self::assertSame('0.000000', $totals->remainingToCapture);
        self::assertFalse($totals->hasAuthorization());
    }
}
