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

namespace Thelia\Messenger;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Thelia\Messenger\Transport\ConfiguredQueues;

/**
 * Deletes the jobs set aside in the failure transport for longer than a given time.
 *
 * A failed job keeps everything it was dispatched with, an order confirmation keeps
 * the address and the content of the order, and nothing else ever removes it: left
 * alone, the failure transport becomes a store of personal data with no end date.
 * The date of a job is the one it was set aside on. In the shop database, the
 * default, that is the date its row was written, and the old ones are deleted in one
 * statement however many there are. Anywhere else every job is read, and dated by its
 * last RedeliveryStamp; a job that carries none is dated by nothing and kept.
 */
final readonly class FailedMessagePurger
{
    /** How long a failed job is kept, and with it the row of a failed export or import. */
    public const RETENTION_DAYS = 30;

    public function __construct(
        #[Autowire(service: 'messenger.transport.failed')]
        private TransportInterface $failureTransport,
        private ConfiguredQueues $queues,
    ) {
    }

    /**
     * @return int the number of jobs deleted, or that would be with $dryRun
     */
    public function purgeSetAsideBefore(\DateTimeImmutable $limit, bool $dryRun = false): int
    {
        $queue = $this->queues->failureQueueInTheShopDatabase();

        if (null !== $queue) {
            return $queue->deleteQueuedBefore($limit, $dryRun);
        }

        if (!$this->failureTransport instanceof ListableReceiverInterface) {
            throw new \LogicException(\sprintf('The failure transport (%s) cannot list its jobs, so the shop cannot tell which are old enough to delete.', $this->failureTransport::class));
        }

        $purged = 0;

        foreach ($this->failureTransport->all() as $envelope) {
            $setAsideAt = self::setAsideAt($envelope);

            if (null === $setAsideAt || $setAsideAt >= $limit) {
                continue;
            }

            if (!$dryRun) {
                $this->failureTransport->reject($envelope);
            }

            ++$purged;
        }

        return $purged;
    }

    private static function setAsideAt(Envelope $envelope): ?\DateTimeInterface
    {
        $stamp = $envelope->last(RedeliveryStamp::class);

        return $stamp instanceof RedeliveryStamp ? $stamp->getRedeliveredAt() : null;
    }
}
