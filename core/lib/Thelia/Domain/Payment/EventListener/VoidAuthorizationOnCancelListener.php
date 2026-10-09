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
use Thelia\Core\Event\Order\OrderPaymentTransactionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\Enum\PaymentTransactionType;
use Thelia\Domain\Payment\Service\PaymentCaptureService;
use Thelia\Domain\Payment\Service\PaymentTransactionTotalsReader;
use Thelia\Log\Tlog;
use Thelia\Model\Order;
use Thelia\Model\OrderStatusQuery;

/**
 * Releases what the authorization of an order still holds when the order is cancelled,
 * so the amount does not stay reserved on the buyer's card until it expires — and when
 * the provider confirms an authorization on an order already cancelled, which nothing
 * would ever capture.
 *
 * Priority 3 on the status change, after every core listener of the status: the
 * cancellation is committed whatever the provider answers, and a release that fails is
 * logged for the merchant to do at the provider. The journal announces its lines once
 * it is released, so the late authorization is released from outside its lock.
 */
final readonly class VoidAuthorizationOnCancelListener
{
    public function __construct(
        private PaymentCaptureService $captureService,
        private PaymentTransactionTotalsReader $totalsReader,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_UPDATE_STATUS, priority: 3)]
    public function onOrderStatusUpdate(OrderEvent $event): void
    {
        $order = $event->getOrder();

        if (!$order->getOrderStatus()->isCancelled(false) || $this->wasAlreadyCancelled($event)) {
            return;
        }

        $this->releaseWhatIsHeld($order);
    }

    #[AsEventListener(event: TheliaEvents::ORDER_PAYMENT_TRANSACTION_RECORDED)]
    public function onTransactionRecorded(OrderPaymentTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();

        if (PaymentTransactionType::AUTHORIZATION !== $transaction->getTypeEnum()
            || !$transaction->isSucceeded()
            || !$event->getOrder()->getOrderStatus()->isCancelled(false)) {
            return;
        }

        $this->releaseWhatIsHeld($event->getOrder());
    }

    private function releaseWhatIsHeld(Order $order): void
    {
        if (!$this->totalsReader->forOrder((int) $order->getId())->hasSomethingLeftToCapture()) {
            return;
        }

        if (!$this->captureService->supportsCapture($order)) {
            Tlog::getInstance()->warning(\sprintf(
                'Order %s is cancelled while its authorization still holds an amount its payment module cannot release: release it at the provider.',
                (string) $order->getRef(),
            ));

            return;
        }

        try {
            $this->captureService->voidAuthorization($order);
        } catch (\Throwable $throwable) {
            Tlog::getInstance()->error(\sprintf(
                'The authorization of cancelled order %s could not be released: %s',
                (string) $order->getRef(),
                $throwable->getMessage(),
            ));
        }
    }

    private function wasAlreadyCancelled(OrderEvent $event): bool
    {
        $previousStatusId = $event->getPreviousStatusId();
        $previousStatus = null === $previousStatusId ? null : OrderStatusQuery::create()->findPk($previousStatusId);

        return null !== $previousStatus && $previousStatus->isCancelled(false);
    }
}
