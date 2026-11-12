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

    public function testTheWordsOfTheProviderAreKeptOnOneReadableLineOfBoundedLength(): void
    {
        // The message is shown in a flash, written to the administration log and answered by
        // the admin API: what a provider sent cannot start a line nor reorder the text there.
        $refusal = new PaymentRefusedException("Declined\n by the\u{202E} issuer ".str_repeat('x', 3000));

        self::assertStringStartsWith('Declined by the? issuer', $refusal->getMessage());
        self::assertStringNotContainsString("\n", $refusal->getMessage());
        self::assertSame(PaymentRefusedException::MESSAGE_MAX_LENGTH, mb_strlen($refusal->getMessage()));
        self::assertSame('Declined', PaymentRefusedException::withProviderCode("Declined\n", '05')->getMessage());
    }

    public function testACodeLongerThanTheJournalHoldsIsCut(): void
    {
        $code = PaymentRefusedException::withProviderCode('Declined', str_repeat('X', 80))->getProviderCode();

        self::assertSame(str_repeat('X', PaymentRefusedException::PROVIDER_CODE_MAX_LENGTH), $code);
    }
}
