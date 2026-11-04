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

    /** The order status action that chose the operation, null while the fixed rule of the core decides. */
    protected ?int $decidingActionId = null;

    /**
     * @param bool $simulation true when the operation is only asked for, to show it in the back office:
     *                         no stock moves afterwards, and a listener must not write anything
     */
    public function __construct(
        protected Order $order,
        protected OrderStatus $newOrderStatus,
        protected bool $simulation = false,
    ) {
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

    public function getDecidingActionId(): ?int
    {
        return $this->decidingActionId;
    }

    /**
     * @return $this
     */
    public function setDecidingActionId(?int $decidingActionId): self
    {
        $this->decidingActionId = $decidingActionId;

        return $this;
    }

    public function isSimulation(): bool
    {
        return $this->simulation;
    }
}
