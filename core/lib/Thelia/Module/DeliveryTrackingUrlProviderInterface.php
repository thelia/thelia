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

use Thelia\Model\Order;

/**
 * Implemented by a delivery module that builds the tracking page address of its
 * parcels itself, because its carrier wants a signature, a customer code or a
 * format a simple "%ID%" template cannot express. When the module implements it,
 * the tracking address typed by the merchant for the module is not read.
 */
interface DeliveryTrackingUrlProviderInterface
{
    /**
     * The http(s) address where the customer follows the parcel of the order, or
     * null when the module cannot give one (no tracking number yet, carrier not
     * configured).
     */
    public function getTrackingUrl(Order $order): ?string;
}
