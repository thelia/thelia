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

namespace Thelia\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Thelia\Command\Install;

/**
 * thelia:install runs unattended from a deployment script: every setting it asks for
 * can be given as an option instead.
 */
final class InstallTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function optionsTakingAValue(): iterable
    {
        foreach ([
            'database_host', 'database_username', 'database_password', 'database_name', 'database_port',
            'frontoffice_theme', 'backoffice_theme', 'pdf_theme', 'email_theme',
            'admin_login_name', 'admin_first_name', 'admin_last_name', 'admin_email', 'admin_locale', 'admin_password',
        ] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('optionsTakingAValue')]
    public function testASettingCanBeGivenAsAnOption(string $name): void
    {
        $definition = (new Install('test'))->getDefinition();

        self::assertTrue($definition->hasOption($name));
        self::assertTrue($definition->getOption($name)->acceptValue());
    }

    public function testTheDemoDataAndTheAdministratorAreAskedForByFlags(): void
    {
        $definition = (new Install('test'))->getDefinition();

        foreach (['with-demo', 'with-admin'] as $name) {
            self::assertTrue($definition->hasOption($name), $name);
            self::assertFalse($definition->getOption($name)->acceptValue(), $name);
        }
    }

    public function testTheDatabaseIsLookedForOnTheLocalMysqlPortUnlessToldOtherwise(): void
    {
        $definition = (new Install('test'))->getDefinition();

        self::assertSame('localhost', $definition->getOption('database_host')->getDefault());
        self::assertSame('3306', $definition->getOption('database_port')->getDefault());
    }
}
