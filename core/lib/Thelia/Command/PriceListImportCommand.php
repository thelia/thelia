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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Domain\Pricing\PriceList\Import\PriceListImportException;
use Thelia\Domain\Pricing\PriceList\Import\PriceListImportReport;
use Thelia\Domain\Pricing\PriceList\Import\PriceListLineImport;
use Thelia\Model\PriceListQuery;

/**
 * Loads the lines of a price list from a CSV file (pse_ref or pse_id, min_quantity, price or
 * discount_percent). With --dry-run nothing is written: the report says what would be. The exit
 * code is not zero when a line is in error, or when the file is refused.
 */
#[AsCommand(
    name: 'thelia:price-list:import',
    description: 'Import the lines of a price list from a CSV file, 5 MB at most.',
)]
class PriceListImportCommand extends ContainerAwareCommand
{
    public function __construct(private readonly PriceListLineImport $import)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('code', InputArgument::REQUIRED, 'The code of the price list')
            ->addArgument('file', InputArgument::REQUIRED, 'The CSV file')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be written, write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $priceList = PriceListQuery::create()->findOneByCode((string) $input->getArgument('code'));

        if (null === $priceList) {
            $output->writeln('<error>No price list has this code.</error>');

            return 1;
        }

        $file = new \SplFileInfo((string) $input->getArgument('file'));

        try {
            $report = $input->getOption('dry-run')
                ? $this->import->preview($priceList, $file)
                : $this->import->apply($priceList, $file);
        } catch (PriceListImportException $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return 1;
        }

        $this->print($report, $output);

        return $report->hasErrors() ? 1 : 0;
    }

    private function print(PriceListImportReport $report, OutputInterface $output): void
    {
        $output->writeln(\sprintf(
            '<info>%s: %d created, %d updated, %d error(s).</info>',
            $report->applied ? 'Imported' : 'Dry run, nothing written',
            $report->created,
            $report->updated,
            $report->errorCount,
        ));

        foreach ($report->errors as $error) {
            $output->writeln(\sprintf('<comment>line %d: %s%s</comment>', $error->line, $error->reason, null === $error->detail ? '' : ' ('.$error->detail.')'));
        }

        if ($report->omittedErrorCount() > 0) {
            $output->writeln(\sprintf('<comment>... and %d more line(s) in error.</comment>', $report->omittedErrorCount()));
        }
    }
}
