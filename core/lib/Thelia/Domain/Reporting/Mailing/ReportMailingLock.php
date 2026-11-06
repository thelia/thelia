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

namespace Thelia\Domain\Reporting\Mailing;

use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * A mailing is sent by one process at a time, whether a scheduled run or a send asked for
 * now: the second one gets no lock and does not send. The lock dies with its process, and
 * with a store that honours it, after TTL_SECONDS should the process hang.
 */
final readonly class ReportMailingLock
{
    public const string KEY_PREFIX = 'report_mailing.';
    private const float TTL_SECONDS = 900.0;

    public function __construct(
        private LockFactory $lockFactory,
    ) {
    }

    /**
     * The lock of the mailing, held, or null when another process holds it.
     */
    public function acquire(int $mailingId): ?LockInterface
    {
        $lock = $this->lockFactory->createLock(self::KEY_PREFIX.$mailingId, self::TTL_SECONDS);

        return $lock->acquire() ? $lock : null;
    }
}
