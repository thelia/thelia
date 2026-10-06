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

namespace Thelia\Model;

use Thelia\Model\Base\DeliverySlot as BaseDeliverySlot;

/**
 * A slot of a delivery day, offered with the same hours on every day the carrier delivers.
 *
 * The hours are local: a slot from 9:00 to 11:00 is 9:00 in the shop time zone whatever the
 * season, which is why they are stored as a time of day and never as an instant.
 */
class DeliverySlot extends BaseDeliverySlot
{
}
