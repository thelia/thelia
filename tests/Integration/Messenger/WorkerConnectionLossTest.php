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

namespace Thelia\Tests\Integration\Messenger;

use Propel\Runtime\Propel;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Messenger\Serializer\AllowedClassesSerializer;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Messenger\InnerSerializerSpy;

/**
 * MySQL closes a connection left idle past wait_timeout, and a worker waits for jobs:
 * the job it then receives is read before anything else runs for it.
 */
final class WorkerConnectionLossTest extends IntegrationTestCase
{
    // The connection is killed: no transaction of the test could be rolled back.
    protected bool $useTransaction = false;

    protected function tearDown(): void
    {
        // Whatever the outcome, the next test gets a live connection.
        Propel::getServiceContainer()->getConnectionManager(DatabaseConfiguration::THELIA_CONNECTION_NAME)->closeConnections();

        parent::tearDown();
    }

    public function testAJobOfAModuleIsReadOnceTheConnectionWasClosedByTheServer(): void
    {
        $module = ModuleQuery::create()->filterByActivate(BaseModule::IS_ACTIVATED)->findOne();

        if (null === $module) {
            self::markTestSkipped('The test database has no active module.');
        }

        $connection = Propel::getConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);

        try {
            $connection->exec('KILL CONNECTION_ID()');
        } catch (\PDOException) {
            // The server answers that it killed the connection asking.
        }

        $inner = new InnerSerializerSpy();
        $type = $module->getCode().'\\Message\\SomeJob';

        (new AllowedClassesSerializer($inner, [], ['*']))->decode(['body' => '{}', 'headers' => ['type' => $type, 'Content-Type' => 'application/json']]);

        self::assertSame($type, $inner->lastDecoded['headers']['type'] ?? null);
    }
}
