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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Order\OrderPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\Exception\CancelledOrderPaymentRefusedException;
use Thelia\Domain\Payment\Exception\InstantPaymentRefusedException;
use Thelia\Domain\Payment\Service\OrderPaymentPartSettlement;
use Thelia\Model\OrderStatusQuery;

/**
 * Settles the parts of an order, and turns it paid when its parts are all there is to pay.
 *
 * Before the payment module is asked to pay (MODULE_PAY, Action\Module::pay listens at 128):
 * the instant parts are settled, all or none, so that the provider is only asked for the
 * balance once what the buyer holds in the shop has been spent. A part already settled is
 * left alone, which makes a new attempt on the same order safe. When the instant parts
 * cover the order the core turns it paid itself, once every part is settled, and
 * Action\Module::pay does not ask the module to pay. An order whose instant parts were
 * released by a cancellation is refused outright by a new MODULE_PAY, under its lock, before
 * any part is settled and before its module is asked to pay, whether it is still cancelled
 * or the back office turned it back to unpaid (CancelledOrderPaymentRefusedException, 409 on
 * the API): nothing pays it any more.
 *
 * The paid status the instant parts lead to goes through OrderPaymentPartPaidStatusGuardListener
 * like any other, which reads the parts again under the lock of the order: a cancellation that
 * releases them between the check below and the status change is refused there.
 *
 * When the order is paid by anyone else (the provider confirming, the back office), its
 * part of the provider is settled. That runs after Action\Order::updateStatus, at 128, so
 * that a transition the status graph refuses settles nothing. Refusing a paid status while an
 * instant part is not settled is OrderPaymentPartPaidStatusGuardListener's business, and a
 * forced transition is let through there.
 */
final readonly class OrderPaymentPartSettlementListener
{
    public function __construct(
        private OrderPaymentPartSettlement $settlement,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @throws InstantPaymentRefusedException
     * @throws CancelledOrderPaymentRefusedException
     */
    #[AsEventListener(event: TheliaEvents::MODULE_PAY, priority: 192)]
    public function settleTheInstantParts(OrderPaymentEvent $event): void
    {
        $order = $event->getOrder();

        $this->settlement->settleInstantPartsOf($order);

        if (!$this->settlement->isPaidByInstantPartsOnly($order) || $order->isPaid(false)) {
            return;
        }

        // Parts released by a cancellation pay nothing: the order stays as it is. The
        // module is not asked to pay() either, since Action\Module::pay skips an order paid
        // by instant parts only, and answers MODULE_PAY with no response.
        if (!$this->settlement->areAllPartsSettled($order)) {
            return;
        }

        $this->dispatcher->dispatch(
            (new OrderEvent($order))->setStatus((int) OrderStatusQuery::getPaidStatus()->getId()),
            TheliaEvents::ORDER_UPDATE_STATUS,
        );
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 100)]
    public function settleTheProviderPartOfAPaidOrder(OrderEvent $event): void
    {
        $order = $event->getOrder();

        if ($order->isPaid(false)) {
            $this->settlement->settleTheProviderPartOf($order, $event->getSourceModuleCode());
        }
    }
}
