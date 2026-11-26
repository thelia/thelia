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

namespace Thelia\Tools;

/**
 * An address a shop is served on, read from a setting, an option or the environment.
 */
final class ShopUrl
{
    /**
     * $url without its trailing slash when it is an absolute http or https address, null otherwise.
     */
    public static function absolute(string $url): ?string
    {
        $url = rtrim(trim($url), '/');
        $scheme = parse_url($url, \PHP_URL_SCHEME);
        $host = parse_url($url, \PHP_URL_HOST);

        if (!\is_string($scheme) || !\in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        return \is_string($host) && '' !== $host ? $url : null;
    }

    /**
     * As absolute(), and null as well when the address names the machine itself (localhost,
     * a loopback IP): `DEFAULT_URI=http://localhost`, the value shipped in `.env`, says
     * nothing about where the shop is served.
     */
    public static function nonLocal(string $url): ?string
    {
        $url = self::absolute($url);

        if (null === $url) {
            return null;
        }

        return self::isLoopbackHost(strtolower(trim((string) parse_url($url, \PHP_URL_HOST), '[]'))) ? null : $url;
    }

    private static function isLoopbackHost(string $host): bool
    {
        if ('localhost' === $host || str_ends_with($host, '.localhost')) {
            return true;
        }

        if (false !== filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4)) {
            return str_starts_with($host, '127.') || '0.0.0.0' === $host;
        }

        if (false !== filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
            return \in_array(inet_pton($host), [inet_pton('::1'), inet_pton('::')], true);
        }

        return false;
    }
}
