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
        $pdo = $this->connect();
        $output = new BufferedOutput();

        try {
            self::assertTrue($this->apply($pdo, 'AbsentDropProbe', $output), $output->fetch());
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS `absent_drop_probe`');
        }
    }

    /**
     * TheliaMain.sql drops its tables before creating them: replaying it on a table that
     * holds rows would empty it, so the module is refused and the rows stay.
     */
    public function testAModuleWhoseTablesHoldRowsIsRefused(): void
    {
        $pdo = $this->connect();
        $output = new BufferedOutput();

        try {
            $this->createKeptDataProbe($pdo, 2);

            self::assertFalse($this->apply($pdo, 'KeptDataProbe', $output));
            $message = $output->fetch();
            self::assertStringContainsString('kept_data_probe (2 rows)', $message);
            self::assertStringContainsString('--force', $message);
            self::assertSame(2, $this->countRows($pdo));
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS `kept_data_probe`');
        }
    }

    public function testForceReplaysAModuleWhoseTablesHoldRows(): void
    {
        $pdo = $this->connect();
        $output = new BufferedOutput();

        try {
            $this->createKeptDataProbe($pdo, 2);

            self::assertTrue((new ModuleSchemaApplyCommand())->getDefinition()->hasOption('force'));
            self::assertTrue($this->apply($pdo, 'KeptDataProbe', $output, force: true), $output->fetch());
            self::assertSame(0, $this->countRows($pdo));
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS `kept_data_probe`');
        }
    }

    public function testAModuleWhoseTablesAreEmptyOrAbsentIsApplied(): void
    {
        $pdo = $this->connect();
        $output = new BufferedOutput();

        try {
            $pdo->exec('DROP TABLE IF EXISTS `kept_data_probe`');
            self::assertTrue($this->apply($pdo, 'KeptDataProbe', $output), $output->fetch());

            $this->createKeptDataProbe($pdo, 0);
            self::assertTrue($this->apply($pdo, 'KeptDataProbe', $output), $output->fetch());
            self::assertSame(0, $this->countRows($pdo));
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS `kept_data_probe`');
        }
    }

    private function connect(): \PDO
    {
        return new \PDO(
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
    }

    private function apply(\PDO $pdo, string $moduleName, BufferedOutput $output, bool $force = false): bool
    {
        $command = new ModuleSchemaApplyCommand();

        return (bool) (new \ReflectionMethod($command, 'applyModuleSchema'))->invoke(
            $command,
            new SymfonyStyle(new ArrayInput([]), $output),
            $pdo,
            $moduleName,
            THELIA_ROOT.'tests/fixtures/install/'.$moduleName,
            false,
            $force,
        );
    }

    private function createKeptDataProbe(\PDO $pdo, int $rows): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `kept_data_probe`');
        $pdo->exec('CREATE TABLE `kept_data_probe` (`id` INTEGER NOT NULL AUTO_INCREMENT, `label` VARCHAR(255) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB');

        for ($row = 1; $row <= $rows; ++$row) {
            $pdo->exec(\sprintf("INSERT INTO `kept_data_probe` (`label`) VALUES ('row %d')", $row));
        }
    }

    private function countRows(\PDO $pdo): int
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM `kept_data_probe`')->fetchColumn();
    }
}
