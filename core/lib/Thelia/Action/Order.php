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

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Exception\OrderException;
use Thelia\Model\AttributeAvI18n;
use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\AttributeI18n;
use Thelia\Model\AttributeI18nQuery;
use Thelia\Model\AddressQuery;
use Thelia\Model\OrderProductAttributeCombination;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\Lang;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatus;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductI18n;

/**
 *
 * Class Order
 * @package Thelia\Action
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Order extends BaseAction implements EventSubscriberInterface
{
    /**
     * @param \Thelia\Core\Event\OrderEvent $event
     */
    public function setDeliveryAddress(OrderEvent $event)
    {
        $order = $event->getOrder();

        $order->chosenDeliveryAddress = $event->getDeliveryAddress();

        $event->setOrder($order);
    }

    /**
     * @param \Thelia\Core\Event\OrderEvent $event
     */
    public function setDeliveryModule(OrderEvent $event)
    {
        $order = $event->getOrder();

        $order->setDeliveryModuleId($event->getDeliveryModule());
        $order->setPostage($event->getPostage());

        $event->setOrder($order);
    }

    /**
     * @param \Thelia\Core\Event\OrderEvent $event
     */
    public function setInvoiceAddress(OrderEvent $event)
    {
        $order = $event->getOrder();

        $order->chosenInvoiceAddress = $event->getInvoiceAddress();

        $event->setOrder($order);
    }

    /**
     * @param \Thelia\Core\Event\OrderEvent $event
     */
    public function setPaymentModule(OrderEvent $event)
    {
        $order = $event->getOrder();

        $order->setPaymentModuleId($event->getPaymentModule());

        $event->setOrder($order);
    }

    /**
     * @param \Thelia\Core\Event\OrderEvent $event
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

        foreach($cartItems as $cartItem) {
            $product = $cartItem->getProduct();

            /* get customer translation */
            $productI18n = $this->getI18n(ProductI18nQuery::create(), new ProductI18n(), $product->getId());

            $pse = $cartItem->getProductSaleElements();

            /* check still in stock */
            if($cartItem->getQuantity() > $pse->getQuantity()) {
                $e = new OrderException("Not enough stock", OrderException::NOT_ENOUGH_STOCK);
                $e->cartItem = $cartItem;
                throw $e;
            }

            /* decrease stock */
            $pse->setQuantity(
                $pse->getQuantity() - $cartItem->getQuantity()
            );
            $pse->save();

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
            ;
            $orderProduct->setDispatcher($this->getDispatcher());
            $orderProduct->save();

            /* fulfill order_attribute_combination and decrease stock */
            foreach($pse->getAttributeCombinations() as $attributeCombination) {
                $attribute = $this->getI18n(AttributeI18nQuery::create(), new AttributeI18n(), $attributeCombination->getAttributeId());
                $attributeAv = $this->getI18n(AttributeAvI18nQuery::create(), new AttributeAvI18n(), $attributeCombination->getAttributeAvId());

                $orderAttributeCombination = new OrderProductAttributeCombination();
                $orderAttributeCombination
                    ->setOrderProductId($orderProduct->getId())
                    ->setAttributeTitle($attribute->getTitle())
                    ->setAttributeChapo($attribute->getChapo())
                    ->setAttributeDescription($attribute->getDescription())
                    ->setAttributePostscriptumn($attribute->getPostscriptum())
                    ->setAttributeAvTitle($attributeAv->getTitle())
                    ->setAttributeAvChapo($attributeAv->getChapo())
                    ->setAttributeAvDescription($attributeAv->getDescription())
                    ->setAttributeAvPostscriptum($attributeAv->getPostscriptum())
                ;

                $orderAttributeCombination->save();
            }
        }

        /* discount @todo */

        $con->commit();

        $this->getDispatcher()->dispatch(TheliaEvents::ORDER_BEFORE_PAYMENT, new OrderEvent($placedOrder));

        /* clear session */
        $sessionOrder = new \Thelia\Model\Order();
        $event->setOrder($sessionOrder);
        $this->getSession()->setOrder($sessionOrder);

        /* empty cart */
        $this->getSession()->getCart()->clear();

        /* call pay method */
        $paymentModuleReflection = new \ReflectionClass($paymentModule->getFullNamespace());
        $paymentModuleInstance = $paymentModuleReflection->newInstance();

        $paymentModuleInstance->setRequest($this->getRequest());
        $paymentModuleInstance->setDispatcher($this->getDispatcher());

        $paymentModuleInstance->pay($placedOrder);
    }

    /**
     * @param \Thelia\Core\Event\OrderEvent $event
     */
    public function sendOrderEmail(OrderEvent $event)
    {
        /* @todo */
    }

    /**
     * @param \Thelia\Core\Event\OrderEvent $event
     */
    public function setReference(OrderEvent $event)
    {
        $event->getOrder()->setRef($this->generateRef());
    }

    public function generateRef()
    {
        /* order addresses are unique */
        return uniqid('ORD', true);
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
            TheliaEvents::ORDER_BEFORE_CREATE => array("setReference", 128),
            TheliaEvents::ORDER_BEFORE_PAYMENT => array("sendOrderEmail", 128),
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

    /**
     * @param ModelCriteria         $query
     * @param ActiveRecordInterface $object
     * @param                       $id
     * @param array                 $needed = array('Title')
     *
     * @return ProductI18n
     */
    protected function getI18n(ModelCriteria $query, ActiveRecordInterface $object, $id, $needed = array('Title'))
    {
        $i18n = $query
            ->filterById($id)
            ->filterByLocale(
                $this->getSession()->getLang()->getLocale()
            )->findOne();
        /* or default translation */
        if(null === $i18n) {
            $i18n = $query
                ->filterById($id)
                ->filterByLocale(
                    Lang::getDefaultLanguage()->getLocale()
                )->findOne();
        }
        if(null === $i18n) { // @todo something else ?
            $i18n = $object;
            foreach($needed as $need) {
                $method = sprintf('get%s', $need);
                if(method_exists($i18n, $method)) {
                    $i18n->$method('DEFAULT ' . strtoupper($need));
                } else {
                    // @todo throw sg ?
                }
            }
        }

        return $i18n;
    }
}
