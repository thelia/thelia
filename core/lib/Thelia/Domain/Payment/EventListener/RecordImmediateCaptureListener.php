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
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Model\Order;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\PaymentModuleWithCaptureInterface;

/**
 * Gives the orders of a payment module that keeps no journal their capture line.
 *
 * A module that takes the price at once tells the core nothing but "paid", through
 * the order status. The first time an order reaches a paid status, a succeeded capture
 * of its total is written, carrying the transaction reference the module saved on the
 * order, so that Cheque, FreeOrder and every published module show one movement without
 * a line of code. Nothing is written for a module that authorizes first and captures
 * later, nor when the journal already holds an authorization or a capture: whatever
 * wrote them told the story, and an open authorization was not taken by marking the
 * order paid.
 *
 * Priority 4: after Thelia\Action\Order::updateStatus (128) has saved the status, and
 * after every core listener of the paid status — history and invoice numbering (64),
 * coupons (10), status actions (5) — so that nothing here can cut them off. A failure is
 * logged and the status change goes on: the status is already committed, and a provider
 * notification answered with an error would be retried on an order already paid.
 */
final readonly class RecordImmediateCaptureListener
{
    public function __construct(
        private PaymentTransactionRecorder $recorder,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 4)]
    public function onOrderStatusUpdate(OrderEvent $event): void
    {
        $order = $event->getOrder();

        if (!$order->isPaid(false)) {
            return;
        }

        // Paid to processing, processing to sent: the order was paid already, and the
        // capture written then.
        if ($this->wasAlreadyPaid($event)) {
            return;
        }

        if ($this->moduleCapturesByHand($order)) {
            return;
        }

        try {
            $this->recorder->recordImmediateCaptureIfNone(
                $order,
                $order->getTotalAmount(),
                $order->getTransactionRef(),
                $event->getSourceModuleCode() ?? $this->paymentModuleCodeOf($order),
            );
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->error(\sprintf(
                'The capture of order %s, paid, could not be written to its payment journal: %s',
                (string) $order->getRef(),
                $throwable->getMessage(),
            ));
        }
    }

    private function wasAlreadyPaid(OrderEvent $event): bool
    {
        $previousStatusId = $event->getPreviousStatusId();

        if (null === $previousStatusId) {
            return false;
        }

        $previousStatus = OrderStatusQuery::create()->findPk($previousStatusId);

        return null !== $previousStatus && $previousStatus->isPaid(false);
    }

    private function moduleCapturesByHand(Order $order): bool
    {
        try {
            $module = $order->getPaymentModuleInstance();
        } catch (TheliaProcessException) {
            // The module is gone: whoever marks the order paid now is the author of
            // the movement, and the line is still worth writing.
            return false;
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->warning(\sprintf('The payment module of order %s cannot be instantiated: %s', (string) $order->getRef(), $throwable->getMessage()));

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
