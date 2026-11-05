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

use Thelia\Domain\Order\DTO\OrderEdit;
use Thelia\Domain\Order\Enum\OrderEditRefusal;
use Thelia\Domain\Order\Enum\OrderHistoryEventType;
use Thelia\Domain\Order\Exception\OrderEditRefusedException;
use Thelia\Domain\Order\Service\OrderEditor;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Test\IntegrationTestCase;

/**
 * An edit that fails after it wrote must leave nothing behind. Under the
 * nested transactions of the other integration tests a rollback does not undo
 * anything, so this one commits for real and removes what it created.
 * `ddev composer test:prepare` after a crash of this test restores a clean base.
 */
final class OrderEditorRollbackTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    /** @var array<string, array{column: string, next: int}> */
    private array $watermarks = [];

    protected function setUp(): void
    {
        parent::setUp();

        $statement = $this->getPropelConnection()->prepare(
            "SELECT t.TABLE_NAME AS name, c.COLUMN_NAME AS col, t.AUTO_INCREMENT AS next FROM information_schema.TABLES t JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME AND c.EXTRA LIKE '%auto_increment%' WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE'"
        );
        $statement->execute();

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $this->watermarks[(string) $row['name']] = ['column' => (string) $row['col'], 'next' => (int) $row['next']];
        }
    }

    /**
     * Deletes every row the test inserted: those past the auto-increment
     * watermark taken in setUp(). A table that refuses while a child still points
     * at it is retried after the others, until a pass deletes nothing more.
     */
    protected function tearDown(): void
    {
        $connection = $this->getPropelConnection();
        $pending = $this->watermarks;

        for ($pass = 0; $pass < 10 && [] !== $pending; ++$pass) {
            foreach ($pending as $table => $watermark) {
                try {
                    $connection->exec(\sprintf('DELETE FROM `%s` WHERE `%s` >= %d', $table, $watermark['column'], $watermark['next']));
                    unset($pending[$table]);
                } catch (\Throwable) {
                }
            }
        }

        parent::tearDown();

        self::assertSame([], array_keys($pending), 'The rows of the test could not all be removed: run `ddev composer test:prepare`.');
    }

    public function testAFailureAfterTheLinesWereWrittenLeavesTheOrderAndTheStockUntouched(): void
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);

        $product = $factory->product($factory->category(), $factory->taxRule(['isDefault' => false]), $factory->currency(), ['title' => 'Widget', 'baseQuantity' => 10]);
        $pse = $product->getDefaultSaleElements();
        $line = $factory->orderProduct($order, [
            'productRef' => $product->getRef(),
            'productSaleElementsRef' => $pse->getRef(),
            'productSaleElementsId' => $pse->getId(),
            'quantity' => 2.0,
            'price' => '10.000000',
            'promoPrice' => '10.000000',
        ]);

        $editor = $this->getService(OrderEditor::class);
        $state = $editor->stateOf($order);
        $stock = (float) ProductSaleElementsQuery::create()->findPk($pse->getId())->getQuantity();

        try {
            $editor->edit($order, new OrderEdit(quantities: [(int) $line->getId() => 5.0], discount: '99999.000000'));
            self::fail('The discount above the total must refuse the edit.');
        } catch (OrderEditRefusedException $refusal) {
            self::assertStringContainsString('('.OrderEditRefusal::InvalidDiscount->value.')', $refusal->getMessage());
        }

        self::assertEqualsWithDelta(2.0, (float) OrderProductQuery::create()->findPk($line->getId())->getQuantity(), 0.0001);
        self::assertEqualsWithDelta($stock, (float) ProductSaleElementsQuery::create()->findPk($pse->getId())->getQuantity(), 0.0001);
        self::assertSame('0.000000', number_format((float) OrderQuery::create()->findPk($order->getId())->getDiscount(), 6, '.', ''));
        self::assertSame($state, $editor->stateOf(OrderQuery::create()->findPk($order->getId())));
        self::assertSame($state, $editor->stateOf($order), 'The order held by the caller must show what the database holds after a refusal.');
        self::assertSame(0, OrderHistoryQuery::create()->filterByOrderId($order->getId())->filterByEventType(OrderHistoryEventType::ORDER_EDITED->value)->count());
        self::assertNotNull(OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId()));
    }

    public function testASimulationAnnouncesTheTotalsOfTheEditAndWritesNothing(): void
    {
        [$order, $line, $pse] = $this->aPaidOrderWithOneLine();

        $editor = $this->getService(OrderEditor::class);
        $edit = new OrderEdit(quantities: [(int) $line->getId() => 5.0], unitPrices: [(int) $line->getId() => '12.000000'], discount: '3.000000');
        $state = $editor->stateOf($order);
        $stock = (float) ProductSaleElementsQuery::create()->findPk($pse->getId())->getQuantity();

        $preview = $editor->simulate($order, $edit);

        self::assertGreaterThan((float) $preview->before->total, (float) $preview->after->total, 'Five units at 12 less a discount of 3 cost more than two units at 10.');
        self::assertSame($state, $editor->stateOf(OrderQuery::create()->findPk($order->getId())));
        self::assertSame($state, $editor->stateOf($order), 'The order held by the caller must show what the database holds after a simulation.');
        self::assertEqualsWithDelta($stock, (float) ProductSaleElementsQuery::create()->findPk($pse->getId())->getQuantity(), 0.0001);
        self::assertEqualsWithDelta(2.0, (float) OrderProductQuery::create()->findPk($line->getId())->getQuantity(), 0.0001);
        self::assertSame(0, OrderHistoryQuery::create()->filterByOrderId($order->getId())->filterByEventType(OrderHistoryEventType::ORDER_EDITED->value)->count());

        $editor->edit($order, $edit);
        $tax = 0;
        $order->clearOrderProducts();
        \Thelia\Model\Order::forgetCachedTotals((int) $order->getId());

        self::assertSame($preview->after->total, number_format($order->getTotalAmount($tax, true, true), 6, '.', ''), 'The simulated total must be the total the real edit gives.');
        self::assertSame($preview->after->tax, number_format($tax, 6, '.', ''));
    }

    public function testASimulationRefusesWhatTheEditRefusesAndLeavesTheOrderUntouched(): void
    {
        [$order, $line] = $this->aPaidOrderWithOneLine();

        $editor = $this->getService(OrderEditor::class);
        $state = $editor->stateOf($order);

        try {
            $editor->simulate($order, new OrderEdit(quantities: [(int) $line->getId() => 5.0], discount: '99999.000000'));
            self::fail('The discount above the total must refuse the simulation.');
        } catch (OrderEditRefusedException $refusal) {
            self::assertStringContainsString('('.OrderEditRefusal::InvalidDiscount->value.')', $refusal->getMessage());
        }

        self::assertSame($state, $editor->stateOf(OrderQuery::create()->findPk($order->getId())));
        self::assertSame($state, $editor->stateOf($order));
    }

    public function testAnotherConnectionCannotLockTheOrderWhileAnEditIsRunning(): void
    {
        [$order, $line] = $this->aPaidOrderWithOneLine();
        $other = $this->anotherConnection();
        $blockedDuringTheEdit = null;

        $probe = function () use ($other, $order, &$blockedDuringTheEdit): void {
            $blockedDuringTheEdit = $this->lockIsRefused($other, (int) $order->getId());
        };

        $dispatcher = $this->getService(\Symfony\Component\EventDispatcher\EventDispatcherInterface::class);
        $dispatcher->addListener(\Thelia\Core\Event\TheliaEvents::ORDER_BEFORE_EDIT, $probe);

        try {
            $this->getService(OrderEditor::class)->edit($order, new OrderEdit(quantities: [(int) $line->getId() => 5.0]));
        } finally {
            $dispatcher->removeListener(\Thelia\Core\Event\TheliaEvents::ORDER_BEFORE_EDIT, $probe);
        }

        self::assertTrue($blockedDuringTheEdit, 'While the edit runs, a second connection must not obtain the lock of the order.');
        self::assertFalse($this->lockIsRefused($other, (int) $order->getId()), 'Once the edit is committed the lock must be released.');
    }

    public function testAnEditWaitsForTheLockAnotherConnectionHoldsAndWritesNothingWhenItGivesUp(): void
    {
        [$order, $line, $pse] = $this->aPaidOrderWithOneLine();
        $other = $this->anotherConnection();
        $stock = (float) ProductSaleElementsQuery::create()->findPk($pse->getId())->getQuantity();

        $other->beginTransaction();
        $other->prepare('SELECT `id` FROM `order` WHERE `id` = :id FOR UPDATE')->execute(['id' => $order->getId()]);

        $this->getPropelConnection()->exec('SET SESSION innodb_lock_wait_timeout = 1');

        try {
            $this->getService(OrderEditor::class)->edit($order, new OrderEdit(quantities: [(int) $line->getId() => 5.0]));
            self::fail('An edit must wait for the lock held on the order and give up when it is not released.');
        } catch (\Throwable $timeout) {
            self::assertStringContainsString('Lock wait timeout', $timeout->getMessage().($timeout->getPrevious()?->getMessage() ?? ''));
        } finally {
            $other->rollBack();
            $this->getPropelConnection()->exec('SET SESSION innodb_lock_wait_timeout = 50');
        }

        self::assertEqualsWithDelta(2.0, (float) OrderProductQuery::create()->findPk($line->getId())->getQuantity(), 0.0001);
        self::assertEqualsWithDelta($stock, (float) ProductSaleElementsQuery::create()->findPk($pse->getId())->getQuantity(), 0.0001);
        self::assertSame(0, OrderHistoryQuery::create()->filterByOrderId($order->getId())->filterByEventType(OrderHistoryEventType::ORDER_EDITED->value)->count());
    }

    private function anotherConnection(): \PDO
    {
        $configuration = \Propel\Runtime\Propel::getServiceContainer()->getConnectionManager('TheliaMain')->getConfiguration();
        $connection = new \PDO((string) $configuration['dsn'], (string) $configuration['user'], (string) $configuration['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $connection->exec('SET SESSION innodb_lock_wait_timeout = 1');

        return $connection;
    }

    private function lockIsRefused(\PDO $connection, int $orderId): bool
    {
        $connection->beginTransaction();

        try {
            $connection->prepare('SELECT `id` FROM `order` WHERE `id` = :id FOR UPDATE')->execute(['id' => $orderId]);

            return false;
        } catch (\PDOException $exception) {
            return 1205 === ($exception->errorInfo[1] ?? null);
        } finally {
            $connection->rollBack();
        }
    }

    /**
     * @return array{0: \Thelia\Model\Order, 1: \Thelia\Model\OrderProduct, 2: \Thelia\Model\ProductSaleElements}
     */
    private function aPaidOrderWithOneLine(): array
    {
        $factory = $this->createFixtureFactory();
        $order = $factory->order(null, ['statusCode' => OrderStatus::CODE_PAID]);

        $product = $factory->product($factory->category(), $factory->taxRule(['isDefault' => false]), $factory->currency(), ['title' => 'Widget', 'baseQuantity' => 10]);
        $pse = $product->getDefaultSaleElements();
        $line = $factory->orderProduct($order, [
            'productRef' => $product->getRef(),
            'productSaleElementsRef' => $pse->getRef(),
            'productSaleElementsId' => $pse->getId(),
            'quantity' => 2.0,
            'price' => '10.000000',
            'promoPrice' => '10.000000',
        ]);

        return [$order, $line, $pse];
    }
}
