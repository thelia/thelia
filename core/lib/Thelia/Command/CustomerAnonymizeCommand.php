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
use Thelia\Model\CustomerQuery;

/**
 * Erases the identifying data of one customer, keeping the orders.
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
        $customer = CustomerQuery::create()->findOneByEmail($email);

        if (null === $customer) {
            $output->writeln(\sprintf('<error>No customer found with email "%s".</error>', $email));

            return 1;
        }

        if (!$input->getOption('force')) {
            $question = new ConfirmationQuestion(
                \sprintf(
                    'Anonymize customer %s (%s)? This cannot be undone. [y/N] ',
                    $customer->getRef(),
                    $email,
                ),
                false,
            );

            if (!$this->getHelper('question')->ask($input, $output, $question)) {
                $output->writeln('<comment>Aborted.</comment>');

                return 0;
            }
        }

        $this->getDispatcher()->dispatch(
            new CustomerAnonymizeEvent($customer),
            TheliaEvents::CUSTOMER_ANONYMIZE,
        );

        $output->writeln(\sprintf(
            '<info>Customer %s anonymized. Orders kept, account disabled.</info>',
            $customer->getRef(),
        ));

        return 0;
    }
}
