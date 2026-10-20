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

namespace Thelia\Domain\Shipping\DestinationRestriction;

use Thelia\Model\ConfigQuery;
use Thelia\Model\CountryI18nQuery;
use Thelia\Model\DestinationRestrictionI18nQuery;
use Thelia\Model\Lang;
use Thelia\Model\ProductI18nQuery;
use Thelia\Model\StateI18nQuery;

/**
 * A wording read in the buyer's language, or in the default language of the shop when
 * the buyer's has none and the shop is set to fall back on it ("If a translation is
 * missing or incomplete"). A shop set to the requested language only gets nothing.
 *
 * Read from the translation table, never through setLocale() on a model: a shared
 * instance would stay in the language read for whoever reads it next.
 */
final class FallbackTranslation
{
    /**
     * @return list<string> the requested locale, then the default one when the shop falls back on it
     */
    public static function localesFor(string $locale): array
    {
        if (Lang::REPLACE_BY_DEFAULT_LANGUAGE !== ConfigQuery::getDefaultLangWhenNoTranslationAvailable()) {
            return [$locale];
        }

        $defaultLocale = Lang::getDefaultLanguage()->getLocale();

        return $defaultLocale === $locale ? [$locale] : [$locale, $defaultLocale];
    }

    /**
     * One value per id: the one of the requested locale when it is not empty, else the
     * one of the default locale when the shop falls back on it. An id with neither is
     * left out.
     *
     * @param list<int> $ids
     *
     * @return array<int, string> id => value
     */
    public static function valuesOf(
        CountryI18nQuery|DestinationRestrictionI18nQuery|ProductI18nQuery|StateI18nQuery $query,
        string $idColumn,
        string $localeColumn,
        string $valueColumn,
        array $ids,
        string $locale,
    ): array {
        if ([] === $ids) {
            return [];
        }

        $locales = self::localesFor($locale);
        $valuesByLocale = [];

        foreach ($query->filterById($ids)->filterByLocale($locales)->select([$idColumn, $localeColumn, $valueColumn])->find() as $row) {
            $value = trim((string) $row[$valueColumn]);

            if ('' !== $value) {
                $valuesByLocale[(string) $row[$localeColumn]][(int) $row[$idColumn]] = $value;
            }
        }

        $values = [];

        // The default locale first, so that the requested one overwrites it.
        foreach (array_reverse($locales) as $readLocale) {
            foreach ($valuesByLocale[$readLocale] ?? [] as $id => $value) {
                $values[$id] = $value;
            }
        }

        return $values;
    }
}
