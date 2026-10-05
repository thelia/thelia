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

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\DriverManager;
use Propel\Runtime\Propel;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Thelia\Config\DatabaseConfiguration;

/**
 * Keeps the jobs of the shop in its own database, behind `doctrine://default`.
 *
 * The official Doctrine transport of Messenger asks DoctrineBundle for a connection,
 * and Thelia runs on Propel: this factory hands the very same transport a DBAL
 * connection opened with the settings of the Propel connection, so the queue lives
 * in the database the shop already has, whatever configured it (environment or
 * database.yml). The connection is a second one to that database, not the Propel
 * one: a job dispatched inside a Propel transaction is queued even if that
 * transaction is rolled back afterwards, so a job is dispatched once the writes it
 * is about are committed.
 *
 * The table is created by the install and the update scripts, never on the fly:
 * `auto_setup` is off unless the DSN asks for it.
 *
 * @implements TransportFactoryInterface<DoctrineTransport>
 */
final class ShopDatabaseTransportFactory implements TransportFactoryInterface
{
    public const CONNECTION_NAME = 'default';

    private ?DbalConnection $connection = null;

    public function createTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        unset($options['transport_name'], $options['use_notify']);

        $configuration = Connection::buildConfiguration($dsn, $options + ['auto_setup' => false]);

        if (self::CONNECTION_NAME !== $configuration['connection']) {
            throw new InvalidArgumentException(\sprintf('The shop has one database, named "%s" in a Messenger DSN: "doctrine://%s" names another one.', self::CONNECTION_NAME, $configuration['connection']));
        }

        return new DoctrineTransport(new Connection($configuration, $this->connection()), $serializer);
    }

    public function supports(#[\SensitiveParameter] string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'doctrine://');
    }

    /**
     * One DBAL connection for every transport of the process, opened on first use.
     */
    private function connection(): DbalConnection
    {
        return $this->connection ??= DriverManager::getConnection(self::connectionParameters());
    }

    /**
     * @return array{driver: 'pdo_mysql', user: string, password: string, host?: string, port?: int, dbname?: string, charset?: string, unix_socket?: string}
     */
    private static function connectionParameters(): array
    {
        $manager = Propel::getServiceContainer()->getConnectionManager(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        $configuration = method_exists($manager, 'getConfiguration') ? $manager->getConfiguration() : null;

        if (!\is_array($configuration) || !isset($configuration['dsn']) || !\is_string($configuration['dsn'])) {
            throw new TransportException('The shop database connection is not configured: the Messenger "doctrine://default" transport has nothing to connect to.');
        }

        return ['driver' => 'pdo_mysql', 'user' => (string) ($configuration['user'] ?? ''), 'password' => (string) ($configuration['password'] ?? '')]
            + self::parametersOfPdoDsn($configuration['dsn']);
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
