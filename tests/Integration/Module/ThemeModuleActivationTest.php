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

namespace Thelia\Tests\Integration\Module;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\ModuleManagement;
use Thelia\Test\IntegrationTestCase;

/**
 * Applying a theme (template:set) installs the modules the theme's composer.json requires.
 * Only a module the theme brings with it is activated on that occasion: a module the shop
 * already knows keeps its state, whether its descriptor says it ships inactive or the
 * merchant switched it off. The install output says so in both cases.
 */
final class ThemeModuleActivationTest extends IntegrationTestCase
{
    // Activating a module reinitializes Propel, which drops the transaction the base
    // class rolls back: clean up by hand on both ends instead.
    protected bool $useTransaction = false;

    private const string SAMPLE_PREFIX = 'ThemeShipSample';

    private const string NEW_CODE = 'ThemeShipSampleNew';

    private const string INACTIVE_CODE = 'ThemeShipSampleInactive';

    private const string SWITCHED_OFF_CODE = 'ThemeShipSampleSwitchedOff';

    private const string PARENT_CODE = 'ThemeShipSampleParent';

    private Filesystem $filesystem;

    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->workDir = sys_get_temp_dir().'/thelia-theme-activation-'.bin2hex(random_bytes(4));
        $this->removeSampleModules();
    }

    protected function tearDown(): void
    {
        $this->removeSampleModules();
        $this->filesystem->remove($this->workDir);

        parent::tearDown();
    }

    public function testApplyingAThemeOnlyActivatesTheModulesItBrings(): void
    {
        // Brought by the theme: not on the shop's disk, unknown to the module table.
        $this->writeSampleModule($this->themeVendorDir().'/thelia/modules/'.self::NEW_CODE, self::NEW_CODE, '');
        // Registered by the install, declared to ship inactive.
        $this->writeSampleModule(THELIA_MODULE_DIR.self::INACTIVE_CODE, self::INACTIVE_CODE, '<enabled-by-default>0</enabled-by-default>');
        $this->registerSampleModule(self::INACTIVE_CODE);
        // Registered by the install as active, switched off by the merchant since.
        $this->writeSampleModule(THELIA_MODULE_DIR.self::SWITCHED_OFF_CODE, self::SWITCHED_OFF_CODE, '');
        $this->registerSampleModule(self::SWITCHED_OFF_CODE);
        $themeDir = $this->writeTheme([self::NEW_CODE, self::INACTIVE_CODE, self::SWITCHED_OFF_CODE]);

        $output = new BufferedOutput();
        /** @var ModuleManagement $moduleManagement */
        $moduleManagement = $this->getService(ModuleManagement::class);
        $moduleManagement->installModulesFromTemplatePath($themeDir, $output);
        $written = $output->fetch();

        self::assertSame(BaseModule::IS_ACTIVATED, $this->activationOf(self::NEW_CODE), 'A module the theme brings is installed and activated.');
        self::assertDirectoryExists(THELIA_MODULE_DIR.self::NEW_CODE, 'The module the theme brings is copied where the shop keeps its modules.');
        self::assertSame(BaseModule::IS_NOT_ACTIVATED, $this->activationOf(self::INACTIVE_CODE), 'A module declaring <enabled-by-default>0</enabled-by-default> stays inactive even though the theme requires it.');
        self::assertStringContainsString(self::INACTIVE_CODE.' is required by the theme but ships inactive', $written);
        self::assertSame(BaseModule::IS_NOT_ACTIVATED, $this->activationOf(self::SWITCHED_OFF_CODE), 'A module the merchant switched off is not switched back on by the theme.');
        self::assertStringContainsString(self::SWITCHED_OFF_CODE.' is required by the theme but was switched off', $written);
    }

    /**
     * Characterisation of an existing rule the new element does not change: a module
     * listed under <required> by a module being activated is activated with it, whatever
     * its own descriptor says. A hard dependency is what the depending module cannot run
     * without.
     */
    public function testAHardDependencyIsActivatedEvenIfItShipsInactive(): void
    {
        $this->writeSampleModule(THELIA_MODULE_DIR.self::INACTIVE_CODE, self::INACTIVE_CODE, '<enabled-by-default>0</enabled-by-default>');
        $this->registerSampleModule(self::INACTIVE_CODE);
        $this->writeSampleModule(THELIA_MODULE_DIR.self::PARENT_CODE, self::PARENT_CODE, '', '<required><module>'.self::INACTIVE_CODE.'</module></required>');
        $this->registerSampleModule(self::PARENT_CODE);

        // The same event ModuleManagement::install() dispatches: the dependency check is
        // what carries the recursive activation (Action\Module::checkToggleActivation).
        $event = new ModuleToggleActivationEvent((int) ModuleQuery::create()->findOneByCode(self::PARENT_CODE)?->getId());
        $event->setNoCheck(false);
        $event->setRecursive(true);
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getService('event_dispatcher');
        $dispatcher->dispatch($event, TheliaEvents::MODULE_TOGGLE_ACTIVATION);

        self::assertSame(BaseModule::IS_ACTIVATED, $this->activationOf(self::PARENT_CODE));
        self::assertSame(BaseModule::IS_ACTIVATED, $this->activationOf(self::INACTIVE_CODE), 'The dependency of an activated module is activated with it, even though it ships inactive.');
    }

    private function activationOf(string $code): int
    {
        $module = ModuleQuery::create()->findOneByCode($code);
        self::assertInstanceOf(Module::class, $module, \sprintf('Module %s is not registered.', $code));
        $module->reload();

        return $module->getActivate();
    }

    /**
     * A module with a real class, so the activation path can instantiate it once the
     * module lives in vendor/thelia/modules, where the autoloader looks.
     */
    private function writeSampleModule(string $moduleDir, string $code, string $declaration, string $required = ''): void
    {
        $this->filesystem->dumpFile($moduleDir.DS.$code.'.php', <<<PHP
            <?php

            namespace {$code};

            use Thelia\\Module\\BaseModule;

            class {$code} extends BaseModule
            {
            }

            PHP);

        $this->filesystem->dumpFile($moduleDir.DS.'Config'.DS.'module.xml', <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <module xmlns="http://thelia.net/schema/dic/module"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xsi:schemaLocation="http://thelia.net/schema/dic/module http://thelia.net/schema/dic/module/module-2_2.xsd">
                <fullnamespace>{$code}\\{$code}</fullnamespace>
                <descriptive locale="en_US">
                    <title>{$code}</title>
                </descriptive>
                <languages>
                    <language>en_US</language>
                </languages>
                <version>1.0.0</version>
                <type>classic</type>
                {$required}
                <thelia>3.0.0</thelia>
                <stability>prod</stability>
                <mandatory>0</mandatory>
                <hidden>0</hidden>
                {$declaration}
            </module>

            XML);

        $this->filesystem->dumpFile($moduleDir.DS.'Config'.DS.'config.xml', <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <config xmlns="http://thelia.net/schema/dic/config"
                    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xsi:schemaLocation="http://thelia.net/schema/dic/config http://thelia.net/schema/dic/config/thelia-1.0.xsd">
            </config>

            XML);
    }

    /**
     * The row a fresh install writes for the module before any theme is applied, here
     * left inactive: either the descriptor asked for it, or the merchant switched it off.
     */
    private function registerSampleModule(string $code): void
    {
        $module = new Module();
        $module
            ->setCode($code)
            ->setVersion('1.0.0')
            ->setType(BaseModule::CLASSIC_MODULE_TYPE)
            ->setCategory('classic')
            ->setActivate(BaseModule::IS_NOT_ACTIVATED)
            ->setFullNamespace($code.'\\'.$code)
            ->setPosition((int) ModuleQuery::create()->orderByPosition(Criteria::DESC)->select('position')->findOne() + 1)
            ->save();
    }

    private function themeVendorDir(): string
    {
        return $this->workDir.'/vendor';
    }

    /**
     * A theme directory whose composer.json requires the sample modules, with its own
     * vendor directory so the installed.json Composer would have written can be forged.
     * A module the shop already has is reached through a symlink to its real directory;
     * the module the theme brings lives in that vendor directory only.
     *
     * @param string[] $codes
     */
    private function writeTheme(array $codes): string
    {
        $vendorDir = $this->themeVendorDir();
        $themeDir = $this->workDir.'/theme';
        $require = [];
        $packages = [];

        foreach ($codes as $code) {
            $packageName = 'thelia-tests/'.strtolower($code).'-module';
            $require[$packageName] = '*';
            $packages[] = [
                'name' => $packageName,
                'version' => '1.0.0',
                'type' => 'thelia-module',
                'install-path' => '/thelia/modules/'.$code,
            ];

            if (!is_dir($vendorDir.'/thelia/modules/'.$code)) {
                $this->filesystem->mkdir($vendorDir.'/thelia/modules');
                $this->filesystem->symlink(THELIA_MODULE_DIR.$code, $vendorDir.'/thelia/modules/'.$code);
            }
        }

        $this->filesystem->dumpFile($vendorDir.'/composer/installed.json', json_encode(['packages' => $packages], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT));
        $this->filesystem->dumpFile($themeDir.'/composer.json', json_encode([
            'name' => 'thelia-tests/theme-activation-sample',
            'type' => 'thelia-backoffice-template',
            'require' => $require,
            'config' => ['vendor-dir' => $vendorDir],
        ], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT));

        return $themeDir;
    }

    private function removeSampleModules(): void
    {
        ModuleQuery::create()->filterByCode(self::SAMPLE_PREFIX.'%', Criteria::LIKE)->delete();
        ModuleQuery::resetActivated();

        $this->filesystem->remove(glob(THELIA_MODULE_DIR.self::SAMPLE_PREFIX.'*') ?: []);
    }
}
