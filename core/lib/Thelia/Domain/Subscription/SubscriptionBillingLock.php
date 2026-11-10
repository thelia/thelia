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

namespace Thelia\Domain\Subscription;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Keeps two billings of the same subscription from running at once on the nodes that share
 * the lock store (LOCK_DSN). Never waited for: the billing that does not get it leaves the
 * installment to the one that holds it. The database still arbitrates between nodes that
 * do not share the store: the unique key of subscription_order.
 */
final readonly class SubscriptionBillingLock
{
    public const string KEY_PREFIX = 'subscription-billing-';
    private const float TTL_SECONDS = 600.0;

    public function __construct(
        private LockFactory $lockFactory,
    ) {
    }

    public function acquire(int $subscriptionId): ?LockInterface
    {
        $lock = $this->lockFactory->createLock(self::KEY_PREFIX.$subscriptionId, self::TTL_SECONDS);

        return $lock->acquire() ? $lock : null;
    }
}
