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

namespace Thelia\Core\Event\Order;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\OrderPaymentPart;

/**
 * A part of an order payment was settled or released. Dispatched after the commit, to
 * announce it, never to perform it.
 */
class OrderPaymentPartEvent extends ActionEvent
{
    public function __construct(protected OrderPaymentPart $part)
    {
    }

    public function getPart(): OrderPaymentPart
    {
        return $this->part;
    }
}
