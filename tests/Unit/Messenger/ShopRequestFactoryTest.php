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

namespace Thelia\Tests\Unit\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RequestContext;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Messenger\ShopRequestFactory;

final class ShopRequestFactoryTest extends TestCase
{
    public function testTheRequestKeepsTheFolderOfAShopInstalledBelowTheRootOfItsSite(): void
    {
        $request = (new ShopRequestFactory(new RequestContext('/shop', 'GET', 'example.com', 'https', 80, 8443)))->create();

        self::assertSame('https://example.com:8443', $request->getSchemeAndHttpHost());
        self::assertSame('/shop', $request->getBaseUrl());
    }

    public function testTheRequestOfAShopAtTheRootOfItsSiteHasNoFolderNorDefaultPort(): void
    {
        $request = (new ShopRequestFactory(new RequestContext('', 'GET', 'example.com', 'http')))->create();

        self::assertSame('http://example.com', $request->getSchemeAndHttpHost());
        self::assertSame('', $request->getBaseUrl());
    }

    public function testTheRequestComesWithAnEmptySessionOfItsOwn(): void
    {
        $factory = new ShopRequestFactory(new RequestContext('', 'GET', 'example.com'));
        $first = $factory->create();
        $second = $factory->create();

        self::assertInstanceOf(Session::class, $first->getSession());
        self::assertSame([], $first->getSession()->all());
        self::assertNotSame($first->getSession(), $second->getSession(), 'No work starts on the session of another.');
    }
}
