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

use Thelia\Model\Order;

/**
 * Class OrderEvent.
 */
class OrderProductEvent extends OrderEvent
{
    protected int $id;

    /**
     * @param int $id order product id
     */
    public function __construct(Order $order, int $id)
    {
        parent::__construct($order);
        $this->setId($id);
    }

    /**
     * @return $this
     */
    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
