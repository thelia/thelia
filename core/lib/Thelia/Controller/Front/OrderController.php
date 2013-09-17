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
use Thelia\Form\OrderDelivery;
use Thelia\Log\Tlog;
use Thelia\Model\Order;

/**
 * Class OrderController
 * @package Thelia\Controller\Front
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderController extends BaseFrontController
{
    /**
     * set billing address
     * set delivery address
     * set delivery module
     */
    public function deliver()
    {
        $message = false;

        $orderDelivery = new OrderDelivery($this->getRequest());

        $x = $this->getRequest();
        $y = $_POST;

        try {
            $form = $this->validateForm($orderDelivery, "post");

            $orderEvent = $this->getOrderEvent();
            $orderEvent->setDeliveryAddress($form->get("delivery-address")->getData());
            $orderEvent->setDeliveryModule($form->get("delivery-module")->getData());


            $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_DELIVERY_ADDRESS, $orderEvent);
            $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_DELIVERY_MODULE, $orderEvent);

            $this->redirectToRoute("order.billing");

        } catch (FormValidationException $e) {
            $message = sprintf("Please check your input: %s", $e->getMessage());
        } catch (PropelException $e) {
            $this->getParserContext()->setGeneralError($e->getMessage());
        } catch (\Exception $e) {
            $message = sprintf("Sorry, an error occured: %s", $e->getMessage());
        }

        if ($message !== false) {
            Tlog::getInstance()->error(sprintf("Error during customer creation process : %s. Exception was %s", $message, $e->getMessage()));

            $orderDelivery->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($orderDelivery)
                ->setGeneralError($message)
            ;
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
