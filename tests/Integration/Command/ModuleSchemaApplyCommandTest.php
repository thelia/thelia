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

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Thelia\Command\ModuleSchemaApplyCommand;
use Thelia\Test\IntegrationTestCase;

/**
 * The command replays the schema of a module (TheliaMain.sql, then every update script)
 * and must stay re-runnable. A schema change commits on its own, so no transaction wraps
 * the case.
 */
final class ModuleSchemaApplyCommandTest extends IntegrationTestCase
{
    protected bool $useTransaction = false;

    /**
     * TheliaMain.sql already has the shape the update scripts lead to: a column, index or
     * foreign key an update drops is already gone, which must not fail the module.
     */
    public function testAModuleUpdateDroppingWhatIsAlreadyGoneIsApplied(): void
    {
        $pdo = new \PDO(
            \sprintf(
                'mysql:host=%s;port=%s;dbname=%s',
                (string) ($_SERVER['DATABASE_HOST'] ?? 'db'),
                (string) ($_SERVER['DATABASE_PORT'] ?? '3306'),
                (string) ($_SERVER['DATABASE_NAME'] ?? 'test'),
            ),
            (string) ($_SERVER['DATABASE_USER'] ?? 'db'),
            (string) ($_SERVER['DATABASE_PASSWORD'] ?? 'db'),
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );
        $output = new BufferedOutput();
        $command = new ModuleSchemaApplyCommand();

        try {
            $applied = (new \ReflectionMethod($command, 'applyModuleSchema'))->invoke(
                $command,
                new SymfonyStyle(new ArrayInput([]), $output),
                $pdo,
                'AbsentDropProbe',
                THELIA_ROOT.'tests/fixtures/install/AbsentDropProbe',
                false,
            );

            self::assertTrue($applied, $output->fetch());
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS `absent_drop_probe`');
        }
    }
}
