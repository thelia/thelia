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

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Thelia\Model\Customer;

/**
 * Answers, for one audience mode, which turned-on rules a customer is named on.
 *
 * The core ships the named customers mode; customer groups (US #122) will ship
 * theirs by implementing this, and the resolver will take the union without being
 * touched.
 */
#[AutoconfigureTag(self::TAG)]
interface AudienceResolverInterface
{
    public const TAG = 'thelia.catalog_price_rule.audience';

    /**
     * The value of `catalog_price_rule.audience_mode` this resolver answers for.
     */
    public function mode(): int;

    /**
     * @return list<int> the ids of the turned-on rules in this mode the customer is entitled to
     */
    public function entitledRuleIds(Customer $customer): array;
}
