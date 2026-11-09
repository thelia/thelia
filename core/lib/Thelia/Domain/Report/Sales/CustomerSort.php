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

namespace Thelia\Domain\Report\Sales;

enum CustomerSort: string
{
    case Name = 'name';
    case OrderCount = 'orders';
    case Total = 'total';
    case AverageBasket = 'average';
    case LastOrder = 'last_order';
}
