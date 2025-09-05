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

use Thelia\Domain\DataTransfer\ExportHandler;
use Thelia\Domain\DataTransfer\ImportHandler;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();
    $parameters = $configurator->parameters();

    $services->alias('thelia.export.handler', ExportHandler::class)
        ->public();

    $services->alias('thelia.import.handler', ImportHandler::class)
        ->public();

    $parameters->set('import.base_url', '/admin/import');
    $parameters->set('export.base_url', '/admin/export');
};
