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
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Tells the listeners of the stock levels what a change of stock did, once the change is written
 * for good.
 *
 * A change written while the caller holds a transaction open is not told at once, nothing saying it
 * will be committed, and is not dropped either: it waits, and goes out when the transaction is over
 * (the end of the request, of the command, of the message, handled or failed), from the stock
 * before the change to the stock read back then. A stock that reads back what it read before was
 * rolled back, or moved back since, and is never told. A failing listener is logged; the stock
 * stays changed.
 */
final class StockLevelAnnouncer implements EventSubscriberInterface, ResetInterface
{
    /** @var array<int, float> by sale element id, the first stock before a change still waiting */
    private array $waiting = [];

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CallerTransactionInterface $callerTransaction,
    ) {
    }

    public function announce(int $productSaleElementsId, float $before, float $after): void
    {
        $this->announceChanges([new StockLevelChange($productSaleElementsId, $before, $after)]);
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
                $this->waiting[$change->productSaleElementsId] ??= $change->previousQuantity;
            }

            return;
        }

        self::tell($this->dispatcher, $changes);
    }

    /**
     * Tells the changes that waited for a transaction, now that it is over; those of a transaction
     * still open keep waiting.
     */
    public function flush(): void
    {
        if ([] === $this->waiting || $this->callerTransaction->isOpen(Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME))) {
            return;
        }

        $waiting = $this->waiting;
        $this->waiting = [];

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

        self::tell($this->dispatcher, $changes);
    }

    public function reset(): void
    {
        $this->waiting = [];
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

        try {
            $dispatcher->dispatch(new StockLevelChangedEvent($changes), TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED);
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->addError('A listener of the stock levels failed after a change of stock: '.$throwable->getMessage(), $throwable);
        }
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
