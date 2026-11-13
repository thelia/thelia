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

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Domain\Subscription\Enum\SubscriptionStatus;
use Thelia\Model\Base\SubscriptionQuery as BaseSubscriptionQuery;

class SubscriptionQuery extends BaseSubscriptionQuery
{
    /**
     * The subscriptions something is due on at that moment, in one query that the indexes
     * on (status, next_billing_at) and (status, next_retry_at) serve: an installment due,
     * a declined one to try again, a cancellation whose end has come. The biller judges
     * each one again under its lock: this only narrows the list.
     *
     * @return list<int>
     */
    public static function dueIdsAt(\DateTimeInterface $now): array
    {
        $moment = $now->format('Y-m-d H:i:s');
        $billed = [SubscriptionStatus::Trial->value, SubscriptionStatus::Active->value, SubscriptionStatus::Cancelled->value];
        $retried = [SubscriptionStatus::PaymentFailed->value, SubscriptionStatus::Suspended->value, SubscriptionStatus::Cancelled->value];

        // Propel joins its where() clauses with AND and no parentheses: the three cases are
        // combined as named conditions, which Propel wraps in parentheses.
        $ids = self::create()
            ->condition('billedStatus', 'Subscription.Status IN ?', $billed)
            ->condition('billingDue', 'Subscription.NextBillingAt <= ?', $moment)
            ->combine(['billedStatus', 'billingDue'], Criteria::LOGICAL_AND, 'billing')
            ->condition('retriedStatus', 'Subscription.Status IN ?', $retried)
            ->condition('retryDue', 'Subscription.NextRetryAt <= ?', $moment)
            ->combine(['retriedStatus', 'retryDue'], Criteria::LOGICAL_AND, 'retry')
            ->condition('cancelledStatus', 'Subscription.Status = ?', SubscriptionStatus::Cancelled->value)
            ->condition('cancellationDue', 'Subscription.CancelAt <= ?', $moment)
            ->combine(['cancelledStatus', 'cancellationDue'], Criteria::LOGICAL_AND, 'cancellation')
            ->where(['billing', 'retry', 'cancellation'], Criteria::LOGICAL_OR)
            ->orderById()
            ->select(['Id'])
            ->find()
            ->getData();

        return array_values(array_map('intval', $ids));
    }
}
