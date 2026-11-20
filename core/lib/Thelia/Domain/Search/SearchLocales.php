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

namespace Thelia\Domain\Search;

use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;

final readonly class SearchLocales
{
    public const string EXCLUDED_LOCALES_CONFIG = 'search_exclude_locales';

    /**
     * @return list<string>
     */
    public function indexed(): array
    {
        $excluded = array_filter(array_map(trim(...), explode(',', (string) ConfigQuery::read(self::EXCLUDED_LOCALES_CONFIG, ''))));

        $locales = [];

        foreach (LangQuery::create()->filterByActive(true)->orderById()->find() as $lang) {
            $locale = (string) $lang->getLocale();

            if ('' !== $locale && !\in_array($locale, $excluded, true)) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }
}
