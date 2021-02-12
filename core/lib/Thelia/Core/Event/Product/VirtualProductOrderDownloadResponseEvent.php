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

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\OrderProduct;

/**
 * Class VirtualProductOrderDownloadResponseEvent.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class VirtualProductOrderDownloadResponseEvent extends ActionEvent
{
    /** @var OrderProduct */
    protected $orderProduct;

    /** @var Response */
    protected $response;

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
     * @return $this
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;

        return $this;
    }
}
