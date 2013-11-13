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
use Thelia\Core\Event\PdfEvent;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Template\TemplateHelper;
use Thelia\Exception\TheliaProcessException;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Form\OrderDelivery;
use Thelia\Form\OrderPayment;
use Thelia\Log\Tlog;
use Thelia\Model\AddressQuery;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Base\OrderQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Tools\URL;

/**
 * Class OrderController
 * @package Thelia\Controller\Front
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderController extends BaseFrontController
{
    /**
     * set delivery address
     * set delivery module
     */
    public function deliver()
    {
        $this->checkAuth();
        $this->checkCartNotEmpty();

        $message = false;

        $orderDelivery = new OrderDelivery($this->getRequest());

        try {
            $form = $this->validateForm($orderDelivery, "post");

            $deliveryAddressId = $form->get("delivery-address")->getData();
            $deliveryModuleId = $form->get("delivery-module")->getData();
            $deliveryAddress = AddressQuery::create()->findPk($deliveryAddressId);
            $deliveryModule = ModuleQuery::create()->findPk($deliveryModuleId);

            /* check that the delivery address belongs to the current customer */
            if ($deliveryAddress->getCustomerId() !== $this->getSecurityContext()->getCustomerUser()->getId()) {
                throw new \Exception("Delivery address does not belong to the current customer");
            }

            /* check that the delivery module fetches the delivery address area */
            if(AreaDeliveryModuleQuery::create()
                ->filterByAreaId($deliveryAddress->getCountry()->getAreaId())
                ->filterByDeliveryModuleId($deliveryModuleId)
                ->count() == 0) {
                throw new \Exception("Delivery module cannot be use with selected delivery address");
            }

            /* get postage amount */
            $moduleReflection = new \ReflectionClass($deliveryModule->getFullNamespace());
            $moduleInstance = $moduleReflection->newInstance();
            $postage = $moduleInstance->getPostage($deliveryAddress->getCountry());

            $orderEvent = $this->getOrderEvent();
            $orderEvent->setDeliveryAddress($deliveryAddressId);
            $orderEvent->setDeliveryModule($deliveryModuleId);
            $orderEvent->setPostage($postage);

            $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_DELIVERY_ADDRESS, $orderEvent);
            $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_DELIVERY_MODULE, $orderEvent);

            $this->redirectToRoute("order.invoice");

        } catch (FormValidationException $e) {
            $message = sprintf("Please check your input: %s", $e->getMessage());
        } catch (PropelException $e) {
            $this->getParserContext()->setGeneralError($e->getMessage());
        } catch (\Exception $e) {
            $message = sprintf("Sorry, an error occured: %s", $e->getMessage());
        }

        if ($message !== false) {
            Tlog::getInstance()->error(sprintf("Error during order delivery process : %s. Exception was %s", $message, $e->getMessage()));

            $orderDelivery->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($orderDelivery)
                ->setGeneralError($message)
            ;
        }

    }

    /**
     * set invoice address
     * set payment module
     */
    public function invoice()
    {
        $this->checkAuth();
        $this->checkCartNotEmpty();
        $this->checkValidDelivery();

        $message = false;

        $orderPayment = new OrderPayment($this->getRequest());

        try {
            $form = $this->validateForm($orderPayment, "post");

            $invoiceAddressId = $form->get("invoice-address")->getData();
            $paymentModuleId = $form->get("payment-module")->getData();

            /* check that the invoice address belongs to the current customer */
            $invoiceAddress = AddressQuery::create()->findPk($invoiceAddressId);
            if ($invoiceAddress->getCustomerId() !== $this->getSecurityContext()->getCustomerUser()->getId()) {
                throw new \Exception("Invoice address does not belong to the current customer");
            }

            $orderEvent = $this->getOrderEvent();
            $orderEvent->setInvoiceAddress($invoiceAddressId);
            $orderEvent->setPaymentModule($paymentModuleId);

            $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_INVOICE_ADDRESS, $orderEvent);
            $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_PAYMENT_MODULE, $orderEvent);

            $this->redirectToRoute("order.payment.process");

        } catch (FormValidationException $e) {
            $message = sprintf("Please check your input: %s", $e->getMessage());
        } catch (PropelException $e) {
            $this->getParserContext()->setGeneralError($e->getMessage());
        } catch (\Exception $e) {
            $message = sprintf("Sorry, an error occured: %s", $e->getMessage());
        }

        if ($message !== false) {
            Tlog::getInstance()->error(sprintf("Error during order payment process : %s. Exception was %s", $message, $e->getMessage()));

            $orderPayment->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($orderPayment)
                ->setGeneralError($message)
            ;
        }

    }

    public function pay()
    {
        /* check customer */
        $this->checkAuth();

        /* check cart count */
        $this->checkCartNotEmpty();

        /* check delivery address and module */
        $this->checkValidDelivery();

        /* check invoice address and payment module */
        $this->checkValidInvoice();

        $orderEvent = $this->getOrderEvent();

        $this->getDispatcher()->dispatch(TheliaEvents::ORDER_PAY, $orderEvent);

        $placedOrder = $orderEvent->getPlacedOrder();

        if (null !== $placedOrder && null !== $placedOrder->getId()) {
            /* order has been placed */
            $this->redirect(URL::getInstance()->absoluteUrl($this->getRoute('order.placed', array('order_id' => $orderEvent->getPlacedOrder()->getId()))));
        } else {
            /* order has not been placed */
            $this->redirectToRoute('cart.view');
        }
    }

    public function orderPlaced($order_id)
    {
        /* check if the placed order matched the customer */
        $placedOrder = OrderQuery::create()->findPk(
            $this->getRequest()->attributes->get('order_id')
        );

        if (null === $placedOrder) {
            throw new TheliaProcessException("No placed order", TheliaProcessException::NO_PLACED_ORDER, $placedOrder);
        }

        $customer = $this->getSecurityContext()->getCustomerUser();

        if (null === $customer || $placedOrder->getCustomerId() !== $customer->getId()) {
            throw new TheliaProcessException("Received placed order id does not belong to the current customer", TheliaProcessException::PLACED_ORDER_ID_BAD_CURRENT_CUSTOMER, $placedOrder);
        }

        $this->getParserContext()->set("placed_order_id", $placedOrder->getId());
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

    public function generateInvoicePdf($order_id)
    {
        /* check customer */
        $this->checkAuth();
        return $this->generateOrderPdf($order_id, ConfigQuery::read('pdf_invoice_file', 'invoice'));
    }

    public function generateDeliveryPdf($order_id)
    {
        /* check customer */
        $this->checkAuth();
        return $this->generateOrderPdf($order_id, ConfigQuery::read('pdf_delivery_file', 'delivery'));
    }


}
