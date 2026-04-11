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
use Thelia\Test\IntegrationTestCase;

/**
 * The success path of `module:activate` depends on the exact modules
 * registered in the test database, which varies across developer
 * setups. We keep the integration tests focused on the error contracts
 * (unknown module) so the suite stays deterministic.
 */
final class ModuleActivateCommandTest extends IntegrationTestCase
{
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
