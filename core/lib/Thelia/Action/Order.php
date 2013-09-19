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

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\AddressQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\ConfigQuery;

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

        $customer = $this->getSecurityContext()->getCustomerUser();
        $currency = $this->getSession()->getCurrency();
        $lang = $this->getSession()->getLang();
        $deliveryAddress = AddressQuery::create()->findPk($sessionOrder->chosenDeliveryAddress);
        $invoiceAddress = AddressQuery::create()->findPk($sessionOrder->chosenInvoiceAddress);

        $paymentModule = ModuleQuery::findPk($placedOrder->getPaymentModuleId());

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

        /* fulfill order_products and decrease stock // @todo dispatch event */

        /* discount @todo */

        $con->commit();

        /* T1style : dispatch mail event ? */

        /* clear session ? */

        /* call pay method */
        $paymentModuleReflection = new \ReflectionClass($paymentModule->getFullNamespace());
        $paymentModuleInstance = $paymentModuleReflection->newInstance();

        $paymentModuleInstance->setRequest($this->request);
        $paymentModuleInstance->setDispatcher($this->dispatcher);

        $paymentModuleInstance->pay();
    }

    /**
     * @param \Thelia\Core\Event\OrderEvent $event
     */
    public function setReference(OrderEvent $event)
    {
        $x = true;

        $this->setRef($this->generateRef());
    }

    public function generateRef()
    {
        return sprintf('O', uniqid('', true), $this->getId());
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
            TheliaEvents::ORDER_SET_REFERENCE => array("setReference", 128),
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
