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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Core\Event\Sale\SaleActiveStatusCheckEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Class SaleCheckActivationCommand.
 *
 * @author manuel raynaud <manu@raynaud.io>
 */
#[AsCommand(name: 'sale:check-activation', description: 'check the activation and deactivation dates of sales, and perform the required action depending on the current date.')]
class SaleCheckActivationCommand extends ContainerAwareCommand
{
    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->getDispatcher()->dispatch(
                new SaleActiveStatusCheckEvent(),
                TheliaEvents::CHECK_SALE_ACTIVATION_EVENT,
            );

            $output->writeln('<info>Sale verification processed successfully</info>');
        } catch (\Exception $exception) {
            $output->writeln(\sprintf('<error>Error : %s</error>', $exception->getMessage()));

            return 1;
        }

        return 0;
    }
}
