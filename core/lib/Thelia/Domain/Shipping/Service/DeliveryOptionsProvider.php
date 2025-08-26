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
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Address;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Module;
use Thelia\Model\State;

final readonly class DeliveryOptionsProvider
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    public function getOptions(Module $module, ?Address $address, Cart $cart, Country $country, ?State $state): array
    {
        $event = new DeliveryModuleOptionEvent($module, $address, $cart, $country, $state);

        $this->dispatcher->dispatch($event, TheliaEvents::MODULE_DELIVERY_GET_OPTIONS);

        return $event->getDeliveryModuleOptions();
    }
}
