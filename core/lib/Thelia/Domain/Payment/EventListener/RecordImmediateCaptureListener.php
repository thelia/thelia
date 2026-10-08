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
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;
use Thelia\Model\Order;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\PaymentModuleWithCaptureInterface;

/**
 * Gives the orders of a payment module that keeps no journal their capture line.
 *
 * A module that takes the price at once tells the core nothing but "paid", through
 * the order status. The first time an order reaches a paid status, a succeeded capture
 * of its total is written, carrying the transaction reference the module saved on
 * the order, so that Cheque, FreeOrder and every published module show one movement
 * without a line of code. A module that authorizes first and captures later writes
 * its own lines and is left alone.
 *
 * Priority 64: after Thelia\Action\Order::updateStatus (128) has saved the status,
 * alongside the invoice numbering and the history, which also read a paid order.
 */
final readonly class RecordImmediateCaptureListener
{
    public function __construct(
        private PaymentTransactionRecorder $recorder,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 64)]
    public function onOrderStatusUpdate(OrderEvent $event): void
    {
        $order = $event->getOrder();

        if (!$order->isPaid(false)) {
            return;
        }

        // Paid to processing, processing to sent: the order was paid already, and the
        // capture written then.
        if ($this->wasAlreadyPaid($event, $order)) {
            return;
        }

        if ($this->moduleKeepsItsOwnJournal($order)) {
            return;
        }

        $this->recorder->recordImmediateCaptureIfNone(
            $order,
            $order->getTotalAmount(),
            $order->getTransactionRef(),
            $event->getSourceModuleCode() ?? $this->paymentModuleCodeOf($order),
        );
    }

    private function wasAlreadyPaid(OrderEvent $event, Order $order): bool
    {
        $previousStatusId = $event->getPreviousStatusId();

        if (null === $previousStatusId) {
            return false;
        }

        $previousStatus = OrderStatusQuery::create()->findPk($previousStatusId);

        return null !== $previousStatus && $previousStatus->isPaid(false);
    }

    private function moduleKeepsItsOwnJournal(Order $order): bool
    {
        try {
            $module = $order->getPaymentModuleInstance();
        } catch (\Throwable) {
            // The module is gone: whoever marks the order paid now is the author of
            // the movement, and the line is still worth writing.
            return false;
        }

        return $module instanceof PaymentModuleWithCaptureInterface && $module->supportsDeferredCapture();
    }

    private function paymentModuleCodeOf(Order $order): ?string
    {
        try {
            return $order->getPaymentModuleInstance()->getCode();
        } catch (\Throwable) {
            return $order->getPaymentModuleTitle();
        }
    }
}
