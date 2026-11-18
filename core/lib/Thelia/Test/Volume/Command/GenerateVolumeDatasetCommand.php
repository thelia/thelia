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

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Test\Volume\BatchInserter;
use Thelia\Test\Volume\VolumeDatasetFingerprint;
use Thelia\Test\Volume\VolumeDatasetGenerator;
use Thelia\Test\Volume\VolumeDatasetManifest;
use Thelia\Test\Volume\VolumeDatasetOptions;

/**
 * Fills a disposable database with a large catalogue, customers and orders, to measure how the
 * shop holds at that size. A test tool: it writes hundreds of thousands of rows, so it refuses the
 * production environment and a database holding orders it did not generate.
 */
#[AsCommand(
    name: 'thelia:test:volume:generate',
    description: 'Test tool, never on a live shop: write a large deterministic catalogue, customers and orders to measure the shop',
)]
final class GenerateVolumeDatasetCommand extends Command
{
    public const IMAGE_SOURCE_DIRECTORY = THELIA_LIB.'Command/Import/data/images/';

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('products', null, InputOption::VALUE_REQUIRED, 'Products to write', '1000')
            ->addOption('orders', null, InputOption::VALUE_REQUIRED, 'Orders to write', '1000')
            ->addOption('customers', null, InputOption::VALUE_REQUIRED, 'Customers to write', '500')
            ->addOption('category-depth', null, InputOption::VALUE_REQUIRED, 'Levels of the category tree', '3')
            ->addOption('categories-per-level', null, InputOption::VALUE_REQUIRED, 'Children of each category', '5')
            ->addOption('locales', null, InputOption::VALUE_REQUIRED, 'Comma-separated locales of the texts (default: the active languages)')
            ->addOption('currencies', null, InputOption::VALUE_REQUIRED, 'Comma-separated currency codes of the prices (default: the default currency and USD)')
            ->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Seed of the random choices: the same seed writes the same dataset', '306')
            ->addOption('images', null, InputOption::VALUE_REQUIRED, 'Source images shared by the products', '8')
            ->addOption('base-date', null, InputOption::VALUE_REQUIRED, 'Date every generated row is dated back from (Y-m-d H:i:s)', VolumeDatasetOptions::DEFAULT_BASE_DATE)
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows per INSERT statement', (string) BatchInserter::DEFAULT_BATCH_SIZE)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Write even though the database holds orders the dataset did not generate (a demo shop, never a live one)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('prod' === $this->environment) {
            $io->error('The volume dataset is a test tool: it refuses the production environment. Run it with APP_ENV=dev on a disposable database.');

            return Command::FAILURE;
        }

        $connection = Propel::getConnection(ProductTableMap::DATABASE_NAME);

        $foreignOrders = $this->countForeignOrders($connection);
        if ($foreignOrders > 0 && !$input->getOption('force')) {
            $io->error(\sprintf(
                'This database holds %d order(s) the volume dataset did not write: it may be a live shop. Run again with --force only on a disposable database (a fresh demo install).',
                $foreignOrders,
            ));

            return Command::FAILURE;
        }

        $options = new VolumeDatasetOptions(
            products: (int) $input->getOption('products'),
            orders: (int) $input->getOption('orders'),
            customers: (int) $input->getOption('customers'),
            categoryDepth: (int) $input->getOption('category-depth'),
            categoriesPerLevel: (int) $input->getOption('categories-per-level'),
            locales: $this->listOption($input, 'locales') ?? $this->activeLocales($connection),
            currencyCodes: $this->listOption($input, 'currencies') ?? $this->defaultCurrencies($connection),
            seed: (int) $input->getOption('seed'),
            images: (int) $input->getOption('images'),
            baseDate: (string) $input->getOption('base-date'),
        );

        $io->title('Volume dataset');
        $io->definitionList(...array_map(
            static fn (string $key, int|string|array $value): array => [$key => \is_array($value) ? implode(', ', $value) : (string) $value],
            array_keys($options->toArray()),
            array_values($options->toArray()),
        ));

        $generator = new VolumeDatasetGenerator(
            $connection,
            THELIA_LOCAL_DIR.'media/',
            self::IMAGE_SOURCE_DIRECTORY,
            (int) $input->getOption('batch-size'),
        );

        $bars = [];
        $manifest = $generator->generate($options, static function (string $step, int $done, int $planned) use ($io, &$bars): void {
            $bars[$step] ??= $io->createProgressBar($planned);
            $bars[$step]->setFormat(\sprintf('%-11s', $step).' %current%/%max% [%bar%] %elapsed:6s%');
            $bars[$step]->setProgress($done);
            if ($done >= $planned) {
                $bars[$step]->finish();
                $io->newLine();
            }
        });

        $this->report($io, $manifest, VolumeDatasetFingerprint::of($connection));

        return Command::SUCCESS;
    }

    private function report(SymfonyStyle $io, VolumeDatasetManifest $manifest, VolumeDatasetFingerprint $fingerprint): void
    {
        $io->section('Generation time');
        $io->table(['Step', 'Seconds'], array_map(
            static fn (string $step, float $seconds): array => [$step, number_format($seconds, 1)],
            array_keys($manifest->durations()),
            array_values($manifest->durations()),
        ));

        $io->section('Rows');
        $io->table(['Table', 'Rows'], array_map(
            static fn (string $table, int $rows): array => [$table, number_format($rows)],
            array_keys($fingerprint->rows),
            array_values($fingerprint->rows),
        ));

        $io->success(\sprintf(
            'Dataset written. Fingerprint %s. Customers sign in with the password "%s".',
            $fingerprint->digest,
            VolumeDatasetGenerator::CUSTOMER_PASSWORD,
        ));
    }

    private function countForeignOrders(ConnectionInterface $connection): int
    {
        $statement = $connection->prepare('SELECT COUNT(*) FROM `order` WHERE `ref` IS NULL OR `ref` NOT LIKE ?');
        $statement->execute([VolumeDatasetManifest::REF_PREFIX.'%']);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<string>|null
     */
    private function listOption(InputInterface $input, string $name): ?array
    {
        $value = $input->getOption($name);
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => '' !== $item));
    }

    /**
     * @return list<string>
     */
    private function activeLocales(ConnectionInterface $connection): array
    {
        $statement = $connection->prepare('SELECT locale FROM lang WHERE active = 1 ORDER BY by_default DESC, id');
        $statement->execute();

        return array_map('strval', $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * @return list<string>
     */
    private function defaultCurrencies(ConnectionInterface $connection): array
    {
        $statement = $connection->prepare('SELECT code FROM currency ORDER BY by_default DESC, id LIMIT 1');
        $statement->execute();
        $default = (string) $statement->fetchColumn();

        return 'USD' === $default ? [$default] : [$default, 'USD'];
    }
}
