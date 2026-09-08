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
 * Decides whether a URL a visitor handed over may be redirected to.
 *
 * Form fields (success_url, error_url) and query parameters that say where to go
 * next are written by whoever sends the request, so they are checked here before
 * a Location header is built out of them (CWE-601).
 */
final class RedirectUrl
{
    /**
     * A redirection URL is safe when it is a relative path, or an absolute http(s)
     * URL whose host is the one being browsed. Protocol-relative URLs, backslash
     * tricks and non-http schemes (javascript:, data:, file:, ...) are rejected.
     */
    public static function isSafe(string $url, string $currentHost): bool
    {
        $url = trim($url);

        if ('' === $url || str_starts_with($url, '//') || str_contains($url, '\\')) {
            return false;
        }

        if (preg_match('#^https?://#i', $url)) {
            $host = parse_url($url, \PHP_URL_HOST);

            return \is_string($host) && '' !== $host && 0 === strcasecmp($host, $currentHost);
        }

        // Reject any other scheme (javascript:, data:, file:, https:evil.com, ...).
        return 1 !== preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url);
    }
}
