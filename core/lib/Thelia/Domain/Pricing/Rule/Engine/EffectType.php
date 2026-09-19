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

namespace Thelia\Domain\Pricing\Rule\Engine;

use Thelia\Model\CatalogPriceRule;

/**
 * What a rule does to the price. The values are the ones stored in
 * `catalog_price_rule.effect_type`.
 */
enum EffectType: int
{
    case Percentage = CatalogPriceRule::EFFECT_TYPE_PERCENTAGE;
    case Amount = CatalogPriceRule::EFFECT_TYPE_AMOUNT;
    case FixedPrice = CatalogPriceRule::EFFECT_TYPE_FIXED_PRICE;

    /**
     * Whether the effect value is typed once, as a percentage, or once per currency.
     */
    public function isPerCurrency(): bool
    {
        return self::Percentage !== $this;
    }
}
