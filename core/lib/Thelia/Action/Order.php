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

use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
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
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\Base\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\Customer;
use Thelia\Model\Lang as LangModel;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Order as ModelOrder;
use Thelia\Model\Order as OrderModel;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\OrderVersionQuery;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Tools\URL;

/**
 * Class Order.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 * @author Franck Allimant <thelia@cqfdev.fr>
 */
class Order extends BaseAction implements EventSubscriberInterface
{
    /**
     * Where the shipped front office serves a guest order tracking link. Kept as a path
     * rather than a route name so that the core never depends on a theme's routing.
     */
    private const GUEST_ORDER_TRACKING_PATH = '/order/track/';

    public function __construct(
        protected RequestStack $requestStack,
        protected MailerFactory $mailer,
        protected SecurityContext $securityContext,
        protected OrderFacade $orderFacade,
        protected GuestOrderAccessService $guestOrderAccessService,
        protected URL $urlManager,
    ) {
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
            $order->setPostage('0');
            $order->setPostageTax('0');
            $order->setPostageTaxRuleTitle(null);
        }

        $event->setOrder($order);
    }

    public function setPostage(OrderEvent $event): void
    {
        $order = $event->getOrder();

        $order->setPostage($event->getPostage() !== null ? (string) $event->getPostage() : null);
        $order->setPostageTax((string) ($event->getPostageTax() ?? 0));
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

        $order->setPaymentModuleId((int) $event->getPaymentModule());

        $event->setOrder($order);
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
            $this->orderFacade->createOrder(
                $dispatcher,
                $event->getOrder(),
                $event->getCurrency(),
                $event->getLang(),
                $event->getCart(),
                $event->getCustomer(),
                $event->getUseOrderDefinedAddresses(),
            ),
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
        if (!$order instanceof OrderModel) {
            throw new TheliaProcessException('Order is not defined');
        }

        $placedOrder = $this->orderFacade->createOrder(
            $dispatcher,
            $event->getOrder(),
            $session?->getCurrency() ?? CurrencyModel::getDefaultCurrency(),
            $session?->getLang() ?? LangModel::getDefaultLanguage(),
            $session?->getSessionCart($dispatcher) ?? CartQuery::create()->findPk($order->getCartId()),
            // The session customer when there is one — a signed-in customer or a guest
            // checking out — and the one the order already names otherwise, which is how
            // an order placed from the command line finds its customer.
            $this->securityContext->getCustomerUser() ?? $order->getCustomer(),
        );

        $placedOrderEvent = new OrderEvent($placedOrder);
        $dispatcher->dispatch($placedOrderEvent, TheliaEvents::ORDER_BEFORE_PAYMENT);
        $dispatcher->dispatch($placedOrderEvent, TheliaEvents::ORDER_CART_CLEAR);

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

        $session?->clearSessionCart($dispatcher);

        $session?->setOrder(new OrderModel());

        // A guest was put in the session only to carry this order through the checkout,
        // and the order is placed: leaving them there would hand the next person on this
        // browser an identity nobody signed into. Read off the row rather than off a
        // session flag — a row is a guest until its activation code is answered, and
        // that is the only thing entitled to say so. The account it points at holds no
        // password and no remember-me token, so there is nothing else to retire — hence
        // no CUSTOMER_LOGOUT, whose job is precisely to retire those.
        $sessionCustomer = $this->securityContext->getCustomerUser();

        if ($sessionCustomer instanceof Customer && $sessionCustomer->isGuest()) {
            $session?->clearCustomerUser();
        }
    }

    /**
     * @throws \Exception if the message cannot be loaded
     */
    public function sendConfirmationEmail(OrderEvent $event): void
    {
        $order = $event->getOrder();
        $customer = $order?->getCustomer();

        $this->mailer->sendEmailToCustomer(
            'order_confirmation',
            $customer,
            [
                'order_id' => $order?->getId(),
                'order_ref' => $order?->getRef(),
                ...$this->guestTrackingVariables($order),
            ],
        );
    }

    /**
     * What a buyer with no account needs to find the order again.
     *
     * They have no account page to look it up on, so this mail is the only thing that
     * ever carries the link. Both the token and a ready-made URL are passed: the token
     * so that a theme can build the link on its own route, the URL so that the mail can
     * show one without the core having to know which theme is installed. The path is
     * the one the shipped front office serves, and a theme that moves it overrides the
     * message template with its own link built from the token.
     *
     * @return array<string, string>
     */
    private function guestTrackingVariables(?ModelOrder $order): array
    {
        $customer = $order?->getCustomer();

        if (!$order instanceof ModelOrder || null === $customer || !$customer->isGuest()) {
            return [];
        }

        $token = $this->guestOrderAccessService->createToken($order);

        return [
            'guest_order_tracking_token' => $token,
            'guest_order_tracking_url' => $this->urlManager->absoluteUrl(self::GUEST_ORDER_TRACKING_PATH.$token),
        ];
    }

    /**
     * @throws \Exception if the message cannot be loaded
     */
    public function sendNotificationEmail(OrderEvent $event): void
    {
        $order = $event->getOrder();

        $this->mailer->sendEmailToShopManagers(
            'order_notification',
            [
                'order_id' => $order->getId(),
                'order_ref' => $order->getRef(),
            ],
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
        } catch (\Throwable $exception) {
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

        if ($newStatus->getId() === $order->getStatusId()) {
            return;
        }
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
            || ($order->isNotPaid(true) && $newStatus->isNotPaid(false) && true === $manageStockOnCreation) // Case 3
        ) {
            $event->setOperation($event::INCREASE_STOCK);
        }

        if ($order->isNotPaid(false) // Case 2
            && $newStatus->isPaid(false)
            && false === $manageStockOnCreation) {
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
            .' = operation: '.$event->getOperation(),
        );
    }

    /**
     * Update order products stock after an order status change.
     *
     * @param int $newStatus the new status ID
     *
     * @throws \Exception
     * @throws PropelException
     */
    protected function updateQuantity(ModelOrder $order, int $newStatus, EventDispatcherInterface $dispatcher): void
    {
        if ($newStatus === $order->getStatusId() || null === $newStatusModel = OrderStatusQuery::create()->findPk($newStatus)) {
            return;
        }
        $operationEvent = new GetStockUpdateOperationOnOrderStatusChangeEvent($order, $newStatusModel);
        $dispatcher->dispatch(
            $operationEvent,
            TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE,
        );

        if ($operationEvent->getOperation() === $operationEvent::DO_NOTHING) {
            return;
        }
        $orderProductList = $order->getOrderProducts();

        /** @var OrderProduct $orderProduct */
        foreach ($orderProductList as $orderProduct) {
            $productSaleElementsId = $orderProduct->getProductSaleElementsId();

            /** @var ProductSaleElements $productSaleElements */
            if (null !== $productSaleElements = ProductSaleElementsQuery::create()->findPk($productSaleElementsId)) {
                $offset = 0;

                if ($operationEvent->getOperation() === $operationEvent::INCREASE_STOCK) {
                    $offset = $orderProduct->getQuantity();
                } elseif ($operationEvent->getOperation() === $operationEvent::DECREASE_STOCK) {
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
            ->setCustomerTitleId($event->getTitle() === null ? null : (int) $event->getTitle())
            ->setCompany($event->getCompany())
            ->setSiret($event->getSiret())
            ->setVatNumber($event->getVatNumber())
            ->setFirstname($event->getFirstname())
            ->setLastname($event->getLastname())
            ->setAddress1($event->getAddress1())
            ->setAddress2($event->getAddress2())
            ->setAddress3($event->getAddress3())
            ->setZipcode($event->getZipcode())
            ->setCity($event->getCity())
            ->setCountryId($event->getCountry() === null ? null : (int) $event->getCountry())
            ->setStateId($event->getState() === null ? null : (int) $event->getState())
            ->setPhone($event->getPhone())
            ->setCellphone($event->getCellphone())
            ->save();

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
            $event->isIncludeDiscount(),
        );

        $event->setTotal($total);
        $event->setTax($tax);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::ORDER_SET_DELIVERY_ADDRESS => ['setDeliveryAddress', 128],
            TheliaEvents::ORDER_SET_INVOICE_ADDRESS => ['setInvoiceAddress', 128],
            TheliaEvents::ORDER_UPDATE_ADDRESS => ['updateAddress', 128],
            TheliaEvents::ORDER_SET_DELIVERY_MODULE => ['setDeliveryModule', 128],
            TheliaEvents::ORDER_SET_POSTAGE => ['setPostage', 128],
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

            TheliaEvents::ORDER_CREATE_MANUAL => ['createManual', 128],
            TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE => ['getStockUpdateOnOrderStatusChange', 128],
        ];
    }

    /**
     * Returns the session from the current request.
     *
     * @return Session
     */
    protected function getSession(): ?SessionInterface
    {
        /** @var Request $request */
        $request = $this->requestStack->getMainRequest();

        return $request?->getSession();
    }
}
