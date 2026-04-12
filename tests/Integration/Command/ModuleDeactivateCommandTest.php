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

final class ModuleDeactivateCommandTest extends IntegrationTestCase
{
    public function testDeactivatesAnActiveModule(): void
    {
        $module = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($module, 'Cheque module must be registered by bin/test-prepare');

        // Ensure it is active.
        $module->setActivate(BaseModule::IS_ACTIVATED)->save();

        $tester = new CommandTester(
            (new Application(self::$kernel))->find('module:deactivate'),
        );
        $tester->execute(['module' => 'Cheque']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(
            BaseModule::IS_NOT_ACTIVATED,
            ModuleQuery::create()->findOneByCode('Cheque')->getActivate(),
        );
    }

    public function testCommandFailsWhenModuleAlreadyDeactivated(): void
    {
        $module = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($module);

        $module->setActivate(BaseModule::IS_NOT_ACTIVATED)->save();

        $tester = new CommandTester(
            (new Application(self::$kernel))->find('module:deactivate'),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already deactivated');

        $tester->execute(['module' => 'Cheque']);
    }

    public function testCommandThrowsWhenModuleDoesNotExist(): void
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('module:deactivate'),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found');

        $tester->execute(['module' => 'NonExistentModule']);
    }
}
