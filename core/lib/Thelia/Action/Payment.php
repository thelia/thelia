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

namespace Thelia\Action;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Module\Payment\PaymentCartContext;

/**
 * Class Payment.
 *
 * @author Julien Chanséaume <julien@thelia.net>
 */
class Payment implements EventSubscriberInterface
{
    public function __construct(
        private readonly PaymentCartContext $paymentCartContext,
    ) {
    }

    /**
     * Check if a module is valid.
     */
    public function isValid(IsValidPaymentEvent $event, $eventName, EventDispatcherInterface $dispatcher): void
    {
        $this->paymentCartContext->within(
            $event->getCart(),
            fn () => $this->judge($event, $dispatcher),
        );
    }

    private function judge(IsValidPaymentEvent $event, EventDispatcherInterface $dispatcher): void
    {
        $module = $event->getModule();

        // dispatch event to target specific module
        $dispatcher->dispatch(
            $event,
            TheliaEvents::getModuleEvent(
                TheliaEvents::MODULE_PAYMENT_IS_VALID,
                $module->getCode(),
            ),
        );

        if ($event->isPropagationStopped()) {
            return;
        }

        // call legacy module method
        $event->setValidModule($module->isValidPayment())
            ->setMinimumAmount((float) $module->getMinimumAmount())
            ->setMaximumAmount((float) $module->getMaximumAmount());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::MODULE_PAYMENT_IS_VALID => ['isValid', 128],
        ];
    }
}
