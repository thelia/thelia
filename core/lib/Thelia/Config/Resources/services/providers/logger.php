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

use Thelia\Log\Tlog;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();
    $parameters = $configurator->parameters();

    // Logger
    $services->set('thelia.logger', '%thelia.logger.class%')
        ->factory([param('thelia.logger.class'), 'getInstance']);

    $parameters->set('thelia.logger.class', Tlog::class);
    $parameters->set('thelia.cache.namespace', 'thelia_cache');
};
