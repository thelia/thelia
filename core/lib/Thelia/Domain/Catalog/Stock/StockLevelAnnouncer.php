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

namespace Thelia\Domain\Catalog\Stock;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Core\Event\ProductSaleElement\SaleUnitStockChangedEvent;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\SaleUnitProductSaleElementsQuery;

/**
 * Tells the listeners of the stock levels what a change of stock did, once the change is written
 * for good.
 *
 * A change written while the caller holds a transaction open is not told at once, nothing saying it
 * will be committed, and is not dropped either: it waits, and goes out when the transaction is over
 * (the end of the request, of the command, of the message, handled or failed), from the stock
 * before the change to the stock read back then. A stock that reads back what it read before was
 * rolled back, or moved back since, and is never told. A change of the same sale element told
 * after that transaction, before the end, takes the waiting one over: one change, from the stock
 * before the first. A failing listener is logged; the stock stays changed.
 *
 * The national counter of a sale element is told on PRODUCT_SALE_ELEMENTS_STOCK_CHANGED, the own
 * stock a sale unit keeps for it on SALE_UNIT_STOCK_CHANGED: two stocks, never mixed in one event.
 */
final class StockLevelAnnouncer implements EventSubscriberInterface, ResetInterface
{
    /** @var array<int, float> by sale element id, the first stock before a change still waiting */
    private array $waiting = [];

    /** @var array<string, StockLevelChange> by sale unit and sale element, the first stock before a change still waiting */
    private array $waitingOnSaleUnits = [];

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CallerTransactionInterface $callerTransaction,
    ) {
    }

    /**
     * @param int|null $saleUnitId the sale unit whose own stock changed; null for the national counter
     */
    public function announce(int $productSaleElementsId, float $before, float $after, ?int $saleUnitId = null): void
    {
        $this->announceChanges([new StockLevelChange($productSaleElementsId, $before, $after, $saleUnitId)]);
    }

    /**
     * The changes of one write (an order, a change of status), told in one event, or kept until the
     * transaction of the caller is over.
     *
     * @param list<StockLevelChange> $changes
     */
    public function announceChanges(array $changes): void
    {
        $changes = self::actualChanges($changes);

        if ([] === $changes) {
            return;
        }

        if ($this->callerTransaction->isOpen(Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME))) {
            foreach ($changes as $change) {
                if ($change->onSaleUnitStock()) {
                    $this->waitingOnSaleUnits[self::saleUnitKey($change)] ??= $change;
                } else {
                    $this->waiting[$change->productSaleElementsId] ??= $change->previousQuantity;
                }
            }

            return;
        }

        // A sale element whose change still waits for a committed transaction: told once, from the
        // stock before the change that waited, never the two apart.
        foreach ($changes as $index => $change) {
            if ($change->onSaleUnitStock()) {
                $key = self::saleUnitKey($change);

                if (isset($this->waitingOnSaleUnits[$key])) {
                    $changes[$index] = new StockLevelChange($change->productSaleElementsId, $this->waitingOnSaleUnits[$key]->previousQuantity, $change->newQuantity, $change->saleUnitId);
                    unset($this->waitingOnSaleUnits[$key]);
                }

                continue;
            }

            if (isset($this->waiting[$change->productSaleElementsId])) {
                $changes[$index] = new StockLevelChange($change->productSaleElementsId, $this->waiting[$change->productSaleElementsId], $change->newQuantity);
                unset($this->waiting[$change->productSaleElementsId]);
            }
        }

        self::tell($this->dispatcher, $changes);
    }

    /**
     * Tells the changes that waited for a transaction, now that it is over; those of a transaction
     * still open keep waiting.
     */
    public function flush(): void
    {
        if (([] === $this->waiting && [] === $this->waitingOnSaleUnits) || $this->callerTransaction->isOpen(Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME))) {
            return;
        }

        $waiting = $this->waiting;
        $waitingOnSaleUnits = $this->waitingOnSaleUnits;
        $this->waiting = [];
        $this->waitingOnSaleUnits = [];

        self::tell($this->dispatcher, [...$this->readBack($waiting), ...$this->readBackOnSaleUnits($waitingOnSaleUnits)]);
    }

    /**
     * @param array<int, float> $waiting
     *
     * @return list<StockLevelChange>
     */
    private function readBack(array $waiting): array
    {
        if ([] === $waiting) {
            return [];
        }

        /** @var list<array{Id: int|string, Quantity: int|string|float|null}> $rows */
        $rows = ProductSaleElementsQuery::create()
            ->filterById(array_keys($waiting), Criteria::IN)
            ->select(['Id', 'Quantity'])
            ->find()
            ->getData();
        $changes = [];

        foreach ($rows as $row) {
            $id = (int) $row['Id'];
            $stored = (float) $row['Quantity'];

            // What the stock reads now, not what the change wrote: another write may have
            // followed it. The same as before: rolled back, nothing to tell.
            if (isset($waiting[$id]) && $stored !== $waiting[$id]) {
                $changes[] = new StockLevelChange($id, $waiting[$id], $stored);
            }
        }

        return $changes;
    }

    /**
     * The own stock of each sale unit read back, as readBack() does for the national counters.
     *
     * @param array<string, StockLevelChange> $waiting
     *
     * @return list<StockLevelChange>
     */
    private function readBackOnSaleUnits(array $waiting): array
    {
        if ([] === $waiting) {
            return [];
        }

        /** @var list<array{SaleUnitId: int|string, ProductSaleElementsId: int|string, Quantity: int|string|float|null}> $rows */
        $rows = SaleUnitProductSaleElementsQuery::create()
            ->filterBySaleUnitId(array_values(array_unique(array_map(static fn (StockLevelChange $change): ?int => $change->saleUnitId, $waiting))), Criteria::IN)
            ->filterByProductSaleElementsId(array_values(array_unique(array_map(static fn (StockLevelChange $change): int => $change->productSaleElementsId, $waiting))), Criteria::IN)
            ->select(['SaleUnitId', 'ProductSaleElementsId', 'Quantity'])
            ->find()
            ->getData();
        $changes = [];

        foreach ($rows as $row) {
            $first = $waiting[$row['SaleUnitId'].':'.$row['ProductSaleElementsId']] ?? null;
            $stored = (float) $row['Quantity'];

            if (null !== $first && $stored !== $first->previousQuantity) {
                $changes[] = new StockLevelChange($first->productSaleElementsId, $first->previousQuantity, $stored, $first->saleUnitId);
            }
        }

        return $changes;
    }

    public function reset(): void
    {
        $this->waiting = [];
        $this->waitingOnSaleUnits = [];
    }

    /**
     * Tells the changes now, in one event, a failing listener logged. For a caller built without the
     * announcer: its changes are told at once, never kept.
     *
     * @param list<StockLevelChange> $changes
     */
    public static function tell(EventDispatcherInterface $dispatcher, array $changes): void
    {
        $changes = self::actualChanges($changes);

        if ([] === $changes) {
            return;
        }

        $national = array_values(array_filter($changes, static fn (StockLevelChange $change): bool => !$change->onSaleUnitStock()));
        $onSaleUnits = array_values(array_filter($changes, static fn (StockLevelChange $change): bool => $change->onSaleUnitStock()));

        if ([] !== $national) {
            self::dispatchSafely($dispatcher, new StockLevelChangedEvent($national), TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED);
        }

        if ([] !== $onSaleUnits) {
            self::dispatchSafely($dispatcher, new SaleUnitStockChangedEvent($onSaleUnits), TheliaEvents::SALE_UNIT_STOCK_CHANGED);
        }
    }

    private static function dispatchSafely(EventDispatcherInterface $dispatcher, StockLevelChangedEvent $event, string $eventName): void
    {
        try {
            $dispatcher->dispatch($event, $eventName);
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->addError('A listener of the stock levels failed after a change of stock: '.$throwable->getMessage(), $throwable);
        }
    }

    private static function saleUnitKey(StockLevelChange $change): string
    {
        return $change->saleUnitId.':'.$change->productSaleElementsId;
    }

    /**
     * @param list<StockLevelChange> $changes
     *
     * @return list<StockLevelChange> those that moved the stock
     */
    private static function actualChanges(array $changes): array
    {
        return array_values(array_filter($changes, static fn (StockLevelChange $change): bool => $change->previousQuantity !== $change->newQuantity));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => ['flush', 100],
            ConsoleEvents::TERMINATE => ['flush', 100],
            WorkerMessageHandledEvent::class => ['flush', 100],
            WorkerMessageFailedEvent::class => ['flush', 100],
        ];
    }
}
