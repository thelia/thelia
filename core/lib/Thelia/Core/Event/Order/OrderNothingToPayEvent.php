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
use Thelia\Model\Order;

/**
 * The discount leaves nothing to pay on this order, and the core is about to turn it paid
 * without asking its payment module to charge it.
 *
 * A listener that has a reason to have the payment module called all the same refuses it:
 * the order is then paid the way any other order is.
 */
class OrderNothingToPayEvent extends ActionEvent
{
    private bool $refused = false;

    public function __construct(private readonly Order $order)
    {
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function refuse(): void
    {
        $this->refused = true;
    }

    public function isRefused(): bool
    {
        return $this->refused;
    }
}
