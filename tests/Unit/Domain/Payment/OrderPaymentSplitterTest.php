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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Payment\DTO\InstantShare;
use Thelia\Domain\Payment\DTO\PaymentComposition;
use Thelia\Domain\Payment\DTO\PlannedPaymentPart;
use Thelia\Domain\Payment\Exception\PaymentCompositionRefusedException;
use Thelia\Domain\Payment\OrderPaymentSplitter;

final class OrderPaymentSplitterTest extends TestCase
{
    private OrderPaymentSplitter $splitter;

    protected function setUp(): void
    {
        // The checkout exceptions translate their message, which needs the singleton translator.
        new Translator(new RequestStack());
        $this->splitter = new OrderPaymentSplitter();
    }

    public function testInstantShareThenProviderChargesTheBalance(): void
    {
        $composition = $this->splitter->split('100.000000', [new InstantShare(7, '30')], 3, true);

        self::assertEquals([
            new PlannedPaymentPart(7, '30.000000', false, 0, true),
            new PlannedPaymentPart(3, '70.000000', true, 1, false),
        ], $composition->parts);
        self::assertSame('70.000000', $composition->balance);
    }

    /**
     * Rounded once, to the cent, half up on the digits as given: 0.0049995 is under half a
     * cent and is nothing, whatever its seventh decimal.
     */
    #[DataProvider('amountsAndTheirCent')]
    public function testAnAmountIsRoundedToTheCentOnce(string $applied, string $expected): void
    {
        $composition = $this->splitter->split('100.000000', [new InstantShare(7, $applied)], 3, true);

        self::assertSame($expected, $composition->parts[0]->amount ?? '0.000000');
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function amountsAndTheirCent(): iterable
    {
        yield 'just under half a cent, seven decimals' => ['0.0049995', '0.000000'];
        yield 'just under half a cent, many decimals' => ['10.00499999999', '10.000000'];
        yield 'half a cent' => ['10.005', '10.010000'];
        yield 'just above a whole cent' => ['10.0100001', '10.010000'];
        yield 'nine tenths of a cent' => ['0.009', '0.010000'];
    }

    public function testShareAboveTheTotalIsBoundedAndBecomesPrimary(): void
    {
        $composition = $this->splitter->split('100.000000', [new InstantShare(7, '120.00')], 3, true);

        self::assertEquals([new PlannedPaymentPart(7, '100.000000', true, 0, true)], $composition->parts);
        self::assertSame('0.000000', $composition->balance);
    }

    public function testProviderThatCannotChargeTheBalanceIsRefused(): void
    {
        $this->assertRefused(
            PaymentCompositionRefusedException::PROVIDER_CANNOT_CHARGE_BALANCE,
            fn () => $this->splitter->split('100.000000', [new InstantShare(7, '30')], 3, false),
        );
    }

    public function testRemainderWithoutProviderIsRefused(): void
    {
        $this->assertRefused(
            PaymentCompositionRefusedException::REMAINDER_WITHOUT_PROVIDER,
            fn () => $this->splitter->split('100.000000', [new InstantShare(7, '30')], null, true),
        );
    }

    public function testNoShareGivesAnEmptyCompositionWithTheWholeTotalAsBalance(): void
    {
        $composition = $this->splitter->split('100.000000', [], 3, false);

        self::assertTrue($composition->isEmpty());
        self::assertSame('100.000000', $composition->balance);
    }

    #[DataProvider('nothingApplied')]
    public function testSharesAppliedNothingAreIgnored(string $amount): void
    {
        $composition = $this->splitter->split('100.000000', [new InstantShare(7, $amount)], 3, false);

        self::assertTrue($composition->isEmpty());
        self::assertSame('100.000000', $composition->balance);
    }

    /** @return iterable<string, array{string}> */
    public static function nothingApplied(): iterable
    {
        yield 'zero' => ['0'];
        yield 'zero with decimals' => ['0.000000'];
        yield 'negative' => ['-5.00'];
        yield 'below half a cent' => ['0.004'];
    }

    public function testZeroShareIsSkippedAmongOthersAndPositionsStayContiguous(): void
    {
        $composition = $this->splitter->split(
            '100.000000',
            [new InstantShare(7, '0'), new InstantShare(8, '25'), new InstantShare(9, '10')],
            3,
            true,
        );

        self::assertEquals([
            new PlannedPaymentPart(8, '25.000000', false, 0, true),
            new PlannedPaymentPart(9, '10.000000', false, 1, true),
            new PlannedPaymentPart(3, '65.000000', true, 2, false),
        ], $composition->parts);
    }

    public function testSeveralSharesAreBoundedInReceivedOrderAndTheLastOneIsPrimary(): void
    {
        $composition = $this->splitter->split(
            '100.000000',
            [new InstantShare(7, '60'), new InstantShare(8, '60')],
            3,
            false,
        );

        self::assertEquals([
            new PlannedPaymentPart(7, '60.000000', false, 0, true),
            new PlannedPaymentPart(8, '40.000000', true, 1, true),
        ], $composition->parts);
        self::assertSame('0.000000', $composition->balance);
    }

    public function testShareAfterTheOrderIsCoveredIsDropped(): void
    {
        $composition = $this->splitter->split(
            '100.000000',
            [new InstantShare(7, '100'), new InstantShare(8, '10')],
            null,
            false,
        );

        self::assertEquals([new PlannedPaymentPart(7, '100.000000', true, 0, true)], $composition->parts);
    }

    public function testAmountsAreRoundedToTheCent(): void
    {
        $composition = $this->splitter->split('100.000000', [new InstantShare(7, '33.333333')], 3, true);

        self::assertEquals([
            new PlannedPaymentPart(7, '33.330000', false, 0, true),
            new PlannedPaymentPart(3, '66.670000', true, 1, false),
        ], $composition->parts);
        self::assertSame('66.670000', $composition->balance);
    }

    public function testAmountsAreNeverComputedAsFloats(): void
    {
        // 0.1 + 0.2 style trap: 19.99 + 0.01 must cover 20.00 exactly.
        $composition = $this->splitter->split('20', [new InstantShare(7, '19.99'), new InstantShare(8, '0.01')], null, false);

        self::assertSame('0.000000', $composition->balance);
        self::assertSame('0.010000', $composition->parts[1]->amount);
    }

    public function testNonDecimalAmountIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->splitter->split('100', [new InstantShare(7, 'abc')], 3, true);
    }

    public function testCompositionOfOnePartIsNotEmpty(): void
    {
        self::assertFalse((new PaymentComposition([new PlannedPaymentPart(1, '1.000000', true, 0, true)], '0.000000'))->isEmpty());
    }

    private function assertRefused(string $reason, callable $call): void
    {
        try {
            $call();
        } catch (PaymentCompositionRefusedException $exception) {
            self::assertSame($reason, $exception->reason());
            self::assertStringNotContainsString('30', $exception->getMessage());

            return;
        }

        self::fail('The composition should have been refused.');
    }
}
