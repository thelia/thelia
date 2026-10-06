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
        // The queue of the heavy jobs, when it must not be derived from
        // MESSENGER_TRANSPORT_DSN (see HeavyTransportDsnProcessor).
        ->set('env(MESSENGER_HEAVY_TRANSPORT_DSN)', '')
        // Message classes, outside the core and the active modules, that a
        // project lets through its queues (see AllowedClassesSerializer).
        ->set('thelia.messenger.allowed_message_classes', [])
        // The recurring tasks of the core (see TheliaSchedule), as cron
        // expressions read when a worker consumes scheduler_thelia. Empty leaves
        // the task out. The currency rates are left out by default: the update
        // overwrites every rate, the ones set by hand included.
        ->set('env(THELIA_SCHEDULE_SALE_CHECK)', '* * * * *')
        ->set('env(THELIA_SCHEDULE_MAINTENANCE_PURGE)', '30 3 * * *')
        ->set('env(THELIA_SCHEDULE_FAILED_JOBS_PURGE)', '0 4 * * *')
        ->set('env(THELIA_SCHEDULE_CURRENCY_RATES)', '');
};
