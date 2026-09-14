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

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\CheckoutStep\CheckoutStepSynchronizeEvent;
use Thelia\Core\Event\CheckoutStep\CheckoutStepToggleActiveEvent;
use Thelia\Core\Event\CheckoutStep\CheckoutStepUpdatePositionEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Checkout\Service\CheckoutStepConfigurationService;

/**
 * The merchant's side of the checkout steps, reached the way the rest of the back
 * office reaches the model: through an event.
 *
 * The work itself is the configuration service's, and the shape the tunnel has to keep
 * is checked there — a module writing the rows straight into the table has to go
 * through the same refusals as the back office form.
 */
class CheckoutStep extends BaseAction implements EventSubscriberInterface
{
    public function __construct(
        private readonly CheckoutStepConfigurationService $configurationService,
    ) {
    }

    /**
     * The event names the state to write, so nothing is read back first: deciding here
     * what the opposite of the current state is would hand two administrators clicking
     * the same switch a result neither of them asked for.
     */
    public function toggleActive(CheckoutStepToggleActiveEvent $event): void
    {
        $event->setCheckoutStep(
            $this->configurationService->setActive($event->getCode(), $event->isActive()),
        );
    }

    public function updatePosition(CheckoutStepUpdatePositionEvent $event): void
    {
        $event->setCheckoutStep(
            $this->configurationService->updatePosition($event->getCode(), $event->getPosition()),
        );
    }

    public function synchronize(CheckoutStepSynchronizeEvent $event): void
    {
        $event->setCreatedCodes($this->configurationService->synchronize());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CHECKOUT_STEP_TOGGLE_ACTIVE => ['toggleActive', 128],
            TheliaEvents::CHECKOUT_STEP_UPDATE_POSITION => ['updatePosition', 128],
            TheliaEvents::CHECKOUT_STEP_SYNCHRONIZE => ['synchronize', 128],
        ];
    }
}
