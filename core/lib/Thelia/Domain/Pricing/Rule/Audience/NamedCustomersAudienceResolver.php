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

namespace Thelia\Domain\Pricing\Rule\Audience;

use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleCustomerQuery;
use Thelia\Model\Customer;

final class NamedCustomersAudienceResolver implements AudienceResolverInterface
{
    public function mode(): int
    {
        return CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS;
    }

    public function entitledRuleIds(Customer $customer): array
    {
        /** @var list<int|string> $ids */
        $ids = CatalogPriceRuleCustomerQuery::create()
            ->filterByCustomerId($customer->getId())
            ->useCatalogPriceRuleQuery()
                ->filterByActive(true)
                ->filterByAudienceMode(CatalogPriceRule::AUDIENCE_MODE_CUSTOMERS)
            ->endUse()
            ->select('CatalogPriceRuleId')
            ->find()
            ->getData();

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
