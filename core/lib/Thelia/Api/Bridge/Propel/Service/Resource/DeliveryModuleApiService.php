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
namespace Thelia\Api\Bridge\Propel\Service\Resource;

use Exception;
use Propel\Runtime\Exception\PropelException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Api\Bridge\Propel\Event\DeliveryModuleOptionEvent;
use Thelia\Api\Resource\DeliveryModule;
use Thelia\Api\Resource\ModuleI18n;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Address;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Module;
use Thelia\Model\State;
use Thelia\Module\Exception\DeliveryException;

class DeliveryModuleApiService
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * @throws PropelException
     * @throws Exception
     */
    public function getDeliveryModuleResource(
        Module $theliaDeliveryModule,
        Cart $cart,
        ?Address $address,
        Country $country,
        ?State $state
    ): DeliveryModule {
        $areaDeliveryModule = AreaDeliveryModuleQuery::create()
            ->findByCountryAndModule($country, $theliaDeliveryModule, $state);
        $isCartVirtual = $cart->isVirtual();

        $isValid = true;
        if (false === $isCartVirtual && null === $areaDeliveryModule) {
            $isValid = false;
        }

        $moduleInstance = $theliaDeliveryModule->getDeliveryModuleInstance($this->container);

        if (true === $isCartVirtual && false === $moduleInstance->handleVirtualProductDelivery()) {
            $isValid = false;
        }

        $deliveryPostageEvent = new DeliveryPostageEvent($moduleInstance, $cart, $address, $country, $state);
        try {
            $this->dispatcher->dispatch(
                $deliveryPostageEvent,
                TheliaEvents::MODULE_DELIVERY_GET_POSTAGE
            );
        } catch (DeliveryException) {
            $isValid = false;
        }

        if (!$deliveryPostageEvent->isValidModule()) {
            $isValid = false;
        }

        $deliveryModuleOptionEvent = new DeliveryModuleOptionEvent($theliaDeliveryModule, $address, $cart, $country, $state);

        $this->dispatcher->dispatch(
            $deliveryModuleOptionEvent,
            TheliaEvents::MODULE_DELIVERY_GET_OPTIONS
        );

        $deliveryModuleApi = (new DeliveryModule())
            ->setId($theliaDeliveryModule->getId())
            ->setCode($theliaDeliveryModule->getCode())
            ->setValid($isValid)
            ->setDeliveryMode($deliveryPostageEvent->getDeliveryMode())
            ->setPosition($theliaDeliveryModule->getPosition())
            ->setOptions($deliveryModuleOptionEvent->getDeliveryModuleOptions());
        foreach ($theliaDeliveryModule->getModuleI18ns() as $i18n) {
            $deliveryModuleApi->addI18n(new ModuleI18n($i18n->toArray()), $i18n->getLocale());
        }

        return $deliveryModuleApi;
    }
}
