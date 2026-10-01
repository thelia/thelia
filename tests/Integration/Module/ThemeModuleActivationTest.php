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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Command\SetTemplate;
use Thelia\Core\Event\Module\ModuleToggleActivationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\TheliaTemplateHelper;
use Thelia\Domain\Module\Composer\ComposerHelper;
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

    private const string NEW_INACTIVE_CODE = 'ThemeShipSampleNewInactive';

    private const string INACTIVE_CODE = 'ThemeShipSampleInactive';

    private const string SWITCHED_OFF_CODE = 'ThemeShipSampleSwitchedOff';

    private const string PARENT_CODE = 'ThemeShipSampleParent';

    private const string FAILING_CODE = 'ThemeShipSampleFailing';

    // A class PHP loads once per process: a module whose class must fail to load needs a
    // code no other test declares a valid class for.
    private const string BROKEN_CODE = 'ThemeShipSampleBroken';

    private const string THEME_NAME = 'ThemeShipSampleTheme';

    private Filesystem $filesystem;

    private string $workDir;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // A run that dies before its tearDown leaves sample modules in vendor/thelia/modules,
        // where bin/test-prepare would register them. They are removed before the class
        // runs, and when the process ends however it ends short of a kill.
        self::removeSampleFiles();
        register_shutdown_function(self::removeSampleFiles(...));
    }

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
        // Brought by the theme too, and declared to ship inactive.
        $this->writeSampleModule($this->themeVendorDir().'/thelia/modules/'.self::NEW_INACTIVE_CODE, self::NEW_INACTIVE_CODE, '<enabled-by-default>0</enabled-by-default>');
        // Registered by the install, declared to ship inactive.
        $this->writeSampleModule(THELIA_MODULE_DIR.self::INACTIVE_CODE, self::INACTIVE_CODE, '<enabled-by-default>0</enabled-by-default>');
        $this->registerSampleModule(self::INACTIVE_CODE);
        // Registered by the install as active, switched off by the merchant since.
        $this->writeSampleModule(THELIA_MODULE_DIR.self::SWITCHED_OFF_CODE, self::SWITCHED_OFF_CODE, '');
        $this->registerSampleModule(self::SWITCHED_OFF_CODE);
        $themeDir = $this->writeTheme([self::NEW_CODE, self::NEW_INACTIVE_CODE, self::INACTIVE_CODE, self::SWITCHED_OFF_CODE]);

        $output = new BufferedOutput();
        /** @var ModuleManagement $moduleManagement */
        $moduleManagement = $this->getService(ModuleManagement::class);
        $modules = $moduleManagement->installModulesFromTemplatePath($themeDir, $output);
        $written = $output->fetch();

        self::assertSame(BaseModule::IS_ACTIVATED, $this->activationOf(self::NEW_CODE), 'A module the theme brings is installed and activated.');
        self::assertSame(BaseModule::IS_ACTIVATED, $this->statesOf($modules)[self::NEW_CODE] ?? null, 'The module handed back for the module the theme brings carries the state its activation wrote, not the one its installation did.');
        self::assertStringNotContainsString(self::NEW_CODE.' is required by the theme but', $written, 'A module the theme brings and activates is not reported inactive.');
        self::assertStringContainsString('Module '.self::NEW_CODE.' successfully installed and activated.', $written, 'A module the theme brings and activates is announced.');
        self::assertDirectoryExists(THELIA_MODULE_DIR.self::NEW_CODE, 'The module the theme brings is copied where the shop keeps its modules.');
        self::assertSame(BaseModule::IS_NOT_ACTIVATED, $this->activationOf(self::NEW_INACTIVE_CODE), 'A module the theme brings is registered but left inactive when its descriptor says so.');
        self::assertDirectoryExists(THELIA_MODULE_DIR.self::NEW_INACTIVE_CODE, 'The module the theme brings is copied even though it ships inactive.');
        self::assertStringContainsString(self::NEW_INACTIVE_CODE.' is required by the theme but ships inactive', $written);
        self::assertSame(BaseModule::IS_NOT_ACTIVATED, $this->activationOf(self::INACTIVE_CODE), 'A module declaring <enabled-by-default>0</enabled-by-default> stays inactive even though the theme requires it.');
        self::assertStringContainsString(self::INACTIVE_CODE.' is required by the theme but ships inactive', $written);
        self::assertSame(BaseModule::IS_NOT_ACTIVATED, $this->activationOf(self::SWITCHED_OFF_CODE), 'A module the merchant switched off is not switched back on by the theme.');
        self::assertStringContainsString(self::SWITCHED_OFF_CODE.' is required by the theme but is registered inactive', $written);
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

    /**
     * The theme lists a module it brings before the dependency that module requires, and
     * the dependency ships inactive: activating the first activates the second on the way.
     * Reached in its turn, the dependency is read in its current state, active, not in the
     * state the loop started with, so it is neither reported missing nor counted inactive.
     */
    public function testADependencyActivatedEarlierInTheLoopIsReadActiveInItsTurn(): void
    {
        $this->writeSampleModule($this->themeVendorDir().'/thelia/modules/'.self::PARENT_CODE, self::PARENT_CODE, '', '<required><module>'.self::INACTIVE_CODE.'</module></required>');
        $this->writeSampleModule(THELIA_MODULE_DIR.self::INACTIVE_CODE, self::INACTIVE_CODE, '<enabled-by-default>0</enabled-by-default>');
        $this->registerSampleModule(self::INACTIVE_CODE);
        $themeDir = $this->writeTheme([self::PARENT_CODE, self::INACTIVE_CODE]);

        $output = new BufferedOutput();
        /** @var ModuleManagement $moduleManagement */
        $moduleManagement = $this->getService(ModuleManagement::class);
        $modules = $moduleManagement->installModulesFromTemplatePath($themeDir, $output);
        $written = $output->fetch();

        self::assertSame(BaseModule::IS_ACTIVATED, $this->activationOf(self::PARENT_CODE), 'The module the theme brings is activated.');
        self::assertSame(BaseModule::IS_ACTIVATED, $this->activationOf(self::INACTIVE_CODE), 'Its dependency is activated with it, even though it ships inactive.');
        self::assertSame(BaseModule::IS_ACTIVATED, $this->statesOf($modules)[self::INACTIVE_CODE] ?? null, 'The module handed back for the dependency carries its current state.');
        self::assertStringNotContainsString(self::INACTIVE_CODE.' is required by the theme but', $written, 'A dependency activated earlier in the loop is not reported missing in its turn.');
        self::assertStringContainsString('Module '.self::PARENT_CODE.' successfully installed and activated.', $written);
        self::assertStringNotContainsString('Module '.self::INACTIVE_CODE.' successfully installed', $written, 'A dependency activated on the way is not announced: the theme did not bring it.');
    }

    /**
     * The theme lists the dependency before the module that requires it: the dependency is
     * met inactive, then activated by the module the theme brings. What the loop reports and
     * hands back is the state once every module has been handled, not the state met on the way.
     */
    public function testADependencyActivatedLaterInTheLoopIsReportedActive(): void
    {
        $this->writeSampleModule($this->themeVendorDir().'/thelia/modules/'.self::PARENT_CODE, self::PARENT_CODE, '', '<required><module>'.self::INACTIVE_CODE.'</module></required>');
        $this->writeSampleModule(THELIA_MODULE_DIR.self::INACTIVE_CODE, self::INACTIVE_CODE, '<enabled-by-default>0</enabled-by-default>');
        $this->registerSampleModule(self::INACTIVE_CODE);
        $themeDir = $this->writeTheme([self::INACTIVE_CODE, self::PARENT_CODE]);

        $output = new BufferedOutput();
        /** @var ModuleManagement $moduleManagement */
        $moduleManagement = $this->getService(ModuleManagement::class);
        $modules = $moduleManagement->installModulesFromTemplatePath($themeDir, $output);
        $written = $output->fetch();

        self::assertSame(BaseModule::IS_ACTIVATED, $this->activationOf(self::INACTIVE_CODE), 'The dependency is activated by the module listed after it.');
        self::assertSame(BaseModule::IS_ACTIVATED, $this->statesOf($modules)[self::INACTIVE_CODE] ?? null, 'The module handed back for the dependency carries the state the rest of the loop left it in.');
        self::assertStringNotContainsString(self::INACTIVE_CODE.' is required by the theme but', $written, 'A dependency activated later in the loop is not reported inactive.');
    }

    /**
     * template:set sums up what ModuleManagement did, then enables the theme. The command
     * is built by hand so that the theme switch itself (bundle registration, configuration
     * row) is observed on a double instead of moving the test shop to a forged theme.
     */
    public function testTheCommandSumsUpTheThemeModulesAndEnablesTheTheme(): void
    {
        $this->writeSampleModule($this->themeVendorDir().'/thelia/modules/'.self::NEW_CODE, self::NEW_CODE, '');
        $this->writeSampleModule(THELIA_MODULE_DIR.self::INACTIVE_CODE, self::INACTIVE_CODE, '<enabled-by-default>0</enabled-by-default>');
        $this->registerSampleModule(self::INACTIVE_CODE);
        $themePath = $this->installTheme([self::NEW_CODE, self::INACTIVE_CODE]);

        $templateHelper = $this->createMock(TheliaTemplateHelper::class);
        $templateHelper->expects(self::once())->method('enableThemeAsBundle')->with($themePath);
        $templateHelper->expects(self::once())->method('setConfigToTemplate')->with(self::anything(), self::THEME_NAME);
        // The autoloader of the project is not regenerated from a test.
        $composerHelper = $this->createMock(ComposerHelper::class);
        $composerHelper->expects(self::once())->method('dumpAutoload');

        $tester = new CommandTester($this->setTemplateCommand($templateHelper, $composerHelper));
        $tester->execute(['type' => 'backOffice', 'name' => self::THEME_NAME]);

        self::assertSame(SetTemplate::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString(self::INACTIVE_CODE.' is required by the theme but ships inactive', $tester->getDisplay());
        self::assertStringContainsString('2 theme modules found, 1 active.', $tester->getDisplay());
        self::assertStringContainsString('Autoload dump completed successfully', $tester->getDisplay());
        self::assertSame(BaseModule::IS_ACTIVATED, $this->activationOf(self::NEW_CODE));
    }

    /**
     * The schema reports each of its errors on a line of its own: template:set prints the
     * refusal on the ERROR line, as the install does, so that nothing a descriptor quotes
     * reads as a line of the command.
     */
    public function testARefusedDescriptorIsReportedOnTheErrorLine(): void
    {
        $this->writeSampleModule($this->themeVendorDir().'/thelia/modules/'.self::FAILING_CODE, self::FAILING_CODE, '<enabled-by-default>maybe</enabled-by-default>');
        $this->installTheme([self::FAILING_CODE]);

        $tester = new CommandTester($this->setTemplateCommand($this->createMock(TheliaTemplateHelper::class), $this->createMock(ComposerHelper::class)));
        $tester->execute(['type' => 'backOffice', 'name' => self::THEME_NAME]);

        self::assertSame(SetTemplate::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertMatchesRegularExpression('/^ERROR: .*not a valid file.*\(Code \d+\).*$/m', $tester->getDisplay());
    }

    /**
     * In verbose mode the failure is followed by its trace, frame by frame and without the
     * arguments of the frames.
     */
    public function testTheTraceOfAFailedModuleIsPrintedInVerboseMode(): void
    {
        $this->writeSampleModule($this->themeVendorDir().'/thelia/modules/'.self::FAILING_CODE, self::FAILING_CODE, '', '', '99.0.0');
        $this->installTheme([self::FAILING_CODE]);

        $tester = new CommandTester($this->setTemplateCommand($this->createMock(TheliaTemplateHelper::class), $this->createMock(ComposerHelper::class)));
        $tester->execute(['type' => 'backOffice', 'name' => self::THEME_NAME], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(SetTemplate::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertMatchesRegularExpression('/^#0 \S+\(\d+\): \S+\(\)$/m', $tester->getDisplay());
    }

    /**
     * A failed regeneration of the autoloader is printed, without the control characters
     * of the Composer output, and does not keep the theme from being enabled.
     */
    public function testAFailedAutoloadDumpIsPrintedAndTheThemeIsEnabled(): void
    {
        $this->writeSampleModule(THELIA_MODULE_DIR.self::INACTIVE_CODE, self::INACTIVE_CODE, '<enabled-by-default>0</enabled-by-default>');
        $this->registerSampleModule(self::INACTIVE_CODE);
        $this->installTheme([self::INACTIVE_CODE]);

        $templateHelper = $this->createMock(TheliaTemplateHelper::class);
        $templateHelper->expects(self::once())->method('setConfigToTemplate')->with(self::anything(), self::THEME_NAME);
        $composerHelper = $this->createMock(ComposerHelper::class);
        $composerHelper->method('dumpAutoload')->willThrowException(new \RuntimeException("Could not scan\e[31m vendor"));

        $tester = new CommandTester($this->setTemplateCommand($templateHelper, $composerHelper));
        $tester->execute(['type' => 'backOffice', 'name' => self::THEME_NAME]);

        self::assertSame(SetTemplate::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Composer dump-autoload failed: Could not scan?[31m vendor', $tester->getDisplay());
        self::assertStringNotContainsString('Autoload dump completed successfully', $tester->getDisplay());
    }

    /**
     * A module the theme brings and the shop cannot activate stops the command: the error
     * is printed with the module named, the exit code is non-zero and the theme is not
     * enabled, so the install does not end on a shop whose theme misses a module.
     */
    public function testTheCommandStopsWithoutEnablingTheThemeWhenAModuleCannotBeActivated(): void
    {
        $this->writeSampleModule($this->themeVendorDir().'/thelia/modules/'.self::FAILING_CODE, self::FAILING_CODE, '', '', '99.0.0');
        $this->installTheme([self::FAILING_CODE]);

        $templateHelper = $this->createMock(TheliaTemplateHelper::class);
        $templateHelper->expects(self::never())->method('enableThemeAsBundle');
        $templateHelper->expects(self::never())->method('setConfigToTemplate');
        $composerHelper = $this->createMock(ComposerHelper::class);
        $composerHelper->expects(self::never())->method('dumpAutoload');

        $tester = new CommandTester($this->setTemplateCommand($templateHelper, $composerHelper));
        $tester->execute(['type' => 'backOffice', 'name' => self::THEME_NAME]);

        self::assertSame(SetTemplate::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('ERROR: The module '.self::FAILING_CODE.' requires Thelia 99.0.0 or newer', $tester->getDisplay());
        self::assertStringNotContainsString('theme modules found', $tester->getDisplay());
        self::assertSame(BaseModule::IS_NOT_ACTIVATED, $this->activationOf(self::FAILING_CODE), 'Its installation registered it inactive, and the row stays: nothing is ever removed on the theme\'s behalf.');

        // Run again: the module is now one the shop knows, left inactive and named, and the
        // theme is enabled. The stop only holds for the run that met the failure.
        $replayTemplateHelper = $this->createMock(TheliaTemplateHelper::class);
        $replayTemplateHelper->expects(self::once())->method('enableThemeAsBundle');
        $replayTester = new CommandTester($this->setTemplateCommand($replayTemplateHelper, $this->createMock(ComposerHelper::class)));
        $replayTester->execute(['type' => 'backOffice', 'name' => self::THEME_NAME]);

        self::assertSame(SetTemplate::SUCCESS, $replayTester->getStatusCode(), $replayTester->getDisplay());
        self::assertStringContainsString('Module '.self::FAILING_CODE.' is required by the theme but is registered inactive', $replayTester->getDisplay());
        self::assertStringContainsString('left as it is, and the theme goes on without it', $replayTester->getDisplay(), 'The second run says the theme is enabled without the module.');
    }

    /**
     * The theme brings two modules and the second cannot be activated: the command stops,
     * the first stays installed and active, the second stays registered inactive.
     */
    public function testAFailureAfterAModuleWasActivatedStopsTheCommand(): void
    {
        $this->writeSampleModule($this->themeVendorDir().'/thelia/modules/'.self::NEW_CODE, self::NEW_CODE, '');
        $this->writeSampleModule($this->themeVendorDir().'/thelia/modules/'.self::FAILING_CODE, self::FAILING_CODE, '', '', '99.0.0');
        $this->installTheme([self::NEW_CODE, self::FAILING_CODE]);

        $templateHelper = $this->createMock(TheliaTemplateHelper::class);
        $templateHelper->expects(self::never())->method('enableThemeAsBundle');
        $composerHelper = $this->createMock(ComposerHelper::class);
        $composerHelper->expects(self::never())->method('dumpAutoload');

        $tester = new CommandTester($this->setTemplateCommand($templateHelper, $composerHelper));
        $tester->execute(['type' => 'backOffice', 'name' => self::THEME_NAME]);

        self::assertSame(SetTemplate::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(BaseModule::IS_ACTIVATED, $this->activationOf(self::NEW_CODE));
        self::assertSame(BaseModule::IS_NOT_ACTIVATED, $this->activationOf(self::FAILING_CODE));
    }

    /**
     * A module whose class does not load raises an \Error, not an exception: template:set
     * reports it the same way, and the theme is not enabled.
     */
    public function testAModuleWhoseClassDoesNotLoadStopsTheCommandTheSameWay(): void
    {
        $this->writeSampleModule($this->themeVendorDir().'/thelia/modules/'.self::BROKEN_CODE, self::BROKEN_CODE, '', '', '3.0.0', '\\ThemeShipSampleMissing\\ParentModule');
        $this->installTheme([self::BROKEN_CODE]);

        $templateHelper = $this->createMock(TheliaTemplateHelper::class);
        $templateHelper->expects(self::never())->method('enableThemeAsBundle');
        $composerHelper = $this->createMock(ComposerHelper::class);
        $composerHelper->expects(self::never())->method('dumpAutoload');

        $tester = new CommandTester($this->setTemplateCommand($templateHelper, $composerHelper));
        $tester->execute(['type' => 'backOffice', 'name' => self::THEME_NAME]);

        self::assertSame(SetTemplate::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('ERROR: ', $tester->getDisplay());
        self::assertStringContainsString('ERROR: Class "ThemeShipSampleMissing\\ParentModule" not found', $tester->getDisplay());
        self::assertNull(ModuleQuery::create()->findOneByCode(self::BROKEN_CODE));
    }

    /**
     * ModuleManagement::installModule() follows the rule template:set follows: a module
     * declaring <enabled-by-default>0</enabled-by-default> is installed and registered, not
     * activated; one that says nothing is installed and activated.
     */
    public function testInstallModuleLeavesAModuleShippingInactiveInactive(): void
    {
        $this->writeSampleModule($this->themeVendorDir().'/thelia/modules/'.self::NEW_INACTIVE_CODE, self::NEW_INACTIVE_CODE, '<enabled-by-default>0</enabled-by-default>');
        /** @var ModuleManagement $moduleManagement */
        $moduleManagement = $this->getService(ModuleManagement::class);

        $module = $moduleManagement->installModule($this->themeVendorDir().'/thelia/modules/'.self::NEW_INACTIVE_CODE);

        self::assertSame(BaseModule::IS_NOT_ACTIVATED, $module->getActivate());
        self::assertSame(BaseModule::IS_NOT_ACTIVATED, $this->activationOf(self::NEW_INACTIVE_CODE));
    }

    public function testInstallModuleActivatesAModuleThatSaysNothing(): void
    {
        $this->writeSampleModule($this->themeVendorDir().'/thelia/modules/'.self::NEW_CODE, self::NEW_CODE, '');
        /** @var ModuleManagement $moduleManagement */
        $moduleManagement = $this->getService(ModuleManagement::class);

        $module = $moduleManagement->installModule($this->themeVendorDir().'/thelia/modules/'.self::NEW_CODE);

        self::assertSame(BaseModule::IS_ACTIVATED, $module->getActivate());
        self::assertSame(BaseModule::IS_ACTIVATED, $this->activationOf(self::NEW_CODE));
    }

    /**
     * The shop registered the module under the namespace of an older release, the merchant
     * switched it off, and the release on disk declares another namespace in the same
     * directory. The row is the merchant's: applying the theme leaves it inactive.
     */
    public function testAModuleKnownUnderAFormerNamespaceKeepsTheStateTheMerchantChose(): void
    {
        $this->writeSampleModule(THELIA_MODULE_DIR.self::SWITCHED_OFF_CODE, self::SWITCHED_OFF_CODE, '');
        $this->registerSampleModule(self::SWITCHED_OFF_CODE, 0, 'FormerVendor\\'.self::SWITCHED_OFF_CODE);
        $rowId = ModuleQuery::create()->findOneByCode(self::SWITCHED_OFF_CODE)?->getId();
        $themeDir = $this->writeTheme([self::SWITCHED_OFF_CODE]);

        /** @var ModuleManagement $moduleManagement */
        $moduleManagement = $this->getService(ModuleManagement::class);
        $moduleManagement->installModulesFromTemplatePath($themeDir, new BufferedOutput());

        self::assertSame($rowId, ModuleQuery::create()->findOneByCode(self::SWITCHED_OFF_CODE)?->getId(), 'The merchant\'s row is the one kept.');
        self::assertSame(BaseModule::IS_NOT_ACTIVATED, $this->activationOf(self::SWITCHED_OFF_CODE), 'A module the merchant switched off is not switched back on because its namespace changed.');
    }

    /**
     * A module the theme brings that ships inactive is registered, not activated: none of
     * its <required> modules is switched on on its behalf either.
     */
    public function testAModuleThatShipsInactiveActivatesNoneOfItsRequiredModules(): void
    {
        $this->writeSampleModule(THELIA_MODULE_DIR.self::INACTIVE_CODE, self::INACTIVE_CODE, '');
        $this->registerSampleModule(self::INACTIVE_CODE);
        $this->writeSampleModule($this->themeVendorDir().'/thelia/modules/'.self::NEW_INACTIVE_CODE, self::NEW_INACTIVE_CODE, '<enabled-by-default>0</enabled-by-default>', '<required><module>'.self::INACTIVE_CODE.'</module></required>');
        $themeDir = $this->writeTheme([self::NEW_INACTIVE_CODE]);

        /** @var ModuleManagement $moduleManagement */
        $moduleManagement = $this->getService(ModuleManagement::class);
        $moduleManagement->installModulesFromTemplatePath($themeDir, new BufferedOutput());

        self::assertSame(BaseModule::IS_NOT_ACTIVATED, $this->activationOf(self::NEW_INACTIVE_CODE));
        self::assertSame(BaseModule::IS_NOT_ACTIVATED, $this->activationOf(self::INACTIVE_CODE), 'A module shipped inactive switches on none of its required modules.');
    }

    /**
     * The activation switches the <required> modules on before it checks the module itself:
     * when the module the theme brings fails its check, its required modules stay active.
     */
    public function testTheRequiredModulesOfAModuleThatFailsStayActive(): void
    {
        $this->writeSampleModule(THELIA_MODULE_DIR.self::INACTIVE_CODE, self::INACTIVE_CODE, '');
        $this->registerSampleModule(self::INACTIVE_CODE);
        $this->writeSampleModule($this->themeVendorDir().'/thelia/modules/'.self::FAILING_CODE, self::FAILING_CODE, '', '<required><module>'.self::INACTIVE_CODE.'</module></required>', '99.0.0');
        $themeDir = $this->writeTheme([self::FAILING_CODE]);

        /** @var ModuleManagement $moduleManagement */
        $moduleManagement = $this->getService(ModuleManagement::class);

        try {
            $moduleManagement->installModulesFromTemplatePath($themeDir, new BufferedOutput());
            self::fail('A module that requires Thelia 99.0.0 cannot be activated.');
        } catch (\Exception $exception) {
            self::assertStringContainsString('requires Thelia 99.0.0', $exception->getMessage());
        }

        self::assertSame(BaseModule::IS_NOT_ACTIVATED, $this->activationOf(self::FAILING_CODE));
        self::assertSame(BaseModule::IS_ACTIVATED, $this->activationOf(self::INACTIVE_CODE), 'Its required module was activated before the module failed, and stays active.');
    }

    /**
     * The shop registered the module under the code its namespace starts with and under the
     * namespace of an older release, and the release on disk lives in a directory of another
     * name: the row is found by that code, and kept.
     */
    public function testAModuleRegisteredUnderTheCodeOfItsNamespaceKeepsItsRow(): void
    {
        $directoryName = 'ThemeShipSampleRenamedDirectory';
        $this->writeSampleModule(THELIA_MODULE_DIR.$directoryName, self::SWITCHED_OFF_CODE, '');
        $this->registerSampleModule(self::SWITCHED_OFF_CODE, 0, 'FormerVendor\\'.self::SWITCHED_OFF_CODE);
        $rowId = ModuleQuery::create()->findOneByCode(self::SWITCHED_OFF_CODE)?->getId();

        /** @var ModuleManagement $moduleManagement */
        $moduleManagement = $this->getService(ModuleManagement::class);
        $module = $moduleManagement->installModule(THELIA_MODULE_DIR.$directoryName);

        self::assertSame($rowId, $module->getId(), 'The row registered under the code of the namespace is the one handed back.');
        self::assertNull(ModuleQuery::create()->findOneByCode($directoryName), 'No row is installed under the name of the directory.');
        self::assertSame(BaseModule::IS_NOT_ACTIVATED, $this->activationOf(self::SWITCHED_OFF_CODE));
    }

    /**
     * module:refresh registers a module under the name of its directory, which need not be the
     * code its namespace starts with: the row is found by that name, and kept.
     */
    public function testAModuleRegisteredUnderItsDirectoryNameKeepsItsRow(): void
    {
        $directoryName = 'ThemeShipSampleDirectory';
        $this->writeSampleModule(THELIA_MODULE_DIR.$directoryName, self::SWITCHED_OFF_CODE, '');
        $this->registerSampleModule($directoryName, 0, 'FormerVendor\\'.$directoryName);
        $rowId = ModuleQuery::create()->findOneByCode($directoryName)?->getId();

        /** @var ModuleManagement $moduleManagement */
        $moduleManagement = $this->getService(ModuleManagement::class);
        $module = $moduleManagement->installModule(THELIA_MODULE_DIR.$directoryName);

        self::assertSame($rowId, $module->getId(), 'The row registered under the directory name is the one handed back.');
        self::assertNull(ModuleQuery::create()->findOneByCode(self::SWITCHED_OFF_CODE), 'No second row is installed under the code of the namespace.');
        self::assertSame(BaseModule::IS_NOT_ACTIVATED, $this->activationOf($directoryName));
    }

    /**
     * A mandatory module the theme requires and finds inactive is named as mandatory, as the
     * install does: nothing else would say that a module the shop cannot do without is off.
     */
    public function testAMandatoryModuleLeftInactiveIsReportedAsMandatory(): void
    {
        $this->writeSampleModule(THELIA_MODULE_DIR.self::SWITCHED_OFF_CODE, self::SWITCHED_OFF_CODE, '');
        $this->registerSampleModule(self::SWITCHED_OFF_CODE, BaseModule::IS_MANDATORY);
        $this->writeSampleModule(THELIA_MODULE_DIR.self::INACTIVE_CODE, self::INACTIVE_CODE, '');
        $this->registerSampleModule(self::INACTIVE_CODE);
        $themeDir = $this->writeTheme([self::SWITCHED_OFF_CODE, self::INACTIVE_CODE]);

        $output = new BufferedOutput();
        /** @var ModuleManagement $moduleManagement */
        $moduleManagement = $this->getService(ModuleManagement::class);
        $moduleManagement->installModulesFromTemplatePath($themeDir, $output);
        $written = $output->fetch();

        self::assertStringContainsString('Module '.self::SWITCHED_OFF_CODE.' is mandatory but is registered inactive: activate it from the back-office.', $written);
        self::assertStringNotContainsString('Module '.self::INACTIVE_CODE.' is mandatory', $written);
    }

    private function setTemplateCommand(TheliaTemplateHelper $templateHelper, ComposerHelper $composerHelper): SetTemplate
    {
        /** @var ModuleManagement $moduleManagement */
        $moduleManagement = $this->getService(ModuleManagement::class);
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getService('event_dispatcher');

        return new SetTemplate($moduleManagement, $templateHelper, $dispatcher, $composerHelper, self::$kernel->getCacheDir());
    }

    /**
     * @param Module[] $modules
     *
     * @return array<string, int> activation by module code, as the modules were handed back
     */
    private function statesOf(array $modules): array
    {
        $states = [];
        foreach ($modules as $module) {
            $states[$module->getCode()] = $module->getActivate();
        }

        return $states;
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
    private function writeSampleModule(string $moduleDir, string $code, string $declaration, string $required = '', string $theliaVersion = '3.0.0', string $parentClass = 'BaseModule'): void
    {
        $this->filesystem->dumpFile($moduleDir.DS.$code.'.php', <<<PHP
            <?php

            namespace {$code};

            use Thelia\\Module\\BaseModule;

            class {$code} extends {$parentClass}
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
                <thelia>{$theliaVersion}</thelia>
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
    private function registerSampleModule(string $code, int $mandatory = 0, ?string $namespace = null): void
    {
        $module = new Module();
        $module
            ->setCode($code)
            ->setMandatory($mandatory)
            ->setVersion('1.0.0')
            ->setType(BaseModule::CLASSIC_MODULE_TYPE)
            ->setCategory('classic')
            ->setActivate(BaseModule::IS_NOT_ACTIVATED)
            ->setFullNamespace($namespace ?? $code.'\\'.$code)
            ->setPosition((int) ModuleQuery::create()->orderByPosition(Criteria::DESC)->select('position')->findOne() + 1)
            ->save();
    }

    private function themeVendorDir(): string
    {
        return $this->workDir.'/vendor';
    }

    /**
     * Where template:set looks the theme up by name: a link to the forged theme, removed
     * with the rest.
     */
    private function themeInstallDir(): string
    {
        return self::themeLink();
    }

    private static function themeLink(): string
    {
        return THELIA_TEMPLATE_DIR.'backOffice'.DS.self::THEME_NAME;
    }

    private static function removeSampleFiles(): void
    {
        (new Filesystem())->remove([...(glob(THELIA_MODULE_DIR.self::SAMPLE_PREFIX.'*') ?: []), self::themeLink()]);
    }

    /**
     * @param string[] $codes
     *
     * @return string the path template:set resolves for the theme
     */
    private function installTheme(array $codes): string
    {
        $this->filesystem->symlink($this->writeTheme($codes), $this->themeInstallDir());

        return $this->themeInstallDir();
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

        self::removeSampleFiles();
    }
}
