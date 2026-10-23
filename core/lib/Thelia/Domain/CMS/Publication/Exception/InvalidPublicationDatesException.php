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

namespace Thelia\Domain\CMS\Publication\Exception;

/**
 * The dates of a gesture do not hold: in the wrong order, in the past where a future
 * date is expected, missing, or unreadable.
 */
final class InvalidPublicationDatesException extends PublicationException
{
    public static function withdrawalBeforePublication(): self
    {
        return new self(self::translated('The withdrawal date must come after the publication date.'));
    }

    public static function publicationDateMissing(): self
    {
        return new self(self::translated('A scheduled publication needs a publication date.'));
    }

    public static function publicationDateInThePast(): self
    {
        return new self(self::translated('The publication date must be in the future.'));
    }

    public static function withdrawalDateInThePast(): self
    {
        return new self(self::translated('The withdrawal date must be in the future.'));
    }

    public static function unknownAction(string $action): self
    {
        return new self(self::translated('Unknown publication action "%action".', ['%action' => $action]));
    }

    public static function notADate(string $date): self
    {
        return new self(self::translated('"%date" is not a date.', ['%date' => $date]));
    }
}
