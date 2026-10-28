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
        // Seconds a provider may stay silent before the call is given up: a model thinks for a while before its first token.
        ->set('thelia.ai.http_timeout', 60)
        // Seconds a whole call may last, a streamed answer included.
        ->set('thelia.ai.http_max_duration', 300);
};
