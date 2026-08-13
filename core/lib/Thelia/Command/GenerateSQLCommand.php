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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Thelia\Install\Generator\SeedSqlGenerator;

/**
 * Class GenerateSQLCommand.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
#[AsCommand(name: 'generate:sql', description: 'Generate setup/insert.sql from setup/insert.sql.tpl')]
class GenerateSQLCommand extends ContainerAwareCommand
{
    public function __construct(
        private readonly SeedSqlGenerator $generator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'locales',
                null,
                InputOption::VALUE_OPTIONAL,
                'generate only for only specific locales (separated by a ,) : fr_FR,es_ES or es_ES',
            )
            ->addOption(
                'check',
                null,
                InputOption::VALUE_NONE,
                'report whether the generated seed matches the one on disk, without writing it',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $locales = $input->getOption('locales');
        $locales = \is_string($locales) && '' !== $locales ? explode(',', $locales) : null;

        $seed = $this->generator->generate($locales);
        $path = $this->generator->getOutputPath();

        if ($input->getOption('check')) {
            if (is_file($path) && $seed === file_get_contents($path)) {
                $io->success($path.' is what the template and the translation files produce.');

                return Command::SUCCESS;
            }

            $io->error($path.' differs from what the template and the translation files produce.');

            return Command::FAILURE;
        }

        if (false === file_put_contents($path, $seed)) {
            $io->error("Can't write file ".$path);

            return Command::FAILURE;
        }

        $io->success('File '.$path.' generated successfully.');

        return Command::SUCCESS;
    }
}
