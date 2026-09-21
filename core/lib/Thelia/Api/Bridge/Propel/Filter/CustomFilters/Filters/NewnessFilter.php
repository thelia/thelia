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
 * The products a shop marked as new arrivals.
 *
 * The flag is set per sale element and nothing ever clears it on its own: it says what the
 * shop declared, not how recently the product was created.
 */
final readonly class NewnessFilter extends AbstractSaleElementFlagFilter
{
    public static function getFilterName(): array
    {
        return ['new'];
    }

    protected static function flagColumn(): string
    {
        return 'Newness';
    }

    protected function valueTitle(string $locale): string
    {
        return $this->translator->trans(id: 'New', locale: $locale);
    }
}
