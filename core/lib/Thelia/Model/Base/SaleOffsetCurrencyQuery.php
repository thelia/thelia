<?php

namespace Thelia\Model\Base;

use \Exception;
use \PDO;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\SaleOffsetCurrency as ChildSaleOffsetCurrency;
use Thelia\Model\SaleOffsetCurrencyQuery as ChildSaleOffsetCurrencyQuery;
use Thelia\Model\Map\SaleOffsetCurrencyTableMap;

/**
 * Base class that represents a query for the 'sale_offset_currency' table.
 *
 *
 *
 * @method     ChildSaleOffsetCurrencyQuery orderBySaleId($order = Criteria::ASC) Order by the sale_id column
 * @method     ChildSaleOffsetCurrencyQuery orderByCurrencyId($order = Criteria::ASC) Order by the currency_id column
 * @method     ChildSaleOffsetCurrencyQuery orderByPriceOffsetValue($order = Criteria::ASC) Order by the price_offset_value column
 *
 * @method     ChildSaleOffsetCurrencyQuery groupBySaleId() Group by the sale_id column
 * @method     ChildSaleOffsetCurrencyQuery groupByCurrencyId() Group by the currency_id column
 * @method     ChildSaleOffsetCurrencyQuery groupByPriceOffsetValue() Group by the price_offset_value column
 *
 * @method     ChildSaleOffsetCurrencyQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildSaleOffsetCurrencyQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildSaleOffsetCurrencyQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildSaleOffsetCurrencyQuery leftJoinSale($relationAlias = null) Adds a LEFT JOIN clause to the query using the Sale relation
 * @method     ChildSaleOffsetCurrencyQuery rightJoinSale($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Sale relation
 * @method     ChildSaleOffsetCurrencyQuery innerJoinSale($relationAlias = null) Adds a INNER JOIN clause to the query using the Sale relation
 *
 * @method     ChildSaleOffsetCurrencyQuery leftJoinCurrency($relationAlias = null) Adds a LEFT JOIN clause to the query using the Currency relation
 * @method     ChildSaleOffsetCurrencyQuery rightJoinCurrency($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Currency relation
 * @method     ChildSaleOffsetCurrencyQuery innerJoinCurrency($relationAlias = null) Adds a INNER JOIN clause to the query using the Currency relation
 *
 * @method     ChildSaleOffsetCurrency findOne(ConnectionInterface $con = null) Return the first ChildSaleOffsetCurrency matching the query
 * @method     ChildSaleOffsetCurrency findOneOrCreate(ConnectionInterface $con = null) Return the first ChildSaleOffsetCurrency matching the query, or a new ChildSaleOffsetCurrency object populated from the query conditions when no match is found
 *
 * @method     ChildSaleOffsetCurrency findOneBySaleId(int $sale_id) Return the first ChildSaleOffsetCurrency filtered by the sale_id column
 * @method     ChildSaleOffsetCurrency findOneByCurrencyId(int $currency_id) Return the first ChildSaleOffsetCurrency filtered by the currency_id column
 * @method     ChildSaleOffsetCurrency findOneByPriceOffsetValue(double $price_offset_value) Return the first ChildSaleOffsetCurrency filtered by the price_offset_value column
 *
 * @method     array findBySaleId(int $sale_id) Return ChildSaleOffsetCurrency objects filtered by the sale_id column
 * @method     array findByCurrencyId(int $currency_id) Return ChildSaleOffsetCurrency objects filtered by the currency_id column
 * @method     array findByPriceOffsetValue(double $price_offset_value) Return ChildSaleOffsetCurrency objects filtered by the price_offset_value column
 *
 */
abstract class SaleOffsetCurrencyQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\SaleOffsetCurrencyQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\SaleOffsetCurrency', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildSaleOffsetCurrencyQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildSaleOffsetCurrencyQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\SaleOffsetCurrencyQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\SaleOffsetCurrencyQuery();
        if (null !== $modelAlias) {
            $query->setModelAlias($modelAlias);
        }
        if ($criteria instanceof Criteria) {
            $query->mergeWith($criteria);
        }

        return $query;
    }

    /**
     * Find object by primary key.
     * Propel uses the instance pool to skip the database if the object exists.
     * Go fast if the query is untouched.
     *
     * <code>
     * $obj = $c->findPk(array(12, 34), $con);
     * </code>
     *
     * @param array[$sale_id, $currency_id] $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildSaleOffsetCurrency|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = SaleOffsetCurrencyTableMap::getInstanceFromPool(serialize(array((string) $key[0], (string) $key[1]))))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(SaleOffsetCurrencyTableMap::DATABASE_NAME);
        }
        $this->basePreSelect($con);
        if ($this->formatter || $this->modelAlias || $this->with || $this->select
         || $this->selectColumns || $this->asColumns || $this->selectModifiers
         || $this->map || $this->having || $this->joins) {
            return $this->findPkComplex($key, $con);
        } else {
            return $this->findPkSimple($key, $con);
        }
    }

    /**
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @return   ChildSaleOffsetCurrency A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `SALE_ID`, `CURRENCY_ID`, `PRICE_OFFSET_VALUE` FROM `sale_offset_currency` WHERE `SALE_ID` = :p0 AND `CURRENCY_ID` = :p1';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key[0], PDO::PARAM_INT);
            $stmt->bindValue(':p1', $key[1], PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $obj = new ChildSaleOffsetCurrency();
            $obj->hydrate($row);
            SaleOffsetCurrencyTableMap::addInstanceToPool($obj, serialize(array((string) $key[0], (string) $key[1])));
        }
        $stmt->closeCursor();

        return $obj;
    }

    /**
     * Find object by primary key.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @return ChildSaleOffsetCurrency|array|mixed the result, formatted by the current formatter
     */
    protected function findPkComplex($key, $con)
    {
        // As the query uses a PK condition, no limit(1) is necessary.
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKey($key)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->formatOne($dataFetcher);
    }

    /**
     * Find objects by primary key
     * <code>
     * $objs = $c->findPks(array(array(12, 56), array(832, 123), array(123, 456)), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     ConnectionInterface $con an optional connection object
     *
     * @return ObjectCollection|array|mixed the list of results, formatted by the current formatter
     */
    public function findPks($keys, $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getReadConnection($this->getDbName());
        }
        $this->basePreSelect($con);
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $dataFetcher = $criteria
            ->filterByPrimaryKeys($keys)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->format($dataFetcher);
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return ChildSaleOffsetCurrencyQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(SaleOffsetCurrencyTableMap::SALE_ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(SaleOffsetCurrencyTableMap::CURRENCY_ID, $key[1], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildSaleOffsetCurrencyQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(SaleOffsetCurrencyTableMap::SALE_ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(SaleOffsetCurrencyTableMap::CURRENCY_ID, $key[1], Criteria::EQUAL);
            $cton0->addAnd($cton1);
            $this->addOr($cton0);
        }

        return $this;
    }

    /**
     * Filter the query on the sale_id column
     *
     * Example usage:
     * <code>
     * $query->filterBySaleId(1234); // WHERE sale_id = 1234
     * $query->filterBySaleId(array(12, 34)); // WHERE sale_id IN (12, 34)
     * $query->filterBySaleId(array('min' => 12)); // WHERE sale_id > 12
     * </code>
     *
     * @see       filterBySale()
     *
     * @param     mixed $saleId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleOffsetCurrencyQuery The current query, for fluid interface
     */
    public function filterBySaleId($saleId = null, $comparison = null)
    {
        if (is_array($saleId)) {
            $useMinMax = false;
            if (isset($saleId['min'])) {
                $this->addUsingAlias(SaleOffsetCurrencyTableMap::SALE_ID, $saleId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($saleId['max'])) {
                $this->addUsingAlias(SaleOffsetCurrencyTableMap::SALE_ID, $saleId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(SaleOffsetCurrencyTableMap::SALE_ID, $saleId, $comparison);
    }

    /**
     * Filter the query on the currency_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCurrencyId(1234); // WHERE currency_id = 1234
     * $query->filterByCurrencyId(array(12, 34)); // WHERE currency_id IN (12, 34)
     * $query->filterByCurrencyId(array('min' => 12)); // WHERE currency_id > 12
     * </code>
     *
     * @see       filterByCurrency()
     *
     * @param     mixed $currencyId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleOffsetCurrencyQuery The current query, for fluid interface
     */
    public function filterByCurrencyId($currencyId = null, $comparison = null)
    {
        if (is_array($currencyId)) {
            $useMinMax = false;
            if (isset($currencyId['min'])) {
                $this->addUsingAlias(SaleOffsetCurrencyTableMap::CURRENCY_ID, $currencyId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($currencyId['max'])) {
                $this->addUsingAlias(SaleOffsetCurrencyTableMap::CURRENCY_ID, $currencyId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(SaleOffsetCurrencyTableMap::CURRENCY_ID, $currencyId, $comparison);
    }

    /**
     * Filter the query on the price_offset_value column
     *
     * Example usage:
     * <code>
     * $query->filterByPriceOffsetValue(1234); // WHERE price_offset_value = 1234
     * $query->filterByPriceOffsetValue(array(12, 34)); // WHERE price_offset_value IN (12, 34)
     * $query->filterByPriceOffsetValue(array('min' => 12)); // WHERE price_offset_value > 12
     * </code>
     *
     * @param     mixed $priceOffsetValue The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleOffsetCurrencyQuery The current query, for fluid interface
     */
    public function filterByPriceOffsetValue($priceOffsetValue = null, $comparison = null)
    {
        if (is_array($priceOffsetValue)) {
            $useMinMax = false;
            if (isset($priceOffsetValue['min'])) {
                $this->addUsingAlias(SaleOffsetCurrencyTableMap::PRICE_OFFSET_VALUE, $priceOffsetValue['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($priceOffsetValue['max'])) {
                $this->addUsingAlias(SaleOffsetCurrencyTableMap::PRICE_OFFSET_VALUE, $priceOffsetValue['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(SaleOffsetCurrencyTableMap::PRICE_OFFSET_VALUE, $priceOffsetValue, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Sale object
     *
     * @param \Thelia\Model\Sale|ObjectCollection $sale The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleOffsetCurrencyQuery The current query, for fluid interface
     */
    public function filterBySale($sale, $comparison = null)
    {
        if ($sale instanceof \Thelia\Model\Sale) {
            return $this
                ->addUsingAlias(SaleOffsetCurrencyTableMap::SALE_ID, $sale->getId(), $comparison);
        } elseif ($sale instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(SaleOffsetCurrencyTableMap::SALE_ID, $sale->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterBySale() only accepts arguments of type \Thelia\Model\Sale or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Sale relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildSaleOffsetCurrencyQuery The current query, for fluid interface
     */
    public function joinSale($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Sale');

        // create a ModelJoin object for this join
        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
        if ($previousJoin = $this->getPreviousJoin()) {
            $join->setPreviousJoin($previousJoin);
        }

        // add the ModelJoin to the current object
        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
            $this->addJoinObject($join, $relationAlias);
        } else {
            $this->addJoinObject($join, 'Sale');
        }

        return $this;
    }

    /**
     * Use the Sale relation Sale object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\SaleQuery A secondary query class using the current class as primary query
     */
    public function useSaleQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinSale($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Sale', '\Thelia\Model\SaleQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Currency object
     *
     * @param \Thelia\Model\Currency|ObjectCollection $currency The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleOffsetCurrencyQuery The current query, for fluid interface
     */
    public function filterByCurrency($currency, $comparison = null)
    {
        if ($currency instanceof \Thelia\Model\Currency) {
            return $this
                ->addUsingAlias(SaleOffsetCurrencyTableMap::CURRENCY_ID, $currency->getId(), $comparison);
        } elseif ($currency instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(SaleOffsetCurrencyTableMap::CURRENCY_ID, $currency->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByCurrency() only accepts arguments of type \Thelia\Model\Currency or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Currency relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildSaleOffsetCurrencyQuery The current query, for fluid interface
     */
    public function joinCurrency($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Currency');

        // create a ModelJoin object for this join
        $join = new ModelJoin();
        $join->setJoinType($joinType);
        $join->setRelationMap($relationMap, $this->useAliasInSQL ? $this->getModelAlias() : null, $relationAlias);
        if ($previousJoin = $this->getPreviousJoin()) {
            $join->setPreviousJoin($previousJoin);
        }

        // add the ModelJoin to the current object
        if ($relationAlias) {
            $this->addAlias($relationAlias, $relationMap->getRightTable()->getName());
            $this->addJoinObject($join, $relationAlias);
        } else {
            $this->addJoinObject($join, 'Currency');
        }

        return $this;
    }

    /**
     * Use the Currency relation Currency object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CurrencyQuery A secondary query class using the current class as primary query
     */
    public function useCurrencyQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCurrency($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Currency', '\Thelia\Model\CurrencyQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildSaleOffsetCurrency $saleOffsetCurrency Object to remove from the list of results
     *
     * @return ChildSaleOffsetCurrencyQuery The current query, for fluid interface
     */
    public function prune($saleOffsetCurrency = null)
    {
        if ($saleOffsetCurrency) {
            $this->addCond('pruneCond0', $this->getAliasedColName(SaleOffsetCurrencyTableMap::SALE_ID), $saleOffsetCurrency->getSaleId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(SaleOffsetCurrencyTableMap::CURRENCY_ID), $saleOffsetCurrency->getCurrencyId(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

    /**
     * Deletes all rows from the sale_offset_currency table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(SaleOffsetCurrencyTableMap::DATABASE_NAME);
        }
        $affectedRows = 0; // initialize var to track total num of affected rows
        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();
            $affectedRows += parent::doDeleteAll($con);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            SaleOffsetCurrencyTableMap::clearInstancePool();
            SaleOffsetCurrencyTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildSaleOffsetCurrency or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildSaleOffsetCurrency object or primary key or array of primary keys
     *              which is used to create the DELETE statement
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *                if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
     public function delete(ConnectionInterface $con = null)
     {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(SaleOffsetCurrencyTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(SaleOffsetCurrencyTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        SaleOffsetCurrencyTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            SaleOffsetCurrencyTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

} // SaleOffsetCurrencyQuery
