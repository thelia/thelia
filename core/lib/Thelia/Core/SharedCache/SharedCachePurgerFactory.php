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

namespace Thelia\Core\SharedCache;

use ApiPlatform\HttpCache\PurgerInterface;
use ApiPlatform\HttpCache\SouinPurger;
use ApiPlatform\HttpCache\SurrogateKeysPurger;
use ApiPlatform\HttpCache\VarnishXKeyPurger;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Exclude]
final class SharedCachePurgerFactory
{
    public const SECRET_HEADER = 'X-Thelia-Purge-Secret';

    public const DRIVERS = ['varnish', 'souin', 'surrogate-key'];

    private function __construct()
    {
    }

    public static function create(string $driver, string $urls, string $secret, ?HttpClientInterface $client = null): SharedCachePurgerInterface
    {
        $driver = strtolower(trim($driver));

        if ('' === $driver || 'none' === $driver) {
            return new NullSharedCachePurger();
        }

        if (!\in_array($driver, self::DRIVERS, true)) {
            return new UnavailableSharedCachePurger(\sprintf('SHARED_CACHE_PURGER "%s" is unknown, expected one of: none, %s.', $driver, implode(', ', self::DRIVERS)));
        }

        $entries = array_values(array_filter(
            array_map('trim', explode(',', $urls)),
            static fn (string $uri): bool => '' !== $uri,
        ));

        if ([] === $entries) {
            return new UnavailableSharedCachePurger(\sprintf('SHARED_CACHE_PURGER is "%s" but SHARED_CACHE_PURGE_URLS lists no proxy.', $driver));
        }

        $proxies = [];

        foreach ($entries as $index => $entry) {
            $proxy = self::proxyOf($entry);

            if (null === $proxy) {
                return new UnavailableSharedCachePurger(\sprintf('SHARED_CACHE_PURGE_URLS is not usable: entry %d is not an http or https address with a host (a login or password with a reserved character must be percent encoded).', $index + 1));
            }

            $proxies[] = $proxy;
        }

        $options = ['timeout' => 2.0, 'max_duration' => 5.0, 'max_redirects' => 0];
        if ('' !== $secret) {
            $options['headers'] = [self::SECRET_HEADER => $secret];
        }

        try {
            $client ??= HttpClient::create();
            $purgers = array_map(
                static fn (array $proxy): PurgerInterface => self::purgerFor(
                    $driver,
                    [ScopingHttpClient::forBaseUri($client, $proxy['base'], null === $proxy['login'] ? $options : $options + ['auth_basic' => $proxy['login']])],
                ),
                $proxies,
            );
        } catch (\Throwable $exception) {
            return new UnavailableSharedCachePurger(\sprintf('SHARED_CACHE_PURGE_URLS is not usable (%s).', $exception::class));
        }

        return new HttpSharedCachePurger($purgers);
    }

    /**
     * The login of an address never travels in the address: it becomes the basic
     * authentication of the client, so no message, log line or exception built
     * from the address can carry it.
     *
     * @return array{base: string, login: ?string}|null
     */
    private static function proxyOf(string $entry): ?array
    {
        $parts = parse_url($entry);

        if (false === $parts || !isset($parts['scheme'], $parts['host']) || !\in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $login = null;
        if (isset($parts['user'])) {
            $login = rawurldecode($parts['user']).(isset($parts['pass']) ? ':'.rawurldecode($parts['pass']) : '');
        }

        return [
            'base' => strtolower($parts['scheme']).'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? ''),
            'login' => $login,
        ];
    }

    /**
     * @param list<HttpClientInterface> $clients
     */
    private static function purgerFor(string $driver, array $clients): PurgerInterface
    {
        return match ($driver) {
            'varnish' => new VarnishXKeyPurger($clients),
            'souin' => new SouinPurger($clients),
            default => new SurrogateKeysPurger($clients),
        };
    }
}
