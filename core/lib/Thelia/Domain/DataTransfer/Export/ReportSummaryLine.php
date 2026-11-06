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

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * One figure of the summary an e-mailed report opens with: the label already translated
 * to the language of the e-mail and the value already formatted.
 */
#[Exclude]
final readonly class ReportSummaryLine
{
    public function __construct(
        public string $label,
        public string $value,
    ) {
    }
}
