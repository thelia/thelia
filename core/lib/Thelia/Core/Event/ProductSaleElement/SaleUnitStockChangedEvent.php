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
 * The own stock that sale units keep for sale elements changed: every change names its sale unit.
 * Dispatched once the change is committed, so a listener that reads the database sees the new
 * quantities. The national counter of a sale element is told on StockLevelChangedEvent alone.
 */
final class SaleUnitStockChangedEvent extends StockLevelChangedEvent
{
}
