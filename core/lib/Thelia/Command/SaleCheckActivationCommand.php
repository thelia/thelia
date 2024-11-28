<?php

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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Core\Event\Sale\SaleActiveStatusCheckEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Class SaleCheckActivationCommand.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
class SaleCheckActivationCommand extends ContainerAwareCommand
{
    public function configure(): void
    {
        $this
            ->setName('sale:check-activation')
            ->setDescription('check the activation and deactivation dates of sales, and perform the required action depending on the current date.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->getDispatcher()->dispatch(
                new SaleActiveStatusCheckEvent(),
                TheliaEvents::CHECK_SALE_ACTIVATION_EVENT
            );

            $output->writeln('<info>Sale verification processed successfully</info>');
        } catch (\Exception $ex) {
            $output->writeln(sprintf('<error>Error : %s</error>', $ex->getMessage()));

            return 1;
        }

        return 0;
    }
}
