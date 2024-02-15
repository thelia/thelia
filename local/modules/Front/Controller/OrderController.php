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

namespace Front\Controller;

use Front\Front;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Product\VirtualProductOrderDownloadResponseEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Exception\TheliaProcessException;
use Thelia\Form\Definition\FrontForm;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Log\Tlog;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Module\AbstractDeliveryModule;
use Thelia\Module\Exception\DeliveryException;

/**
 * Class OrderController.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderController extends BaseFrontController
{
    /**
     * Check if the cart contains only virtual products.
     */
    public function deliverView(EventDispatcherInterface $eventDispatcher)
    {
        $this->checkAuth();
        $this->checkCartNotEmpty($eventDispatcher);

        // check if the cart contains only virtual products
        $cart = $this->getSession()->getSessionCart($eventDispatcher);

        $deliveryAddress = $this->getCustomerAddress();

        if ($cart->isVirtual()) {
            if (null !== $deliveryAddress) {
                $deliveryModule = ModuleQuery::create()->retrieveVirtualProductDelivery($this->container);

                if (false === $deliveryModule) {
                    Tlog::getInstance()->error(
                        $this->getTranslator()->trans(
                            'To enable the virtual product feature, the VirtualProductDelivery module should be activated',
                            [],
                            Front::MESSAGE_DOMAIN
                        )
                    );
                } elseif (\count($deliveryModule) == 1) {
                    return $this->registerVirtualProductDelivery($eventDispatcher, $deliveryModule[0], $deliveryAddress);
                }
            }
        }

        return $this->render(
            'order-delivery',
            [
                'delivery_address_id' => (null !== $deliveryAddress) ? $deliveryAddress->getId() : null,
            ]
        );
    }

    /**
     * @param AbstractDeliveryModule $moduleInstance
     * @param Address                $deliveryAddress
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function registerVirtualProductDelivery(EventDispatcherInterface $eventDispatcher, $moduleInstance, $deliveryAddress)
    {
        /* get postage amount */
        $deliveryModule = $moduleInstance->getModuleModel();
        $cart = $this->getSession()->getSessionCart($eventDispatcher);
        $deliveryPostageEvent = new DeliveryPostageEvent($moduleInstance, $cart, $deliveryAddress);

        $eventDispatcher->dispatch(
            $deliveryPostageEvent,
            TheliaEvents::MODULE_DELIVERY_GET_POSTAGE
        );

        $postage = $deliveryPostageEvent->getPostage();

        $orderEvent = $this->getOrderEvent();
        $orderEvent->setDeliveryAddress($deliveryAddress->getId());
        $orderEvent->setDeliveryModule($deliveryModule->getId());
        $orderEvent->setPostage($postage->getAmount());
        $orderEvent->setPostageTax($postage->getAmountTax());
        $orderEvent->setPostageTaxRuleTitle($postage->getTaxRuleTitle());

        $eventDispatcher->dispatch($orderEvent, TheliaEvents::ORDER_SET_DELIVERY_ADDRESS);
        $orderEvent->resetStopPropagation();
        $eventDispatcher->dispatch($orderEvent, TheliaEvents::ORDER_SET_DELIVERY_MODULE);
        $orderEvent->resetStopPropagation();
        $eventDispatcher->dispatch($orderEvent, TheliaEvents::ORDER_SET_POSTAGE);

        return $this->generateRedirectFromRoute('order.invoice');
    }

    /**
     * set delivery address
     * set delivery module.
     */
    public function deliver(EventDispatcherInterface $eventDispatcher)
    {
        $this->checkAuth();
        $this->checkCartNotEmpty($eventDispatcher);

        $message = false;

        $orderDelivery = $this->createForm(FrontForm::ORDER_DELIVER);

        try {
            $form = $this->validateForm($orderDelivery, 'post');

            $deliveryAddressId = $form->get('delivery-address')->getData();
            $deliveryModuleId = $form->get('delivery-module')->getData();
            $deliveryAddress = AddressQuery::create()->findPk($deliveryAddressId);
            $deliveryModule = ModuleQuery::create()->findPk($deliveryModuleId);

            /* check that the delivery address belongs to the current customer */
            if ($deliveryAddress->getCustomerId() !== $this->getSecurityContext()->getCustomerUser()->getId()) {
                throw new \Exception(
                    $this->getTranslator()->trans(
                        'Delivery address does not belong to the current customer',
                        [],
                        Front::MESSAGE_DOMAIN
                    )
                );
            }

            /* check that the delivery module fetches the delivery address area */
            if (null === AreaDeliveryModuleQuery::create()->findByCountryAndModule(
                $deliveryAddress->getCountry(),
                $deliveryModule,
                $deliveryAddress->getState()
            )) {
                throw new \Exception(
                    $this->getTranslator()->trans(
                        'Delivery module cannot be use with selected delivery address',
                        [],
                        Front::MESSAGE_DOMAIN
                    )
                );
            }

            /* get postage amount */
            $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);

            $cart = $this->getSession()->getSessionCart($eventDispatcher);
            $deliveryPostageEvent = new DeliveryPostageEvent($moduleInstance, $cart, $deliveryAddress);

            $eventDispatcher->dispatch(
                $deliveryPostageEvent,
                TheliaEvents::MODULE_DELIVERY_GET_POSTAGE
            );

            if (!$deliveryPostageEvent->isValidModule() || null === $deliveryPostageEvent->getPostage()) {
                throw new DeliveryException(
                    $this->getTranslator()->trans('The delivery module is not valid.', [], Front::MESSAGE_DOMAIN)
                );
            }

            $postage = $deliveryPostageEvent->getPostage();

            $orderEvent = $this->getOrderEvent();
            $orderEvent->setDeliveryAddress($deliveryAddressId);
            $orderEvent->setDeliveryModule($deliveryModuleId);
            $orderEvent->setPostage($postage->getAmount());
            $orderEvent->setPostageTax($postage->getAmountTax());
            $orderEvent->setPostageTaxRuleTitle($postage->getTaxRuleTitle());

            $eventDispatcher->dispatch($orderEvent, TheliaEvents::ORDER_SET_DELIVERY_ADDRESS);
            $orderEvent->resetStopPropagation();
            $eventDispatcher->dispatch($orderEvent, TheliaEvents::ORDER_SET_DELIVERY_MODULE);
            $orderEvent->resetStopPropagation();
            $eventDispatcher->dispatch($orderEvent, TheliaEvents::ORDER_SET_POSTAGE);

            return $this->generateRedirectFromRoute('order.invoice');
        } catch (FormValidationException $e) {
            $message = $this->getTranslator()->trans(
                'Please check your input: %s',
                ['%s' => $e->getMessage()],
                Front::MESSAGE_DOMAIN
            );
        } catch (PropelException $e) {
            $this->getParserContext()->setGeneralError($e->getMessage());
        } catch (\Exception $e) {
            $message = $this->getTranslator()->trans(
                'Sorry, an error occured: %s',
                ['%s' => $e->getMessage()],
                Front::MESSAGE_DOMAIN
            );
        }

        if ($message !== false) {
            Tlog::getInstance()->error(
                sprintf('Error during order delivery process : %s. Exception was %s', $message, $e->getMessage())
            );

            $orderDelivery->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($orderDelivery)
                ->setGeneralError($message)
            ;
        }
    }

    /**
     * set invoice address
     * set payment module.
     */
    public function invoice(EventDispatcherInterface $eventDispatcher)
    {
        $this->checkAuth();
        $this->checkCartNotEmpty($eventDispatcher);
        $this->checkValidDelivery();

        $message = false;

        $orderPayment = $this->createForm(FrontForm::ORDER_PAYMENT);

        try {
            $form = $this->validateForm($orderPayment, 'post');

            $invoiceAddressId = $form->get('invoice-address')->getData();
            $paymentModuleId = $form->get('payment-module')->getData();

            /* check that the invoice address belongs to the current customer */
            $invoiceAddress = AddressQuery::create()->findPk($invoiceAddressId);
            if ($invoiceAddress->getCustomerId() !== $this->getSecurityContext()->getCustomerUser()->getId()) {
                throw new \Exception(
                    $this->getTranslator()->trans(
                        'Invoice address does not belong to the current customer',
                        [],
                        Front::MESSAGE_DOMAIN
                    )
                );
            }

            $orderEvent = $this->getOrderEvent();
            $orderEvent->setInvoiceAddress($invoiceAddressId);
            $orderEvent->setPaymentModule($paymentModuleId);

            $eventDispatcher->dispatch($orderEvent, TheliaEvents::ORDER_SET_INVOICE_ADDRESS);
            $orderEvent->resetStopPropagation();
            $eventDispatcher->dispatch($orderEvent, TheliaEvents::ORDER_SET_PAYMENT_MODULE);

            return $this->generateRedirectFromRoute('order.payment.process');
        } catch (FormValidationException $e) {
            $message = $this->getTranslator()->trans(
                'Please check your input: %s',
                ['%s' => $e->getMessage()],
                Front::MESSAGE_DOMAIN
            );
        } catch (PropelException $e) {
            $this->getParserContext()->setGeneralError($e->getMessage());
        } catch (\Exception $e) {
            $message = $this->getTranslator()->trans(
                'Sorry, an error occured: %s',
                ['%s' => $e->getMessage()],
                Front::MESSAGE_DOMAIN
            );
        }

        if ($message !== false) {
            Tlog::getInstance()->error(
                sprintf('Error during order payment process : %s. Exception was %s', $message, $e->getMessage())
            );

            $orderPayment->setErrorMessage($message);

            $this->getParserContext()
                ->addForm($orderPayment)
                ->setGeneralError($message)
            ;
        }

        return $this->generateErrorRedirect($orderPayment);
    }

    public function pay(EventDispatcherInterface $eventDispatcher)
    {
        /* check customer */
        $this->checkAuth();

        /* check cart count */
        $this->checkCartNotEmpty($eventDispatcher);

        /* check stock not empty */
        if (true === ConfigQuery::checkAvailableStock()) {
            if (null !== $response = $this->checkStockNotEmpty($eventDispatcher)) {
                return $response;
            }
        }

        /* check delivery address and module */
        $this->checkValidDelivery();

        /* check invoice address and payment module */
        $this->checkValidInvoice();

        $orderEvent = $this->getOrderEvent();

        $eventDispatcher->dispatch($orderEvent, TheliaEvents::ORDER_PAY);

        $placedOrder = $orderEvent->getPlacedOrder();

        if (null !== $placedOrder && null !== $placedOrder->getId()) {
            /* order has been placed */
            if ($orderEvent->hasResponse()) {
                return $orderEvent->getResponse();
            }

            return $this->generateRedirectFromRoute(
                'order.placed',
                [],
                ['order_id' => $orderEvent->getPlacedOrder()->getId()]
            );
        }

        /* order has not been placed */
        return $this->generateRedirectFromRoute('cart.view');
    }

    public function orderPlaced(EventDispatcherInterface $eventDispatcher, $order_id): void
    {
        /* check if the placed order matched the customer */
        $placedOrder = OrderQuery::create()->findPk(
            $this->getRequest()->attributes->get('order_id')
        );

        if (null === $placedOrder) {
            throw new TheliaProcessException(
                $this->getTranslator()->trans(
                    'No placed order',
                    [],
                    Front::MESSAGE_DOMAIN
                ),
                TheliaProcessException::NO_PLACED_ORDER,
                $placedOrder
            );
        }

        $customer = $this->getSecurityContext()->getCustomerUser();

        if (null === $customer || $placedOrder->getCustomerId() !== $customer->getId()) {
            throw new TheliaProcessException(
                $this->getTranslator()->trans(
                    'Received placed order id does not belong to the current customer',
                    [],
                    Front::MESSAGE_DOMAIN
                ),
                TheliaProcessException::PLACED_ORDER_ID_BAD_CURRENT_CUSTOMER,
                $placedOrder
            );
        }

        $eventDispatcher->dispatch($this->getOrderEvent(), TheliaEvents::ORDER_CART_CLEAR);

        $this->getParserContext()->set('placed_order_id', $placedOrder->getId());
    }

    public function orderFailed($order_id, $message): void
    {
        if (empty($order_id)) {
            // Fallback to request parameter if the method parameter is empty.
            $order_id = $this->getRequest()->get('order_id');
        }

        $failedOrder = OrderQuery::create()->findPk($order_id);

        if (null !== $failedOrder) {
            $customer = $this->getSecurityContext()->getCustomerUser();

            if (null === $customer || $failedOrder->getCustomerId() !== $customer->getId()) {
                throw new TheliaProcessException(
                    $this->getTranslator()->trans(
                        'Received failed order id does not belong to the current customer',
                        [],
                        Front::MESSAGE_DOMAIN
                    ),
                    TheliaProcessException::PLACED_ORDER_ID_BAD_CURRENT_CUSTOMER,
                    $failedOrder
                );
            }
        } else {
            Tlog::getInstance()->warning("Failed order ID '$order_id' not found.");
        }

        $this->getParserContext()
            ->set('failed_order_id', $order_id)
            ->set('failed_order_message', $message)
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

    public function viewAction($order_id)
    {
        $this->checkOrderCustomer($order_id);

        return $this->render('account-order', ['order_id' => $order_id]);
    }

    public function generateInvoicePdf(EventDispatcherInterface $eventDispatcher, $order_id)
    {
        $this->checkOrderCustomer($order_id);

        return $this->generateOrderPdf($eventDispatcher, $order_id, ConfigQuery::read('pdf_invoice_file', 'invoice'));
    }

    public function generateDeliveryPdf(EventDispatcherInterface $eventDispatcher, $order_id)
    {
        $this->checkOrderCustomer($order_id);

        return $this->generateOrderPdf($eventDispatcher, $order_id, ConfigQuery::read('pdf_delivery_file', 'delivery'));
    }

    public function downloadVirtualProduct(EventDispatcherInterface $eventDispatcher, $order_product_id)
    {
        if (null !== $orderProduct = OrderProductQuery::create()->findPk($order_product_id)) {
            $order = $orderProduct->getOrder();

            if ($order->isPaid(false)) {
                // check customer
                $this->checkOrderCustomer($order->getId());

                $virtualProductEvent = new VirtualProductOrderDownloadResponseEvent($orderProduct);
                $eventDispatcher->dispatch(
                    $virtualProductEvent,
                    TheliaEvents::VIRTUAL_PRODUCT_ORDER_DOWNLOAD_RESPONSE
                );

                $response = $virtualProductEvent->getResponse();

                if (!$response instanceof BaseResponse) {
                    throw new \RuntimeException('A Response must be added in the event TheliaEvents::VIRTUAL_PRODUCT_ORDER_DOWNLOAD_RESPONSE');
                }

                return $response;
            }
        }

        throw new AccessDeniedHttpException();
    }

    private function checkOrderCustomer($order_id): void
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
        $this->checkXmlHttpRequest();

        // Change the delivery address if customer has changed it
        $address = null;
        $session = $this->getSession();
        $addressId = $this->getRequest()->get('address_id', null);
        if (null !== $addressId && $addressId !== $session->getOrder()->getChoosenDeliveryAddress()) {
            $address = AddressQuery::create()->findPk($addressId);
            if (null !== $address && $address->getCustomerId() === $session->getCustomerUser()->getId()) {
                $session->getOrder()->setChoosenDeliveryAddress($addressId);
            }
        }

        $address = AddressQuery::create()->findPk($session->getOrder()->getChoosenDeliveryAddress());

        $countryId = $address->getCountryId();
        $stateId = $address->getStateId();

        $args = [
            'country' => $countryId,
            'state' => $stateId,
            'address' => $session->getOrder()->getChoosenDeliveryAddress(),
        ];

        return $this->render('ajax/order-delivery-module-list', $args);
    }

    /**
     * Redirect to cart view if at least one non product is out of stock.
     *
     * @return BaseResponse|null
     */
    private function checkStockNotEmpty(EventDispatcherInterface $eventDispatcher)
    {
        $cart = $this->getSession()->getSessionCart($eventDispatcher);

        $cartItems = $cart->getCartItems();

        foreach ($cartItems as $cartItem) {
            $pse = $cartItem->getProductSaleElements();

            $product = $cartItem->getProduct();

            if ($pse->getQuantity() <= 0 && $product->getVirtual() !== 1) {
                return $this->generateRedirectFromRoute('cart.view');
            }
        }

        return null;
    }

    /**
     * Retrieve the chosen delivery address for a cart or the default customer address if not exists.
     *
     * @return Address|null
     */
    protected function getCustomerAddress()
    {
        $deliveryAddress = null;
        $addressId = $this->getSession()->getOrder()->getChoosenDeliveryAddress();
        if (null === $addressId) {
            $customer = $this->getSecurityContext()->getCustomerUser();

            $deliveryAddress = AddressQuery::create()
                ->filterByCustomerId($customer->getId())
                ->orderByIsDefault(Criteria::DESC)
                ->findOne();

            if (null !== $deliveryAddress) {
                $this->getSession()->getOrder()->setChoosenDeliveryAddress(
                    $deliveryAddress->getId()
                );
            }
        } else {
            $deliveryAddress = AddressQuery::create()->findPk($addressId);
        }

        return $deliveryAddress;
    }
}
