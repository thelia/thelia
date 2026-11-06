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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A report to send now: the period it covers, and, when only a retry is left, the
 * recipients it goes to (null: every recipient), and how many tries this one makes of the
 * period (the first send is the first try). On the last try, the recipients still failing
 * are given up for the period.
 */
#[Exclude]
final readonly class DueReport
{
    /**
     * @param list<string>|null $onlyRecipients
     */
    public function __construct(
        public \DateTimeImmutable $occurrence,
        public \DateTimeImmutable $periodStart,
        public \DateTimeImmutable $periodEnd,
        public ?array $onlyRecipients,
        public int $attempt = 1,
        public bool $lastAttempt = false,
    ) {
    }
}
