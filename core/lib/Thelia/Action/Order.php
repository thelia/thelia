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
use Thelia\Core\Event\ProductSaleElement\StockLevelChange;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Catalog\Stock\OutOfStockPolicy;
use Thelia\Domain\Catalog\Stock\PreorderRule;
use Thelia\Domain\Catalog\Stock\SellableStock;
use Thelia\Domain\Catalog\Stock\StockLevelAnnouncer;
use Thelia\Domain\Catalog\Stock\StockReservationService;
use Thelia\Domain\Customer\Service\CustomerGroupPaymentPolicy;
use Thelia\Domain\Module\Payment\PaymentCartContext;
use Thelia\Domain\Order\Exception\OrderStatusChangeConflictException;
use Thelia\Domain\Order\OrderFacade;
use Thelia\Domain\Order\Service\GuestOrderAccessService;
use Thelia\Domain\Order\Service\OrderStatusTransitionGuard;
use Thelia\Domain\Order\Service\PackPreorder;
use Thelia\Domain\Order\Service\PaymentStockLock;
use Thelia\Domain\Order\Service\StockDecrementer;
use Thelia\Domain\Order\Service\StockMovement;
use Thelia\Domain\Shared\Database\LockConflictDetector;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\Base\CartQuery;
use Thelia\Model\Cart as CartModel;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency as CurrencyModel;
use Thelia\Model\Customer;
use Thelia\Model\Lang as LangModel;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Model\Map\OrderVersionTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Order as ModelOrder;
use Thelia\Model\Order as OrderModel;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\OrderVersionQuery;
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
        protected OrderStatusTransitionGuard $transitionGuard,
        protected PaymentCartContext $paymentCartContext,
        protected CustomerGroupPaymentPolicy $customerGroupPaymentPolicy,
        protected ?StockDecrementer $stockDecrementer = null,
        protected ?StockReservationService $stockReservation = null,
        protected ?StockLevelAnnouncer $stockLevelAnnouncer = null,
        protected ?PaymentStockLock $paymentStockLock = null,
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
                // Placed from the back office: there is no buyer ticking boxes, so there
                // is nothing to freeze as a consent answer.
                recordConsentAnswers: false,
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

        $cart = $this->resolveCart($order, $session, $dispatcher);

        // The lowest point every placement goes through, the front API and the theme
        // included: the checkout guards refuse a module closed to the customer's group
        // earlier, this holds for a caller that raises ORDER_PAY without them.
        if (null !== $order->getPaymentModuleId()) {
            $this->customerGroupPaymentPolicy->refuseUnlessAllowedForCart((int) $order->getPaymentModuleId(), $cart);
        }

        $placedOrder = $this->orderFacade->createOrder(
            $dispatcher,
            $order,
            // Whatever the order already states comes first, then the session, then the
            // shop default. The tunnel of a theme states neither, so it still reads the
            // session exactly as before; a caller with no session states both, which is
            // the only way an order placed outside a browser gets a currency and a
            // language that are not the shop's own.
            $order->getCurrency() ?? $session?->getCurrency() ?? CurrencyModel::getDefaultCurrency(),
            $order->getLang() ?? $session?->getLang() ?? LangModel::getDefaultLanguage(),
            $cart,
            // The session customer when there is one — a signed-in customer or a guest
            // checking out — and the one the order already names otherwise, which is how
            // an order placed from the command line finds its customer.
            $this->securityContext->getCustomerUser() ?? $order->getCustomer(),
        );

        $placedOrderEvent = new OrderEvent($placedOrder);
        $dispatcher->dispatch($placedOrderEvent, TheliaEvents::ORDER_BEFORE_PAYMENT);

        // The cart is deliberately not cleared here. The payment module has not answered
        // yet, and a declined card or a closed tab used to leave the buyer on an empty
        // cart with nothing pointing at the one they filled. The cart stays the session
        // cart until an order that names it is paid: Session::getSessionCart() consumes
        // it on the next read. ORDER_CART_CLEAR stays declared and listened to for the
        // modules that raise it themselves.

        /* but memorize placed order */
        $event->setOrder(new OrderModel());
        $event->setPlacedOrder($placedOrder);

        /* call pay method */
        $payEvent = new OrderPaymentEvent($placedOrder);

        $this->paymentCartContext->within(
            $cart,
            static fn () => $dispatcher->dispatch($payEvent, TheliaEvents::MODULE_PAY),
        );

        if ($payEvent->hasResponse()) {
            $event->setResponse($payEvent->getResponse());
        }
    }

    /**
     * The cart the order is about to be written from.
     *
     * The order names it — `order.cart_id` is required, and every path that raises
     * ORDER_PAY writes it — so that is what is read first, and the session cart is the
     * fallback for a caller that left it out. In the tunnel of a theme the two are the
     * same row, since the order was built from the session cart a moment earlier; asking
     * the session first would hand a caller with no session a brand new empty cart, which
     * is what getSessionCart() answers when it has nothing to restore.
     *
     * @throws TheliaProcessException when neither names a cart
     */
    private function resolveCart(OrderModel $order, ?SessionInterface $session, EventDispatcherInterface $dispatcher): CartModel
    {
        $cart = null !== $order->getCartId()
            ? CartQuery::create()->findPk($order->getCartId())
            : null;

        $cart ??= $session instanceof Session ? $session->getSessionCart($dispatcher) : null;

        return $cart ?? throw new TheliaProcessException('The order names no cart to be built from');
    }

    public function orderBeforePayment(OrderEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $dispatcher->dispatch(clone $event, TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL);

        $dispatcher->dispatch(clone $event, TheliaEvents::ORDER_SEND_NOTIFICATION_EMAIL);
    }

    /**
     * Clear the cart and the order in the customer session, and retire the guest.
     *
     * The core no longer raises ORDER_CART_CLEAR itself — the cart is consumed by
     * Session::getSessionCart() once its order is paid — but the event stays for the
     * modules that raise it at a moment of their own, and it still does what it did.
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

        if (null === $newStatus) {
            throw new \LogicException('ORDER_UPDATE_STATUS was dispatched without a target status.');
        }

        // Every entry point (back office, API, payment modules, commands) lands here,
        // so this is where the transition graph is enforced.
        $this->transitionGuard->assertAllowed($order, $newStatus, $event->isStatusTransitionForced());

        $event->setPreviousStatusId($order->getStatusId());

        $con = Propel::getConnection(OrderTableMap::DATABASE_NAME);

        // Read before the transaction: what the payment takes from the stock, when the shop reserves
        // it and the stock was left for the payment, and the operation the listeners answer for it
        // (the admin API worked both out already, before its own transaction).
        $stockLock = $this->stockLock();
        $stockPlan = $event->getStockPlan() ?? $stockLock?->planFor($order, $newStatus, $dispatcher);
        $releaseTheCart = $this->leavesTheUnpaidStatus($order, $newStatus);

        // Prevent partial stock update on status change.
        $con->beginTransaction();

        try {
            // The locks come in the same order on every path that changes a status (the admin API
            // takes them so): the row of the order first, then the stock rows in ascending id.
            // Taken the other way round, two requests on the same order wait for each other.
            $statusIdUnderLock = null === $order->getId() ? null : OrderQuery::create()->lockStatusIdForUpdate((int) $order->getId(), $con);

            // Before anything else is read in the transaction: the stock rows the payment takes
            // from, so that the reservations of the other carts are read as they stand.
            $stockLock?->lock($stockPlan->rows ?? [], $con);

            // The model of the order was read before the lock. Another request may have moved the
            // status since (the notification of a payment and the return of the browser confirm
            // the same payment): it is judged on the status stored, never on the one that was read.
            if (null !== $statusIdUnderLock && $statusIdUnderLock !== (int) $order->getStatusId()) {
                $order->reload(false, $con);
                $event->setPreviousStatusId($order->getStatusId());

                if ($statusIdUnderLock === $newStatus) {
                    // Already where the change leads: nothing to take, nothing to tell.
                    $event->setOrder($order);
                    $con->commit();

                    return;
                }

                $this->transitionGuard->assertAllowed($order, $newStatus, $event->isStatusTransitionForced());
                $releaseTheCart = $this->leavesTheUnpaidStatus($order, $newStatus);
            }

            $operation = null === $stockPlan || null === $statusIdUnderLock
                ? null
                : $stockPlan->operationFor($statusIdUnderLock, (int) $order->getStatusId(), $newStatus);

            $stockChanges = $this->updateQuantity($order, $newStatus, $dispatcher, $operation);

            // A stock left for the payment was held by the cart of the order until now: it is sold,
            // or the order is given up.
            if ($releaseTheCart) {
                $this->stockReservation?->releaseCart((int) $order->getCartId(), $con);
            }

            $order->setStatusId($newStatus)->save();

            $event->setOrder($order);

            $con->commit();
        } catch (\Throwable $exception) {
            $con->rollBack();

            // Another request took a lock this one needed and the database broke the tie: nothing
            // of this change stands, and asked again it is judged on what the other one left.
            if (!$exception instanceof OrderStatusChangeConflictException && LockConflictDetector::isLockConflict($exception)) {
                throw new OrderStatusChangeConflictException($exception);
            }

            throw $exception;
        }

        // After the commit, never inside it: a listener meets the stock as the change left it, and
        // the status stays changed whatever the listeners do. Inside the transaction of a caller
        // (the admin API), once that one is over, and never when it is rolled back.
        if (null !== $this->stockLevelAnnouncer) {
            $this->stockLevelAnnouncer->announceChanges($stockChanges);
        } else {
            StockLevelAnnouncer::tell($dispatcher, $stockChanges);
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
        if (OrderVersionQuery::create()->filterById($order->getId())->select(OrderVersionTableMap::COL_STATUS_ID)->distinct()->count() > 1) {
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
     * @param int                                                  $newStatus the new status ID
     * @param GetStockUpdateOperationOnOrderStatusChangeEvent|null $operation what the listeners answered already for this very change, asked again when null
     *
     * @return list<StockLevelChange> what the change did to the stock of each sale element, for the event sent once it is committed
     *
     * @throws \Exception
     * @throws PropelException
     */
    protected function updateQuantity(ModelOrder $order, int $newStatus, EventDispatcherInterface $dispatcher, ?GetStockUpdateOperationOnOrderStatusChangeEvent $operation = null): array
    {
        if ($newStatus === $order->getStatusId() || null === $newStatusModel = OrderStatusQuery::create()->findPk($newStatus)) {
            return [];
        }

        $operationEvent = $operation;

        if (null === $operationEvent) {
            $operationEvent = new GetStockUpdateOperationOnOrderStatusChangeEvent($order, $newStatusModel);
            $dispatcher->dispatch(
                $operationEvent,
                TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE,
            );
        }

        if ($operationEvent->getOperation() === $operationEvent::DO_NOTHING) {
            return [];
        }
        // In the ascending id of the sale element, the order the placement locked the stock rows
        // in: two orders moving the same rows the other way round would wait for each other.
        $orderProductList = $order->getOrderProducts()->getData();
        usort($orderProductList, static fn (OrderProduct $first, OrderProduct $second): int => (int) $first->getProductSaleElementsId() <=> (int) $second->getProductSaleElementsId());

        $checkAvailableStock = ConfigQuery::checkAvailableStock();
        $decreasing = $operationEvent->getOperation() === $operationEvent::DECREASE_STOCK;
        $cartId = (int) $order->getCartId();

        // What the other carts hold of the stock the change takes, read once, after the stock rows
        // were locked (updateStatus()); nothing is read when the shop does not reserve.
        $reservedByOtherCarts = $decreasing && $checkAvailableStock && null !== $this->stockReservation
            ? $this->stockReservation->reservedByOtherCartsOf(
                PaymentStockLock::stockRowsOf($orderProductList),
                $order->getSaleUnitId(),
                $cartId > 0 ? $cartId : null,
                Propel::getConnection(OrderTableMap::DATABASE_NAME),
            )
            : [];

        /** @var array<int, StockLevelChange> $changes one per sale element, from the stock before the first line to the stock after the last */
        $changes = [];
        /** @var array<int, list<StockMovement|null>> $componentMovementsByPack what the take of each component did, by the line of its pack */
        $componentMovementsByPack = [];

        foreach ($orderProductList as $orderProduct) {
            // The sale element of a pack holds no stock: what the pack took is carried by
            // its component lines, which come through here like any other line.
            if (!$orderProduct->movesStockOfItsOwn()) {
                continue;
            }

            $productSaleElementsId = $orderProduct->getProductSaleElementsId();
            $reserved = $reservedByOtherCarts[(int) $productSaleElementsId] ?? 0.0;

            $movement = $this->movesSaleUnitStock($order, $productSaleElementsId)
                ? $this->updateSaleUnitQuantity($order, $orderProduct, $operationEvent, $reserved)
                : $this->updateNationalQuantity($orderProduct, $operationEvent, $checkAvailableStock, $reserved);

            if (null !== $movement && !$movement->onSaleUnitStock()) {
                $changes[$movement->productSaleElementsId] = new StockLevelChange(
                    $movement->productSaleElementsId,
                    isset($changes[$movement->productSaleElementsId]) ? $changes[$movement->productSaleElementsId]->previousQuantity : $movement->previousQuantity,
                    $movement->newQuantity,
                );
            }

            // The stock taken is what the order froze as a preorder, unless the order froze it
            // when it was placed: a line is marked once, by the take that goes below zero.
            if (true === $movement?->preorder) {
                $orderProduct->setIsPreorder(1)->setRestockDate($movement->restockDate)->save();
            }

            if (null !== $orderProduct->getParent()) {
                $componentMovementsByPack[(int) $orderProduct->getParent()][] = $movement;
            }
        }

        // A pack is a preorder when one of its components is, and waits for the latest date.
        foreach ($orderProductList as $orderProduct) {
            if (!isset($componentMovementsByPack[(int) $orderProduct->getId()])) {
                continue;
            }

            $packPreorder = PackPreorder::of($componentMovementsByPack[(int) $orderProduct->getId()]);

            if ($packPreorder['isPreorder']) {
                $orderProduct->setIsPreorder(1)->setRestockDate($packPreorder['restockDate'])->save();
            }
        }

        return array_values($changes);
    }

    /**
     * The shared counter of a sale element moved by a change of status: given back, or taken
     * again when the stock was left for the payment, within what the sale element may still sell
     * (below zero included when it sells there) less what the other carts hold.
     *
     * @return StockMovement|null what the change did to the stock, null when it moved nothing
     *
     * @throws TheliaProcessException when the sale element has not enough stock left to take the order
     * @throws PropelException
     */
    private function updateNationalQuantity(
        OrderProduct $orderProduct,
        GetStockUpdateOperationOnOrderStatusChangeEvent $operationEvent,
        bool $checkAvailableStock,
        float $reservedByOtherCarts,
    ): ?StockMovement {
        $productSaleElements = ProductSaleElementsQuery::create()->findPk($orderProduct->getProductSaleElementsId());

        if (null === $productSaleElements) {
            return null;
        }

        $quantity = (float) $orderProduct->getQuantity();
        $before = (float) $productSaleElements->getQuantity();
        $policy = OutOfStockPolicy::fromStored($productSaleElements->getOutOfStockPolicy());
        $decreasing = $operationEvent->getOperation() === $operationEvent::DECREASE_STOCK;
        $offset = 0.0;

        if ($operationEvent->getOperation() === $operationEvent::INCREASE_STOCK) {
            $offset = $quantity;
        } elseif ($decreasing) {
            $ceiling = SellableStock::ceilingOf($before, $policy, $productSaleElements->getPreorderLimit());

            /* Check if we have enough stock: what the sale element may still sell, below zero included when it sells there */
            if ($checkAvailableStock && null !== $ceiling && $quantity > max(0.0, $ceiling - $reservedByOtherCarts)) {
                throw new TheliaProcessException($productSaleElements->getRef().' : Not enough stock 2');
            }

            $offset = -$quantity;
        }

        Tlog::getInstance()->addError('Product stock: '.$before.' -> '.($before + $offset));

        $productSaleElements
            ->setQuantity($before + $offset)
            ->save();

        if (0.0 === $offset) {
            return null;
        }

        $preorder = $decreasing && $checkAvailableStock && PreorderRule::isPreorder($quantity, $before, $reservedByOtherCarts, $policy);

        return new StockMovement(
            (int) $productSaleElements->getId(),
            null,
            $before,
            (float) $productSaleElements->getQuantity(),
            $preorder,
            $preorder ? $productSaleElements->getRestockDate('Y-m-d') : null,
        );
    }

    /**
     * The injected lock, or the one a caller that gave the reservation alone would get.
     */
    private function stockLock(): ?PaymentStockLock
    {
        return $this->paymentStockLock ?? (null === $this->stockReservation ? null : new PaymentStockLock($this->stockReservation));
    }

    /**
     * Whether the change takes an unpaid order to a paid or a cancelled status, when the shop
     * reserves its stock: what its cart still holds is then released. Nothing is read otherwise.
     *
     * @throws PropelException
     */
    private function leavesTheUnpaidStatus(ModelOrder $order, int $newStatus): bool
    {
        if (null === $this->stockReservation || !$this->stockReservation->isActive() || $newStatus === $order->getStatusId() || (int) $order->getCartId() <= 0) {
            return false;
        }

        $newStatusModel = OrderStatusQuery::create()->findPk($newStatus);

        return null !== $newStatusModel && $order->isNotPaid() && ($newStatusModel->isPaid(false) || $newStatusModel->isCancelled());
    }

    /**
     * An order placed on a sale unit that keeps its own stock of the sale element gives
     * back, or takes again, from that stock: the same rule the checkout decremented by.
     * Any other order moves the shared national counter, as it always has.
     */
    private function movesSaleUnitStock(ModelOrder $order, ?int $productSaleElementsId): bool
    {
        return null !== $productSaleElementsId
            && null !== $this->stockDecrementer
            && $this->stockDecrementer->movesSaleUnitStock($order->getSaleUnitId(), $productSaleElementsId);
    }

    /**
     * @return StockMovement|null what a decrease did to the stock of the unit
     *
     * @throws TheliaProcessException when the sale unit has not enough stock left to take the order again
     */
    private function updateSaleUnitQuantity(
        ModelOrder $order,
        OrderProduct $orderProduct,
        GetStockUpdateOperationOnOrderStatusChangeEvent $operationEvent,
        float $reservedByOtherCarts,
    ): ?StockMovement {
        $productSaleElementsId = (int) $orderProduct->getProductSaleElementsId();
        $quantity = (float) $orderProduct->getQuantity();
        $connection = Propel::getConnection(ProductSaleElementsTableMap::DATABASE_NAME);

        if ($operationEvent->getOperation() === $operationEvent::INCREASE_STOCK) {
            $this->stockDecrementer?->increment($productSaleElementsId, $quantity, $connection, $order->getSaleUnitId());

            return null;
        }

        if ($operationEvent->getOperation() !== $operationEvent::DECREASE_STOCK) {
            return null;
        }

        $checkAvailableStock = ConfigQuery::checkAvailableStock();

        try {
            return $this->stockDecrementer?->decrement(
                $productSaleElementsId,
                $quantity,
                guardAvailability: $checkAvailableStock,
                allowNegativeStock: !$checkAvailableStock,
                connection: $connection,
                saleUnitId: $order->getSaleUnitId(),
                // What the other carts hold of the unit's stock stays theirs.
                reservedByOtherCarts: $checkAvailableStock ? $reservedByOtherCarts : 0.0,
            );
        } catch (TheliaProcessException $shortage) {
            throw new TheliaProcessException($orderProduct->getProductSaleElementsRef().' : Not enough stock 2', previous: $shortage);
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

        if (1 !== (int) $orderAddress->getVatExempted()) {
            $orderAddress
                ->setCompany($event->getCompany())
                ->setSiret($event->getSiret())
                ->setVatNumber($event->getVatNumber());
        }

        $orderAddress
            ->setCustomerTitleId($event->getTitle() === null ? null : (int) $event->getTitle())
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
     * The session of the current request, when there is a request and it has one.
     *
     * `Request::getSession()` throws on a request that carries none, which is the shape
     * of a stateless API request: asking whether there is one first is what lets the very
     * same listener place an order for a browser and for a caller that has no session at
     * all.
     */
    protected function getSession(): ?SessionInterface
    {
        /** @var Request|null $request */
        $request = $this->requestStack->getMainRequest();

        return $request?->hasSession() === true ? $request->getSession() : null;
    }
}
