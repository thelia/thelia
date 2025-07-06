<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Action;

use Propel\Runtime\Propel;
use Propel\Runtime\Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Thelia\Core\Event\Order\GetStockUpdateOperationOnOrderStatusChangeEvent;
use Thelia\Core\Event\Order\OrderAddressEvent;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderManualEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\Order\OrderPayTotalEvent;
use Thelia\Core\Event\Product\VirtualProductOrderHandleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\AddressQuery;
use Thelia\Model\Attribute;
use Thelia\Model\AttributeAv;
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
use Thelia\Model\OrderVersionQuery;
use Thelia\Model\ProductI18n;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleI18n;
use Thelia\Tools\I18n;

/**
 * Class Order.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 * @author Franck Allimant <thelia@cqfdev.fr>
 */
class Order extends BaseAction implements EventSubscriberInterface
{
    public function __construct(protected RequestStack $requestStack, protected MailerFactory $mailer, protected SecurityContext $securityContext)
    {
    }

    public function setDeliveryAddress(OrderEvent $event): void
    {
        $order = $event->getOrder();

        $order->setChoosenDeliveryAddress($event->getDeliveryAddress());

        $event->setOrder($order);
    }

    public function setDeliveryModule(OrderEvent $event): void
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

    public function setPostage(OrderEvent $event): void
    {
        $order = $event->getOrder();

        $order->setPostage($event->getPostage());
        $order->setPostageTax($event->getPostageTax());
        $order->setPostageTaxRuleTitle($event->getPostageTaxRuleTitle());

        $event->setOrder($order);
    }

    public function setInvoiceAddress(OrderEvent $event): void
    {
        $order = $event->getOrder();

        $order->setChoosenInvoiceAddress($event->getInvoiceAddress());

        $event->setOrder($order);
    }

    public function setPaymentModule(OrderEvent $event): void
    {
        $order = $event->getOrder();

        $order->setPaymentModuleId($event->getPaymentModule());

        $event->setOrder($order);
    }

    /**
     * @param bool $unusedArgument           deprecated argument. Will be removed in 2.5
     * @param bool $useOrderDefinedAddresses if true, the delivery and invoice OrderAddresses will be used instead of creating new OrderAdresses using Order::getChoosenXXXAddress()
     *
     * @throws \Exception
     * @throws PropelException
     *
     * @return ModelOrder
     */
    protected function createOrder(
        EventDispatcherInterface $dispatcher,
        ModelOrder $sessionOrder,
        CurrencyModel $currency,
        LangModel $lang,
        CartModel $cart,
        UserInterface $customer,
        $unusedArgument = null,
        $useOrderDefinedAddresses = false,
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
        $placedOrder->resetModified(OrderTableMap::COL_CREATED_AT);
        $placedOrder->resetModified(OrderTableMap::COL_UPDATED_AT);
        $placedOrder->resetModified(OrderTableMap::COL_VERSION_CREATED_AT);

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

        $manageStock = $placedOrder->isStockManagedOnOrderCreation($dispatcher);

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
                $dispatcher->dispatch($virtualDocumentEvent, TheliaEvents::VIRTUAL_PRODUCT_ORDER_HANDLE);
                $useStock = $virtualDocumentEvent->isUseStock();
                $virtual = $virtualDocumentEvent->isVirtual() ? 1 : 0;
            }

            /* check still in stock */
            if ($cartItem->getQuantity() > $pse->getQuantity()
                && true === ConfigQuery::checkAvailableStock()
                && $useStock) {
                throw new TheliaProcessException('Not enough stock', TheliaProcessException::CART_ITEM_NOT_ENOUGH_STOCK, $cartItem);
            }

            if ($useStock && $manageStock) {
                /* decrease stock for non virtual product */
                $allowNegativeStock = (int) ConfigQuery::read('allow_negative_stock', 0);
                $newStock = $pse->getQuantity() - $cartItem->getQuantity();
                // Forbid negative stock
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
                /** @var Attribute $attribute */
                $attribute = I18n::forceI18nRetrieving($lang->getLocale(), 'Attribute', $attributeCombination->getAttributeId());

                /** @var AttributeAv $attributeAv */
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
     *
     * @throws \Exception
     * @throws PropelException
     */
    public function createManual(OrderManualEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $event->setPlacedOrder(
            $this->createOrder(
                $dispatcher,
                $event->getOrder(),
                $event->getCurrency(),
                $event->getLang(),
                $event->getCart(),
                $event->getCustomer(),
                null,
                $event->getUseOrderDefinedAddresses()
            )
        );

        $event->setOrder(new OrderModel());
    }

    /**
     * @throws TheliaProcessException
     * @throws \Exception
     * @throws PropelException
     */
    public function create(OrderEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $session = $this->getSession();

        $order = $event->getOrder();
        ModuleQuery::create()->findPk($order->getPaymentModuleId());

        $placedOrder = $this->createOrder(
            $dispatcher,
            $event->getOrder(),
            $session->getCurrency(),
            $session->getLang(),
            $session->getSessionCart($dispatcher),
            $this->securityContext->getCustomerUser()
        );

        $dispatcher->dispatch(new OrderEvent($placedOrder), TheliaEvents::ORDER_BEFORE_PAYMENT);

        /* but memorize placed order */
        $event->setOrder(new OrderModel());
        $event->setPlacedOrder($placedOrder);

        /* call pay method */
        $payEvent = new OrderPaymentEvent($placedOrder);

        $dispatcher->dispatch($payEvent, TheliaEvents::MODULE_PAY);

        if ($payEvent->hasResponse()) {
            $event->setResponse($payEvent->getResponse());
        }
    }

    public function orderBeforePayment(OrderEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $dispatcher->dispatch(clone $event, TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL);

        $dispatcher->dispatch(clone $event, TheliaEvents::ORDER_SEND_NOTIFICATION_EMAIL);
    }

    /**
     * Clear the cart and the order in the customer session once the order is placed,
     * and the payment performed.
     */
    public function orderCartClear(/* @noinspection PhpUnusedParameterInspection */ OrderEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        // Empty cart and clear current order
        $session = $this->getSession();

        $session->clearSessionCart($dispatcher);

        $session->setOrder(new OrderModel());
    }

    /**
     * @throws \Exception if the message cannot be loaded
     */
    public function sendConfirmationEmail(OrderEvent $event): void
    {
        $this->mailer->sendEmailToCustomer(
            'order_confirmation',
            $event->getOrder()->getCustomer(),
            [
                'order_id' => $event->getOrder()->getId(),
                'order_ref' => $event->getOrder()->getRef(),
            ]
        );
    }

    /**
     * @throws \Exception if the message cannot be loaded
     */
    public function sendNotificationEmail(OrderEvent $event): void
    {
        $this->mailer->sendEmailToShopManagers(
            'order_notification',
            [
                'order_id' => $event->getOrder()->getId(),
                'order_ref' => $event->getOrder()->getRef(),
            ]
        );
    }

    /**
     * @throws \Exception
     * @throws PropelException
     */
    public function updateStatus(OrderEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $order = $event->getOrder();
        $newStatus = $event->getStatus();

        $con = Propel::getConnection(OrderTableMap::DATABASE_NAME);

        // Prevent partial stock update on status change.
        $con->beginTransaction();

        try {
            $this->updateQuantity($order, $newStatus, $dispatcher);

            $order->setStatusId($newStatus)->save();

            $event->setOrder($order);

            $con->commit();
        } catch (\Exception $exception) {
            $con->rollBack();

            throw $exception;
        }
    }

    /**
     * Check if a stock update is required on order products for a given order status change, and compute if
     * the stock should be decreased or increased.
     *
     * @throws PropelException
     */
    public function getStockUpdateOnOrderStatusChange(GetStockUpdateOperationOnOrderStatusChangeEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        // The order
        $order = $event->getOrder();

        // The new order status
        $newStatus = $event->getNewOrderStatus();

        if ($newStatus->getId() !== $order->getStatusId()) {
            // We have to change the stock in the following cases :
            // 1) The order is currently paid, and will become unpaid (get products back in stock unconditionnaly)
            // 2) The order is currently unpaid, and will become paid (remove products from stock, except if was done at order creation $manageStockOnCreation == false)
            // 3) The order is currently NOT PAID, and will become canceled or the like (get products back in stock if it was done at order creation $manageStockOnCreation == true)

            // We consider the ManageStockOnCreation flag only if the order status as not yet changed.
            // Count distinct order statuses (e.g. NOT_PAID to something else) in the order version table.
            if (OrderVersionQuery::create()->groupByStatusId()->filterById($order->getId())->count() > 1) {
                // A status change occured. Ignore $manageStockOnCreation
                $manageStockOnCreation = false;
            } else {
                // A status has not yet occured. Consider the ManageStockOnCreation flag
                $manageStockOnCreation = $order->isStockManagedOnOrderCreation($dispatcher);
            }

            if (($order->isPaid(false) && $newStatus->isNotPaid(false)) // Case 1
                || ($order->isNotPaid(true) && $newStatus->isNotPaid(false) && $manageStockOnCreation === true) // Case 3
            ) {
                $event->setOperation($event::INCREASE_STOCK);
            }

            if ($order->isNotPaid(false) // Case 2
                && $newStatus->isPaid(false)
                && $manageStockOnCreation === false) {
                $event->setOperation($event::DECREASE_STOCK);
            }

            Tlog::getInstance()->addInfo(
                'Checking stock operation for status change of order : '.$order->getRef()
                .', version: '.$order->getVersion()
                .', manageStockOnCreation: '.($manageStockOnCreation ? 0 : 1)
                .', paid:'.($order->isPaid(false) ? 1 : 0)
                .', is not paid:'.($order->isNotPaid(false) ? 1 : 0)
                .', new status paid:'.($newStatus->isPaid(false) ? 1 : 0)
                .', new status is not paid:'.($newStatus->isNotPaid(false) ? 1 : 0)
                .' = operation: '.$event->getOperation()
            );
        }
    }

    /**
     * Update order products stock after an order status change.
     *
     * @param int $newStatus the new status ID
     *
     * @throws \Exception
     * @throws PropelException
     */
    protected function updateQuantity(ModelOrder $order, $newStatus, EventDispatcherInterface $dispatcher): void
    {
        if ($newStatus !== $order->getStatusId() && null !== $newStatusModel = OrderStatusQuery::create()->findPk($newStatus)) {
            $operationEvent = new GetStockUpdateOperationOnOrderStatusChangeEvent($order, $newStatusModel);
            $dispatcher->dispatch(
                $operationEvent,
                TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE
            );
            if ($operationEvent->getOperation() !== $operationEvent::DO_NOTHING) {
                $orderProductList = $order->getOrderProducts();

                /** @var OrderProduct $orderProduct */
                foreach ($orderProductList as $orderProduct) {
                    $productSaleElementsId = $orderProduct->getProductSaleElementsId();

                    /** @var ProductSaleElements $productSaleElements */
                    if (null !== $productSaleElements = ProductSaleElementsQuery::create()->findPk($productSaleElementsId)) {
                        $offset = 0;

                        if ($operationEvent->getOperation() == $operationEvent::INCREASE_STOCK) {
                            $offset = $orderProduct->getQuantity();
                        } elseif ($operationEvent->getOperation() == $operationEvent::DECREASE_STOCK) {
                            /* Check if we have enough stock */
                            if ($orderProduct->getQuantity() > $productSaleElements->getQuantity() && true === ConfigQuery::checkAvailableStock()) {
                                throw new TheliaProcessException($productSaleElements->getRef().' : Not enough stock 2');
                            }

                            $offset = -$orderProduct->getQuantity();
                        }

                        Tlog::getInstance()->addError('Product stock: '.$productSaleElements->getQuantity().' -> '.($productSaleElements->getQuantity() + $offset));

                        $productSaleElements
                            ->setQuantity($productSaleElements->getQuantity() + $offset)
                            ->save();
                    }
                }
            }
        }
    }

    /**
     * @throws PropelException
     */
    public function updateDeliveryRef(OrderEvent $event): void
    {
        $order = $event->getOrder();

        $order->setDeliveryRef($event->getDeliveryRef())->save();

        $event->setOrder($order);
    }

    /**
     * @throws PropelException
     */
    public function updateTransactionRef(OrderEvent $event): void
    {
        $order = $event->getOrder();

        $order->setTransactionRef($event->getTransactionRef())->save();

        $event->setOrder($order);
    }

    /**
     * @throws PropelException
     */
    public function updateAddress(OrderAddressEvent $event): void
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
            ->save()
        ;

        $event->setOrderAddress($orderAddress);
    }

    /**
     * @throws PropelException
     */
    public function getOrderPayTotal(OrderPayTotalEvent $event): void
    {
        $order = $event->getOrder();
        $tax = $event->getTax();

        $total = $order->getTotalAmount(
            $tax,
            $event->isIncludePostage(),
            $event->isIncludeDiscount()
        );

        $event->setTotal($total);
        $event->setTax($tax);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::ORDER_SET_DELIVERY_ADDRESS => ['setDeliveryAddress', 128],
            TheliaEvents::ORDER_SET_DELIVERY_MODULE => ['setDeliveryModule', 128],
            TheliaEvents::ORDER_SET_POSTAGE => ['setPostage', 128],
            TheliaEvents::ORDER_SET_INVOICE_ADDRESS => ['setInvoiceAddress', 128],
            TheliaEvents::ORDER_SET_PAYMENT_MODULE => ['setPaymentModule', 128],
            TheliaEvents::ORDER_PAY => ['create', 128],
            TheliaEvents::ORDER_PAY_GET_TOTAL => ['getOrderPayTotal', 128],
            TheliaEvents::ORDER_CART_CLEAR => ['orderCartClear', 128],
            TheliaEvents::ORDER_BEFORE_PAYMENT => ['orderBeforePayment', 128],
            TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL => ['sendConfirmationEmail', 128],
            TheliaEvents::ORDER_SEND_NOTIFICATION_EMAIL => ['sendNotificationEmail', 128],
            TheliaEvents::ORDER_UPDATE_STATUS => ['updateStatus', 128],
            TheliaEvents::ORDER_UPDATE_DELIVERY_REF => ['updateDeliveryRef', 128],
            TheliaEvents::ORDER_UPDATE_TRANSACTION_REF => ['updateTransactionRef', 128],
            TheliaEvents::ORDER_UPDATE_ADDRESS => ['updateAddress', 128],
            TheliaEvents::ORDER_CREATE_MANUAL => ['createManual', 128],
            TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE => ['getStockUpdateOnOrderStatusChange', 128],
        ];
    }

    /**
     * Returns the session from the current request.
     *
     * @return Session
     */
    protected function getSession(): SessionInterface
    {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();

        return $request->getSession();
    }
}
