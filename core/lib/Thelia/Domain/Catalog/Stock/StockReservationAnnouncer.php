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

use Propel\Runtime\Propel;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Core\Event\ProductSaleElement\StockReleasedEvent;
use Thelia\Core\Event\ProductSaleElement\StockReservationLine;
use Thelia\Core\Event\ProductSaleElement\StockReservedEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Model\Map\CartItemTableMap;

/**
 * Tells the listeners of the reservations which lines a cart started or stopped holding, once the
 * change is written for good: one event per cart and per direction (STOCK_RESERVED,
 * STOCK_RELEASED). A twin of StockLevelAnnouncer, for the holds of the carts.
 *
 * A hold runs in a transaction of its own and tells what it did after its commit. A release
 * written inside a transaction of the caller (an order consuming its cart, a line removed by an
 * API write) is not told at once, nothing saying it will be committed, and is not dropped
 * either: it waits, and goes out when the transaction is over (the end of the request, of the
 * command, of the message, handled or failed, or a call of flush()). A release is told for the
 * lines that hold nothing any more when it goes out: a line read back still held (its date ahead
 * or run out, but not cleared) was rolled back, or held again since, and is never told; the purge
 * tells it once its date ran out. A failing listener is logged; the stock stays as it is.
 *
 * A line is told as reserved once, and as released once with the quantity it was told with, which
 * the line itself keeps (`reserved_quantity`): whatever changed its quantity since, the release
 * tells the quantity of the reservation, and a hold that finds another quantity tells the change as
 * a release of the old quantity and a reservation of the new.
 */
final class StockReservationAnnouncer implements EventSubscriberInterface, ResetInterface
{
    /** @var array<int, array<int, StockReservationLine>> the lines to tell as released, by cart id then line id */
    private array $waiting = [];

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly CallerTransactionInterface $callerTransaction,
        private readonly HeldCartLineReader $heldLines,
    ) {
    }

    /**
     * Lines a hold just wrote, its transaction committed.
     *
     * @param list<StockReservationLine> $lines
     */
    public function reserved(int $cartId, array $lines): void
    {
        if ([] === $lines) {
            return;
        }

        $this->dispatch(new StockReservedEvent($cartId, $lines), TheliaEvents::STOCK_RESERVED);
    }

    /**
     * Lines a hold took off, or whose quantity it changed, its transaction committed: no line is
     * read back, the hold wrote them itself and ran outside any transaction of the caller.
     *
     * @param list<StockReservationLine> $lines
     */
    public function releasedNow(int $cartId, array $lines): void
    {
        if ([] === $lines) {
            return;
        }

        $this->dispatch(new StockReleasedEvent($cartId, $lines), TheliaEvents::STOCK_RELEASED);
    }

    /**
     * Lines that held stock before a write: told now, or kept until the transaction of the caller
     * is over.
     *
     * @param list<StockReservationLine> $lines
     */
    public function released(int $cartId, array $lines): void
    {
        if ([] === $lines) {
            return;
        }

        if ($this->callerTransaction->isOpen(Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME))) {
            foreach ($lines as $line) {
                $this->waiting[$cartId][$line->cartItemId] ??= $line;
            }

            return;
        }

        $this->tellReleased([$cartId => $lines]);
    }

    /**
     * Tells the releases that waited for a transaction, now that it is over; those of a transaction
     * still open keep waiting.
     */
    public function flush(): void
    {
        if ([] === $this->waiting || $this->callerTransaction->isOpen(Propel::getWriteConnection(CartItemTableMap::DATABASE_NAME))) {
            return;
        }

        $waiting = $this->waiting;
        $this->waiting = [];

        $this->tellReleased(array_map(array_values(...), $waiting));
    }

    public function reset(): void
    {
        $this->waiting = [];
    }

    /**
     * @param array<int, list<StockReservationLine>> $byCart
     */
    private function tellReleased(array $byCart): void
    {
        // What reads held now was not released, or was held again: a rollback, a later hold.
        $stillHeld = $this->heldLines->announcedAmong(array_merge(...array_map(
            static fn (array $lines): array => array_map(static fn (StockReservationLine $line): int => $line->cartItemId, $lines),
            array_values($byCart),
        )));

        foreach ($byCart as $cartId => $lines) {
            $released = array_values(array_filter($lines, static fn (StockReservationLine $line): bool => !isset($stillHeld[$line->cartItemId])));

            if ([] !== $released) {
                $this->dispatch(new StockReleasedEvent($cartId, $released), TheliaEvents::STOCK_RELEASED);
            }
        }
    }

    private function dispatch(StockReservedEvent|StockReleasedEvent $event, string $eventName): void
    {
        try {
            $this->dispatcher->dispatch($event, $eventName);
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->addError('A listener of the stock reservations failed after a change of a cart: '.$throwable->getMessage(), $throwable);
        }
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
