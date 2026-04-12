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

final class ModuleRefreshCommandTest extends IntegrationTestCase
{
    public function testRefreshRunsSuccessfully(): void
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('module:refresh'),
        );
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('successfully', $tester->getDisplay());
    }
}
