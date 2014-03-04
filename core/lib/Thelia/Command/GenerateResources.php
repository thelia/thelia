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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Thelia\Core\Security\Resource\AdminResources;

use Thelia\Model\Map\ResourceI18nTableMap;
use Thelia\Model\Map\ResourceTableMap;

class GenerateResources extends ContainerAwareCommand
{
    /**
     * Configure the command
     */
    protected function configure()
    {
        $this
            ->setName("thelia:generate-resources")
            ->setDescription("Outputs admin resources")
            ->setHelp("The <info>thelia:generate-resources</info> outputs admin resources.")
            ->addOption(
                'output',
                null,
                InputOption::VALUE_OPTIONAL,
                'Output format amid (string, sql, sql-i18n)',
                null
            )
        ;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $class = new \ReflectionClass('Thelia\Core\Security\Resource\AdminResources');

        $constants = $class->getConstants();

        if (count($constants) == 0) {
            $output->writeln('No resources found');
            exit;
        }

        switch ($input->getOption("output")) {
            case 'sql':
                $output->writeln(
                    'INSERT INTO ' . ResourceTableMap::TABLE_NAME . ' (`id`, `code`, `created_at`, `updated_at`) VALUES '
                );
                $compteur = 0;
                foreach ($constants as $constant => $value) {
                    if ($constant == AdminResources::SUPERADMINISTRATOR) {
                        continue;
                    }
                    $compteur++;
                    $output->writeln(
                        "($compteur, '$value', NOW(), NOW())" . ($constant === key( array_slice( $constants, -1, 1, true ) ) ? ';' : ',')
                    );
                }
                break;
            case 'sql-i18n':
                $output->writeln(
                    'INSERT INTO ' . ResourceI18nTableMap::TABLE_NAME . ' (`id`, `locale`, `title`) VALUES '
                );
                $compteur = 0;
                foreach ($constants as $constant => $value) {
                    if ($constant == AdminResources::SUPERADMINISTRATOR) {
                        continue;
                    }

                    $compteur++;

                    $title = ucwords( str_replace('.', ' / ', str_replace('admin.', '', $value) ) );

                    $output->writeln(
                        "($compteur, 'en_US', '$title'),"
                    );
                    $output->writeln(
                        "($compteur, 'fr_FR', '$title')" . ($constant === key( array_slice( $constants, -1, 1, true ) ) ? ';' : ',')
                    );
                }
                break;
            default :
                foreach ($constants as $constant => $value) {
                    if ($constant == AdminResources::SUPERADMINISTRATOR) {
                        continue;
                    }
                    $output->writeln('[' . $constant . "] => " . $value);
                }
                break;
        }
    }

}
