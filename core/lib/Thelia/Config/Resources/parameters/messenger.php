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
        // Where the background jobs go when MESSENGER_TRANSPORT_DSN is empty or
        // missing: nowhere. They run at once, in the request or the command that
        // dispatched them, as everything did before the shop had a queue. A shop
        // that runs no worker therefore never leaves a job waiting for one.
        ->set('thelia.messenger.inline_transport_dsn', 'sync://')
        // Where a job lands once it has failed every attempt. In the shop
        // database by default, whatever MESSENGER_TRANSPORT_DSN names, so the
        // back office has one place to read the failures from.
        ->set('env(MESSENGER_FAILURE_TRANSPORT_DSN)', 'doctrine://default?queue_name=failed')
        // Message classes, outside the core and the active modules, that a
        // project lets through its queues (see AllowedClassesSerializer).
        ->set('thelia.messenger.allowed_message_classes', []);
};
