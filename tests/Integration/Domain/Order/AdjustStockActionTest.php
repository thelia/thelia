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
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Module;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusAction;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\MovesOrders;

/**
 * A stock action moves the stock of an order as a whole or not at all, in the transaction of
 * the status change: a product the stock cannot serve refuses the status change, and leaves
 * every product, the ones before it included, as it was.
 */
final class AdjustStockActionTest extends ActionIntegrationTestCase
{
    use MovesOrders;

    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
        parent::tearDown();
    }

    /**
     * A title can be a sentence that names no module ("There's nothing to pay for this order"):
     * the back office lists the modules of a limit as the modules page does.
     */
    public function testTheModulesOfALimitAreNamedByTheirTitleAndTheirCode(): void
    {
        (new Module())->setCode('StockLimitTitled')->setType(BaseModule::PAYMENT_MODULE_TYPE)->setActivate(1)
            ->setLocale('en_US')->setTitle('Nothing to pay')->save();
        (new Module())->setCode('StockLimitUntitled')->setType(BaseModule::PAYMENT_MODULE_TYPE)->setActivate(1)->save();

        $choices = [];

        foreach ($this->getService(AdjustStockAction::class)->describePayload() as $field) {
            if (AdjustStockAction::FIELD_PAYMENT_MODULES === $field->name) {
                $choices = $field->choices;
            }
        }

        self::assertSame('Nothing to pay (StockLimitTitled)', $choices['StockLimitTitled'] ?? null);
        self::assertSame('StockLimitUntitled', $choices['StockLimitUntitled'] ?? null);
    }

    public function testADecreaseTheStockCannotServeRefusesTheStatusChangeAndMovesNothing(): void
    {
        ConfigQuery::write('check_available_stock', '1');
        [$order, $plenty, $short] = $this->orderOfAProductInStockAndOneShortOfIt();

        try {
            $this->moveOrderTo($order, OrderStatus::CODE_SENT);
            self::fail('A stock the order cannot take must refuse the status change.');
        } catch (TheliaProcessException) {
        }

        self::assertSame(1.0, $this->stockOf($short), 'The product without enough stock is left alone.');
        self::assertSame(10.0, $this->stockOf($plenty), 'The first product must not stay decremented when a later one fails.');
        self::assertSame(OrderStatus::CODE_PROCESSING, OrderQuery::create()->findPk($order->getId())?->getOrderStatus()->getCode());
    }

    /**
     * The same, inside a transaction opened by the caller: the admin API wraps the whole
     * ORDER_UPDATE_STATUS dispatch in one, and gets the refusal to roll it back.
     */
    public function testADecreaseTheStockCannotServeInsideACallersTransactionMovesNothing(): void
    {
        ConfigQuery::write('check_available_stock', '1');
        [$order, $plenty, $short] = $this->orderOfAProductInStockAndOneShortOfIt();

        $connection = Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $this->moveOrderTo($order, OrderStatus::CODE_SENT);
            $connection->commit();
            self::fail('A stock the order cannot take must refuse the status change.');
        } catch (TheliaProcessException) {
            $connection->rollBack();
        }

        self::assertSame(1.0, $this->stockOf($short), 'The product without enough stock is left alone.');
        self::assertSame(10.0, $this->stockOf($plenty), 'The first product must not stay decremented when a later one fails.');
    }

    /**
     * @return array{Order, ProductSaleElements, ProductSaleElements}
     */
    private function orderOfAProductInStockAndOneShortOfIt(): array
    {
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

        return [$order, $plenty, $short];
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
