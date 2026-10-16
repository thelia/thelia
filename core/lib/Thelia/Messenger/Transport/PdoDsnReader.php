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

namespace Thelia\Messenger\Transport;

use Propel\Runtime\Propel;
use Symfony\Component\Messenger\Exception\TransportException;
use Thelia\Config\DatabaseConfiguration;

/**
 * Reads the settings of the Propel connection into the parameters of a DBAL one.
 */
final class PdoDsnReader
{
    /**
     * @return array{driver: 'pdo_mysql', user: string, password: string, driverOptions: array<int|string, mixed>, host?: string, port?: int, dbname?: string, charset?: string, unix_socket?: string}
     */
    public static function connectionParameters(): array
    {
        $manager = Propel::getServiceContainer()->getConnectionManager(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        $configuration = method_exists($manager, 'getConfiguration') ? $manager->getConfiguration() : null;

        if (!\is_array($configuration) || !isset($configuration['dsn']) || !\is_string($configuration['dsn'])) {
            throw new TransportException('The shop database connection is not configured: the Messenger "doctrine://default" transport has nothing to connect to.');
        }

        return ['driver' => 'pdo_mysql', 'user' => (string) ($configuration['user'] ?? ''), 'password' => (string) ($configuration['password'] ?? ''), 'driverOptions' => self::driverOptionsOf($configuration)]
            + self::parametersOfPdoDsn($configuration['dsn']);
    }

    /**
     * The PDO options and attributes of the Propel connection, a TLS certificate
     * among them: the queue connection is opened the way the shop one is, never in
     * clear against a database that wants TLS. A value written as a class constant
     * (`PDO::MYSQL_ATTR_SSL_CA`-style) is resolved, as Propel does.
     *
     * @param array<string, mixed> $configuration
     *
     * @return array<int|string, mixed>
     */
    public static function driverOptionsOf(array $configuration): array
    {
        $options = [];

        foreach (['options', 'attributes'] as $section) {
            if (!isset($configuration[$section]) || !\is_array($configuration[$section])) {
                continue;
            }

            foreach ($configuration[$section] as $option => $value) {
                if (\is_string($value) && str_contains($value, '::') && \defined($value)) {
                    $value = \constant($value);
                }

                $options[$option] = $value;
            }
        }

        return $options;
    }

    /**
     * Reads `mysql:host=db;port=3306;dbname=shop;charset=utf8mb4` into DBAL parameters.
     *
     * @return array{host?: string, port?: int, dbname?: string, charset?: string, unix_socket?: string}
     */
    public static function parametersOfPdoDsn(string $dsn): array
    {
        if (!str_starts_with($dsn, 'mysql:')) {
            throw new TransportException('The shop database is not a MySQL or MariaDB one: the Messenger "doctrine://default" transport only supports those.');
        }

        $parameters = [];

        foreach (explode(';', substr($dsn, \strlen('mysql:'))) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $name = strtolower(trim($name));
            $value = trim($value);

            if ('' === $value) {
                continue;
            }

            match ($name) {
                'host' => $parameters['host'] = $value,
                'port' => $parameters['port'] = (int) $value,
                'dbname' => $parameters['dbname'] = $value,
                'charset' => $parameters['charset'] = $value,
                'unix_socket' => $parameters['unix_socket'] = $value,
                default => null,
            };
        }

        return $parameters;
    }
}
