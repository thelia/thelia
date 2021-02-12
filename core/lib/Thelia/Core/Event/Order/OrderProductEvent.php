<?php

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
    /** @var int */
    protected $id;

    /**
     * @param int $id order product id
     */
    public function __construct(Order $order, $id)
    {
        parent::__construct($order);
        $this->setId($id);
    }

    /**
     * @param $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getId()
    {
        return $this->id;
    }
}
