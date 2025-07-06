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

use Thelia\Files\Service\FileDeleteService;
use Thelia\Files\Service\FilePositionService;
use Thelia\Files\Service\FileProcessorService;
use Thelia\Files\Service\FileUpdateService;
use Thelia\Files\Service\FileVisibilityService;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductImage;
use Thelia\Model\CategoryDocument;
use Thelia\Model\CategoryImage;
use Thelia\Model\ContentDocument;
use Thelia\Model\ContentImage;
use Thelia\Model\FolderDocument;
use Thelia\Model\FolderImage;
use Thelia\Model\BrandDocument;
use Thelia\Model\BrandImage;
use Thelia\Model\ModuleImage;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();
    $parameters = $configurator->parameters();

    // Liste des classes de modèles qui supportent la gestion d'images ou de documents
    $parameters->set('file_model.classes', [
        'document.product' => ProductDocument::class,
        'image.product' => ProductImage::class,
        'document.category' => CategoryDocument::class,
        'image.category' => CategoryImage::class,
        'document.content' => ContentDocument::class,
        'image.content' => ContentImage::class,
        'document.folder' => FolderDocument::class,
        'image.folder' => FolderImage::class,
        'document.brand' => BrandDocument::class,
        'image.brand' => BrandImage::class,
        'image.module' => ModuleImage::class,
    ]);


    // Register file services
    $services->set(FileProcessorService::class)
        ->args([
            service('thelia.file_manager'),
            service('translator'),
            service('thelia.admin.resources'),
        ])
        ->public();

    $services->set(FileUpdateService::class)
        ->factory([service('request_stack'), 'getCurrentRequest'])
        ->args([
            service('thelia.file_manager'),
            service('translator'),
        ])
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

    // Create aliases for services
    $services->alias('thelia.file.processor', FileProcessorService::class);
    $services->alias('thelia.file.update', FileUpdateService::class);
    $services->alias('thelia.file.delete', FileDeleteService::class);
    $services->alias('thelia.file.position', FilePositionService::class);
    $services->alias('thelia.file.visibility', FileVisibilityService::class);
};
