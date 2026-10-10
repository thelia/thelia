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
use Thelia\Core\Event\Order\OrderPaymentSettlementEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Service\OrderHistoryActorResolver;
use Thelia\Domain\Payment\Exception\PaymentAnnouncementFailedException;
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;

/**
 * The core answer to ORDER_PAYMENT_TRANSACTION_SETTLE: a pending line the provider never
 * confirmed gets the outcome the merchant read at the provider. The line says it was
 * settled by hand, by whom, with the merchant's note; the administration log keeps the
 * same.
 */
final readonly class SettleOrderPaymentTransactionListener
{
    public const ERROR_CODE_SETTLED_BY_HAND = 'settled_by_hand';

    private const DEFAULT_NOTE = 'Outcome recorded by hand from the provider\'s back office.';

    public function __construct(
        private PaymentTransactionRecorder $recorder,
        private OrderHistoryActorResolver $actorResolver,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_PAYMENT_TRANSACTION_SETTLE, priority: 128)]
    public function onSettle(OrderPaymentSettlementEvent $event): void
    {
        $note = trim((string) $event->getNote());
        $note = '' === $note ? self::DEFAULT_NOTE : $note;
        $administrator = $this->actorResolver->actingAdministrator();

        if (null !== $administrator) {
            $note .= \sprintf(' (recorded by %s)', $administrator->getUsername());
        }

        try {
            $event->setTransaction($this->recorder->settle(
                $event->getTransaction(),
                $event->getState(),
                $event->getPspReference(),
                self::ERROR_CODE_SETTLED_BY_HAND,
                $note,
            ));
        } catch (PaymentAnnouncementFailedException $announcementFailure) {
            // The line is settled; a listener that broke afterwards is logged by the
            // recorder, and the merchant's gesture is reported, and logged, as done.
            $event->setTransaction($announcementFailure->getTransaction());
        }
    }
}
