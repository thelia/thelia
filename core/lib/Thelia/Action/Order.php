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

namespace Thelia\Action;

use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderAddressEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderManualEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\Product\VirtualProductOrderHandleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Exception\TheliaProcessException;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\Lang as LangModel;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order as ModelOrder;
use Thelia\Model\Order as OrderModel;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductAttributeCombination;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\ProductI18n;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleI18n;
use Thelia\Tools\I18n;

/**
 *
 * Class Order
 * @package Thelia\Action
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Order extends BaseAction implements EventSubscriberInterface
{
    /**
     * @var \Thelia\Core\HttpFoundation\Request
     */
    protected $request;
    /**
     * @var MailerFactory
     */
    protected $mailer;
    /**
     * @var SecurityContext
     */
    protected $securityContext;

    public function __construct(Request $request, MailerFactory $mailer, SecurityContext $securityContext)
    {
        $this->request = $request;
        $this->mailer = $mailer;
        $this->securityContext = $securityContext;
    }

    /**
     * @param \Thelia\Core\Event\Order\OrderEvent $event
     */
    public function setDeliveryAddress(OrderEvent $event)
    {
        $order = $event->getOrder();

        $order->setChoosenDeliveryAddress($event->getDeliveryAddress());

        $event->setOrder($order);
    }

    /**
     * @param \Thelia\Core\Event\Order\OrderEvent $event
     */
    public function setDeliveryModule(OrderEvent $event)
    {
        $order = $event->getOrder();

        $deliveryModuleId = $event->getDeliveryModule();

        $order->setDeliveryModuleId($deliveryModuleId);

        // Reset postage cost if the delivery module had been removed
        if ($deliveryModuleId <= 0) {
            $order->setPostage(0);
            $order->setPostageTax(0);
            $order->setPostageTaxRuleTitle(null);
        }

        $event->setOrder($order);
    }

    /**
     * @param \Thelia\Core\Event\Order\OrderEvent $event
     */
    public function setPostage(OrderEvent $event)
    {
        $order = $event->getOrder();

        $order->setPostage($event->getPostage());
        $order->setPostageTax($event->getPostageTax());
        $order->setPostageTaxRuleTitle($event->getPostageTaxRuleTitle());

        $event->setOrder($order);
    }

    /**
     * @param \Thelia\Core\Event\Order\OrderEvent $event
     */
    public function setInvoiceAddress(OrderEvent $event)
    {
        $order = $event->getOrder();

        $order->setChoosenInvoiceAddress($event->getInvoiceAddress());

        $event->setOrder($order);
    }

    /**
     * @param \Thelia\Core\Event\Order\OrderEvent $event
     */
    public function setPaymentModule(OrderEvent $event)
    {
        $order = $event->getOrder();

        $order->setPaymentModuleId($event->getPaymentModule());

        $event->setOrder($order);
    }

    /**
     * @param EventDispatcherInterface $dispatcher
     * @param ModelOrder $sessionOrder
     * @param CurrencyModel $currency
     * @param LangModel $lang
     * @param CartModel $cart
     * @param UserInterface $customer
     * @param bool $manageStock decrement stock when order is created if true
     * @param bool $useOrderDefinedAddresses if true, the delivery and invoice OrderAddresses will be used instead of creating new OrderAdresses using Order::getChoosenXXXAddress()
     * @return ModelOrder
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function createOrder(
        EventDispatcherInterface $dispatcher,
        ModelOrder $sessionOrder,
        CurrencyModel $currency,
        LangModel $lang,
        CartModel $cart,
        UserInterface $customer,
        $manageStock,
        $useOrderDefinedAddresses = false
    ) {
        $con = Propel::getConnection(
            OrderTableMap::DATABASE_NAME
        );

        $con->beginTransaction();

        $placedOrder = $sessionOrder->copy();

        // Be sure to create a brand new order, as copy raises the modified flag for all fields
        // and will also copy order reference and id.
        $placedOrder->setId(null)->setRef(null)->setNew(true);

        // Dates should be marked as not updated so that Propel will update them.
        $placedOrder->resetModified(OrderTableMap::CREATED_AT);
        $placedOrder->resetModified(OrderTableMap::UPDATED_AT);
        $placedOrder->resetModified(OrderTableMap::VERSION_CREATED_AT);

        $placedOrder->setDispatcher($dispatcher);

        $cartItems = $cart->getCartItems();

        /* fulfill order */
        $placedOrder->setCustomerId($customer->getId());
        $placedOrder->setCurrencyId($currency->getId());
        $placedOrder->setCurrencyRate($currency->getRate());
        $placedOrder->setLangId($lang->getId());
        
        if ($useOrderDefinedAddresses) {
            $taxCountry =
                OrderAddressQuery::create()
                    ->findPk($placedOrder->getDeliveryOrderAddressId())
                    ->getCountry()
            ;
        } else {
            $deliveryAddress = AddressQuery::create()->findPk($sessionOrder->getChoosenDeliveryAddress());
            $invoiceAddress = AddressQuery::create()->findPk($sessionOrder->getChoosenInvoiceAddress());

            /* hard save the delivery and invoice addresses */
            $deliveryOrderAddress = new OrderAddress();
            $deliveryOrderAddress
                ->setCustomerTitleId($deliveryAddress->getTitleId())
                ->setCompany($deliveryAddress->getCompany())
                ->setFirstname($deliveryAddress->getFirstname())
                ->setLastname($deliveryAddress->getLastname())
                ->setAddress1($deliveryAddress->getAddress1())
                ->setAddress2($deliveryAddress->getAddress2())
                ->setAddress3($deliveryAddress->getAddress3())
                ->setZipcode($deliveryAddress->getZipcode())
                ->setCity($deliveryAddress->getCity())
                ->setPhone($deliveryAddress->getPhone())
                ->setCellphone($deliveryAddress->getCellphone())
                ->setCountryId($deliveryAddress->getCountryId())
                ->setStateId($deliveryAddress->getStateId())
                ->save($con);

            $invoiceOrderAddress = new OrderAddress();
            $invoiceOrderAddress
                ->setCustomerTitleId($invoiceAddress->getTitleId())
                ->setCompany($invoiceAddress->getCompany())
                ->setFirstname($invoiceAddress->getFirstname())
                ->setLastname($invoiceAddress->getLastname())
                ->setAddress1($invoiceAddress->getAddress1())
                ->setAddress2($invoiceAddress->getAddress2())
                ->setAddress3($invoiceAddress->getAddress3())
                ->setZipcode($invoiceAddress->getZipcode())
                ->setCity($invoiceAddress->getCity())
                ->setPhone($invoiceAddress->getPhone())
                ->setCellphone($invoiceAddress->getCellphone())
                ->setCountryId($invoiceAddress->getCountryId())
                ->setStateId($deliveryAddress->getStateId())
                ->save($con);

            $placedOrder->setDeliveryOrderAddressId($deliveryOrderAddress->getId());
            $placedOrder->setInvoiceOrderAddressId($invoiceOrderAddress->getId());

            $taxCountry = $deliveryAddress->getCountry();
        }

        $placedOrder->setStatusId(
            OrderStatusQuery::getNotPaidStatus()->getId()
        );

        $placedOrder->setCartId($cart->getId());

        /* memorize discount */
        $placedOrder->setDiscount(
            $cart->getDiscount()
        );

        $placedOrder->save($con);

        /* fulfill order_products and decrease stock */

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProduct();

            /* get translation */
            /** @var ProductI18n $productI18n */
            $productI18n = I18n::forceI18nRetrieving($lang->getLocale(), 'Product', $product->getId());

            $pse = $cartItem->getProductSaleElements();

            // get the virtual document path
            $virtualDocumentEvent = new VirtualProductOrderHandleEvent($placedOrder, $pse->getId());
            // essentially used for virtual product. modules that handles virtual product can
            // allow the use of stock even for virtual products
            $useStock = true;
            $virtual = 0;

            // if the product is virtual, dispatch an event to collect information
            if ($product->getVirtual() === 1) {
                $dispatcher->dispatch(TheliaEvents::VIRTUAL_PRODUCT_ORDER_HANDLE, $virtualDocumentEvent);
                $useStock = $virtualDocumentEvent->isUseStock();
                $virtual = $virtualDocumentEvent->isVirtual() ? 1 : 0;
            }

            /* check still in stock */
            if ($cartItem->getQuantity() > $pse->getQuantity()
                    && true === ConfigQuery::checkAvailableStock()
                    && $useStock) {
                throw new TheliaProcessException("Not enough stock", TheliaProcessException::CART_ITEM_NOT_ENOUGH_STOCK, $cartItem);
            }

            if ($useStock && $manageStock) {
                /* decrease stock for non virtual product */
                $allowNegativeStock = intval(ConfigQuery::read('allow_negative_stock', 0));
                $newStock = $pse->getQuantity() - $cartItem->getQuantity();
                //Forbid negative stock
                if ($newStock < 0 && 0 === $allowNegativeStock) {
                    $newStock = 0;
                }
                $pse->setQuantity(
                    $newStock
                );

                $pse->save($con);
            }

            /* get tax */
            /** @var TaxRuleI18n $taxRuleI18n */
            $taxRuleI18n = I18n::forceI18nRetrieving($lang->getLocale(), 'TaxRule', $product->getTaxRuleId());

            $taxDetail = $product->getTaxRule()->getTaxDetail(
                $product,
                $taxCountry,
                $cartItem->getPrice(),
                $cartItem->getPromoPrice(),
                $lang->getLocale()
            );

            $orderProduct = new OrderProduct();
            $orderProduct
                ->setOrderId($placedOrder->getId())
                ->setProductRef($product->getRef())
                ->setProductSaleElementsRef($pse->getRef())
                ->setProductSaleElementsId($pse->getId())
                ->setTitle($productI18n->getTitle())
                ->setChapo($productI18n->getChapo())
                ->setDescription($productI18n->getDescription())
                ->setPostscriptum($productI18n->getPostscriptum())
                ->setVirtual($virtual)
                ->setVirtualDocument($virtualDocumentEvent->getPath())
                ->setQuantity($cartItem->getQuantity())
                ->setPrice($cartItem->getPrice())
                ->setPromoPrice($cartItem->getPromoPrice())
                ->setWasNew($pse->getNewness())
                ->setWasInPromo($cartItem->getPromo())
                ->setWeight($pse->getWeight())
                ->setTaxRuleTitle($taxRuleI18n->getTitle())
                ->setTaxRuleDescription($taxRuleI18n->getDescription())
                ->setEanCode($pse->getEanCode())
                ->setCartItemId($cartItem->getId())
                ->setDispatcher($dispatcher)
                ->save($con)
            ;

            /* fulfill order_product_tax */
            /** @var OrderProductTax $tax */
            foreach ($taxDetail as $tax) {
                $tax->setOrderProductId($orderProduct->getId());
                $tax->save($con);
            }

            /* fulfill order_attribute_combination and decrease stock */
            foreach ($pse->getAttributeCombinations() as $attributeCombination) {
                /** @var \Thelia\Model\Attribute $attribute */
                $attribute = I18n::forceI18nRetrieving($lang->getLocale(), 'Attribute', $attributeCombination->getAttributeId());

                /** @var \Thelia\Model\AttributeAv $attributeAv */
                $attributeAv = I18n::forceI18nRetrieving($lang->getLocale(), 'AttributeAv', $attributeCombination->getAttributeAvId());

                $orderAttributeCombination = new OrderProductAttributeCombination();
                $orderAttributeCombination
                    ->setOrderProductId($orderProduct->getId())
                    ->setAttributeTitle($attribute->getTitle())
                    ->setAttributeChapo($attribute->getChapo())
                    ->setAttributeDescription($attribute->getDescription())
                    ->setAttributePostscriptum($attribute->getPostscriptum())
                    ->setAttributeAvTitle($attributeAv->getTitle())
                    ->setAttributeAvChapo($attributeAv->getChapo())
                    ->setAttributeAvDescription($attributeAv->getDescription())
                    ->setAttributeAvPostscriptum($attributeAv->getPostscriptum())
                    ->save($con);
            }
        }

        $con->commit();

        return $placedOrder;
    }

    /**
     * Create an order outside of the front-office context, e.g. manually from the back-office.
     * @param OrderManualEvent $event
     */
    public function createManual(OrderManualEvent $event)
    {
        $paymentModule = ModuleQuery::create()->findPk($event->getOrder()->getPaymentModuleId());

        /** @var \Thelia\Module\PaymentModuleInterface $paymentModuleInstance */
        $paymentModuleInstance = $paymentModule->createInstance();

        $event->setPlacedOrder(
            $this->createOrder(
                $event->getDispatcher(),
                $event->getOrder(),
                $event->getCurrency(),
                $event->getLang(),
                $event->getCart(),
                $event->getCustomer(),
                $paymentModuleInstance->manageStockOnCreation(),
                $event->getUseOrderDefinedAddresses()
            )
        );

        $event->setOrder(new OrderModel());
    }

    /**
     * @param OrderEvent $event
     *
     * @throws \Thelia\Exception\TheliaProcessException
     */
    public function create(OrderEvent $event)
    {
        $session = $this->getSession();

        $dispatcher = $event->getDispatcher();
        $order = $event->getOrder();
        $paymentModule = ModuleQuery::create()->findPk($order->getPaymentModuleId());

        /** @var \Thelia\Module\PaymentModuleInterface $paymentModuleInstance */
        $paymentModuleInstance = $paymentModule->createInstance();

        $placedOrder = $this->createOrder(
            $event->getDispatcher(),
            $event->getOrder(),
            $session->getCurrency(),
            $session->getLang(),
            $session->getSessionCart($dispatcher),
            $this->securityContext->getCustomerUser(),
            $paymentModuleInstance->manageStockOnCreation()
        );

        $event->getDispatcher()->dispatch(TheliaEvents::ORDER_BEFORE_PAYMENT, new OrderEvent($placedOrder));

        /* but memorize placed order */
        $event->setOrder(new OrderModel());
        $event->setPlacedOrder($placedOrder);

        /* call pay method */
        $payEvent = new OrderPaymentEvent($placedOrder);

        $dispatcher->dispatch(TheliaEvents::MODULE_PAY, $payEvent);

        if ($payEvent->hasResponse()) {
            $event->setResponse($payEvent->getResponse());
        }
    }

    /**
     * @param OrderEvent $event
     */
    public function orderBeforePayment(OrderEvent $event)
    {
        $event->getDispatcher()->dispatch(TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL, clone $event);

        $event->getDispatcher()->dispatch(TheliaEvents::ORDER_SEND_NOTIFICATION_EMAIL, clone $event);
    }

    /**
     * Clear the cart and the order in the customer session once the order is placed,
     * and the payment performed.
     *
     * @param OrderEvent $event
     */
    public function orderCartClear(/** @noinspection PhpUnusedParameterInspection */ OrderEvent $event)
    {
        // Empty cart and clear current order
        $session = $this->getSession();

        $session->clearSessionCart($event->getDispatcher());

        $session->setOrder(new OrderModel());
    }

    /**
     * @param OrderEvent $event
     *
     * @throws \Exception if the message cannot be loaded.
     */
    public function sendConfirmationEmail(OrderEvent $event)
    {
        $this->mailer->sendEmailToCustomer(
            'order_confirmation',
            $event->getOrder()->getCustomer(),
            [
                'order_id' => $event->getOrder()->getId(),
                'order_ref' => $event->getOrder()->getRef()
            ]
        );
    }

    /**
     * @param OrderEvent $event
     *
     * @throws \Exception if the message cannot be loaded.
     */
    public function sendNotificationEmail(OrderEvent $event)
    {
        $this->mailer->sendEmailToShopManagers(
            'order_notification',
            [
                'order_id' => $event->getOrder()->getId(),
                'order_ref' => $event->getOrder()->getRef()
            ]
        );
    }

    /**
     * @param OrderEvent $event
     */
    public function updateStatus(OrderEvent $event)
    {
        $order = $event->getOrder();
        $newStatus = $event->getStatus();

        $this->updateQuantity($order, $newStatus);

        $order->setStatusId($newStatus);
        $order->save();

        $event->setOrder($order);
    }

    /**
     * @param ModelOrder $order
     * @param $newStatus $newStatus the new status ID
     * @throws \Thelia\Exception\TheliaProcessException
     */
    public function updateQuantity(ModelOrder $order, $newStatus)
    {
        $canceledStatus = OrderStatusQuery::getCancelledStatus()->getId();
        $paidStatus = OrderStatusQuery::getPaidStatus()->getId();
        if ($newStatus == $canceledStatus || $order->isCancelled()) {
            $this->updateQuantityForCanceledOrder($order, $newStatus, $canceledStatus);
        } elseif ($paidStatus == $newStatus && $order->isNotPaid() && $order->getVersion() == 1) {
            $this->updateQuantityForPaidOrder($order);
        }
    }

    /**
     * @param ModelOrder $order
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function updateQuantityForPaidOrder(ModelOrder $order)
    {
        $paymentModule = ModuleQuery::create()->findPk($order->getPaymentModuleId());

        /** @var \Thelia\Module\PaymentModuleInterface $paymentModuleInstance */
        $paymentModuleInstance = $paymentModule->createInstance();

        if (false === $paymentModuleInstance->manageStockOnCreation()) {
            $orderProductList = $order->getOrderProducts();

            /** @var OrderProduct  $orderProduct */
            foreach ($orderProductList as $orderProduct) {
                $productSaleElementsId = $orderProduct->getProductSaleElementsId();

                /** @var ProductSaleElements $productSaleElements */
                if (null !== $productSaleElements = ProductSaleElementsQuery::create()->findPk($productSaleElementsId)) {
                    /* check still in stock */
                    if ($orderProduct->getQuantity() > $productSaleElements->getQuantity() && true === ConfigQuery::checkAvailableStock()) {
                        throw new TheliaProcessException($productSaleElements->getRef() . " : Not enough stock");
                    }

                    $productSaleElements->setQuantity($productSaleElements->getQuantity() - $orderProduct->getQuantity());

                    $productSaleElements->save();
                }
            }
        }
    }

    /**
     * Update product quantity if new status is canceled or if old status is canceled.
     *
     * @param ModelOrder $order
     * @param $newStatus
     * @param $canceledStatus
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    protected function updateQuantityForCanceledOrder(ModelOrder $order, $newStatus, $canceledStatus)
    {
        $orderProductList = $order->getOrderProducts();

        /** @var OrderProduct  $orderProduct */
        foreach ($orderProductList as $orderProduct) {
            $productSaleElementsId = $orderProduct->getProductSaleElementsId();

            /** @var ProductSaleElements $productSaleElements */
            if (null !== $productSaleElements = ProductSaleElementsQuery::create()->findPk($productSaleElementsId)) {
                if ($newStatus == $canceledStatus) {
                    $productSaleElements->setQuantity($productSaleElements->getQuantity() + $orderProduct->getQuantity());
                } else {
                    /* check still in stock */
                    if ($orderProduct->getQuantity() > $productSaleElements->getQuantity() && true === ConfigQuery::checkAvailableStock()) {
                        throw new TheliaProcessException($productSaleElements->getRef() . " : Not enough stock");
                    }

                    $productSaleElements->setQuantity($productSaleElements->getQuantity() - $orderProduct->getQuantity());
                }

                $productSaleElements->save();
            }
        }
    }

    /**
     * @param OrderEvent $event
     */
    public function updateDeliveryRef(OrderEvent $event)
    {
        $order = $event->getOrder();

        $order->setDeliveryRef($event->getDeliveryRef());
        $order->save();

        $event->setOrder($order);
    }

    /**
     * @param OrderAddressEvent $event
     */
    public function updateAddress(OrderAddressEvent $event)
    {
        $orderAddress = $event->getOrderAddress();

        $orderAddress
            ->setCustomerTitleId($event->getTitle())
            ->setCompany($event->getCompany())
            ->setFirstname($event->getFirstname())
            ->setLastname($event->getLastname())
            ->setAddress1($event->getAddress1())
            ->setAddress2($event->getAddress2())
            ->setAddress3($event->getAddress3())
            ->setZipcode($event->getZipcode())
            ->setCity($event->getCity())
            ->setCountryId($event->getCountry())
            ->setStateId($event->getState())
            ->setPhone($event->getPhone())
            ->setCellphone($event->getCellphone())
        ;
        $orderAddress->save();

        $event->setOrderAddress($orderAddress);
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::ORDER_SET_DELIVERY_ADDRESS => array("setDeliveryAddress", 128),
            TheliaEvents::ORDER_SET_DELIVERY_MODULE => array("setDeliveryModule", 128),
            TheliaEvents::ORDER_SET_POSTAGE => array("setPostage", 128),
            TheliaEvents::ORDER_SET_INVOICE_ADDRESS => array("setInvoiceAddress", 128),
            TheliaEvents::ORDER_SET_PAYMENT_MODULE => array("setPaymentModule", 128),
            TheliaEvents::ORDER_PAY => array("create", 128),
            TheliaEvents::ORDER_CART_CLEAR => array("orderCartClear", 128),
            TheliaEvents::ORDER_BEFORE_PAYMENT => array("orderBeforePayment", 128),
            TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL => array("sendConfirmationEmail", 128),
            TheliaEvents::ORDER_SEND_NOTIFICATION_EMAIL => array("sendNotificationEmail", 128),
            TheliaEvents::ORDER_UPDATE_STATUS => array("updateStatus", 128),
            TheliaEvents::ORDER_UPDATE_DELIVERY_REF => array("updateDeliveryRef", 128),
            TheliaEvents::ORDER_UPDATE_ADDRESS => array("updateAddress", 128),
            TheliaEvents::ORDER_CREATE_MANUAL => array("createManual", 128),
        );
    }

    /**
     * Returns the session from the current request
     *
     * @return \Thelia\Core\HttpFoundation\Session\Session
     */
    protected function getSession()
    {
        return $this->request->getSession();
    }
}
