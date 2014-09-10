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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Cart\CartTrait;
use Thelia\Core\Event\Order\OrderAddressEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderManualEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\SecurityContext;
use Thelia\Exception\TheliaProcessException;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\Customer as CustomerModel;
use Thelia\Model\Lang as LangModel;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order as ModelOrder;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductAttributeCombination;
use Thelia\Model\OrderStatusQuery;
use Thelia\Tools\I18n;

/**
 *
 * Class Order
 * @package Thelia\Action
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Order extends BaseAction implements EventSubscriberInterface
{
    use CartTrait;

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

    protected function createOrder(EventDispatcherInterface $dispatcher, ModelOrder $sessionOrder, CurrencyModel $currency, LangModel $lang, CartModel $cart, CustomerModel $customer)
    {
        $con = \Propel\Runtime\Propel::getConnection(
                OrderTableMap::DATABASE_NAME
        );

        $con->beginTransaction();

        $placedOrder = $sessionOrder->copy();
        $placedOrder->setDispatcher($dispatcher);

        $deliveryAddress = AddressQuery::create()->findPk($sessionOrder->getChoosenDeliveryAddress());
        $taxCountry = $deliveryAddress->getCountry();
        $invoiceAddress = AddressQuery::create()->findPk($sessionOrder->getChoosenInvoiceAddress());
        $cartItems = $cart->getCartItems();

        /* fulfill order */
        $placedOrder->setCustomerId($customer->getId());
        $placedOrder->setCurrencyId($currency->getId());
        $placedOrder->setCurrencyRate($currency->getRate());
        $placedOrder->setLangId($lang->getId());

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
            ->setCountryId($deliveryAddress->getCountryId())
        ->save($con)
        ;

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
            ->setCountryId($invoiceAddress->getCountryId())
        ->save($con)
        ;

        $placedOrder->setDeliveryOrderAddressId($deliveryOrderAddress->getId());
        $placedOrder->setInvoiceOrderAddressId($invoiceOrderAddress->getId());

        $placedOrder->setStatusId(
                OrderStatusQuery::getNotPaidStatus()->getId()
        );

        $placedOrder->setCart($cart);

        /* memorize discount */
        $placedOrder->setDiscount(
                $cart->getDiscount()
        );

        $placedOrder->save($con);

        /* fulfill order_products and decrease stock */

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProduct();

            /* get translation */
            $productI18n = I18n::forceI18nRetrieving($lang->getLocale(), 'Product', $product->getId());

            $pse = $cartItem->getProductSaleElements();

            /* check still in stock */
            if ($cartItem->getQuantity() > $pse->getQuantity() && true === ConfigQuery::checkAvailableStock()) {
                throw new TheliaProcessException("Not enough stock", TheliaProcessException::CART_ITEM_NOT_ENOUGH_STOCK, $cartItem);
            }

            /* decrease stock */
            $newStock = $pse->getQuantity() - $cartItem->getQuantity();
            if($newStock < 0) $newStock = 0; //Forbid negative stock
            $pse->setQuantity(
                    $newStock
            );
            $pse->save($con);

            /* get tax */
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
                ->setTitle($productI18n->getTitle())
                ->setChapo($productI18n->getChapo())
                ->setDescription($productI18n->getDescription())
                ->setPostscriptum($productI18n->getPostscriptum())
                ->setQuantity($cartItem->getQuantity())
                ->setPrice($cartItem->getPrice())
                ->setPromoPrice($cartItem->getPromoPrice())
                ->setWasNew($pse->getNewness())
                ->setWasInPromo($cartItem->getPromo())
                ->setWeight($pse->getWeight())
                ->setTaxRuleTitle($taxRuleI18n->getTitle())
                ->setTaxRuleDescription($taxRuleI18n->getDescription())
                ->setEanCode($pse->getEanCode())
                ->setCartIemId($cartItem->getId())
                ->setDispatcher($dispatcher)
            ->save($con)
            ;

            /* fulfill order_product_tax */
            foreach ($taxDetail as $tax) {
                $tax->setOrderProductId($orderProduct->getId());
                $tax->save($con);
            }

            /* fulfill order_attribute_combination and decrease stock */
            foreach ($pse->getAttributeCombinations() as $attributeCombination) {
                $attribute = I18n::forceI18nRetrieving($lang->getLocale(), 'Attribute', $attributeCombination->getAttributeId());
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
     */
    public function createManual(OrderManualEvent $event)
    {
        $event->setPlacedOrder(
            $this->createOrder(
                $event->getDispatcher(),
                $event->getOrder(),
                $event->getCurrency(),
                $event->getLang(),
                $event->getCart(),
                $event->getCustomer()
            )
        );

        $event->setOrder(new \Thelia\Model\Order());
    }

    /**
     * @param OrderEvent $event
     *
     * @throws \Thelia\Exception\TheliaProcessException
     */
    public function create(OrderEvent $event)
    {
        $session = $this->getSession();

        $placedOrder = $this->createOrder(
            $event->getDispatcher(),
            $event->getOrder(),
            $session->getCurrency(),
            $session->getLang(),
            $session->getCart(),
            $this->securityContext->getCustomerUser()
        );

        $event->getDispatcher()->dispatch(TheliaEvents::ORDER_BEFORE_PAYMENT, new OrderEvent($placedOrder));

        /* but memorize placed order */
        $event->setOrder(new \Thelia\Model\Order());
        $event->setPlacedOrder($placedOrder);

        /* empty cart */
        $dispatcher = $event->getDispatcher();

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
    public function orderBeforePayement(OrderEvent $event)
    {
        $event->getDispatcher()->dispatch(TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL, $event);

        $event->getDispatcher()->dispatch(TheliaEvents::ORDER_SEND_NOTIFICATION_EMAIL, $event);
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
            ]);
    }

    /**
     * @param OrderEvent $event
     */
    public function updateStatus(OrderEvent $event)
    {
        $order = $event->getOrder();

        $order->setStatusId($event->getStatus());
        $order->save();

        $event->setOrder($order);
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
            ->setPhone($event->getPhone())
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
            TheliaEvents::ORDER_BEFORE_PAYMENT => array("orderBeforePayement", 128),
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
