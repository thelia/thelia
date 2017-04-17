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

namespace Thelia\Tests\Action;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Action\Order;
use Thelia\Core\Event\Order\OrderAddressEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderManualEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer as CustomerModel;
use Thelia\Model\CustomerQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Order as OrderModel;
use Thelia\Model\OrderAddressQuery;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Module\BaseModule;

/**
 * Class CustomerTest
 * @package Thelia\Tests\Action
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class OrderTest extends BaseAction
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

    /**
     * @var SecurityContext
     */
    protected $securityContext;

    /** @var RequestStack */
    protected $requestStack;

    public function setUp()
    {
        $session = new Session(new MockArraySessionStorage());

        $request = new Request();
        $request->setSession($session);

        $this->container = new ContainerBuilder();

        $this->container->set("event_dispatcher", $this->getMockEventDispatcher());
        $this->container->set('request', $request);

        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);
        $this->container->set('request_stack', $this->requestStack);

        $this->securityContext = new SecurityContext($this->requestStack);

        $this->orderEvent = new OrderEvent(new OrderModel());

        $mailerFactory = new MailerFactory(
            $this->getMockEventDispatcher(),
            $this->getMockParserInterface()
        );

        $this->orderAction = new Order(
            $this->requestStack,
            $mailerFactory,
            $this->securityContext
        );

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

        $this->securityContext->setCustomerUser($customer);

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

        $this->requestStack->getCurrentRequest()->getSession()->set("thelia.cart_id", $cart->getId());

        return $cart;
    }

    public function testSetDeliveryAddress()
    {
        //$validAddressId = AddressQuery::create()->findOneByCustomerId($this->customer->getId());

        $this->orderEvent->setDeliveryAddress(321);

        $this->orderAction->setDeliveryAddress($this->orderEvent);

        $this->assertEquals(
            321,
            $this->orderEvent->getOrder()->getChoosenDeliveryAddress()
        );
    }

    public function testSetinvoiceAddress()
    {
        $this->orderEvent->setInvoiceAddress(654);

        $this->orderAction->setInvoiceAddress($this->orderEvent);

        $this->assertEquals(
            654,
            $this->orderEvent->getOrder()->getChoosenInvoiceAddress()
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

        /** @var \Thelia\Module\PaymentModuleInterface $paymentInstance */
        $paymentInstance = new $paymentModuleClass();
        $this->container->set(sprintf('module.%s', $paymentModule->getCode()), $paymentInstance);
        $manageStock = $paymentInstance->manageStockOnCreation();


        $this->orderEvent->getOrder()->setChoosenDeliveryAddress($validDeliveryAddress->getId());
        $this->orderEvent->getOrder()->setChoosenInvoiceAddress($validInvoiceAddress->getId());
        $this->orderEvent->getOrder()->setDeliveryModuleId($deliveryModule->getId());
        $this->orderEvent->getOrder()->setPostage(20);
        $this->orderEvent->getOrder()->setPaymentModuleId($paymentModule->getId());

        /* memorize current stocks */
        $itemsStock = array();
        foreach ($this->cartItems as $index => $cartItem) {
            $itemsStock[$index] = $cartItem->getProductSaleElements()->getQuantity();
        }

        $this->orderAction->create($this->orderEvent, null, $this->getMockEventDispatcher());

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
        $this->assertEquals($this->requestStack->getCurrentRequest()->getSession()->getLang()->getId(), $placedOrder->getLangId(), 'lang does not  match');

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

            if ($manageStock) {
                if ($orderProduct->getVirtual()) {
                    /* check same stock*/
                    $this->assertEquals(
                        $itemsStock[$index],
                        $cartItem->getProductSaleElements()->getQuantity()
                    );
                } else {
                    /* check stock decrease */
                    $this->assertEquals(
                        $itemsStock[$index] - $orderProduct->getQuantity(),
                        $cartItem->getProductSaleElements()->getQuantity()
                    );
                }
            } else {
                /* check same stock*/
                $this->assertEquals(
                    $itemsStock[$index],
                    $cartItem->getProductSaleElements()->getQuantity()
                );
            }


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
     * @param OrderModel $order
     * @return OrderModel
     */
    public function testCreateManual(OrderModel $order)
    {
        $orderCopy = $order->copy();

        $validDeliveryAddress = AddressQuery::create()->findOneByCustomerId($this->customer->getId());
        $validInvoiceAddress = AddressQuery::create()->filterById($validDeliveryAddress->getId(), Criteria::NOT_EQUAL)->findOneByCustomerId($this->customer->getId());

        $orderManuelEvent = new OrderManualEvent(
            $orderCopy,
            $this->cart->getCurrency(),
            $this->requestStack->getCurrentRequest()->getSession()->getLang(),
            $this->cart,
            $this->customer
        );

        $orderManuelEvent->getOrder()->setChoosenDeliveryAddress($validDeliveryAddress->getId());
        $orderManuelEvent->getOrder()->setChoosenInvoiceAddress($validInvoiceAddress->getId());

        $deliveryModuleId = $orderCopy->getDeliveryModuleId();
        $paymentModuleId = $orderCopy->getPaymentModuleId();

        $this->orderAction->createManual($orderManuelEvent, null, $this->getMockEventDispatcher());

        $placedOrder = $orderManuelEvent->getPlacedOrder();

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
        $this->assertEquals($deliveryModuleId, $placedOrder->getDeliveryModuleId(), 'delivery module does not  match');

        /* check payment module */
        $this->assertEquals($paymentModuleId, $placedOrder->getPaymentModuleId(), 'payment module does not  match');

        /* check status */
        $this->assertEquals(OrderStatus::CODE_NOT_PAID, $placedOrder->getOrderStatus()->getCode(), 'status does not  match');

        /* check lang */
        $this->assertEquals($this->requestStack->getCurrentRequest()->getSession()->getLang()->getId(), $placedOrder->getLangId(), 'lang does not  match');


        // without address duplication
        $copyOrder = $order->copy();

        $orderManuelEvent
            ->setOrder($copyOrder)
            ->setUseOrderDefinedAddresses(true);

        $validDeliveryAddressId = $orderCopy->getDeliveryOrderAddressId();
        $validInvoiceAddressId = $orderCopy->getInvoiceOrderAddressId();

        $this->orderAction->createManual($orderManuelEvent, null, $this->getMockEventDispatcher());

        $placedOrder = $orderManuelEvent->getPlacedOrder();

        $this->assertNotNull($placedOrder);
        $this->assertNotNull($placedOrder->getId());

        /* check delivery address */
        $deliveryOrderAddress = $placedOrder->getOrderAddressRelatedByDeliveryOrderAddressId();
        $this->assertEquals($validDeliveryAddressId, $deliveryOrderAddress->getId(), 'delivery address title does not match');

        /* check invoice address */
        $invoiceOrderAddress = $placedOrder->getOrderAddressRelatedByInvoiceOrderAddressId();
        $this->assertEquals($validInvoiceAddressId, $invoiceOrderAddress->getId(), 'invoice address title does not match');

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

        $this->orderAction->updateStatus($this->orderEvent, null, $this->getMockEventDispatcher());

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
    public function testModelUpdateStatusPaidWithHelpers(OrderModel $order)
    {
        $order->setPaid();

        $this->assertEquals(
            $order->getStatusId(),
            OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PAID)->getId()
        );

        $this->assertTrue(
            $order->isPaid()
        );
    }

    /**
     * @depends testCreate
     *
     * @param OrderModel $order
     */
    public function testModelUpdateStatusNotPaidWithHelpers(OrderModel $order)
    {
        $order->setNotPaid();

        $this->assertEquals(
            $order->getStatusId(),
            OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_NOT_PAID)->getId()
        );

        $this->assertTrue(
            $order->isNotPaid()
        );
    }

    /**
     * @depends testCreate
     *
     * @param OrderModel $order
     */
    public function testModelUpdateStatusProcessedWithHelpers(OrderModel $order)
    {
        $order->setProcessing();

        $this->assertEquals(
            $order->getStatusId(),
            OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_PROCESSING)->getId()
        );

        $this->assertTrue(
            $order->isProcessing()
        );
    }

    /**
     * @depends testCreate
     *
     * @param OrderModel $order
     */
    public function testModelUpdateStatusSentWithHelpers(OrderModel $order)
    {
        $order->setSent();

        $this->assertEquals(
            $order->getStatusId(),
            OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_SENT)->getId()
        );

        $this->assertTrue(
            $order->isSent()
        );
    }

    /**
     * @depends testCreate
     *
     * @param OrderModel $order
     */
    public function testModelUpdateStatusCanceledWithHelpers(OrderModel $order)
    {
        $order->setCancelled();

        $this->assertEquals(
            $order->getStatusId(),
            OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_CANCELED)->getId()
        );

        $this->assertTrue(
            $order->isCancelled()
        );
    }

    /**
     * @depends testCreate
     *
     * @param OrderModel $order
     */
    public function testUpdateTransactionRef(OrderModel $order)
    {
        $transactionRef = uniqid('TRANSREF');
        $this->orderEvent->setTransactionRef($transactionRef);
        $this->orderEvent->setOrder($order);

        $this->orderAction->updateTransactionRef($this->orderEvent);

        $this->assertEquals(
            $transactionRef,
            $this->orderEvent->getOrder()->getTransactionRef()
        );
        $this->assertEquals(
            $transactionRef,
            OrderQuery::create()->findPk($order->getId())->getTransactionRef()
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
        $orderAddress = OrderAddressQuery::create()->findPk($order->getDeliveryOrderAddressId());
        $title = $orderAddress->getCustomerTitleId() == 3 ? 1 : 3;
        $country = $orderAddress->getCountryId() == 64 ? 1 : 64;
        $orderAddressEvent = new OrderAddressEvent(
            $title, 'B', 'C', 'D', 'E', 'F', 'G', 'H', $country, 'J', 'K', '0102030405'
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
        $this->assertEquals('0102030405', $orderAddressEvent->getOrderAddress()->getCellphone());
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
