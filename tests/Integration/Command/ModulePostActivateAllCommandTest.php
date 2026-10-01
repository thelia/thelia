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

namespace Thelia\Tests\Integration\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;
use Thelia\Tests\Support\Module\FailingPostActivationModule;
use Thelia\Tests\Support\Module\RecordingPostActivationModule;

/**
 * bin/install and bin/test-prepare run this command after registering the
 * modules, and trust its exit code: a module whose post-activation failed has
 * to make it fail, or the install reports success with that module half set up.
 */
final class ModulePostActivateAllCommandTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Only the probe modules of the test are active, inside the transaction
        // rolled back after it.
        ModuleQuery::create()
            ->filterByActivate(BaseModule::IS_ACTIVATED)
            ->update(['Activate' => 0]);

        RecordingPostActivationModule::$postActivationCount = 0;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        ModuleQuery::resetActivated();
    }

    public function testSucceedsWhenEveryModuleIsPostActivated(): void
    {
        $this->activateProbeModule('RecordingPostActivationProbe', RecordingPostActivationModule::class);

        $tester = $this->runCommand();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(1, RecordingPostActivationModule::$postActivationCount);
        self::assertStringContainsString('1 module(s) post-activated.', $tester->getDisplay());
    }

    public function testFailsAndNamesTheModuleWhosePostActivationFailed(): void
    {
        $this->activateProbeModule('FailingPostActivationProbe', FailingPostActivationModule::class);
        $this->activateProbeModule('RecordingPostActivationProbe', RecordingPostActivationModule::class);

        $tester = $this->runCommand();
        $display = $tester->getDisplay();

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $display);
        self::assertStringContainsString('FailingPostActivationProbe: '.FailingPostActivationModule::FAILURE_MESSAGE, $display);
        self::assertStringContainsString('Post-activation failed for 1 module(s): FailingPostActivationProbe.', $display);
        self::assertSame(1, RecordingPostActivationModule::$postActivationCount, 'The modules after the failing one must still be post-activated.');
        self::assertStringContainsString('1 module(s) post-activated.', $display);
    }

    private function activateProbeModule(string $code, string $moduleClass): void
    {
        (new Module())
            ->setCode($code)
            ->setVersion('1.0.0')
            ->setType(BaseModule::CLASSIC_MODULE_TYPE)
            ->setCategory('classic')
            ->setActivate(BaseModule::IS_ACTIVATED)
            ->setFullNamespace($moduleClass)
            ->save();
    }

    private function runCommand(): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('module:post-activate-all'));
        $tester->execute([]);

        return $tester;
    }
}
