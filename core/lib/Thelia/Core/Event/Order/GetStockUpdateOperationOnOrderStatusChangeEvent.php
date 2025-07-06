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
use Thelia\Model\OrderStatus;

/**
 * Class OrderEvent.
 */
class GetStockUpdateOperationOnOrderStatusChangeEvent extends ActionEvent
{
    public const DECREASE_STOCK = -1;
    public const INCREASE_STOCK = 1;
    public const DO_NOTHING = 0;

    protected $operation = self::DO_NOTHING;

    /**
     * StockUpdateOnOrderStatusChangeEvent constructor.
     */
    public function __construct(protected Order $order, protected OrderStatus $newOrderStatus)
    {
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getNewOrderStatus(): OrderStatus
    {
        return $this->newOrderStatus;
    }

    public function getOperation(): int
    {
        return $this->operation;
    }

    /**
     * @return $this
     */
    public function setOperation(int $operation): self
    {
        $this->operation = $operation;

        return $this;
    }
}
