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

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        // The hosts the MCP endpoint answers for (DNS rebinding protection), comma separated and without
        // port: THELIA_MCP_ALLOWED_HOSTS=shop.example.com,www.example.com. Only localhost until the shop sets it.
        ->set('env(THELIA_MCP_ALLOWED_HOSTS)', 'localhost');
};
