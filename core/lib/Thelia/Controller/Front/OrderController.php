<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/
namespace Thelia\Controller\Front;

use Propel\Runtime\Exception\PropelException;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Core\Event\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Model\Order;

class OrderController extends BaseFrontController
{
    /**
     * set billing address
     * set delivery address
     * set delivery module
     */
    public function deliver()
    {
        $orderEvent = $this->getOrderEvent();
        //$orderEvent->setBillingAddress($this->getRequest()->get("billing-address"));
        $orderEvent->setDeliveryAddress($this->getRequest()->get("delivery-address"));
        $orderEvent->setDeliveryModule($this->getRequest()->get("delivery-module"));

        try {
            //$this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_BILLING_ADDRESS, $orderEvent);
            $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_DELIVERY_ADDRESS, $orderEvent);
            $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_DELIVERY_MODULE, $orderEvent);

            $this->redirectSuccess();
        } catch (PropelException $e) {
            $this->getParserContext()->setGeneralError($e->getMessage());
        }

    }

    protected function getOrderEvent()
    {
        $order = $this->getOrder($this->getRequest());

        return new OrderEvent($order);
    }

    public function getOrder(Request $request)
    {
        $session = $request->getSession();

        if (null !== $order = $session->getOrder()) {
            return $order;
        }

        $order = new Order();

        $session->setOrder($order);

        return $order;
    }
}
