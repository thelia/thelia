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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\Map\ResourceI18nTableMap;
use Thelia\Model\Map\ResourceTableMap;

#[AsCommand(name: 'thelia:generate-resources', description: 'Outputs admin resources')]
class GenerateResources extends ContainerAwareCommand
{
    /**
     * Configure the command.
     */
    protected function configure(): void
    {
        $this
            ->setHelp('The <info>thelia:generate-resources</info> outputs admin resources.')
            ->addOption(
                'output',
                null,
                InputOption::VALUE_OPTIONAL,
                'Output format amid (string, sql, sql-i18n)',
                null,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $class = new \ReflectionClass(AdminResources::class);

        $constants = $class->getConstants();

        if (0 === \count($constants)) {
            throw new \RuntimeException('No resources found');
        }

        switch ($input->getOption('output')) {
            case 'sql':
                $output->writeln(
                    'INSERT INTO '.ResourceTableMap::TABLE_NAME.' (`id`, `code`, `created_at`, `updated_at`) VALUES ',
                );
                $compteur = 0;

                foreach ($constants as $constant => $value) {
                    if (AdminResources::SUPERADMINISTRATOR === $constant) {
                        continue;
                    }

                    ++$compteur;
                    $output->writeln(
                        \sprintf("(%d, '%s', NOW(), NOW())", $compteur, $value).($constant === key(\array_slice($constants, -1, 1, true)) ? ';' : ','),
                    );
                }

                break;
            case 'sql-i18n':
                $output->writeln(
                    'INSERT INTO '.ResourceI18nTableMap::TABLE_NAME.' (`id`, `locale`, `title`) VALUES ',
                );
                $compteur = 0;

                foreach ($constants as $constant => $value) {
                    if (AdminResources::SUPERADMINISTRATOR === $constant) {
                        continue;
                    }

                    ++$compteur;

                    $title = ucwords(str_replace('.', ' / ', str_replace('admin.', '', $value)));

                    $output->writeln(
                        \sprintf("(%d, 'en_US', '%s'),", $compteur, $title),
                    );
                    $output->writeln(
                        \sprintf("(%d, 'fr_FR', '%s')", $compteur, $title).($constant === key(\array_slice($constants, -1, 1, true)) ? ';' : ','),
                    );
                }

                break;
            default:
                foreach ($constants as $constant => $value) {
                    if (AdminResources::SUPERADMINISTRATOR === $constant) {
                        continue;
                    }

                    $output->writeln('['.$constant.'] => '.$value);
                }

                break;
        }

        return 0;
    }
}
