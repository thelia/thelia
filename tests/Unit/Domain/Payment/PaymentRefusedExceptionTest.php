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
use Thelia\Domain\Payment\Exception\PaymentRefusedException;

final class PaymentRefusedExceptionTest extends TestCase
{
    public function testARefusalBuiltTheUsualWayCarriesNoCodeOfTheProvider(): void
    {
        self::assertNull((new PaymentRefusedException('Declined', 0))->getProviderCode());
    }

    public function testTheCodeOfTheProviderIsKeptWithTheMessageAndTheCause(): void
    {
        $cause = new \RuntimeException('raw answer');
        $refusal = PaymentRefusedException::withProviderCode('Declined', '51', $cause);

        self::assertSame('51', $refusal->getProviderCode());
        self::assertSame('Declined', $refusal->getMessage());
        self::assertSame($cause, $refusal->getPrevious());
    }

    public function testABlankCodeIsNoCode(): void
    {
        self::assertNull(PaymentRefusedException::withProviderCode('Declined', '  ')->getProviderCode());
        self::assertNull(PaymentRefusedException::withProviderCode('Declined', null)->getProviderCode());
    }

    public function testACodeLongerThanTheJournalHoldsIsCut(): void
    {
        $code = PaymentRefusedException::withProviderCode('Declined', str_repeat('X', 80))->getProviderCode();

        self::assertSame(str_repeat('X', PaymentRefusedException::PROVIDER_CODE_MAX_LENGTH), $code);
    }
}
