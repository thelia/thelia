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

use Thelia\Api\Bridge\Propel\MetaData\PropelResourceCollectionMetadataFactory;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    // API Platform integration
    $services->set('thelia.api.propel.resource.metadata_collection_factory', PropelResourceCollectionMetadataFactory::class)
        ->decorate('api_platform.metadata.resource.metadata_collection_factory', null, 40)
        ->args([service('thelia.api.propel.resource.metadata_collection_factory.inner')]);
};
