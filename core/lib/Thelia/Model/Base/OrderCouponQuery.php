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
use Thelia\Model\OrderCoupon as ChildOrderCoupon;
use Thelia\Model\OrderCouponQuery as ChildOrderCouponQuery;
use Thelia\Model\Map\OrderCouponTableMap;

/**
 * Base class that represents a query for the 'order_coupon' table.
 *
 *
 *
 * @method     ChildOrderCouponQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildOrderCouponQuery orderByOrderId($order = Criteria::ASC) Order by the order_id column
 * @method     ChildOrderCouponQuery orderByCode($order = Criteria::ASC) Order by the code column
 * @method     ChildOrderCouponQuery orderByType($order = Criteria::ASC) Order by the type column
 * @method     ChildOrderCouponQuery orderByAmount($order = Criteria::ASC) Order by the amount column
 * @method     ChildOrderCouponQuery orderByTitle($order = Criteria::ASC) Order by the title column
 * @method     ChildOrderCouponQuery orderByShortDescription($order = Criteria::ASC) Order by the short_description column
 * @method     ChildOrderCouponQuery orderByDescription($order = Criteria::ASC) Order by the description column
 * @method     ChildOrderCouponQuery orderByStartDate($order = Criteria::ASC) Order by the start_date column
 * @method     ChildOrderCouponQuery orderByExpirationDate($order = Criteria::ASC) Order by the expiration_date column
 * @method     ChildOrderCouponQuery orderByIsCumulative($order = Criteria::ASC) Order by the is_cumulative column
 * @method     ChildOrderCouponQuery orderByIsRemovingPostage($order = Criteria::ASC) Order by the is_removing_postage column
 * @method     ChildOrderCouponQuery orderByIsAvailableOnSpecialOffers($order = Criteria::ASC) Order by the is_available_on_special_offers column
 * @method     ChildOrderCouponQuery orderBySerializedConditions($order = Criteria::ASC) Order by the serialized_conditions column
 * @method     ChildOrderCouponQuery orderByPerCustomerUsageCount($order = Criteria::ASC) Order by the per_customer_usage_count column
 * @method     ChildOrderCouponQuery orderByUsageCanceled($order = Criteria::ASC) Order by the usage_canceled column
 * @method     ChildOrderCouponQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildOrderCouponQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildOrderCouponQuery groupById() Group by the id column
 * @method     ChildOrderCouponQuery groupByOrderId() Group by the order_id column
 * @method     ChildOrderCouponQuery groupByCode() Group by the code column
 * @method     ChildOrderCouponQuery groupByType() Group by the type column
 * @method     ChildOrderCouponQuery groupByAmount() Group by the amount column
 * @method     ChildOrderCouponQuery groupByTitle() Group by the title column
 * @method     ChildOrderCouponQuery groupByShortDescription() Group by the short_description column
 * @method     ChildOrderCouponQuery groupByDescription() Group by the description column
 * @method     ChildOrderCouponQuery groupByStartDate() Group by the start_date column
 * @method     ChildOrderCouponQuery groupByExpirationDate() Group by the expiration_date column
 * @method     ChildOrderCouponQuery groupByIsCumulative() Group by the is_cumulative column
 * @method     ChildOrderCouponQuery groupByIsRemovingPostage() Group by the is_removing_postage column
 * @method     ChildOrderCouponQuery groupByIsAvailableOnSpecialOffers() Group by the is_available_on_special_offers column
 * @method     ChildOrderCouponQuery groupBySerializedConditions() Group by the serialized_conditions column
 * @method     ChildOrderCouponQuery groupByPerCustomerUsageCount() Group by the per_customer_usage_count column
 * @method     ChildOrderCouponQuery groupByUsageCanceled() Group by the usage_canceled column
 * @method     ChildOrderCouponQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildOrderCouponQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildOrderCouponQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildOrderCouponQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildOrderCouponQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildOrderCouponQuery leftJoinOrder($relationAlias = null) Adds a LEFT JOIN clause to the query using the Order relation
 * @method     ChildOrderCouponQuery rightJoinOrder($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Order relation
 * @method     ChildOrderCouponQuery innerJoinOrder($relationAlias = null) Adds a INNER JOIN clause to the query using the Order relation
 *
 * @method     ChildOrderCouponQuery leftJoinOrderCouponCountry($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderCouponCountry relation
 * @method     ChildOrderCouponQuery rightJoinOrderCouponCountry($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderCouponCountry relation
 * @method     ChildOrderCouponQuery innerJoinOrderCouponCountry($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderCouponCountry relation
 *
 * @method     ChildOrderCouponQuery leftJoinOrderCouponModule($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderCouponModule relation
 * @method     ChildOrderCouponQuery rightJoinOrderCouponModule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderCouponModule relation
 * @method     ChildOrderCouponQuery innerJoinOrderCouponModule($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderCouponModule relation
 *
 * @method     ChildOrderCoupon findOne(ConnectionInterface $con = null) Return the first ChildOrderCoupon matching the query
 * @method     ChildOrderCoupon findOneOrCreate(ConnectionInterface $con = null) Return the first ChildOrderCoupon matching the query, or a new ChildOrderCoupon object populated from the query conditions when no match is found
 *
 * @method     ChildOrderCoupon findOneById(int $id) Return the first ChildOrderCoupon filtered by the id column
 * @method     ChildOrderCoupon findOneByOrderId(int $order_id) Return the first ChildOrderCoupon filtered by the order_id column
 * @method     ChildOrderCoupon findOneByCode(string $code) Return the first ChildOrderCoupon filtered by the code column
 * @method     ChildOrderCoupon findOneByType(string $type) Return the first ChildOrderCoupon filtered by the type column
 * @method     ChildOrderCoupon findOneByAmount(string $amount) Return the first ChildOrderCoupon filtered by the amount column
 * @method     ChildOrderCoupon findOneByTitle(string $title) Return the first ChildOrderCoupon filtered by the title column
 * @method     ChildOrderCoupon findOneByShortDescription(string $short_description) Return the first ChildOrderCoupon filtered by the short_description column
 * @method     ChildOrderCoupon findOneByDescription(string $description) Return the first ChildOrderCoupon filtered by the description column
 * @method     ChildOrderCoupon findOneByStartDate(string $start_date) Return the first ChildOrderCoupon filtered by the start_date column
 * @method     ChildOrderCoupon findOneByExpirationDate(string $expiration_date) Return the first ChildOrderCoupon filtered by the expiration_date column
 * @method     ChildOrderCoupon findOneByIsCumulative(boolean $is_cumulative) Return the first ChildOrderCoupon filtered by the is_cumulative column
 * @method     ChildOrderCoupon findOneByIsRemovingPostage(boolean $is_removing_postage) Return the first ChildOrderCoupon filtered by the is_removing_postage column
 * @method     ChildOrderCoupon findOneByIsAvailableOnSpecialOffers(boolean $is_available_on_special_offers) Return the first ChildOrderCoupon filtered by the is_available_on_special_offers column
 * @method     ChildOrderCoupon findOneBySerializedConditions(string $serialized_conditions) Return the first ChildOrderCoupon filtered by the serialized_conditions column
 * @method     ChildOrderCoupon findOneByPerCustomerUsageCount(boolean $per_customer_usage_count) Return the first ChildOrderCoupon filtered by the per_customer_usage_count column
 * @method     ChildOrderCoupon findOneByUsageCanceled(boolean $usage_canceled) Return the first ChildOrderCoupon filtered by the usage_canceled column
 * @method     ChildOrderCoupon findOneByCreatedAt(string $created_at) Return the first ChildOrderCoupon filtered by the created_at column
 * @method     ChildOrderCoupon findOneByUpdatedAt(string $updated_at) Return the first ChildOrderCoupon filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildOrderCoupon objects filtered by the id column
 * @method     array findByOrderId(int $order_id) Return ChildOrderCoupon objects filtered by the order_id column
 * @method     array findByCode(string $code) Return ChildOrderCoupon objects filtered by the code column
 * @method     array findByType(string $type) Return ChildOrderCoupon objects filtered by the type column
 * @method     array findByAmount(string $amount) Return ChildOrderCoupon objects filtered by the amount column
 * @method     array findByTitle(string $title) Return ChildOrderCoupon objects filtered by the title column
 * @method     array findByShortDescription(string $short_description) Return ChildOrderCoupon objects filtered by the short_description column
 * @method     array findByDescription(string $description) Return ChildOrderCoupon objects filtered by the description column
 * @method     array findByStartDate(string $start_date) Return ChildOrderCoupon objects filtered by the start_date column
 * @method     array findByExpirationDate(string $expiration_date) Return ChildOrderCoupon objects filtered by the expiration_date column
 * @method     array findByIsCumulative(boolean $is_cumulative) Return ChildOrderCoupon objects filtered by the is_cumulative column
 * @method     array findByIsRemovingPostage(boolean $is_removing_postage) Return ChildOrderCoupon objects filtered by the is_removing_postage column
 * @method     array findByIsAvailableOnSpecialOffers(boolean $is_available_on_special_offers) Return ChildOrderCoupon objects filtered by the is_available_on_special_offers column
 * @method     array findBySerializedConditions(string $serialized_conditions) Return ChildOrderCoupon objects filtered by the serialized_conditions column
 * @method     array findByPerCustomerUsageCount(boolean $per_customer_usage_count) Return ChildOrderCoupon objects filtered by the per_customer_usage_count column
 * @method     array findByUsageCanceled(boolean $usage_canceled) Return ChildOrderCoupon objects filtered by the usage_canceled column
 * @method     array findByCreatedAt(string $created_at) Return ChildOrderCoupon objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildOrderCoupon objects filtered by the updated_at column
 *
 */
abstract class OrderCouponQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\OrderCouponQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\OrderCoupon', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildOrderCouponQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildOrderCouponQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\OrderCouponQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\OrderCouponQuery();
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
     * @return ChildOrderCoupon|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = OrderCouponTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(OrderCouponTableMap::DATABASE_NAME);
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
     * @return   ChildOrderCoupon A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `ORDER_ID`, `CODE`, `TYPE`, `AMOUNT`, `TITLE`, `SHORT_DESCRIPTION`, `DESCRIPTION`, `START_DATE`, `EXPIRATION_DATE`, `IS_CUMULATIVE`, `IS_REMOVING_POSTAGE`, `IS_AVAILABLE_ON_SPECIAL_OFFERS`, `SERIALIZED_CONDITIONS`, `PER_CUSTOMER_USAGE_COUNT`, `USAGE_CANCELED`, `CREATED_AT`, `UPDATED_AT` FROM `order_coupon` WHERE `ID` = :p0';
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
            $obj = new ChildOrderCoupon();
            $obj->hydrate($row);
            OrderCouponTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildOrderCoupon|array|mixed the result, formatted by the current formatter
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
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(OrderCouponTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(OrderCouponTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(OrderCouponTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(OrderCouponTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderCouponTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the order_id column
     *
     * Example usage:
     * <code>
     * $query->filterByOrderId(1234); // WHERE order_id = 1234
     * $query->filterByOrderId(array(12, 34)); // WHERE order_id IN (12, 34)
     * $query->filterByOrderId(array('min' => 12)); // WHERE order_id > 12
     * </code>
     *
     * @see       filterByOrder()
     *
     * @param     mixed $orderId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByOrderId($orderId = null, $comparison = null)
    {
        if (is_array($orderId)) {
            $useMinMax = false;
            if (isset($orderId['min'])) {
                $this->addUsingAlias(OrderCouponTableMap::ORDER_ID, $orderId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($orderId['max'])) {
                $this->addUsingAlias(OrderCouponTableMap::ORDER_ID, $orderId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderCouponTableMap::ORDER_ID, $orderId, $comparison);
    }

    /**
     * Filter the query on the code column
     *
     * Example usage:
     * <code>
     * $query->filterByCode('fooValue');   // WHERE code = 'fooValue'
     * $query->filterByCode('%fooValue%'); // WHERE code LIKE '%fooValue%'
     * </code>
     *
     * @param     string $code The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByCode($code = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($code)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $code)) {
                $code = str_replace('*', '%', $code);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderCouponTableMap::CODE, $code, $comparison);
    }

    /**
     * Filter the query on the type column
     *
     * Example usage:
     * <code>
     * $query->filterByType('fooValue');   // WHERE type = 'fooValue'
     * $query->filterByType('%fooValue%'); // WHERE type LIKE '%fooValue%'
     * </code>
     *
     * @param     string $type The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByType($type = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($type)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $type)) {
                $type = str_replace('*', '%', $type);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderCouponTableMap::TYPE, $type, $comparison);
    }

    /**
     * Filter the query on the amount column
     *
     * Example usage:
     * <code>
     * $query->filterByAmount(1234); // WHERE amount = 1234
     * $query->filterByAmount(array(12, 34)); // WHERE amount IN (12, 34)
     * $query->filterByAmount(array('min' => 12)); // WHERE amount > 12
     * </code>
     *
     * @param     mixed $amount The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByAmount($amount = null, $comparison = null)
    {
        if (is_array($amount)) {
            $useMinMax = false;
            if (isset($amount['min'])) {
                $this->addUsingAlias(OrderCouponTableMap::AMOUNT, $amount['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($amount['max'])) {
                $this->addUsingAlias(OrderCouponTableMap::AMOUNT, $amount['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderCouponTableMap::AMOUNT, $amount, $comparison);
    }

    /**
     * Filter the query on the title column
     *
     * Example usage:
     * <code>
     * $query->filterByTitle('fooValue');   // WHERE title = 'fooValue'
     * $query->filterByTitle('%fooValue%'); // WHERE title LIKE '%fooValue%'
     * </code>
     *
     * @param     string $title The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByTitle($title = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($title)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $title)) {
                $title = str_replace('*', '%', $title);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderCouponTableMap::TITLE, $title, $comparison);
    }

    /**
     * Filter the query on the short_description column
     *
     * Example usage:
     * <code>
     * $query->filterByShortDescription('fooValue');   // WHERE short_description = 'fooValue'
     * $query->filterByShortDescription('%fooValue%'); // WHERE short_description LIKE '%fooValue%'
     * </code>
     *
     * @param     string $shortDescription The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByShortDescription($shortDescription = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($shortDescription)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $shortDescription)) {
                $shortDescription = str_replace('*', '%', $shortDescription);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderCouponTableMap::SHORT_DESCRIPTION, $shortDescription, $comparison);
    }

    /**
     * Filter the query on the description column
     *
     * Example usage:
     * <code>
     * $query->filterByDescription('fooValue');   // WHERE description = 'fooValue'
     * $query->filterByDescription('%fooValue%'); // WHERE description LIKE '%fooValue%'
     * </code>
     *
     * @param     string $description The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByDescription($description = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($description)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $description)) {
                $description = str_replace('*', '%', $description);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderCouponTableMap::DESCRIPTION, $description, $comparison);
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
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByStartDate($startDate = null, $comparison = null)
    {
        if (is_array($startDate)) {
            $useMinMax = false;
            if (isset($startDate['min'])) {
                $this->addUsingAlias(OrderCouponTableMap::START_DATE, $startDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($startDate['max'])) {
                $this->addUsingAlias(OrderCouponTableMap::START_DATE, $startDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderCouponTableMap::START_DATE, $startDate, $comparison);
    }

    /**
     * Filter the query on the expiration_date column
     *
     * Example usage:
     * <code>
     * $query->filterByExpirationDate('2011-03-14'); // WHERE expiration_date = '2011-03-14'
     * $query->filterByExpirationDate('now'); // WHERE expiration_date = '2011-03-14'
     * $query->filterByExpirationDate(array('max' => 'yesterday')); // WHERE expiration_date > '2011-03-13'
     * </code>
     *
     * @param     mixed $expirationDate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByExpirationDate($expirationDate = null, $comparison = null)
    {
        if (is_array($expirationDate)) {
            $useMinMax = false;
            if (isset($expirationDate['min'])) {
                $this->addUsingAlias(OrderCouponTableMap::EXPIRATION_DATE, $expirationDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($expirationDate['max'])) {
                $this->addUsingAlias(OrderCouponTableMap::EXPIRATION_DATE, $expirationDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderCouponTableMap::EXPIRATION_DATE, $expirationDate, $comparison);
    }

    /**
     * Filter the query on the is_cumulative column
     *
     * Example usage:
     * <code>
     * $query->filterByIsCumulative(true); // WHERE is_cumulative = true
     * $query->filterByIsCumulative('yes'); // WHERE is_cumulative = true
     * </code>
     *
     * @param     boolean|string $isCumulative The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByIsCumulative($isCumulative = null, $comparison = null)
    {
        if (is_string($isCumulative)) {
            $is_cumulative = in_array(strtolower($isCumulative), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(OrderCouponTableMap::IS_CUMULATIVE, $isCumulative, $comparison);
    }

    /**
     * Filter the query on the is_removing_postage column
     *
     * Example usage:
     * <code>
     * $query->filterByIsRemovingPostage(true); // WHERE is_removing_postage = true
     * $query->filterByIsRemovingPostage('yes'); // WHERE is_removing_postage = true
     * </code>
     *
     * @param     boolean|string $isRemovingPostage The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByIsRemovingPostage($isRemovingPostage = null, $comparison = null)
    {
        if (is_string($isRemovingPostage)) {
            $is_removing_postage = in_array(strtolower($isRemovingPostage), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(OrderCouponTableMap::IS_REMOVING_POSTAGE, $isRemovingPostage, $comparison);
    }

    /**
     * Filter the query on the is_available_on_special_offers column
     *
     * Example usage:
     * <code>
     * $query->filterByIsAvailableOnSpecialOffers(true); // WHERE is_available_on_special_offers = true
     * $query->filterByIsAvailableOnSpecialOffers('yes'); // WHERE is_available_on_special_offers = true
     * </code>
     *
     * @param     boolean|string $isAvailableOnSpecialOffers The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByIsAvailableOnSpecialOffers($isAvailableOnSpecialOffers = null, $comparison = null)
    {
        if (is_string($isAvailableOnSpecialOffers)) {
            $is_available_on_special_offers = in_array(strtolower($isAvailableOnSpecialOffers), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(OrderCouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS, $isAvailableOnSpecialOffers, $comparison);
    }

    /**
     * Filter the query on the serialized_conditions column
     *
     * Example usage:
     * <code>
     * $query->filterBySerializedConditions('fooValue');   // WHERE serialized_conditions = 'fooValue'
     * $query->filterBySerializedConditions('%fooValue%'); // WHERE serialized_conditions LIKE '%fooValue%'
     * </code>
     *
     * @param     string $serializedConditions The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterBySerializedConditions($serializedConditions = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($serializedConditions)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $serializedConditions)) {
                $serializedConditions = str_replace('*', '%', $serializedConditions);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderCouponTableMap::SERIALIZED_CONDITIONS, $serializedConditions, $comparison);
    }

    /**
     * Filter the query on the per_customer_usage_count column
     *
     * Example usage:
     * <code>
     * $query->filterByPerCustomerUsageCount(true); // WHERE per_customer_usage_count = true
     * $query->filterByPerCustomerUsageCount('yes'); // WHERE per_customer_usage_count = true
     * </code>
     *
     * @param     boolean|string $perCustomerUsageCount The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByPerCustomerUsageCount($perCustomerUsageCount = null, $comparison = null)
    {
        if (is_string($perCustomerUsageCount)) {
            $per_customer_usage_count = in_array(strtolower($perCustomerUsageCount), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(OrderCouponTableMap::PER_CUSTOMER_USAGE_COUNT, $perCustomerUsageCount, $comparison);
    }

    /**
     * Filter the query on the usage_canceled column
     *
     * Example usage:
     * <code>
     * $query->filterByUsageCanceled(true); // WHERE usage_canceled = true
     * $query->filterByUsageCanceled('yes'); // WHERE usage_canceled = true
     * </code>
     *
     * @param     boolean|string $usageCanceled The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByUsageCanceled($usageCanceled = null, $comparison = null)
    {
        if (is_string($usageCanceled)) {
            $usage_canceled = in_array(strtolower($usageCanceled), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(OrderCouponTableMap::USAGE_CANCELED, $usageCanceled, $comparison);
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
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(OrderCouponTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(OrderCouponTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderCouponTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(OrderCouponTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(OrderCouponTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderCouponTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Order object
     *
     * @param \Thelia\Model\Order|ObjectCollection $order The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByOrder($order, $comparison = null)
    {
        if ($order instanceof \Thelia\Model\Order) {
            return $this
                ->addUsingAlias(OrderCouponTableMap::ORDER_ID, $order->getId(), $comparison);
        } elseif ($order instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderCouponTableMap::ORDER_ID, $order->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByOrder() only accepts arguments of type \Thelia\Model\Order or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Order relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function joinOrder($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Order');

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
            $this->addJoinObject($join, 'Order');
        }

        return $this;
    }

    /**
     * Use the Order relation Order object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderQuery A secondary query class using the current class as primary query
     */
    public function useOrderQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrder($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Order', '\Thelia\Model\OrderQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\OrderCouponCountry object
     *
     * @param \Thelia\Model\OrderCouponCountry|ObjectCollection $orderCouponCountry  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByOrderCouponCountry($orderCouponCountry, $comparison = null)
    {
        if ($orderCouponCountry instanceof \Thelia\Model\OrderCouponCountry) {
            return $this
                ->addUsingAlias(OrderCouponTableMap::ID, $orderCouponCountry->getCouponId(), $comparison);
        } elseif ($orderCouponCountry instanceof ObjectCollection) {
            return $this
                ->useOrderCouponCountryQuery()
                ->filterByPrimaryKeys($orderCouponCountry->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByOrderCouponCountry() only accepts arguments of type \Thelia\Model\OrderCouponCountry or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderCouponCountry relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function joinOrderCouponCountry($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderCouponCountry');

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
            $this->addJoinObject($join, 'OrderCouponCountry');
        }

        return $this;
    }

    /**
     * Use the OrderCouponCountry relation OrderCouponCountry object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderCouponCountryQuery A secondary query class using the current class as primary query
     */
    public function useOrderCouponCountryQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderCouponCountry($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderCouponCountry', '\Thelia\Model\OrderCouponCountryQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\OrderCouponModule object
     *
     * @param \Thelia\Model\OrderCouponModule|ObjectCollection $orderCouponModule  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByOrderCouponModule($orderCouponModule, $comparison = null)
    {
        if ($orderCouponModule instanceof \Thelia\Model\OrderCouponModule) {
            return $this
                ->addUsingAlias(OrderCouponTableMap::ID, $orderCouponModule->getCouponId(), $comparison);
        } elseif ($orderCouponModule instanceof ObjectCollection) {
            return $this
                ->useOrderCouponModuleQuery()
                ->filterByPrimaryKeys($orderCouponModule->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByOrderCouponModule() only accepts arguments of type \Thelia\Model\OrderCouponModule or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderCouponModule relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function joinOrderCouponModule($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderCouponModule');

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
            $this->addJoinObject($join, 'OrderCouponModule');
        }

        return $this;
    }

    /**
     * Use the OrderCouponModule relation OrderCouponModule object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderCouponModuleQuery A secondary query class using the current class as primary query
     */
    public function useOrderCouponModuleQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderCouponModule($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderCouponModule', '\Thelia\Model\OrderCouponModuleQuery');
    }

    /**
     * Filter the query by a related Country object
     * using the order_coupon_country table as cross reference
     *
     * @param Country $country the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByCountry($country, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useOrderCouponCountryQuery()
            ->filterByCountry($country, $comparison)
            ->endUse();
    }

    /**
     * Filter the query by a related Module object
     * using the order_coupon_module table as cross reference
     *
     * @param Module $module the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function filterByModule($module, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useOrderCouponModuleQuery()
            ->filterByModule($module, $comparison)
            ->endUse();
    }

    /**
     * Exclude object from result
     *
     * @param   ChildOrderCoupon $orderCoupon Object to remove from the list of results
     *
     * @return ChildOrderCouponQuery The current query, for fluid interface
     */
    public function prune($orderCoupon = null)
    {
        if ($orderCoupon) {
            $this->addUsingAlias(OrderCouponTableMap::ID, $orderCoupon->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the order_coupon table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderCouponTableMap::DATABASE_NAME);
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
            OrderCouponTableMap::clearInstancePool();
            OrderCouponTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildOrderCoupon or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildOrderCoupon object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(OrderCouponTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(OrderCouponTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        OrderCouponTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            OrderCouponTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

    // timestampable behavior

    /**
     * Filter by the latest updated
     *
     * @param      int $nbDays Maximum age of the latest update in days
     *
     * @return     ChildOrderCouponQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(OrderCouponTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildOrderCouponQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(OrderCouponTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildOrderCouponQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(OrderCouponTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildOrderCouponQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(OrderCouponTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildOrderCouponQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(OrderCouponTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildOrderCouponQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(OrderCouponTableMap::CREATED_AT);
    }

} // OrderCouponQuery
