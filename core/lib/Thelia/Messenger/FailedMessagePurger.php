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

/**
 * Deletes the jobs set aside in the failure transport for longer than a given time.
 *
 * A failed job keeps everything it was dispatched with, an order confirmation keeps
 * the address and the content of the order, and nothing else ever removes it: left
 * alone, the failure transport becomes a store of personal data with no end date.
 * The date of a job is the one it was set aside on, carried by its last
 * RedeliveryStamp; a job that carries none is dated by nothing and kept.
 */
final readonly class FailedMessagePurger
{
    public function __construct(
        #[Autowire(service: 'messenger.transport.failed')]
        private TransportInterface $failureTransport,
    ) {
    }

    /**
     * @return int the number of jobs deleted, or that would be with $dryRun
     */
    public function purgeSetAsideBefore(\DateTimeImmutable $limit, bool $dryRun = false): int
    {
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
