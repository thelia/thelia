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

namespace Thelia\Command;

use Propel\Runtime\Connection\ConnectionWrapper;
use Propel\Runtime\Propel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Thelia\Core\Install\Database;
use Thelia\Model\Map\ProductTableMap;

/**
 * Class ReloadDatabasesCommand.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
#[AsCommand(name: 'thelia:dev:reloadDB', description: 'erase current database and create new one')]
class ReloadDatabaseCommand extends BaseModuleGenerate
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'If defined, it will reload the db without asking',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (false === $input->getOption('force')) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('<question>Confirm database reset ?<question> (y/N)', false);

            if (!$helper->ask($input, $output, $question)) {
                return self::FAILURE;
            }

            $question = new ConfirmationQuestion('<question>Are you really sure ?<question> (y/N)', false);

            if (!$helper->ask($input, $output, $question)) {
                return self::FAILURE;
            }
        }

        /** @var ConnectionWrapper $connection */
        $connection = Propel::getConnection(ProductTableMap::DATABASE_NAME);
        $connection = $connection->getWrappedConnection();

        $database = new Database($connection);
        $output->writeln([
            '',
            '<info>starting reloaded database, please wait</info>',
        ]);
        $database->insertSql();
        $output->writeln([
            '',
            '<info>Database reloaded with success</info>',
            '',
        ]);

        return 0;
    }
}
