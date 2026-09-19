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
 * The sale elements carrying one of the named attribute values - the one criterion
 * that tells the combinations of a product apart, the way a flash sale narrowed
 * down to an attribute value does.
 */
final class AttributeValueCriterionResolver implements ScopeCriterionResolverInterface
{
    public function type(): string
    {
        return CatalogPriceRule::CRITERION_ATTRIBUTE_AV;
    }

    public function predicate(array $targetIds, CatalogPriceRule $rule): string
    {
        return \sprintf(
            'EXISTS (SELECT 1 FROM attribute_combination ac WHERE ac.product_sale_elements_id = pse.id AND ac.attribute_av_id IN %s)',
            SqlIdList::inList($targetIds),
        );
    }
}
