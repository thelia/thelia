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

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\AdminLogQuery;
use Thelia\Model\CartQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderQuery;

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

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting maintenance purge...</info>');

        try {
            $cartNoOrderDays = (int) ConfigQuery::read(
                self::DEFAULT_CART_NO_ORDER_DAYS_KEY,
                60
            );

            $deletedCartNoOrder = $this->purgeCartsWithoutOrder($cartNoOrderDays);

            $output->writeln(sprintf(
                '<comment>Carts without order (>%d days):</comment> <info>%d deleted</info>',
                $cartNoOrderDays,
                $deletedCartNoOrder
            ));

            $cartAnonymousDays = (int) ConfigQuery::read(
                self::DEFAULT_CART_ANONYMOUS_DAYS_KEY,
                30
            );

            $deletedAnonymousCarts = $this->purgeAnonymousCarts($cartAnonymousDays);

            $output->writeln(sprintf(
                '<comment>Anonymous carts (>%d days):</comment> <info>%d deleted</info>',
                $cartAnonymousDays,
                $deletedAnonymousCarts
            ));

            $adminLogsDays = (int) ConfigQuery::read(
                self::DEFAULT_ADMIN_LOGS_DAYS_KEY,
                180
            );

            $deletedAdminLogs = $this->purgeAdminLogs($adminLogsDays);

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

    /**
     * Delete carts older than $days days that have no associated order.
     * @throws PropelException
     */
    private function purgeCartsWithoutOrder(int $days): int
    {
        $threshold = $this->getThresholdDate($days);

        // Retrieve IDs of carts that DO have an associated order
        $cartIdsWithOrder = OrderQuery::create()
            ->select('CartId')
            ->find()
            ->toArray();

        $query = CartQuery::create()->filterByCreatedAt($threshold, Criteria::LESS_THAN);

        if (!empty($cartIdsWithOrder)) {
            $query->filterById($cartIdsWithOrder, Criteria::NOT_IN);
        }

        // Only target carts with an identified customer
        $query->filterByCustomerId(null, Criteria::ISNOTNULL);

        return $query->delete();
    }

    /**
     * Delete anonymous carts (customer_id IS NULL) older than $days days.
     * @throws PropelException
     */
    private function purgeAnonymousCarts(int $days): int
    {
        $threshold = $this->getThresholdDate($days);

        return CartQuery::create()
            ->filterByCustomerId(null, Criteria::ISNULL)
            ->filterByCreatedAt($threshold, Criteria::LESS_THAN)
            ->delete();
    }

    /**
     * Delete admin log entries older than $days days.
     * @throws PropelException
     */
    private function purgeAdminLogs(int $days): int
    {
        $threshold = $this->getThresholdDate($days);

        return AdminLogQuery::create()
            ->filterByCreatedAt($threshold, Criteria::LESS_THAN)
            ->delete();
    }

    /**
     * Return a \DateTime set to midnight, $days days ago.
     */
    private function getThresholdDate(int $days): \DateTime
    {
        return (new \DateTime())->modify(sprintf('-%d days', $days));
    }
}
