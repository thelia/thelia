<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Tests\Action;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Action\Hook as HookAction;
use Thelia\Action\Module as ModuleAction;
use Thelia\Core\Event\Module\ModuleInstallEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Area;
use Thelia\Model\AreaDeliveryModule;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\AreaQuery;
use Thelia\Model\HookQuery;
use Thelia\Model\IgnoredModuleHook;
use Thelia\Model\IgnoredModuleHookQuery;
use Thelia\Model\Module as ModuleModel;
use Thelia\Model\ModuleConfig;
use Thelia\Model\ModuleConfigQuery;
use Thelia\Model\ModuleHook;
use Thelia\Model\ModuleHookQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Profile;
use Thelia\Model\ProfileModule;
use Thelia\Model\ProfileModuleQuery;
use Thelia\Model\ProfileQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\Validator\ModuleDefinition;

/**
 * Uploading a zip of a module that is already installed is an upgrade, not a fresh install: the
 * module row is kept. Deleting it, as Thelia used to, cascades on ten foreign keys and takes the
 * hooks and their positions, the configuration, the administrator access rights, the delivery
 * areas and the coupon conditions with it — and hands the module a new id.
 */
class ModuleInstallTest extends BaseAction
{
    public const SAMPLE_PREFIX = 'ZipUpgrade';

    public const SAMPLE_CODE = 'ZipUpgradeSample';

    public const SAMPLE_NAMESPACE = 'ZipUpgradeSample\ZipUpgradeSample';

    public const DECLARED_HOOK_CODE = 'zipupgradesample.declared';

    public const SAMPLE_AREA_NAME = 'Zip upgrade sample area';

    /**
     * Where the sample module records the lifecycle methods Thelia calls on it.
     */
    public const CALL_LOG = '/tmp/thelia-zip-upgrade-sample-calls.log';

    /** @var EventDispatcher */
    private $dispatcher;

    /** @var ModuleAction */
    private $action;

    /** @var Filesystem */
    private $filesystem;

    /** @var array */
    private $archiveDirectories = [];

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();

        $container = new ContainerBuilder();
        $container->setParameter('kernel.cache_dir', THELIA_CACHE_DIR);
        $container->set('thelia.translator', Translator::getInstance());

        $this->dispatcher = new EventDispatcher();
        $container->set('event_dispatcher', $this->dispatcher);

        $this->action = new ModuleAction($container);

        $this->dispatcher->addListener(TheliaEvents::MODULE_TOGGLE_ACTIVATION, [$this->action, 'checkToggleActivation'], 255);
        $this->dispatcher->addListener(TheliaEvents::MODULE_TOGGLE_ACTIVATION, [$this->action, 'toggleActivation'], 128);
        // The listener an upgrade must no longer reach. Without it here these tests would pass
        // against the code they cover: nothing would delete the row they check for.
        $this->dispatcher->addListener(TheliaEvents::MODULE_DELETE, [$this->action, 'delete'], 128);
        $this->dispatcher->addListener(TheliaEvents::CACHE_CLEAR, function (): void {});
        // registerHooks() goes through these two to declare the hooks a module ships with.
        $this->dispatcher->addSubscriber(new HookAction(THELIA_CACHE_DIR, $this->dispatcher));

        $this->removeSampleModules();
    }

    protected function tearDown(): void
    {
        $this->removeSampleModules();
    }

    public function testUpgradeKeepsTheModuleRow(): void
    {
        $installed = $this->installSampleModule('1.0.0');
        $moduleId = $installed->getId();

        $upgraded = $this->upgradeSampleModule('2.0.0');

        self::assertSame(
            $moduleId,
            $upgraded->getId(),
            'Upgrading a module must keep its row: a new id discards everything the foreign keys cascade on.'
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
            'A hook the administrator removed must not come back after an upgrade.'
        );
    }

    public function testUpgradeKeepsTheAdministratorAccessAndDeliveryAreas(): void
    {
        $module = $this->installSampleModule('1.0.0');

        $profile = new Profile();
        $profile->setCode(self::SAMPLE_PREFIX.'Profile')->save();

        $profileModule = new ProfileModule();
        $profileModule
            ->setProfileId($profile->getId())
            ->setModuleId($module->getId())
            ->setAccess(1)
            ->save();

        $area = new Area();
        $area->setName(self::SAMPLE_AREA_NAME)->save();

        $areaDeliveryModule = new AreaDeliveryModule();
        $areaDeliveryModule
            ->setAreaId($area->getId())
            ->setDeliveryModuleId($module->getId())
            ->save();

        $this->upgradeSampleModule('2.0.0');

        self::assertSame(
            1,
            ProfileModuleQuery::create()->filterByModuleId($module->getId())->count(),
            'The access an administrator profile has on a module must survive an upgrade.'
        );
        self::assertSame(
            1,
            AreaDeliveryModuleQuery::create()->filterByDeliveryModuleId($module->getId())->count(),
            'The delivery areas a module is attached to must survive an upgrade.'
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
            'An upgrade must not overwrite the title with the one from the archive descriptor.'
        );
    }

    /**
     * Installing a newer version of an already installed module used to delete its row first,
     * which the order table refuses for a payment module. Upgrading in place no longer reaches
     * that constraint: the shop can upgrade the payment module it has taken orders with.
     */
    public function testUpgradeSucceedsForAnActivatedPaymentModule(): void
    {
        $module = $this->installSampleModule('1.0.0');
        $module->setActivate(BaseModule::IS_ACTIVATED)->save();

        $upgraded = $this->upgradeSampleModule('2.0.0');

        self::assertSame($module->getId(), $upgraded->getId());
        self::assertSame('2.0.0', $upgraded->getVersion());
        self::assertSame(
            BaseModule::IS_ACTIVATED,
            ModuleQuery::create()->findPk($module->getId())->getActivate(),
            'An upgraded payment module must be left activated, not silently switched off.'
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
            'An upgrade must call update() on the module, not install() again.'
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
            'The files the new version no longer ships must be removed, or their classes stay autoloadable.'
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
            'Reinstalling an archive of the same version must still register the hooks the module declares.'
        );
    }

    public function testInstallRefusesAnArchiveThatRenamesTheModuleCode(): void
    {
        $module = $this->installSampleModule('1.0.0');

        $this->expectExceptionMessageMatches('/already installed as "'.self::SAMPLE_CODE.'"/');

        $this->install(
            self::SAMPLE_PREFIX.'Renamed',
            self::SAMPLE_NAMESPACE,
            $this->buildArchive(self::SAMPLE_CODE, self::SAMPLE_NAMESPACE, '2.0.0'),
            '2.0.0'
        );

        self::assertSame('1.0.0', ModuleQuery::create()->findPk($module->getId())->getVersion());
    }

    /**
     * @return ModuleModel
     */
    private function installSampleModule($version, array $extraFiles = [])
    {
        return $this->install(
            self::SAMPLE_CODE,
            self::SAMPLE_NAMESPACE,
            $this->buildArchive(self::SAMPLE_CODE, self::SAMPLE_NAMESPACE, $version, $extraFiles),
            $version
        );
    }

    /**
     * @return ModuleModel
     */
    private function upgradeSampleModule($version)
    {
        return $this->install(
            self::SAMPLE_CODE,
            self::SAMPLE_NAMESPACE,
            $this->buildArchive(self::SAMPLE_CODE, self::SAMPLE_NAMESPACE, $version),
            $version
        );
    }

    /**
     * @return ModuleModel
     */
    private function install($code, $namespace, $archivePath, $version)
    {
        $definition = new ModuleDefinition();
        $definition->setCode($code);
        $definition->setNamespace($namespace);
        $definition->setVersion($version);

        $event = new ModuleInstallEvent();
        $event->setModuleDefinition($definition);
        $event->setModulePath($archivePath);

        $this->action->install($event, TheliaEvents::MODULE_INSTALL, $this->dispatcher);

        return $event->getModule();
    }

    /**
     * Writes the directory an administrator would get by unzipping an uploaded archive.
     *
     * @return string
     */
    private function buildArchive($code, $namespace, $version, array $extraFiles = [])
    {
        $archiveDirectory = sys_get_temp_dir().DS.uniqid('thelia-module-archive-', true);
        $this->archiveDirectories[] = $archiveDirectory;

        $moduleDirectory = $archiveDirectory.DS.$code;

        [$moduleNamespace, $className] = explode('\\', $namespace);

        $this->filesystem->dumpFile(
            $moduleDirectory.DS.$className.'.php',
            $this->moduleClassSource($moduleNamespace, $className)
        );

        $this->filesystem->dumpFile(
            $moduleDirectory.DS.'Config'.DS.'module.xml',
            $this->moduleDescriptorSource($namespace, $code, $version)
        );

        foreach ($extraFiles as $relativePath => $contents) {
            $this->filesystem->dumpFile($moduleDirectory.DS.str_replace('/', DS, $relativePath), $contents);
        }

        return $moduleDirectory;
    }

    /**
     * @return string
     */
    private function moduleClassSource($moduleNamespace, $className)
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
    public function install(ConnectionInterface \$con = null): void
    {
        file_put_contents({$callLog}, "install\\n", FILE_APPEND);
    }

    public function update(\$currentVersion, \$newVersion, ConnectionInterface \$con = null): void
    {
        file_put_contents({$callLog}, "update \$currentVersion \$newVersion\\n", FILE_APPEND);
    }

    public function getHooks()
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

    public function pay(Order \$order)
    {
        return null;
    }

    public function isValidPayment()
    {
        return true;
    }

    public function manageStockOnCreation()
    {
        return true;
    }
}

PHP;
    }

    /**
     * @return string
     */
    private function moduleDescriptorSource($namespace, $code, $version)
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

    private function removeSampleModules(): void
    {
        $modules = ModuleQuery::create()->filterByCode(self::SAMPLE_PREFIX.'%', Criteria::LIKE)->find();

        foreach ($modules as $module) {
            $module->delete();
        }

        HookQuery::create()->filterByCode(self::DECLARED_HOOK_CODE)->delete();
        ProfileQuery::create()->filterByCode(self::SAMPLE_PREFIX.'%', Criteria::LIKE)->delete();
        AreaQuery::create()->filterByName(self::SAMPLE_AREA_NAME)->delete();

        $this->filesystem->remove(array_merge(
            $this->archiveDirectories,
            glob(THELIA_MODULE_DIR.self::SAMPLE_PREFIX.'*') ?: [],
            [self::CALL_LOG]
        ));

        $this->archiveDirectories = [];
    }
}
