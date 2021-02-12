<?php

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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Install\Database;
use Thelia\Model\Map\ProductTableMap;

/**
 * Class ReloadDatabasesCommand.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class ReloadDatabaseCommand extends BaseModuleGenerate
{
    public function configure(): void
    {
        $this
            ->setName('thelia:dev:reloadDB')
            ->setDescription('erase current database and create new one')
/*            ->addOption(
                "load-fixtures",
                null,
                InputOption::VALUE_NONE,
                "load fixtures in databases"
            )*/
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
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
