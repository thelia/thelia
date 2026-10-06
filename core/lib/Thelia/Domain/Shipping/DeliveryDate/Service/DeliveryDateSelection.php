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

use Propel\Runtime\Exception\PropelException;
use Thelia\Domain\Checkout\Exception\DeliveryDateRequiredException;
use Thelia\Domain\Checkout\Exception\DeliveryDateUnavailableException;
use Thelia\Domain\Checkout\Exception\DeliverySlotFullException;
use Thelia\Domain\Checkout\Exception\InvalidDeliveryException;
use Thelia\Model\Cart;
use Thelia\Model\ModuleQuery;

/**
 * Writes the delivery day and slot the buyer picked on the cart, or takes them off.
 *
 * The cart drops the choice by itself when its carrier changes (Cart::preSave()); what is
 * left here is the choice itself, judged on the way in, and the check a theme runs after the
 * buyer changed something else, to learn whether the day is still possible.
 */
final readonly class DeliveryDateSelection
{
    public function __construct(
        private DeliveryDateCalendar $calendar,
        private DeliveryDateGuard $guard,
    ) {
    }

    /**
     * @param string|null $date   the day as Y-m-d, null to clear the choice
     * @param int|null    $slotId the slot of that day, for a carrier that offers slots
     *
     * @throws InvalidDeliveryException         when the cart has no carrier yet
     * @throws DeliveryDateRequiredException    when a slot is required and missing
     * @throws DeliveryDateUnavailableException when the day or the slot is not offered
     * @throws DeliverySlotFullException        when the slot has no place left that day
     * @throws PropelException
     */
    public function choose(Cart $cart, ?string $date, ?int $slotId = null): void
    {
        if (null === $date) {
            $this->clear($cart);

            return;
        }

        $module = null === $cart->getDeliveryModuleId() ? null : ModuleQuery::create()->findPk($cart->getDeliveryModuleId());

        if (null === $module) {
            throw new InvalidDeliveryException('Please choose a carrier before a delivery date.');
        }

        $offer = $this->calendar->offerFor($module) ?? throw new DeliveryDateUnavailableException('This carrier does not offer delivery dates.');
        $day = DeliveryDateGuard::parseDay($date) ?? throw new DeliveryDateUnavailableException();

        $this->guard->assertPossibleFor($cart, $offer, $day, $slotId);

        $cart
            ->setDeliveryDate($day->format('Y-m-d'))
            ->setDeliverySlotId($slotId)
            ->save();
    }

    /**
     * @throws PropelException
     */
    public function clear(Cart $cart): void
    {
        if (null === $cart->getDeliveryDate() && null === $cart->getDeliverySlotId()) {
            return;
        }

        $cart->setDeliveryDate(null)->setDeliverySlotId(null)->save();
    }

    /**
     * Takes the choice off when it is no longer possible, and says whether it did, so the
     * buyer can be told why the day they picked is gone.
     *
     * @throws PropelException
     */
    public function dropIfNoLongerPossible(Cart $cart): bool
    {
        if (null === $cart->getDeliveryDate()) {
            return false;
        }

        $module = null === $cart->getDeliveryModuleId() ? null : ModuleQuery::create()->findPk($cart->getDeliveryModuleId());
        $offer = null === $module ? null : $this->calendar->offerFor($module);

        try {
            if (null === $offer) {
                throw new DeliveryDateUnavailableException();
            }

            $this->guard->assertPossibleFor($cart, $offer, DeliveryDateGuard::parseDay($cart->getDeliveryDate('Y-m-d')), null === $cart->getDeliverySlotId() ? null : (int) $cart->getDeliverySlotId());

            return false;
        } catch (InvalidDeliveryException) {
            $this->clear($cart);

            return true;
        }
    }
}
