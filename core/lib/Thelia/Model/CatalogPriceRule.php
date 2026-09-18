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

namespace Thelia\Model;

use Thelia\Model\Base\CatalogPriceRule as BaseCatalogPriceRule;

class CatalogPriceRule extends BaseCatalogPriceRule
{
    /** What the rule does to the price: a percentage off, an amount off, or a fixed price. */
    public const EFFECT_TYPE_PERCENTAGE = 1;

    public const EFFECT_TYPE_AMOUNT = 2;

    public const EFFECT_TYPE_FIXED_PRICE = 3;

    /** Who the rule prices for; the values are those of Sale::AUDIENCE_MODE_*. */
    public const AUDIENCE_MODE_PUBLIC = 0;

    public const AUDIENCE_MODE_CUSTOMERS = 1;

    /** Customer groups are US #122: the mode exists, nothing reads the groups yet. */
    public const AUDIENCE_MODE_CUSTOMER_GROUPS = 2;

    /** The criterion types the core knows; a module may register more through ScopeCriterionResolverInterface. */
    public const CRITERION_CATEGORY = 'category';

    public const CRITERION_BRAND = 'brand';

    public const CRITERION_TEMPLATE = 'template';

    public const CRITERION_FEATURE_AV = 'feature_av';

    public const CRITERION_ATTRIBUTE_AV = 'attribute_av';

    public const CRITERION_PRODUCT = 'product';

    /** The states the back office shows, derived from the flag and the dates. */
    public const STATE_INACTIVE = 'inactive';

    public const STATE_SCHEDULED = 'scheduled';

    public const STATE_RUNNING = 'running';

    public const STATE_EXPIRED = 'expired';

    /**
     * Whether the rule prices for a named audience rather than for everyone.
     *
     * A public rule has its prices stored ahead of time for every visitor; a
     * reserved one is resolved at read time for the customers it names.
     */
    public function isReserved(): bool
    {
        return self::AUDIENCE_MODE_PUBLIC !== $this->getAudienceMode();
    }

    /**
     * Whether the rule prices anything at the given instant: turned on, and inside
     * its date window. A missing bound is an open one.
     */
    public function isRunningAt(\DateTimeInterface $now): bool
    {
        if (!$this->getActive()) {
            return false;
        }

        $startDate = $this->getStartDate();

        if (null !== $startDate && $startDate > $now) {
            return false;
        }

        $endDate = $this->getEndDate();

        return null === $endDate || $endDate > $now;
    }

    /**
     * One of the STATE_* constants, the way the list screen tells the merchant where
     * the rule stands without making them read three columns.
     */
    public function getStateAt(\DateTimeInterface $now): string
    {
        if (!$this->getActive()) {
            return self::STATE_INACTIVE;
        }

        $startDate = $this->getStartDate();

        if (null !== $startDate && $startDate > $now) {
            return self::STATE_SCHEDULED;
        }

        $endDate = $this->getEndDate();

        if (null !== $endDate && $endDate <= $now) {
            return self::STATE_EXPIRED;
        }

        return self::STATE_RUNNING;
    }

    /**
     * The effect values per currency, for the amount and fixed price effects.
     *
     * @return array<int, float> currency id => value, tax included
     */
    public function getEffectValuesByCurrency(): array
    {
        $values = [];

        foreach (CatalogPriceRuleEffectCurrencyQuery::create()->filterByCatalogPriceRuleId($this->getId())->find() as $effectCurrency) {
            $values[(int) $effectCurrency->getCurrencyId()] = (float) $effectCurrency->getValue();
        }

        return $values;
    }

    /**
     * The criteria of the rule grouped by type.
     *
     * @return array<string, list<int>> type => target ids
     */
    public function getCriteriaByType(): array
    {
        $criteria = [];

        foreach (CatalogPriceRuleCriterionQuery::create()->filterByCatalogPriceRuleId($this->getId())->orderById()->find() as $criterion) {
            $criteria[$criterion->getType()][] = (int) $criterion->getTargetId();
        }

        return $criteria;
    }
}
