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
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Maintenance\Service\MaintenancePurgeService;
use Thelia\Model\ConfigQuery;

class MaintenancePurgeCommand extends ContainerAwareCommand
{
    private const DEFAULT_CART_NO_ORDER_DAYS_KEY = 'purification_cart_no_order_days';
    private const DEFAULT_CART_ANONYMOUS_DAYS_KEY = 'purification_cart_anonymous_days';
    private const DEFAULT_ADMIN_LOGS_DAYS_KEY = 'purification_admin_logs_days';

    public function configure(): void
    {
        $this
            ->setName('maintenance:purge')
            ->setDescription(
                'Purge old data from the database: carts without orders, anonymous carts, and admin logs.'
            );
    }

    public function __construct(protected MaintenancePurgeService $maintenancePurgeService)
    {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting maintenance purge...</info>');

        try {
            $cartNoOrderDays = (int) ConfigQuery::read(
                self::DEFAULT_CART_NO_ORDER_DAYS_KEY,
                60
            );

            $deletedCartNoOrder = $this->maintenancePurgeService->purgeCartsWithoutOrder($cartNoOrderDays);

            $output->writeln(sprintf(
                '<comment>Carts without order (>%d days):</comment> <info>%d deleted</info>',
                $cartNoOrderDays,
                $deletedCartNoOrder
            ));

            $cartAnonymousDays = (int) ConfigQuery::read(
                self::DEFAULT_CART_ANONYMOUS_DAYS_KEY,
                30
            );

            $deletedAnonymousCarts = $this->maintenancePurgeService->purgeAnonymousCarts($cartAnonymousDays);

            $output->writeln(sprintf(
                '<comment>Anonymous carts (>%d days):</comment> <info>%d deleted</info>',
                $cartAnonymousDays,
                $deletedAnonymousCarts
            ));

            $adminLogsDays = (int) ConfigQuery::read(
                self::DEFAULT_ADMIN_LOGS_DAYS_KEY,
                180
            );

            $deletedAdminLogs = $this->maintenancePurgeService->purgeAdminLogs($adminLogsDays);

            $output->writeln(sprintf(
                '<comment>Admin logs (>%d days):</comment> <info>%d deleted</info>',
                $adminLogsDays,
                $deletedAdminLogs
            ));

            $event = new MaintenancePurgeEvent();
            $this->getDispatcher()->dispatch($event, TheliaEvents::MAINTENANCE_PURGE);

            foreach ($event->getResults() as $result) {
                $output->writeln($result);
            }

            $output->writeln('<info>Maintenance purge completed successfully.</info>');
        } catch (\Exception $ex) {
            $output->writeln(sprintf('<error>Error: %s</error>', $ex->getMessage()));

            return 1;
        }

        return 0;
    }
}
