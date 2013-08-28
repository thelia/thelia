<?php

namespace Thelia\Model\Tools;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Model\Base\LangQuery;

/**
 * Class ModelCriteriaTools
 *
 * @package Thelia\Model\Tools
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class ModelCriteriaTools
{
    /**
     * @param ModelCriteria $search
     * @param               $defaultLangWithoutTranslation
     * @param               $askedLocale
     * @param array         $columns
     * @param null          $foreignTable
     * @param string        $foreignKey
     */
    public static function getFrontEndI18n(ModelCriteria &$search, $defaultLangWithoutTranslation, $askedLocale, $columns = array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'), $foreignTable = null, $foreignKey = 'ID')
    {
        if($foreignTable === null) {
            $foreignTable = $search->getTableMap()->getName();
            $aliasPrefix = '';
        } else {
            $aliasPrefix = $foreignTable . '_';
        }

        $askedLocaleI18nAlias = 'asked_locale_i18n';
        $defaultLocaleI18nAlias = 'default_locale_i18n';

        if($defaultLangWithoutTranslation == 0) {
            $askedLocaleJoin = new Join();
            $askedLocaleJoin->addExplicitCondition($search->getTableMap()->getName(), $foreignKey, null, $foreignTable . '_i18n', 'ID', $askedLocaleI18nAlias);
            $askedLocaleJoin->setJoinType(Criteria::INNER_JOIN);

            $search->addJoinObject($askedLocaleJoin, $askedLocaleI18nAlias)
                ->addJoinCondition($askedLocaleI18nAlias ,'`' . $askedLocaleI18nAlias . '`.LOCALE = ?', $askedLocale, null, \PDO::PARAM_STR);

            $search->withColumn('NOT ISNULL(`' . $askedLocaleI18nAlias . '`.`ID`)', $aliasPrefix . 'IS_TRANSLATED');

            foreach($columns as $column) {
                $search->withColumn('`' . $askedLocaleI18nAlias . '`.`' . $column . '`', $aliasPrefix . 'i18n_' . $column);
            }
        } else {
            $defaultLocale = LangQuery::create()->findOneById($defaultLangWithoutTranslation)->getLocale();

            $defaultLocaleJoin = new Join();
            $defaultLocaleJoin->addExplicitCondition($search->getTableMap()->getName(), $foreignKey, null, $foreignTable . '_i18n', 'ID', $defaultLocaleI18nAlias);
            $defaultLocaleJoin->setJoinType(Criteria::LEFT_JOIN);

            $search->addJoinObject($defaultLocaleJoin, $defaultLocaleI18nAlias)
                ->addJoinCondition($defaultLocaleI18nAlias ,'`' . $defaultLocaleI18nAlias . '`.LOCALE = ?', $defaultLocale, null, \PDO::PARAM_STR);

            $askedLocaleJoin = new Join();
            $askedLocaleJoin->addExplicitCondition($search->getTableMap()->getName(), $foreignKey, null, $foreignTable . '_i18n', 'ID', $askedLocaleI18nAlias);
            $askedLocaleJoin->setJoinType(Criteria::LEFT_JOIN);

            $search->addJoinObject($askedLocaleJoin, $askedLocaleI18nAlias)
                ->addJoinCondition($askedLocaleI18nAlias ,'`' . $askedLocaleI18nAlias . '`.LOCALE = ?', $askedLocale, null, \PDO::PARAM_STR);

            $search->withColumn('NOT ISNULL(`' . $askedLocaleI18nAlias . '`.`ID`)', $aliasPrefix . 'IS_TRANSLATED');

            $search->where('NOT ISNULL(`' . $askedLocaleI18nAlias . '`.ID)')->_or()->where('NOT ISNULL(`' . $defaultLocaleI18nAlias . '`.ID)');

            foreach($columns as $column) {
                $search->withColumn('CASE WHEN NOT ISNULL(`' . $askedLocaleI18nAlias . '`.ID) THEN `' . $askedLocaleI18nAlias . '`.`' . $column . '` ELSE `' . $defaultLocaleI18nAlias . '`.`' . $column . '` END', $aliasPrefix . 'i18n_' . $column);
            }
        }
    }

    public static function getBackEndI18n(ModelCriteria &$search, $askedLocale, $columns = array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'), $foreignTable = null, $foreignKey = 'ID')
    {
        if($foreignTable === null) {
            $foreignTable = $search->getTableMap()->getName();
            $aliasPrefix = '';
        } else {
            $aliasPrefix = $foreignTable . '_';
        }

        $askedLocaleI18nAlias = 'asked_locale_i18n';

        $askedLocaleJoin = new Join();
        $askedLocaleJoin->addExplicitCondition($search->getTableMap()->getName(), $foreignKey, null, $foreignTable . '_i18n', 'ID', $askedLocaleI18nAlias);
        $askedLocaleJoin->setJoinType(Criteria::LEFT_JOIN);

        $search->addJoinObject($askedLocaleJoin, $askedLocaleI18nAlias)
            ->addJoinCondition($askedLocaleI18nAlias ,'`' . $askedLocaleI18nAlias . '`.LOCALE = ?', $askedLocale, null, \PDO::PARAM_STR);

        $search->withColumn('NOT ISNULL(`' . $askedLocaleI18nAlias . '`.`ID`)', $aliasPrefix . 'IS_TRANSLATED');

        foreach($columns as $column) {
            $search->withColumn('`' . $askedLocaleI18nAlias . '`.`' . $column . '`', $aliasPrefix . 'i18n_' . $column);
        }
    }

    public static function getI18n($backendContext, $lang, ModelCriteria &$search, $defaultLangWithoutTranslation, $currentLocale, $columns = array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'), $foreignTable = null, $foreignKey = 'ID')
    {
        if($lang !== null) {
            $localeSearch = LangQuery::create()->findOneById($lang);
            if($localeSearch === null) {
                throw new \InvalidArgumentException('Incorrect lang argument given in attribute loop');
            }
        }

        if($backendContext) {
            self::getBackEndI18n($search, $lang === null ? $currentLocale : $localeSearch->getLocale(), $columns, $foreignTable, $foreignKey);
        } else {
            self::getFrontEndI18n($search, $defaultLangWithoutTranslation, $lang === null ? $currentLocale : $localeSearch->getLocale(), $columns, $foreignTable, $foreignKey);
        }
    }
}
