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
use Thelia\Tools\RedirectUrl;

final class RedirectUrlTest extends TestCase
{
    #[DataProvider('safeUrls')]
    public function testAcceptsUrlsThatStayOnTheShop(string $url): void
    {
        self::assertTrue(RedirectUrl::isSafe($url, 'shop.example.com'));
    }

    #[DataProvider('unsafeUrls')]
    public function testRejectsUrlsThatLeaveTheShop(string $url): void
    {
        self::assertFalse(RedirectUrl::isSafe($url, 'shop.example.com'));
    }

    public static function safeUrls(): iterable
    {
        yield 'relative path' => ['/checkout/delivery'];
        yield 'relative path with query' => ['/category/wine?page=2'];
        yield 'path without leading slash' => ['account'];
        yield 'absolute url on the same host' => ['https://shop.example.com/cart'];
        yield 'same host, other scheme' => ['http://shop.example.com/cart'];
        yield 'same host, different case' => ['https://SHOP.example.com/cart'];
        yield 'surrounded by spaces' => ['  /cart  '];
    }

    public static function unsafeUrls(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'another host' => ['https://evil.example.net/pwn'];
        yield 'protocol relative' => ['//evil.example.net/pwn'];
        yield 'backslash trick' => ['/\\evil.example.net'];
        yield 'javascript scheme' => ['javascript:alert(1)'];
        yield 'data scheme' => ['data:text/html,<script>alert(1)</script>'];
        yield 'scheme without slashes' => ['https:evil.example.net'];
        yield 'host lookalike' => ['https://shop.example.com.evil.net/cart'];
    }
}
