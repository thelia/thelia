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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Admin\Service\AdminLogPurger;
use Thelia\Domain\Cart\Service\CartPurger;
use Thelia\Domain\Customer\Service\CustomerPurger;
use Thelia\Domain\Form\Service\FormFirewallPurger;
use Thelia\Model\ConfigQuery;

class MaintenancePurgeCommand extends ContainerAwareCommand
{
    private const DEFAULT_CART_NO_ORDER_DAYS_KEY = 'purification_cart_no_order_days';
    private const DEFAULT_CART_ANONYMOUS_DAYS_KEY = 'purification_cart_anonymous_days';
    private const DEFAULT_ADMIN_LOGS_DAYS_KEY = 'purification_admin_logs_days';
    private const DEFAULT_FORM_FIREWALL_DAYS_KEY = 'purification_form_firewall_days';
    private const DEFAULT_CUSTOMER_NO_ORDER_DAYS_KEY = 'purification_customer_no_order_days';
    private const DEFAULT_CUSTOMER_AFTER_LAST_ORDER_DAYS_KEY = 'purification_customer_after_last_order_days';

    public function configure(): void
    {
        $this
            ->setName('maintenance:purge')
            ->setDescription(
                'Purge old data from the database: carts without orders, anonymous carts, admin logs, form firewall records, and the identity of accounts nobody uses anymore.'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Report what the purge would remove, without touching anything'
            );
    }

    public function __construct(
        protected AdminLogPurger $adminLogPurger,
        protected CartPurger $cartPurger,
        protected FormFirewallPurger $formFirewallPurger,
        protected CustomerPurger $customerPurger,
    ) {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');

        $output->writeln($dryRun
            ? '<info>Starting maintenance purge (dry run, nothing is written)...</info>'
            : '<info>Starting maintenance purge...</info>');

        try {
            $cartNoOrderDays = (int) ConfigQuery::read(
                self::DEFAULT_CART_NO_ORDER_DAYS_KEY,
                60
            );

            $deletedCartNoOrder = $dryRun
                ? $this->cartPurger->countCartsWithoutOrder($cartNoOrderDays)
                : $this->cartPurger->purgeCartsWithoutOrder($cartNoOrderDays);

            $output->writeln(\sprintf(
                '<comment>Carts without order (>%d days):</comment> <info>%d %s</info>',
                $cartNoOrderDays,
                $deletedCartNoOrder,
                $dryRun ? 'to delete' : 'deleted'
            ));

            $cartAnonymousDays = (int) ConfigQuery::read(
                self::DEFAULT_CART_ANONYMOUS_DAYS_KEY,
                30
            );

            $deletedAnonymousCarts = $dryRun
                ? $this->cartPurger->countAnonymousCarts($cartAnonymousDays)
                : $this->cartPurger->purgeAnonymousCarts($cartAnonymousDays);

            $output->writeln(\sprintf(
                '<comment>Anonymous carts (>%d days):</comment> <info>%d %s</info>',
                $cartAnonymousDays,
                $deletedAnonymousCarts,
                $dryRun ? 'to delete' : 'deleted'
            ));

            $adminLogsDays = (int) ConfigQuery::read(
                self::DEFAULT_ADMIN_LOGS_DAYS_KEY,
                180
            );

            $deletedAdminLogs = $dryRun
                ? $this->adminLogPurger->countAdminLogs($adminLogsDays)
                : $this->adminLogPurger->purgeAdminLogs($adminLogsDays);

            $output->writeln(\sprintf(
                '<comment>Admin logs (>%d days):</comment> <info>%d %s</info>',
                $adminLogsDays,
                $deletedAdminLogs,
                $dryRun ? 'to delete' : 'deleted'
            ));

            $formFirewallDays = (int) ConfigQuery::read(
                self::DEFAULT_FORM_FIREWALL_DAYS_KEY,
                1
            );

            $deletedFormFirewall = $dryRun
                ? $this->formFirewallPurger->countExpiredEntries($formFirewallDays)
                : $this->formFirewallPurger->purgeExpiredEntries($formFirewallDays);

            $output->writeln(\sprintf(
                '<comment>Form firewall records (>%d days):</comment> <info>%d %s</info>',
                $formFirewallDays,
                $deletedFormFirewall,
                $dryRun ? 'to delete' : 'deleted'
            ));

            $this->anonymizeInactiveAccounts($output, $dryRun);

            $event = new MaintenancePurgeEvent();
            $this->getDispatcher()->dispatch($event, TheliaEvents::MAINTENANCE_PURGE);

            foreach ($event->getResults() as $result) {
                $output->writeln($result);
            }

            $output->writeln('<info>Maintenance purge completed successfully.</info>');
        } catch (\Exception $ex) {
            $output->writeln(\sprintf('<error>Error: %s</error>', $ex->getMessage()));

            return 1;
        }

        return 0;
    }

    /**
     * Customer retention is off until the shop sets a period: erasing an
     * identity cannot be undone, and the shop is the only one that knows how
     * long it is allowed to keep the data. A period of 0 keeps it off.
     */
    private function anonymizeInactiveAccounts(OutputInterface $output, bool $dryRun): void
    {
        $noOrderDays = (int) ConfigQuery::read(self::DEFAULT_CUSTOMER_NO_ORDER_DAYS_KEY, 0);
        $afterLastOrderDays = (int) ConfigQuery::read(self::DEFAULT_CUSTOMER_AFTER_LAST_ORDER_DAYS_KEY, 0);

        if ($noOrderDays <= 0 && $afterLastOrderDays <= 0) {
            $output->writeln(\sprintf(
                '<comment>Customer retention:</comment> <info>disabled (set %s and %s to enable it)</info>',
                self::DEFAULT_CUSTOMER_NO_ORDER_DAYS_KEY,
                self::DEFAULT_CUSTOMER_AFTER_LAST_ORDER_DAYS_KEY
            ));

            return;
        }

        if ($noOrderDays > 0) {
            $accounts = $dryRun
                ? $this->customerPurger->countAccountsWithoutOrder($noOrderDays)
                : $this->customerPurger->anonymizeAccountsWithoutOrder($noOrderDays);

            $output->writeln(\sprintf(
                '<comment>Accounts that never ordered (>%d days):</comment> <info>%d %s</info>',
                $noOrderDays,
                $accounts,
                $dryRun ? 'to anonymize' : 'anonymized'
            ));
        }

        if ($afterLastOrderDays > 0) {
            $accounts = $dryRun
                ? $this->customerPurger->countAccountsAfterLastOrder($afterLastOrderDays)
                : $this->customerPurger->anonymizeAccountsAfterLastOrder($afterLastOrderDays);

            $output->writeln(\sprintf(
                '<comment>Accounts idle since their last order (>%d days):</comment> <info>%d %s</info>',
                $afterLastOrderDays,
                $accounts,
                $dryRun ? 'to anonymize' : 'anonymized'
            ));
        }
    }
}
