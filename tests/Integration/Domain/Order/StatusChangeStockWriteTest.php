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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Action\Order as OrderAction;
use Thelia\Core\Event\Order\GetStockUpdateOperationOnOrderStatusChangeEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Customer\Service\CustomerGroupPaymentPolicy;
use Thelia\Domain\Module\Payment\PaymentCartContext;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Domain\Order\Service\OrderStatusTransitionGuard;
use Thelia\Domain\Order\Service\StockDecrementer;
use Thelia\Domain\Order\StatusAction\Effect\AdjustStockAction;
use Thelia\Domain\Order\StatusAction\OrderStatusActionContext;
use Thelia\Domain\SaleUnit\Stock\SaleUnitStockResolver;
use Thelia\Exception\TheliaProcessException;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Tests\Support\Order\MovesOrders;
use Thelia\Tests\Support\Order\RecordingStockDecrementer;
use Thelia\Tests\Support\Order\RecordsStatements;
use Thelia\Tools\URL;

/**
 * How a status change writes the stock: one conditional UPDATE per row through the stock
 * decrementer, never a quantity read and written back, and a failure undone without hiding it.
 *
 * The test runs inside a transaction, so a second connection never sees its rows: the atomicity
 * is proved by the statement that moves the stock, not by a real race.
 */
final class StatusChangeStockWriteTest extends ActionIntegrationTestCase
{
    use MovesOrders;
    use RecordsStatements;

    /** @var list<array{string, callable}> */
    private array $registeredListeners = [];

    protected function setUp(): void
    {
        parent::setUp();
        ConfigQuery::write('check_available_stock', '1');
        $this->stockIsManagedOnOrderCreation(false);
    }

    protected function tearDown(): void
    {
        foreach ($this->registeredListeners as [$eventName, $listener]) {
            $this->dispatcher->removeListener($eventName, $listener);
        }

        Propel::disableInstancePooling();
        ConfigQuery::resetCache();
        parent::tearDown();
    }

    public function testAnOrderThatBecomesPaidTakesTheSharedStockThroughTheDecrementer(): void
    {
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf([[$productSaleElements, 3]], OrderStatus::CODE_NOT_PAID);
        $decrementer = $this->recordingDecrementer();

        $statements = $this->statementsDuring(fn () => $this->changeStatus($this->orderAction($decrementer), $order, OrderStatus::CODE_PAID));

        self::assertSame([(int) $productSaleElements->getId()], $decrementer->decrementedIds());
        self::assertSame(7.0, $this->stockOf($productSaleElements));
        self::assertSame([], array_values(array_filter(
            $statements,
            static fn (string $statement): bool => 1 === preg_match('/^UPDATE `product_sale_elements` SET .* WHERE `product_sale_elements`.`id`=/s', $statement),
        )), 'No quantity read beforehand is written back over the row.');
        self::assertNotEmpty(array_filter(
            $statements,
            static fn (string $statement): bool => str_starts_with($statement, 'UPDATE `product_sale_elements` SET `quantity` = `quantity` - :quantity WHERE `id` = :id AND `quantity` >= :quantity'),
        ), 'The database checks the availability in the very statement that takes the stock.');
    }

    public function testAPaidOrderCanceledPutsTheSharedStockBackThroughTheDecrementer(): void
    {
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf([[$productSaleElements, 3]], OrderStatus::CODE_PAID);
        $decrementer = $this->recordingDecrementer();

        $this->changeStatus($this->orderAction($decrementer), $order, OrderStatus::CODE_CANCELED);

        self::assertSame([(int) $productSaleElements->getId()], $decrementer->incrementedIds());
        self::assertSame(13.0, $this->stockOf($productSaleElements));
    }

    public function testAStockTooLowRefusesTheStatusChangeNamingTheSaleElementAndMovesNothing(): void
    {
        $available = $this->createProductSaleElements(10);
        $short = $this->createProductSaleElements(1);
        $order = $this->orderOf([[$short, 3], [$available, 3]], OrderStatus::CODE_NOT_PAID);
        $decrementer = $this->recordingDecrementer();

        try {
            $this->changeStatus($this->orderAction($decrementer), $order, OrderStatus::CODE_PAID);
            self::fail('A stock the order cannot take must refuse the status change.');
        } catch (TheliaProcessException $refusal) {
            self::assertStringContainsString((string) $short->getRef(), $refusal->getMessage());
        }

        self::assertSame([(int) $available->getId(), (int) $short->getId()], $decrementer->decrementedIds(), 'Both rows are asked of the database, in ascending id.');
        self::assertSame([10.0, 1.0], [$this->stockOf($available), $this->stockOf($short)], 'The row taken before the refusal is given back.');
        self::assertSame($this->orderStatus(OrderStatus::CODE_NOT_PAID)->getId(), OrderQuery::create()->findPk($order->getId())?->getStatusId());
    }

    public function testALineWhoseSaleElementIsGoneIsSkippedAsItAlwaysWas(): void
    {
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf([[$productSaleElements, 3]], OrderStatus::CODE_NOT_PAID);
        $highestId = (int) ProductSaleElementsQuery::create()->orderById(Criteria::DESC)->findOne()?->getId();
        $this->factory->orderProduct($order, ['productSaleElementsId' => $highestId + 1000, 'quantity' => 2.0, 'price' => '10.000000', 'promoPrice' => '10.000000']);

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertSame($this->orderStatus(OrderStatus::CODE_PAID)->getId(), OrderQuery::create()->findPk($order->getId())?->getStatusId());
        self::assertSame(7.0, $this->stockOf($productSaleElements));
    }

    public function testTheStockReadAfterTheStatusChangeIsTheOneInTheDatabase(): void
    {
        $productSaleElements = $this->createProductSaleElements(10);
        $order = $this->orderOf([[$productSaleElements, 3]], OrderStatus::CODE_NOT_PAID);
        Propel::enableInstancePooling();
        // Pooled from now on: the copy a findPk() hands back unless the pool forgets it.
        self::assertSame(10.0, (float) ProductSaleElementsQuery::create()->findPk($productSaleElements->getId())?->getQuantity());

        $this->moveOrderTo($order, OrderStatus::CODE_PAID);

        self::assertSame(7.0, (float) ProductSaleElementsQuery::create()->findPk($productSaleElements->getId())?->getQuantity());
    }

    public function testAStatusChangeDispatchedFromADecisionListenerDoesNotBreakTheOuterOne(): void
    {
        $outerStock = $this->createProductSaleElements(10);
        $innerStock = $this->createProductSaleElements(10);
        $outerOrder = $this->orderOf([[$outerStock, 3]], OrderStatus::CODE_NOT_PAID);
        $innerOrder = $this->orderOf([[$innerStock, 3]], OrderStatus::CODE_PAID);
        $fired = false;

        $this->onStockDecision(function () use (&$fired, $innerOrder): void {
            if ($fired) {
                return;
            }

            $fired = true;
            $this->moveOrderTo($innerOrder, OrderStatus::CODE_CANCELED);
        });

        $this->moveOrderTo($outerOrder, OrderStatus::CODE_PAID);

        self::assertTrue($fired);
        self::assertSame($this->orderStatus(OrderStatus::CODE_PAID)->getId(), OrderQuery::create()->findPk($outerOrder->getId())?->getStatusId());
        self::assertSame($this->orderStatus(OrderStatus::CODE_CANCELED)->getId(), OrderQuery::create()->findPk($innerOrder->getId())?->getStatusId());
        self::assertSame([7.0, 13.0], [$this->stockOf($outerStock), $this->stockOf($innerStock)]);
    }

    public function testAStatusChangeWhoseTransactionTheDatabaseAlreadyRolledBackRethrowsTheOriginalFailure(): void
    {
        $order = $this->orderOf([[$this->createProductSaleElements(10), 3]], OrderStatus::CODE_NOT_PAID);
        $deadlock = $this->deadlock();
        $this->onStockDecision(function () use ($deadlock): void {
            $this->transactionRolledBackByTheServer();

            throw $deadlock;
        });

        try {
            $this->moveOrderTo($order, OrderStatus::CODE_PAID);
            self::fail('The deadlock must reach the caller.');
        } catch (\Throwable $failure) {
            self::assertSame($deadlock, $failure, 'The failure to go back to a savepoint the server dropped must not replace the deadlock.');
        } finally {
            $this->reopenTheTestTransaction();
        }
    }

    public function testAStockAdjustmentWhoseTransactionTheDatabaseAlreadyRolledBackRethrowsTheOriginalFailure(): void
    {
        $order = $this->orderOf([[$this->createProductSaleElements(10), 3]], OrderStatus::CODE_PAID);
        $deadlock = $this->deadlock();
        $action = new AdjustStockAction($this->decrementerRunningFirst(function () use ($deadlock): void {
            $this->transactionRolledBackByTheServer();

            throw $deadlock;
        }));

        try {
            $action->execute($this->decreaseContext($order));
            self::fail('The deadlock must reach the caller.');
        } catch (\Throwable $failure) {
            self::assertSame($deadlock, $failure);
        } finally {
            $this->reopenTheTestTransaction();
        }
    }

    public function testAStockAdjustmentRunInsideAnotherDoesNotBreakTheOuterOne(): void
    {
        $outerStock = $this->createProductSaleElements(10);
        $innerStock = $this->createProductSaleElements(10);
        $outerOrder = $this->orderOf([[$outerStock, 3]], OrderStatus::CODE_PAID);
        $innerOrder = $this->orderOf([[$innerStock, 3]], OrderStatus::CODE_PAID);
        $innerAction = new AdjustStockAction($this->getService(StockDecrementer::class));
        $fired = false;
        $outerAction = new AdjustStockAction($this->decrementerRunningFirst(function () use (&$fired, $innerAction, $innerOrder): void {
            if (!$fired) {
                $fired = true;
                $innerAction->execute($this->decreaseContext($innerOrder));
            }
        }));

        $outerAction->execute($this->decreaseContext($outerOrder));

        self::assertTrue($fired);
        self::assertSame([7.0, 7.0], [$this->stockOf($outerStock), $this->stockOf($innerStock)]);
    }

    private function changeStatus(OrderAction $action, Order $order, string $statusCode): void
    {
        $event = (new OrderEvent($order))->setStatus($this->orderStatus($statusCode)->getId());

        $action->updateStatus($event, TheliaEvents::ORDER_UPDATE_STATUS, $this->dispatcher);
    }

    /**
     * The order action of the container, with every collaborator it has but the stock decrementer.
     */
    private function orderAction(StockDecrementer $decrementer): OrderAction
    {
        return new OrderAction(
            $this->getService(RequestStack::class),
            $this->getService(MailerFactory::class),
            $this->getService(SecurityContext::class),
            $this->getService(OrderFacade::class),
            $this->getService(GuestOrderAccessService::class),
            $this->getService(URL::class),
            $this->getService(OrderStatusTransitionGuard::class),
            $this->getService(PaymentCartContext::class),
            $this->getService(CustomerGroupPaymentPolicy::class),
            $decrementer,
        );
    }

    private function recordingDecrementer(): RecordingStockDecrementer
    {
        return new RecordingStockDecrementer($this->getService(SaleUnitStockResolver::class));
    }

    /**
     * The decrementer of the core, which runs $before ahead of each movement it is asked for.
     */
    private function decrementerRunningFirst(\Closure $before): StockDecrementer
    {
        return new readonly class($this->getService(SaleUnitStockResolver::class), $before) extends StockDecrementer {
            public function __construct(SaleUnitStockResolver $saleUnitStockResolver, private \Closure $before)
            {
                parent::__construct($saleUnitStockResolver);
            }

            public function decrement(
                int $productSaleElementsId,
                float $quantity,
                bool $guardAvailability,
                bool $allowNegativeStock,
                ConnectionInterface $connection,
                ?int $saleUnitId = null,
            ): void {
                ($this->before)();
                parent::decrement($productSaleElementsId, $quantity, $guardAvailability, $allowNegativeStock, $connection, $saleUnitId);
            }
        };
    }

    private function decreaseContext(Order $order): OrderStatusActionContext
    {
        return new OrderStatusActionContext(
            $order,
            $this->orderStatus(OrderStatus::CODE_PAID),
            $this->orderStatus(OrderStatus::CODE_PROCESSING),
            [AdjustStockAction::FIELD_OPERATION => AdjustStockAction::OPERATION_DECREASE],
        );
    }

    private function onStockDecision(callable $listener): void
    {
        $eventName = TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE;
        $wrapped = static function (GetStockUpdateOperationOnOrderStatusChangeEvent $event) use ($listener): void {
            if (!$event->isSimulation()) {
                $listener();
            }
        };
        $this->dispatcher->addListener($eventName, $wrapped, -1024);
        $this->registeredListeners[] = [$eventName, $wrapped];
    }

    private function deadlock(): \PDOException
    {
        $deadlock = new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock');
        $deadlock->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];

        return $deadlock;
    }

    /**
     * What MySQL and MariaDB do on a deadlock: the whole transaction is rolled back on the server,
     * behind the back of Propel, whose nesting counter stays where it was.
     */
    private function transactionRolledBackByTheServer(): void
    {
        $this->rawConnection()->rollBack();
    }

    /**
     * Gives the test case back the transaction it rolls back in tearDown(): without it, the next
     * test would run without one and commit its fixtures.
     */
    private function reopenTheTestTransaction(): void
    {
        $connection = $this->rawConnection();

        if (!$connection->inTransaction()) {
            $connection->beginTransaction();
        }
    }

    private function rawConnection(): ConnectionInterface
    {
        $connection = $this->getPropelConnection();
        self::assertInstanceOf(ConnectionWrapper::class, $connection);
        $raw = $connection->getWrappedConnection();
        self::assertInstanceOf(ConnectionInterface::class, $raw);

        return $raw;
    }

    private function stockIsManagedOnOrderCreation(bool $managed): void
    {
        $listener = static function (ManageStockOnCreationEvent $event) use ($managed): void {
            $event->setManageStock($managed);
        };
        $eventName = TheliaEvents::getModuleEvent(TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK, 'Cheque');
        $this->dispatcher->addListener($eventName, $listener, -1024);
        $this->registeredListeners[] = [$eventName, $listener];
    }

    /**
     * @param list<array{ProductSaleElements, int}> $lines
     */
    private function orderOf(array $lines, string $statusCode): Order
    {
        $order = $this->factory->order(null, ['statusCode' => $statusCode]);

        foreach ($lines as [$productSaleElements, $quantity]) {
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

        return $order;
    }

    private function createProductSaleElements(int $quantity): ProductSaleElements
    {
        $product = $this->factory->product($this->factory->category(), $this->factory->taxRule(), $this->factory->currency());

        return $this->factory->productSaleElement($product, ['quantity' => $quantity]);
    }

    private function stockOf(ProductSaleElements $productSaleElements): float
    {
        return (float) ProductSaleElementsQuery::create()->findPk($productSaleElements->getId())?->getQuantity();
    }
}
