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

use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Event\Order\OrderAddressEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Form\OrderUpdateAddress;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Base\OrderAddressQuery;
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
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, AccessManager::VIEW)) return $response;
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
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, AccessManager::UPDATE)) return $response;

        $message = null;

        try {
            if ($order_id !== null) {
                $order_id = $order_id;
            } else {
                $order_id = $this->getRequest()->get("order_id");
            }

            $order = OrderQuery::create()->findPk($order_id);

            $statusId = $this->getRequest()->request->get("status_id");
            $status = OrderStatusQuery::create()->findPk($statusId);

            if (null === $order) {
                throw new \InvalidArgumentException("The order you want to update status does not exist");
            }
            if (null === $status) {
                throw new \InvalidArgumentException("The status you want to set to the order does not exist");
            }

            $event = new OrderEvent($order);
            $event->setStatus($statusId);

            $this->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        $params = array();

        if ($message) {
            $params["update_status_error_message"] = $message;
        }

        $browsedPage = $this->getRequest()->get("order_page");

        if ($browsedPage) {
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
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, AccessManager::UPDATE)) return $response;

        $message = null;

        try {
            $order = OrderQuery::create()->findPk($order_id);

            $deliveryRef = $this->getRequest()->get("delivery_ref");

            if (null === $order) {
                throw new \InvalidArgumentException("The order you want to update status does not exist");
            }

            $event = new OrderEvent($order);
            $event->setDeliveryRef($deliveryRef);

            $this->dispatch(TheliaEvents::ORDER_UPDATE_DELIVERY_REF, $event);
        } catch (\Exception $e) {
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

    public function updateAddress($order_id)
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, AccessManager::UPDATE)) return $response;

        $message = null;

        $orderUpdateAddress = new OrderUpdateAddress($this->getRequest());

        try {
            $order = OrderQuery::create()->findPk($order_id);

            if (null === $order) {
                throw new \InvalidArgumentException("The order you want to update does not exist");
            }

            $form = $this->validateForm($orderUpdateAddress, "post");

            $orderAddress = OrderAddressQuery::create()->findPk($form->get("id")->getData());

            if ($orderAddress->getId() !== $order->getInvoiceOrderAddressId() && $orderAddress->getId() !== $order->getDeliveryOrderAddressId()) {
                throw new \InvalidArgumentException("The order address you want to update does not belong to the current order not exist");
            }

            $event = new OrderAddressEvent(
                $form->get("title")->getData(),
                $form->get("firstname")->getData(),
                $form->get("lastname")->getData(),
                $form->get("address1")->getData(),
                $form->get("address2")->getData(),
                $form->get("address3")->getData(),
                $form->get("zipcode")->getData(),
                $form->get("city")->getData(),
                $form->get("country")->getData(),
                $form->get("phone")->getData(),
                $form->get("company")->getData()
            );
            $event->setOrderAddress($orderAddress);
            $event->setOrder($order);

            $this->dispatch(TheliaEvents::ORDER_UPDATE_ADDRESS, $event);
        } catch (\Exception $e) {
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

    public function generateInvoicePdf($order_id)
    {
        return $this->generatePdf($order_id, ConfigQuery::read('pdf_invoice_file', 'invoice'));
    }

    public function generateDeliveryPdf($order_id)
    {
        return $this->generatePdf($order_id, ConfigQuery::read('pdf_delivery_file', 'delivery'));
    }

    protected function generatePdf($order_id, $fileName)
    {
        if (null !== $response = $this->checkAuth(AdminResources::ORDER, AccessManager::UPDATE)) return $response;

        $html = $this->renderRaw(
            $fileName,
            array(
                'order_id' => $order_id
            ),
            ConfigQuery::read('pdf_template', 'pdf')
        );

        $order = OrderQuery::create()->findPk($order_id);

        try {
            $pdfEvent = new PdfEvent($html);

            $this->dispatch(TheliaEvents::GENERATE_PDF, $pdfEvent);

            if ($pdfEvent->hasPdf()) {
                return Response::create($pdfEvent->getPdf(), 200,
                    array(
                        'Content-type' => "application/pdf",
                        'Content-Disposition' => sprintf('Attachment;filename=%s.pdf', $order->getRef()),
                    ));
            }

        } catch (\Exception $e) {
            \Thelia\Log\Tlog::getInstance()->error(sprintf('error during generating invoice pdf for order id : %d with message "%s"', $order_id, $e->getMessage()));

        }

        $this->redirect(URL::getInstance()->absoluteUrl($this->getRoute("admin.order.update.view", array(
            'order_id' => $order_id
        ))));
    }
}
