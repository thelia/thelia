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
use Thelia\Domain\Catalog\Quantity\QuantityRule;
use Thelia\Domain\Catalog\Quantity\UnitCode;
use Thelia\Domain\DataTransfer\Export\Type\OrderLineExport;
use Thelia\Domain\DataTransfer\Import\Type\ProductStockImport;
use Thelia\Model\Lang;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\FixtureFactory;
use Thelia\Test\IntegrationTestCase;

/**
 * A stock imported for a combination holds the decimals the combination is sold with,
 * and the order line export writes a quantity with its decimals and its unit.
 */
final class DecimalQuantityDataTransferTest extends IntegrationTestCase
{
    private FixtureFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = $this->createFixtureFactory();

        // getDataJsonCache() writes its row cache there without creating it.
        (new Filesystem())->mkdir(THELIA_CACHE_DIR.'export');
    }

    public function testAStockWithTheDecimalsOfTheCombinationIsImported(): void
    {
        $combination = $this->combination(new QuantityRule(minQuantity: 0.25, step: 0.25, decimals: 2, unitCode: UnitCode::KILOGRAM));

        self::assertNull((new ProductStockImport())->importData(['id' => $combination->getId(), 'stock' => '37.25']));
        self::assertSame(37.25, (float) $this->reloaded($combination)->getQuantity());
    }

    /**
     * A stock of 12.5 for a combination sold by the piece is a unit the combination does
     * not sell in: the row is refused and says why, the stock is left as it was.
     */
    public function testAStockWithMoreDecimalsThanTheCombinationIsRefusedAndSaysWhy(): void
    {
        $byThePiece = $this->combination(new QuantityRule());
        $byTheHundredth = $this->combination(new QuantityRule(minQuantity: 0.01, step: 0.01, decimals: 2));

        $wholeNumberError = (new ProductStockImport())->importData(['id' => $byThePiece->getId(), 'stock' => '12.5']);
        $decimalsError = (new ProductStockImport())->importData(['id' => $byTheHundredth->getId(), 'stock' => '12.125']);

        self::assertSame(
            'The stock 12.5 of the product sale element id '.$byThePiece->getId().' cannot be kept: The quantity must be a whole number.',
            $wholeNumberError,
        );
        self::assertSame(
            'The stock 12.125 of the product sale element id '.$byTheHundredth->getId().' cannot be kept: The quantity cannot carry more than 2 decimal places.',
            $decimalsError,
        );
        self::assertSame(10.0, (float) $this->reloaded($byThePiece)->getQuantity());
        self::assertSame(10.0, (float) $this->reloaded($byTheHundredth)->getQuantity());
    }

    public function testTheOrderLineExportWritesTheQuantityWithItsDecimalsAndItsUnit(): void
    {
        // A single precision column gave six significant digits back: 1234.57.
        $weighed = $this->orderWithALine(1234.567, 'kg', 3);
        $legacy = $this->orderWithALine(3.0, null, 0);

        $rows = $this->exportedRows();

        self::assertSame('1234.567', (string) $this->lineOf($weighed, $rows)['quantity']);
        self::assertSame('kg', $this->lineOf($weighed, $rows)['unit']);
        self::assertSame('3', (string) $this->lineOf($legacy, $rows)['quantity']);
        self::assertNull($this->lineOf($legacy, $rows)['unit']);
    }

    private function combination(QuantityRule $rule): ProductSaleElements
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());
        $combination = $this->factory->productSaleElement($product, ['quantity' => 10]);
        $rule->applyTo($combination);
        $combination->save($this->getPropelConnection());

        return $combination;
    }

    private function orderWithALine(float $quantity, ?string $unitCode, int $decimals): Order
    {
        $order = $this->factory->order();
        $order->setRef('QTY-'.uniqid())->save($this->getPropelConnection());

        (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef('WEIGHED')
            ->setProductSaleElementsRef('WEIGHED-PSE')
            ->setTitle('Weighed product')
            ->setQuantity($quantity)
            ->setUnitCode($unitCode)
            ->setQuantityDecimals($decimals)
            ->setPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save($this->getPropelConnection());

        return $order;
    }

    /**
     * Rows as written to the file: past beforeSerialize() and the aliases.
     *
     * @return list<array<string, mixed>>
     */
    private function exportedRows(): array
    {
        $export = new OrderLineExport();
        $export->setLang(Lang::getDefaultLanguage());
        $export->setRangeDate(null);

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
    private function lineOf(Order $order, array $rows): array
    {
        foreach ($rows as $row) {
            if ($row['order_ref'] === $order->getRef()) {
                return $row;
            }
        }

        self::fail('The order '.$order->getRef().' is not in the export.');
    }

    private function reloaded(ProductSaleElements $combination): ProductSaleElements
    {
        ProductSaleElementsTableMap::clearInstancePool();

        return ProductSaleElementsQuery::create()->findPk($combination->getId())
            ?? throw new \RuntimeException('The combination is gone.');
    }
}
