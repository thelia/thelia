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

namespace Thelia\Service\Model;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Event\DeliveryModuleOptionEvent;
use Thelia\Api\Resource\DeliveryModule;
use Thelia\Api\Resource\ModuleI18n;
use Thelia\Api\State\Collection\DeliveryModuleCollection;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Address;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Module;
use Thelia\Model\State;
use Thelia\Module\Exception\DeliveryException;
use TwigEngine\Service\DataAccess\DataAccessService;

readonly class DeliveryModuleService
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private ContainerInterface       $container,
        private DataAccessService        $dataAccessService
    )
    {
    }

    public function setDeliveryPart()
    {

    }

    /**
     * @throws PropelException
     */
    public function getDeliveryModuleResource(
        Module   $theliaDeliveryModule,
        Cart     $cart,
        ?Address $address,
        Country  $country,
        ?State   $state,
        bool     $onlyValid = false
    ): ?DeliveryModule
    {
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

        if (!$isValid && $onlyValid) {
            return null;
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

    public function getValidDeliveryModuleCollection(): DeliveryModuleCollection
    {
        return $this->getDeliveryModuleCollection();
    }

    public function getAllDeliveryModuleCollection(): DeliveryModuleCollection
    {
        return $this->getDeliveryModuleCollection(false);
    }

    protected function getDeliveryModuleCollection(bool $onlyValid = true): DeliveryModuleCollection
    {
        $modules = $this->dataAccessService->resources(
            '/api/front/delivery_modules',
            ['only_valid' => $onlyValid ? 1 : 0]
        ) ?? [];

        return new DeliveryModuleCollection($modules);
    }
}
