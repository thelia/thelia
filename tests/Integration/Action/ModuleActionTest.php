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
use Thelia\Core\Event\Module\ModuleDeleteEvent;
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
use Thelia\Model\LangQuery;
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
use Thelia\Module\ModuleManagement;
use Thelia\Module\Validator\ModuleDefinition;
use Thelia\Test\ActionIntegrationTestCase;
use Thelia\Test\TestToken;

final class ModuleActionTest extends ActionIntegrationTestCase
{
    private const SAMPLE_PREFIX = 'ZipUpgrade';

    private const DECLARED_HOOK_CODE = 'zipupgradesample.declared';

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
            ->setClassname(self::sampleCode().'\Hook\SampleHook')
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
            ->setClassname(self::sampleCode().'\Hook\SampleHook')
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

        $order = $this->factory->order(null, ['paymentModuleCode' => self::sampleCode()]);
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

    /**
     * A module an order references could not be deleted at all: the order table points at it
     * through two foreign keys, and a guard turned the constraint violation into an error
     * message. The order now keeps the name the module carried and lets the reference go, so
     * the shop can clear out a payment or delivery method it stopped using.
     */
    public function testDeletingAModuleUsedByAnOrderFreezesItsNameOnTheOrder(): void
    {
        $module = $this->installSampleModule('1.0.0');
        $moduleId = $module->getId();

        $order = $this->factory->order(null, [
            'paymentModuleCode' => self::sampleCode(),
            'deliveryModuleCode' => self::sampleCode(),
        ]);
        $orderId = $order->getId();

        // The name to keep is the one the module carries in the language of the order.
        $locale = LangQuery::create()->findPk($order->getLangId())->getLocale();
        $module->setLocale($locale)->setTitle('Sample payment method')->save();

        $this->dispatch(new ModuleDeleteEvent($moduleId), TheliaEvents::MODULE_DELETE);

        self::assertNull(
            ModuleQuery::create()->findPk($moduleId),
            'A module referenced by an order must be deletable.',
        );

        $reloaded = OrderQuery::create()->findPk($orderId);
        self::assertNotNull($reloaded, 'Deleting a module must not take the orders that used it away.');
        self::assertNull($reloaded->getPaymentModuleId());
        self::assertNull($reloaded->getDeliveryModuleId());
        self::assertSame(
            'Sample payment method',
            $reloaded->getPaymentModuleTitle(),
            'The order must keep the name of the payment module it used.',
        );
        self::assertSame(
            'Sample payment method',
            $reloaded->getDeliveryModuleTitle(),
            'The order must keep the name of the delivery module it used.',
        );

        // Installing a module reinitializes Propel and drops the transaction this test case
        // rolls back. The order no longer names a module, so the cleanup in tearDown cannot
        // find it: take it away here.
        OrderQuery::create()->filterById($orderId)->delete();
    }

    public function testUpgradeCallsUpdateThenPostUpdateOnceCommittedAndNotInstall(): void
    {
        $this->installSampleModule('1.0.0');

        self::assertSame("install\n", file_get_contents(self::callLog()), 'An install does not call postUpdate().');
        file_put_contents(self::callLog(), '');

        $this->upgradeSampleModule('2.0.0');

        // The calls log the nesting level of the Propel transaction: the test case may run
        // inside a transaction of its own.
        self::assertMatchesRegularExpression(
            '/\Aupdate 1\.0\.0 2\.0\.0 (\d+)\npostUpdate 1\.0\.0 2\.0\.0 (\d+)\n\z/',
            (string) file_get_contents(self::callLog()),
            'An upgrade calls update(), then postUpdate(), never install().',
        );
        preg_match_all('/ (\d+)$/m', (string) file_get_contents(self::callLog()), $levels);
        self::assertSame((int) $levels[1][0] - 1, (int) $levels[1][1], 'postUpdate() runs once the transaction of the update is committed.');
    }

    public function testUpgradeRemovesTheFilesTheNewVersionDropped(): void
    {
        $this->installSampleModule('1.0.0', ['Config/dropped-in-2.0.0.txt' => 'obsolete']);

        $droppedFile = THELIA_MODULE_DIR.self::sampleCode().DS.'Config'.DS.'dropped-in-2.0.0.txt';
        self::assertFileExists($droppedFile);

        $this->upgradeSampleModule('2.0.0');

        self::assertFileDoesNotExist(
            $droppedFile,
            'The files the new version no longer ships must be removed, or their classes stay autoloadable.',
        );
    }

    public function testUpgradeOfAModuleInstalledLocallyStaysInLocalModules(): void
    {
        $localDirectory = THELIA_LOCAL_MODULE_DIR.self::localSampleCode();

        $this->writeModuleFiles($localDirectory, self::localSampleCode(), self::localSampleNamespace(), '1.0.0');

        $module = new Module();
        $module
            ->setCode(self::localSampleCode())
            ->setFullNamespace(self::localSampleNamespace())
            ->setVersion('1.0.0')
            ->setType(BaseModule::PAYMENT_MODULE_TYPE)
            ->setCategory('payment')
            ->setActivate(BaseModule::IS_NOT_ACTIVATED)
            ->save();

        $this->dispatchInstall(
            self::localSampleCode(),
            self::localSampleNamespace(),
            $this->buildArchive(self::localSampleCode(), self::localSampleNamespace(), '2.0.0'),
            '2.0.0',
        );

        self::assertDirectoryDoesNotExist(
            THELIA_MODULE_DIR.self::localSampleCode(),
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

    /**
     * The back-office module list refreshes every module on every visit. Registering the hooks
     * there dispatches a hook create or update event, and those clear the kernel cache: a page
     * of the back office would wipe the cache each time it is opened.
     */
    public function testRefreshingAModuleDoesNotRegisterItsHooksWhenNothingChanged(): void
    {
        $this->installSampleModule('1.0.0');

        HookQuery::create()->filterByCode(self::DECLARED_HOOK_CODE)->delete();

        $this->getService(ModuleManagement::class)->updateModule(
            new \SplFileInfo(THELIA_MODULE_DIR.self::sampleCode().DS.'Config'.DS.'module.xml'),
            static::getContainer(),
        );

        self::assertNull(
            HookQuery::create()->findOneByCode(self::DECLARED_HOOK_CODE),
            'Refreshing an unchanged module must not touch its hooks.',
        );
    }

    public function testInstallRefusesAnArchiveThatRenamesTheModuleCode(): void
    {
        $module = $this->installSampleModule('1.0.0');

        $this->expectExceptionMessageMatches('/already installed as "'.self::sampleCode().'"/');

        $this->dispatchInstall(
            self::samplePrefix().'Renamed',
            self::sampleNamespace(),
            $this->buildArchive(self::sampleCode(), self::sampleNamespace(), '2.0.0'),
            '2.0.0',
        );

        self::assertSame('1.0.0', ModuleQuery::create()->findPk($module->getId())->getVersion());
    }

    /**
     * The sample modules land in vendor/thelia/modules and local/modules, which every test
     * process shares: a process running with a TEST_TOKEN names them after its number
     * (ZipUpgrade2Sample), so that one process never removes or rewrites the module another
     * one is installing. The number sits between the prefix and a capital letter, so the
     * cleanup of one process cannot match the modules of another (2 and 21 included).
     * Without a token, the names stay ZipUpgradeSample and its siblings.
     */
    private static function samplePrefix(): string
    {
        return self::SAMPLE_PREFIX.(TestToken::current() ?? '');
    }

    private static function sampleCode(): string
    {
        return self::samplePrefix().'Sample';
    }

    private static function sampleNamespace(): string
    {
        return self::sampleCode().'\\'.self::sampleCode();
    }

    private static function localSampleCode(): string
    {
        return self::samplePrefix().'LocalSample';
    }

    private static function localSampleNamespace(): string
    {
        return self::localSampleCode().'\\'.self::localSampleCode();
    }

    /**
     * Where the sample module records the lifecycle methods Thelia calls on it, one file per
     * test process.
     */
    private static function callLog(): string
    {
        return '/tmp/thelia-zip-upgrade-sample-calls'.TestToken::suffix().'.log';
    }

    private function installSampleModule(string $version, array $extraFiles = []): Module
    {
        return $this->dispatchInstall(
            self::sampleCode(),
            self::sampleNamespace(),
            $this->buildArchive(self::sampleCode(), self::sampleNamespace(), $version, $extraFiles),
            $version,
        );
    }

    private function upgradeSampleModule(string $version): Module
    {
        return $this->dispatchInstall(
            self::sampleCode(),
            self::sampleNamespace(),
            $this->buildArchive(self::sampleCode(), self::sampleNamespace(), $version),
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
        $callLog = var_export(self::callLog(), true);
        $hookCode = var_export(self::DECLARED_HOOK_CODE, true);

        return <<<PHP
            <?php

            namespace {$moduleNamespace};

            use Propel\\Runtime\\Connection\\ConnectionInterface;
            use Propel\\Runtime\\Propel;
            use Symfony\\Component\\HttpFoundation\\Response;
            use Thelia\\Core\\Template\\TemplateDefinition;
            use Thelia\\Model\\Map\\ModuleTableMap;
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
                    \$level = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME)->getNestedTransactionCount();
                    file_put_contents({$callLog}, "update \$currentVersion \$newVersion \$level\\n", \\FILE_APPEND);
                }

                public function postUpdate(string \$currentVersion, string \$newVersion): void
                {
                    \$level = Propel::getWriteConnection(ModuleTableMap::DATABASE_NAME)->getNestedTransactionCount();
                    file_put_contents({$callLog}, "postUpdate \$currentVersion \$newVersion \$level\\n", \\FILE_APPEND);
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

                public function supportsPaymentRetry(): bool
                {
                    return false;
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
        $modules = ModuleQuery::create()->filterByCode(self::samplePrefix().'%', Criteria::LIKE)->find();

        foreach ($modules as $module) {
            OrderQuery::create()->filterByPaymentModuleId($module->getId())->delete();
            $module->delete();
        }

        HookQuery::create()->filterByCode(self::DECLARED_HOOK_CODE)->delete();

        $this->filesystem->remove(array_merge(
            $this->archiveDirectories,
            glob(THELIA_MODULE_DIR.self::samplePrefix().'[A-Z]*') ?: [],
            glob(THELIA_LOCAL_MODULE_DIR.self::samplePrefix().'[A-Z]*') ?: [],
            [self::callLog()],
        ));

        $this->archiveDirectories = [];
    }
}
