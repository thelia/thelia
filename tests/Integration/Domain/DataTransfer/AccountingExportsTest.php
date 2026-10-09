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

namespace Thelia\Tests\Integration\Domain\DataTransfer;

use Propel\Runtime\Propel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Domain\Accounting\AccountingChart;
use Thelia\Domain\DataTransfer\Export\ReportingExportInterface;
use Thelia\Domain\DataTransfer\Export\Type\SalesJournalExport;
use Thelia\Domain\DataTransfer\Export\Type\TaxSummaryExport;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\ExportQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatus;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The sales journal and the tax summary of a period: the invoices of the period by their
 * invoice date, booked balanced, the taxes of the summary being those of the journal.
 */
final class AccountingExportsTest extends ActionIntegrationTestCase
{
    private const FEC_FIELDS = ['JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate', 'CompteNum', 'CompteLib', 'CompAuxNum', 'CompAuxLib', 'PieceRef', 'PieceDate', 'EcritureLib', 'Debit', 'Credit', 'EcritureLet', 'DateLet', 'ValidDate', 'Montantdevise', 'Idevise'];

    protected function tearDown(): void
    {
        parent::tearDown();
        ConfigQuery::resetCache();
    }

    public function testWithoutAChartOfAccountsTheJournalRefusesAndSaysWhatIsMissing(): void
    {
        $this->chart('', '', '');

        $this->expectExceptionMessage('the customer account');

        $this->rows(new SalesJournalExport(), '2026-02');
    }

    public function testTheJournalOfAMonthBooksTheInvoicesOfThatMonthBalanced(): void
    {
        $this->chart('411000', '708500', '20:706200:445720,5.5:706055:445705');
        $february = $this->invoicedOrder('2026-02-10', [[100.0, 1, 20.0], [10.0, 3, 0.55]], postage: 12.0, postageTax: 2.0);
        $createdInJanuary = $this->invoicedOrder('2026-02-03', [[50.0, 1, 10.0]]);
        $createdInJanuary->setCreatedAt(new \DateTime('2026-01-28'))->save();
        $this->invoicedOrder('2026-03-01', [[50.0, 1, 10.0]]);

        $export = new SalesJournalExport();
        $rows = $this->mine($this->rows($export, '2026-02'), [$february, $createdInJanuary]);

        self::assertSame(self::FEC_FIELDS, array_keys($rows[0]));
        self::assertSame([(string) $createdInJanuary->getInvoiceRef(), (string) $february->getInvoiceRef()], array_values(array_unique(array_column($rows, 'PieceRef'))), 'By invoice date; the order placed in January is in the February journal.');
        self::assertSame($this->sum($rows, 'Debit'), $this->sum($rows, 'Credit'));
        $februaryRows = $this->mine($rows, [$february]);
        self::assertSame('2026-02-10', $februaryRows[0]['EcritureDate']);
        self::assertSame('411000', $februaryRows[0]['CompteNum']);
        self::assertSame((string) $february->getCustomer()->getRef(), $februaryRows[0]['CompAuxNum']);
        self::assertSame('', $februaryRows[1]['CompAuxNum'], 'The customer is named on the customer line only.');
        self::assertCount(2, array_filter($rows, static fn (array $row): bool => \in_array($row['CompteNum'], ['706200', '706055'], true) && (string) $february->getInvoiceRef() === $row['PieceRef']), 'Two rates, two product lines.');
        self::assertInstanceOf(ReportingExportInterface::class, $export);
    }

    public function testOrdersLeftOutAreNamedInTheReport(): void
    {
        $this->chart('411000', '708500', '20:706200:445720');
        $unmapped = $this->invoicedOrder('2026-02-11', [[100.0, 1, 10.0]]);
        $withoutReference = $this->invoicedOrder('2026-02-12', [[100.0, 1, 20.0]]);
        $withoutReference->setInvoiceRef(null)->save();

        $export = new SalesJournalExport();
        $rows = $this->rows($export, '2026-02');
        $report = implode("\n", [...$export->report()->lines(), ...$export->report()->warnings()]);

        self::assertSame([], $this->mine($rows, [$unmapped]));
        self::assertStringContainsString((string) $unmapped->getRef(), $report);
        self::assertStringContainsString('10.00%', $report);
        self::assertMatchesRegularExpression('/[1-9]\d* orders? of the period ha(?:s|ve) an invoice date but no invoice reference/', $report);
        self::assertStringContainsString('credit note', $report);
    }

    public function testTheTaxSummaryAddsUpTheTaxesOfTheJournal(): void
    {
        $this->chart('411000', '708500', '20:706200:445720,5.5:706055:445705');
        $this->invoicedOrder('2026-02-10', [[100.0, 1, 20.0], [10.0, 3, 0.55]], postage: 12.0, postageTax: 2.0);
        $this->invoicedOrder('2026-02-20', [[33.33, 3, 6.67]], discount: 5.0);

        $journal = $this->rows(new SalesJournalExport(), '2026-02');
        $summary = $this->rows(new TaxSummaryExport(), '2026-02');

        $taxCredits = $this->sum(array_filter($journal, static fn (array $row): bool => \in_array($row['CompteNum'], ['445720', '445705'], true)), 'Credit');
        self::assertSame($taxCredits, $this->sum($summary, 'tax_amount'));
        self::assertSame(['period', 'rate', 'taxable_amount', 'tax_amount', 'pieces'], array_keys($summary[0]));
        self::assertSame('2026-02', $summary[0]['period']);
    }

    public function testTheLastSecondOfThePeriodIsInItAndTheNextOneIsNot(): void
    {
        $this->chart('411000', '708500', '20:706200:445720');
        $last = $this->invoicedOrder('2026-02-28', [[100.0, 1, 20.0]]);
        $last->setInvoiceDate(new \DateTime('2026-02-28 23:59:59'))->save();
        $next = $this->invoicedOrder('2026-03-01', [[100.0, 1, 20.0]]);
        $next->setInvoiceDate(new \DateTime('2026-03-01 00:00:00'))->save();
        $empty = $this->invoicedOrder('2026-02-15', [[100.0, 1, 20.0]]);
        $empty->setInvoiceRef('')->save();

        $export = new SalesJournalExport();
        $rows = $this->rows($export, '2026-02');

        self::assertNotSame([], $this->mine($rows, [$last]));
        self::assertSame([], $this->mine($rows, [$next]));
        self::assertSame([], array_filter($rows, static fn (array $row): bool => '' === $row['PieceRef']));
    }

    public function testMoreThanABatchOfInvoicesIsBooked(): void
    {
        $this->chart('411000', '708500', '20:706200:445720');
        $orders = [];

        for ($i = 0; $i < 205; ++$i) {
            $orders[] = $this->invoicedOrder('2026-02-1'.($i % 9), [[10.0, 1, 2.0]]);
        }

        // The shop runs with the instance pool on, which the tests turn off.
        OrderTableMap::clearInstancePool();
        OrderProductTableMap::clearInstancePool();
        Propel::enableInstancePooling();

        try {
            $rows = $this->mine($this->rows(new SalesJournalExport(), '2026-02'), $orders);
            $pooledLines = \count(OrderProductTableMap::$instances);
            $pooledOrders = \count(OrderTableMap::$instances);
        } finally {
            Propel::disableInstancePooling();
            OrderTableMap::clearInstancePool();
            OrderProductTableMap::clearInstancePool();
        }

        self::assertCount(205, array_unique(array_column($rows, 'PieceRef')));
        self::assertSame(0, $pooledLines, 'The models of each batch are let go.');
        self::assertSame(0, $pooledOrders);
    }

    public function testTheReportSpeaksTheLanguageOfTheExport(): void
    {
        $this->chart('411000', '708500', '20:706200:445720');
        $this->invoicedOrder('2026-02-11', [[100.0, 1, 10.0]]);

        $export = new SalesJournalExport();
        $this->rows($export, '2026-02', 'fr_FR');

        self::assertStringContainsString('Commande', implode("\n", $export->report()->warnings()));
    }

    public function testTheTaxSummaryListsTheRatesOfAMonthHighestFirst(): void
    {
        $this->chart('411000', '708500', '20:706200:445720,2.1:706021:445721');
        $this->invoicedOrder('2026-02-10', [[100.0, 1, 20.0], [10.0, 3, 0.21]]);

        $summary = array_values(array_filter($this->rows(new TaxSummaryExport(), '2026-02'), static fn (array $row): bool => '2026-02' === $row['period']));

        self::assertSame(['20.00', '2.10'], array_column($summary, 'rate'), 'By number, not by text: 2.1 sorts after 20 as text.');
    }

    public function testAnEmptyPeriodStillGivesTheFieldNames(): void
    {
        $this->chart('411000', '708500', '20:706200:445720');

        $tester = new CommandTester((new Application(self::$kernel))->find('export'));
        $tester->execute(['ref' => 'thelia.export.sales_journal', 'serializer' => 'thelia.fec', '--start' => '1990-01-01', '--end' => '1990-01-31']);

        self::assertSame(1, preg_match('/(\S+\.txt)/', $tester->getDisplay(), $file));
        self::assertSame(implode("\t", self::FEC_FIELDS)."\r\n", (string) file_get_contents($file[1]));
    }

    public function testBothExportsAreInTheCatalogue(): void
    {
        self::assertSame(SalesJournalExport::class, ExportQuery::create()->findOneByRef('thelia.export.sales_journal')?->getHandleClass());
        self::assertSame(TaxSummaryExport::class, ExportQuery::create()->findOneByRef('thelia.export.tax_summary')?->getHandleClass());
    }

    public function testTheCommandWritesTheFecFileAndPrintsTheReport(): void
    {
        $this->chart('411000', '708500', '20:706200:445720');
        $this->invoicedOrder('2026-02-10', [[100.0, 1, 20.0]]);

        $tester = new CommandTester((new Application(self::$kernel))->find('export'));
        $tester->execute(['ref' => 'thelia.export.sales_journal', 'serializer' => 'thelia.fec', '--start' => '2026-02-01', '--end' => '2026-02-28']);

        $tester->assertCommandIsSuccessful();
        self::assertMatchesRegularExpression('/[1-9]\d* pieces?/', $tester->getDisplay());
        self::assertSame(1, preg_match('/(\S+\.txt)/', $tester->getDisplay(), $file));
        self::assertStringStartsWith(implode("\t", self::FEC_FIELDS)."\r\n", (string) file_get_contents($file[1]));
    }

    private function chart(string $customerAccount, string $shippingAccount, string $rateAccounts): void
    {
        ConfigQuery::write(AccountingChart::CUSTOMER_ACCOUNT_KEY, $customerAccount);
        ConfigQuery::write(AccountingChart::SHIPPING_ACCOUNT_KEY, $shippingAccount);
        ConfigQuery::write(AccountingChart::RATE_ACCOUNTS_KEY, $rateAccounts);
    }

    /**
     * @param list<array{float, int, float}> $lines unit price, quantity, unit tax
     */
    private function invoicedOrder(string $invoiceDate, array $lines, float $postage = 0.0, float $postageTax = 0.0, float $discount = 0.0): Order
    {
        $order = $this->factory->order(null, ['postage' => $postage, 'postageTax' => $postageTax, 'statusCode' => OrderStatus::CODE_PAID]);
        $order
            ->setCurrencyId((int) CurrencyQuery::create()->findOneByByDefault(1)->getId())
            ->setInvoiceRef('T172-'.$order->getId())
            ->setInvoiceDate(new \DateTime($invoiceDate.' 10:00:00'))
            ->setDiscount((string) $discount)
            ->save();

        foreach ($lines as [$unitPrice, $quantity, $unitTax]) {
            $line = (new OrderProduct())
                ->setOrderId($order->getId())
                ->setProductRef('REF-'.uniqid())
                ->setProductSaleElementsRef('PSE-'.uniqid())
                ->setProductSaleElementsId(0)
                ->setTitle('Line')
                ->setQuantity($quantity)
                ->setPrice((string) $unitPrice)
                ->setPromoPrice((string) $unitPrice)
                ->setWasNew(0)
                ->setWasInPromo(0);
            $line->save();
            (new OrderProductTax())->setOrderProductId($line->getId())->setTitle('VAT')->setAmount((string) $unitTax)->setPromoAmount((string) $unitTax)->save();
        }

        return $order;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(SalesJournalExport|TaxSummaryExport $export, string $month, ?string $locale = null): array
    {
        $export->setLang(null !== $locale ? LangQuery::create()->findOneByLocale($locale) : Lang::getDefaultLanguage());
        $start = new \DateTime($month.'-01 00:00:00');
        $export->setRangeDate(['start' => $start, 'end' => (clone $start)->modify('last day of this month 23:59:59')]);

        $rows = [];

        foreach ($export as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * The rows of these orders: the test database may hold other invoices of the period.
     *
     * @param list<array<string, mixed>> $rows
     * @param list<Order>                $orders
     *
     * @return list<array<string, mixed>>
     */
    private function mine(array $rows, array $orders): array
    {
        $refs = array_map(static fn (Order $order): string => (string) $order->getInvoiceRef(), $orders);

        return array_values(array_filter($rows, static fn (array $row): bool => \in_array($row['PieceRef'], $refs, true)));
    }

    /**
     * @param iterable<array<string, mixed>> $rows
     */
    private function sum(iterable $rows, string $column): int
    {
        $cents = 0;

        foreach ($rows as $row) {
            $cents += (int) round((float) $row[$column] * 100);
        }

        return $cents;
    }
}
