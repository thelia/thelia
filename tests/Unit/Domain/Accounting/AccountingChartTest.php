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

namespace Thelia\Tests\Unit\Domain\Accounting;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Domain\Accounting\AccountingChart;
use Thelia\Domain\Accounting\InvalidAccountingChartException;

final class AccountingChartTest extends TestCase
{
    public function testAChartReadsItsAccountsByRate(): void
    {
        $chart = AccountingChart::fromValues('VE', 'Ventes', '411000', '708500', '20:706200:445720, 5.5:706055:445705,0:706000');

        self::assertSame([], $chart->missing());
        self::assertSame(['product' => '706200', 'tax' => '445720'], $chart->accountsOf(AccountingChart::rateKey(20.0)));
        self::assertSame(['product' => '706055', 'tax' => '445705'], $chart->accountsOf(AccountingChart::rateKey(5.5)));
        self::assertSame(['product' => '706000', 'tax' => null], $chart->accountsOf(AccountingChart::rateKey(0.0)));
        self::assertNull($chart->accountsOf(AccountingChart::rateKey(10.0)));
        self::assertSame('20:706200:445720,5.5:706055:445705,0:706000', $chart->rateAccountsSetting());
    }

    public function testAnEmptyChartSaysWhatIsMissing(): void
    {
        $chart = AccountingChart::fromValues('', '', '', '', '');

        self::assertSame([AccountingChart::MISSING_CUSTOMER_ACCOUNT, AccountingChart::MISSING_SHIPPING_ACCOUNT, AccountingChart::MISSING_RATE_ACCOUNTS], $chart->missing());
        self::assertSame('VE', $chart->journalCode, 'The sales journal has a default code.');
    }

    public function testRatesAreKeyedToTheHundredthOfAPercent(): void
    {
        self::assertSame('20.00', AccountingChart::rateKey(19.996));
        self::assertSame('5.50', AccountingChart::rateKey(5.5));
        self::assertSame('0.00', AccountingChart::rateKey(0.0));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rates(): iterable
    {
        yield 'ten' => ['10:706100:445710', '10.00'];
        yield 'a hundred' => ['100:706999:445999', '100.00'];
        yield 'a half' => ['0.5:706005:445705', '0.50'];
        yield 'five and a half' => ['5.5:706055:445705', '5.50'];
    }

    #[DataProvider('rates')]
    public function testARateIsWrittenBackAsTheMerchantTypedIt(string $setting, string $key): void
    {
        $chart = AccountingChart::fromValues('VE', 'Ventes', '411000', '708500', $setting);

        self::assertSame($setting, $chart->rateAccountsSetting());
        self::assertNotNull($chart->accountsOf($key));
    }

    public function testARateIsFoundFromAnAmountAndItsRoundedTax(): void
    {
        $chart = AccountingChart::fromValues('VE', 'Ventes', '411000', '708500', '20:706200:445720,5.5:706055:445705,0:706000');

        self::assertSame('20.00', $chart->rateFor(4.17, 0.83), '19.90%, a tenth of a point off.');
        self::assertSame('20.00', $chart->rateFor(0.99, 0.20), '20.20%, but 0.198 rounds to 0.20.');
        self::assertSame('5.50', $chart->rateFor(1.00, 0.06), '6%, but 0.055 rounds to 0.06.');
        self::assertSame('0.00', $chart->rateFor(10.00, 0.0));
        self::assertNull($chart->rateFor(100.0, 10.0), '10% is no rate of this chart.');
    }

    public function testAJournalCodeOrLabelTheFileCannotCarryIsRefused(): void
    {
        foreach ([['V E', 'Ventes'], [str_repeat('V', 21), 'Ventes'], ['VE', str_repeat('x', 101)]] as [$code, $label]) {
            try {
                AccountingChart::fromValues($code, $label, '411000', '708500', '20:706200:445720');
                self::fail(\sprintf('"%s" / "%s" was accepted.', $code, $label));
            } catch (InvalidAccountingChartException) {
            }
        }

        self::assertSame('VE', AccountingChart::fromValues('VE', 'Ventes', '411000', '708500', '20:706200:445720')->journalCode);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRateAccounts(): iterable
    {
        yield 'no product account' => ['20'];
        yield 'a taxed rate without tax account' => ['20:706200'];
        yield 'a rate that is not a number' => ['twenty:706200:445720'];
        yield 'an account with spaces' => ['20:706 200:445720'];
        yield 'the same rate twice' => ['20:706200:445720,20.0:706201:445721'];
    }

    #[DataProvider('invalidRateAccounts')]
    public function testRateAccountsThatCannotBeReadAreRefused(string $setting): void
    {
        $this->expectException(InvalidAccountingChartException::class);

        AccountingChart::fromValues('VE', 'Ventes', '411000', '708500', $setting);
    }
}
