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
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Core\Event\Currency\CurrencyUpdateRateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;

/**
 * Class UpdateCurrenciesRates.
 *
 * @author Franck Allimant <thelia@cqfdev.fr>
 */
#[AsCommand(name: 'currency:update-rates', description: 'Update currency rates')]
class UpdateCurrenciesRates extends ContainerAwareCommand
{
    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /* @var EventDispatcherInterface $dispatcher */
        try {
            $event = new CurrencyUpdateRateEvent();

            $this->getDispatcher()->dispatch($event, TheliaEvents::CURRENCY_UPDATE_RATES);

            if ($event->hasUndefinedRates()) {
                $output->writeln('<comment>Rate was not found for the following currencies:');

                $undefinedCurrencies = CurrencyQuery::create()
                    ->filterById($event->getUndefinedRates())
                    ->find();

                /** @var Currency $currency */
                foreach ($undefinedCurrencies as $currency) {
                    $output->writeln('  -'.$currency->getName().' ('.$currency->getCode().'), current rate is '.$currency->getRate());
                }

                $output->writeln('Update done with errors</comment>');

                return 1;
            }
        } catch (Exception $exception) {
            // Any error
            $output->writeln('<error>Update failed: '.$exception->getMessage().'</error>');

            return 1;
        }

        $output->writeln('<info>Update done withourt errors</info>');

        return 0;
    }
}
