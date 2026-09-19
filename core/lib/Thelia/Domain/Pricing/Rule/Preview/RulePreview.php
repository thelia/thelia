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

namespace Thelia\Domain\Pricing\Rule\Preview;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What a rule under review would do: how much of the catalog it covers, and the
 * price before and after on a sample of it.
 */
#[Exclude]
final readonly class RulePreview
{
    /**
     * @param list<PreviewLine> $lines
     * @param list<string>      $unknownCriterionTypes
     */
    public function __construct(
        public int $affectedProductCount,
        public int $affectedSaleElementCount,
        public array $lines,
        public array $unknownCriterionTypes = [],
    ) {
    }
}
