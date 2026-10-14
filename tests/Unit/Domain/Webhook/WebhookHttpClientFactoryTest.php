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

namespace Thelia\Tests\Unit\Domain\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Thelia\Domain\Webhook\WebhookHttpClientFactory;

final class WebhookHttpClientFactoryTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function privateTargets(): iterable
    {
        yield 'loopback address' => ['http://127.0.0.1:9/hook'];
        yield 'name that resolves to loopback' => ['http://localhost:9/hook'];
    }

    #[DataProvider('privateTargets')]
    public function testTheClientRefusesToConnectToAPrivateNetwork(string $url): void
    {
        $client = WebhookHttpClientFactory::create();

        $this->expectException(TransportExceptionInterface::class);
        $this->expectExceptionMessageMatches('/blocked/i');

        $client->request('POST', $url, ['body' => '{}'])->getStatusCode();
    }
}
