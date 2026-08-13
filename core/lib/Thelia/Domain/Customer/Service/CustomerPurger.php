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

namespace Thelia\Domain\Customer\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Customer\CustomerAnonymizeEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\CustomerQuery;

/**
 * Applies a retention period to customer accounts: past it, an account that
 * nobody uses anymore is anonymized rather than kept with its identity.
 *
 * An account is measured against two distinct periods, because an account
 * backed by orders is also backed by an accounting retention obligation:
 *
 *  - an account that never ordered ages from the day it was created;
 *  - an account that ordered ages from its most recent order.
 *
 * Accounts already carrying the anonymization marker are skipped, so a job
 * that runs every night does its work once per account.
 */
final readonly class CustomerPurger
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function countAccountsWithoutOrder(int $days): int
    {
        return $this->accountsWithoutOrder($days)->count();
    }

    public function anonymizeAccountsWithoutOrder(int $days): int
    {
        return $this->anonymizeAll($this->accountsWithoutOrder($days));
    }

    public function countAccountsAfterLastOrder(int $days): int
    {
        return $this->accountsAfterLastOrder($days)->count();
    }

    public function anonymizeAccountsAfterLastOrder(int $days): int
    {
        return $this->anonymizeAll($this->accountsAfterLastOrder($days));
    }

    private function accountsWithoutOrder(int $days): CustomerQuery
    {
        $query = $this->identifiedAccounts()
            ->filterByCreatedAt($this->thresholdDate($days), Criteria::LESS_THAN);

        $query->useOrderNotExistsQuery()->endUse();

        return $query;
    }

    private function accountsAfterLastOrder(int $days): CustomerQuery
    {
        $query = $this->identifiedAccounts();

        // An account with no order at all ages on the other period, so it is
        // excluded here rather than counted as idle since forever.
        $query->useOrderExistsQuery()->endUse();

        $query->useOrderNotExistsQuery('recent_order')
            ->filterByCreatedAt($this->thresholdDate($days), Criteria::GREATER_EQUAL)
            ->endUse();

        return $query;
    }

    private function identifiedAccounts(): CustomerQuery
    {
        return CustomerQuery::create()->filterByAnonymizedAt(null, Criteria::ISNULL);
    }

    /**
     * Anonymization goes through the event, so that the modules listening to
     * it erase their own data on a retention run exactly as they do when an
     * administrator triggers the operation by hand.
     */
    private function anonymizeAll(CustomerQuery $query): int
    {
        $anonymized = 0;

        foreach ($query->find() as $customer) {
            $this->eventDispatcher->dispatch(
                new CustomerAnonymizeEvent($customer),
                TheliaEvents::CUSTOMER_ANONYMIZE,
            );

            ++$anonymized;
        }

        return $anonymized;
    }

    private function thresholdDate(int $days): \DateTime
    {
        return (new \DateTime())->modify(\sprintf('-%d days', $days));
    }
}
