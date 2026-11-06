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

namespace Thelia\Core\Event\ProductSaleElement;

/**
 * Lines of a cart that were held are not any more (TheliaEvents::STOCK_RELEASED): the cart became an
 * order, was purged or emptied, the line was removed, or the stock no longer covers it. Dispatched
 * once the change is committed. A hold that ran out by its date is not told.
 */
final class StockReleasedEvent extends StockReservationEvent
{
}
