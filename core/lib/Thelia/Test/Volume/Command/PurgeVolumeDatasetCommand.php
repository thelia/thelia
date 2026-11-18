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

namespace Thelia\Test\Volume\Command;

use Propel\Runtime\Propel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Test\Volume\VolumeDatasetManifest;
use Thelia\Test\Volume\VolumeDatasetPurger;

/**
 * Deletes the volume dataset of the database, and only it: the rows its manifest recorded, its
 * image files and its manifest. Counts first: --dry-run says what would go.
 */
#[AsCommand(
    name: 'thelia:test:volume:purge',
    description: 'Test tool: delete the volume dataset written by thelia:test:volume:generate, and nothing else',
)]
final class PurgeVolumeDatasetCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count the rows the purge would delete, delete nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('prod' === $this->environment) {
            $io->error('The volume dataset is a test tool: it refuses the production environment. Run it with APP_ENV=dev.');

            return Command::FAILURE;
        }

        $connection = Propel::getConnection(ProductTableMap::DATABASE_NAME);
        if (null === VolumeDatasetManifest::load($connection)) {
            $io->warning('No volume dataset in this database.');

            return Command::SUCCESS;
        }

        $purger = new VolumeDatasetPurger($connection, THELIA_LOCAL_DIR.'media/');

        if ($input->getOption('dry-run')) {
            $io->table(['Table', 'Rows to delete'], $this->rows($purger->count()));
            $io->note('Nothing deleted: run again without --dry-run to purge.');

            return Command::SUCCESS;
        }

        $start = hrtime(true);
        $deleted = $purger->purge();
        $io->table(['Table', 'Rows deleted'], $this->rows($deleted));
        $io->success(\sprintf('Volume dataset deleted in %.1f s.', (hrtime(true) - $start) / 1e9));

        return Command::SUCCESS;
    }

    /**
     * @param array<string, int> $counts
     *
     * @return list<array{0: string, 1: string}>
     */
    private function rows(array $counts): array
    {
        return array_map(
            static fn (string $table, int $rows): array => [$table, number_format($rows)],
            array_keys($counts),
            array_values($counts),
        );
    }
}
