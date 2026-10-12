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

namespace Thelia\Tests\Support\Delivery;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Domain\Shipping\DeliveryDate\Enum\DeliveryDateChoiceMode;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\Country;
use Thelia\Model\CountryArea;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

/**
 * Installs DeliveryDateTestCarrier for a test: its `module` row, written on the connection
 * the test runs in, and its instance under the service id the core resolves a delivery
 * module by (`module.<code>`), which the compiled container does not know.
 */
trait RegistersDeliveryDateTestCarrier
{
    protected function registerDeliveryDateTestCarrier(ContainerInterface $container, ConnectionInterface $connection): Module
    {
        DeliveryDateTestCarrier::$acceptedModes = [DeliveryDateChoiceMode::Date, DeliveryDateChoiceMode::Slot];

        $serviceId = 'module.'.DeliveryDateTestCarrier::getModuleCode();

        if (!$container->has($serviceId)) {
            $container->set($serviceId, new DeliveryDateTestCarrier());
        }

        $module = ModuleQuery::create()->findOneByCode(DeliveryDateTestCarrier::getModuleCode(), $connection);

        if (null === $module) {
            $module = (new Module())
                ->setCode(DeliveryDateTestCarrier::getModuleCode())
                ->setFullNamespace(DeliveryDateTestCarrier::class)
                ->setVersion('1.0.0')
                ->setType(BaseModule::DELIVERY_MODULE_TYPE)
                ->setCategory('delivery')
                ->setActivate(BaseModule::IS_ACTIVATED);
            $module->save($connection);
        }

        return $module;
    }

    protected function serveCountryWithCarrier(Module $module, Country $country, ConnectionInterface $connection): void
    {
        $area = (new Area())->setName('Delivery date test area '.uniqid());
        $area->save($connection);
        (new CountryArea())->setAreaId($area->getId())->setCountryId($country->getId())->save($connection);
        (new AreaDeliveryModule())->setAreaId($area->getId())->setDeliveryModuleId($module->getId())->save($connection);
    }
}
