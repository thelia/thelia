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
use Thelia\Core\Event\Order\OrderPaymentCaptureEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Payment\Service\PaymentCaptureService;

/**
 * The core answer to ORDER_PAYMENT_CAPTURE: the back office, the admin API and a
 * module's own automation all capture through this event.
 */
final readonly class CaptureOrderPaymentListener
{
    public function __construct(
        private PaymentCaptureService $captureService,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::ORDER_PAYMENT_CAPTURE, priority: 128)]
    public function onCapture(OrderPaymentCaptureEvent $event): void
    {
        $event->setTransaction($this->captureService->capture($event->getOrder(), $event->getAmount()));
    }
}
