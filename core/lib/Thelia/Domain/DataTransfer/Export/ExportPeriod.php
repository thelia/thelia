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

namespace Thelia\Domain\DataTransfer\Export;

use Thelia\Domain\DataTransfer\Exception\JobRefusedException;

/**
 * The period an export covers, read from what the back office or a caller gave.
 */
final class ExportPeriod
{
    /**
     * The period of an export, as the export reads it: a start and an end given as a
     * year and a month (the form of the back office) become the first second of that
     * month and the last second of the end month. Dates are kept as they are.
     *
     * @param array{start?: mixed, end?: mixed}|null $rangeDate
     *
     * @return array{start: ?\DateTimeInterface, end: ?\DateTimeInterface}|null
     */
    public static function resolve(?array $rangeDate): ?array
    {
        if (null === $rangeDate) {
            return null;
        }

        return [
            'start' => self::boundOf($rangeDate['start'] ?? null, false),
            'end' => self::boundOf($rangeDate['end'] ?? null, true),
        ];
    }

    /**
     * A bound given as a date stays as it is; one given as the year and month of the
     * back-office form becomes the first, or the last, moment of that month.
     */
    private static function boundOf(mixed $bound, bool $endOfMonth): ?\DateTimeInterface
    {
        if (!$bound) {
            return null;
        }

        if ($bound instanceof \DateTimeInterface) {
            return $bound;
        }

        $year = \is_array($bound) ? (string) ($bound['year'] ?? '') : '';
        $month = \is_array($bound) ? (string) ($bound['month'] ?? '') : '';

        // A year of four digits and a month of the year: anything else would not parse,
        // or would roll over into another date, and the export would quietly cover
        // another period.
        $valid = \is_array($bound)
            && ('' === $year || (ctype_digit($year) && 4 === \strlen($year)))
            && ('' === $month || (ctype_digit($month) && (int) $month >= 1 && (int) $month <= 12));

        $date = $valid ? \DateTime::createFromFormat(
            'Y-m-d H:i:s',
            ('' !== $year ? $year : (new \DateTime())->format('Y')).'-'.('' !== $month ? $month : (new \DateTime())->format('m')).($endOfMonth ? '-1 23:59:59' : '-1 00:00:00'),
        ) : false;

        if (false === $date) {
            // Translated where it is shown, as the reason of any job refused.
            throw new JobRefusedException('The dates of the export are not valid.');
        }

        if ($endOfMonth) {
            $date->add(new \DateInterval('P1M'))->sub(new \DateInterval('P1D'));
        }

        return $date;
    }
}
