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

/**
 * The products carrying one of the named feature values. Free-text feature values
 * have no `feature_av` row and cannot be named, so they are out of reach here.
 */
final class FeatureValueCriterionResolver implements ScopeCriterionResolverInterface
{
    public function type(): string
    {
        return CatalogPriceRule::CRITERION_FEATURE_AV;
    }

    public function predicate(array $targetIds, CatalogPriceRule $rule): string
    {
        return \sprintf(
            'EXISTS (SELECT 1 FROM feature_product fp WHERE fp.product_id = p.id AND fp.feature_av_id IN %s)',
            SqlIdList::inList($targetIds),
        );
    }
}
