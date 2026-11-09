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

namespace Thelia\Model;

use Thelia\Domain\Reporting\Mailing\ReportFrequency;
use Thelia\Domain\Reporting\Mailing\ReportPeriod;
use Thelia\Domain\Reporting\Mailing\ReportSchedule;
use Thelia\Model\Base\ReportMailing as BaseReportMailing;

/**
 * A report sent by e-mail on a schedule: an export, the period it covers and the list of
 * recipients it goes to.
 */
class ReportMailing extends BaseReportMailing
{
    /**
     * @return list<string>
     */
    public function getRecipientList(): array
    {
        return self::splitAddresses((string) $this->getRecipients());
    }

    /**
     * The addresses that are still recipients of the mailing, compared without case: an
     * address the merchant removed is no longer sent anything, a retry included.
     *
     * @param list<string> $addresses
     *
     * @return list<string>
     */
    public function keepCurrentRecipients(array $addresses): array
    {
        return array_values(array_uintersect($addresses, $this->getRecipientList(), strcasecmp(...)));
    }

    /**
     * @return list<string>
     */
    public function getPendingRecipientList(): array
    {
        return self::splitAddresses((string) $this->getPendingRecipients());
    }

    /**
     * @param list<string> $addresses
     */
    public function setPendingRecipientList(array $addresses): static
    {
        return $this->setPendingRecipients([] === $addresses ? null : implode(',', $addresses));
    }

    public function getSchedule(): ReportSchedule
    {
        $lastPeriodEnd = $this->getLastPeriodEnd('Y-m-d');

        return new ReportSchedule(
            ReportFrequency::from((string) $this->getFrequency()),
            ReportPeriod::from((string) $this->getPeriod()),
            (int) $this->getSendHour(),
            $this->getDayOfWeek(),
            $this->getDayOfMonth(),
            null === $lastPeriodEnd ? null : new \DateTimeImmutable($lastPeriodEnd),
            $this->getPendingRecipientList(),
            (int) $this->getPendingAttempts(),
        );
    }

    /**
     * @return list<string>
     */
    private static function splitAddresses(string $addresses): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $addresses)), static fn (string $address): bool => '' !== $address));
    }
}
