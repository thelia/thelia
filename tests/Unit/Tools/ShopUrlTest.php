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

namespace Thelia\Tests\Unit\Tools;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Tools\ShopUrl;

final class ShopUrlTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function absoluteAddresses(): iterable
    {
        yield 'https' => ['https://shop.example', 'https://shop.example'];
        yield 'trailing slash and spaces' => [' https://shop.example/ ', 'https://shop.example'];
        yield 'folder and port' => ['http://shop.example:8080/store/', 'http://shop.example:8080/store'];
        yield 'localhost' => ['http://localhost', 'http://localhost'];
        yield 'no scheme' => ['shop.example', null];
        yield 'other scheme' => ['ftp://shop.example', null];
        yield 'no host' => ['https://', null];
        yield 'empty' => ['', null];
    }

    #[DataProvider('absoluteAddresses')]
    public function testAbsoluteKeepsHttpAddressesOnly(string $url, ?string $expected): void
    {
        self::assertSame($expected, ShopUrl::absolute($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function addressesOfTheMachine(): iterable
    {
        yield 'localhost' => ['http://localhost'];
        yield 'localhost with port' => ['http://localhost:8000/'];
        yield 'localhost subdomain' => ['https://shop.localhost'];
        yield 'IPv4 loopback' => ['http://127.0.0.1'];
        yield 'other IPv4 loopback' => ['http://127.1.2.3:8080'];
        yield 'any IPv4' => ['http://0.0.0.0'];
        yield 'IPv6 loopback' => ['http://[::1]:8000'];
    }

    #[DataProvider('addressesOfTheMachine')]
    public function testNonLocalRefusesTheAddressesOfTheMachine(string $url): void
    {
        self::assertNull(ShopUrl::nonLocal($url));
    }

    public function testNonLocalKeepsAnAddressServedElsewhere(): void
    {
        self::assertSame('https://shop.example/store', ShopUrl::nonLocal('https://shop.example/store/'));
        self::assertSame('http://192.0.2.10', ShopUrl::nonLocal('http://192.0.2.10'));
        self::assertNull(ShopUrl::nonLocal('shop.example'));
    }
}
