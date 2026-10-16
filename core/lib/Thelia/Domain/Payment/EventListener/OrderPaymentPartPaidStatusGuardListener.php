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

namespace Thelia\Domain\Payment\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Exception\OrderStatusTransitionRefusedException;
use Thelia\Domain\Payment\Service\OrderPaymentPartSettlement;
use Thelia\Model\OrderStatusQuery;

/**
 * Refuses to turn an order paid while one of its instant parts is not settled, unless the
 * transition is forced.
 *
 * The status graph lets a cancelled order be paid when the shop drew no transition from
 * cancelled, and a provider may confirm the old session of an order a new attempt cancelled,
 * whose gift card was given back. Before Action\Order::updateStatus, at 128, so that the
 * refusal comes before anything is written, the same way a transition the graph refuses
 * does. A transition forced from the back office is let through, and settles nothing: the
 * administrator answers for it.
 */
final readonly class OrderPaymentPartPaidStatusGuardListener
{
    public function __construct(
        private OrderPaymentPartSettlement $settlement,
    ) {
    }

    /**
     * @throws OrderStatusTransitionRefusedException
     */
    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 160)]
    public function refuseAPaidStatusTheInstantPartsDoNotHold(OrderEvent $event): void
    {
        $toStatusId = $event->getStatus();

        if (null === $toStatusId || $event->isStatusTransitionForced()) {
            return;
        }

        if (!(OrderStatusQuery::create()->findPk($toStatusId)?->isPaid(false) ?? false)) {
            return;
        }

        $this->settlement->assertMayBeTurnedPaid($event->getOrder(), (int) $toStatusId, $event->getSourceModuleCode());
    }
}
