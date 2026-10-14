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

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Thelia\Domain\Webhook\WebhookHttpClientFactory;

return static function (ContainerConfigurator $configurator): void {
    $configurator->services()
        ->set('thelia.webhook.http_client', HttpClientInterface::class)
        ->factory([WebhookHttpClientFactory::class, 'create'])
        ->public();
};
