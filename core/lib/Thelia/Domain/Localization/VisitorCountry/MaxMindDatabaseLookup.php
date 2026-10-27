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

namespace Thelia\Domain\Localization\VisitorCountry;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads a MaxMind country database (GeoLite2-Country, GeoIP2-Country or any database
 * with a `country.iso_code` record).
 *
 * The database and the `maxmind-db/reader` library are the shop's to provide: neither
 * ships with Thelia, and without them the detection answers nothing. A failure is
 * logged and never thrown, and never with the address: the detection runs once per
 * visitor session, and the log gets a given line once an hour at most.
 */
#[AsAlias(IpCountryLookupInterface::class)]
final readonly class MaxMindDatabaseLookup implements IpCountryLookupInterface
{
    public const READER_CLASS = 'MaxMind\Db\Reader';

    private const WARNING_LIFETIME = 3600;

    private string $directory;

    /**
     * @param CacheItemPoolInterface|null $warnings the application cache, where a warning is remembered for an hour:
     *                                              a robot without a cookie opens a session per request, and a
     *                                              warning once per session would be a line per request. Without a
     *                                              pool every failure is logged
     */
    public function __construct(
        private ?LoggerInterface $logger = null,
        ?string $directory = null,
        #[Autowire(service: 'cache.app')]
        private ?CacheItemPoolInterface $warnings = null,
    ) {
        $this->directory = rtrim($directory ?? THELIA_LOCAL_DIR.'geoip', '/\\');
    }

    public function lookup(string $ipAddress, string $databaseFileName): ?string
    {
        if (!self::isPlainFileName($databaseFileName)) {
            $this->warnOnce('The visitor country database must be a file name inside the geoip directory of the local directory: the setting is ignored.');

            return null;
        }

        if (!class_exists(self::READER_CLASS)) {
            $this->warnOnce('The visitor country detection reads an IP database but the maxmind-db/reader library is not installed.');

            return null;
        }

        $path = $this->directory.'/'.$databaseFileName;

        if (!is_file($path) || !is_readable($path)) {
            $this->warnOnce('The visitor country database is missing or not readable.', ['file' => $databaseFileName]);

            return null;
        }

        try {
            $readerClass = self::READER_CLASS;
            $reader = new $readerClass($path);

            try {
                $record = $reader->get($ipAddress);
            } finally {
                $reader->close();
            }
        } catch (\Throwable $failure) {
            // The message of the reader may quote the address it was given.
            $this->warnOnce('The visitor country database could not be read.', ['file' => $databaseFileName, 'failure' => $failure::class]);

            return null;
        }

        $code = \is_array($record) ? ($record['country']['iso_code'] ?? null) : null;

        return \is_string($code) ? $code : null;
    }

    /**
     * @param array<string, string> $context
     */
    private function warnOnce(string $message, array $context = []): void
    {
        if (null === $this->logger) {
            return;
        }

        if (null !== $this->warnings) {
            try {
                $item = $this->warnings->getItem('thelia.visitor_country.warning.'.hash('xxh128', $message.'|'.json_encode($context, \JSON_THROW_ON_ERROR)));

                if ($item->isHit()) {
                    return;
                }

                $this->warnings->save($item->set(true)->expiresAfter(self::WARNING_LIFETIME));
            } catch (\Throwable) {
                // A cache that fails must not hide the warning: it is logged each time.
            }
        }

        $this->logger->warning($message, $context);
    }

    private static function isPlainFileName(string $name): bool
    {
        return '' !== $name
            && !str_contains($name, '/')
            && !str_contains($name, '\\')
            && !str_contains($name, '..')
            && !str_contains($name, "\0");
    }
}
