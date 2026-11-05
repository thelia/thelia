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

use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Catalog\Stock\OutOfStockPolicy;
use Thelia\Domain\Catalog\Stock\StockSettings;
use Thelia\Domain\Order\Enum\OrderStatusActionTrigger;
use Thelia\Domain\Order\Service\StockDecrementer;
use Thelia\Domain\Order\StatusAction\Effect\AdjustStockAction;
use Thelia\Domain\Order\StatusAction\OrderStatusActionContext;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Module;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusAction;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\MovesOrders;
use Thelia\Tests\Support\Stock\TestCallerTransaction;

/**
 * A stock action moves the stock of an order in the transaction of the status change: a product
 * the stock cannot serve refuses the status change, and that transaction is rolled back with
 * what it wrote for the products before it.
 */
final class AdjustStockActionTest extends ActionIntegrationTestCase
{
    use MovesOrders;

    protected function tearDown(): void
    {
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '0');
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

    public function testADecreaseTheStockCannotServeRefusesTheStatusChange(): void
    {
        ConfigQuery::write('check_available_stock', '1');
        [$order, $short] = $this->orderOfAProductInStockAndOneShortOfIt();

        try {
            $this->moveOrderTo($order, OrderStatus::CODE_SENT);
            self::fail('A stock the order cannot take must refuse the status change.');
        } catch (TheliaProcessException $refusal) {
            self::assertSame($short->getRef().' : Not enough stock', $refusal->getMessage());
        }

        self::assertSame(1.0, $this->stockOf($short), 'The product without enough stock is left alone.');
        self::assertSame(OrderStatus::CODE_PROCESSING, OrderQuery::create()->findPk($order->getId())?->getOrderStatus()->getCode());
    }

    /**
     * The same, inside a transaction opened by the caller (a module, or this test case, which
     * wraps every test in one): the status change rolls its own level back, so the caller can no
     * longer commit, and what the change wrote for the products before the failing one goes
     * with the transaction of the caller.
     */
    public function testADecreaseTheStockCannotServeInsideACallersTransactionLeavesTheCallerNothingToCommit(): void
    {
        ConfigQuery::write('check_available_stock', '1');
        [$order] = $this->orderOfAProductInStockAndOneShortOfIt();

        $connection = Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME);
        self::assertInstanceOf(ConnectionWrapper::class, $connection);
        $connection->beginTransaction();

        try {
            $this->moveOrderTo($order, OrderStatus::CODE_SENT);
            self::fail('A stock the order cannot take must refuse the status change.');
        } catch (TheliaProcessException) {
            self::assertFalse($connection->isCommitable(), 'The refused change rolled its level back: the caller cannot commit the stock it moved.');
        } finally {
            $connection->rollBack();
        }
    }

    /**
     * @return array{Order, ProductSaleElements} the order, and the product it asks more of than the stock holds
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

        return [$order, $short];
    }

    /**
     * Physical 1, another cart holds 1: a decrease of 1 refuses the status change, the unit stays
     * the other cart's.
     */
    public function testADecreaseLeavesToAnotherCartWhatItHolds(): void
    {
        $this->reserveStock();
        $this->decreaseOnSent();

        $last = $this->createProductSaleElements(1);
        $this->holdInAnotherCart($last, 1);

        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PROCESSING]);
        $this->addOrderProduct($order, $last, 1);

        try {
            $this->moveOrderTo($order, OrderStatus::CODE_SENT);
            self::fail('A unit another cart holds must refuse the status change.');
        } catch (TheliaProcessException) {
        }

        self::assertSame(1.0, $this->stockOf($last), 'The unit another cart holds is not taken.');
        self::assertSame(OrderStatus::CODE_PROCESSING, OrderQuery::create()->findPk($order->getId())?->getOrderStatus()->getCode());
    }

    /**
     * Physical 1, sold up to 5 below zero, another cart holds 1: the decrease of 1 goes below what is
     * left to this order and is frozen on the line as a preorder.
     */
    public function testADecreaseBeyondWhatOtherCartsHoldIsAPreorder(): void
    {
        $this->reserveStock();
        $this->decreaseOnSent();

        $limited = $this->createProductSaleElements(1);
        $limited->setOutOfStockPolicy(OutOfStockPolicy::Limited->value)->setPreorderLimit(5)->setRestockDate(new \DateTime('2030-01-15'))->save();
        $this->holdInAnotherCart($limited, 1);

        $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PROCESSING]);
        $this->addOrderProduct($order, $limited, 1);

        $this->moveOrderTo($order, OrderStatus::CODE_SENT);

        self::assertSame(0.0, $this->stockOf($limited));
        $line = OrderProductQuery::create()->filterByOrderId($order->getId())->findOne();
        self::assertSame(1, (int) $line?->getIsPreorder(), 'The line is a preorder.');
        self::assertSame('2030-01-15', $line?->getRestockDate('Y-m-d'));
    }

    public function testADecreaseIsToldToTheListenersOfTheStock(): void
    {
        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::resetCache();
        $this->decreaseOnSent();
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn(Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME));

        $changes = [];
        $listener = static function (StockLevelChangedEvent $event) use (&$changes): void {
            $changes = [...$changes, ...$event->getChanges()];
        };
        $this->dispatcher->addListener(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, $listener);

        try {
            $stock = $this->createProductSaleElements(3);
            $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PROCESSING]);
            $this->addOrderProduct($order, $stock, 1);

            $this->moveOrderTo($order, OrderStatus::CODE_SENT);
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, $listener);
            $this->getService(TestCallerTransaction::class)->reset();
        }

        self::assertEquals([new StockLevelChange((int) $stock->getId(), 3.0, 2.0)], $changes);
    }

    /**
     * Built without the announcer (a module that wires the action by hand), the action still tells
     * what it did, at once, through the dispatcher.
     */
    public function testAnActionWithoutAnAnnouncerTellsTheChangesAtOnce(): void
    {
        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::resetCache();
        $this->getService(TestCallerTransaction::class)->ignoreTheLevelsOpenOn(Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME));

        $changes = [];
        $listener = static function (StockLevelChangedEvent $event) use (&$changes): void {
            $changes = [...$changes, ...$event->getChanges()];
        };
        $this->dispatcher->addListener(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, $listener);

        try {
            $stock = $this->createProductSaleElements(3);
            $order = $this->factory->order(null, ['statusCode' => OrderStatus::CODE_PROCESSING]);
            $this->addOrderProduct($order, $stock, 1);

            $action = new AdjustStockAction($this->getService(StockDecrementer::class), null, null, $this->dispatcher);
            $action->execute(new OrderStatusActionContext($order, $this->orderStatus(OrderStatus::CODE_PROCESSING), $this->orderStatus(OrderStatus::CODE_SENT), [AdjustStockAction::FIELD_OPERATION => AdjustStockAction::OPERATION_DECREASE]));
            $action->execute(new OrderStatusActionContext($order, $this->orderStatus(OrderStatus::CODE_SENT), $this->orderStatus(OrderStatus::CODE_PROCESSING), [AdjustStockAction::FIELD_OPERATION => AdjustStockAction::OPERATION_INCREASE]));
        } finally {
            $this->dispatcher->removeListener(TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, $listener);
            $this->getService(TestCallerTransaction::class)->reset();
        }

        self::assertEquals(
            [new StockLevelChange((int) $stock->getId(), 3.0, 2.0), new StockLevelChange((int) $stock->getId(), 2.0, 3.0)],
            $changes,
            'The decrease and the increase are both told.',
        );
    }

    private function reserveStock(): void
    {
        ConfigQuery::write('check-available-stock', '1');
        ConfigQuery::write('allow_negative_stock', '0');
        ConfigQuery::write(StockSettings::RESERVATION_ENABLED, '1');
        ConfigQuery::write(StockSettings::RESERVATION_SCOPE, 'checkout');
        ConfigQuery::resetCache();
    }

    private function decreaseOnSent(): void
    {
        $this->action(
            OrderStatusActionTrigger::TRANSITION,
            OrderStatus::CODE_PROCESSING,
            OrderStatus::CODE_SENT,
            AdjustStockAction::getType(),
            [AdjustStockAction::FIELD_OPERATION => AdjustStockAction::OPERATION_DECREASE],
        );
    }

    private function holdInAnotherCart(ProductSaleElements $productSaleElements, float $quantity): void
    {
        $cart = $this->factory->cart(null, ['currency' => $this->factory->currency()]);
        $line = $this->factory->cartItem($cart, $productSaleElements->getProduct(), $productSaleElements, ['quantity' => $quantity]);
        Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME)->exec('UPDATE cart_item SET reserved_until = NOW() + INTERVAL 10 MINUTE WHERE id = '.(int) $line->getId());
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
