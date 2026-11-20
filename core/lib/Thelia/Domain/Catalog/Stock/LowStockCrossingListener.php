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

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\ProductSaleElement\LowStockReachedEvent;
use Thelia\Core\Event\ProductSaleElement\SaleUnitStockChangedEvent;
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\ProductSaleElement\StockLevelChangedEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Raises the low stock signal when a sale element goes from above its threshold
 * to at or under it. A level that was already at or under the threshold raises
 * nothing again; it has to climb back above first. The own stock of a sale unit
 * crosses its own threshold, and its signal names the unit.
 */
final readonly class LowStockCrossingListener
{
    public function __construct(
        private LowStockThreshold $threshold,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::PRODUCT_SALE_ELEMENTS_STOCK_CHANGED)]
    public function onStockChanged(StockLevelChangedEvent $event): void
    {
        $changes = $event->getChanges();

        if ([] === $changes) {
            return;
        }

        $thresholds = $this->threshold->forMany(array_values(array_unique(array_map(
            static fn ($change): int => $change->productSaleElementsId,
            $changes,
        ))));

        foreach ($changes as $change) {
            $this->signalWhenCrossed($change, $thresholds[$change->productSaleElementsId] ?? null);
        }
    }

    #[AsEventListener(event: TheliaEvents::SALE_UNIT_STOCK_CHANGED)]
    public function onSaleUnitStockChanged(SaleUnitStockChangedEvent $event): void
    {
        $stocks = [];

        foreach ($event->getChanges() as $change) {
            if (null !== $change->saleUnitId) {
                $stocks[$change->saleUnitId.':'.$change->productSaleElementsId] = [$change->saleUnitId, $change->productSaleElementsId];
            }
        }

        $thresholds = $this->threshold->forManySaleUnits(array_values($stocks));

        foreach ($event->getChanges() as $change) {
            $this->signalWhenCrossed($change, $thresholds[$change->saleUnitId.':'.$change->productSaleElementsId] ?? null);
        }
    }

    private function signalWhenCrossed(StockLevelChange $change, ?float $threshold): void
    {
        if (null === $threshold || !($change->previousQuantity > $threshold && $change->newQuantity <= $threshold)) {
            return;
        }

        $this->dispatcher->dispatch(
            new LowStockReachedEvent($change->productSaleElementsId, $change->newQuantity, $threshold, $change->saleUnitId),
            TheliaEvents::PRODUCT_SALE_ELEMENTS_LOW_STOCK_REACHED,
        );
    }
}
