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

namespace Thelia\Module;

use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;

/**
 * A delivery module that lets the buyer pick the day, and possibly the slot, it delivers on.
 *
 * Optional, the way DeliveryModuleWithStateInterface is: a module that does not implement it
 * keeps its delivery step exactly as it was, whatever the merchant writes in the delivery
 * date settings. The module says which shapes of choice it can honour; the merchant picks
 * one of them per carrier in the back office, along with the delay, the horizon, the closed
 * days and the slots. The core never knows the rounds of a carrier, only the shape of the
 * choice and the constraints the merchant set.
 */
interface DeliveryDateAwareInterface
{
    /**
     * The shapes of choice this carrier can honour, never including None.
     *
     * A pickup in store that serves a counter by the hour returns only Slot; a carrier that
     * books a day with its rounds returns Date, or both.
     *
     * @return list<DeliveryDateChoiceMode>
     */
    public function getAcceptedDeliveryDateChoiceModes(): array;
}
