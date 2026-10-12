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
use Symfony\Component\HttpFoundation\Request;
use Thelia\Tests\Support\Kernel\TrustedRequestSourcesKernel;

/**
 * A list of trusted proxies written as one comma-separated string, as an
 * environment variable holds it, reaches Request one address per entry; the
 * proxies are only trusted once the container also says which headers to read.
 */
final class TheliaKernelTrustedProxiesTest extends TestCase
{
    /** @var array<int, string> */
    private array $trustedProxies;

    private int $trustedHeaderSet;

    protected function setUp(): void
    {
        $this->trustedProxies = Request::getTrustedProxies();
        $this->trustedHeaderSet = Request::getTrustedHeaderSet();
        Request::setTrustedProxies([], -1);
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies($this->trustedProxies, $this->trustedHeaderSet);
    }

    public function testSpacesAroundCommaSeparatedProxiesAreDropped(): void
    {
        $container = new Container();
        $container->setParameter('kernel.trusted_proxies', '10.0.0.1, 10.0.0.2');
        $container->setParameter('kernel.trusted_headers', ['x-forwarded-for']);

        TrustedRequestSourcesKernel::applyTo($container);

        self::assertSame(['10.0.0.1', '10.0.0.2'], Request::getTrustedProxies());
    }

    public function testProxiesWithoutTrustedHeadersAreNotTrusted(): void
    {
        $container = new Container();
        $container->setParameter('kernel.trusted_proxies', '10.0.0.1');

        TrustedRequestSourcesKernel::applyTo($container);

        self::assertSame([], Request::getTrustedProxies());
    }
}
