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

use Thelia\Domain\Customer\Service\CustomerGroupResolver;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleCustomerGroupQuery;
use Thelia\Model\Customer;

/**
 * The rules reserved for the customer group a customer belongs to.
 *
 * A guest account never reaches here: {@see CustomerAudience} entitles it to nothing,
 * so a guest row an administrator put in a group is no member of it for the rules.
 * Every other account is in the group {@see CustomerGroupResolver} reads back from the
 * database: an account moved to another group is entitled through the new one at once.
 */
final readonly class CustomerGroupsAudienceResolver implements AudienceResolverInterface
{
    public function __construct(private CustomerGroupResolver $customerGroupResolver)
    {
    }

    public function mode(): int
    {
        return CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS;
    }

    public function entitledRuleIds(Customer $customer): array
    {
        /** @var list<int|string> $ids */
        $ids = CatalogPriceRuleCustomerGroupQuery::create()
            ->filterByCustomerGroupId($this->customerGroupResolver->groupIdOf($customer))
            ->useCatalogPriceRuleQuery()
                ->filterByActive(true)
                ->filterByAudienceMode(CatalogPriceRule::AUDIENCE_MODE_CUSTOMER_GROUPS)
            ->endUse()
            ->select('CatalogPriceRuleId')
            ->find()
            ->getData();

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
