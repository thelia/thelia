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

namespace Thelia\Domain\Subscription\EventListener;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Subscription\SubscriptionFactory;
use Thelia\Log\Tlog;
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;
use Thelia\Model\CustomerPaymentMethod;
use Thelia\Model\CustomerPaymentMethodQuery;
use Thelia\Model\Order;
use Thelia\Model\OrderStatusQuery;
use Thelia\Model\SubscriptionOrderQuery;
use Thelia\Model\SubscriptionQuery;

/**
 * Takes the subscription of a customer when the order that carried it turns paid.
 *
 * The line that holds the plan is read on the cart the order was placed from (a cart that
 * has an order is never purged), and the subscription is created with
 * SubscriptionFactory::fromInitialOrder(). A plan with a trial leaves the order at 0: the
 * payment module registers the method and turns the order paid, which is what is waited for
 * here, as for any order.
 *
 * The method installments are charged to, in this order:
 *  1. the stored method the buyer chose on the cart (cart.customer_payment_method_id);
 *  2. otherwise, the most recent method of this customer that the payment module of the order
 *     registered from the moment the order was placed on: a module that stores the card
 *     while it takes the first payment, or the registration at 0 of a trial, is not told
 *     to the cart, and its method is the one the order created;
 *  3. otherwise none: the subscription exists, its first charge fails and the customer is
 *     asked to give a method (SubscriptionManager::changePaymentMethod()).
 *
 * Idempotent: an order that already is the initial order of a subscription, or that is an
 * installment order (its cart carries the plan too), creates nothing, so a second paid
 * event, a notification replayed by the provider or a status moved from paid to processing
 * leaves one subscription.
 *
 * After the status is written and the listeners that matter to the payment ran: a failure
 * here is logged and never fails the payment notification, the order is paid either way.
 */
final readonly class SubscriptionActivationListener
{
    public function __construct(
        private SubscriptionFactory $subscriptionFactory,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 20)]
    public function takeTheSubscriptionOfAPaidOrder(OrderEvent $event): void
    {
        $order = $event->getOrder();

        if (null === $order->getId() || !$this->justTurnedPaid($event, $order)) {
            return;
        }

        try {
            $this->activate($order);
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->error(\sprintf('The subscription of the order %s could not be created: %s', (string) $order->getRef(), $throwable->getMessage()));
        }
    }

    private function justTurnedPaid(OrderEvent $event, Order $order): bool
    {
        if (!$order->getOrderStatus()?->isPaid(false)) {
            return false;
        }

        $previousStatusId = $event->getPreviousStatusId();

        return null === $previousStatusId || !(OrderStatusQuery::create()->findPk($previousStatusId)?->isPaid(false) ?? false);
    }

    private function activate(Order $order): void
    {
        if (null === $order->getCartId()) {
            return;
        }

        $line = CartItemQuery::create()
            ->filterByCartId($order->getCartId())
            ->filterBySubscriptionPlanId(null, Criteria::ISNOTNULL)
            ->orderById()
            ->findOne();

        if (!$line instanceof CartItem) {
            return;
        }

        if (SubscriptionQuery::create()->filterByInitialOrderId($order->getId())->exists()
            || SubscriptionOrderQuery::create()->filterByOrderId($order->getId())->exists()) {
            return;
        }

        $plan = $line->getSubscriptionPlan();
        $saleElements = $line->getProductSaleElements();

        if (null === $plan || null === $saleElements) {
            return;
        }

        $this->subscriptionFactory->fromInitialOrder(
            $order,
            $plan,
            $saleElements,
            max(1, (int) $line->getQuantity()),
            $this->methodOf($order, $line),
        );
    }

    private function methodOf(Order $order, CartItem $line): ?CustomerPaymentMethod
    {
        $chosenId = $line->getCart()?->getCustomerPaymentMethodId();

        if (null !== $chosenId) {
            $chosen = CustomerPaymentMethodQuery::create()
                ->filterById($chosenId)
                ->filterByCustomerId($order->getCustomerId())
                ->findOne();

            if ($chosen instanceof CustomerPaymentMethod) {
                return $chosen;
            }
        }

        $query = CustomerPaymentMethodQuery::create()
            ->filterByCustomerId($order->getCustomerId())
            ->filterByPaymentModuleId($order->getPaymentModuleId())
            ->orderByCreatedAt(Criteria::DESC)
            ->orderById(Criteria::DESC);

        // To the second, as the column holds it: a DateTime with its microseconds would put the
        // order a fraction of a second after a method written in the same second.
        if (null !== $order->getCreatedAt()) {
            $query->filterByCreatedAt($order->getCreatedAt('Y-m-d H:i:s'), Criteria::GREATER_EQUAL);
        }

        return $query->findOne();
    }
}
