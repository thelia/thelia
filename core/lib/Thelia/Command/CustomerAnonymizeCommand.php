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
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Thelia\Core\Event\Customer\CustomerAnonymizeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;

/**
 * Erases the identifying data of a customer, keeping the orders.
 *
 * An email address may carry more than one row: ordering without an account opens one,
 * and registering later opens another. An erasure request is about the person behind the
 * address, so every row it carries is erased — reading only the first would leave the
 * other one's name, addresses and orders untouched, and say nothing about it.
 */
class CustomerAnonymizeCommand extends ContainerAwareCommand
{
    public function configure(): void
    {
        $this
            ->setName('customer:anonymize')
            ->setDescription(
                'Erase the identifying data of a customer, keeping the accounting record of the orders.'
            )
            ->addArgument('email', InputArgument::REQUIRED, 'Email address of the customer to anonymize')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Do not ask for confirmation');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $customers = CustomerQuery::create()->filterByEmail($email)->orderById()->find();

        if (0 === $customers->count()) {
            $output->writeln(\sprintf('<error>No customer found with email "%s".</error>', $email));

            return 1;
        }

        $references = implode(', ', array_map(
            static fn (Customer $customer): string => (string) $customer->getRef(),
            iterator_to_array($customers),
        ));

        $output->writeln(\sprintf(
            '<info>%d customer record(s) found on "%s": %s</info>',
            $customers->count(),
            $email,
            $references,
        ));

        if (!$input->getOption('force')) {
            $question = new ConfirmationQuestion(
                \sprintf(
                    'Anonymize %d record(s) on %s? This cannot be undone. [y/N] ',
                    $customers->count(),
                    $email,
                ),
                false,
            );

            if (!$this->getHelper('question')->ask($input, $output, $question)) {
                $output->writeln('<comment>Aborted.</comment>');

                return 0;
            }
        }

        foreach ($customers as $customer) {
            $this->getDispatcher()->dispatch(
                new CustomerAnonymizeEvent($customer),
                TheliaEvents::CUSTOMER_ANONYMIZE,
            );
        }

        $output->writeln(\sprintf(
            '<info>%d customer record(s) anonymized. Orders kept, accounts disabled.</info>',
            $customers->count(),
        ));

        return 0;
    }
}
