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

namespace Thelia\Tests\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Command\ModuleGenerateCommand;
use Thelia\Core\Application;

/**
 * test the module:generate command.
 *
 * Class ModuleGenerateCommandTest
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ModuleGenerateCommandTest extends BaseCommandTest
{
    /** @var Command */
    protected $command;

    /** @var CommandTester */
    protected $commandTester;

    public static function clearTest()
    {
        $fs = new Filesystem();

        if ($fs->exists(THELIA_MODULE_DIR.'Test')) {
            $fs->remove(THELIA_MODULE_DIR.'Test');
        }
    }

    public static function setUpBeforeClass(): void
    {
        self::clearTest();
    }

    public static function tearDownAfterClass(): void
    {
        self::clearTest();
    }

    public function setUp(): void
    {
        $application = new Application($this->getKernel());

        $moduleGenerator = new ModuleGenerateCommand();

        $application->add($moduleGenerator);

        $this->command = $application->find('module:generate');
        $this->commandTester = new CommandTester($this->command);
    }

    public function testGenerateModule()
    {
        $tester = $this->commandTester;

        $tester->execute([
           'command' => $this->command->getName(),
            'name' => 'test',
        ]);

        $fs = new Filesystem();

        $this->assertTrue($fs->exists(THELIA_MODULE_DIR.'Test'));
    }

    /**
     * @depends testGenerateModule
     */
    public function testGenerateDuplicateModule()
    {
        $tester = $this->commandTester;

        $this->expectException(\RuntimeException::class);
        $tester->execute([
            'command' => $this->command->getName(),
            'name' => 'test',
        ]);
    }

    /**
     * @depends testGenerateModule
     */
    public function testGenerateDuplicateModuleWithForceOption()
    {
        $tester = $this->commandTester;

        // remove the config.xml
        $fs = new Filesystem();
        $configFile = THELIA_MODULE_DIR.'Test'.
            DIRECTORY_SEPARATOR.'Config'.
            DIRECTORY_SEPARATOR.'config.xml'
        ;
        $fs->remove($configFile);

        $tester->execute([
            'command' => $this->command->getName(),
            'name' => 'test',
            '--force' => '',
        ]);

        $this->assertTrue($fs->exists($configFile));
    }

    public function testGenerateWithReservedKeyWord()
    {
        $tester = $this->commandTester;

        $this->expectException(\RuntimeException::class);
        $tester->execute([
           'command' => $this->command->getName(),
            'name' => 'thelia',
        ]);
    }
}
