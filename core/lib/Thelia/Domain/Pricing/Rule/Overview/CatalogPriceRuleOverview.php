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

namespace Thelia\Domain\Pricing\Rule\Overview;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Model\CatalogPriceRule;

/**
 * One line of the back-office list: the rule, where it stands, and what it covers.
 */
#[Exclude]
final readonly class CatalogPriceRuleOverview
{
    /**
     * @param list<string> $unknownCriterionTypes
     */
    public function __construct(
        public CatalogPriceRule $rule,
        public string $state,
        public int $productCount,
        public int $saleElementCount,
        public int $customerCount,
        public array $unknownCriterionTypes = [],
    ) {
    }
}
