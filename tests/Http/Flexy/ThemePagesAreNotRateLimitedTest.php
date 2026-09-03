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

namespace Thelia\Tests\Http\Flexy;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Test\WebIntegrationTestCase;

/**
 * A shop under load must not turn its own visitors away.
 *
 * The theme reads its data through the API, and the limit that protects the API
 * is set for one caller — so if those reads were counted, the shop's own pages
 * would be the first thing to hit the ceiling on the day the shop is busiest,
 * and the busier it got the harder it would refuse.
 *
 * They are not counted, because the theme reads in process: it asks the state
 * providers directly instead of calling itself over HTTP. This test pins that
 * down with the anonymous budget set to one, so a single page view would be
 * enough to exhaust it if those reads went through the limiter.
 */
final class ThemePagesAreNotRateLimitedTest extends WebIntegrationTestCase
{
    private const int PAGE_VIEWS = 12;

    private const string VISITOR = '192.0.2.60';

    /** @var array<string, string|null> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        $this->setLimit('THELIA_API_RATE_LIMIT_ANONYMOUS', '1');

        parent::setUp();

        $pool = static::getContainer()->get('cache.rate_limiter');

        if ($pool instanceof CacheItemPoolInterface) {
            $pool->clear();
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $name => $value) {
            if (null === $value) {
                unset($_ENV[$name], $_SERVER[$name]);
                continue;
            }

            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        $this->envBackup = [];

        parent::tearDown();
    }

    public function testTheHomepageKeepsRenderingWellPastTheAnonymousBudget(): void
    {
        for ($view = 1; $view <= self::PAGE_VIEWS; ++$view) {
            $response = $this->view('/');

            // A plain 200 is the assertion: the page rendered, so it read the
            // catalogue it renders from, and it read it without being counted.
            self::assertSame(
                Response::HTTP_OK,
                $response->getStatusCode(),
                \sprintf('View %d of the homepage did not render.', $view),
            );
            self::assertNotSame('', (string) $response->getContent());
        }
    }

    private function view(string $uri): Response
    {
        $this->client->request('GET', $uri, server: ['REMOTE_ADDR' => self::VISITOR]);

        return $this->client->getResponse();
    }

    private function setLimit(string $name, string $value): void
    {
        $this->envBackup[$name] ??= $_SERVER[$name] ?? null;
        $_ENV[$name] = $_SERVER[$name] = $value;
    }
}
