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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The publication state of a row, read from its three columns at a given moment.
 *
 * `visible` stays the public truth: a published row is a visible one, whatever its
 * dates say, so every list that filters on `visible = 1` stays right. The dates only
 * tell a hidden row apart: taken offline (`unpublish_at` reached), waiting for its date
 * (`publish_at` set), or a draft (no date).
 */
#[Exclude]
final readonly class PublicationStatus
{
    private function __construct(
        public PublicationState $state,
        public ?\DateTimeImmutable $publishAt,
        public ?\DateTimeImmutable $unpublishAt,
        private bool $overdue,
    ) {
    }

    public static function of(bool $visible, ?\DateTimeInterface $publishAt, ?\DateTimeInterface $unpublishAt, \DateTimeInterface $now): self
    {
        $publishAt = PublicationMoment::atTheSecondOrNull($publishAt);
        $unpublishAt = PublicationMoment::atTheSecondOrNull($unpublishAt);
        $now = PublicationMoment::atTheSecond($now);

        if ($visible) {
            return new self(PublicationState::Published, $publishAt, $unpublishAt, null !== $unpublishAt && $unpublishAt <= $now);
        }

        if (null !== $unpublishAt && $unpublishAt <= $now) {
            return new self(PublicationState::Unpublished, $publishAt, $unpublishAt, false);
        }

        if (null !== $publishAt) {
            return new self(PublicationState::Scheduled, $publishAt, $unpublishAt, $publishAt <= $now);
        }

        return new self(PublicationState::Draft, null, $unpublishAt, false);
    }

    public static function ofPublishable(PublishableInterface $publishable, \DateTimeInterface $now): self
    {
        return self::of(
            1 === (int) $publishable->getVisible(),
            self::dateOf($publishable->getPublishAt()),
            self::dateOf($publishable->getUnpublishAt()),
            $now,
        );
    }

    public function isDraft(): bool
    {
        return PublicationState::Draft === $this->state;
    }

    public function isScheduled(): bool
    {
        return PublicationState::Scheduled === $this->state;
    }

    public function isPublished(): bool
    {
        return PublicationState::Published === $this->state;
    }

    public function isUnpublished(): bool
    {
        return PublicationState::Unpublished === $this->state;
    }

    /**
     * A date has come and nothing acted on it: a scheduled row whose publication date is
     * past, or a published row whose withdrawal date is past. The scheduled publication
     * command is what acts on them; a shop that does not run it sees them pile up here.
     */
    public function isOverdue(): bool
    {
        return $this->overdue;
    }

    /**
     * The date the state waits for, if any: the publication of a scheduled row, the
     * withdrawal of a published row.
     */
    public function pendingDate(): ?\DateTimeImmutable
    {
        return match ($this->state) {
            PublicationState::Scheduled => $this->publishAt,
            PublicationState::Published => $this->unpublishAt,
            default => null,
        };
    }

    private static function dateOf(string|\DateTimeInterface|null $date): ?\DateTimeInterface
    {
        return PublicationMoment::atTheSecondOrNull($date);
    }
}
