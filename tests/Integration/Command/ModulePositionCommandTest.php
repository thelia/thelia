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
use Thelia\Test\IntegrationTestCase;

final class ModulePositionCommandTest extends IntegrationTestCase
{
    public function testAbsolutePositionChangesModulePosition(): void
    {
        $module = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertNotNull($module);

        $tester = new CommandTester(
            (new Application(self::$kernel))->find('module:position'),
        );
        $tester->execute(['modules' => ['Cheque:1']]);

        self::assertSame(0, $tester->getStatusCode());

        $reloaded = ModuleQuery::create()->findOneByCode('Cheque');
        self::assertSame(1, $reloaded->getPosition());
    }

    public function testUpPositionMovesModuleUp(): void
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('module:position'),
        );
        $tester->execute(['modules' => ['Cheque:up']]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('updated', $tester->getDisplay());
    }

    public function testUnknownModuleThrows(): void
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('module:position'),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exists');

        $tester->execute(['modules' => ['FakeModule:1']]);
    }
}
