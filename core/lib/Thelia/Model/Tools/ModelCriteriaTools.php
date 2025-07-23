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

namespace Thelia\Model\Tools;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
 * Class ModelCriteriaTools.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ModelCriteriaTools
{
    /**
     * @param string $forceReturn
     */
    public static function getFrontEndI18n(
        ModelCriteria $search,
        $requestedLocale,
        array $columns,
        $foreignTable,
        string $foreignKey,
        bool $forceReturn = false,
        $localeAlias = null,
    ): void {
        if (!empty($columns)) {
            if (null === $foreignTable) {
                $foreignTable = $search->getTableMap()->getName();
                $aliasPrefix = '';
            } else {
                $aliasPrefix = $foreignTable.'_';
            }

            if (null === $localeAlias) {
                $localeAlias = $search->getTableMap()->getName();
            }

            $defaultLangWithoutTranslation = ConfigQuery::getDefaultLangWhenNoTranslationAvailable();

            $requestedLocaleI18nAlias = $aliasPrefix.'requested_locale_i18n';
            $defaultLocaleI18nAlias = $aliasPrefix.'default_locale_i18n';

            if (Lang::STRICTLY_USE_REQUESTED_LANGUAGE === $defaultLangWithoutTranslation) {
                $requestedLocaleJoin = new Join();
                $requestedLocaleJoin->addExplicitCondition(
                    $localeAlias,
                    $foreignKey,
                    null,
                    $foreignTable.'_i18n',
                    'ID',
                    $requestedLocaleI18nAlias,
                );
                $requestedLocaleJoin->setJoinType(false === $forceReturn ? Criteria::INNER_JOIN : Criteria::LEFT_JOIN);

                $search->addJoinObject($requestedLocaleJoin, $requestedLocaleI18nAlias)
                    ->addJoinCondition(
                        $requestedLocaleI18nAlias,
                        '`'.$requestedLocaleI18nAlias.'`.LOCALE = ?',
                        $requestedLocale,
                        null,
                        \PDO::PARAM_STR,
                    );

                $search->withColumn(
                    'NOT ISNULL(`'.$requestedLocaleI18nAlias.'`.`ID`)',
                    $aliasPrefix.'IS_TRANSLATED',
                );

                foreach ($columns as $column) {
                    $search->withColumn(
                        '`'.$requestedLocaleI18nAlias.'`.`'.$column.'`',
                        $aliasPrefix.'i18n_'.$column,
                    );
                }
            } else {
                $defaultLocale = Lang::getDefaultLanguage()->getLocale();

                $defaultLocaleJoin = new Join();
                $defaultLocaleJoin->addExplicitCondition(
                    $localeAlias,
                    $foreignKey,
                    null,
                    $foreignTable.'_i18n',
                    'ID',
                    $defaultLocaleI18nAlias,
                );
                $defaultLocaleJoin->setJoinType(Criteria::LEFT_JOIN);

                $search->addJoinObject($defaultLocaleJoin, $defaultLocaleI18nAlias)
                    ->addJoinCondition(
                        $defaultLocaleI18nAlias,
                        '`'.$defaultLocaleI18nAlias.'`.LOCALE = ?',
                        $defaultLocale,
                        null,
                        \PDO::PARAM_STR,
                    );

                $requestedLocaleJoin = new Join();
                $requestedLocaleJoin->addExplicitCondition(
                    $localeAlias,
                    $foreignKey,
                    null,
                    $foreignTable.'_i18n',
                    'ID',
                    $requestedLocaleI18nAlias,
                );
                $requestedLocaleJoin->setJoinType(Criteria::LEFT_JOIN);

                $search->addJoinObject($requestedLocaleJoin, $requestedLocaleI18nAlias)
                    ->addJoinCondition(
                        $requestedLocaleI18nAlias,
                        '`'.$requestedLocaleI18nAlias.'`.LOCALE = ?',
                        $requestedLocale,
                        null,
                        \PDO::PARAM_STR,
                    );

                $search->withColumn(
                    'NOT ISNULL(`'.$requestedLocaleI18nAlias.'`.`ID`)',
                    $aliasPrefix.'IS_TRANSLATED',
                );

                if (false === $forceReturn) {
                    $search->where('NOT ISNULL(`'.$requestedLocaleI18nAlias.'`.ID)')->_or()->where(
                        'NOT ISNULL(`'.$defaultLocaleI18nAlias.'`.ID)',
                    );
                }

                foreach ($columns as $column) {
                    $search->withColumn(
                        'CASE WHEN NOT ISNULL(`'.$requestedLocaleI18nAlias.'`.`'.$column.'`) THEN `'.$requestedLocaleI18nAlias.'`.`'.$column.'` ELSE `'.$defaultLocaleI18nAlias.'`.`'.$column.'` END',
                        $aliasPrefix.'i18n_'.$column,
                    );
                }
            }
        }
    }

    public static function getBackEndI18n(
        ModelCriteria $search,
        $requestedLocale,
        $columns = ['TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'],
        $foreignTable = null,
        $foreignKey = 'ID',
        $localeAlias = null,
    ): void {
        if (!empty($columns)) {
            if (null === $foreignTable) {
                $foreignTable = $search->getTableMap()->getName();
                $aliasPrefix = '';
            } else {
                $aliasPrefix = $foreignTable.'_';
            }

            if (null === $localeAlias) {
                $localeAlias = $search->getTableMap()->getName();
            }

            $requestedLocaleI18nAlias = $aliasPrefix.'requested_locale_i18n';

            $requestedLocaleJoin = new Join();
            $requestedLocaleJoin->addExplicitCondition(
                $localeAlias,
                $foreignKey,
                null,
                $foreignTable.'_i18n',
                'ID',
                $requestedLocaleI18nAlias,
            );
            $requestedLocaleJoin->setJoinType(Criteria::LEFT_JOIN);

            $search->addJoinObject($requestedLocaleJoin, $requestedLocaleI18nAlias)
                ->addJoinCondition(
                    $requestedLocaleI18nAlias,
                    '`'.$requestedLocaleI18nAlias.'`.LOCALE = ?',
                    $requestedLocale,
                    null,
                    \PDO::PARAM_STR,
                );

            $search->withColumn('NOT ISNULL(`'.$requestedLocaleI18nAlias.'`.`ID`)', $aliasPrefix.'IS_TRANSLATED');

            foreach ($columns as $column) {
                $search->withColumn(
                    '`'.$requestedLocaleI18nAlias.'`.`'.$column.'`',
                    $aliasPrefix.'i18n_'.$column,
                );
            }
        }
    }

    /**
     * Bild query to retrieve I18n.
     *
     * @param string|null $localeAlias le local table if different of the main query table
     */
    public static function getI18n(
        bool $backendContext,
        int $requestedLangId,
        ModelCriteria &$search,
        string $currentLocale,
        array $columns,
        ?string $foreignTable,
        string $foreignKey,
        bool $forceReturn = false,
        ?string $localeAlias = null,
    ): string {
        // If a lang has been requested, find the related Lang object, and get the locale
        if (null !== $requestedLangId) {
            $localeSearch = LangQuery::create()->findByIdOrLocale($requestedLangId);

            if (null === $localeSearch) {
                throw new \InvalidArgumentException(\sprintf('Incorrect lang argument given : lang %s not found', $requestedLangId));
            }

            $locale = $localeSearch->getLocale();
        } else {
            // Use the currently defined locale
            $locale = $currentLocale;
        }

        // Call the proper method depending on the context: front or back
        if ($backendContext) {
            self::getBackEndI18n($search, $locale, $columns, $foreignTable, $foreignKey, $localeAlias);
        } else {
            self::getFrontEndI18n($search, $locale, $columns, $foreignTable, $foreignKey, $forceReturn, $localeAlias);
        }

        return $locale;
    }
}
