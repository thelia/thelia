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

namespace Thelia\Tests\Integration\Core\Propel;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Thelia\Core\Propel\PropelInitService;
use Thelia\Core\Propel\Schema\SchemaLocator;
use Thelia\Test\IntegrationTestCase;

final class PropelInitServiceTest extends IntegrationTestCase
{
    private const TEST_ENVIRONMENT = 'propel_init_service_test';

    private PropelInitService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PropelInitService(
            self::TEST_ENVIRONMENT,
            false,
            [],
            new SchemaLocator(THELIA_CONF_DIR, THELIA_MODULE_DIR, THELIA_LOCAL_MODULE_DIR),
        );
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->service->getPropelCacheDir());

        parent::tearDown();
    }

    public function testGetActiveModuleCodesReturnsNullWithoutConfigFile(): void
    {
        self::assertNull($this->getActiveModuleCodes());
    }

    public function testGetActiveModuleCodesReturnsNullOnEmptyModuleTable(): void
    {
        // Right after thelia:install the module table exists but is empty. The
        // service must fall back to the filesystem scan (null) instead of
        // building schemas for an empty module list, which would produce no
        // schema at all and crash the model build.
        $this->createModuleDatabase();

        self::assertNull($this->getActiveModuleCodes());
    }

    public function testGetActiveModuleCodesReturnsNullWhenNoModuleIsActive(): void
    {
        $pdo = $this->createModuleDatabase();
        $pdo->exec("INSERT INTO `module` (`code`, `activate`) VALUES ('InactiveModule', 0)");

        self::assertNull($this->getActiveModuleCodes());
    }

    public function testGetActiveModuleCodesReturnsActiveModuleCodes(): void
    {
        $pdo = $this->createModuleDatabase();
        $pdo->exec("INSERT INTO `module` (`code`, `activate`) VALUES ('ActiveModule', 1), ('InactiveModule', 0)");

        self::assertSame(['ActiveModule'], $this->getActiveModuleCodes());
    }

    private function getActiveModuleCodes(): ?array
    {
        $method = new \ReflectionMethod($this->service, 'getActiveModuleCodes');

        return $method->invoke($this->service);
    }

    /**
     * Create a throwaway sqlite database holding a `module` table, and point the
     * service's propel.yml at it. getActiveModuleCodes() uses plain PDO, so any
     * driver works — sqlite avoids needing server-level privileges.
     */
    private function createModuleDatabase(): \PDO
    {
        $databaseFile = $this->service->getPropelCacheDir().'module-codes.sqlite';

        (new Filesystem())->mkdir(\dirname($databaseFile));

        $pdo = new \PDO('sqlite:'.$databaseFile, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE `module` (`code` VARCHAR(55) NOT NULL, `activate` TINYINT NOT NULL DEFAULT 0)');

        $config = [
            'propel' => [
                'database' => [
                    'connections' => [
                        'TheliaMain' => [
                            'dsn' => 'sqlite:'.$databaseFile,
                        ],
                    ],
                ],
            ],
        ];

        (new Filesystem())->dumpFile($this->service->getPropelConfigFile(), Yaml::dump($config));

        return $pdo;
    }
}
