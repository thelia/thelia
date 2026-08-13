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

namespace Thelia\Tests\Integration\Action;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Event\Module\ModuleEvent;
use Thelia\Core\Event\Module\ModuleInstallEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\UpdatePositionEvent;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\HookQuery;
use Thelia\Model\IgnoredModuleHook;
use Thelia\Model\IgnoredModuleHookQuery;
use Thelia\Model\Module;
use Thelia\Model\ModuleConfig;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleHook;
use Thelia\Model\ModuleHookQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\ProfileModule;
use Thelia\Model\ProfileModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\Validator\ModuleDefinition;
use Thelia\Test\ActionIntegrationTestCase;

final class ModuleActionTest extends ActionIntegrationTestCase
{
    private const SAMPLE_PREFIX = 'ZipUpgrade';

    private const SAMPLE_CODE = 'ZipUpgradeSample';

    private const SAMPLE_NAMESPACE = 'ZipUpgradeSample\ZipUpgradeSample';

    private const LOCAL_SAMPLE_CODE = 'ZipUpgradeLocalSample';

    private const LOCAL_SAMPLE_NAMESPACE = 'ZipUpgradeLocalSample\ZipUpgradeLocalSample';

    private const DECLARED_HOOK_CODE = 'zipupgradesample.declared';

    /**
     * Where the sample module records the lifecycle methods Thelia calls on it.
     */
    private const CALL_LOG = '/tmp/thelia-zip-upgrade-sample-calls.log';

    /**
     * @var list<string> the temporary directories standing in for an uploaded archive
     */
    private array $archiveDirectories = [];

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();

        // The module upgrade path reinitializes Propel, which can drop the transaction
        // this test case rolls back. Start from a known state rather than from whatever
        // a previous run left behind.
        $this->removeSampleModules();
    }

    protected function tearDown(): void
    {
        $this->removeSampleModules();

        parent::tearDown();
    }

    public function testUpdateChangesI18nFields(): void
    {
        $module = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($module, 'Cheque module must be registered by bin/test-prepare');

        $event = new ModuleEvent($module);
        $event->setId($module->getId());
        $event->setLocale('en_US');
        $event->setTitle('Cheque Updated');
        $event->setChapo('A short chapo');
        $event->setDescription('A module description');
        $event->setPostscriptum('PS text');

        $this->dispatch($event, TheliaEvents::MODULE_UPDATE);

        $reloaded = ModuleQuery::create()->findPk($module->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Cheque Updated', $reloaded->setLocale('en_US')->getTitle());
        self::assertSame('A short chapo', $reloaded->getChapo());
        self::assertSame('A module description', $reloaded->getDescription());
        self::assertSame('PS text', $reloaded->getPostscriptum());
    }

    public function testUpdatePositionMovesModuleToAbsolutePosition(): void
    {
        $module = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($module);

        $targetPosition = $module->getPosition() + 1;

        $event = new UpdatePositionEvent(
            $module->getId(),
            UpdatePositionEvent::POSITION_ABSOLUTE,
            $targetPosition,
        );

        $this->dispatch($event, TheliaEvents::MODULE_UPDATE_POSITION);

        self::assertSame(
            $targetPosition,
            ModuleQuery::create()->findPk($module->getId())->getPosition(),
        );
    }

    public function testUpgradeKeepsTheModuleRow(): void
    {
        $installed = $this->installSampleModule('1.0.0');
        $moduleId = $installed->getId();

        $upgraded = $this->upgradeSampleModule('2.0.0');

        self::assertSame(
            $moduleId,
            $upgraded->getId(),
            'Upgrading a module must keep its row: a new id discards everything the foreign keys cascade on.',
        );
        self::assertSame('2.0.0', $upgraded->getVersion());
    }

    public function testUpgradeKeepsTheHookPositionsSetInTheBackOffice(): void
    {
        $module = $this->installSampleModule('1.0.0');
        $hookId = HookQuery::create()->findOne()->getId();

        $moduleHook = new ModuleHook();
        $moduleHook
            ->setModuleId($module->getId())
            ->setHookId($hookId)
            ->setClassname('ZipUpgradeSample\Hook\SampleHook')
            ->setMethod('onSomething')
            ->setActive(true)
            ->setHookActive(true)
            ->setModuleActive(true)
            ->setPosition(42)
            ->save();

        $this->upgradeSampleModule('2.0.0');

        $reloaded = ModuleHookQuery::create()
            ->filterByModuleId($module->getId())
            ->filterByHookId($hookId)
            ->findOne();

        self::assertNotNull($reloaded, 'The module hooks must survive an upgrade.');
        self::assertSame(42, $reloaded->getPosition(), 'The position set in the back office must survive an upgrade.');
    }

    public function testUpgradeKeepsTheModuleConfiguration(): void
    {
        $module = $this->installSampleModule('1.0.0');

        $config = new ModuleConfig();
        $config
            ->setModuleId($module->getId())
            ->setName('api_key')
            ->setValue('the-key-the-merchant-typed')
            ->save();

        $this->upgradeSampleModule('2.0.0');

        $reloaded = ModuleConfigQuery::create()
            ->filterByModuleId($module->getId())
            ->filterByName('api_key')
            ->findOne();

        self::assertNotNull($reloaded, 'The module configuration must survive an upgrade.');
        self::assertSame('the-key-the-merchant-typed', $reloaded->getValue());
    }

    public function testUpgradeKeepsTheHooksTheAdministratorSwitchedOff(): void
    {
        $module = $this->installSampleModule('1.0.0');
        $hookId = HookQuery::create()->findOne()->getId();

        $ignored = new IgnoredModuleHook();
        $ignored
            ->setModuleId($module->getId())
            ->setHookId($hookId)
            ->setClassname('ZipUpgradeSample\Hook\SampleHook')
            ->setMethod('onSomething')
            ->save();

        $this->upgradeSampleModule('2.0.0');

        self::assertSame(
            1,
            IgnoredModuleHookQuery::create()->filterByModuleId($module->getId())->count(),
            'A hook the administrator removed must not come back after an upgrade.',
        );
    }

    public function testUpgradeKeepsTheAdministratorAccessAndDeliveryAreas(): void
    {
        $module = $this->installSampleModule('1.0.0');

        $profileModule = new ProfileModule();
        $profileModule
            ->setProfileId($this->factory->profile()->getId())
            ->setModuleId($module->getId())
            ->setAccess(1)
            ->save();

        $area = new Area();
        $area->setName('Zip upgrade sample area')->save();

        $areaDeliveryModule = new AreaDeliveryModule();
        $areaDeliveryModule
            ->setAreaId($area->getId())
            ->setDeliveryModuleId($module->getId())
            ->save();

        $this->upgradeSampleModule('2.0.0');

        self::assertSame(
            1,
            ProfileModuleQuery::create()->filterByModuleId($module->getId())->count(),
            'The access an administrator profile has on a module must survive an upgrade.',
        );
        self::assertSame(
            1,
            AreaDeliveryModuleQuery::create()->filterByDeliveryModuleId($module->getId())->count(),
            'The delivery areas a module is attached to must survive an upgrade.',
        );
    }

    public function testUpgradeKeepsTheTitleTheAdministratorEdited(): void
    {
        $module = $this->installSampleModule('1.0.0');

        $module->setLocale('en_US')->setTitle('Renamed by the merchant')->save();

        $upgraded = $this->upgradeSampleModule('2.0.0');

        self::assertSame(
            'Renamed by the merchant',
            $upgraded->setLocale('en_US')->getTitle(),
            'An upgrade must not overwrite the title with the one from the archive descriptor.',
        );
    }

    /**
     * Installing a newer version of an already installed module used to delete its row first,
     * which the order table refuses for a payment module. Upgrading in place no longer reaches
     * that constraint: the shop can upgrade the payment module it has taken orders with.
     */
    public function testUpgradeSucceedsForAPaymentModuleUsedByAnOrder(): void
    {
        $module = $this->installSampleModule('1.0.0');
        $module->setActivate(BaseModule::IS_ACTIVATED)->save();

        $order = $this->factory->order(null, ['paymentModuleCode' => self::SAMPLE_CODE]);
        self::assertSame($module->getId(), $order->getPaymentModuleId());

        $upgraded = $this->upgradeSampleModule('2.0.0');

        self::assertSame($module->getId(), $upgraded->getId());
        self::assertSame('2.0.0', $upgraded->getVersion());
        self::assertSame(
            BaseModule::IS_ACTIVATED,
            ModuleQuery::create()->findPk($module->getId())->getActivate(),
            'An upgraded payment module must be left activated, not silently switched off.',
        );
    }

    public function testUpgradeCallsUpdateAndNotInstall(): void
    {
        $this->installSampleModule('1.0.0');

        file_put_contents(self::CALL_LOG, '');

        $this->upgradeSampleModule('2.0.0');

        self::assertSame(
            "update 1.0.0 2.0.0\n",
            file_get_contents(self::CALL_LOG),
            'An upgrade must call update() on the module, not install() again.',
        );
    }

    public function testUpgradeRemovesTheFilesTheNewVersionDropped(): void
    {
        $this->installSampleModule('1.0.0', ['Config/dropped-in-2.0.0.txt' => 'obsolete']);

        $droppedFile = THELIA_MODULE_DIR.self::SAMPLE_CODE.DS.'Config'.DS.'dropped-in-2.0.0.txt';
        self::assertFileExists($droppedFile);

        $this->upgradeSampleModule('2.0.0');

        self::assertFileDoesNotExist(
            $droppedFile,
            'The files the new version no longer ships must be removed, or their classes stay autoloadable.',
        );
    }

    public function testUpgradeOfAModuleInstalledLocallyStaysInLocalModules(): void
    {
        $localDirectory = THELIA_LOCAL_MODULE_DIR.self::LOCAL_SAMPLE_CODE;

        $this->writeModuleFiles($localDirectory, self::LOCAL_SAMPLE_CODE, self::LOCAL_SAMPLE_NAMESPACE, '1.0.0');

        $module = new Module();
        $module
            ->setCode(self::LOCAL_SAMPLE_CODE)
            ->setFullNamespace(self::LOCAL_SAMPLE_NAMESPACE)
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_NOT_ACTIVATED)
            ->save();

        $this->dispatchInstall(
            self::LOCAL_SAMPLE_CODE,
            self::LOCAL_SAMPLE_NAMESPACE,
            $this->buildArchive(self::LOCAL_SAMPLE_CODE, self::LOCAL_SAMPLE_NAMESPACE, '2.0.0'),
            '2.0.0',
        );

        self::assertDirectoryDoesNotExist(
            THELIA_MODULE_DIR.self::LOCAL_SAMPLE_CODE,
            'Upgrading a module installed in local/modules must not leave a second copy in vendor/thelia/modules.',
        );
        self::assertStringContainsString(
            '<version>2.0.0</version>',
            file_get_contents($localDirectory.DS.'Config'.DS.'module.xml'),
            'The new files must land where the module already lives.',
        );
    }

    public function testReinstallingTheSameVersionRegistersTheHooksTheModuleDeclares(): void
    {
        $this->installSampleModule('1.0.0');

        self::assertNotNull(HookQuery::create()->findOneByCode(self::DECLARED_HOOK_CODE));

        HookQuery::create()->filterByCode(self::DECLARED_HOOK_CODE)->delete();

        $this->upgradeSampleModule('1.0.0');

        self::assertNotNull(
            HookQuery::create()->findOneByCode(self::DECLARED_HOOK_CODE),
            'Reinstalling an archive of the same version must still register the hooks the module declares.',
        );
    }

    public function testInstallRefusesAnArchiveThatRenamesTheModuleCode(): void
    {
        $module = $this->installSampleModule('1.0.0');

        $this->expectExceptionMessageMatches('/already installed as "'.self::SAMPLE_CODE.'"/');

        $this->dispatchInstall(
            'ZipUpgradeRenamed',
            self::SAMPLE_NAMESPACE,
            $this->buildArchive(self::SAMPLE_CODE, self::SAMPLE_NAMESPACE, '2.0.0'),
            '2.0.0',
        );

        self::assertSame('1.0.0', ModuleQuery::create()->findPk($module->getId())->getVersion());
    }

    private function installSampleModule(string $version, array $extraFiles = []): Module
    {
        return $this->dispatchInstall(
            self::SAMPLE_CODE,
            self::SAMPLE_NAMESPACE,
            $this->buildArchive(self::SAMPLE_CODE, self::SAMPLE_NAMESPACE, $version, $extraFiles),
            $version,
        );
    }

    private function upgradeSampleModule(string $version): Module
    {
        return $this->dispatchInstall(
            self::SAMPLE_CODE,
            self::SAMPLE_NAMESPACE,
            $this->buildArchive(self::SAMPLE_CODE, self::SAMPLE_NAMESPACE, $version),
            $version,
        );
    }

    private function dispatchInstall(string $code, string $namespace, string $archivePath, string $version): Module
    {
        $definition = new ModuleDefinition();
        $definition->setCode($code);
        $definition->setNamespace($namespace);
        $definition->setVersion($version);

        $event = new ModuleInstallEvent();
        $event->setModuleDefinition($definition);
        $event->setModulePath($archivePath);

        $this->dispatch($event, TheliaEvents::MODULE_INSTALL);

        return $event->getModule();
    }

    /**
     * Writes the directory an administrator would get by unzipping an uploaded archive.
     *
     * @param array<string, string> $extraFiles paths relative to the module directory
     */
    private function buildArchive(string $code, string $namespace, string $version, array $extraFiles = []): string
    {
        $archiveDirectory = sys_get_temp_dir().DS.uniqid('thelia-module-archive-', true);
        $this->archiveDirectories[] = $archiveDirectory;

        $moduleDirectory = $archiveDirectory.DS.$code;
        $this->writeModuleFiles($moduleDirectory, $code, $namespace, $version, $extraFiles);

        return $moduleDirectory;
    }

    /**
     * @param array<string, string> $extraFiles
     */
    private function writeModuleFiles(
        string $moduleDirectory,
        string $code,
        string $namespace,
        string $version,
        array $extraFiles = [],
    ): void {
        [$moduleNamespace, $className] = explode('\\', $namespace);

        $this->filesystem->dumpFile(
            $moduleDirectory.DS.$className.'.php',
            $this->moduleClassSource($moduleNamespace, $className),
        );

        $this->filesystem->dumpFile(
            $moduleDirectory.DS.'Config'.DS.'module.xml',
            $this->moduleDescriptorSource($namespace, $code, $version),
        );

        foreach ($extraFiles as $relativePath => $contents) {
            $this->filesystem->dumpFile($moduleDirectory.DS.str_replace('/', DS, $relativePath), $contents);
        }
    }

    private function moduleClassSource(string $moduleNamespace, string $className): string
    {
        $callLog = var_export(self::CALL_LOG, true);
        $hookCode = var_export(self::DECLARED_HOOK_CODE, true);

        return <<<PHP
            <?php

            namespace {$moduleNamespace};

            use Propel\\Runtime\\Connection\\ConnectionInterface;
            use Symfony\\Component\\HttpFoundation\\Response;
            use Thelia\\Core\\Template\\TemplateDefinition;
            use Thelia\\Model\\Order;
            use Thelia\\Module\\BaseModule;
            use Thelia\\Module\\PaymentModuleInterface;

            class {$className} extends BaseModule implements PaymentModuleInterface
            {
                public function install(?ConnectionInterface \$con = null): void
                {
                    file_put_contents({$callLog}, "install\\n", \\FILE_APPEND);
                }

                public function update(\$currentVersion, \$newVersion, ?ConnectionInterface \$con = null): void
                {
                    file_put_contents({$callLog}, "update \$currentVersion \$newVersion\\n", \\FILE_APPEND);
                }

                public function getHooks(): array
                {
                    return [
                        [
                            'type' => TemplateDefinition::FRONT_OFFICE,
                            'code' => {$hookCode},
                            'title' => 'Zip upgrade sample hook',
                            'active' => true,
                        ],
                    ];
                }

                public function pay(Order \$order): ?Response
                {
                    return null;
                }

                public function isValidPayment(): bool
                {
                    return true;
                }

                public function manageStockOnCreation(): bool
                {
                    return true;
                }
            }

            PHP;
    }

    private function moduleDescriptorSource(string $namespace, string $code, string $version): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <module xmlns="http://thelia.net/schema/dic/module"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xsi:schemaLocation="http://thelia.net/schema/dic/module http://thelia.net/schema/dic/module/module-2_1.xsd">
                <fullnamespace>{$namespace}</fullnamespace>
                <descriptive locale="en_US">
                    <title>{$code} from the archive</title>
                </descriptive>
                <languages>
                    <language>en_US</language>
                </languages>
                <version>{$version}</version>
                <author>
                    <name>Thelia</name>
                    <email>info@thelia.net</email>
                </author>
                <type>payment</type>
                <thelia>2.5.5</thelia>
                <stability>alpha</stability>
            </module>

            XML;
    }

    /**
     * Installing a module reinitializes Propel, which drops the transaction this test case
     * rolls back: the sample modules can survive a test. Clean them up on both ends so a
     * leftover row never decides the outcome of the next run.
     */
    private function removeSampleModules(): void
    {
        $modules = ModuleQuery::create()->filterByCode(self::SAMPLE_PREFIX.'%', Criteria::LIKE)->find();

        foreach ($modules as $module) {
            OrderQuery::create()->filterByPaymentModuleId($module->getId())->delete();
            $module->delete();
        }

        HookQuery::create()->filterByCode(self::DECLARED_HOOK_CODE)->delete();

        $this->filesystem->remove(array_merge(
            $this->archiveDirectories,
            glob(THELIA_MODULE_DIR.self::SAMPLE_PREFIX.'*') ?: [],
            glob(THELIA_LOCAL_MODULE_DIR.self::SAMPLE_PREFIX.'*') ?: [],
            [self::CALL_LOG],
        ));

        $this->archiveDirectories = [];
    }
}
