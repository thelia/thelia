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

namespace Thelia\Messenger\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Thelia\Messenger\Message\ReplayableJob;

/**
 * Starts a job replayed from the failure transport afresh ({@see ReplayableJob}).
 *
 * `messenger:failed:retry` hands the bus the envelope it read from the failure
 * transport: received, and stamped as sent there, which is how Symfony itself tells a
 * replay (FailedMessageProcessingMiddleware).
 */
final readonly class ReplayedJobMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        if ($message instanceof ReplayableJob
            && null !== $envelope->last(SentToFailureTransportStamp::class)
            && null !== $envelope->last(ReceivedStamp::class)
        ) {
            $envelope = new Envelope($message->forReplay(), array_merge(...array_values($envelope->all())));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
