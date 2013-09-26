<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
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
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Controller\Admin;

use Thelia\Core\Event\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Tools\URL;

/**
 * Class OrderController
 * @package Thelia\Controller\Admin
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class OrderController extends BaseAdminController
{
    public function indexAction()
    {
        if (null !== $response = $this->checkAuth("admin.orders.view")) return $response;
        return $this->render("orders", array("display_order" => 20));
    }

    public function viewAction($order_id)
    {

    	return $this->render("order-edit", array(
    		"order_id" => $order_id
    	));
    }

    public function updateStatus($order_id = null)
    {
        if (null !== $response = $this->checkAuth("admin.order.update")) return $response;

        $message = null;

        try {
            if($order_id !== null) {
                $order_id = $order_id;
            } else {
                $order_id = $this->getRequest()->get("order_id");
            }

            $order = OrderQuery::create()->findPk($order_id);

            $statusId = $this->getRequest()->request->get("status_id");
            $status = OrderStatusQuery::create()->findPk($statusId);

            if(null === $order) {
                throw new \InvalidArgumentException("The order you want to update status does not exist");
            }
            if(null === $status) {
                throw new \InvalidArgumentException("The status you want to set to the order does not exist");
            }

            $event = new OrderEvent($order);
            $event->setStatus($statusId);

            $this->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);
        } catch(\Exception $e) {
            $message = $e->getMessage();
        }

        $params = array();

        if ($message) {
            $params["update_status_error_message"] = $message;
        }

        $browsedPage = $this->getRequest()->get("order_page");

        if($browsedPage) {
            $params["order_page"] = $browsedPage;
            $this->redirectToRoute("admin.order.list", $params);
        } else {
            $params["order_id"] = $order_id;
            $params["tab"] = $this->getRequest()->get("tab", 'cart');
            $this->redirect(URL::getInstance()->absoluteUrl($this->getRoute("admin.order.update.view", $params)));
        }
    }

    public function updateDeliveryRef($order_id)
    {
        if (null !== $response = $this->checkAuth("admin.order.update")) return $response;

        $message = null;

        try {
            $order = OrderQuery::create()->findPk($order_id);

            $deliveryRef = $this->getRequest()->get("delivery_ref");

            if(null === $order) {
                throw new \InvalidArgumentException("The order you want to update status does not exist");
            }

            $event = new OrderEvent($order);
            $event->setDeliveryRef($deliveryRef);

            $this->dispatch(TheliaEvents::ORDER_UPDATE_DELIVERY_REF, $event);
        } catch(\Exception $e) {
            $message = $e->getMessage();
        }

        $params = array();

        if ($message) {
            $params["update_status_error_message"] = $message;
        }

        $params["order_id"] = $order_id;
        $params["tab"] = $this->getRequest()->get("tab", 'bill');

        $this->redirect(URL::getInstance()->absoluteUrl($this->getRoute("admin.order.update.view", $params)));
    }
}