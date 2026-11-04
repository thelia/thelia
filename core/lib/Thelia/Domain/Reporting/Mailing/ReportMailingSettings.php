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

namespace Thelia\Domain\Reporting\Mailing;

use Thelia\Model\ConfigQuery;

/**
 * The two settings of the e-mailed reports, read from the configuration of the shop and
 * never seeded: a shop that has set nothing gets the defaults. A value of 0 is a value:
 * ConfigQuery::read() hands back '0' as present, and 0 MB means every report is sent as a
 * link.
 */
final class ReportMailingSettings
{
    public const string ATTACHMENT_MAX_MB = 'report_mail_attachment_max_mb';
    public const string LINK_LIFETIME_DAYS = 'report_mail_link_lifetime_days';

    public const int DEFAULT_ATTACHMENT_MAX_MB = 5;
    public const int DEFAULT_LINK_LIFETIME_DAYS = 3;

    public function attachmentMaxBytes(): int
    {
        return max(0, (int) ConfigQuery::read(self::ATTACHMENT_MAX_MB, self::DEFAULT_ATTACHMENT_MAX_MB)) * 1024 * 1024;
    }

    public function linkLifetimeDays(): int
    {
        return max(1, (int) ConfigQuery::read(self::LINK_LIFETIME_DAYS, self::DEFAULT_LINK_LIFETIME_DAYS));
    }

    /**
     * Attached when the file fits in the limit, linked otherwise (always, with a limit of 0).
     */
    public function deliveryFor(int $fileSizeInBytes): ReportDeliveryMode
    {
        return self::chooseDelivery($fileSizeInBytes, $this->attachmentMaxBytes());
    }

    public static function chooseDelivery(int $fileSizeInBytes, int $limitInBytes): ReportDeliveryMode
    {
        return $limitInBytes > 0 && $fileSizeInBytes <= $limitInBytes ? ReportDeliveryMode::Attached : ReportDeliveryMode::Linked;
    }
}
