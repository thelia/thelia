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
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Core\Event\Customer\CustomerPersonalDataExportEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CustomerQuery;

/**
 * Writes everything the shop knows about one customer as JSON.
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
        $customer = CustomerQuery::create()->findOneByEmail($email);

        if (null === $customer) {
            $output->writeln(\sprintf('<error>No customer found with email "%s".</error>', $email));

            return 1;
        }

        $event = new CustomerPersonalDataExportEvent($customer);
        $this->getDispatcher()->dispatch($event, TheliaEvents::CUSTOMER_PERSONAL_DATA_EXPORT);

        $json = json_encode(
            $event->getPersonalData(),
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
}
