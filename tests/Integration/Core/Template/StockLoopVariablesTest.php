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

namespace Thelia\Tests\Integration\Core\Template;

use Thelia\Core\Template\Loop\LoopExecutor;
use Thelia\Model\LangQuery;
use Thelia\Test\IntegrationTestCase;

/** The stock variables the product sale element and order product loops hand to the templates. */
final class StockLoopVariablesTest extends IntegrationTestCase
{
    public function testTheSaleElementLoopExposesTheStockSettings(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $pse = $factory->productSaleElement($product, ['ref' => 'STOCK-LOOP-PSE']);
        $pse->setOutOfStockPolicy(2)->setPreorderLimit(10.0)->setRestockDate(new \DateTime('2027-03-15'))->setLowStockThreshold(20.0)->save();

        $rows = $this->rows('product_sale_elements', ['id' => $pse->getId(), 'product' => $product->getId(), 'backend_context' => true]);

        self::assertCount(1, $rows);
        self::assertSame(2, (int) $rows[0]['OUT_OF_STOCK_POLICY']);
        self::assertSame(10.0, (float) $rows[0]['PREORDER_LIMIT']);
        self::assertSame('2027-03-15', $rows[0]['RESTOCK_DATE']->format('Y-m-d'));
        self::assertSame(20.0, (float) $rows[0]['LOW_STOCK_THRESHOLD']);
    }

    public function testTheSaleElementLoopNeverHandsTheLowStockThresholdToAFrontTemplate(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $pse = $factory->productSaleElement($product, ['ref' => 'STOCK-LOOP-FRONT']);
        $pse->setOutOfStockPolicy(2)->setPreorderLimit(10.0)->setLowStockThreshold(20.0)->save();

        $rows = $this->rows('product_sale_elements', ['id' => $pse->getId(), 'product' => $product->getId()]);

        self::assertCount(1, $rows);
        self::assertSame(2, (int) $rows[0]['OUT_OF_STOCK_POLICY'], 'The mode is for the front.');
        self::assertArrayNotHasKey('LOW_STOCK_THRESHOLD', $rows[0], 'The threshold the owner is alerted at is not.');
    }

    public function testTheSaleElementLoopSaysRefuseAndNothingElseByDefault(): void
    {
        $factory = $this->createFixtureFactory();
        $product = $factory->product($factory->category(), $factory->taxRule(), $factory->currency());
        $pse = $factory->productSaleElement($product, ['ref' => 'STOCK-LOOP-DEFAULT']);

        $rows = $this->rows('product_sale_elements', ['id' => $pse->getId(), 'product' => $product->getId()]);

        self::assertCount(1, $rows);
        self::assertSame(0, (int) $rows[0]['OUT_OF_STOCK_POLICY']);
        self::assertSame('', $rows[0]['PREORDER_LIMIT'], 'A missing value reaches the template empty.');
        self::assertSame('', $rows[0]['RESTOCK_DATE'], 'A missing value reaches the template empty.');

        $backRows = $this->rows('product_sale_elements', ['id' => $pse->getId(), 'product' => $product->getId(), 'backend_context' => true]);
        self::assertSame('', $backRows[0]['LOW_STOCK_THRESHOLD'], 'A missing value reaches the template empty.');
    }

    public function testTheOrderProductLoopExposesThePreorderMark(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order();
        $preorder = $factory->orderProduct($order, ['productRef' => 'PREORDER-LINE']);
        $preorder->setIsPreorder(1)->setRestockDate(new \DateTime('2027-04-01'))->save();
        $factory->orderProduct($order, ['productRef' => 'PLAIN-LINE']);

        $rows = [];

        foreach ($this->rows('order_product', ['order' => $order->getId()]) as $row) {
            $rows[$row['REF']] = $row;
        }

        self::assertSame(1, $rows['PREORDER-LINE']['IS_PREORDER']);
        self::assertSame('2027-04-01', $rows['PREORDER-LINE']['RESTOCK_DATE']->format('Y-m-d'));
        self::assertSame(0, $rows['PLAIN-LINE']['IS_PREORDER']);
        self::assertSame('', $rows['PLAIN-LINE']['RESTOCK_DATE']);
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $type, array $arguments): array
    {
        $lang = LangQuery::create()->filterByByDefault(1)->findOne() ?? LangQuery::create()->findOne();
        $rows = [];

        foreach ($this->getService(LoopExecutor::class)->execute($type, $arguments + ['lang' => $lang->getId()]) as $row) {
            $rows[] = $row->getVarVal();
        }

        return $rows;
    }
}
