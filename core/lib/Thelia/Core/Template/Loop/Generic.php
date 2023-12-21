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
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use TheliaMain\PropelResolver;

/**
 * @method string getTableName()
 * @method string getFilters()
 * @method string getOrders()
 * @method string getLocale()
 * @method string getLimit()()
 */
class Generic extends BaseLoop implements PropelSearchLoopInterface
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createAlphaNumStringTypeArgument('table_name', null, true),
            Argument::createAnyTypeArgument('filters'),
            Argument::createAnyTypeArgument('orders'),
            Argument::createAnyTypeArgument('locale'),
            Argument::createIntTypeArgument('limit', 100)
        );
    }

    public function buildModelCriteria()
    {
        if (!$locale = $this->getLocale()) {
            $locale = $this->getCurrentRequest()->getSession()->getLang()->getLocale();
        }

        $tableMapClass = PropelResolver::getTableMapByTableName($this->getTableName());
        $tableMap = new $tableMapClass();

        /** @var ModelCriteria $queryClass */
        $queryClass = $tableMap->getClassName().'Query';

        /** @var ModelCriteria $query */
        $query = $queryClass::create();

        $filters = $this->getParsedParams($this->getFilters());

        foreach ($filters as $filter => $value) {
            if (!$value) {
                continue;
            }

            $filterMethod = 'filterBy'.str_replace('_', '', ucwords($filter, '_'));

            if (!method_exists($query, $filterMethod)) {
                continue;
            }

            $query->$filterMethod($value, Criteria::IN);
        }

        $i18nTableMapClass = PropelResolver::getTableMapByTableName($this->getTableName().'_i18n');
        $useI18nQueryMethod = 'use'.$tableMap->getPhpName().'I18nQuery';
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

        $orders = $this->getParsedParams($this->getOrders());

        foreach ($orders as $order => $direction) {
            $orderByMethod = 'orderBy'.str_replace('_', '', ucwords($order, '_'));
            if (!\is_callable([$query, $orderByMethod])) {
                continue;
            }
            $direction = $direction[0] ?? 'ASC';
            $query->$orderByMethod($direction);
        }

        $query->limit($this->getLimit());

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
                $getter = 'get'.$columnPhpNames[$columnIndex];
                if (method_exists($item, $getter)) {
                    $loopResultRow->set(strtoupper($columnName), $item->$getter());
                }
            }

            $i18nTableMapClass = PropelResolver::getTableMapByTableName($this->getTableName().'_i18n');
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

    protected function getParsedParams($params)
    {
        $rawParams = explode('|', $params);
        $params = [];

        foreach ($rawParams as $rawParam) {
            $paramData = explode(':', $rawParam);
            if (!isset($paramData[0]) || empty($paramData[0])) {
                continue;
            }
            $params[$paramData[0]] = isset($paramData[1]) ? explode(',', $paramData[1]) : null;
        }

        return $params;
    }
}
