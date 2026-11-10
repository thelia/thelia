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

use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Thelia\Domain\CMS\Publication\PublicationMoment;
use Thelia\Domain\Subscription\Message\BillSubscriptionInstallment;
use Thelia\Model\SubscriptionQuery;

#[AsCommand(name: 'subscription:bill-due', description: 'Bill the installments of the subscriptions that are due, try again the declined ones, and end the cancelled subscriptions whose paid period is over.')]
class SubscriptionBillDueCommand extends ContainerAwareCommand
{
    public function __construct(
        protected MessageBusInterface $bus,
        protected ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Run it every few minutes from the scheduler of the server, or let the scheduler of the shop run it. '
                .'One job is sent per subscription that has something due; each job takes a lock on its subscription '
                .'and reads it again, so two runs at once never bill an installment twice. Without a worker, the jobs '
                .'run at once, in this command.',
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the subscriptions that have something due, without sending any job');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $ids = SubscriptionQuery::dueIdsAt(PublicationMoment::atTheSecond($this->clock->now()));

        if ([] === $ids) {
            $output->writeln('<info>Subscription billing</info>: nothing is due.');

            return self::SUCCESS;
        }

        foreach ($ids as $id) {
            if (!$dryRun) {
                $this->bus->dispatch(new BillSubscriptionInstallment($id));
            }

            $output->writeln(\sprintf('  Subscription #%d%s', $id, $dryRun ? ' (would be billed)' : ''));
        }

        $output->writeln(\sprintf('<info>Subscription billing</info>: %d subscription(s) %s.', \count($ids), $dryRun ? 'due' : 'sent to billing'));

        return self::SUCCESS;
    }
}
