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

use Thelia\Model\BrandDocument;
use Thelia\Model\BrandImage;
use Thelia\Model\CategoryDocument;
use Thelia\Model\CategoryImage;
use Thelia\Model\ContentDocument;
use Thelia\Model\ContentImage;
use Thelia\Model\FolderDocument;
use Thelia\Model\FolderImage;
use Thelia\Model\ModuleImage;
use Thelia\Model\ProductDocument;
use Thelia\Model\ProductImage;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('file_model.classes', [
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
};
