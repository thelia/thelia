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
use Symfony\Component\HttpFoundation\Response;

/**
 * Class VirtualProductOrderDownloadResponseEvent
 * @package Thelia\Core\Event\Product
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class VirtualProductOrderDownloadResponseEvent extends ActionEvent
{
    /** @var OrderProduct */
    protected $orderProduct;

    /**@var Response */
    protected $response;

    /**
     * @param OrderProduct $orderProduct
     */
    public function __construct(OrderProduct $orderProduct)
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
     * @return $this
     */
    public function setOrderProduct(OrderProduct $orderProduct)
    {
        $this->orderProduct = $orderProduct;

        return $this;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @param Response $response
     * @return $this
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;

        return $this;
    }
}
