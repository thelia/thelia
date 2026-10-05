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

namespace Thelia\Tests\Unit\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Thelia\Messenger\Transport\ShopDatabaseTransportFactory;

final class ShopDatabaseTransportFactoryTest extends TestCase
{
    public function testItAnswersForTheDoctrineScheme(): void
    {
        $factory = new ShopDatabaseTransportFactory();

        self::assertTrue($factory->supports('doctrine://default', []));
        self::assertTrue($factory->supports('doctrine://default?queue_name=failed', []));
        self::assertFalse($factory->supports('redis://localhost:6379/messages', []));
        self::assertFalse($factory->supports('sync://', []));
    }

    /**
     * There is one database: a DSN naming another connection is a mistake, and it is
     * answered before anything connects anywhere.
     */
    public function testADsnNamingAnotherConnectionIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"doctrine://reporting"');

        (new ShopDatabaseTransportFactory())->createTransport('doctrine://reporting', [], new PhpSerializer());
    }

    public function testThePdoDsnOfThePropelConnectionIsReadIntoDbalParameters(): void
    {
        self::assertSame(
            ['host' => 'db', 'dbname' => 'shop', 'port' => 3307, 'charset' => 'utf8mb4'],
            ShopDatabaseTransportFactory::parametersOfPdoDsn('mysql:host=db;dbname=shop;port=3307;charset=utf8mb4'),
        );
    }

    public function testASocketAndEmptyPartsOfThePdoDsnAreHandled(): void
    {
        self::assertSame(
            ['unix_socket' => '/run/mysqld/mysqld.sock', 'dbname' => 'shop'],
            ShopDatabaseTransportFactory::parametersOfPdoDsn('mysql:unix_socket=/run/mysqld/mysqld.sock;dbname=shop;port=;'),
        );
    }

    public function testADatabaseOtherThanMysqlIsRefused(): void
    {
        $this->expectException(TransportException::class);

        ShopDatabaseTransportFactory::parametersOfPdoDsn('pgsql:host=db;dbname=shop');
    }
}
