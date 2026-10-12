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

namespace Thelia\Tests\Unit\Core\HttpFoundation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\ConfigQuery;

/**
 * The Thelia request answers `/admin/` as it answers `/admin` when the shop allows slash-ended
 * URIs, and a request taken over from the Symfony one keeps what the kernel set on it.
 */
final class RequestTest extends TestCase
{
    protected function tearDown(): void
    {
        ConfigQuery::resetCache();
    }

    public function testATrailingSlashIsDroppedWhenTheShopAllowsSlashEndedUris(): void
    {
        ConfigQuery::initCache(['allow_slash_ended_uri' => '1']);

        $request = Request::create('/admin/');

        self::assertSame('/admin', $request->getPathInfo());
        self::assertSame('/admin/', $request->getRealPathInfo());
    }

    public function testOnlyTheLastSlashOfAPathIsDropped(): void
    {
        ConfigQuery::initCache(['allow_slash_ended_uri' => '1']);

        self::assertSame('/catalog/shoes', Request::create('/catalog/shoes/')->getPathInfo());
        self::assertSame('/catalog/shoes', Request::create('/catalog/shoes')->getPathInfo());
    }

    public function testTheRootPathIsKeptWhenTheShopAllowsSlashEndedUris(): void
    {
        ConfigQuery::initCache(['allow_slash_ended_uri' => '1']);

        self::assertSame('/', Request::create('/')->getPathInfo());
    }

    public function testATrailingSlashIsKeptWhenTheShopDoesNotAllowSlashEndedUris(): void
    {
        ConfigQuery::initCache(['allow_slash_ended_uri' => '0']);

        self::assertSame('/admin/', Request::create('/admin/')->getPathInfo());
    }

    public function testATrailingSlashIsKeptWhenTheShopNeverSaidAnythingAboutSlashEndedUris(): void
    {
        ConfigQuery::initCache([]);

        self::assertSame('/admin/', Request::create('/admin/')->getPathInfo());
    }

    public function testARequestTakenOverFromTheSymfonyOneKeepsItsMethodLocaleAndFormat(): void
    {
        $base = SymfonyRequest::create('/api/products/3', 'GET', ['page' => '2'], [], [], [], '{"title":"Chair"}');
        $base->setMethod('PUT');
        $base->setLocale('fr_FR');
        $base->setRequestFormat('json');

        $request = Request::createFromBase($base);

        self::assertSame('PUT', $request->getMethod());
        self::assertSame('fr_FR', $request->getLocale());
        self::assertSame('json', $request->getRequestFormat());
        self::assertSame('2', $request->query->get('page'));
        self::assertSame('{"title":"Chair"}', $request->getContent());
    }
}
