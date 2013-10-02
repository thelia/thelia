<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Command;
use Propel\Runtime\Propel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Install\Database;

/**
 * Class ReloadDatabasesCommand
 * @package Thelia\Command
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ReloadDatabaseCommand extends BaseModuleGenerate
{
    public function configure()
    {
        $this
            ->setName("thelia:dev:reloadDB")
            ->setDescription("erase current database and create new one")
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
        $connection = Propel::getConnection(\Thelia\Model\Map\ProductTableMap::DATABASE_NAME);
        $connection = $connection->getWrappedConnection();

        $tables = $connection->query("SHOW TABLES");
        $connection->query("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($tables as $table) {
            $connection->query(sprintf("DROP TABLE `%s`", $table[0]));
        }
        $connection->query("SET FOREIGN_KEY_CHECKS = 1");

        $database = new Database($connection);
        $output->writeln(array(
           '',
           '<info>starting reloaded database, please wait</info>'
        ));
        $database->insertSql();
        $output->writeln(array(
            '',
            '<info>Database reloaded with success</info>',
            ''
        ));
    }
}
