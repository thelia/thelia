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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Shipping\ProductRule\ProductShippingRuleQuoter;
use Thelia\Log\Tlog;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Module;
use Thelia\Model\OrderPostage;
use Thelia\Model\State;
use Thelia\Module\Exception\DeliveryException;

final readonly class DeliveryPostageQuerier
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private ContainerInterface $container,
        private ProductShippingRuleQuoter $productShippingRuleQuoter,
    ) {
    }

    /**
     * @return array{postage: OrderPostage|null, deliveryMode: ?string, valid: bool}
     */
    public function query(Module $module, Cart $cart, ?Address $address, Country $country, ?State $state): array
    {
        $moduleInstance = $module->getDeliveryModuleInstance($this->container);

        $deliveryPostageEvent = null;

        try {
            $postage = $this->productShippingRuleQuoter->quote(
                $module,
                $cart,
                $country,
                $state,
                function (Cart $quotedCart) use ($moduleInstance, $address, $country, $state, &$deliveryPostageEvent): ?OrderPostage {
                    $deliveryPostageEvent = new DeliveryPostageEvent($moduleInstance, $quotedCart, $address, $country, $state);
                    $this->dispatcher->dispatch($deliveryPostageEvent, TheliaEvents::MODULE_DELIVERY_GET_POSTAGE);

                    return $deliveryPostageEvent->isValidModule() && $deliveryPostageEvent->getPostage() instanceof OrderPostage
                        ? $deliveryPostageEvent->getPostage()
                        : null;
                },
            );
        } catch (DeliveryException $e) {
            Tlog::getInstance()->error(\sprintf('Delivery module %s is not available: %s', $module->getCode(), $e->getMessage()));

            return [
                'postage' => null,
                'deliveryMode' => null,
                'valid' => false,
            ];
        }
        $valid = $deliveryPostageEvent instanceof DeliveryPostageEvent && $deliveryPostageEvent->isValidModule();
        $postage = $valid ? $postage : null;
        $deliveryMode = $deliveryPostageEvent?->getDeliveryMode()?->value;

        return [
            'postage' => $postage,
            'deliveryMode' => $deliveryMode,
            'valid' => $valid,
        ];
    }
}
