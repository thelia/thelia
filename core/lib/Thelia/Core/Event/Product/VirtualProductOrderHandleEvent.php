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

namespace Thelia\Core\Event\Product;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Order;

/**
 * This event allow modules to get information on a virtual product :.
 *
 * - download path if a file is attached to the product sale element
 * - use stock or not
 * - the pse is a virtual product ? As (virtual) product can have only some PSE
 *      really virtual.
 *
 * Class VirtualProductCreateEvent
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class VirtualProductOrderHandleEvent extends ActionEvent
{
    /** @var Order the order */
    protected $order;

    /** @var string the path of the file */
    protected $path;

    /** @var bool is virtual product is really virtual */
    protected $virtual = true;

    /** @var bool use the stock for this virtual product */
    protected $useStock = false;

    /**
     * @param int $pseId
     */
    public function __construct(Order $order, /** @var int the product sale element id */
    protected $pseId)
    {
        $this->order = $order;
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param Order $order
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @return int
     */
    public function getPseId()
    {
        return $this->pseId;
    }

    /**
     * @param int $pseId
     */
    public function setPseId($pseId)
    {
        $this->pseId = $pseId;

        return $this;
    }

    /**
     * @return bool
     */
    public function isUseStock()
    {
        return $this->useStock;
    }

    /**
     * @param bool $useStock
     */
    public function setUseStock($useStock)
    {
        $this->useStock = $useStock;

        return $this;
    }

    /**
     * @return bool
     */
    public function isVirtual()
    {
        return $this->virtual;
    }

    /**
     * @param bool $virtual
     */
    public function setVirtual($virtual)
    {
        $this->virtual = $virtual;

        return $this;
    }
}
