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

namespace Thelia\Core\Event\Order;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Order;

/**
 * Class PaymentEvent
 * @package Thelia\Core\Event\Module
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class OrderPaymentEvent extends ActionEvent
{
    /**
     * @var Order
     */
    protected $order;

    /**
     * @var \Thelia\Core\HttpFoundation\Response
     */
    protected $response;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * @return \Thelia\Model\Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param Response $response
     *
     * @return $this
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;

        return $this;
    }

    /**
     * @return \Thelia\Core\HttpFoundation\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    public function hasResponse()
    {
        return null !== $this->response;
    }
}
