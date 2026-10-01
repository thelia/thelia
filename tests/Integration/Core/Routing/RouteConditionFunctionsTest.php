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

namespace Thelia\Tests\Integration\Core\Routing;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Thelia\Core\TheliaKernel;
use Thelia\Tests\Support\Kernel\RoutingConditionKernel;
use Thelia\Tests\Support\Routing\RoutingConditionProbe;

/**
 * Thelia matches a request through its own router chain and request context,
 * which the chain hands to every router it holds. A route condition calling
 * service() or env() reads those functions from that context: without them the
 * condition crashed and the page answered a 500.
 */
final class RouteConditionFunctionsTest extends WebTestCase
{
    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return RoutingConditionKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();

        if (!TheliaKernel::isInstalled()) {
            $this->markTestSkipped('Test database not available. Run: php bin/test-prepare');
        }
    }

    public function testARouteConditionCallingAServiceLetsTheRequestThrough(): void
    {
        $this->client->request('GET', RoutingConditionKernel::ROUTE_PATH.'?allowed=1');

        $response = $this->client->getResponse();

        self::assertSame(200, $response->getStatusCode(), (string) mb_substr((string) $response->getContent(), 0, 2000));
        self::assertSame(RoutingConditionProbe::BODY, $response->getContent());
    }

    public function testARouteConditionCallingAServiceTurnsTheRequestAway(): void
    {
        $this->client->request('GET', RoutingConditionKernel::ROUTE_PATH);

        $response = $this->client->getResponse();

        self::assertSame(404, $response->getStatusCode(), (string) mb_substr((string) $response->getContent(), 0, 2000));
    }
}
