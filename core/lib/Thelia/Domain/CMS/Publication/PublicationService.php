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

namespace Thelia\Domain\CMS\Publication;

use Psr\Clock\ClockInterface;
use Thelia\Domain\CMS\Publication\Exception\EmptyContentPublicationException;
use Thelia\Domain\CMS\Publication\Exception\InvalidPublicationDatesException;
use Thelia\Domain\CMS\Publication\Exception\InvalidPublicationTransitionException;

/**
 * The one place a content or a folder changes publication state.
 *
 * Every transition writes the three columns together and saves the row, so the state
 * read back by `statusOf()` is the one the gesture meant. The body guard is not here: it
 * sits in `Content::preSave()` and fires on the save this service makes, like on any
 * other save that puts a content online.
 */
final readonly class PublicationService
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function statusOf(PublishableInterface $publishable): PublicationStatus
    {
        return PublicationStatus::ofPublishable($publishable, $this->clock->now());
    }

    /**
     * @throws InvalidPublicationDatesException      a date in the past where a future one is expected
     * @throws InvalidPublicationTransitionException a gesture the current state does not take
     * @throws EmptyContentPublicationException      a content put online with no body in the default language
     */
    public function apply(PublishableInterface $publishable, PublicationRequest $request): PublicationStatus
    {
        $now = $this->clock->now();
        $status = PublicationStatus::ofPublishable($publishable, $now);

        match ($request->action) {
            PublicationAction::Publish => $this->publish($publishable, $request, $now),
            PublicationAction::Schedule => $this->schedule($publishable, $request, $now),
            PublicationAction::Cancel => $this->cancel($publishable, $status),
            PublicationAction::Unpublish => $this->unpublish($publishable, $request, $now),
            PublicationAction::ScheduleUnpublication => $this->scheduleUnpublication($publishable, $request, $status, $now),
        };

        $publishable->save();

        return PublicationStatus::ofPublishable($publishable, $now);
    }

    private function publish(PublishableInterface $publishable, PublicationRequest $request, \DateTimeImmutable $now): void
    {
        $unpublishAt = $request->unpublishAt;

        if (null !== $unpublishAt) {
            $this->assertWithdrawalInTheFuture($unpublishAt, $now);
        } else {
            // A withdrawal date already set and still ahead is kept: publishing earlier than
            // planned does not cancel the planned end.
            $current = self::dateOf($publishable->getUnpublishAt());
            $unpublishAt = null !== $current && $current > $now ? $current : null;
        }

        $publishable
            ->setVisible(1)
            ->setPublishAt($request->publishAt ?? $now)
            ->setUnpublishAt($unpublishAt);
    }

    private function schedule(PublishableInterface $publishable, PublicationRequest $request, \DateTimeImmutable $now): void
    {
        $publishAt = $request->publishAt ?? throw InvalidPublicationDatesException::publicationDateMissing();
        $this->assertPublicationInTheFuture($publishAt, $now);

        $publishable
            ->setVisible(0)
            ->setPublishAt($publishAt)
            ->setUnpublishAt($request->unpublishAt);
    }

    private function cancel(PublishableInterface $publishable, PublicationStatus $status): void
    {
        if ($status->isPublished()) {
            $publishable->setUnpublishAt(null);

            return;
        }

        // A scheduled row goes back to a draft; a draft or an unpublished row has no
        // pending date, and the gesture leaves it as it is.
        if ($status->isScheduled()) {
            $publishable
                ->setPublishAt(null)
                ->setUnpublishAt(null);
        }
    }

    private function unpublish(PublishableInterface $publishable, PublicationRequest $request, \DateTimeImmutable $now): void
    {
        $publishable
            ->setVisible(0)
            ->setUnpublishAt($request->unpublishAt ?? $now);
    }

    private function scheduleUnpublication(PublishableInterface $publishable, PublicationRequest $request, PublicationStatus $status, \DateTimeImmutable $now): void
    {
        if (!$status->isPublished()) {
            throw InvalidPublicationTransitionException::of($request->action, $status->state);
        }

        if (null !== $request->unpublishAt) {
            $this->assertWithdrawalInTheFuture($request->unpublishAt, $now);
        }

        $publishable->setUnpublishAt($request->unpublishAt);
    }

    private function assertPublicationInTheFuture(\DateTimeImmutable $date, \DateTimeImmutable $now): void
    {
        if ($date <= $now) {
            throw InvalidPublicationDatesException::publicationDateInThePast();
        }
    }

    private function assertWithdrawalInTheFuture(\DateTimeImmutable $date, \DateTimeImmutable $now): void
    {
        if ($date <= $now) {
            throw InvalidPublicationDatesException::withdrawalDateInThePast();
        }
    }

    private static function dateOf(string|\DateTimeInterface|null $date): ?\DateTimeImmutable
    {
        if (null === $date) {
            return null;
        }

        return \is_string($date) ? new \DateTimeImmutable($date) : \DateTimeImmutable::createFromInterface($date);
    }
}
