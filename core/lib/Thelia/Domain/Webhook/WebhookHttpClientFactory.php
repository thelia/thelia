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

namespace Thelia\Domain\Webhook;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WebhookHttpClientFactory
{
    public const array OPTIONS = [
        'max_redirects' => 0,
        'timeout' => 5,
        'max_duration' => 10,
    ];

    public static function create(?array $privateSubnets = null): HttpClientInterface
    {
        return (new NoPrivateNetworkHttpClient(HttpClient::create(), $privateSubnets ?? [...IpUtils::PRIVATE_SUBNETS, ...WebhookUrlValidator::NON_ROUTABLE]))->withOptions(self::OPTIONS);
    }
}
