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

namespace Thelia\Tests\Unit\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Thelia\Domain\DataTransfer\Job\RunExportJob;
use Thelia\Messenger\Middleware\ReplayedJobMiddleware;

/**
 * messenger:failed:retry replays a job as it was set aside: one that was looked at
 * again a few times starts afresh, or it could never restart a failed job.
 */
final class ReplayedJobMiddlewareTest extends TestCase
{
    public function testAJobReplayedFromTheFailureTransportStartsAfresh(): void
    {
        $handled = $this->handle(new Envelope(new RunExportJob(12, 3), [new SentToFailureTransportStamp('async_heavy'), new ReceivedStamp('failed')]));

        self::assertEquals(new RunExportJob(12), $handled->getMessage());
        self::assertNotNull($handled->last(SentToFailureTransportStamp::class), 'The stamps stay.');
    }

    public function testAJobLookedAtAgainKeepsItsCount(): void
    {
        $handled = $this->handle(new Envelope(new RunExportJob(12, 3), [new ReceivedStamp('async_heavy')]));

        self::assertEquals(new RunExportJob(12, 3), $handled->getMessage());
    }

    private function handle(Envelope $envelope): Envelope
    {
        // The last middleware hands back the envelope it was given.
        $last = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $envelope;
            }
        };

        return (new ReplayedJobMiddleware())->handle($envelope, new StackMiddleware([$last]));
    }
}
