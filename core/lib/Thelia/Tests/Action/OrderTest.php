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

namespace Thelia\Tests\Action;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\Event\Order\OrderAddressEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\AddressQuery;
use Thelia\Model\Base\OrderAddressQuery;
use Thelia\Model\Base\OrderProductQuery;
use Thelia\Model\Base\OrderQuery;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderStatus;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order as OrderModel;
use Thelia\Model\Customer as CustomerModel;
use Thelia\Action\Order;
use Thelia\Model\ProductQuery;
use Thelia\Module\BaseModule;

/**
 * Class CustomerTest
 * @package Thelia\Tests\Action
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ContainerBuilder $container
     */
    protected $container;

    /**
     * @var Order $orderAction
     */
    protected $orderAction;

    /**
     * @var \Thelia\Core\Event\Order\OrderEvent $orderEvent
     */
    protected $orderEvent;

    /**
     * @var CustomerModel $customer
     */
    protected $customer;

    /**
     * @var Cart $customer
     */
    protected $cart;

    /**
     * @var CartItem[]
     */
    protected $cartItems;

    public function setUp()
    {
        $container = new ContainerBuilder();

        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $dispatcher = $this->getMock("Symfony\Component\EventDispatcher\EventDispatcherInterface");

        $request->setSession($session);

        $container->set("event_dispatcher", $dispatcher);
        $container->set('request', $request);
        $container->set('thelia.securityContext', new SecurityContext($request));

        $this->container = $container;

        $this->orderEvent = new OrderEvent(new OrderModel());

        $this->orderAction = new Order($this->container);

        /* load customer */
        $this->customer = $this->loadCustomer();
        if (null === $this->customer) {
            return;
        }

        /* fill cart */
        $this->cart = $this->fillCart();

    }

    public function loadCustomer()
    {
        $customer = CustomerQuery::create()->findOne();
        if (null === $customer) {
            return null;
        }

        $this->container->get('thelia.securityContext')->setCustomerUser($customer);

        return $customer;
    }

    public function fillCart()
    {
        $currency = CurrencyQuery::create()->findOne();

        //create a fake cart in database;
        $cart = new Cart();
        $cart->setToken(uniqid("createorder", true))
            ->setCustomer($this->customer)
            ->setCurrency($currency)
            ->save();

        /* add 3 items */
        $productList = array();
        for ($i=0; $i<3; $i++) {
            $pse = ProductSaleElementsQuery::create()
                ->filterByProduct(
                    ProductQuery::create()
                        ->filterByVisible(1)
                        ->filterById($productList, Criteria::NOT_IN)
                        ->find()
                )
                ->filterByQuantity(5, Criteria::GREATER_EQUAL)
                ->joinProductPrice('pp', Criteria::INNER_JOIN)
                ->addJoinCondition('pp', 'currency_id = ?', $currency->getId(), null, \PDO::PARAM_INT)
                ->withColumn('`pp`.price', 'price_PRICE')
                ->withColumn('`pp`.promo_price', 'price_PROMO_PRICE')
                ->findOne();

            $productList[] = $pse->getProductId();

            $cartItem = new CartItem();
            $cartItem
                ->setCart($cart)
                ->setProduct($pse->getProduct())
                ->setProductSaleElements($pse)
                ->setQuantity($i+1)
                ->setPrice($pse->getPrice())
                ->setPromoPrice($pse->getPromoPrice())
                ->setPromo($pse->getPromo())
                ->setPriceEndOfLife(time() + 60*60*24*30)
                ->save();
            $this->cartItems[] = $cartItem;
        }

        $this->container->get('request')->getSession()->setCart($cart->getId());

        return $cart;
    }

    public function testSetDeliveryAddress()
    {
        //$validAddressId = AddressQuery::create()->findOneByCustomerId($this->customer->getId());

        $this->orderEvent->setDeliveryAddress(321);

        $this->orderAction->setDeliveryAddress($this->orderEvent);

        $this->assertEquals(
            321,
            $this->orderEvent->getOrder()->chosenDeliveryAddress
        );
    }

    public function testSetinvoiceAddress()
    {
        $this->orderEvent->setInvoiceAddress(654);

        $this->orderAction->setInvoiceAddress($this->orderEvent);

        $this->assertEquals(
            654,
            $this->orderEvent->getOrder()->chosenInvoiceAddress
        );
    }

    public function testSetDeliveryModule()
    {
        $this->orderEvent->setDeliveryModule(123);

        $this->orderAction->setDeliveryModule($this->orderEvent);

        $this->assertEquals(
            123,
            $this->orderEvent->getOrder()->getDeliveryModuleId()
        );
    }

    public function testSetPaymentModule()
    {
        $this->orderEvent->setPaymentModule(456);

        $this->orderAction->setPaymentModule($this->orderEvent);

        $this->assertEquals(
            456,
            $this->orderEvent->getOrder()->getPaymentModuleId()
        );
    }

    public function testCreate()
    {
        $validDeliveryAddress = AddressQuery::create()->findOneByCustomerId($this->customer->getId());
        $validInvoiceAddress = AddressQuery::create()->filterById($validDeliveryAddress->getId(), Criteria::NOT_EQUAL)->findOneByCustomerId($this->customer->getId());

        $deliveryModule = ModuleQuery::create()
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE)
            ->filterByActivate(1)
            ->findOne();

        if (null === $deliveryModule) {
            throw new \Exception('No Delivery Module fixture found');
        }

        $paymentModule = ModuleQuery::create()
            ->filterByType(BaseModule::PAYMENT_MODULE_TYPE)
            ->filterByActivate(1)
            ->findOne();

        if (null === $paymentModule) {
            throw new \Exception('No Payment Module fixture found');
        }

        /* define payment module in container */
        $paymentModuleClass = $paymentModule->getFullNamespace();
        $this->container->set(sprintf('module.%s', $paymentModule->getCode()), new $paymentModuleClass());

        $this->orderEvent->getOrder()->chosenDeliveryAddress = $validDeliveryAddress->getId();
        $this->orderEvent->getOrder()->chosenInvoiceAddress = $validInvoiceAddress->getId();
        $this->orderEvent->getOrder()->setDeliveryModuleId($deliveryModule->getId());
        $this->orderEvent->getOrder()->setPostage(20);
        $this->orderEvent->getOrder()->setPaymentModuleId($paymentModule->getId());

        /* memorize current stocks */
        $itemsStock = array();
        foreach ($this->cartItems as $index => $cartItem) {
            $itemsStock[$index] = $cartItem->getProductSaleElements()->getQuantity();
        }

        $this->orderAction->create($this->orderEvent);

        $placedOrder = $this->orderEvent->getPlacedOrder();

        $this->assertNotNull($placedOrder);
        $this->assertNotNull($placedOrder->getId());

        /* check customer */
        $this->assertEquals($this->customer->getId(), $placedOrder->getCustomerId(), 'customer i does not  match');

        /* check delivery address */
        $deliveryOrderAddress = $placedOrder->getOrderAddressRelatedByDeliveryOrderAddressId();
        $this->assertEquals($validDeliveryAddress->getCustomerTitle()->getId(), $deliveryOrderAddress->getCustomerTitleId(), 'delivery address title does not match');
        $this->assertEquals($validDeliveryAddress->getCompany(), $deliveryOrderAddress->getCompany(), 'delivery address company does not match');
        $this->assertEquals($validDeliveryAddress->getFirstname(), $deliveryOrderAddress->getFirstname(), 'delivery address fistname does not match');
        $this->assertEquals($validDeliveryAddress->getLastname(), $deliveryOrderAddress->getLastname(), 'delivery address lastname does not match');
        $this->assertEquals($validDeliveryAddress->getAddress1(), $deliveryOrderAddress->getAddress1(), 'delivery address address1 does not match');
        $this->assertEquals($validDeliveryAddress->getAddress2(), $deliveryOrderAddress->getAddress2(), 'delivery address address2 does not match');
        $this->assertEquals($validDeliveryAddress->getAddress3(), $deliveryOrderAddress->getAddress3(), 'delivery address address3 does not match');
        $this->assertEquals($validDeliveryAddress->getZipcode(), $deliveryOrderAddress->getZipcode(), 'delivery address zipcode does not match');
        $this->assertEquals($validDeliveryAddress->getCity(), $deliveryOrderAddress->getCity(), 'delivery address city does not match');
        $this->assertEquals($validDeliveryAddress->getPhone(), $deliveryOrderAddress->getPhone(), 'delivery address phone does not match');
        $this->assertEquals($validDeliveryAddress->getCountryId(), $deliveryOrderAddress->getCountryId(), 'delivery address country does not match');

        /* check invoice address */
        $invoiceOrderAddress = $placedOrder->getOrderAddressRelatedByInvoiceOrderAddressId();
        $this->assertEquals($validInvoiceAddress->getCustomerTitle()->getId(), $invoiceOrderAddress->getCustomerTitleId(), 'invoice address title does not match');
        $this->assertEquals($validInvoiceAddress->getCompany(), $invoiceOrderAddress->getCompany(), 'invoice address company does not match');
        $this->assertEquals($validInvoiceAddress->getFirstname(), $invoiceOrderAddress->getFirstname(), 'invoice address fistname does not match');
        $this->assertEquals($validInvoiceAddress->getLastname(), $invoiceOrderAddress->getLastname(), 'invoice address lastname does not match');
        $this->assertEquals($validInvoiceAddress->getAddress1(), $invoiceOrderAddress->getAddress1(), 'invoice address address1 does not match');
        $this->assertEquals($validInvoiceAddress->getAddress2(), $invoiceOrderAddress->getAddress2(), 'invoice address address2 does not match');
        $this->assertEquals($validInvoiceAddress->getAddress3(), $invoiceOrderAddress->getAddress3(), 'invoice address address3 does not match');
        $this->assertEquals($validInvoiceAddress->getZipcode(), $invoiceOrderAddress->getZipcode(), 'invoice address zipcode does not match');
        $this->assertEquals($validInvoiceAddress->getCity(), $invoiceOrderAddress->getCity(), 'invoice address city does not match');
        $this->assertEquals($validInvoiceAddress->getPhone(), $invoiceOrderAddress->getPhone(), 'invoice address phone does not match');
        $this->assertEquals($validInvoiceAddress->getCountryId(), $invoiceOrderAddress->getCountryId(), 'invoice address country does not match');

        /* check currency */
        $this->assertEquals($this->cart->getCurrencyId(), $placedOrder->getCurrencyId(), 'currency id does not  match');
        $this->assertEquals($this->cart->getCurrency()->getRate(), $placedOrder->getCurrencyRate(), 'currency rate does not  match');

        /* check delivery module */
        $this->assertEquals(20, $placedOrder->getPostage(), 'postage does not  match');
        $this->assertEquals($deliveryModule->getId(), $placedOrder->getDeliveryModuleId(), 'delivery module does not  match');

        /* check payment module */
        $this->assertEquals($paymentModule->getId(), $placedOrder->getPaymentModuleId(), 'payment module does not  match');

        /* check status */
        $this->assertEquals(OrderStatus::CODE_NOT_PAID, $placedOrder->getOrderStatus()->getCode(), 'status does not  match');

        /* check lang */
        $this->assertEquals($this->container->get('request')->getSession()->getLang()->getId(), $placedOrder->getLangId(), 'lang does not  match');

        /* check ordered product */
        foreach ($this->cartItems as $index => $cartItem) {
            $orderProduct = OrderProductQuery::create()
                ->filterByOrderId($placedOrder->getId())
                ->filterByProductRef($cartItem->getProduct()->getRef())
                ->filterByProductSaleElementsRef($cartItem->getProductSaleElements()->getRef())
                ->filterByQuantity($cartItem->getQuantity())
                ->filterByPrice($cartItem->getPrice(), Criteria::LIKE)
                ->filterByPromoPrice($cartItem->getPromoPrice(), Criteria::LIKE)
                ->filterByWasNew($cartItem->getProductSaleElements()->getNewness())
                ->filterByWasInPromo($cartItem->getPromo())
                ->filterByWeight($cartItem->getProductSaleElements()->getWeight())
                ->findOne();

            $this->assertNotNull($orderProduct);

            /* check attribute combinations */
            $this->assertEquals(
                $cartItem->getProductSaleElements()->getAttributeCombinations()->count(),
                $orderProduct->getOrderProductAttributeCombinations()->count()
            );

            /* check stock decrease */
            $this->assertEquals(
                $itemsStock[$index] - $orderProduct->getQuantity(),
                $cartItem->getProductSaleElements()->getQuantity()
            );

            /* check tax */
            $orderProductTaxList = $orderProduct->getOrderProductTaxes();
            foreach ($cartItem->getProduct()->getTaxRule()->getTaxDetail($cartItem->getProduct(), $validDeliveryAddress->getCountry(), $cartItem->getPrice(), $cartItem->getPromoPrice()) as $index => $tax) {
                $orderProductTax = $orderProductTaxList[$index];
                $this->assertEquals($tax->getAmount(), $orderProductTax->getAmount());
                $this->assertEquals($tax->getPromoAmount(), $orderProductTax->getPromoAmount());
            }
        }

        return $placedOrder;
    }

    /**
     * @depends testCreate
     *
     * @param OrderModel $order
     */
    public function testUpdateStatus(OrderModel $order)
    {
        $newStatus = $order->getStatusId() == 5 ? 1 : 5;
        $this->orderEvent->setStatus($newStatus);
        $this->orderEvent->setOrder($order);

        $this->orderAction->updateStatus($this->orderEvent);

        $this->assertEquals(
            $newStatus,
            $this->orderEvent->getOrder()->getStatusId()
        );
        $this->assertEquals(
            $newStatus,
            OrderQuery::create()->findPk($order->getId())->getStatusId()
        );
    }

    /**
     * @depends testCreate
     *
     * @param OrderModel $order
     */
    public function testUpdateDeliveryRef(OrderModel $order)
    {
        $deliveryRef = uniqid('DELREF');
        $this->orderEvent->setDeliveryRef($deliveryRef);
        $this->orderEvent->setOrder($order);

        $this->orderAction->updateDeliveryRef($this->orderEvent);

        $this->assertEquals(
            $deliveryRef,
            $this->orderEvent->getOrder()->getDeliveryRef()
        );
        $this->assertEquals(
            $deliveryRef,
            OrderQuery::create()->findPk($order->getId())->getDeliveryRef()
        );
    }

    /**
     * @depends testCreate
     *
     * @param OrderModel $order
     */
    public function testUpdateAddress(OrderModel $order)
    {
        $deliveryRef = uniqid('DELREF');
        $orderAddress = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId());
        $title = $orderAddress->getCustomerTitleId() == 3 ? 1 : 3;
        $country = $orderAddress->getCountryId() == 64 ? 1 : 64;
        $orderAddressEvent = new OrderAddressEvent(
            $title, 'B', 'C', 'D', 'E', 'F', 'G', 'H', $country, 'J', 'K'
        );
        $orderAddressEvent->setOrderAddress($orderAddress);
        $orderAddressEvent->setOrder($order);

        $this->orderAction->updateAddress($orderAddressEvent);

        $newOrderAddress = OrderAddressQuery::create()->findPk($orderAddress->getId());

        $this->assertEquals($title, $orderAddressEvent->getOrderAddress()->getCustomerTitleId());
        $this->assertEquals('B', $orderAddressEvent->getOrderAddress()->getFirstname());
        $this->assertEquals('C', $orderAddressEvent->getOrderAddress()->getLastname());
        $this->assertEquals('D', $orderAddressEvent->getOrderAddress()->getAddress1());
        $this->assertEquals('E', $orderAddressEvent->getOrderAddress()->getAddress2());
        $this->assertEquals('F', $orderAddressEvent->getOrderAddress()->getAddress3());
        $this->assertEquals('G', $orderAddressEvent->getOrderAddress()->getZipcode());
        $this->assertEquals('H', $orderAddressEvent->getOrderAddress()->getCity());
        $this->assertEquals($country, $orderAddressEvent->getOrderAddress()->getCountryId());
        $this->assertEquals('J', $orderAddressEvent->getOrderAddress()->getPhone());
        $this->assertEquals('K', $orderAddressEvent->getOrderAddress()->getCompany());

        $this->assertEquals($title, $newOrderAddress->getCustomerTitleId());
        $this->assertEquals('B', $newOrderAddress->getFirstname());
        $this->assertEquals('C', $newOrderAddress->getLastname());
        $this->assertEquals('D', $newOrderAddress->getAddress1());
        $this->assertEquals('E', $newOrderAddress->getAddress2());
        $this->assertEquals('F', $newOrderAddress->getAddress3());
        $this->assertEquals('G', $newOrderAddress->getZipcode());
        $this->assertEquals('H', $newOrderAddress->getCity());
        $this->assertEquals($country, $newOrderAddress->getCountryId());
        $this->assertEquals('J', $newOrderAddress->getPhone());
        $this->assertEquals('K', $newOrderAddress->getCompany());
    }
}
