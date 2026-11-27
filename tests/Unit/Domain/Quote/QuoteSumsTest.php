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

namespace Thelia\Tests\Unit\Domain\Quote;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Quote\DTO\QuoteSumLine;
use Thelia\Domain\Quote\DTO\QuoteTotals;
use Thelia\Domain\Quote\Service\QuoteSums;

/**
 * What a revision comes to, on amounts already taxed: a rule of 20 % is a line whose taxed price
 * is the price times 1.2, and the tax on the postage is whatever the closure answers.
 */
final class QuoteSumsTest extends TestCase
{
    private const float VAT = 1.2;

    public function testTheSendingOfTheShopComesToSixteenHundredAndTwenty(): void
    {
        $totals = (new QuoteSums())->totals(
            [$this->line('90', 12.0), $this->line('45', 4.0), $this->line('20', 5.0)],
            false,
            30.0,
            15.0,
            static fn (float $postage): float => $postage * 0.2,
        );

        // (1 080 + 180 + 100 = 1 360 + 15 of postage) x 1.2 - 30
        self::assertEquals(new QuoteTotals('1360.000000', '272.000000', '15.000000', '3.000000', '30.000000', '1620.000000', '1350.000000'), $totals);
    }

    public function testANegotiatedPriceIsTaxedWithTheRuleOfItsLine(): void
    {
        $totals = (new QuoteSums())->totals(
            [$this->line('87', 12.0), $this->line('45', 4.0), $this->line('20', 5.0)],
            false,
            30.0,
            15.0,
            static fn (float $postage): float => $postage * 0.2,
        );

        // (1 044 + 180 + 100 = 1 324 + 15) x 1.2 - 30
        self::assertEquals(new QuoteTotals('1324.000000', '264.800000', '15.000000', '3.000000', '30.000000', '1576.800000', '1314.000000'), $totals);
    }

    public function testALineWithNoRuleIsTaxedAtItsOwnPrice(): void
    {
        $totals = (new QuoteSums())->totals([new QuoteSumLine(10.0, 10.0, 3.0), $this->line('10', 1.0)], false, 0.0, 0.0, static fn (): float => 0.0);

        self::assertSame('40.000000', $totals->itemsUntaxed);
        self::assertSame('2.000000', $totals->itemsTax, 'only the line that has a rule carries a tax');
        self::assertSame('42.000000', $totals->totalTaxed);
    }

    public function testTheDiscountNeverGoesAboveTheLinesWithTaxesNorBelowNothing(): void
    {
        $over = (new QuoteSums())->totals([$this->line('100', 1.0)], false, 5000.0, 10.0, static fn (float $postage): float => $postage * 0.2);

        self::assertSame('120.000000', $over->discount, 'the lines with taxes, no more');
        self::assertSame('12.000000', $over->totalTaxed, 'what is left is the postage and its tax');
        self::assertSame('10.000000', $over->totalUntaxed, 'the lines are all taken off, the postage stays');

        $none = (new QuoteSums())->totals([$this->line('100', 1.0)], false, 0.0, 0.0, static fn (): float => 0.0);

        self::assertSame('0.000000', $none->discount);
        self::assertSame('120.000000', $none->totalTaxed);
    }

    public function testTheShareOfTheDiscountThatIsNotTaxFollowsTheAverageRateOfTheLines(): void
    {
        $totals = (new QuoteSums())->totals(
            [$this->line('100', 1.0), new QuoteSumLine(100.0, 100.0, 1.0)],
            false,
            22.0,
            0.0,
            static fn (): float => 0.0,
        );

        // 200 without taxes, 220 with: a discount of 22 takes 20 off the untaxed side.
        self::assertSame(['200.000000', '20.000000'], [$totals->itemsUntaxed, $totals->itemsTax]);
        self::assertSame('180.000000', $totals->totalUntaxed);
        self::assertSame('198.000000', $totals->totalTaxed);
    }

    public function testTheLinesAreRoundedAsTheShopRoundsPerUnitOrOverTheSum(): void
    {
        $line = new QuoteSumLine(0.333, 0.3996, 3.0);
        $tax = static fn (): float => 0.0;

        $overTheSum = (new QuoteSums())->totals([$line], true, 0.0, 0.0, $tax);
        $perUnit = (new QuoteSums())->totals([$line], false, 0.0, 0.0, $tax);

        self::assertSame(['1.000000', '0.200000'], [$overTheSum->itemsUntaxed, $overTheSum->itemsTax], '0.999 and 1.1988 brought to the cent');
        self::assertSame(['0.990000', '0.210000'], [$perUnit->itemsUntaxed, $perUnit->itemsTax], '0.33 and 0.40 three times');
    }

    public function testThePostageIsBroughtToTheCentBeforeItIsTaxed(): void
    {
        $asked = [];

        $totals = (new QuoteSums())->totals([$this->line('100', 1.0)], false, 0.0, 10.126, static function (float $postage) use (&$asked): float {
            $asked[] = $postage;

            return round($postage * 0.2, 2);
        });

        self::assertSame([10.13], $asked, 'the tax is asked once, for the postage of the cent');
        self::assertSame('10.130000', $totals->postageUntaxed);
        self::assertSame('2.030000', $totals->postageTax);
        self::assertSame('132.160000', $totals->totalTaxed, '120 + 10.13 + 2.03');
    }

    public function testARevisionWithNoLineComesToItsPostage(): void
    {
        $totals = (new QuoteSums())->totals([], false, 30.0, 15.0, static fn (float $postage): float => $postage * 0.2);

        self::assertEquals(new QuoteTotals('0.000000', '0.000000', '15.000000', '3.000000', '0.000000', '18.000000', '15.000000'), $totals);
    }

    private function line(string $price, float $quantity): QuoteSumLine
    {
        return new QuoteSumLine((float) $price, (float) $price * self::VAT, $quantity);
    }
}
