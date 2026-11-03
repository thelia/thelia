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
use Thelia\Domain\Order\DTO\OrderEdit;
use Thelia\Model\Order;

class OrderEditEvent extends ActionEvent
{
    public function __construct(
        private readonly Order $order,
        private readonly OrderEdit $edit,
    ) {
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getEdit(): OrderEdit
    {
        return $this->edit;
    }
}
