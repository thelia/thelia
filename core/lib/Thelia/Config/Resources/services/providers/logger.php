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

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    // Logger. The class is a parameter so a project can substitute its own;
    // it is declared once, in parameters/thelia_core.php.
    $services->set('thelia.logger', '%thelia.logger.class%')
        ->factory([param('thelia.logger.class'), 'getInstance']);
};
