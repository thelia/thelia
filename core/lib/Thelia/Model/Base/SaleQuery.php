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
use Thelia\Model\Sale as ChildSale;
use Thelia\Model\SaleI18nQuery as ChildSaleI18nQuery;
use Thelia\Model\SaleQuery as ChildSaleQuery;
use Thelia\Model\Map\SaleTableMap;

/**
 * Base class that represents a query for the 'sale' table.
 *
 *
 *
 * @method     ChildSaleQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildSaleQuery orderByActive($order = Criteria::ASC) Order by the active column
 * @method     ChildSaleQuery orderByDisplayInitialPrice($order = Criteria::ASC) Order by the display_initial_price column
 * @method     ChildSaleQuery orderByStartDate($order = Criteria::ASC) Order by the start_date column
 * @method     ChildSaleQuery orderByEndDate($order = Criteria::ASC) Order by the end_date column
 * @method     ChildSaleQuery orderByPriceOffsetType($order = Criteria::ASC) Order by the price_offset_type column
 * @method     ChildSaleQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildSaleQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildSaleQuery groupById() Group by the id column
 * @method     ChildSaleQuery groupByActive() Group by the active column
 * @method     ChildSaleQuery groupByDisplayInitialPrice() Group by the display_initial_price column
 * @method     ChildSaleQuery groupByStartDate() Group by the start_date column
 * @method     ChildSaleQuery groupByEndDate() Group by the end_date column
 * @method     ChildSaleQuery groupByPriceOffsetType() Group by the price_offset_type column
 * @method     ChildSaleQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildSaleQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildSaleQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildSaleQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildSaleQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildSaleQuery leftJoinSaleOffsetCurrency($relationAlias = null) Adds a LEFT JOIN clause to the query using the SaleOffsetCurrency relation
 * @method     ChildSaleQuery rightJoinSaleOffsetCurrency($relationAlias = null) Adds a RIGHT JOIN clause to the query using the SaleOffsetCurrency relation
 * @method     ChildSaleQuery innerJoinSaleOffsetCurrency($relationAlias = null) Adds a INNER JOIN clause to the query using the SaleOffsetCurrency relation
 *
 * @method     ChildSaleQuery leftJoinSaleProduct($relationAlias = null) Adds a LEFT JOIN clause to the query using the SaleProduct relation
 * @method     ChildSaleQuery rightJoinSaleProduct($relationAlias = null) Adds a RIGHT JOIN clause to the query using the SaleProduct relation
 * @method     ChildSaleQuery innerJoinSaleProduct($relationAlias = null) Adds a INNER JOIN clause to the query using the SaleProduct relation
 *
 * @method     ChildSaleQuery leftJoinSaleI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the SaleI18n relation
 * @method     ChildSaleQuery rightJoinSaleI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the SaleI18n relation
 * @method     ChildSaleQuery innerJoinSaleI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the SaleI18n relation
 *
 * @method     ChildSale findOne(ConnectionInterface $con = null) Return the first ChildSale matching the query
 * @method     ChildSale findOneOrCreate(ConnectionInterface $con = null) Return the first ChildSale matching the query, or a new ChildSale object populated from the query conditions when no match is found
 *
 * @method     ChildSale findOneById(int $id) Return the first ChildSale filtered by the id column
 * @method     ChildSale findOneByActive(boolean $active) Return the first ChildSale filtered by the active column
 * @method     ChildSale findOneByDisplayInitialPrice(boolean $display_initial_price) Return the first ChildSale filtered by the display_initial_price column
 * @method     ChildSale findOneByStartDate(string $start_date) Return the first ChildSale filtered by the start_date column
 * @method     ChildSale findOneByEndDate(string $end_date) Return the first ChildSale filtered by the end_date column
 * @method     ChildSale findOneByPriceOffsetType(int $price_offset_type) Return the first ChildSale filtered by the price_offset_type column
 * @method     ChildSale findOneByCreatedAt(string $created_at) Return the first ChildSale filtered by the created_at column
 * @method     ChildSale findOneByUpdatedAt(string $updated_at) Return the first ChildSale filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildSale objects filtered by the id column
 * @method     array findByActive(boolean $active) Return ChildSale objects filtered by the active column
 * @method     array findByDisplayInitialPrice(boolean $display_initial_price) Return ChildSale objects filtered by the display_initial_price column
 * @method     array findByStartDate(string $start_date) Return ChildSale objects filtered by the start_date column
 * @method     array findByEndDate(string $end_date) Return ChildSale objects filtered by the end_date column
 * @method     array findByPriceOffsetType(int $price_offset_type) Return ChildSale objects filtered by the price_offset_type column
 * @method     array findByCreatedAt(string $created_at) Return ChildSale objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildSale objects filtered by the updated_at column
 *
 */
abstract class SaleQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\SaleQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Sale', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildSaleQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildSaleQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\SaleQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\SaleQuery();
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
     * $obj  = $c->findPk(12, $con);
     * </code>
     *
     * @param mixed $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildSale|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = SaleTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(SaleTableMap::DATABASE_NAME);
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
     * @return   ChildSale A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `ACTIVE`, `DISPLAY_INITIAL_PRICE`, `START_DATE`, `END_DATE`, `PRICE_OFFSET_TYPE`, `CREATED_AT`, `UPDATED_AT` FROM `sale` WHERE `ID` = :p0';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $obj = new ChildSale();
            $obj->hydrate($row);
            SaleTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildSale|array|mixed the result, formatted by the current formatter
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
     * $objs = $c->findPks(array(12, 56, 832), $con);
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
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(SaleTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(SaleTableMap::ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the id column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE id = 1234
     * $query->filterById(array(12, 34)); // WHERE id IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE id > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(SaleTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(SaleTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(SaleTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the active column
     *
     * Example usage:
     * <code>
     * $query->filterByActive(true); // WHERE active = true
     * $query->filterByActive('yes'); // WHERE active = true
     * </code>
     *
     * @param     boolean|string $active The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function filterByActive($active = null, $comparison = null)
    {
        if (is_string($active)) {
            $active = in_array(strtolower($active), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(SaleTableMap::ACTIVE, $active, $comparison);
    }

    /**
     * Filter the query on the display_initial_price column
     *
     * Example usage:
     * <code>
     * $query->filterByDisplayInitialPrice(true); // WHERE display_initial_price = true
     * $query->filterByDisplayInitialPrice('yes'); // WHERE display_initial_price = true
     * </code>
     *
     * @param     boolean|string $displayInitialPrice The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function filterByDisplayInitialPrice($displayInitialPrice = null, $comparison = null)
    {
        if (is_string($displayInitialPrice)) {
            $display_initial_price = in_array(strtolower($displayInitialPrice), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(SaleTableMap::DISPLAY_INITIAL_PRICE, $displayInitialPrice, $comparison);
    }

    /**
     * Filter the query on the start_date column
     *
     * Example usage:
     * <code>
     * $query->filterByStartDate('2011-03-14'); // WHERE start_date = '2011-03-14'
     * $query->filterByStartDate('now'); // WHERE start_date = '2011-03-14'
     * $query->filterByStartDate(array('max' => 'yesterday')); // WHERE start_date > '2011-03-13'
     * </code>
     *
     * @param     mixed $startDate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function filterByStartDate($startDate = null, $comparison = null)
    {
        if (is_array($startDate)) {
            $useMinMax = false;
            if (isset($startDate['min'])) {
                $this->addUsingAlias(SaleTableMap::START_DATE, $startDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($startDate['max'])) {
                $this->addUsingAlias(SaleTableMap::START_DATE, $startDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(SaleTableMap::START_DATE, $startDate, $comparison);
    }

    /**
     * Filter the query on the end_date column
     *
     * Example usage:
     * <code>
     * $query->filterByEndDate('2011-03-14'); // WHERE end_date = '2011-03-14'
     * $query->filterByEndDate('now'); // WHERE end_date = '2011-03-14'
     * $query->filterByEndDate(array('max' => 'yesterday')); // WHERE end_date > '2011-03-13'
     * </code>
     *
     * @param     mixed $endDate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function filterByEndDate($endDate = null, $comparison = null)
    {
        if (is_array($endDate)) {
            $useMinMax = false;
            if (isset($endDate['min'])) {
                $this->addUsingAlias(SaleTableMap::END_DATE, $endDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($endDate['max'])) {
                $this->addUsingAlias(SaleTableMap::END_DATE, $endDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(SaleTableMap::END_DATE, $endDate, $comparison);
    }

    /**
     * Filter the query on the price_offset_type column
     *
     * Example usage:
     * <code>
     * $query->filterByPriceOffsetType(1234); // WHERE price_offset_type = 1234
     * $query->filterByPriceOffsetType(array(12, 34)); // WHERE price_offset_type IN (12, 34)
     * $query->filterByPriceOffsetType(array('min' => 12)); // WHERE price_offset_type > 12
     * </code>
     *
     * @param     mixed $priceOffsetType The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function filterByPriceOffsetType($priceOffsetType = null, $comparison = null)
    {
        if (is_array($priceOffsetType)) {
            $useMinMax = false;
            if (isset($priceOffsetType['min'])) {
                $this->addUsingAlias(SaleTableMap::PRICE_OFFSET_TYPE, $priceOffsetType['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($priceOffsetType['max'])) {
                $this->addUsingAlias(SaleTableMap::PRICE_OFFSET_TYPE, $priceOffsetType['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(SaleTableMap::PRICE_OFFSET_TYPE, $priceOffsetType, $comparison);
    }

    /**
     * Filter the query on the created_at column
     *
     * Example usage:
     * <code>
     * $query->filterByCreatedAt('2011-03-14'); // WHERE created_at = '2011-03-14'
     * $query->filterByCreatedAt('now'); // WHERE created_at = '2011-03-14'
     * $query->filterByCreatedAt(array('max' => 'yesterday')); // WHERE created_at > '2011-03-13'
     * </code>
     *
     * @param     mixed $createdAt The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(SaleTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(SaleTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(SaleTableMap::CREATED_AT, $createdAt, $comparison);
    }

    /**
     * Filter the query on the updated_at column
     *
     * Example usage:
     * <code>
     * $query->filterByUpdatedAt('2011-03-14'); // WHERE updated_at = '2011-03-14'
     * $query->filterByUpdatedAt('now'); // WHERE updated_at = '2011-03-14'
     * $query->filterByUpdatedAt(array('max' => 'yesterday')); // WHERE updated_at > '2011-03-13'
     * </code>
     *
     * @param     mixed $updatedAt The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(SaleTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(SaleTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(SaleTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\SaleOffsetCurrency object
     *
     * @param \Thelia\Model\SaleOffsetCurrency|ObjectCollection $saleOffsetCurrency  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function filterBySaleOffsetCurrency($saleOffsetCurrency, $comparison = null)
    {
        if ($saleOffsetCurrency instanceof \Thelia\Model\SaleOffsetCurrency) {
            return $this
                ->addUsingAlias(SaleTableMap::ID, $saleOffsetCurrency->getSaleId(), $comparison);
        } elseif ($saleOffsetCurrency instanceof ObjectCollection) {
            return $this
                ->useSaleOffsetCurrencyQuery()
                ->filterByPrimaryKeys($saleOffsetCurrency->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterBySaleOffsetCurrency() only accepts arguments of type \Thelia\Model\SaleOffsetCurrency or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the SaleOffsetCurrency relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function joinSaleOffsetCurrency($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('SaleOffsetCurrency');

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
            $this->addJoinObject($join, 'SaleOffsetCurrency');
        }

        return $this;
    }

    /**
     * Use the SaleOffsetCurrency relation SaleOffsetCurrency object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\SaleOffsetCurrencyQuery A secondary query class using the current class as primary query
     */
    public function useSaleOffsetCurrencyQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinSaleOffsetCurrency($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'SaleOffsetCurrency', '\Thelia\Model\SaleOffsetCurrencyQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\SaleProduct object
     *
     * @param \Thelia\Model\SaleProduct|ObjectCollection $saleProduct  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function filterBySaleProduct($saleProduct, $comparison = null)
    {
        if ($saleProduct instanceof \Thelia\Model\SaleProduct) {
            return $this
                ->addUsingAlias(SaleTableMap::ID, $saleProduct->getSaleId(), $comparison);
        } elseif ($saleProduct instanceof ObjectCollection) {
            return $this
                ->useSaleProductQuery()
                ->filterByPrimaryKeys($saleProduct->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterBySaleProduct() only accepts arguments of type \Thelia\Model\SaleProduct or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the SaleProduct relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function joinSaleProduct($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('SaleProduct');

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
            $this->addJoinObject($join, 'SaleProduct');
        }

        return $this;
    }

    /**
     * Use the SaleProduct relation SaleProduct object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\SaleProductQuery A secondary query class using the current class as primary query
     */
    public function useSaleProductQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinSaleProduct($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'SaleProduct', '\Thelia\Model\SaleProductQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\SaleI18n object
     *
     * @param \Thelia\Model\SaleI18n|ObjectCollection $saleI18n  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function filterBySaleI18n($saleI18n, $comparison = null)
    {
        if ($saleI18n instanceof \Thelia\Model\SaleI18n) {
            return $this
                ->addUsingAlias(SaleTableMap::ID, $saleI18n->getId(), $comparison);
        } elseif ($saleI18n instanceof ObjectCollection) {
            return $this
                ->useSaleI18nQuery()
                ->filterByPrimaryKeys($saleI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterBySaleI18n() only accepts arguments of type \Thelia\Model\SaleI18n or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the SaleI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function joinSaleI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('SaleI18n');

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
            $this->addJoinObject($join, 'SaleI18n');
        }

        return $this;
    }

    /**
     * Use the SaleI18n relation SaleI18n object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\SaleI18nQuery A secondary query class using the current class as primary query
     */
    public function useSaleI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinSaleI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'SaleI18n', '\Thelia\Model\SaleI18nQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildSale $sale Object to remove from the list of results
     *
     * @return ChildSaleQuery The current query, for fluid interface
     */
    public function prune($sale = null)
    {
        if ($sale) {
            $this->addUsingAlias(SaleTableMap::ID, $sale->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the sale table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(SaleTableMap::DATABASE_NAME);
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
            SaleTableMap::clearInstancePool();
            SaleTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildSale or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildSale object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(SaleTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(SaleTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        SaleTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            SaleTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildSaleQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'SaleI18n';

        return $this
            ->joinSaleI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildSaleQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('SaleI18n');
        $this->with['SaleI18n']->setIsWithOneToMany(false);

        return $this;
    }

    /**
     * Use the I18n relation query object
     *
     * @see       useQuery()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildSaleI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'SaleI18n', '\Thelia\Model\SaleI18nQuery');
    }

    // timestampable behavior

    /**
     * Filter by the latest updated
     *
     * @param      int $nbDays Maximum age of the latest update in days
     *
     * @return     ChildSaleQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(SaleTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildSaleQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(SaleTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildSaleQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(SaleTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildSaleQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(SaleTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildSaleQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(SaleTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildSaleQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(SaleTableMap::CREATED_AT);
    }

} // SaleQuery
