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
use Thelia\Domain\Shipping\ProductRule\ProductShippingRuleQuoter;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Module;
use Thelia\Model\OrderPostage;
use Thelia\Model\State;

final readonly class DeliveryOptionsProvider
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private ?ProductShippingRuleQuoter $productShippingRuleQuoter = null,
    ) {
    }

    public function getOptions(Module $module, ?Address $address, Cart $cart, Country $country, ?State $state): array
    {
        $askCarrier = function (Cart $quotedCart) use ($module, $address, $country, $state): array {
            $event = new DeliveryModuleOptionEvent($module, $address, $quotedCart, $country, $state);

            $this->dispatcher->dispatch($event, TheliaEvents::MODULE_DELIVERY_GET_OPTIONS);

            return $event->getDeliveryModuleOptions();
        };

        // The prices listed to the buyer follow the shipping rules of the products,
        // as the postage charged afterwards does.
        $options = null !== $this->productShippingRuleQuoter
            ? $this->productShippingRuleQuoter->quoteOptions($module, $cart, $country, $askCarrier)
            : $askCarrier($cart);

        foreach ($options as $option) {
            if ($option instanceof DeliveryModuleOption) {
                $this->completeUntaxedPostage($option);
            }
        }

        return $options;
    }

    /**
     * A carrier may quote the postage and its tax alone. The untaxed amount is then read the way
     * the core reads it on its own postage, in {@see OrderPostage::getUntaxedAmount()}, so that
     * every option is published with all three figures and a theme never subtracts one from
     * another.
     */
    private function completeUntaxedPostage(DeliveryModuleOption $option): void
    {
        if (null !== $option->getPostageUntaxed() || null === $option->getPostage()) {
            return;
        }

        $option->setPostageUntaxed(
            (new OrderPostage($option->getPostage(), $option->getPostageTax()))->getUntaxedAmount(),
        );
    }
}
