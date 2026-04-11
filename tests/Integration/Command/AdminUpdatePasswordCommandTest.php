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
use Thelia\Model\AdminQuery;
use Thelia\Test\IntegrationTestCase;

final class AdminUpdatePasswordCommandTest extends IntegrationTestCase
{
    public function testUpdatesExistingAdminPasswordWithCustomValue(): void
    {
        $factory = $this->createFixtureFactory();
        $admin = $factory->admin();
        $previousHash = $admin->getPassword();

        $tester = new CommandTester(
            (new Application(self::$kernel))->find('admin:updatePassword'),
        );
        $tester->execute([
            'login' => $admin->getLogin(),
            '--password' => 'newSecret42',
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('password updated', $tester->getDisplay());

        $reloaded = AdminQuery::create()->findOneByLogin($admin->getLogin());
        self::assertNotNull($reloaded);
        self::assertNotSame($previousHash, $reloaded->getPassword());
        self::assertTrue(password_verify('newSecret42', $reloaded->getPassword()));
    }

    public function testCommandThrowsWhenLoginIsUnknown(): void
    {
        $tester = new CommandTester(
            (new Application(self::$kernel))->find('admin:updatePassword'),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exists');

        $tester->execute([
            'login' => 'nobody-'.uniqid(),
            '--password' => 'whatever',
        ]);
    }
}
