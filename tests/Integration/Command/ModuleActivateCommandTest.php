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
use Symfony\Component\Console\Tester\CommandTester;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Test\IntegrationTestCase;

final class ModuleActivateCommandTest extends IntegrationTestCase
{
    public function testActivatesAModuleThatIsCurrentlyDeactivated(): void
    {
        // Cheque ships with every Thelia install, has a trivial
        // postActivation (insert one Message row) that the transaction
        // rollback will undo, and is always present after
        // `bin/test-prepare`. Deactivate it first so the command has
        // something to toggle back to active.
        $module = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($module, 'Cheque module must be registered by bin/test-prepare');

        $module->setActivate(BaseModule::IS_NOT_ACTIVATED)->save();

        $tester = new CommandTester(
            (new Application(self::$kernel))->find('module:activate'),
        );
        $tester->execute(['module' => 'Cheque']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(
            BaseModule::IS_ACTIVATED,
            ModuleQuery::create()->findOneByCode('Cheque')->getActivate(),
        );
    }

    public function testCommandFailsGracefullyWhenModuleAlreadyActive(): void
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('module:activate'),
        );
        $tester->execute(['module' => 'Cheque']);

        // The command prints an error and returns FAILURE but does not
        // throw — an already-active module is not a fatal error.
        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('already activated', $tester->getDisplay());
    }

    public function testCommandThrowsWhenModuleDoesNotExist(): void
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('module:activate'),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found');

        $tester->execute([
            'module' => 'ThisModuleDoesNotExist',
        ]);
    }

    public function testCommandSwallowsErrorWhenSilentFlagIsSet(): void
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('module:activate'),
        );
        $tester->execute([
            'module' => 'ThisModuleDoesNotExist',
            '--silent' => true,
        ]);

        self::assertSame(0, $tester->getStatusCode());
    }
}
