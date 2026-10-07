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

use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\DoctrineTransport;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

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
final readonly class ShopDatabaseTransportFactory implements TransportFactoryInterface
{
    public const CONNECTION_NAME = 'default';

    public function __construct(
        private ShopDatabaseConnection $connection,
    ) {
    }

    public function createTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        unset($options['transport_name'], $options['use_notify']);

        $configuration = Connection::buildConfiguration($dsn, $options + ['auto_setup' => false]);

        if (self::CONNECTION_NAME !== $configuration['connection']) {
            throw new InvalidArgumentException(\sprintf('The shop has one database, named "%s" in a Messenger DSN: "doctrine://%s" names another one.', self::CONNECTION_NAME, $configuration['connection']));
        }

        return new DoctrineTransport(new Connection($configuration, $this->connection->get()), $serializer);
    }

    public function supports(#[\SensitiveParameter] string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'doctrine://');
    }
}
