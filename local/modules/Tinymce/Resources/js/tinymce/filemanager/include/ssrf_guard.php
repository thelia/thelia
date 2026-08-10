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

/*
 * SSRF protection for the remote-URL upload feature of the file manager.
 *
 * The upload endpoint may fetch a user-supplied URL server-side. Without
 * validation this lets an attacker reach internal services, cloud metadata
 * endpoints or local files. These helpers enforce an http/https allowlist,
 * reject any host that resolves to a private, loopback, link-local or reserved
 * address, pin the connection to the validated address (mitigating DNS
 * rebinding) and forbid redirects.
 */

if (!function_exists('thelia_ip_in_cidr')) {
    function thelia_ip_in_cidr(string $ip, string $cidr): bool
    {
        [$subnet, $maskLength] = explode('/', $cidr);

        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);

        if ($ipBinary === false || $subnetBinary === false || \strlen($ipBinary) !== \strlen($subnetBinary)) {
            return false;
        }

        $maskLength = (int) $maskLength;
        $fullBytes = intdiv($maskLength, 8);
        $remainingBits = $maskLength % 8;

        if ($fullBytes > 0 && strncmp($ipBinary, $subnetBinary, $fullBytes) !== 0) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = \chr((0xFF << (8 - $remainingBits)) & 0xFF);

        return (\ord($ipBinary[$fullBytes]) & \ord($mask)) === (\ord($subnetBinary[$fullBytes]) & \ord($mask));
    }
}

if (!function_exists('thelia_ip_is_blocked')) {
    function thelia_ip_is_blocked(string $ip): bool
    {
        // Normalize IPv4-mapped IPv6 addresses (e.g. ::ffff:127.0.0.1).
        if (str_starts_with($ip, '::ffff:')) {
            $mapped = substr($ip, 7);
            if (filter_var($mapped, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) !== false) {
                $ip = $mapped;
            }
        }

        if (filter_var($ip, \FILTER_VALIDATE_IP) === false) {
            return true;
        }

        if (filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        $blockedRanges = [
            '0.0.0.0/8',
            '10.0.0.0/8',
            '100.64.0.0/10',
            '127.0.0.0/8',
            '169.254.0.0/16',
            '172.16.0.0/12',
            '192.0.0.0/24',
            '192.168.0.0/16',
            '198.18.0.0/15',
            '::1/128',
            '::/128',
            'fc00::/7',
            'fe80::/10',
            '64:ff9b::/96',
        ];

        foreach ($blockedRanges as $range) {
            if (thelia_ip_in_cidr($ip, $range)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('thelia_resolve_host_ips')) {
    /**
     * @return list<string>
     */
    function thelia_resolve_host_ips(string $host): array
    {
        if (filter_var($host, \FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $ips = [];

        $ipv4 = @gethostbynamel($host);
        if (\is_array($ipv4)) {
            $ips = $ipv4;
        }

        $records = @dns_get_record($host, \DNS_AAAA);
        if (\is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }
}

if (!function_exists('thelia_remote_url_target')) {
    /**
     * Validate a user-supplied URL for server-side fetching.
     *
     * @return array{host: string, port: int, ip: string}|null the validated
     *                                                          target, or null when the URL must be rejected
     */
    function thelia_remote_url_target(string $url): ?array
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        if (!\in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = trim($parts['host'], '[]');
        if ($host === '') {
            return null;
        }

        $ips = thelia_resolve_host_ips($host);
        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            if (thelia_ip_is_blocked($ip)) {
                return null;
            }
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ('https' === $scheme ? 443 : 80);

        return ['host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }
}

if (!function_exists('thelia_fetch_remote_file')) {
    /**
     * Fetch a user-supplied URL with SSRF protections enabled.
     *
     * @return string|false the response body, or false when the URL is rejected
     *                      or the request fails
     */
    function thelia_fetch_remote_file(string $url): string|false
    {
        $target = thelia_remote_url_target($url);
        if ($target === null) {
            return false;
        }

        if (!\function_exists('curl_init')) {
            return false;
        }

        $handle = curl_init();
        curl_setopt($handle, \CURLOPT_URL, $url);
        curl_setopt($handle, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, \CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($handle, \CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($handle, \CURLOPT_TIMEOUT, 30);
        curl_setopt($handle, \CURLOPT_MAXFILESIZE, 64 * 1024 * 1024);
        // Pin the resolved address so the host cannot rebind to an internal
        // target between validation and connection.
        curl_setopt($handle, \CURLOPT_RESOLVE, [$target['host'].':'.$target['port'].':'.$target['ip']]);

        if (\defined('CURLOPT_PROTOCOLS') && \defined('CURLPROTO_HTTP') && \defined('CURLPROTO_HTTPS')) {
            curl_setopt($handle, \CURLOPT_PROTOCOLS, \CURLPROTO_HTTP | \CURLPROTO_HTTPS);
            curl_setopt($handle, \CURLOPT_REDIR_PROTOCOLS, \CURLPROTO_HTTP | \CURLPROTO_HTTPS);
        }

        $data = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, \CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($data === false) {
            return false;
        }

        // Reject anything but a final 2xx response; a 3xx here means a blocked
        // redirect (redirects are disabled above).
        if ($statusCode < 200 || $statusCode >= 300) {
            return false;
        }

        return $data;
    }
}
