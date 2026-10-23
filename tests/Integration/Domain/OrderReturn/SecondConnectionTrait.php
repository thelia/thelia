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

namespace Thelia\Tests\Integration\Domain\OrderReturn;

use Propel\Runtime\Connection\ConnectionFactory;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;

/**
 * A second connection standing for a second request, to check that a line the
 * code under test locked cannot be taken by another one.
 *
 * The line has to be committed for that second connection to see it, so it is
 * written outside the test transaction and deleted by hand: the test calls
 * deleteTheCommittedLines() whatever its teardown does, and
 * deleteTheLinesAKilledTestLeft() before it starts, for a process killed in the
 * middle of one (an Infection timeout).
 *
 * A test that runs outside the test transaction altogether, to have its own
 * requests wait on other sessions, commits whole orders: it hands their ids to
 * deleteTheCommittedOrders() in its teardown.
 */
trait SecondConnectionTrait
{
    private const string COMMITTED_LINE_REF_PREFIX = 'LOCK-TEST-';

    /** Lock wait timeout (MariaDB, which also answers NOWAIT with it) and NOWAIT refusal (MySQL 8). */
    private const array LOCK_REFUSED_ERROR_CODES = [1205, 3572];

    private ?ConnectionInterface $secondConnection = null;

    /** @var list<int> */
    private array $committedOrderProductIds = [];

    /**
     * A line committed outside the test transaction, which the second connection
     * can see. The order it points to is not committed, hence the foreign key
     * check left off for this one insert.
     */
    private function committedOrderProduct(Order $order, float $quantity): OrderProduct
    {
        $connection = $this->secondConnection();
        $connection->exec('SET SESSION foreign_key_checks = 0');
        $connection->prepare(
            'INSERT INTO order_product (order_id, product_ref, product_sale_elements_ref, title, quantity, price, promo_price, was_new, was_in_promo, created_at, updated_at)'
            .' VALUES (:order_id, :ref, :ref, :title, :quantity, 10, 10, 1, 0, NOW(), NOW())'
        )->execute([
            ':order_id' => $order->getId(),
            ':ref' => uniqid(self::COMMITTED_LINE_REF_PREFIX),
            ':title' => 'A returnable product',
            ':quantity' => $quantity,
        ]);
        $orderProductId = (int) $connection->lastInsertId();
        $this->committedOrderProductIds[] = $orderProductId;
        $connection->exec('SET SESSION foreign_key_checks = 1');

        $orderProduct = OrderProductQuery::create()->findPk($orderProductId, $connection);
        self::assertNotNull($orderProduct);

        return $orderProduct;
    }

    private function aSecondRequestCanTakeTheLine(OrderProduct $orderProduct): bool
    {
        try {
            $this->secondConnection()
                ->prepare('SELECT id FROM order_product WHERE id = :id FOR UPDATE NOWAIT')
                ->execute([':id' => $orderProduct->getId()]);
        } catch (\PDOException $refused) {
            if (!\in_array($refused->errorInfo[1] ?? null, self::LOCK_REFUSED_ERROR_CODES, true)) {
                throw $refused;
            }

            return false;
        }

        return true;
    }

    private function deleteTheCommittedLines(): void
    {
        try {
            foreach ($this->committedOrderProductIds as $orderProductId) {
                $this->secondConnection?->prepare('DELETE FROM order_product WHERE id = :id')->execute([':id' => $orderProductId]);
            }
        } finally {
            $this->committedOrderProductIds = [];
            $this->secondConnection = null;
        }
    }

    private function deleteTheLinesAKilledTestLeft(): void
    {
        $this->secondConnection()
            ->prepare('DELETE FROM order_product WHERE product_ref LIKE :prefix AND order_id NOT IN (SELECT id FROM `order`)')
            ->execute([':prefix' => self::COMMITTED_LINE_REF_PREFIX.'%']);
    }

    /**
     * Deletes, in foreign key order, orders a test committed for real together
     * with everything hanging from them: their returns, their lines, their cart,
     * their addresses and their customer. Nothing rolls them back on their own.
     *
     * @param list<int> $orderIds
     */
    private function deleteTheCommittedOrders(array $orderIds): void
    {
        $connection = $this->secondConnection();

        foreach ($orderIds as $orderId) {
            $read = $connection->prepare('SELECT customer_id, cart_id, invoice_order_address_id, delivery_order_address_id FROM `order` WHERE id = :id');
            $read->execute([':id' => $orderId]);
            $order = $read->fetch(\PDO::FETCH_ASSOC);
            $read->closeCursor();

            // The lines of a return go with it, the lines and the history of an
            // order with it.
            $connection->prepare('DELETE FROM order_return WHERE order_id = :id')->execute([':id' => $orderId]);
            $connection->prepare('DELETE FROM `order` WHERE id = :id')->execute([':id' => $orderId]);

            if (!\is_array($order)) {
                continue;
            }

            $connection->prepare('DELETE FROM cart WHERE id = :id')->execute([':id' => $order['cart_id']]);
            $connection->prepare('DELETE FROM order_address WHERE id IN (:invoice, :delivery)')
                ->execute([':invoice' => $order['invoice_order_address_id'], ':delivery' => $order['delivery_order_address_id']]);
            $connection->prepare('DELETE FROM customer WHERE id = :id')->execute([':id' => $order['customer_id']]);
        }
    }

    /**
     * What a connection of its own is opened from: the configuration Propel
     * already holds, with the DSN, the user and the password of the test
     * database.
     *
     * @return array{dsn: string, user?: string, password?: string}
     */
    private function secondConnectionConfiguration(): array
    {
        $connectionManager = Propel::getServiceContainer()->getConnectionManager(OrderProductTableMap::DATABASE_NAME);

        if (!$connectionManager instanceof ConnectionManagerSingle) {
            self::fail(\sprintf(
                'A second connection is opened from a single-connection manager, the test database is served by %s.',
                get_debug_type($connectionManager),
            ));
        }

        return $connectionManager->getConfiguration();
    }

    /**
     * A connection of its own, opened from the configuration Propel already holds:
     * Propel hands out one connection per datasource, and the test transaction is
     * on it.
     */
    private function secondConnection(): ConnectionInterface
    {
        if (null !== $this->secondConnection) {
            return $this->secondConnection;
        }

        return $this->secondConnection = ConnectionFactory::create(
            $this->secondConnectionConfiguration(),
            Propel::getServiceContainer()->getAdapter(OrderProductTableMap::DATABASE_NAME),
        );
    }
}
