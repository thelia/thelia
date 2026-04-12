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

final class ModuleListCommandTest extends IntegrationTestCase
{
    public function testListsRegisteredModules(): void
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('module:list'),
        );
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('Cheque', $output);
        self::assertStringContainsString('Code', $output);
    }
}
