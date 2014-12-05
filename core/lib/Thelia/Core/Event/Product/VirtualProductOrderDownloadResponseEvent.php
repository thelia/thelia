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
use Thelia\Model\OrderProduct;

/**
 * Class VirtualProductOrderDownloadResponseEvent
 * @package Thelia\Core\Event\Product
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class VirtualProductOrderDownloadResponseEvent extends ActionEvent
{
    /** @var OrderProduct */
    protected $orderProduct;

    /**@var \Thelia\Core\HttpFoundation\Response */
    protected $response;

    /**
     * @param $productOrder
     */
    public function __construct($orderProduct)
    {
        $this->orderProduct = $orderProduct;
    }

    /**
     * @return OrderProduct
     */
    public function getOrderProduct()
    {
        return $this->orderProduct;
    }

    /**
     * @param OrderProduct $orderProduct
     */
    public function setOrderProduct($orderProduct)
    {
        $this->orderProduct = $orderProduct;

        return $this;
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param \Thelia\Core\HttpFoundation\Response $response
     */
    public function setResponse($response)
    {
        $this->response = $response;

        return $this;
    }
}
