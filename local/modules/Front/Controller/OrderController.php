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
namespace Front\Controller;

use Front\Front;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Thelia\Cart\CartTrait;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Translation\Translator;
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
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\TaxEngine\TaxEngine;
use Thelia\Tools\URL;

/**
 * Class OrderController
 * @package Thelia\Controller\Front
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderController extends BaseFrontController
{
    use CartTrait;


    /**
     * Check if the cart contains only virtual products.
     */
    public function deliverView()
    {
        $this->checkAuth();
        $this->checkCartNotEmpty();

        // check if the cart contains only virtual products
        $cart = $this->getSession()->getCart();

        if ( $cart->isVirtual()){
            // get the virtual product module
            $customer = $this->getSecurityContext()->getCustomerUser();

            $deliveryAddress = AddressQuery::create()
                ->filterByCustomerId($customer->getId())
                ->orderByIsDefault(Criteria::DESC)
                ->findOne();

            if (null !== $deliveryAddress) {

                $deliveryModule = ModuleQuery::create()
                    ->findOneByCode('VirtualProductDelivery');

                if (null !== $deliveryModule) {
                    /* get postage amount */
                    $moduleInstance = $deliveryModule->getModuleInstance($this->container);
                    $postage = $moduleInstance->getPostage($deliveryAddress->getCountry());

                    $orderEvent = $this->getOrderEvent();
                    $orderEvent->setDeliveryAddress($deliveryAddress->getId());
                    $orderEvent->setDeliveryModule($deliveryModule->getId());
                    $orderEvent->setPostage($postage);

                    $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_DELIVERY_ADDRESS, $orderEvent);
                    $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_DELIVERY_MODULE, $orderEvent);
                    $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_POSTAGE, $orderEvent);

                    $this->redirectToRoute("order.invoice");
                }
            }
        }

        return $this->render('order-delivery');
    }

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
            $moduleInstance = $deliveryModule->getModuleInstance($this->container);

            $postage = $moduleInstance->getPostage($deliveryAddress->getCountry());

            $orderEvent = $this->getOrderEvent();
            $orderEvent->setDeliveryAddress($deliveryAddressId);
            $orderEvent->setDeliveryModule($deliveryModuleId);
            $orderEvent->setPostage($postage);

            $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_DELIVERY_ADDRESS, $orderEvent);
            $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_DELIVERY_MODULE, $orderEvent);
            $this->getDispatcher()->dispatch(TheliaEvents::ORDER_SET_POSTAGE, $orderEvent);

            return $this->generateRedirectFromRoute("order.invoice");

        } catch (FormValidationException $e) {
            $message = Translator::getInstance()->trans("Please check your input: %s", ['%s' => $e->getMessage()], Front::MESSAGE_DOMAIN);
        } catch (PropelException $e) {
            $this->getParserContext()->setGeneralError($e->getMessage());
        } catch (\Exception $e) {
            $message = Translator::getInstance()->trans("Sorry, an error occured: %s", ['%s' => $e->getMessage()], Front::MESSAGE_DOMAIN);
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

            return $this->generateRedirectFromRoute("order.payment.process");

        } catch (FormValidationException $e) {
            $message = Translator::getInstance()->trans("Please check your input: %s", ['%s' => $e->getMessage()], Front::MESSAGE_DOMAIN);
        } catch (PropelException $e) {
            $this->getParserContext()->setGeneralError($e->getMessage());
        } catch (\Exception $e) {
            $message = Translator::getInstance()->trans("Sorry, an error occured: %s", ['%s' => $e->getMessage()], Front::MESSAGE_DOMAIN);
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
            $this->getDispatcher()->dispatch(TheliaEvents::ORDER_AFTER_PAYMENT, $orderEvent);

            if ($orderEvent->hasResponse()) {
                return $orderEvent->getResponse();
            } else {
                return $this->generateRedirectFromRoute('order.placed', [], ['order_id' => $orderEvent->getPlacedOrder()->getId()]);
            }
        } else {
            /* order has not been placed */
            return $this->generateRedirectFromRoute('cart.view');
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

    public function orderFailed($order_id, $message)
    {
        /* check if the placed order matched the customer */
        $failedOrder = OrderQuery::create()->findPk(
            $this->getRequest()->attributes->get('order_id')
        );

        if (null === $failedOrder) {
            throw new TheliaProcessException("No failed order", TheliaProcessException::NO_PLACED_ORDER, $failedOrder);
        }

        $customer = $this->getSecurityContext()->getCustomerUser();

        if (null === $customer || $failedOrder->getCustomerId() !== $customer->getId()) {
            throw new TheliaProcessException("Received failed order id does not belong to the current customer", TheliaProcessException::PLACED_ORDER_ID_BAD_CURRENT_CUSTOMER, $failedOrder);
        }

        $this->getParserContext()
            ->set("failed_order_id", $failedOrder->getId())
            ->set("failed_order_message", $message)
        ;
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
        $this->checkOrderCustomer($order_id);


        return $this->generateOrderPdf($order_id, ConfigQuery::read('pdf_invoice_file', 'invoice'));
    }

    public function generateDeliveryPdf($order_id)
    {
        $this->checkOrderCustomer($order_id);

        return $this->generateOrderPdf($order_id, ConfigQuery::read('pdf_delivery_file', 'delivery'));
    }


    public function downloadVirtualProduct($order_product_id)
    {

        if (null !== $orderProduct = OrderProductQuery::create()->findPk($order_product_id)){

            $order = $orderProduct->getOrder();

            if ($order->isPaid()){

                // check customer
                $this->checkOrderCustomer($order->getId());

                if ($orderProduct->getVirtualDocument()) {

                    // try to get the file
                    $path = THELIA_ROOT
                        . ConfigQuery::read('documents_library_path', 'local/media/documents')
                        . DS . "product" . DS
                        . $orderProduct->getVirtualDocument();

                    if (!is_file($path) || !is_readable($path)) {
                        throw new \ErrorException(
                            Translator::getInstance()->trans(
                                "The file [%file] does not exist",
                                [
                                    "%file" => $order_product_id
                                ]
                            )
                        );
                    }

                    $data = file_get_contents($path);

                    $mime = MimeTypeGuesser::getInstance()
                        ->guess($path)
                    ;

                    return new Response($data, 200, ["Content-Type" => $mime]);
                }
            }
        }

        throw new AccessDeniedHttpException();

    }

    private function checkOrderCustomer($order_id)
    {
        $this->checkAuth();

        $order = OrderQuery::create()->findPk($order_id);
        $valid = true;
        if ($order) {
            $customerOrder = $order->getCustomer();
            $customer = $this->getSecurityContext()->getCustomerUser();

            if ($customerOrder->getId() != $customer->getId()) {
                $valid = false;
            }
        } else {
            $valid = false;
        }

        if (false === $valid) {
            throw new AccessDeniedHttpException();
        }
    }

    public function getDeliveryModuleListAjaxAction()
    {
        $country = $this->getRequest()->get(
            'country_id',
            $this->container->get('thelia.taxEngine')->getDeliveryCountry()->getId()
        );

        $this->checkXmlHttpRequest();
        $args = array('country' => $country);

        return $this->render('ajax/order-delivery-module-list', $args);
    }

}
