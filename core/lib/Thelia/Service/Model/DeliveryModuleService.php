<?php

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

use OpenApi\Events\DeliveryModuleOptionEvent;
use OpenApi\Events\OpenApiEvents;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Exception\PropelException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Api\Bridge\Propel\Service\Resource\DeliveryModuleApiService;
use Thelia\Api\Resource\DeliveryModule;
use Thelia\Api\Resource\DeliveryModuleOption;
use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Address;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\State;
use Thelia\Module\BaseModule;
use Thelia\Module\Exception\DeliveryException;

readonly class DeliveryModuleService
{
    public function getDeliveryModules(): array|ObjectCollection {
        $moduleQuery = ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE);
        return $moduleQuery->find();
    }

}
