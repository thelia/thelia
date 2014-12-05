<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/


namespace Thelia\Core\Event\Product;

use Thelia\Core\Event\ActionEvent;

/**
 * Class VirtualProductCreateEvent
 * @package Thelia\Core\Event\Product
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class VirtualProductOrderHandleEvent extends ActionEvent
{
    /** @var  int the producrt sale element id*/
    protected $pseId;

    /** @var  int the order id */
    protected $order;

    /** @var  string the path of the file */
    protected $path;

    /** @var  bool is virtual product is really virtual */
    protected $virtual = true;

    /** @var  bool use the stock for this virtual product */
    protected $useStock = false;

    public function __construct($order, $pseId)
    {
        $this->order = $order;
        $this->pseId = $pseId;
    }

    /**
     * @return int
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param int $order
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
     * @return boolean
     */
    public function isUseStock()
    {
        return $this->useStock;
    }

    /**
     * @param boolean $useStock
     */
    public function setUseStock($useStock)
    {
        $this->useStock = $useStock;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isVirtual()
    {
        return $this->virtual;
    }

    /**
     * @param boolean $virtual
     */
    public function setVirtual($virtual)
    {
        $this->virtual = $virtual;

        return $this;
    }
}
