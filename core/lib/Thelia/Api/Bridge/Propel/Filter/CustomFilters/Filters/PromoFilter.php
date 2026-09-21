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

namespace Thelia\Api\Bridge\Propel\Filter\CustomFilters\Filters;

/**
 * The products a shop discounted.
 *
 * The flag read here is the one the catalog stores, which is what every visitor sees as a
 * struck-through price. An operation reserved to an audience deliberately writes neither
 * the flag nor the discounted price — its price is served to its own customer at read time
 * — so the facet does not describe it, and the count it shows stays the same for everyone.
 */
final readonly class PromoFilter extends AbstractSaleElementFlagFilter
{
    public static function getFilterName(): array
    {
        return ['promo'];
    }

    protected static function flagColumn(): string
    {
        return 'Promo';
    }

    protected function valueTitle(string $locale): string
    {
        return $this->translator->trans(id: 'On sale', locale: $locale);
    }
}
