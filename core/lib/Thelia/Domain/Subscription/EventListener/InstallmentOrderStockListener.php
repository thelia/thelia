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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\GetStockUpdateOperationOnOrderStatusChangeEvent;
use Thelia\Core\Event\Product\VirtualProductOrderHandleEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Subscription\InstallmentPlacementContext;
use Thelia\Model\SubscriptionOrderQuery;

/**
 * A subscription to a virtual product (a membership, an online service) moves the stock once,
 * with the order it is taken with; the orders of the installments that follow leave it alone.
 *
 * The stock of an order moves at two moments, and the rule is applied at both, and only to
 * the installment orders of a virtual sale element:
 *  - when the order is placed, a virtual line takes from the stock only when a listener of
 *    VIRTUAL_PRODUCT_ORDER_HANDLE says so: an installment being placed is told not to;
 *  - when its status changes, the core moves the stock of every line, virtual or not
 *    (Action\Order::getStockUpdateOnOrderStatusChange()): for an installment order, the
 *    answer is turned into "do nothing", whether it would take (paid, with a module that
 *    leaves the stock to the payment) or give back (cancelled, refunded) what the order
 *    never took.
 * The order a subscription is taken with is not an installment order here (it is the
 * initial order of the subscription), and a physical product moves its stock with every
 * order, as any order does.
 */
final readonly class InstallmentOrderStockListener implements EventSubscriberInterface
{
    public function __construct(
        private InstallmentPlacementContext $placementContext,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After the listeners that decide for the virtual product (VirtualProductDelivery at 128).
            TheliaEvents::VIRTUAL_PRODUCT_ORDER_HANDLE => ['leaveTheStockOfAPlacedInstallment', 64],
            // After the core decided the operation (Action\Order at 128).
            TheliaEvents::ORDER_GET_STOCK_UPDATE_OPERATION_ON_ORDER_STATUS_CHANGE => ['leaveTheStockOfAVirtualInstallment', 64],
        ];
    }

    public function leaveTheStockOfAPlacedInstallment(VirtualProductOrderHandleEvent $event): void
    {
        if ($this->placementContext->isPlacingAnInstallment()) {
            $event->setUseStock(false);
        }
    }

    public function leaveTheStockOfAVirtualInstallment(GetStockUpdateOperationOnOrderStatusChangeEvent $event): void
    {
        if ($event::DO_NOTHING === $event->getOperation() || null === $event->getOrder()->getId()) {
            return;
        }

        $installment = SubscriptionOrderQuery::create()
            ->filterByOrderId($event->getOrder()->getId())
            ->findOne();

        $subscription = $installment?->getSubscription();

        if (null === $subscription || $subscription->getInitialOrderId() === $event->getOrder()->getId()) {
            return;
        }

        if (1 === (int) $subscription->getProductSaleElements()?->getProduct()?->getVirtual()) {
            $event->setOperation($event::DO_NOTHING);
        }
    }
}
