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

use Thelia\Model\CatalogPriceRule;

final class BrandCriterionResolver implements ScopeCriterionResolverInterface
{
    public function type(): string
    {
        return CatalogPriceRule::CRITERION_BRAND;
    }

    public function predicate(array $targetIds, CatalogPriceRule $rule): string
    {
        return \sprintf('p.brand_id IN %s', SqlIdList::inList($targetIds));
    }
}
