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
use Thelia\Domain\CMS\Publication\Exception\InvalidPublicationDatesException;

/**
 * One gesture on the publication of a row, with its dates. Built by name, so a caller
 * cannot pair a gesture with a date it does not take; the order of the dates is checked
 * here, whether they are in the past is checked by the service against its clock.
 */
#[Exclude]
final readonly class PublicationRequest
{
    private function __construct(
        public PublicationAction $action,
        public ?\DateTimeImmutable $publishAt,
        public ?\DateTimeImmutable $unpublishAt,
    ) {
    }

    /**
     * Online now. `$publishAt` is the date written as the publication date: the service
     * takes now when it is null, the scheduled command passes the date that was waited for.
     */
    public static function publish(?\DateTimeInterface $unpublishAt = null, ?\DateTimeInterface $publishAt = null): self
    {
        return self::ordered(PublicationAction::Publish, $publishAt, $unpublishAt);
    }

    public static function schedule(\DateTimeInterface $publishAt, ?\DateTimeInterface $unpublishAt = null): self
    {
        return self::ordered(PublicationAction::Schedule, $publishAt, $unpublishAt);
    }

    public static function cancel(): self
    {
        return new self(PublicationAction::Cancel, null, null);
    }

    /**
     * Offline now. `$unpublishAt` is the date written as the withdrawal date: now when it
     * is null, the date that was waited for when the scheduled command passes it.
     */
    public static function unpublish(?\DateTimeInterface $unpublishAt = null): self
    {
        return new self(PublicationAction::Unpublish, null, self::immutable($unpublishAt));
    }

    /** A null date clears the withdrawal date of a published row. */
    public static function scheduleUnpublication(?\DateTimeInterface $unpublishAt): self
    {
        return new self(PublicationAction::ScheduleUnpublication, null, self::immutable($unpublishAt));
    }

    /**
     * From the strings a form or a JSON body carries. An unknown action or an unreadable
     * date is refused as a dates error, so a controller has one exception to catch.
     *
     * @throws InvalidPublicationDatesException
     */
    public static function fromStrings(string $action, ?string $publishAt = null, ?string $unpublishAt = null): self
    {
        $publicationAction = PublicationAction::tryFrom($action)
            ?? throw InvalidPublicationDatesException::unknownAction($action);

        $publishDate = self::parse($publishAt);
        $unpublishDate = self::parse($unpublishAt);

        return match ($publicationAction) {
            PublicationAction::Publish => self::publish($unpublishDate),
            PublicationAction::Schedule => self::schedule(
                $publishDate ?? throw InvalidPublicationDatesException::publicationDateMissing(),
                $unpublishDate,
            ),
            PublicationAction::Cancel => self::cancel(),
            PublicationAction::Unpublish => self::unpublish(),
            PublicationAction::ScheduleUnpublication => self::scheduleUnpublication($unpublishDate),
        };
    }

    private static function ordered(PublicationAction $action, ?\DateTimeInterface $publishAt, ?\DateTimeInterface $unpublishAt): self
    {
        $publishAt = self::immutable($publishAt);
        $unpublishAt = self::immutable($unpublishAt);

        if (null !== $publishAt && null !== $unpublishAt && $unpublishAt <= $publishAt) {
            throw InvalidPublicationDatesException::withdrawalBeforePublication();
        }

        return new self($action, $publishAt, $unpublishAt);
    }

    private static function immutable(?\DateTimeInterface $date): ?\DateTimeImmutable
    {
        return PublicationMoment::atTheSecondOrNull($date);
    }

    private static function parse(?string $date): ?\DateTimeImmutable
    {
        if (null === $date || '' === trim($date)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($date);
        } catch (\Exception) {
            throw InvalidPublicationDatesException::notADate($date);
        }
    }
}
