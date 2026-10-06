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

namespace Thelia\Domain\Shipping\DeliveryDate\Enum;

/**
 * What the buyer picks for a carrier: nothing, a day, or a slot of a day.
 *
 * The values are stored in `delivery_date_rule`.`choice_mode` and published by the front
 * API: they never change.
 */
enum DeliveryDateChoiceMode: string
{
    case None = 'none';

    case Date = 'date';

    case Slot = 'slot';
}
