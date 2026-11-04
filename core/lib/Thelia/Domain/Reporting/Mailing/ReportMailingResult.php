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
 * What a send of a report came to: who got it, who did not, and what the e-mail carried.
 */
#[Exclude]
final readonly class ReportMailingResult
{
    /**
     * @param list<string> $delivered
     * @param list<string> $failed    the recipients the transport refused or could not reach: tried again
     * @param list<string> $rejected  the addresses that are not valid e-mail addresses: never tried again
     */
    public function __construct(
        public array $delivered,
        public array $failed,
        public array $rejected,
        public bool $empty,
        public ReportDeliveryMode $delivery,
    ) {
    }
}
