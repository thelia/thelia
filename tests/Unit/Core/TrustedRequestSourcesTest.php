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

namespace Thelia\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Thelia\Tests\Support\Kernel\TrustedRequestSourcesKernel;

/**
 * framework.trusted_hosts and framework.trusted_proxies reach the container as the
 * shapes FrameworkExtension produces: a scalar for a single value, and a list of
 * header names for the trusted headers. The kernel has to translate them into what
 * Request accepts, or booting fails on a TypeError.
 */
final class TrustedRequestSourcesTest extends TestCase
{
    /** @var array<int, string> */
    private array $trustedProxies;

    private int $trustedHeaderSet;

    protected function setUp(): void
    {
        $this->trustedProxies = Request::getTrustedProxies();
        $this->trustedHeaderSet = Request::getTrustedHeaderSet();
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies($this->trustedProxies, $this->trustedHeaderSet);
        Request::setTrustedHosts([]);
    }

    public function testASingleTrustedProxyIsAccepted(): void
    {
        TrustedRequestSourcesKernel::applyTo($this->containerWith([
            'kernel.trusted_proxies' => '10.0.0.1',
            'kernel.trusted_headers' => ['x-forwarded-for', 'x-forwarded-proto'],
        ]));

        self::assertSame(['10.0.0.1'], Request::getTrustedProxies());
        self::assertSame(
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
            Request::getTrustedHeaderSet(),
        );
    }

    public function testTrustedProxiesFallBackToTheDefaultHeaderSet(): void
    {
        TrustedRequestSourcesKernel::applyTo($this->containerWith([
            'kernel.trusted_proxies' => ['10.0.0.1', '10.0.0.2'],
            'kernel.trusted_headers' => null,
        ]));

        self::assertSame(['10.0.0.1', '10.0.0.2'], Request::getTrustedProxies());
        self::assertSame(
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
            Request::getTrustedHeaderSet(),
        );
    }

    public function testAProxyTerminatingTlsIsSeenAsAnHttpsRequest(): void
    {
        TrustedRequestSourcesKernel::applyTo($this->containerWith([
            'kernel.trusted_proxies' => '10.0.0.1',
            'kernel.trusted_headers' => ['x-forwarded-for', 'x-forwarded-proto'],
        ]));

        $request = Request::create('http://shop.example.com/admin/login', 'GET', [], [], [], [
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        self::assertSame('https', $request->getScheme());
    }

    public function testAnUnsupportedTrustedHeaderIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The trusted header "x-real-ip" is not supported.');

        TrustedRequestSourcesKernel::applyTo($this->containerWith([
            'kernel.trusted_proxies' => '10.0.0.1',
            'kernel.trusted_headers' => ['x-real-ip'],
        ]));
    }

    public function testASingleTrustedHostIsAccepted(): void
    {
        TrustedRequestSourcesKernel::applyTo($this->containerWith([
            'kernel.trusted_hosts' => 'shop.example.com',
        ]));

        self::assertSame(['{shop.example.com}i'], Request::getTrustedHosts());
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function containerWith(array $parameters): ContainerInterface
    {
        $container = new Container();

        foreach ($parameters as $name => $value) {
            $container->setParameter($name, $value);
        }

        return $container;
    }
}
