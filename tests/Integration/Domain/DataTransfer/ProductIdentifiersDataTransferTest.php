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

use Symfony\Component\Filesystem\Filesystem;
use Thelia\Domain\DataTransfer\Export\AbstractExport;
use Thelia\Domain\DataTransfer\Export\Type\OrderLineExport;
use Thelia\Domain\DataTransfer\Export\Type\ProductPricesExport;
use Thelia\Domain\DataTransfer\Export\Type\ProductTaxedPricesExport;
use Thelia\Domain\DataTransfer\Import\Type\ProductStockImport;
use Thelia\Model\ExportQuery;
use Thelia\Model\Lang;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * The GTIN and the manufacturer part number in the exports and the stock import shipped
 * with Thelia.
 */
final class ProductIdentifiersDataTransferTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();

        // getDataJsonCache() writes its row cache there without creating it.
        (new Filesystem())->mkdir(THELIA_CACHE_DIR.'export');
    }

    public function testTheOrderLineExportGivesTheCodesFrozenOnTheOrderLine(): void
    {
        $combination = $this->combination(['eanCode' => '4006381333931', 'mpn' => 'MPN-CATALOGUE']);
        $order = $this->orderWithALine($combination, '4006381333931', 'MPN-SOLD');

        $combination->setEanCode('036000291452')->setMpn('MPN-CORRECTED')->save();

        $line = $this->orderLineOf($order, $this->exportedRows(new OrderLineExport()));

        self::assertSame('4006381333931', $line['gtin']);
        self::assertSame('MPN-SOLD', $line['mpn']);
        self::assertSame($combination->getRef(), $line['combination_ref']);
        self::assertSame($order->getRef(), $line['order_ref']);
    }

    public function testTheOrderLineExportKeepsOnlyTheOrdersOfThePeriod(): void
    {
        $combination = $this->combination();
        $old = $this->orderWithALine($combination, null, 'MPN-OLD', '-3 years');
        $recent = $this->orderWithALine($combination, null, 'MPN-RECENT', '-1 day');

        $refs = array_column(
            $this->exportedRows(new OrderLineExport(), ['start' => new \DateTime('-1 month'), 'end' => new \DateTime('+1 day')]),
            'order_ref',
        );

        self::assertContains($recent->getRef(), $refs);
        self::assertNotContains($old->getRef(), $refs);
    }

    public function testAPartNumberASpreadsheetWouldRunIsExportedAsText(): void
    {
        $combination = $this->combination(['mpn' => '=HYPERLINK("http://example.test","x")']);
        $order = $this->orderWithALine($combination, null, '=HYPERLINK("http://example.test","x")');

        $line = $this->orderLineOf($order, $this->exportedRows(new OrderLineExport()));
        self::assertSame('\'=HYPERLINK("http://example.test","x")', $line['mpn']);

        $priceRow = $this->rowOfCombination($combination, $this->exportedRows(new ProductPricesExport()));
        self::assertSame('\'=HYPERLINK("http://example.test","x")', $priceRow['mpn']);
    }

    public function testBothPriceExportsCarryTheGtinAndThePartNumber(): void
    {
        $combination = $this->combination(['eanCode' => '96385074', 'mpn' => 'MPN-PRICE']);

        foreach ([new ProductPricesExport(), new ProductTaxedPricesExport()] as $export) {
            $row = $this->rowOfCombination($combination, $this->exportedRows($export));

            self::assertSame('96385074', $row['ean'], $export::class);
            self::assertSame('MPN-PRICE', $row['mpn'], $export::class);
        }
    }

    public function testTheStockImportRefusesARowWhoseCodeIsNotAGtinAndSaysWhy(): void
    {
        $combination = $this->combination(['eanCode' => '4006381333931']);

        $error = (new ProductStockImport())->importData(['id' => $combination->getId(), 'stock' => 99, 'ean' => '4006381333932']);

        self::assertNotNull($error);
        self::assertStringContainsString('check digit', $error);

        $stored = $this->reloaded($combination);
        self::assertSame('4006381333931', $stored->getEanCode());
        self::assertNotSame(99.0, $stored->getQuantity());
    }

    public function testTheStockImportWritesAValidCodeAndThePartNumber(): void
    {
        $combination = $this->combination();
        $import = new ProductStockImport();

        self::assertNull($import->importData(['id' => $combination->getId(), 'stock' => 12, 'ean' => '0 36000 29145 2', 'mpn' => 'MPN-IMPORTED']));

        $stored = $this->reloaded($combination);
        self::assertSame('036000291452', $stored->getEanCode());
        self::assertSame('MPN-IMPORTED', $stored->getMpn());
        self::assertSame(1, $import->getImportedRows());
    }

    public function testTheOrderLineExportIsRegisteredInTheCatalogue(): void
    {
        $export = ExportQuery::create()->findOneByRef('thelia.export.order_lines');

        self::assertNotNull($export);
        self::assertSame(OrderLineExport::class, $export->getHandleClass());
        self::assertSame('thelia.export.orders', $export->getExportCategory()?->getRef());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function combination(array $overrides = []): ProductSaleElements
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());

        return $this->factory->productSaleElement($product, $overrides);
    }

    private function orderWithALine(ProductSaleElements $combination, ?string $gtin, ?string $mpn, string $placedAt = 'now'): Order
    {
        $order = $this->factory->order();
        $order->setRef('GTIN-'.uniqid());
        $order->setCreatedAt(new \DateTime($placedAt));
        $order->save($this->getPropelConnection());

        (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef($combination->getProduct()->getRef())
            ->setProductSaleElementsRef($combination->getRef())
            ->setProductSaleElementsId($combination->getId())
            ->setTitle('Identified product')
            ->setQuantity(1.0)
            ->setPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->setEanCode($gtin)
            ->setMpn($mpn)
            ->save($this->getPropelConnection());

        return $order;
    }

    /**
     * Rows as written to the file: past beforeSerialize() and the aliases.
     *
     * @param array<string, \DateTime>|null $rangeDate
     *
     * @return list<array<string, mixed>>
     */
    private function exportedRows(AbstractExport $export, ?array $rangeDate = null): array
    {
        $export->setLang(Lang::getDefaultLanguage());
        $export->setRangeDate($rangeDate);

        $rows = [];
        foreach ($export as $row) {
            $rows[] = $export->applyOrderAndAliases($export->beforeSerialize($row));
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    private function orderLineOf(Order $order, array $rows): array
    {
        foreach ($rows as $row) {
            if ($row['order_ref'] === $order->getRef()) {
                return $row;
            }
        }

        self::fail('The order '.$order->getRef().' is not in the export.');
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    private function rowOfCombination(ProductSaleElements $combination, array $rows): array
    {
        foreach ($rows as $row) {
            if ((int) $row['id'] === $combination->getId()) {
                return $row;
            }
        }

        self::fail('The combination '.$combination->getRef().' is not in the export.');
    }

    private function reloaded(ProductSaleElements $combination): ProductSaleElements
    {
        ProductSaleElementsTableMap::clearInstancePool();

        return ProductSaleElementsQuery::create()->findPk($combination->getId())
            ?? throw new \RuntimeException('The combination is gone.');
    }
}
