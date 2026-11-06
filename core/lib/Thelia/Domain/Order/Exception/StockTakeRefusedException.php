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

namespace Thelia\Domain\Order\Exception;

use Thelia\Exception\TheliaProcessException;

/**
 * The guard of a stock take found too little stock. It names the take by the key its caller
 * gave it, the caller being the one that knows which product to name to the buyer.
 */
final class StockTakeRefusedException extends TheliaProcessException
{
    public function __construct(public readonly int|string $takeKey)
    {
        parent::__construct('Not enough stock');
    }
}
