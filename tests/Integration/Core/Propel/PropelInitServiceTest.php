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

use Propel\Generator\Builder\Om\TableMapLoaderScriptBuilder;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;
use Thelia\Core\Propel\PropelInitService;
use Thelia\Core\Propel\Schema\SchemaLocator;
use Thelia\Core\TheliaKernel;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;

final class PropelInitServiceTest extends IntegrationTestCase
{
    private const TEST_ENVIRONMENT = 'propel_init_service_test';

    private const PROBE_MODULE_CODE = 'PropelInitServiceReloadProbe';
    private const PROBE_TABLE_NAME = 'propel_init_service_reload_probe';

    // A module row committed outside any transaction is required so that
    // getActiveModuleCodes(), which opens its own PDO connection, can see it.
    protected bool $useTransaction = false;

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

    /**
     * Reproduces the module activation crash: the kernel boot already required
     * the init file once in this process with the pre-activation table list,
     * then BaseModule::activate() forces a rebuild mid-request. Without the
     * fix, the second require_once of the same path is a silent no-op and the
     * DatabaseMap never learns about the module's new i18n table.
     */
    public function testForcedRebuildReloadsTheDatabaseMapDespiteTheAlreadyRequiredInitFile(): void
    {
        $envParameters = [
            'thelia.database_host' => TheliaKernel::resolveEnv('DATABASE_HOST'),
            'thelia.database_port' => TheliaKernel::resolveEnv('DATABASE_PORT'),
            'thelia.database_name' => TheliaKernel::resolveEnv('DATABASE_NAME'),
            'thelia.database_user' => TheliaKernel::resolveEnv('DATABASE_USER'),
            'thelia.database_password' => TheliaKernel::resolveEnv('DATABASE_PASSWORD'),
        ];

        $service = new PropelInitService(
            'propel_init_service_reload_probe_test',
            false,
            $envParameters,
            new SchemaLocator(THELIA_CONF_DIR, THELIA_MODULE_DIR, THELIA_LOCAL_MODULE_DIR),
        );

        try {
            // Normal kernel boot, before the module exists: builds and
            // requires the init file once in this very process.
            self::assertTrue($service->init(false), 'The initial, module-less build must succeed.');

            $this->writeProbeModule();
            $this->activateProbeModuleRow();

            // A module activation forcing a rebuild mid-request, exactly like
            // BaseModule::activate() -> $kernel->initializePropelService(true).
            self::assertTrue($service->init(true), 'The forced rebuild must succeed.');

            self::assertInstanceOf(
                TableMap::class,
                Propel::getServiceContainer()->getDatabaseMap('TheliaMain')->getTable(self::PROBE_TABLE_NAME.'_i18n'),
                'The forced rebuild must reload the DatabaseMap even though this process already required the init file once.',
            );
        } finally {
            // Best-effort, independent steps: one failing (e.g. the DB row
            // already gone) must not skip the filesystem or DatabaseMap
            // cleanup that follows, or later tests in this process inherit
            // the mess.
            foreach ([
                static fn () => (new Filesystem())->remove($service->getPropelCacheDir()),
                fn () => $this->removeProbeModule(),
                fn () => $this->removeProbeModuleRow(),
                fn () => $this->restoreTheRealDatabaseMap(),
            ] as $cleanupStep) {
                try {
                    $cleanupStep();
                } catch (\Throwable) {
                    // Best effort: see above.
                }
            }
        }
    }

    private function writeProbeModule(): void
    {
        $fs = new Filesystem();
        $moduleDir = THELIA_LOCAL_MODULE_DIR.self::PROBE_MODULE_CODE.DS;

        $fs->dumpFile($moduleDir.'Config'.DS.'module.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <module>
                <fullnamespace>PropelInitServiceReloadProbe\PropelInitServiceReloadProbe</fullnamespace>
                <descriptive locale="en_US">
                    <title>Propel init service reload probe</title>
                </descriptive>
                <version>1.0.0</version>
                <author>
                    <name>Thelia</name>
                    <email>info@thelia.net</email>
                </author>
                <type>classic</type>
                <thelia>3.0.0</thelia>
                <stability>dev</stability>
            </module>
            XML);

        $schema = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <database name="TheliaMain" namespace="PropelInitServiceReloadProbe\Model">
                <table name="__TABLE_NAME__">
                    <column name="id" type="INTEGER" primaryKey="true" autoIncrement="true" />
                    <column name="title" type="VARCHAR" size="255" />
                    <behavior name="i18n">
                        <parameter name="i18n_columns" value="title" />
                    </behavior>
                </table>
            </database>
            XML;

        $fs->dumpFile(
            $moduleDir.'Config'.DS.'schema.xml',
            str_replace('__TABLE_NAME__', self::PROBE_TABLE_NAME, $schema),
        );
    }

    private function removeProbeModule(): void
    {
        (new Filesystem())->remove(THELIA_LOCAL_MODULE_DIR.self::PROBE_MODULE_CODE);
    }

    private function activateProbeModuleRow(): void
    {
        (new Module())
            ->setCode(self::PROBE_MODULE_CODE)
            ->setVersion('1.0.0')
            ->setType(BaseModule::CLASSIC_MODULE_TYPE)
            ->setCategory('classic')
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->setFullNamespace('PropelInitServiceReloadProbe\\PropelInitServiceReloadProbe')
            ->save();
    }

    private function removeProbeModuleRow(): void
    {
        ModuleQuery::create()->findOneByCode(self::PROBE_MODULE_CODE)?->delete();
    }

    /**
     * Undoes, unconditionally, whatever the probe's rebuild just did to the
     * process-wide DatabaseMap: a plain require (not require_once) of the
     * real test environment's still-current loader script, so no later test
     * in this process is left holding a class-map entry pointing at the
     * probe's now-deleted generated files.
     */
    private function restoreTheRealDatabaseMap(): void
    {
        $realService = new PropelInitService(
            self::$kernel->getEnvironment(),
            self::$kernel->isDebug(),
            [],
            new SchemaLocator(THELIA_CONF_DIR, THELIA_MODULE_DIR, THELIA_LOCAL_MODULE_DIR),
        );

        require $realService->getPropelLoaderScriptDir().TableMapLoaderScriptBuilder::FILENAME;
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

    /**
     * A cache clear running on one worker takes the init file away while others
     * are booting on a stat cache that still lists it: loading the runtime then
     * killed the request. Answering "rebuild me" keeps the boot alive.
     */
    public function testLoadingTheRuntimeWithoutItsInitFileAsksForARebuild(): void
    {
        self::assertFileDoesNotExist($this->service->getPropelInitFile());

        $method = new \ReflectionMethod($this->service, 'loadPropelRuntime');

        self::assertNull($method->invoke($this->service));
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
