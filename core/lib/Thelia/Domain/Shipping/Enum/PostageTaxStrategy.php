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

namespace Thelia\Domain\Shipping\Enum;

use Thelia\Model\ConfigQuery;

/**
 * How a shop splits the tax on a postage between the tax rules its goods follow.
 *
 * SINGLE_RULE is the default and is what Thelia has always done: the delivery
 * module applies one rule to the whole postage. The other two move the VAT a
 * shop declares, so they are opt-in - an upgrade must never change that figure
 * on its own.
 */
enum PostageTaxStrategy: string
{
    /** One rule for the whole postage, the one the delivery module resolved. */
    case SINGLE_RULE = 'single_rule';

    /** The postage follows the goods, split over the untaxed value of each rate. */
    case PRO_RATA = 'pro_rata';

    /** The whole postage follows the highest rate the cart carries. */
    case HIGHEST_RATE = 'highest_rate';

    public const CONFIG_KEY = 'postage_tax_strategy';

    /**
     * The strategy the shop configured, SINGLE_RULE when the variable is unset
     * or holds a value this version does not know.
     */
    public static function fromShopConfiguration(): self
    {
        return self::tryFrom((string) ConfigQuery::read(self::CONFIG_KEY, self::SINGLE_RULE->value))
            ?? self::SINGLE_RULE;
    }
}
