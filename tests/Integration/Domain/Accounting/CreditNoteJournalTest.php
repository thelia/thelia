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

namespace Thelia\Tests\Integration\Domain\Accounting;

use Propel\Runtime\Propel;
use Thelia\Domain\Accounting\AccountingChart;
use Thelia\Domain\Accounting\AccountingEntry;
use Thelia\Domain\Accounting\AccountingPiece;
use Thelia\Domain\Accounting\SalesJournal;
use Thelia\Domain\DataTransfer\Export\ExportReport;
use Thelia\Domain\DataTransfer\Export\Type\SalesJournalExport;
use Thelia\Domain\DataTransfer\Export\Type\TaxSummaryExport;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatus;
use Thelia\Test\ActionIntegrationTestCase;

/**
 * The credit notes of the CreditNote module in the sales journal: each numbered credit note
 * is a piece that reverses the sales it gives back, by tax rate, read from the tables of the
 * module while it is active. The module is not shipped with the core: its tables are
 * created for this class, with the columns of CreditNote 4.4.0, and dropped after it.
 */
final class CreditNoteJournalTest extends ActionIntegrationTestCase
{
    private const MONTH = '2030-07';

    private const STATUSES = ['proposed' => [1, 0], 'refused' => [2, 0], 'accepted' => [3, 1], 'used' => [4, 1]];

    private const TYPES = ['order_full_refund' => 1, 'back_product' => 2, 'billing_error' => 3, 'rebate' => 4, 'discount' => 5, 'difference_refund' => 6];

    private const TABLES = [
        'credit_note' => <<<'SQL'
            CREATE TABLE `credit_note` (
                `id` INTEGER NOT NULL AUTO_INCREMENT, `ref` VARCHAR(45), `invoice_ref` VARCHAR(45),
                `invoice_address_id` INTEGER NOT NULL, `invoice_date` DATETIME, `order_id` INTEGER,
                `customer_id` INTEGER NOT NULL, `parent_id` INTEGER, `type_id` INTEGER NOT NULL,
                `status_id` INTEGER NOT NULL, `currency_id` INTEGER NOT NULL, `currency_rate` FLOAT,
                `total_price` DECIMAL(16,6) DEFAULT 0.000000, `total_price_with_tax` DECIMAL(16,6) DEFAULT 0.000000,
                `discount_without_tax` DECIMAL(16,6) DEFAULT 0.000000, `discount_with_tax` DECIMAL(16,6) DEFAULT 0.000000,
                `allow_partial_use` TINYINT(1) DEFAULT 1, `lang_id` INTEGER, `created_at` DATETIME, `updated_at` DATETIME,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB
            SQL,
        'credit_note_detail' => <<<'SQL'
            CREATE TABLE `credit_note_detail` (
                `id` INTEGER NOT NULL AUTO_INCREMENT, `credit_note_id` INTEGER NOT NULL,
                `price` DECIMAL(16,6) DEFAULT 0.000000, `price_with_tax` DECIMAL(16,6) DEFAULT 0.000000,
                `tax_rule_id` INTEGER, `order_product_id` INTEGER, `type` VARCHAR(55),
                `quantity` INTEGER DEFAULT 0 NOT NULL, `title` VARCHAR(255), `created_at` DATETIME, `updated_at` DATETIME,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB
            SQL,
        'credit_note_status' => <<<'SQL'
            CREATE TABLE `credit_note_status` (
                `id` INTEGER NOT NULL AUTO_INCREMENT, `code` VARCHAR(45), `color` CHAR(7),
                `invoiced` TINYINT(1) DEFAULT 0 NOT NULL, `used` TINYINT(1) DEFAULT 0 NOT NULL,
                `position` INTEGER(11), `created_at` DATETIME, `updated_at` DATETIME,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB
            SQL,
        'credit_note_type' => <<<'SQL'
            CREATE TABLE `credit_note_type` (
                `id` INTEGER NOT NULL AUTO_INCREMENT, `code` VARCHAR(45), `color` CHAR(7), `position` INTEGER(11),
                `required_order` TINYINT(1) DEFAULT 0 NOT NULL, `created_at` DATETIME, `updated_at` DATETIME,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB
            SQL,
        'credit_note_address' => <<<'SQL'
            CREATE TABLE `credit_note_address` (
                `id` INTEGER NOT NULL AUTO_INCREMENT, `customer_title_id` INTEGER, `company` VARCHAR(255),
                `firstname` VARCHAR(255) NOT NULL, `lastname` VARCHAR(255) NOT NULL, `address1` VARCHAR(255) NOT NULL,
                `address2` VARCHAR(255), `address3` VARCHAR(255), `zipcode` VARCHAR(10) NOT NULL, `city` VARCHAR(255) NOT NULL,
                `phone` VARCHAR(20), `cellphone` VARCHAR(20), `country_id` INTEGER NOT NULL, `state_id` INTEGER,
                `created_at` DATETIME, `updated_at` DATETIME,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB
            SQL,
    ];

    private static bool $tablesCreated = false;

    private AccountingChart $chart;

    protected function setUp(): void
    {
        // The tables of the module, created before the transaction of the test: DDL would end it.
        if (!self::$tablesCreated) {
            self::bootKernel();
            self::createTheTablesOfTheModule();
            self::$tablesCreated = true;
        }

        parent::setUp();
        $this->chart = AccountingChart::fromValues('VE', 'Ventes', '411000', '708500', '20:706200:445720,5.5:706055:445705,0:706000');
        $this->activateTheModule(true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        ConfigQuery::resetCache();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tablesCreated) {
            $connection = Propel::getConnection('TheliaMain');

            foreach (array_keys(self::TABLES) as $table) {
                $connection->exec(\sprintf('DROP TABLE IF EXISTS `%s`', $table));
            }

            self::$tablesCreated = false;
        }

        parent::tearDownAfterClass();
    }

    public function testAFullCreditNoteOfATwoRateOrderReversesItsInvoice(): void
    {
        $order = $this->invoicedOrder('10', [[100.0, 1, 20.0], [10.0, 3, 0.55]], postage: 12.0, postageTax: 2.0);
        $creditNote = $this->creditNote('order_full_refund', 'accepted', '20', $order, $this->wholeOrder($order));

        $pieces = $this->pieces();
        $invoice = $this->pieceOf($pieces, (string) $order->getInvoiceRef());
        $credit = $this->pieceOf($pieces, $creditNote);

        self::assertTrue($credit->isCreditNote());
        self::assertSame(
            $this->reversed($invoice),
            $this->described($credit),
        );
    }

    public function testAPartialCreditNoteOfAProductAndTheShippingDebitsTheirRates(): void
    {
        $order = $this->invoicedOrder('10', [[100.0, 1, 20.0], [10.0, 3, 0.55]], postage: 12.0, postageTax: 2.0);
        $lines = $this->linesOf($order);
        $creditNote = $this->creditNote('back_product', 'accepted', '20', $order, [
            ['product', 10.0, 10.55, 1, $lines[1]],
            ['shipping', 10.0, 12.0, 1, null],
        ]);

        self::assertSame([
            'customer 411000 - D0 C2255',
            'product 706055 5.50 D1000 C0',
            'shipping 708500 20.00 D1000 C0',
            'tax 445720 20.00 D200 C0',
            'tax 445705 5.50 D55 C0',
        ], $this->described($this->pieceOf($this->pieces(), $creditNote)));
    }

    public function testTheDiscountOfACreditNoteIsUntaxedFromItsAmountWithTax(): void
    {
        $order = $this->invoicedOrder('10', [[100.0, 1, 20.0]], discount: 12.0);
        // The module copies the discount of the order to both fields: the one without tax is wrong.
        $creditNote = $this->creditNote('order_full_refund', 'accepted', '20', $order, $this->wholeOrder($order), discountWithoutTax: 12.0, discountWithTax: 12.0);

        self::assertSame([
            'customer 411000 - D0 C10800',
            'product 706200 20.00 D9000 C0',
            'tax 445720 20.00 D1800 C0',
        ], $this->described($this->pieceOf($this->pieces(), $creditNote)));
    }

    public function testACommercialCreditNoteWithoutOrderIsBookedAtTheRateItsAmountsMake(): void
    {
        $creditNote = $this->creditNote('rebate', 'used', '15', null, [['other', 50.0, 60.0, 1, null]]);

        self::assertSame([
            'customer 411000 - D0 C6000',
            'product 706200 20.00 D5000 C0',
            'tax 445720 20.00 D1000 C0',
        ], $this->described($this->pieceOf($this->pieces(), $creditNote)));
    }

    public function testOnlyACommercialCreditNoteGoesToTheAccountOfCommercialCreditNotes(): void
    {
        $this->chart = AccountingChart::fromValues('VE', 'Ventes', '411000', '708500', '20:706200:445720,5.5:706055:445705,0:706000', '709000');
        $order = $this->invoicedOrder('10', [[100.0, 1, 20.0]]);
        $rebate = $this->creditNote('rebate', 'accepted', '15', null, [['other', 50.0, 60.0, 1, null]]);
        $backProduct = $this->creditNote('back_product', 'accepted', '15', $order, $this->wholeOrder($order));
        $pieces = $this->pieces();

        self::assertContains('product 709000 20.00 D5000 C0', $this->described($this->pieceOf($pieces, $rebate)));
        self::assertContains('product 706200 20.00 D10000 C0', $this->described($this->pieceOf($pieces, $backProduct)));
    }

    public function testACreditNoteWhoseTotalIsNotItsLinesStillBalancesAndIsNamedInTheReport(): void
    {
        $creditNote = $this->creditNote('rebate', 'accepted', '15', null, [['other', 50.0, 60.0, 1, null]], totalWithTax: 61.0);
        $report = new ExportReport();

        $piece = $this->pieceOf($this->pieces($report), $creditNote);

        self::assertSame([
            'customer 411000 - D0 C6100',
            'product 706200 20.00 D5100 C0',
            'tax 445720 20.00 D1000 C0',
        ], $this->described($piece));
        self::assertStringContainsString('Credit note '.$creditNote.': its total is 1.00 away from its lines', implode("\n", $report->warnings()));
    }

    public function testACreditNoteNeitherAcceptedNorUsedOrNotNumberedIsLeftOut(): void
    {
        $order = $this->invoicedOrder('10', [[100.0, 1, 20.0]]);
        $proposed = $this->creditNote('order_full_refund', 'proposed', '12', $order, $this->wholeOrder($order));
        $refused = $this->creditNote('order_full_refund', 'refused', '12', $order, $this->wholeOrder($order));
        $notNumbered = $this->creditNote('order_full_refund', 'accepted', '12', $order, $this->wholeOrder($order), numbered: false);

        $refs = array_map(static fn (AccountingPiece $piece): string => $piece->invoiceRef, $this->pieces());

        self::assertNotContains($proposed, $refs);
        self::assertNotContains($refused, $refs);
        self::assertNotContains($notNumbered, $refs);
    }

    public function testWhileTheModuleIsInactiveItsCreditNotesAreLeftOutAndTheReportSaysSo(): void
    {
        $order = $this->invoicedOrder('10', [[100.0, 1, 20.0]]);
        $creditNote = $this->creditNote('order_full_refund', 'accepted', '20', $order, $this->wholeOrder($order));
        $this->activateTheModule(false);
        $report = new ExportReport();

        $refs = array_map(static fn (AccountingPiece $piece): string => $piece->invoiceRef, $this->pieces($report));

        self::assertNotContains($creditNote, $refs);
        self::assertStringContainsString('No credit note module', implode("\n", $report->warnings()));
    }

    public function testTheReportCountsTheCreditNotesAndNoLongerSaysTheyAreLeftOut(): void
    {
        $order = $this->invoicedOrder('10', [[100.0, 1, 20.0]]);
        $this->creditNote('order_full_refund', 'accepted', '20', $order, $this->wholeOrder($order));
        $report = new ExportReport();

        $this->pieces($report);

        self::assertMatchesRegularExpression('/[1-9]\d* credit notes?\b/', implode("\n", $report->lines()));
        self::assertStringNotContainsString('not in this journal', implode("\n", $report->warnings()));
    }

    public function testTheJournalIsInDateOrderAndNamesTheCreditNote(): void
    {
        $order = $this->invoicedOrder('10', [[100.0, 1, 20.0]]);
        $creditNote = $this->creditNote('order_full_refund', 'accepted', '05', $order, $this->wholeOrder($order));
        $later = $this->invoicedOrder('25', [[50.0, 1, 10.0]]);

        $rows = $this->rows(new SalesJournalExport());
        $refs = array_values(array_intersect(array_unique(array_column($rows, 'PieceRef')), [(string) $order->getInvoiceRef(), $creditNote, (string) $later->getInvoiceRef()]));

        self::assertSame([$creditNote, (string) $order->getInvoiceRef(), (string) $later->getInvoiceRef()], $refs);
        $creditRows = array_values(array_filter($rows, static fn (array $row): bool => $creditNote === $row['PieceRef']));
        self::assertStringStartsWith('Credit note '.$creditNote, $creditRows[0]['EcritureLib']);
        self::assertSame('0.00', $creditRows[0]['Debit']);
        self::assertSame('120.00', $creditRows[0]['Credit']);
    }

    public function testTheTaxSummaryOfAMonthTakesTheCreditNotesOff(): void
    {
        $this->invoicedOrder('10', [[100.0, 1, 20.0]]);
        $this->creditNote('rebate', 'accepted', '20', null, [['other', 25.0, 30.0, 1, null]]);
        $ours = static fn (array $row): bool => '20.00' === $row['rate'];

        $rows = array_values(array_filter($this->rows(new TaxSummaryExport()), $ours));

        self::assertCount(1, $rows);
        self::assertSame('75.00', $rows[0]['taxable_amount'], 'Invoice 100.00, credit note 25.00.');
        self::assertSame('15.00', $rows[0]['tax_amount']);
        self::assertSame(2, $rows[0]['pieces']);
    }

    public function testTheSummaryOfTheJournalCountsTheCreditNotesAndLeavesItsRowsAlone(): void
    {
        $order = $this->invoicedOrder('10', [[100.0, 1, 20.0]]);
        $this->creditNote('rebate', 'accepted', '20', null, [['other', 25.0, 30.0, 1, null]]);
        $expected = $this->rows(new SalesJournalExport());

        $export = new SalesJournalExport();
        $this->configured($export);
        $export->rewind();
        $export->next();
        $summary = $export->summarize();
        $rows = [];

        while ($export->valid()) {
            $rows[] = $export->current();
            $export->next();
        }

        self::assertSame(['Pieces', 'Credit notes among them', 'Total debit', 'Total credit'], array_column($summary, 'label'));
        self::assertSame('2', $summary[0]->value);
        self::assertSame('1', $summary[1]->value);
        self::assertStringContainsString('150.00', $summary[2]->value);
        self::assertStringContainsString('150.00', $summary[3]->value);
        self::assertSame(\count($expected) - 1, \count($rows), 'The summary did not move the rows of the export.');
        self::assertContains((string) $order->getInvoiceRef(), array_column($expected, 'PieceRef'));
    }

    public function testTheSummaryOfTheTaxSummaryTakesTheCreditNotesOff(): void
    {
        $this->invoicedOrder('10', [[100.0, 1, 20.0]]);
        $this->creditNote('rebate', 'accepted', '20', null, [['other', 25.0, 30.0, 1, null]]);
        $this->rows(new TaxSummaryExport());

        $export = new TaxSummaryExport();
        $this->configured($export);
        $summary = $export->summarize();

        self::assertSame(['Taxable amount', 'Tax collected', 'Pieces'], array_column($summary, 'label'));
        self::assertStringContainsString('75.00', $summary[0]->value);
        self::assertStringContainsString('15.00', $summary[1]->value);
        self::assertSame('2', $summary[2]->value);
    }

    public function testACreditNoteInAnotherCurrencyIsBookedInTheShopCurrencyAtItsRate(): void
    {
        $foreign = CurrencyQuery::create()->filterByByDefault(0)->findOne() ?? self::markTestSkipped('The test database has a single currency.');
        $creditNote = $this->creditNote('rebate', 'accepted', '20', null, [['other', 50.0, 60.0, 1, null]], currencyId: (int) $foreign->getId(), currencyRate: 2.0);

        $piece = $this->pieceOf($this->pieces(), $creditNote);

        self::assertSame((string) $foreign->getCode(), $piece->foreignCurrencyCode);
        self::assertSame([
            'customer 411000 - D0 C3000',
            'product 706200 20.00 D2500 C0',
            'tax 445720 20.00 D500 C0',
        ], $this->described($piece));
        self::assertSame(6000, $piece->entries[0]->foreignCents);
    }

    public function testAFullRefundInAnotherCurrencyWithoutRateIsBookedAtTheRateOfItsOrder(): void
    {
        $foreign = CurrencyQuery::create()->filterByByDefault(0)->findOne() ?? self::markTestSkipped('The test database has a single currency.');
        $order = $this->invoicedOrder('10', [[100.0, 1, 20.0], [10.0, 3, 0.55]], postage: 12.0, postageTax: 2.0);
        $order->setCurrencyId((int) $foreign->getId())->setCurrencyRate(1.25)->save();
        $creditNote = $this->creditNote('order_full_refund', 'accepted', '20', $order, $this->wholeOrder($order), currencyId: (int) $foreign->getId(), currencyRate: null);

        $pieces = $this->pieces();
        $invoice = $this->pieceOf($pieces, (string) $order->getInvoiceRef());
        $credit = $this->pieceOf($pieces, $creditNote);

        self::assertSame((string) $foreign->getCode(), $credit->foreignCurrencyCode);
        self::assertNotNull($credit->entries[0]->foreignCents);
        self::assertSame($this->reversed($invoice), $this->described($credit));
    }

    public function testACreditNoteInAnotherCurrencyWithoutRateNorOrderIsStillLeftOut(): void
    {
        $foreign = CurrencyQuery::create()->filterByByDefault(0)->findOne() ?? self::markTestSkipped('The test database has a single currency.');
        $creditNote = $this->creditNote('rebate', 'accepted', '20', null, [['other', 50.0, 60.0, 1, null]], currencyId: (int) $foreign->getId(), currencyRate: null);

        $report = new ExportReport();
        $refs = array_map(static fn (AccountingPiece $piece): string => $piece->invoiceRef, $this->pieces($report));

        self::assertNotContains($creditNote, $refs);
        self::assertStringContainsString($creditNote.': it was issued in another currency without exchange rate', implode("\n", $report->warnings()));
    }

    private static function createTheTablesOfTheModule(): void
    {
        $connection = Propel::getConnection('TheliaMain');

        foreach (self::TABLES as $table => $definition) {
            $connection->exec(\sprintf('DROP TABLE IF EXISTS `%s`', $table));
            $connection->exec($definition);
        }

        foreach (self::STATUSES as $code => [$id, $invoiced]) {
            $connection->exec(\sprintf("INSERT INTO `credit_note_status` (`id`, `code`, `invoiced`, `used`, `position`) VALUES (%d, '%s', %d, %d, %d)", $id, $code, $invoiced, 'used' === $code ? 1 : 0, $id));
        }

        foreach (self::TYPES as $code => $id) {
            $connection->exec(\sprintf("INSERT INTO `credit_note_type` (`id`, `code`, `position`, `required_order`) VALUES (%d, '%s', %d, %d)", $id, $code, $id, $id <= 3 ? 1 : 0));
        }
    }

    private function activateTheModule(bool $active): void
    {
        $connection = Propel::getConnection('TheliaMain');
        $connection->exec("DELETE FROM `module` WHERE `code` = 'CreditNote'");

        if ($active) {
            $connection->exec("INSERT INTO `module` (`code`, `version`, `type`, `activate`, `position`, `full_namespace`, `created_at`, `updated_at`) VALUES ('CreditNote', '4.4.0', 1, 1, 999, 'CreditNote\\\\CreditNote', NOW(), NOW())");
        }
    }

    /**
     * @param list<array{float, int, float}> $lines unit price, quantity, unit tax
     */
    private function invoicedOrder(string $day, array $lines, float $postage = 0.0, float $postageTax = 0.0, float $discount = 0.0): Order
    {
        $order = $this->factory->order(null, ['postage' => $postage, 'postageTax' => $postageTax, 'statusCode' => OrderStatus::CODE_PAID]);
        $order
            ->setCurrencyId((int) CurrencyQuery::create()->findOneByByDefault(1)->getId())
            ->setInvoiceRef('T172-'.$order->getId())
            ->setInvoiceDate(new \DateTime(self::MONTH.'-'.$day.' 10:00:00'))
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
     * @return list<OrderProduct>
     */
    private function linesOf(Order $order): array
    {
        return iterator_to_array($order->getOrderProducts(), false);
    }

    /**
     * What a full refund holds: each line of the order at its frozen prices, and its shipping.
     *
     * @return list<array{string, float, float, int, ?OrderProduct}>
     */
    private function wholeOrder(Order $order): array
    {
        $details = [];

        foreach ($this->linesOf($order) as $line) {
            $tax = array_sum(array_map(static fn (OrderProductTax $tax): float => (float) $tax->getAmount(), iterator_to_array($line->getOrderProductTaxes(), false)));
            $details[] = ['product', (float) $line->getPrice(), (float) $line->getPrice() + $tax, (int) $line->getQuantity(), $line];
        }

        if ((float) $order->getPostage() > 0) {
            $details[] = ['shipping', (float) $order->getPostage() - (float) $order->getPostageTax(), (float) $order->getPostage(), 1, null];
        }

        return $details;
    }

    /**
     * A credit note as the module writes it: the totals are the sum of its details less its
     * discount, the reference and date of invoice given when it is accepted.
     *
     * @param list<array{string, float, float, int, ?OrderProduct}> $details type, unit price, unit price with tax, quantity, line of the order
     *
     * @return string the reference of the credit note
     */
    private function creditNote(string $type, string $status, string $day, ?Order $order, array $details, float $discountWithoutTax = 0.0, float $discountWithTax = 0.0, bool $numbered = true, ?int $currencyId = null, ?float $currencyRate = 1.0, ?float $totalWithTax = null): string
    {
        $connection = Propel::getConnection('TheliaMain');
        $customer = $order?->getCustomer() ?? $this->factory->customer($this->factory->customerTitle());
        $connection->exec("INSERT INTO `credit_note_address` (`company`, `firstname`, `lastname`, `address1`, `zipcode`, `city`, `country_id`) VALUES (NULL, 'Jane', 'Refunded', '1 Main Street', '75001', 'Paris', 64)");
        $addressId = (int) $connection->lastInsertId();

        $total = 0.0;
        $sumWithTax = 0.0;

        foreach ($details as [, $price, $priceWithTax, $quantity]) {
            $total += $price * $quantity;
            $sumWithTax += $priceWithTax * $quantity;
        }

        $statement = $connection->prepare('INSERT INTO `credit_note` (`ref`, `invoice_ref`, `invoice_address_id`, `invoice_date`, `order_id`, `customer_id`, `type_id`, `status_id`, `currency_id`, `currency_rate`, `total_price`, `total_price_with_tax`, `discount_without_tax`, `discount_with_tax`, `created_at`, `updated_at`) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $statement->execute([
            'CN-'.uniqid(),
            $addressId,
            self::MONTH.'-'.$day.' 11:00:00',
            $order?->getId(),
            $customer->getId(),
            self::TYPES[$type],
            self::STATUSES[$status][0],
            $currencyId ?? (int) CurrencyQuery::create()->findOneByByDefault(1)->getId(),
            $currencyRate,
            $total - $discountWithoutTax,
            ($totalWithTax ?? $sumWithTax) - $discountWithTax,
            $discountWithoutTax,
            $discountWithTax,
        ]);
        $creditNoteId = (int) $connection->lastInsertId();
        $ref = 'AV-'.$creditNoteId;

        if ($numbered) {
            $connection->prepare('UPDATE `credit_note` SET `invoice_ref` = ? WHERE `id` = ?')->execute([$ref, $creditNoteId]);
        }

        $detail = $connection->prepare('INSERT INTO `credit_note_detail` (`credit_note_id`, `price`, `price_with_tax`, `order_product_id`, `type`, `quantity`, `title`) VALUES (?, ?, ?, ?, ?, ?, ?)');

        foreach ($details as [$detailType, $price, $priceWithTax, $quantity, $line]) {
            $detail->execute([$creditNoteId, $price, $priceWithTax, $line?->getId(), $detailType, $quantity, 'Detail']);
        }

        return $ref;
    }

    /**
     * @return list<AccountingPiece>
     */
    private function pieces(?ExportReport $report = null): array
    {
        $from = new \DateTime(self::MONTH.'-01 00:00:00');

        return iterator_to_array((new SalesJournal())->pieces($this->chart, $from, (clone $from)->modify('last day of this month 23:59:59'), $report ?? new ExportReport(), 'en_US'), false);
    }

    /**
     * @param list<AccountingPiece> $pieces
     */
    private function pieceOf(array $pieces, string $ref): AccountingPiece
    {
        foreach ($pieces as $piece) {
            if ($ref === $piece->invoiceRef) {
                return $piece;
            }
        }

        self::fail(\sprintf('No piece %s in the journal.', $ref));
    }

    /**
     * @return list<string>
     */
    private function described(AccountingPiece $piece): array
    {
        return array_map(static fn (AccountingEntry $entry): string => \sprintf('%s %s %s D%d C%d', $entry->role, $entry->account, $entry->rateKey ?? '-', $entry->debitCents, $entry->creditCents), $piece->entries);
    }

    /**
     * @return list<string>
     */
    private function reversed(AccountingPiece $piece): array
    {
        return array_map(static fn (AccountingEntry $entry): string => \sprintf('%s %s %s D%d C%d', $entry->role, $entry->account, $entry->rateKey ?? '-', $entry->creditCents, $entry->debitCents), $piece->entries);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(SalesJournalExport|TaxSummaryExport $export): array
    {
        $this->configured($export);

        $rows = [];

        foreach ($export as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function configured(SalesJournalExport|TaxSummaryExport $export): void
    {
        ConfigQuery::write(AccountingChart::CUSTOMER_ACCOUNT_KEY, '411000');
        ConfigQuery::write(AccountingChart::SHIPPING_ACCOUNT_KEY, '708500');
        ConfigQuery::write(AccountingChart::RATE_ACCOUNTS_KEY, '20:706200:445720,5.5:706055:445705,0:706000');
        $export->setLang(LangQuery::create()->findOneByLocale('en_US'));
        $start = new \DateTime(self::MONTH.'-01 00:00:00');
        $export->setRangeDate(['start' => $start, 'end' => (clone $start)->modify('last day of this month 23:59:59')]);
    }
}
