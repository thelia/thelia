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

namespace Thelia\Domain\Shipping\Service;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Event\DeliveryModuleOptionEvent;
use Thelia\Api\Resource\DeliveryModuleOption;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Shipping\DeliveryDate\Service\DeliveryTimeEstimator;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Module;
use Thelia\Model\State;

final readonly class DeliveryOptionsProvider
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private DeliveryTimeEstimator $deliveryTimeEstimator,
    ) {
    }

    public function getOptions(Module $module, ?Address $address, Cart $cart, Country $country, ?State $state): array
    {
        $event = new DeliveryModuleOptionEvent($module, $address, $cart, $country, $state);

        $this->dispatcher->dispatch($event, TheliaEvents::MODULE_DELIVERY_GET_OPTIONS);

        $options = $event->getDeliveryModuleOptions();
        $this->announceDeliveryDates($module, $cart, $options);

        return $options;
    }

    /**
     * Fills the two dates of each option from the carrier delays, unless the module gave
     * them itself: a module that knows its rounds better than the merchant's settings keeps
     * the last word. An empty string, which some modules write for "unknown", is no date.
     *
     * @param array<mixed> $options
     */
    private function announceDeliveryDates(Module $module, Cart $cart, array $options): void
    {
        $estimate = null;

        foreach ($options as $option) {
            if (!$option instanceof DeliveryModuleOption
                || '' !== (string) $option->getMinimumDeliveryDate()
                || '' !== (string) $option->getMaximumDeliveryDate()) {
                continue;
            }

            $estimate ??= $this->deliveryTimeEstimator->estimateForCart($module, $cart) ?? false;

            if (false === $estimate) {
                return;
            }

            $option
                ->setMinimumDeliveryDate($estimate->earliestDay())
                ->setMaximumDeliveryDate($estimate->latestDay());
        }
    }
}
