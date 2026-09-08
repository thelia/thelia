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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Core\Event\Customer\CustomerPersonalDataExportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CustomerQuery;

/**
 * Writes everything the shop knows about an email address as JSON.
 *
 * An address may carry more than one row: ordering without an account opens one, and
 * registering later opens another. The export is therefore a list, one entry per row —
 * an export that stopped at the first would hand the buyer half of their own history
 * without saying so.
 */
class CustomerPersonalDataExportCommand extends ContainerAwareCommand
{
    public function configure(): void
    {
        $this
            ->setName('customer:export-personal-data')
            ->setDescription('Export everything the shop knows about one customer, as JSON.')
            ->addArgument('email', InputArgument::REQUIRED, 'Email address of the customer')
            ->addOption(
                'output-file',
                null,
                InputOption::VALUE_REQUIRED,
                'Write the archive to this file instead of the standard output'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $customers = CustomerQuery::create()->filterByEmail($email)->orderById()->find();

        if (0 === $customers->count()) {
            $output->writeln(\sprintf('<error>No customer found with email "%s".</error>', $email));

            return 1;
        }

        $personalData = [];

        foreach ($customers as $customer) {
            $event = new CustomerPersonalDataExportEvent($customer);
            $this->getDispatcher()->dispatch($event, TheliaEvents::CUSTOMER_PERSONAL_DATA_EXPORT);

            $personalData[] = $event->getPersonalData();
        }

        // Beside the JSON rather than in it: the export goes to the standard output when
        // no file is asked for, and a count written there would not be JSON any more.
        $this->countOutput($output)->writeln(\sprintf(
            '<info>%d customer record(s) found on "%s".</info>',
            $customers->count(),
            $email,
        ));

        $json = json_encode(
            $personalData,
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
        );

        $outputFile = $input->getOption('output-file');

        if (null === $outputFile) {
            $output->writeln($json);

            return 0;
        }

        if (false === file_put_contents($outputFile, $json)) {
            $output->writeln(\sprintf('<error>Unable to write to "%s".</error>', $outputFile));

            return 1;
        }

        $output->writeln(\sprintf('<info>Personal data of %s written to %s</info>', $email, $outputFile));

        return 0;
    }

    private function countOutput(OutputInterface $output): OutputInterface
    {
        return $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
    }
}
