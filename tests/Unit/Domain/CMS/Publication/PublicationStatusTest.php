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
use Thelia\Domain\CMS\Publication\PublicationState;
use Thelia\Domain\CMS\Publication\PublicationStatus;

/**
 * The four states read from `visible` and the two dates, with the bounds at the second.
 */
final class PublicationStatusTest extends TestCase
{
    private const string NOW = '2026-10-21 12:00:00';

    public function testAVisibleRowIsPublishedWhateverItsDatesSay(): void
    {
        self::assertSame(PublicationState::Published, $this->statusOf(true, null, null)->state);
        self::assertSame(PublicationState::Published, $this->statusOf(true, '2026-10-01 00:00:00', '2026-12-01 00:00:00')->state);
        // Dates in the past or in the wrong order do not take a visible row off the shop: visible stays the truth.
        self::assertSame(PublicationState::Published, $this->statusOf(true, '2026-12-01 00:00:00', null)->state);
    }

    public function testAHiddenRowWithNoDateIsADraft(): void
    {
        $status = $this->statusOf(false, null, null);

        self::assertSame(PublicationState::Draft, $status->state);
        self::assertTrue($status->isDraft());
        self::assertFalse($status->isOverdue());
        self::assertNull($status->pendingDate());
    }

    public function testAHiddenRowWithAFutureWithdrawalAndNoPublicationDateIsStillADraft(): void
    {
        self::assertSame(PublicationState::Draft, $this->statusOf(false, null, '2026-12-01 00:00:00')->state);
    }

    public function testAHiddenRowWithAPublicationDateIsScheduled(): void
    {
        $status = $this->statusOf(false, '2026-10-22 09:00:00', null);

        self::assertSame(PublicationState::Scheduled, $status->state);
        self::assertFalse($status->isOverdue());
        self::assertSame('2026-10-22 09:00:00', $status->pendingDate()?->format('Y-m-d H:i:s'));
    }

    public function testAScheduledRowWhoseDateHasComeIsOverdue(): void
    {
        self::assertTrue($this->statusOf(false, self::NOW, null)->isOverdue(), 'the second it is due');
        self::assertTrue($this->statusOf(false, '2026-10-21 11:59:59', null)->isOverdue());
        self::assertFalse($this->statusOf(false, '2026-10-21 12:00:01', null)->isOverdue(), 'one second ahead');
    }

    public function testAHiddenRowWhoseWithdrawalDateHasComeIsUnpublished(): void
    {
        self::assertSame(PublicationState::Unpublished, $this->statusOf(false, null, self::NOW)->state, 'the second it is due');
        self::assertSame(PublicationState::Unpublished, $this->statusOf(false, '2026-10-01 00:00:00', '2026-10-20 00:00:00')->state, 'a window entirely past');
        self::assertSame(PublicationState::Scheduled, $this->statusOf(false, '2026-10-01 00:00:00', '2026-10-21 12:00:01')->state, 'withdrawal one second ahead');
    }

    public function testAPublishedRowWithAWithdrawalDateWaitsForIt(): void
    {
        $status = $this->statusOf(true, '2026-10-01 00:00:00', '2026-12-01 00:00:00');

        self::assertSame('2026-12-01 00:00:00', $status->pendingDate()?->format('Y-m-d H:i:s'));
        self::assertFalse($status->isOverdue());
        self::assertTrue($this->statusOf(true, '2026-10-01 00:00:00', self::NOW)->isOverdue(), 'the withdrawal has come');
    }

    public function testTheDatesAreReadAsImmutableWhateverTheyWereGiven(): void
    {
        $status = PublicationStatus::of(true, new \DateTime('2026-10-01 00:00:00'), null, new \DateTime(self::NOW));

        self::assertInstanceOf(\DateTimeImmutable::class, $status->publishAt);
        self::assertNull($status->unpublishAt);
    }

    private function statusOf(bool $visible, ?string $publishAt, ?string $unpublishAt): PublicationStatus
    {
        return PublicationStatus::of(
            $visible,
            null === $publishAt ? null : new \DateTimeImmutable($publishAt),
            null === $unpublishAt ? null : new \DateTimeImmutable($unpublishAt),
            new \DateTimeImmutable(self::NOW),
        );
    }
}
