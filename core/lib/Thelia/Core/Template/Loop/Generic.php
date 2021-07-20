<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Map\TableMap;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use TheliaMain\PropelResolver;

class Generic extends BaseI18nLoop implements PropelSearchLoopInterface
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createAlphaNumStringTypeArgument('table_name', null, true),
            Argument::createAnyTypeArgument('filters')
        );
    }

    public function buildModelCriteria()
    {
        $locale = $this->getCurrentRequest()->getSession()->getLang()->getLocale();

        $tableMapClass = PropelResolver::getTableMapByTableName($this->getTableName());
        $tableMap = new $tableMapClass();

        /** @var ModelCriteria $queryClass */
        $queryClass = $tableMap->getClassName()."Query";

        /** @var ModelCriteria $query */
        $query = $queryClass::create();

        $filters = $this->getParsedFilters();

        foreach ($filters as $filter => $value) {
            $filterMethod = "filterBy".ucfirst($filter);
            if (!method_exists($query, $filterMethod)) {
                continue;
            }

            $query->$filterMethod($value, Criteria::IN);
        }

        $i18nTableMapClass = PropelResolver::getTableMapByTableName($this->getTableName()."_i18n");
        $useI18nQueryMethod = "use".$tableMap->getPhpName()."I18nQuery";
        if (null !== $i18nTableMapClass && method_exists($query, $useI18nQueryMethod)) {
            $i18nTableMap = new $i18nTableMapClass();
            $i18nQuery = $query->$useI18nQueryMethod();
            $i18nQuery->filterByLocale($locale);

            $i18nQuery->endUse();
            $i18nFields = TableMap::getFieldnamesForClass($i18nTableMap->getClassName(), TableMap::TYPE_PHPNAME);
            foreach (TableMap::getFieldnamesForClass($i18nTableMap->getClassName(), TableMap::TYPE_COLNAME) as $fieldIndex => $columnName) {
                $query->withColumn($columnName, $i18nFields[$fieldIndex]);
            }
        }
        return $query;
    }

    public function parseResults(LoopResult $loopResult)
    {
        $tableMapClass = PropelResolver::getTableMapByTableName($this->getTableName());
        $tableMap = new $tableMapClass();

        foreach ($loopResult->getResultDataCollection() as $item) {
            $loopResultRow = new LoopResultRow($item);

            $columnPhpNames = TableMap::getFieldnamesForClass($tableMap->getClassName(), TableMap::TYPE_PHPNAME);
            foreach (TableMap::getFieldnamesForClass($tableMap->getClassName(), TableMap::TYPE_FIELDNAME) as $columnIndex => $columnName) {
                $getter = "get".$columnPhpNames[$columnIndex];
                if (method_exists($item, $getter)) {
                    $loopResultRow->set(strtoupper($columnName), $item->$getter());
                }
            }

            $i18nTableMapClass = PropelResolver::getTableMapByTableName($this->getTableName()."_i18n");
            if (null !== $i18nTableMapClass) {
                $i18nTableMap = new $i18nTableMapClass();
                foreach (TableMap::getFieldnamesForClass($i18nTableMap->getClassName(), TableMap::TYPE_PHPNAME) as $columnName) {
                    $loopResultRow->set(strtoupper($columnName), $item->getVirtualColumn($columnName));
                }
            }

            $this->addOutputFields($loopResultRow, $item);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    protected function getParsedFilters()
    {
        $rawFilters = explode("|", $this->getFilters());
        $filters = [];
        foreach ($rawFilters as $rawFilter) {
            $filterData = explode(":", $rawFilter);
            if (!isset($filterData[0]) || !isset($filterData[1])) {
                continue;
            }

            $filters[$filterData[0]] = explode(',', $filterData[1]);
        }

        return $filters;
    }
}
