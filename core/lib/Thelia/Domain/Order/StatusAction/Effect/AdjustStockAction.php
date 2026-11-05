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

namespace Thelia\Domain\Order\StatusAction\Effect;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Domain\Catalog\Stock\StockLevelAnnouncer;
use Thelia\Domain\Catalog\Stock\StockReservationService;
use Thelia\Domain\Order\Exception\InvalidOrderStatusActionPayloadException;
use Thelia\Domain\Order\Exception\StockTakeRefusedException;
use Thelia\Domain\Order\Service\PackPreorder;
use Thelia\Domain\Order\Service\PaymentStockLock;
use Thelia\Domain\Order\Service\StockDecrementer;
use Thelia\Domain\Order\Service\StockMovement;
use Thelia\Domain\Order\Service\StockTake;
use Thelia\Domain\Order\StatusAction\OrderStatusActionContext;
use Thelia\Domain\Order\StatusAction\OrderStatusActionInterface;
use Thelia\Domain\Order\StatusAction\OrderStatusActionPayloadField;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\OrderProduct;

/**
 * Puts the ordered quantities back in stock, or takes them out, on a transition
 * the merchant chose. The core keeps handling the stock of the standard
 * paid/unpaid changes by itself; this action covers the custom flows.
 */
final readonly class AdjustStockAction implements OrderStatusActionInterface
{
    public const FIELD_OPERATION = 'operation';
    public const OPERATION_INCREASE = 'increase';
    public const OPERATION_DECREASE = 'decrease';

    /** Undoes the movements of ONE run of this action inside a transaction the caller owns. */
    private const SAVEPOINT = 'thelia_adjust_stock';

    public function __construct(
        private StockDecrementer $stockDecrementer,
        private ?StockReservationService $stockReservation = null,
        private ?StockLevelAnnouncer $stockLevelAnnouncer = null,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    public static function getType(): string
    {
        return 'adjust_stock';
    }

    public function describePayload(): array
    {
        return [
            OrderStatusActionPayloadField::choice(self::FIELD_OPERATION, 'Stock operation', [
                self::OPERATION_INCREASE => 'Put the ordered quantities back in stock',
                self::OPERATION_DECREASE => 'Take the ordered quantities out of stock',
            ]),
        ];
    }

    public function normalizePayload(array $payload): array
    {
        if ([] !== array_diff(array_keys($payload), [self::FIELD_OPERATION])) {
            throw InvalidOrderStatusActionPayloadException::unexpectedFields(self::getType(), $payload, [self::FIELD_OPERATION]);
        }

        $operation = $payload[self::FIELD_OPERATION] ?? null;

        if (!\in_array($operation, [self::OPERATION_INCREASE, self::OPERATION_DECREASE], true)) {
            throw InvalidOrderStatusActionPayloadException::invalidValue(self::getType(), self::FIELD_OPERATION, 'must be "increase" or "decrease"');
        }

        return [self::FIELD_OPERATION => $operation];
    }

    public function execute(OrderStatusActionContext $context): void
    {
        $increase = self::OPERATION_INCREASE === $context->payload[self::FIELD_OPERATION];
        $checkAvailableStock = ConfigQuery::checkAvailableStock();

        $connection = Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME);

        // Read before the transaction, whose first statement locks the stock rows (takeOut()). In
        // the ascending id of the sale element, the order the placement locked the stock rows in.
        $orderProducts = $context->order->getOrderProducts()->getData();
        usort($orderProducts, static fn (OrderProduct $first, OrderProduct $second): int => (int) $first->getProductSaleElementsId() <=> (int) $second->getProductSaleElementsId());

        // The sale element of a pack holds no stock: its component lines carry the movement.
        $movingLines = array_values(array_filter(
            $orderProducts,
            static fn (OrderProduct $orderProduct): bool => null !== $orderProduct->getProductSaleElementsId() && (float) $orderProduct->getQuantity() > 0 && $orderProduct->movesStockOfItsOwn(),
        ));

        // The order is adjusted as a whole or not at all, WHATEVER the surrounding transaction.
        // The status change itself is dispatched inside one (the back office as well as the admin
        // API), so this action rarely owns it; a nested rollBack would poison the caller's commit,
        // and doing nothing left the products adjusted before the failing one decremented for good.
        // A savepoint undoes exactly what this action wrote, and nothing of what the caller wrote.
        $ownTransaction = !$connection->inTransaction();

        if ($ownTransaction) {
            $connection->beginTransaction();
        } else {
            $connection->exec('SAVEPOINT '.self::SAVEPOINT);
        }

        // The stock of the sale unit the order was placed on, when the unit keeps a row
        // for the sale element: the same rule the checkout decremented by.
        $saleUnitId = $context->order->getSaleUnitId();

        try {
            $changes = $increase
                ? $this->putBack($movingLines, $saleUnitId, $connection)
                : $this->takeOut($context, $movingLines, $saleUnitId, $checkAvailableStock, $connection);

            if ($ownTransaction) {
                $connection->commit();
            } else {
                $connection->exec('RELEASE SAVEPOINT '.self::SAVEPOINT);
            }
        } catch (\Throwable $throwable) {
            if ($ownTransaction) {
                $connection->rollBack();
            } else {
                $connection->exec('ROLLBACK TO SAVEPOINT '.self::SAVEPOINT);
            }

            throw $throwable;
        }

        // Once written: inside the transaction of a caller, when that one is over, never when it
        // is rolled back. Without an announcer, the changes are told at once.
        if (null !== $this->stockLevelAnnouncer) {
            $this->stockLevelAnnouncer->announceChanges($changes);
        } elseif (null !== $this->dispatcher) {
            StockLevelAnnouncer::tell($this->dispatcher, $changes);
        }
    }

    /**
     * @param list<OrderProduct> $lines
     *
     * @return list<StockLevelChange> what went back to the shared counter of each sale element
     */
    private function putBack(array $lines, ?int $saleUnitId, ConnectionInterface $connection): array
    {
        /** @var array<int, float> $putBack on the shared counter, by sale element id */
        $putBack = [];

        foreach ($lines as $orderProduct) {
            $productSaleElementsId = (int) $orderProduct->getProductSaleElementsId();
            $quantity = (float) $orderProduct->getQuantity();

            $this->stockDecrementer->increment($productSaleElementsId, $quantity, $connection, $saleUnitId);

            if (!$this->stockDecrementer->movesSaleUnitStock($saleUnitId, $productSaleElementsId)) {
                $putBack[$productSaleElementsId] = ($putBack[$productSaleElementsId] ?? 0.0) + $quantity;
            }
        }

        if ([] === $putBack || (null === $this->stockLevelAnnouncer && null === $this->dispatcher)) {
            return [];
        }

        // Our own UPDATEs hold the rows: what is read now is what they left.
        $statement = $connection->prepare('SELECT `id`, `quantity` FROM `product_sale_elements` WHERE `id` IN ('.implode(',', array_keys($putBack)).')');
        $statement->execute();
        $changes = [];

        foreach ($statement->fetchAll(\PDO::FETCH_NUM) as [$id, $quantity]) {
            $changes[] = new StockLevelChange((int) $id, (float) $quantity - $putBack[(int) $id], (float) $quantity);
        }

        return $changes;
    }

    /**
     * Takes the lines out of the stock the way a payment does: the stock rows locked first when the
     * shop reserves its stock, what the other carts hold left to them, and a line taken below zero
     * frozen as a preorder. Inside the transaction of a caller the rows were locked by the caller
     * before it read anything, as the admin API does for the actions of the transition
     * (PaymentStockLock::planFor()); locked again here, which waits on nothing. Any other caller that
     * opens a transaction and reads before the dispatch still locks after its own reads.
     *
     * @param list<OrderProduct> $lines
     *
     * @return list<StockLevelChange> what was taken from the shared counter of each sale element
     */
    private function takeOut(OrderStatusActionContext $context, array $lines, ?int $saleUnitId, bool $checkAvailableStock, ConnectionInterface $connection): array
    {
        $productSaleElementsIds = PaymentStockLock::stockRowsOf($lines);
        $cartId = (int) $context->order->getCartId();

        $this->stockReservation?->lockStockRows($productSaleElementsIds, $connection);
        $reservedByOtherCarts = $checkAvailableStock && null !== $this->stockReservation
            ? $this->stockReservation->reservedByOtherCartsOf($productSaleElementsIds, $saleUnitId, $cartId > 0 ? $cartId : null, $connection)
            : [];

        $takes = [];

        foreach ($lines as $index => $orderProduct) {
            $productSaleElementsId = (int) $orderProduct->getProductSaleElementsId();
            $takes[$index] = new StockTake($productSaleElementsId, (float) $orderProduct->getQuantity(), $checkAvailableStock, $reservedByOtherCarts[$productSaleElementsId] ?? 0.0);
        }

        try {
            $movements = $this->stockDecrementer->decrementAll($takes, !$checkAvailableStock, $connection, $saleUnitId);
        } catch (StockTakeRefusedException $shortage) {
            throw new TheliaProcessException($lines[$shortage->takeKey]->getProductSaleElementsRef().' : Not enough stock', previous: $shortage);
        }

        /** @var array<int, StockLevelChange> $changes */
        $changes = [];
        /** @var array<int, list<StockMovement|null>> $componentMovementsByPack */
        $componentMovementsByPack = [];

        foreach ($lines as $index => $orderProduct) {
            $movement = $movements[$index] ?? null;

            if (true === $movement?->preorder && 1 !== (int) $orderProduct->getIsPreorder()) {
                $orderProduct->setIsPreorder(1)->setRestockDate($movement->restockDate)->save($connection);
            }

            if (null !== $orderProduct->getParent()) {
                $componentMovementsByPack[(int) $orderProduct->getParent()][] = $movement;
            }

            if (null !== $movement && !$movement->onSaleUnitStock()) {
                $changes[$movement->productSaleElementsId] = new StockLevelChange(
                    $movement->productSaleElementsId,
                    $changes[$movement->productSaleElementsId]->previousQuantity ?? $movement->previousQuantity,
                    $movement->newQuantity,
                );
            }
        }

        // A pack is a preorder when one of its components is, and waits for the latest date.
        foreach ($context->order->getOrderProducts() as $orderProduct) {
            if (!isset($componentMovementsByPack[(int) $orderProduct->getId()])) {
                continue;
            }

            $packPreorder = PackPreorder::of($componentMovementsByPack[(int) $orderProduct->getId()]);

            if ($packPreorder['isPreorder'] && 1 !== (int) $orderProduct->getIsPreorder()) {
                $orderProduct->setIsPreorder(1)->setRestockDate($packPreorder['restockDate'])->save($connection);
            }
        }

        return array_values($changes);
    }
}
