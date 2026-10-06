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

namespace Thelia\Domain\Shipping\DeliveryDate\Service;

use Thelia\Domain\Checkout\Exception\DeliveryDateRequiredException;
use Thelia\Domain\Checkout\Exception\DeliveryDateUnavailableException;
use Thelia\Domain\Checkout\Exception\DeliverySlotFullException;
use Thelia\Domain\Shipping\DeliveryDate\DTO\DeliveryDateOffer;
use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;
use Thelia\Model\Cart;
use Thelia\Model\Module;
use Thelia\Model\OrderQuery;

/**
 * Whether the day and the slot a cart holds are among those its carrier offers.
 *
 * The answer is recomputed from the settings and the bookings every time it is asked: what
 * the page offered when it was drawn proves nothing, a slot fills while the buyer reads, and
 * a request can carry any date at all.
 */
final readonly class DeliveryDateGuard
{
    public function __construct(
        private DeliveryDateCalendar $calendar,
    ) {
    }

    /**
     * Nothing to check for a carrier that offers no date: whatever the cart may still hold
     * from an earlier choice is not copied on the order (see OrderFactory).
     *
     * @throws DeliveryDateRequiredException
     * @throws DeliveryDateUnavailableException
     * @throws DeliverySlotFullException
     */
    public function check(Cart $cart, Module $module): void
    {
        $offer = $this->calendar->offerFor($module);

        if (null === $offer) {
            return;
        }

        $this->assertPossibleFor($cart, $offer, self::parseDay($cart->getDeliveryDate('Y-m-d')), null === $cart->getDeliverySlotId() ? null : (int) $cart->getDeliverySlotId());
    }

    /**
     * The same judgement, for a choice made on this cart: a slot that reads as full because
     * an order of this very cart holds its last place is not full for this buyer. That is
     * the buyer whose payment failed and who comes back to pay: their unpaid order kept the
     * place, and refusing it to them would lock them out of the slot they booked.
     *
     * @throws DeliveryDateRequiredException
     * @throws DeliveryDateUnavailableException
     * @throws DeliverySlotFullException
     */
    public function assertPossibleFor(Cart $cart, DeliveryDateOffer $offer, ?\DateTimeInterface $date, ?int $slotId): void
    {
        try {
            $this->assertPossible($offer, $date, $slotId);
        } catch (DeliverySlotFullException $full) {
            if (null === $date || null === $slotId || !$this->isHeldByAnOrderOf($cart, $slotId, $date)) {
                throw $full;
            }
        }
    }

    private function isHeldByAnOrderOf(Cart $cart, int $slotId, \DateTimeInterface $date): bool
    {
        if (null === $cart->getId()) {
            return false;
        }

        $orders = OrderQuery::create()
            ->filterByCartId($cart->getId())
            ->filterByDeliverySlotId($slotId)
            ->filterByDeliveryDate($date->format('Y-m-d'))
            ->find();

        foreach ($orders as $order) {
            // A cancelled or refunded order gave its place back (DeliverySlotReleaseListener).
            if (!$order->isCancelled() && !$order->isRefunded()) {
                return true;
            }
        }

        return false;
    }

    /**
     * A day written exactly as Y-m-d, and a real one: 2026-02-30 is refused rather than
     * rolled over into March.
     */
    public static function parseDay(?string $date): ?\DateTimeImmutable
    {
        if (null === $date) {
            return null;
        }

        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return false !== $day && $day->format('Y-m-d') === $date ? $day : null;
    }

    /**
     * @throws DeliveryDateRequiredException
     * @throws DeliveryDateUnavailableException
     * @throws DeliverySlotFullException
     */
    public function assertPossible(DeliveryDateOffer $offer, ?\DateTimeInterface $date, ?int $slotId): void
    {
        if (null === $date) {
            throw new DeliveryDateRequiredException();
        }

        $day = $offer->day($date);

        if (null === $day || !$day->open) {
            throw new DeliveryDateUnavailableException();
        }

        if (DeliveryDateChoiceMode::Date === $offer->choiceMode) {
            // A slot sent to a carrier that offers whole days is not ignored: the caller
            // believes it booked hours this carrier will not honour.
            if (null !== $slotId) {
                throw new DeliveryDateUnavailableException();
            }

            return;
        }

        if (null === $slotId) {
            throw new DeliveryDateRequiredException('Please choose a delivery slot.');
        }

        $slot = $day->slot($slotId) ?? throw new DeliveryDateUnavailableException();

        if (!$slot->available) {
            throw new DeliverySlotFullException();
        }
    }
}
