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

namespace Thelia\Tests\Unit\Domain\CMS\Publication;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\CMS\Publication\Exception\InvalidPublicationDatesException;
use Thelia\Domain\CMS\Publication\PublicationAction;
use Thelia\Domain\CMS\Publication\PublicationRequest;

/**
 * A gesture carries its dates in the right order, or is not built at all.
 */
final class PublicationRequestTest extends TestCase
{
    public function testAWithdrawalBeforeThePublicationIsRefused(): void
    {
        $this->expectException(InvalidPublicationDatesException::class);

        PublicationRequest::schedule(new \DateTimeImmutable('2026-11-01 10:00:00'), new \DateTimeImmutable('2026-11-01 09:00:00'));
    }

    public function testAWithdrawalAtTheSameSecondAsThePublicationIsRefused(): void
    {
        $this->expectException(InvalidPublicationDatesException::class);

        PublicationRequest::publish(new \DateTimeImmutable('2026-11-01 10:00:00'), new \DateTimeImmutable('2026-11-01 10:00:00'));
    }

    public function testAWithdrawalAfterThePublicationIsKept(): void
    {
        $request = PublicationRequest::schedule(new \DateTimeImmutable('2026-11-01 10:00:00'), new \DateTimeImmutable('2026-11-01 10:00:01'));

        self::assertSame(PublicationAction::Schedule, $request->action);
        self::assertSame('2026-11-01 10:00:01', $request->unpublishAt?->format('Y-m-d H:i:s'));
    }

    public function testTheGesturesAreBuiltFromTheStringsAFormCarries(): void
    {
        $schedule = PublicationRequest::fromStrings('schedule', '2026-11-01 10:00', '2026-11-02 10:00');
        self::assertSame(PublicationAction::Schedule, $schedule->action);
        self::assertSame('2026-11-01 10:00:00', $schedule->publishAt?->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-02 10:00:00', $schedule->unpublishAt?->format('Y-m-d H:i:s'));

        self::assertSame(PublicationAction::Publish, PublicationRequest::fromStrings('publish')->action);
        self::assertSame(PublicationAction::Cancel, PublicationRequest::fromStrings('cancel', '2026-11-01 10:00')->action);
        self::assertSame(PublicationAction::Unpublish, PublicationRequest::fromStrings('unpublish')->action);

        $withdrawal = PublicationRequest::fromStrings('schedule_unpublication', null, '');
        self::assertSame(PublicationAction::ScheduleUnpublication, $withdrawal->action);
        self::assertNull($withdrawal->unpublishAt, 'an empty string clears the date');
    }

    public function testAnUnknownGestureIsRefused(): void
    {
        $this->expectException(InvalidPublicationDatesException::class);

        PublicationRequest::fromStrings('archive');
    }

    public function testAScheduleWithoutADateIsRefused(): void
    {
        $this->expectException(InvalidPublicationDatesException::class);

        PublicationRequest::fromStrings('schedule', '', null);
    }

    public function testAStringThatIsNotADateIsRefused(): void
    {
        $this->expectException(InvalidPublicationDatesException::class);

        PublicationRequest::fromStrings('schedule', 'next tuesday-ish');
    }
}
