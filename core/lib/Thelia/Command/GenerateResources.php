<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
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
            throw new \RuntimeException('No resources found');
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
                        "($compteur, '$value', NOW(), NOW())" . ($constant === key(array_slice($constants, -1, 1, true)) ? ';' : ',')
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

                    $title = ucwords(str_replace('.', ' / ', str_replace('admin.', '', $value)));

                    $output->writeln(
                        "($compteur, 'en_US', '$title'),"
                    );
                    $output->writeln(
                        "($compteur, 'fr_FR', '$title')" . ($constant === key(array_slice($constants, -1, 1, true)) ? ';' : ',')
                    );
                }
                break;
            default:
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
