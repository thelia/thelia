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
use Thelia\Model\ConfigQuery;
use Thelia\Test\IntegrationTestCase;

final class ConfigCommandTest extends IntegrationTestCase
{
    public function testListDisplaysConfigVariables(): void
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('thelia:config'),
        );
        $tester->execute(['COMMAND' => 'list']);

        self::assertSame(0, $tester->getStatusCode());

        $output = $tester->getDisplay();
        self::assertStringContainsString('Name', $output);
        self::assertStringContainsString('store_name', $output);
    }

    public function testSetCreatesNewVariable(): void
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('thelia:config'),
        );
        $tester->execute([
            'COMMAND' => 'set',
            'name' => 'test_config_var',
            'value' => 'hello_world',
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame('hello_world', ConfigQuery::read('test_config_var'));
    }

    public function testGetDisplaysExistingVariable(): void
    {
        ConfigQuery::write('test_get_var', 'get_value');

        $tester = new CommandTester(
            (new Application(self::$kernel))->find('thelia:config'),
        );
        $tester->execute([
            'COMMAND' => 'get',
            'name' => 'test_get_var',
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('get_value', $tester->getDisplay());
    }

    public function testGetDisplaysErrorForUnknownVariable(): void
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('thelia:config'),
        );
        $tester->execute([
            'COMMAND' => 'get',
            'name' => 'this_does_not_exist',
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Unknown variable', $tester->getDisplay());
    }

    public function testDeleteRemovesVariable(): void
    {
        ConfigQuery::write('test_delete_var', 'to_delete');
        self::assertNotNull(ConfigQuery::read('test_delete_var'));

        $tester = new CommandTester(
            (new Application(self::$kernel))->find('thelia:config'),
        );
        $tester->execute([
            'COMMAND' => 'delete',
            'name' => 'test_delete_var',
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertNull(ConfigQuery::read('test_delete_var'));
    }
}
