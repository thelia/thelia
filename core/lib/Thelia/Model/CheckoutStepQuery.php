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
     * The rows in the order the buyer walks them, from the first screen to the last.
     *
     * Two rows sharing a position is not something a buyer should see as a tunnel whose
     * order changes between two page loads, so the code settles the tie: the order is
     * the same for the back-office list, for the progression and for anything else
     * reading the table.
     *
     * @return $this
     */
    public function orderedByTunnel(): self
    {
        return $this->orderByPosition()->orderByCode();
    }
}
