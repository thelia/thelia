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
use Symfony\Component\Process\Process;
use Thelia\Core\TheliaKernel;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Test\Volume\Measure\MeasurePlan;
use Thelia\Test\Volume\Measure\MeasureReport;
use Thelia\Test\Volume\Measure\PageMeasure;
use Thelia\Test\Volume\Measure\QueryCountingClient;
use Thelia\Test\Volume\VolumeDatasetFingerprint;
use Thelia\Test\Volume\VolumeDatasetGenerator;
use Thelia\Test\Volume\VolumeDatasetManifest;

/**
 * Measures a running shop that holds a volume dataset: for each page of the plan, the statements
 * its database receives (the main measure, the same at every call) and its times, then the
 * duration of the long jobs. Two campaigns on the same dataset are compared with --compare.
 */
#[AsCommand(
    name: 'thelia:test:volume:measure',
    description: 'Test tool: count the database queries and time the pages and jobs of a shop holding a volume dataset',
)]
final class MeasureVolumeCommand extends Command
{
    /** Rows per page of the back-office lists. */
    private const BACK_OFFICE_PAGE_SIZE = 25;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDirectory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'URL of the shop to measure', 'http://127.0.0.1')
            ->addOption('plan', null, InputOption::VALUE_REQUIRED, 'YAML file listing the pages and jobs', 'setup/volume/pages.yaml')
            ->addOption('runs', null, InputOption::VALUE_REQUIRED, 'Measured calls per page, after one warm-up call left out', '3')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'JSON file the campaign is written to')
            ->addOption('compare', null, InputOption::VALUE_REQUIRED, 'JSON file of a previous campaign: fail on any query count that differs')
            ->addOption('admin-login', null, InputOption::VALUE_REQUIRED, 'Administrator login', 'thelia')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Administrator password', 'thelia')
            ->addOption('skip-jobs', null, InputOption::VALUE_NONE, 'Measure the pages only');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connection = Propel::getConnection(ProductTableMap::DATABASE_NAME);
        $manifest = VolumeDatasetManifest::load($connection);
        if (null === $manifest) {
            $io->error('No volume dataset in this database: run thelia:test:volume:generate first.');

            return Command::FAILURE;
        }

        $runs = max(1, (int) $input->getOption('runs'));
        $baseUrl = (string) $input->getOption('base-url');
        $plan = MeasurePlan::fromFile(
            $this->path((string) $input->getOption('plan')),
            $this->placeholders($connection, $manifest) + [
                'admin_login' => (string) $input->getOption('admin-login'),
                'admin_password' => (string) $input->getOption('admin-password'),
            ],
        );

        $fingerprint = VolumeDatasetFingerprint::of($connection);
        $report = new MeasureReport([
            'date' => gmdate('Y-m-d\TH:i:s\Z'),
            'thelia' => TheliaKernel::THELIA_VERSION,
            'php' => \PHP_VERSION,
            'database' => $this->scalar($connection, 'SELECT VERSION()'),
            'baseUrl' => $baseUrl,
            'runs' => $runs,
            'dataset' => ['fingerprint' => $fingerprint->digest, 'options' => $manifest->options(), 'rows' => $fingerprint->rows, 'tables' => $fingerprint->tables, 'generationSeconds' => $manifest->durations()],
        ]);

        $client = new QueryCountingClient($connection, $baseUrl);
        foreach ($plan->signIns as $session => $signIn) {
            $client->signIn($session, $signIn['form'], $signIn['action'], $signIn['fields']);
        }

        $io->section(\sprintf('Pages (%d calls each after a warm-up)', $runs));
        foreach ($plan->pages as $page) {
            $call = null === $page['submit']
                ? static fn (): PageMeasure => $client->measure($page['path'], $page['session'])
                : static fn (): PageMeasure => $client->submit($page['path'], $page['submit']['action'], $page['submit']['fields'], $page['submit']['expect'], $page['session']);
            // The first call pays the warm-up of caches a running shop keeps warm: left out.
            $call();
            $measures = [];
            for ($run = 0; $run < $runs; ++$run) {
                $measures[] = $call();
            }
            $report->addPage($page, $measures);
        }
        $io->table(
            ['Page', 'Status', 'Queries', 'TTFB (s)', 'Total (s)', 'KB'],
            array_map(static fn (array $page): array => [
                $page['name'],
                $page['status'],
                $page['steady'].($page['stable'] ? '' : ' (calls: '.implode('/', $page['queries']).')'),
                number_format($page['firstByteMedian'], 3),
                number_format($page['totalMedian'], 3),
                number_format($page['bytes'] / 1024, 0),
            ], $report->pages()),
        );

        if (!$input->getOption('skip-jobs') && [] !== $plan->jobs) {
            $io->section('Jobs');
            foreach ($plan->jobs as $job) {
                $process = new Process([\PHP_BINARY, 'bin/console', ...$job['command']], $this->projectDirectory, null, null, null);
                $start = hrtime(true);
                $exitCode = $process->run();
                $report->addJob($job['name'], implode(' ', $job['command']), (hrtime(true) - $start) / 1e9, $exitCode);
            }
            $io->table(['Job', 'Seconds', 'Exit code'], array_map(
                static fn (array $job): array => [$job['name'], number_format($job['seconds'], 1), $job['exitCode']],
                $report->jobs(),
            ));
        }

        $outputFile = $input->getOption('output');
        if (\is_string($outputFile) && '' !== $outputFile) {
            file_put_contents($this->path($outputFile), $report->toJson());
            $io->text(\sprintf('Campaign written to %s.', $outputFile));
        }

        return $this->compare($io, $report, $input->getOption('compare'));
    }

    private function compare(SymfonyStyle $io, MeasureReport $report, mixed $previousFile): int
    {
        $failures = array_map(
            static fn (array $page): string => \sprintf('%s: answered %d', $page['name'], $page['status']),
            array_filter($report->pages(), static fn (array $page): bool => $page['status'] >= 400),
        );

        if (\is_string($previousFile) && '' !== $previousFile) {
            /** @var array{pages?: list<array{name: string, steady: int}>} $previous */
            $previous = json_decode((string) file_get_contents($this->path($previousFile)), true, 512, \JSON_THROW_ON_ERROR);
            $differences = $report->differencesWith($previous);
            if ([] === $differences) {
                $io->success('Same query count as the previous campaign on every page.');
            }
            $failures = [...$failures, ...$differences];
        }

        if ([] !== $failures) {
            $io->error($failures);

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string|int>
     */
    private function placeholders(ConnectionInterface $connection, VolumeDatasetManifest $manifest): array
    {
        $product = $manifest->range('product') ?? [0, 0];
        $category = $manifest->range('category') ?? [0, 0];
        $order = $manifest->range('order') ?? [0, 0];
        $customer = $manifest->range('customer') ?? [0, 0];
        $locale = (string) $this->scalar($connection, 'SELECT locale FROM lang ORDER BY by_default DESC, id LIMIT 1');

        $categoryId = (int) $this->scalar(
            $connection,
            'SELECT category_id FROM product_category WHERE category_id BETWEEN ? AND ? GROUP BY category_id ORDER BY COUNT(*) DESC, category_id LIMIT 1',
            $category,
        );
        $customerId = (int) $this->scalar(
            $connection,
            'SELECT customer_id FROM `order` WHERE id BETWEEN ? AND ? GROUP BY customer_id ORDER BY COUNT(*) DESC, customer_id LIMIT 1',
            $order,
        );
        if (0 === $customerId) {
            $customerId = $customer[1];
        }

        return [
            'locale' => $locale,
            'product_id' => $product[1],
            'product_url' => $this->url($connection, 'product', $product[1], $locale),
            'category_id' => $categoryId,
            'category_url' => $this->url($connection, 'category', $categoryId, $locale),
            'category_products' => (int) $this->scalar($connection, 'SELECT COUNT(*) FROM product_category WHERE category_id = ?', [$categoryId]),
            'order_id' => $order[1],
            'customer_id' => $customerId,
            'customer_email' => (string) $this->scalar($connection, 'SELECT email FROM customer WHERE id = ?', [$customerId]),
            'customer_password' => VolumeDatasetGenerator::CUSTOMER_PASSWORD,
            'admin_products_last_page' => $this->lastPage($connection, 'product'),
            'admin_orders_last_page' => $this->lastPage($connection, 'order'),
            'admin_customers_last_page' => $this->lastPage($connection, 'customer'),
        ];
    }

    private function url(ConnectionInterface $connection, string $view, int $id, string $locale): string
    {
        $url = $this->scalar($connection, 'SELECT url FROM rewriting_url WHERE view = ? AND view_id = ? AND view_locale = ? AND redirected IS NULL ORDER BY id DESC LIMIT 1', [$view, (string) $id, $locale]);

        return '/'.((string) $url);
    }

    private function lastPage(ConnectionInterface $connection, string $table): int
    {
        return max(1, (int) ceil((int) $this->scalar($connection, \sprintf('SELECT COUNT(*) FROM `%s`', $table)) / self::BACK_OFFICE_PAGE_SIZE));
    }

    /**
     * @param list<int|string> $parameters
     */
    private function scalar(ConnectionInterface $connection, string $sql, array $parameters = []): string|int|null
    {
        $statement = $connection->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch(\PDO::FETCH_NUM);

        return \is_array($row) ? $row[0] : null;
    }

    private function path(string $file): string
    {
        return str_starts_with($file, '/') ? $file : $this->projectDirectory.'/'.$file;
    }
}
