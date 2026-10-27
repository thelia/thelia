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

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Reads a MaxMind country database (GeoLite2-Country, GeoIP2-Country or any database
 * with a `country.iso_code` record).
 *
 * The database and the `maxmind-db/reader` library are the shop's to provide: neither
 * ships with Thelia, and without them the detection answers nothing. A failure is
 * logged and never thrown, and never with the address: the detection runs once per
 * visitor session, so the log gets one line per session at most.
 */
#[AsAlias(IpCountryLookupInterface::class)]
final class MaxMindDatabaseLookup implements IpCountryLookupInterface
{
    public const READER_CLASS = 'MaxMind\Db\Reader';

    /**
     * The warnings already logged by this PHP process. A robot without a cookie opens a
     * session per request: a warning once per session would be a line per request.
     *
     * @var array<string, true>
     */
    private static array $warned = [];

    private readonly string $directory;

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        ?string $directory = null,
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
     * @internal for the tests: a process that serves many requests never forgets
     */
    public static function forgetWarnings(): void
    {
        self::$warned = [];
    }

    /**
     * @param array<string, string> $context
     */
    private function warnOnce(string $message, array $context = []): void
    {
        $key = $message.'|'.json_encode($context, \JSON_THROW_ON_ERROR);

        if (isset(self::$warned[$key])) {
            return;
        }

        self::$warned[$key] = true;
        $this->logger?->warning($message, $context);
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
