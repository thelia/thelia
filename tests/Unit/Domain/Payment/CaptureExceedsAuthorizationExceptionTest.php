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
use Thelia\Domain\Payment\Exception\CaptureExceedsAuthorizationException;

final class CaptureExceedsAuthorizationExceptionTest extends TestCase
{
    public function testTheMerchantReadsTheAmountsAsAmountsAndTheCallerKeepsThemExact(): void
    {
        $exception = new CaptureExceedsAuthorizationException('ORD000000000059', '2000.000000', '1022.800000');

        self::assertSame('Order ORD000000000059: a capture of 2000.00 exceeds the 1022.80 its authorization still holds.', $exception->getMessage());
        self::assertSame('2000.000000', $exception->getRequestedAmount());
        self::assertSame('1022.800000', $exception->getRemainingToCapture());
    }
}
