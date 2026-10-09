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
use Thelia\Domain\Payment\Service\PaymentTransactionRecorder;

/**
 * The core answer to ORDER_PAYMENT_TRANSACTION_SETTLE: a pending line the provider never
 * confirmed gets the outcome the merchant read at the provider. The line says it was
 * settled by hand, with the merchant's note; who did it is in the administration log.
 */
final readonly class SettleOrderPaymentTransactionListener
{
    public const ERROR_CODE_SETTLED_BY_HAND = 'settled_by_hand';

    private const DEFAULT_NOTE = 'Outcome recorded by hand from the provider\'s back office.';

    public function __construct(
        private PaymentTransactionRecorder $recorder,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_PAYMENT_TRANSACTION_SETTLE, priority: 128)]
    public function onSettle(OrderPaymentSettlementEvent $event): void
    {
        $note = trim((string) $event->getNote());

        $event->setTransaction($this->recorder->settle(
            $event->getTransaction(),
            $event->getState(),
            $event->getPspReference(),
            self::ERROR_CODE_SETTLED_BY_HAND,
            '' === $note ? self::DEFAULT_NOTE : $note,
        ));
    }
}
