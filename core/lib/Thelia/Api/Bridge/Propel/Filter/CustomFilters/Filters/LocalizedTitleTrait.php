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

use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;

trait LocalizedTitleTrait
{
    /**
     * FilterValue::setTitle() takes a string, so an entity left untranslated in the
     * requested locale would otherwise fail the whole filter list rather than that
     * single facet.
     *
     * Falling back to the default language mirrors ResourceService::formatI18ns():
     * the back office "If a translation is missing or incomplete" setting decides.
     * Filters are exposed on the front only (GET /front/tfilters/{resource}), so the
     * admin exclusion that applies there has no equivalent here.
     */
    protected function localizedTitle(ActiveRecordInterface $record, string $locale): string
    {
        $title = $record->setLocale($locale)->getTitle();

        // Explicit emptiness test rather than ?: — "0" is a legitimate attribute
        // value title (a size, for instance) and must not count as missing.
        if (null !== $title && '' !== $title) {
            return $title;
        }

        $fallbackLocale = $this->fallbackLocale($locale);

        if (null !== $fallbackLocale) {
            $title = $record->setLocale($fallbackLocale)->getTitle();
        }

        return $title ?? '';
    }

    private function fallbackLocale(string $currentLocale): ?string
    {
        if (Lang::REPLACE_BY_DEFAULT_LANGUAGE !== (int) ConfigQuery::getDefaultLangWhenNoTranslationAvailable()) {
            return null;
        }

        $defaultLocale = Lang::getDefaultLanguage()->getLocale();

        return $defaultLocale === $currentLocale ? null : $defaultLocale;
    }
}
