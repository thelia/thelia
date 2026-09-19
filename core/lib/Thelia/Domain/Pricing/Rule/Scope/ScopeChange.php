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

namespace Thelia\Domain\Pricing\Rule\Scope;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What a materialization did to the scope of a rule: the sale elements it covered
 * before, and the ones it covers now. Their union is what has to be repriced.
 */
#[Exclude]
final readonly class ScopeChange
{
    /**
     * @param list<int>    $before
     * @param list<int>    $after
     * @param list<string> $unknownCriterionTypes criterion types no resolver answers for; the rule then covers nothing
     */
    public function __construct(
        public int $ruleId,
        public array $before,
        public array $after,
        public array $unknownCriterionTypes = [],
    ) {
    }

    /**
     * @return list<int>
     */
    public function touchedProductSaleElementsIds(): array
    {
        return array_values(array_unique([...$this->before, ...$this->after]));
    }

    public function isUnchanged(): bool
    {
        $before = $this->before;
        $after = $this->after;
        sort($before);
        sort($after);

        return $before === $after;
    }
}
