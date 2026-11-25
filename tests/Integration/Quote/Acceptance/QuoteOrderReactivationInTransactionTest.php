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

namespace Thelia\Tests\Integration\Quote\Acceptance;

use PHPUnit\Framework\Attributes\Group;
use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Service\ConsentProvider;
use Thelia\Domain\Order\Exception\OrderStatusRefusedByQuoteException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\Quote;

/**
 * An order that was cancelled comes back to life while the database says something else than the
 * view this process holds: the transaction that surrounds the status change was opened, and its
 * view fixed, before another session brought an order of the same quote back to life; or another
 * session gave the order a quote of its own meanwhile. The order of the quote that is alive is
 * never detached, and nothing is written when the link cannot be taken back.
 *
 * The other session has to commit for this process to be unable to see it, so what these tests
 * write is committed and taken back by hand: every row they added is deleted, and the settings, the
 * consents and the sequences are put back as they were.
 */
#[Group('concurrency')]
final class QuoteOrderReactivationInTransactionTest extends QuoteAcceptanceTestCase
{
    protected bool $useTransaction = false;

    private ?ConnectionInterface $connection = null;

    private ?ConnectionInterface $otherSession = null;

    /** @var array<string, int> the greatest id of every table that has one, before the test */
    private array $watermarks = [];

    /** @var array<string, string|null> */
    private array $configBefore = [];

    /** @var array<int, int> */
    private array $consentsBefore = [];

    /** @var array<string, int> */
    private array $sequencesBefore = [];

    protected function setUp(): void
    {
        // What the setup of the scenario writes is remembered before it is written.
        self::bootKernel();
        $this->connection = Propel::getWriteConnection(QuoteTableMap::DATABASE_NAME);
        $this->rememberTheDatabase();

        parent::setUp();

        $this->otherSession = $this->openOtherSession();
    }

    protected function tearDown(): void
    {
        try {
            while ($this->connection?->inTransaction()) {
                $this->connection->rollBack();
            }

            $this->takeBackWhatTheTestWrote();
        } finally {
            Propel::disableInstancePooling();

            parent::tearDown();
        }
    }

    public function testAnOrderBroughtBackToLifeInAnOpenTransactionDoesNotDetachTheOrderThatCameToLifeSinceItsViewWasFixed(): void
    {
        [$quote, $first, $second] = $this->twoCancelledOrdersTheSecondOneHoldingTheQuote();
        $connection = $this->connection;
        \assert($connection instanceof ConnectionInterface);

        $connection->beginTransaction();
        // The view of the transaction is fixed here: the second order is cancelled in it.
        $connection->query('SELECT COUNT(*) FROM `order`')->fetchColumn();
        $this->otherSession?->exec('UPDATE `order` SET `status_id` = '.$this->notPaidStatusId().' WHERE `id` = '.(int) $second->getId());

        try {
            $this->bringBackToLife($first);
            self::fail('The second order is alive again: the first one cannot take the quote.');
        } catch (OrderStatusRefusedByQuoteException $refusal) {
            self::assertSame($first->getRef(), $refusal->getOrderRef());
        } finally {
            $connection->rollBack();
        }

        self::assertSame($quote->getId(), $this->quoteIdOf($second), 'the order that is alive keeps the quote');
        self::assertNull($this->quoteIdOf($first));
        self::assertSame(1, OrderQuery::create()->filterByQuoteId($quote->getId())->count());
    }

    public function testNothingIsDetachedWhenTheLinkCannotBeTakenBack(): void
    {
        [$quote, $first, $second] = $this->twoCancelledOrdersTheSecondOneHoldingTheQuote();
        $another = $this->quotedScenario()['quote'];
        $stale = $this->fresh($first);
        self::assertNull($stale->getQuoteId());
        // Another session gave the order a quote of its own after this one read it.
        $this->otherSession?->exec('UPDATE `order` SET `quote_id` = '.(int) $another->getId().' WHERE `id` = '.(int) $first->getId());

        try {
            $this->bringBackToLife($stale);
            self::fail('The order holds another quote: it cannot take this one.');
        } catch (OrderStatusRefusedByQuoteException $refusal) {
            self::assertSame($first->getRef(), $refusal->getOrderRef());
        }

        self::assertSame($quote->getId(), $this->quoteIdOf($second), 'the order that held the quote was not let go');
        self::assertSame($another->getId(), $this->quoteIdOf($first));
    }

    /**
     * Two orders of one quote, both cancelled: the first let go of the quote when the second was
     * begun, the second holds it.
     *
     * @return array{Quote, Order, Order}
     */
    private function twoCancelledOrdersTheSecondOneHoldingTheQuote(): array
    {
        $dispatcher = $this->getService(EventDispatcherInterface::class);
        $scenario = $this->quotedScenario();
        $quote = $scenario['quote'];
        $cart = $this->quotes->accept($quote, $scenario['buyer'], 2);
        $first = $this->pay($cart);
        $first->setCancelled($dispatcher);
        $second = $this->pay($cart);
        $second->setCancelled($dispatcher);

        self::assertNull($this->quoteIdOf($first), 'the first order let go of the quote');
        self::assertSame($quote->getId(), $this->quoteIdOf($second));

        return [$quote, $this->fresh($first), $this->fresh($second)];
    }

    private function bringBackToLife(Order $order): void
    {
        $event = (new OrderEvent($order))->setStatus($this->notPaidStatusId());

        $this->getService(EventDispatcherInterface::class)->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
    }

    private function notPaidStatusId(): int
    {
        return (int) OrderStatusQuery::getNotPaidStatus()?->getId();
    }

    private function quoteIdOf(Order $order): ?int
    {
        return $this->fresh($order)->getQuoteId();
    }

    private function fresh(Order $order): Order
    {
        OrderTableMap::clearInstancePool();

        return OrderQuery::create()->findPk($order->getId()) ?? throw new \LogicException('The order is gone.');
    }

    private function rememberTheDatabase(): void
    {
        $connection = $this->connection;
        \assert($connection instanceof ConnectionInterface);

        /** @var list<string> $tables */
        $tables = $connection->query("SELECT DISTINCT `TABLE_NAME` FROM information_schema.COLUMNS WHERE `TABLE_SCHEMA` = DATABASE() AND `COLUMN_NAME` = 'id' AND `COLUMN_KEY` = 'PRI'")->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $this->watermarks[$table] = (int) $connection->query('SELECT COALESCE(MAX(`id`), 0) FROM `'.$table.'`')->fetchColumn();
        }

        foreach ($connection->query('SELECT `name`, `value` FROM `config`')->fetchAll(\PDO::FETCH_NUM) as [$name, $value]) {
            $this->configBefore[(string) $name] = null === $value ? null : (string) $value;
        }

        foreach ($connection->query('SELECT `id`, `active` FROM `consent`')->fetchAll(\PDO::FETCH_NUM) as [$id, $active]) {
            $this->consentsBefore[(int) $id] = (int) $active;
        }

        foreach ($connection->query('SELECT `name`, `current_value` FROM `order_sequence`')->fetchAll(\PDO::FETCH_NUM) as [$name, $value]) {
            $this->sequencesBefore[(string) $name] = (int) $value;
        }
    }

    /**
     * Every row the test added is deleted, children before parents (a table that cannot be emptied
     * yet is tried again after the others), then the settings are put back.
     */
    private function takeBackWhatTheTestWrote(): void
    {
        $connection = $this->connection;

        if (!$connection instanceof ConnectionInterface || [] === $this->watermarks) {
            return;
        }

        $pending = array_keys($this->watermarks);

        for ($round = 0; $round < 10 && [] !== $pending; ++$round) {
            $stuck = [];

            foreach ($pending as $table) {
                try {
                    $connection->exec('DELETE FROM `'.$table.'` WHERE `id` > '.$this->watermarks[$table]);
                } catch (\Throwable) {
                    $stuck[] = $table;
                }
            }

            $pending = $stuck;
        }

        if ([] !== $pending) {
            $connection->exec('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($pending as $table) {
                $connection->exec('DELETE FROM `'.$table.'` WHERE `id` > '.$this->watermarks[$table]);
            }

            $connection->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        foreach ($this->configBefore as $name => $value) {
            $statement = $connection->prepare('UPDATE `config` SET `value` = :value WHERE `name` = :name AND NOT (`value` <=> :same)');
            $statement->bindValue(':value', $value);
            $statement->bindValue(':same', $value);
            $statement->bindValue(':name', $name);
            $statement->execute();
        }

        foreach ($this->consentsBefore as $id => $active) {
            $connection->exec('UPDATE `consent` SET `active` = '.$active.' WHERE `id` = '.$id);
        }

        foreach ($this->sequencesBefore as $name => $value) {
            $statement = $connection->prepare('UPDATE `order_sequence` SET `current_value` = :value WHERE `name` = :name');
            $statement->bindValue(':value', $value, \PDO::PARAM_INT);
            $statement->bindValue(':name', $name);
            $statement->execute();
        }

        $known = implode(',', array_map(static fn (string $name): string => "'".str_replace("'", "''", $name)."'", array_keys($this->sequencesBefore)));
        $connection->exec('DELETE FROM `order_sequence` WHERE '.('' === $known ? '1 = 1' : '`name` NOT IN ('.$known.')'));

        ConfigQuery::resetCache();
        $this->getService(ConsentProvider::class)->forgetCache();
    }

    private function openOtherSession(): ConnectionInterface
    {
        $serviceContainer = Propel::getServiceContainer();
        $connectionManager = $serviceContainer->getConnectionManager(QuoteTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::markTestSkipped('The test database is not served by a single-connection manager.');
        }

        return ConnectionFactory::create(
            $connectionManager->getConfiguration(),
            $serviceContainer->getAdapter(QuoteTableMap::DATABASE_NAME),
        );
    }
}
