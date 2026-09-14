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

namespace Thelia\Tests\Integration\Domain\Order;

use Propel\Runtime\Propel;
use Thelia\Domain\Order\Enum\OrderStatusActionTrigger;
use Thelia\Domain\Order\StatusAction\Effect\AdjustStockAction;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusAction;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\MovesOrders;

/**
 * The stock of an order is adjusted as a whole or not at all: a product that cannot be
 * decremented leaves the products already decremented untouched. The status change itself
 * survives, the runner records the failure - only the stock is kept whole.
 */
final class AdjustStockActionTest extends ActionIntegrationTestCase
{
    use MovesOrders;

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        parent::tearDown();
    }

    public function testAFailingDecreaseLeavesNoProductAdjusted(): void
    {
        ConfigQuery::write('check_available_stock', '1');

        $this->action(
            OrderStatusActionTrigger::TRANSITION,
            OrderStatus::CODE_PROCESSING,
            OrderStatus::CODE_SENT,
            AdjustStockAction::getType(),
            [AdjustStockAction::FIELD_OPERATION => AdjustStockAction::OPERATION_DECREASE],
        );

        $plenty = $this->createProductSaleElements(10);
        $short = $this->createProductSaleElements(1);

        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PROCESSING]);
        $this->addOrderProduct($order, $plenty, 3);
        $this->addOrderProduct($order, $short, 5);

        $this->moveOrderTo($order, OrderStatus::CODE_SENT);

        self::assertSame(1.0, $this->stockOf($short), 'The product without enough stock is left alone.');
        self::assertSame(
            10.0,
            $this->stockOf($plenty),
            'The action adjusts the order as a whole or not at all: the first product must not stay decremented when a later one fails.',
        );
    }

    /**
     * The same, inside a transaction opened by the caller: the admin API wraps the whole
     * ORDER_UPDATE_STATUS dispatch in one, and a nested rollback would poison its commit.
     */
    public function testAFailingDecreaseInsideACallersTransactionLeavesNoProductAdjusted(): void
    {
        ConfigQuery::write('check_available_stock', '1');

        $this->action(
            OrderStatusActionTrigger::TRANSITION,
            OrderStatus::CODE_PROCESSING,
            OrderStatus::CODE_SENT,
            AdjustStockAction::getType(),
            [AdjustStockAction::FIELD_OPERATION => AdjustStockAction::OPERATION_DECREASE],
        );

        $plenty = $this->createProductSaleElements(10);
        $short = $this->createProductSaleElements(1);

        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PROCESSING]);
        $this->addOrderProduct($order, $plenty, 3);
        $this->addOrderProduct($order, $short, 5);

        $connection = Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->moveOrderTo($order, OrderStatus::CODE_SENT);
        } finally {
            $connection->commit();
        }

        self::assertSame(1.0, $this->stockOf($short), 'The product without enough stock is left alone.');
        self::assertSame(10.0, $this->stockOf($plenty), 'The caller commits, and the first product must not stay decremented.');
    }

    private function action(
        OrderStatusActionTrigger $trigger,
        ?string $fromCode,
        string $toCode,
        string $type,
        array $payload = [],
    ): void {
        (new OrderStatusAction())
            ->setTriggerType($trigger->value)
            ->setFromStatusId(null === $fromCode ? null : $this->orderStatus($fromCode)->getId())
            ->setToStatusId($this->orderStatus($toCode)->getId())
            ->setActionType($type)
            ->setDecodedPayload($payload)
            ->setPosition(1)
            ->setActive(true)
            ->save();
    }

    private function createProductSaleElements(int $quantity): ProductSaleElements
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());

        return $this->factory->productSaleElement($product, ['quantity' => $quantity]);
    }

    private function addOrderProduct(Order $order, ProductSaleElements $productSaleElements, int $quantity): void
    {
        (new OrderProduct())
            ->setOrderId($order->getId())
            ->setProductRef('ref-'.$productSaleElements->getId())
            ->setProductSaleElementsRef((string) $productSaleElements->getRef())
            ->setProductSaleElementsId($productSaleElements->getId())
            ->setTitle('Product')
            ->setQuantity((float) $quantity)
            ->setPrice('10.000000')
            ->setWasNew(0)
            ->setWasInPromo(0)
            ->save();
    }

    private function stockOf(ProductSaleElements $productSaleElements): float
    {
        return (float) ProductSaleElementsQuery::create()->findPk($productSaleElements->getId())->getQuantity();
    }
}
