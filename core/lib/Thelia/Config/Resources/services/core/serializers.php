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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Thelia\Core\Serializer\Serializer\CSVSerializer;
use Thelia\Core\Serializer\Serializer\JSONSerializer;
use Thelia\Core\Serializer\Serializer\XMLSerializer;
use Thelia\Core\Serializer\SerializerManager;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->set(SerializerManager::class)
        ->public();

    $services->alias('thelia.serializer.manager', SerializerManager::class)
        ->public();

    $services->alias('thelia.serializer.csv', CSVSerializer::class);

    $services->alias('thelia.serializer.xml', XMLSerializer::class);

    $services->alias('thelia.serializer.json', JSONSerializer::class);
};
