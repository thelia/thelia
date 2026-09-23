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

namespace Thelia\Domain\SaleUnit\Exception;

/**
 * A code path that owns data per sale unit ran with no sale unit resolved,
 * in a shop where at least one is active. Taking a silent default here would
 * write one shop's data into another's: failing is the contract.
 */
class NoCurrentSaleUnitException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'No current sale unit, and at least one is active so this shop is in multi-sale-unit mode. '
            .'On the web the sale unit comes from the domain, the session or the X-Sale-Unit header; '
            .'from the command line, resolve one explicitly before touching data a sale unit owns.'
        );
    }
}
