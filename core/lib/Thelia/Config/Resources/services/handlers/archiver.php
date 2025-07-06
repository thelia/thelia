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

use Thelia\Core\Archiver\Archiver\TarArchiver;
use Thelia\Core\Archiver\Archiver\TarBz2Archiver;
use Thelia\Core\Archiver\Archiver\TarGzArchiver;
use Thelia\Core\Archiver\Archiver\ZipArchiver;
use Thelia\Core\Archiver\ArchiverManager;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->set(ArchiverManager::class)
        ->public();

    $services->alias('thelia.archiver.manager', ArchiverManager::class)
        ->public();

    $services->alias('thelia.archiver.zip', ZipArchiver::class);

    $services->alias('thelia.archiver.tar', TarArchiver::class);

    $services->alias('thelia.archiver.tgz', TarGzArchiver::class);

    $services->alias('thelia.archiver.bz2', TarBz2Archiver::class);
};
