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

namespace Thelia\Core\Event\OrderReturn;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\OrderReturn;

/**
 * Carries a return through the actions that create it and move it between statuses.
 */
class OrderReturnEvent extends ActionEvent
{
    protected OrderReturn $orderReturn;

    protected ?int $targetStatusId = null;

    protected ?string $refusalReason = null;

    public function __construct(OrderReturn $orderReturn)
    {
        $this->orderReturn = $orderReturn;
    }

    public function getOrderReturn(): OrderReturn
    {
        return $this->orderReturn;
    }

    /**
     * @return $this
     */
    public function setOrderReturn(OrderReturn $orderReturn): self
    {
        $this->orderReturn = $orderReturn;

        return $this;
    }

    public function getTargetStatusId(): ?int
    {
        return $this->targetStatusId;
    }

    /**
     * @return $this
     */
    public function setTargetStatusId(?int $targetStatusId): self
    {
        $this->targetStatusId = $targetStatusId;

        return $this;
    }

    public function getRefusalReason(): ?string
    {
        return $this->refusalReason;
    }

    /**
     * @return $this
     */
    public function setRefusalReason(?string $refusalReason): self
    {
        $this->refusalReason = $refusalReason;

        return $this;
    }
}
