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

        $baseUris = array_values(array_filter(
            array_map('trim', explode(',', $urls)),
            static fn (string $uri): bool => '' !== $uri,
        ));

        if ([] === $baseUris) {
            return new UnavailableSharedCachePurger(\sprintf('SHARED_CACHE_PURGER is "%s" but SHARED_CACHE_PURGE_URLS lists no proxy.', $driver));
        }

        $options = ['timeout' => 2.0, 'max_duration' => 5.0];
        if ('' !== $secret) {
            $options['headers'] = [self::SECRET_HEADER => $secret];
        }

        $client ??= HttpClient::create();
        $clients = array_map(
            static fn (string $uri): HttpClientInterface => ScopingHttpClient::forBaseUri($client, $uri, $options),
            $baseUris,
        );

        return new HttpSharedCachePurger(match ($driver) {
            'varnish' => new VarnishXKeyPurger($clients),
            'souin' => new SouinPurger($clients),
            default => new SurrogateKeysPurger($clients),
        });
    }
}
