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

namespace Thelia\Domain\Pricing;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Contracts\Service\ResetInterface;
use Thelia\Domain\Sale\SaleAudienceChecker;
use Thelia\Model\CatalogPriceRule;
use Thelia\Model\CatalogPriceRuleQuery;

/**
 * Whether anything at all is pricing the catalog beyond `product_price`, so that a
 * shop without a single rule keeps the query plans and the caches it had before.
 *
 * Three indexed existence checks, each asked of the database once per request.
 */
class PricingActivityChecker implements ResetInterface
{
    private ?bool $hasActivePublicRule = null;

    private ?bool $hasActiveNamedRule = null;

    public function __construct(private readonly SaleAudienceChecker $saleAudienceChecker)
    {
    }

    /**
     * A turned-on rule open to everyone exists. Its dates are not looked at here: the
     * stored segments carry them, and a rule between two windows costs one indexed
     * join, not a wrong price.
     */
    public function hasActivePublicRule(): bool
    {
        return $this->hasActivePublicRule ??= $this->exists(CatalogPriceRule::AUDIENCE_MODE_PUBLIC, Criteria::EQUAL);
    }

    /**
     * A turned-on rule pricing for named customers exists, so the price of a sale
     * element may depend on who is asking.
     */
    public function hasActiveNamedRule(): bool
    {
        return $this->hasActiveNamedRule ??= $this->exists(CatalogPriceRule::AUDIENCE_MODE_PUBLIC, Criteria::NOT_EQUAL);
    }

    /**
     * Two visitors may owe two different prices for the same sale element: a shared
     * cache of prices then has to step aside.
     */
    public function hasVisitorDependentPricing(): bool
    {
        return $this->hasActiveNamedRule() || $this->saleAudienceChecker->hasActiveReservedSale();
    }

    public function reset(): void
    {
        $this->hasActivePublicRule = null;
        $this->hasActiveNamedRule = null;
    }

    private function exists(int $audienceMode, string $comparison): bool
    {
        return CatalogPriceRuleQuery::create()
            ->filterByActive(true)
            ->filterByAudienceMode($audienceMode, $comparison)
            ->exists();
    }
}
