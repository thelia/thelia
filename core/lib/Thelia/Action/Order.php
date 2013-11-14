<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
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
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Action;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Cart\CartTrait;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Order\OrderAddressEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\AddressQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\MessageQuery;
use Thelia\Model\OrderProductAttributeCombination;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatus;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderAddress;
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
     * @param \Thelia\Core\Event\Order\OrderEvent $event
     */
    public function setDeliveryAddress(OrderEvent $event)
    {
        $order = $event->getOrder();

        $order->chosenDeliveryAddress = $event->getDeliveryAddress();

        $event->setOrder($order);
    }

    /**
     * @param \Thelia\Core\Event\Order\OrderEvent $event
     */
    public function setDeliveryModule(OrderEvent $event)
    {
        $order = $event->getOrder();

        $order->setDeliveryModuleId($event->getDeliveryModule());
        $order->setPostage($event->getPostage());

        $event->setOrder($order);
    }

    /**
     * @param \Thelia\Core\Event\Order\OrderEvent $event
     */
    public function setInvoiceAddress(OrderEvent $event)
    {
        $order = $event->getOrder();

        $order->chosenInvoiceAddress = $event->getInvoiceAddress();

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
     * @param OrderEvent $event
     *
     * @throws \Thelia\Exception\TheliaProcessException
     */
    public function create(OrderEvent $event)
    {
        $con = \Propel\Runtime\Propel::getConnection(
            OrderTableMap::DATABASE_NAME
        );

        $con->beginTransaction();

        $sessionOrder = $event->getOrder();

        /* use a copy to avoid errored reccord in session */
        $placedOrder = $sessionOrder->copy();
        $placedOrder->setDispatcher($this->getDispatcher());

        $customer = $this->getSecurityContext()->getCustomerUser();
        $currency = $this->getSession()->getCurrency();
        $lang = $this->getSession()->getLang();
        $deliveryAddress = AddressQuery::create()->findPk($sessionOrder->chosenDeliveryAddress);
        $taxCountry = $deliveryAddress->getCountry();
        $invoiceAddress = AddressQuery::create()->findPk($sessionOrder->chosenInvoiceAddress);
        $cart = $this->getSession()->getCart();
        $cartItems = $cart->getCartItems();

        $paymentModule = ModuleQuery::create()->findPk($placedOrder->getPaymentModuleId());

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
            OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_NOT_PAID)->getId()
        );

        $placedOrder->save($con);

        /* fulfill order_products and decrease stock */

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->getProduct();

            /* get translation */
            $productI18n = I18n::forceI18nRetrieving($this->getSession()->getLang()->getLocale(), 'Product', $product->getId());

            $pse = $cartItem->getProductSaleElements();

            /* check still in stock */
            if ($cartItem->getQuantity() > $pse->getQuantity()) {
                throw new TheliaProcessException("Not enough stock", TheliaProcessException::CART_ITEM_NOT_ENOUGH_STOCK, $cartItem);
            }

            /* decrease stock */
            $pse->setQuantity(
                $pse->getQuantity() - $cartItem->getQuantity()
            );
            $pse->save($con);

            /* get tax */
            $taxRuleI18n = I18n::forceI18nRetrieving($this->getSession()->getLang()->getLocale(), 'TaxRule', $product->getTaxRuleId());

            $taxDetail = $product->getTaxRule()->getTaxDetail(
                $product,
                $taxCountry,
                $cartItem->getPrice(),
                $cartItem->getPromoPrice(),
                $this->getSession()->getLang()->getLocale()
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
            ;
            $orderProduct->setDispatcher($this->getDispatcher());
            $orderProduct->save($con);

            /* fulfill order_product_tax */
            foreach ($taxDetail as $tax) {
                $tax->setOrderProductId($orderProduct->getId());
                $tax->save($con);
            }

            /* fulfill order_attribute_combination and decrease stock */
            foreach ($pse->getAttributeCombinations() as $attributeCombination) {
                $attribute = I18n::forceI18nRetrieving($this->getSession()->getLang()->getLocale(), 'Attribute', $attributeCombination->getAttributeId());
                $attributeAv = I18n::forceI18nRetrieving($this->getSession()->getLang()->getLocale(), 'AttributeAv', $attributeCombination->getAttributeAvId());

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
                ;

                $orderAttributeCombination->save($con);
            }
        }

        /* discount @todo */

        $con->commit();

        $this->getDispatcher()->dispatch(TheliaEvents::ORDER_BEFORE_PAYMENT, new OrderEvent($placedOrder));

        /* clear session */
        /* but memorize placed order */
        $sessionOrder = new \Thelia\Model\Order();
        $event->setOrder($sessionOrder);
        $event->setPlacedOrder($placedOrder);
        $this->getSession()->setOrder($sessionOrder);

        /* empty cart */
        $this->getDispatcher()->dispatch(TheliaEvents::CART_CLEAR, new CartEvent($this->getCart($this->getRequest())));

        /* call pay method */
        /*$paymentModuleReflection = new \ReflectionClass($paymentModule->getFullNamespace());
        $paymentModuleInstance = $paymentModuleReflection->newInstance();

        $paymentModuleInstance->setRequest($this->getRequest());
        $paymentModuleInstance->setDispatcher($this->getDispatcher());*/

        $paymentModuleInstance = $this->container->get(sprintf('module.%s', $paymentModule->getCode()));
        $paymentModuleInstance->pay($placedOrder);
    }

    /**
     * @param \Thelia\Core\Event\Order\OrderEvent $event
     */
    public function sendOrderEmail(OrderEvent $event)
    {
        $contact_email = ConfigQuery::read('contact_email');
        if($contact_email) {
            $order = $event->getOrder();
            $customer = $order->getCustomer();

            $parser = $this->container->get("thelia.parser");

            $parser->assign('order_id', $order->getId());
            $parser->assign('order_ref', $order->getRef());

            $message = MessageQuery::create()
                ->filterByName('order_confirmation')
                ->findOne();

            $message
                ->setLocale($order->getLang()->getLocale());

            $subject = $parser->fetch(sprintf("string:%s", $message->getSubject()));
            $htmlMessage = $parser->fetch(sprintf("string:%s", $message->getHtmlMessage()));
            $textMessage = $parser->fetch(sprintf("string:%s", $message->getTextMessage()));

            $instance = \Swift_Message::newInstance($subject)
                ->addTo($customer->getEmail(), $customer->getFirstname()." ".$customer->getLastname())
                ->addFrom(ConfigQuery::read('contact_email'), ConfigQuery::read('company_name'))
            ;
            $instance
                ->setBody($htmlMessage, 'text/html')
                ->addPart($textMessage, 'text/plain');

            $mail = $this->getMailer()->send($instance);
        }
    }

    /**
     *
     * return an instance of \Swift_Mailer with good Transporter configured.
     *
     * @return \Swift_Mailer
     */
    public function getMailer()
    {
        $mailer = $this->container->get('mailer');

        return $mailer->getSwiftMailer();
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
            TheliaEvents::ORDER_SET_INVOICE_ADDRESS => array("setInvoiceAddress", 128),
            TheliaEvents::ORDER_SET_PAYMENT_MODULE => array("setPaymentModule", 128),
            TheliaEvents::ORDER_PAY => array("create", 128),
            TheliaEvents::ORDER_BEFORE_PAYMENT => array("sendOrderEmail", 128),
            TheliaEvents::ORDER_UPDATE_STATUS => array("updateStatus", 128),
            TheliaEvents::ORDER_UPDATE_DELIVERY_REF => array("updateDeliveryRef", 128),
            TheliaEvents::ORDER_UPDATE_ADDRESS => array("updateAddress", 128),
        );
    }

    /**
     * Return the security context
     *
     * @return SecurityContext
     */
    protected function getSecurityContext()
    {
        return $this->container->get('thelia.securityContext');
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Request
     */
    protected function getRequest()
    {
        return $this->container->get('request');
    }

    /**
     * Returns the session from the current request
     *
     * @return \Thelia\Core\HttpFoundation\Session\Session
     */
    protected function getSession()
    {
        $request = $this->getRequest();

        return $request->getSession();
    }
}
