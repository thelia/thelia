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

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Payment\IsValidPaymentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Subscription\SubscriptionCartGuard;
use Thelia\Module\RecurringPaymentModuleInterface;

/**
 * A cart that holds a subscription is paid with a module that can charge the installments
 * without the customer, and with no other: FreeOrder, which would settle a trial at 0 EUR
 * and keep no payment method, is not offered, nor is a module that only takes a payment
 * the customer makes.
 *
 * Above the judge of the modules (Action\Payment, 128) and of the customer group policy, and
 * it stops the propagation when it refuses: the answer holds whatever the module itself
 * would say. The same event is what the options of the checkout and the guard of the order
 * placement (CartGuard::checkValidPayment) are built on.
 */
final readonly class SubscriptionPaymentOptionsListener
{
    public function __construct(
        private SubscriptionCartGuard $subscriptionCartGuard,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::MODULE_PAYMENT_IS_VALID, priority: 256)]
    public function offerOnlyTheModulesThatCanCharge(IsValidPaymentEvent $event): void
    {
        if (!$this->subscriptionCartGuard->loadedCartHoldsASubscriptionLine($event->getCart())) {
            return;
        }

        $module = $event->getModule();

        if ($module instanceof RecurringPaymentModuleInterface && $module->supportsRecurringCharges()) {
            return;
        }

        $event->setValidModule(false);
        $event->stopPropagation();
    }
}
