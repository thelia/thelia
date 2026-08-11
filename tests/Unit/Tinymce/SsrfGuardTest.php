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

namespace Thelia\Tests\Unit\Tinymce;

use PHPUnit\Framework\TestCase;

final class SsrfGuardTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once \dirname(__DIR__, 3)
            .'/local/modules/Tinymce/Resources/js/tinymce/filemanager/include/ssrf_guard.php';
    }

    /**
     * @dataProvider blockedIpProvider
     */
    public function testInternalAddressesAreBlocked(string $ip): void
    {
        self::assertTrue(thelia_ip_is_blocked($ip));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blockedIpProvider(): iterable
    {
        yield 'loopback' => ['127.0.0.1'];
        yield 'private class A' => ['10.0.0.5'];
        yield 'private class B' => ['172.16.0.1'];
        yield 'private class C' => ['192.168.1.1'];
        yield 'cloud metadata' => ['169.254.169.254'];
        yield 'cgnat' => ['100.64.0.1'];
        yield 'ipv6 loopback' => ['::1'];
        yield 'ipv6 unique local' => ['fc00::1'];
        yield 'ipv6 mapped loopback' => ['::ffff:127.0.0.1'];
    }

    public function testPublicAddressIsAllowed(): void
    {
        self::assertFalse(thelia_ip_is_blocked('8.8.8.8'));
    }

    /**
     * @dataProvider rejectedUrlProvider
     */
    public function testDangerousUrlsAreRejected(string $url): void
    {
        self::assertNull(thelia_remote_url_target($url));
        self::assertFalse(thelia_fetch_remote_file($url));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedUrlProvider(): iterable
    {
        yield 'file scheme' => ['file:///etc/passwd'];
        yield 'gopher scheme' => ['gopher://127.0.0.1:6379/_INFO'];
        yield 'php wrapper' => ['php://filter/convert.base64-encode/resource=index.php'];
        yield 'loopback host' => ['http://127.0.0.1/'];
        yield 'cloud metadata host' => ['http://169.254.169.254/latest/meta-data/'];
        yield 'private host' => ['http://10.0.0.5:8080/admin'];
        yield 'ipv6 loopback host' => ['http://[::1]:80/'];
    }

    public function testPublicHttpUrlIsAccepted(): void
    {
        $target = thelia_remote_url_target('http://8.8.8.8/robots.txt');

        self::assertIsArray($target);
        self::assertSame('8.8.8.8', $target['ip']);
        self::assertSame(80, $target['port']);
    }
}
