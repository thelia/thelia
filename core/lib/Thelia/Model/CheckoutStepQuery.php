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

use Thelia\Model\Base\CheckoutStepQuery as BaseCheckoutStepQuery;

class CheckoutStepQuery extends BaseCheckoutStepQuery
{
    /**
     * The rows by position, then by code, so that what the database returns never
     * depends on how it happened to store them.
     *
     * This is not the order the buyer walks them in: two rows sharing a position are
     * ordered by the tie rule of the tunnel, which SQL cannot express. A reader that
     * shows or acts on the order passes the result through
     * `CheckoutTunnelShape::sortRows()`.
     *
     * @return $this
     */
    public function orderedByTunnel(): self
    {
        return $this->orderByPosition()->orderByCode();
    }
}
