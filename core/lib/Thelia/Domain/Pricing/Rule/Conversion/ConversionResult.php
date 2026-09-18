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

namespace Thelia\Domain\Pricing\Rule\Conversion;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Thelia\Model\CatalogPriceRule;

/**
 * What converting a flash sale produced: the rules, turned off, and what the
 * merchant should know before turning them on.
 */
#[Exclude]
final readonly class ConversionResult
{
    /**
     * @param list<CatalogPriceRule> $rules
     * @param list<string>           $warnings
     */
    public function __construct(
        public array $rules,
        public array $warnings = [],
    ) {
    }
}
