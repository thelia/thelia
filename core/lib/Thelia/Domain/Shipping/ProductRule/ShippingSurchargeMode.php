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

namespace Thelia\Domain\Shipping\ProductRule;

/**
 * How often the shipping surcharge of a product is charged: a handling fee once
 * per cart line, a bulky parcel once per unit.
 */
enum ShippingSurchargeMode: int
{
    case PerLine = 0;
    case PerUnit = 1;
}
