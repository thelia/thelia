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

use Thelia\Core\Event\ActionEvent;

/**
 * The physical stock of sale elements changed. Dispatched once the change is
 * committed, so a listener that reads the database sees the new quantities.
 */
class StockLevelChangedEvent extends ActionEvent
{
    /** @param list<StockLevelChange> $changes */
    public function __construct(private readonly array $changes)
    {
    }

    /** @return list<StockLevelChange> */
    public function getChanges(): array
    {
        return $this->changes;
    }
}
