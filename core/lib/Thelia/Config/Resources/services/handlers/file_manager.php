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

use Thelia\Core\File\FileManager;
use Thelia\Core\File\Service\FileDeleteService;
use Thelia\Core\File\Service\FilePositionService;
use Thelia\Core\File\Service\FileProcessorService;
use Thelia\Core\File\Service\FileUpdateService;
use Thelia\Core\File\Service\FileVisibilityService;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();
    // Register file services
    $services->set(FileProcessorService::class)
        ->args([
            service('thelia.file_manager'),
            service('translator'),
            service('thelia.admin.resources'),
        ])
        ->public();

    $services->set(FileUpdateService::class)
        ->public();

    $services->set(FileDeleteService::class)
        ->args([
            service('thelia.file_manager'),
            service('translator'),
            service('thelia.admin.resources'),
        ])
        ->public();

    $services->set(FilePositionService::class)
        ->args([
            service('thelia.file_manager'),
            service('translator'),
            service('thelia.admin.resources'),
        ])
        ->public();

    $services->set(FileVisibilityService::class)
        ->args([
            service('thelia.file_manager'),
            service('translator'),
            service('thelia.admin.resources'),
        ])
        ->public();

    $services->set(FileManager::class)->public();

    // Create aliases for services
    $services->alias('thelia.file.processor', FileProcessorService::class);
    $services->alias('thelia.file.update', FileUpdateService::class);
    $services->alias('thelia.file.delete', FileDeleteService::class);
    $services->alias('thelia.file.position', FilePositionService::class);
    $services->alias('thelia.file.visibility', FileVisibilityService::class);
};
