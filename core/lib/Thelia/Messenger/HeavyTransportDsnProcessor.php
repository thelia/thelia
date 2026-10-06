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

namespace Thelia\Messenger;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;

/**
 * Derives the queue of the heavy jobs (the exports and imports of the back office)
 * from the queue of the shop, so a long import never holds up the mails behind it.
 *
 * `%env(thelia_heavy_queue:MESSENGER_TRANSPORT_DSN)%` reads:
 *  - MESSENGER_HEAVY_TRANSPORT_DSN when it is set, as it is;
 *  - nothing: `sync://`, the heavy jobs run at once like every other one;
 *  - `doctrine://…`: the same table, under the queue name `heavy`;
 *  - `redis://…`: the same server, on a stream named after the first one with `_heavy`;
 *  - anything else: the same DSN, the heavy jobs then share the queue of the others,
 *    and MESSENGER_HEAVY_TRANSPORT_DSN separates them.
 */
final class HeavyTransportDsnProcessor implements EnvVarProcessorInterface
{
    public const HEAVY_QUEUE = 'heavy';

    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $explicit = self::read($getEnv, 'MESSENGER_HEAVY_TRANSPORT_DSN');

        if ('' !== $explicit) {
            return $explicit;
        }

        return self::heavyDsnOf(self::read($getEnv, $name));
    }

    public static function getProvidedTypes(): array
    {
        return ['thelia_heavy_queue' => 'string'];
    }

    public static function heavyDsnOf(string $dsn): string
    {
        if ('' === $dsn) {
            return 'sync://';
        }

        if (str_starts_with($dsn, 'doctrine://')) {
            return self::withQuery($dsn, 'queue_name', self::HEAVY_QUEUE);
        }

        if (str_starts_with($dsn, 'redis://') || str_starts_with($dsn, 'rediss://')) {
            return self::withRedisStream($dsn);
        }

        return $dsn;
    }

    private static function read(\Closure $getEnv, string $name): string
    {
        try {
            return trim((string) $getEnv($name));
        } catch (EnvNotFoundException) {
            return '';
        }
    }

    private static function withQuery(string $dsn, string $key, string $value): string
    {
        [$base, $query] = array_pad(explode('?', $dsn, 2), 2, '');
        parse_str($query, $parameters);
        $parameters[$key] = $value;

        return $base.'?'.http_build_query($parameters);
    }

    /**
     * redis://host:6379/messages/group/consumer: the stream is the first segment of the
     * path, `messages` when there is none.
     */
    private static function withRedisStream(string $dsn): string
    {
        [$base, $query] = array_pad(explode('?', $dsn, 2), 2, '');

        if (1 === preg_match('#^(rediss?://[^/]+)/([^/]+)(.*)$#', $base, $parts)) {
            $base = $parts[1].'/'.$parts[2].'_'.self::HEAVY_QUEUE.$parts[3];
        } else {
            $base = rtrim($base, '/').'/messages_'.self::HEAVY_QUEUE;
        }

        return '' === $query ? $base : $base.'?'.$query;
    }
}
