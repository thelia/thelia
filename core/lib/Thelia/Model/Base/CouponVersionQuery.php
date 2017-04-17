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
use Thelia\Model\CouponVersion as ChildCouponVersion;
use Thelia\Model\CouponVersionQuery as ChildCouponVersionQuery;
use Thelia\Model\Map\CouponVersionTableMap;

/**
 * Base class that represents a query for the 'coupon_version' table.
 *
 *
 *
 * @method     ChildCouponVersionQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildCouponVersionQuery orderByCode($order = Criteria::ASC) Order by the code column
 * @method     ChildCouponVersionQuery orderByType($order = Criteria::ASC) Order by the type column
 * @method     ChildCouponVersionQuery orderBySerializedEffects($order = Criteria::ASC) Order by the serialized_effects column
 * @method     ChildCouponVersionQuery orderByIsEnabled($order = Criteria::ASC) Order by the is_enabled column
 * @method     ChildCouponVersionQuery orderByStartDate($order = Criteria::ASC) Order by the start_date column
 * @method     ChildCouponVersionQuery orderByExpirationDate($order = Criteria::ASC) Order by the expiration_date column
 * @method     ChildCouponVersionQuery orderByMaxUsage($order = Criteria::ASC) Order by the max_usage column
 * @method     ChildCouponVersionQuery orderByIsCumulative($order = Criteria::ASC) Order by the is_cumulative column
 * @method     ChildCouponVersionQuery orderByIsRemovingPostage($order = Criteria::ASC) Order by the is_removing_postage column
 * @method     ChildCouponVersionQuery orderByIsAvailableOnSpecialOffers($order = Criteria::ASC) Order by the is_available_on_special_offers column
 * @method     ChildCouponVersionQuery orderByIsUsed($order = Criteria::ASC) Order by the is_used column
 * @method     ChildCouponVersionQuery orderBySerializedConditions($order = Criteria::ASC) Order by the serialized_conditions column
 * @method     ChildCouponVersionQuery orderByPerCustomerUsageCount($order = Criteria::ASC) Order by the per_customer_usage_count column
 * @method     ChildCouponVersionQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildCouponVersionQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 * @method     ChildCouponVersionQuery orderByVersion($order = Criteria::ASC) Order by the version column
 * @method     ChildCouponVersionQuery orderByVersionCreatedAt($order = Criteria::ASC) Order by the version_created_at column
 * @method     ChildCouponVersionQuery orderByVersionCreatedBy($order = Criteria::ASC) Order by the version_created_by column
 *
 * @method     ChildCouponVersionQuery groupById() Group by the id column
 * @method     ChildCouponVersionQuery groupByCode() Group by the code column
 * @method     ChildCouponVersionQuery groupByType() Group by the type column
 * @method     ChildCouponVersionQuery groupBySerializedEffects() Group by the serialized_effects column
 * @method     ChildCouponVersionQuery groupByIsEnabled() Group by the is_enabled column
 * @method     ChildCouponVersionQuery groupByStartDate() Group by the start_date column
 * @method     ChildCouponVersionQuery groupByExpirationDate() Group by the expiration_date column
 * @method     ChildCouponVersionQuery groupByMaxUsage() Group by the max_usage column
 * @method     ChildCouponVersionQuery groupByIsCumulative() Group by the is_cumulative column
 * @method     ChildCouponVersionQuery groupByIsRemovingPostage() Group by the is_removing_postage column
 * @method     ChildCouponVersionQuery groupByIsAvailableOnSpecialOffers() Group by the is_available_on_special_offers column
 * @method     ChildCouponVersionQuery groupByIsUsed() Group by the is_used column
 * @method     ChildCouponVersionQuery groupBySerializedConditions() Group by the serialized_conditions column
 * @method     ChildCouponVersionQuery groupByPerCustomerUsageCount() Group by the per_customer_usage_count column
 * @method     ChildCouponVersionQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildCouponVersionQuery groupByUpdatedAt() Group by the updated_at column
 * @method     ChildCouponVersionQuery groupByVersion() Group by the version column
 * @method     ChildCouponVersionQuery groupByVersionCreatedAt() Group by the version_created_at column
 * @method     ChildCouponVersionQuery groupByVersionCreatedBy() Group by the version_created_by column
 *
 * @method     ChildCouponVersionQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildCouponVersionQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildCouponVersionQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildCouponVersionQuery leftJoinCoupon($relationAlias = null) Adds a LEFT JOIN clause to the query using the Coupon relation
 * @method     ChildCouponVersionQuery rightJoinCoupon($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Coupon relation
 * @method     ChildCouponVersionQuery innerJoinCoupon($relationAlias = null) Adds a INNER JOIN clause to the query using the Coupon relation
 *
 * @method     ChildCouponVersion findOne(ConnectionInterface $con = null) Return the first ChildCouponVersion matching the query
 * @method     ChildCouponVersion findOneOrCreate(ConnectionInterface $con = null) Return the first ChildCouponVersion matching the query, or a new ChildCouponVersion object populated from the query conditions when no match is found
 *
 * @method     ChildCouponVersion findOneById(int $id) Return the first ChildCouponVersion filtered by the id column
 * @method     ChildCouponVersion findOneByCode(string $code) Return the first ChildCouponVersion filtered by the code column
 * @method     ChildCouponVersion findOneByType(string $type) Return the first ChildCouponVersion filtered by the type column
 * @method     ChildCouponVersion findOneBySerializedEffects(string $serialized_effects) Return the first ChildCouponVersion filtered by the serialized_effects column
 * @method     ChildCouponVersion findOneByIsEnabled(boolean $is_enabled) Return the first ChildCouponVersion filtered by the is_enabled column
 * @method     ChildCouponVersion findOneByStartDate(string $start_date) Return the first ChildCouponVersion filtered by the start_date column
 * @method     ChildCouponVersion findOneByExpirationDate(string $expiration_date) Return the first ChildCouponVersion filtered by the expiration_date column
 * @method     ChildCouponVersion findOneByMaxUsage(int $max_usage) Return the first ChildCouponVersion filtered by the max_usage column
 * @method     ChildCouponVersion findOneByIsCumulative(boolean $is_cumulative) Return the first ChildCouponVersion filtered by the is_cumulative column
 * @method     ChildCouponVersion findOneByIsRemovingPostage(boolean $is_removing_postage) Return the first ChildCouponVersion filtered by the is_removing_postage column
 * @method     ChildCouponVersion findOneByIsAvailableOnSpecialOffers(boolean $is_available_on_special_offers) Return the first ChildCouponVersion filtered by the is_available_on_special_offers column
 * @method     ChildCouponVersion findOneByIsUsed(boolean $is_used) Return the first ChildCouponVersion filtered by the is_used column
 * @method     ChildCouponVersion findOneBySerializedConditions(string $serialized_conditions) Return the first ChildCouponVersion filtered by the serialized_conditions column
 * @method     ChildCouponVersion findOneByPerCustomerUsageCount(boolean $per_customer_usage_count) Return the first ChildCouponVersion filtered by the per_customer_usage_count column
 * @method     ChildCouponVersion findOneByCreatedAt(string $created_at) Return the first ChildCouponVersion filtered by the created_at column
 * @method     ChildCouponVersion findOneByUpdatedAt(string $updated_at) Return the first ChildCouponVersion filtered by the updated_at column
 * @method     ChildCouponVersion findOneByVersion(int $version) Return the first ChildCouponVersion filtered by the version column
 * @method     ChildCouponVersion findOneByVersionCreatedAt(string $version_created_at) Return the first ChildCouponVersion filtered by the version_created_at column
 * @method     ChildCouponVersion findOneByVersionCreatedBy(string $version_created_by) Return the first ChildCouponVersion filtered by the version_created_by column
 *
 * @method     array findById(int $id) Return ChildCouponVersion objects filtered by the id column
 * @method     array findByCode(string $code) Return ChildCouponVersion objects filtered by the code column
 * @method     array findByType(string $type) Return ChildCouponVersion objects filtered by the type column
 * @method     array findBySerializedEffects(string $serialized_effects) Return ChildCouponVersion objects filtered by the serialized_effects column
 * @method     array findByIsEnabled(boolean $is_enabled) Return ChildCouponVersion objects filtered by the is_enabled column
 * @method     array findByStartDate(string $start_date) Return ChildCouponVersion objects filtered by the start_date column
 * @method     array findByExpirationDate(string $expiration_date) Return ChildCouponVersion objects filtered by the expiration_date column
 * @method     array findByMaxUsage(int $max_usage) Return ChildCouponVersion objects filtered by the max_usage column
 * @method     array findByIsCumulative(boolean $is_cumulative) Return ChildCouponVersion objects filtered by the is_cumulative column
 * @method     array findByIsRemovingPostage(boolean $is_removing_postage) Return ChildCouponVersion objects filtered by the is_removing_postage column
 * @method     array findByIsAvailableOnSpecialOffers(boolean $is_available_on_special_offers) Return ChildCouponVersion objects filtered by the is_available_on_special_offers column
 * @method     array findByIsUsed(boolean $is_used) Return ChildCouponVersion objects filtered by the is_used column
 * @method     array findBySerializedConditions(string $serialized_conditions) Return ChildCouponVersion objects filtered by the serialized_conditions column
 * @method     array findByPerCustomerUsageCount(boolean $per_customer_usage_count) Return ChildCouponVersion objects filtered by the per_customer_usage_count column
 * @method     array findByCreatedAt(string $created_at) Return ChildCouponVersion objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildCouponVersion objects filtered by the updated_at column
 * @method     array findByVersion(int $version) Return ChildCouponVersion objects filtered by the version column
 * @method     array findByVersionCreatedAt(string $version_created_at) Return ChildCouponVersion objects filtered by the version_created_at column
 * @method     array findByVersionCreatedBy(string $version_created_by) Return ChildCouponVersion objects filtered by the version_created_by column
 *
 */
abstract class CouponVersionQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\CouponVersionQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\CouponVersion', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildCouponVersionQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildCouponVersionQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\CouponVersionQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\CouponVersionQuery();
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
     * @param array[$id, $version] $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildCouponVersion|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = CouponVersionTableMap::getInstanceFromPool(serialize(array((string) $key[0], (string) $key[1]))))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(CouponVersionTableMap::DATABASE_NAME);
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
     * @return   ChildCouponVersion A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `CODE`, `TYPE`, `SERIALIZED_EFFECTS`, `IS_ENABLED`, `START_DATE`, `EXPIRATION_DATE`, `MAX_USAGE`, `IS_CUMULATIVE`, `IS_REMOVING_POSTAGE`, `IS_AVAILABLE_ON_SPECIAL_OFFERS`, `IS_USED`, `SERIALIZED_CONDITIONS`, `PER_CUSTOMER_USAGE_COUNT`, `CREATED_AT`, `UPDATED_AT`, `VERSION`, `VERSION_CREATED_AT`, `VERSION_CREATED_BY` FROM `coupon_version` WHERE `ID` = :p0 AND `VERSION` = :p1';
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
            $obj = new ChildCouponVersion();
            $obj->hydrate($row);
            CouponVersionTableMap::addInstanceToPool($obj, serialize(array((string) $key[0], (string) $key[1])));
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
     * @return ChildCouponVersion|array|mixed the result, formatted by the current formatter
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
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(CouponVersionTableMap::ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(CouponVersionTableMap::VERSION, $key[1], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(CouponVersionTableMap::ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(CouponVersionTableMap::VERSION, $key[1], Criteria::EQUAL);
            $cton0->addAnd($cton1);
            $this->addOr($cton0);
        }

        return $this;
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
     * @see       filterByCoupon()
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(CouponVersionTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(CouponVersionTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponVersionTableMap::ID, $id, $comparison);
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
     * @return ChildCouponVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CouponVersionTableMap::CODE, $code, $comparison);
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
     * @return ChildCouponVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CouponVersionTableMap::TYPE, $type, $comparison);
    }

    /**
     * Filter the query on the serialized_effects column
     *
     * Example usage:
     * <code>
     * $query->filterBySerializedEffects('fooValue');   // WHERE serialized_effects = 'fooValue'
     * $query->filterBySerializedEffects('%fooValue%'); // WHERE serialized_effects LIKE '%fooValue%'
     * </code>
     *
     * @param     string $serializedEffects The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterBySerializedEffects($serializedEffects = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($serializedEffects)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $serializedEffects)) {
                $serializedEffects = str_replace('*', '%', $serializedEffects);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CouponVersionTableMap::SERIALIZED_EFFECTS, $serializedEffects, $comparison);
    }

    /**
     * Filter the query on the is_enabled column
     *
     * Example usage:
     * <code>
     * $query->filterByIsEnabled(true); // WHERE is_enabled = true
     * $query->filterByIsEnabled('yes'); // WHERE is_enabled = true
     * </code>
     *
     * @param     boolean|string $isEnabled The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByIsEnabled($isEnabled = null, $comparison = null)
    {
        if (is_string($isEnabled)) {
            $is_enabled = in_array(strtolower($isEnabled), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(CouponVersionTableMap::IS_ENABLED, $isEnabled, $comparison);
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
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByStartDate($startDate = null, $comparison = null)
    {
        if (is_array($startDate)) {
            $useMinMax = false;
            if (isset($startDate['min'])) {
                $this->addUsingAlias(CouponVersionTableMap::START_DATE, $startDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($startDate['max'])) {
                $this->addUsingAlias(CouponVersionTableMap::START_DATE, $startDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponVersionTableMap::START_DATE, $startDate, $comparison);
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
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByExpirationDate($expirationDate = null, $comparison = null)
    {
        if (is_array($expirationDate)) {
            $useMinMax = false;
            if (isset($expirationDate['min'])) {
                $this->addUsingAlias(CouponVersionTableMap::EXPIRATION_DATE, $expirationDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($expirationDate['max'])) {
                $this->addUsingAlias(CouponVersionTableMap::EXPIRATION_DATE, $expirationDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponVersionTableMap::EXPIRATION_DATE, $expirationDate, $comparison);
    }

    /**
     * Filter the query on the max_usage column
     *
     * Example usage:
     * <code>
     * $query->filterByMaxUsage(1234); // WHERE max_usage = 1234
     * $query->filterByMaxUsage(array(12, 34)); // WHERE max_usage IN (12, 34)
     * $query->filterByMaxUsage(array('min' => 12)); // WHERE max_usage > 12
     * </code>
     *
     * @param     mixed $maxUsage The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByMaxUsage($maxUsage = null, $comparison = null)
    {
        if (is_array($maxUsage)) {
            $useMinMax = false;
            if (isset($maxUsage['min'])) {
                $this->addUsingAlias(CouponVersionTableMap::MAX_USAGE, $maxUsage['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($maxUsage['max'])) {
                $this->addUsingAlias(CouponVersionTableMap::MAX_USAGE, $maxUsage['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponVersionTableMap::MAX_USAGE, $maxUsage, $comparison);
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
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByIsCumulative($isCumulative = null, $comparison = null)
    {
        if (is_string($isCumulative)) {
            $is_cumulative = in_array(strtolower($isCumulative), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(CouponVersionTableMap::IS_CUMULATIVE, $isCumulative, $comparison);
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
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByIsRemovingPostage($isRemovingPostage = null, $comparison = null)
    {
        if (is_string($isRemovingPostage)) {
            $is_removing_postage = in_array(strtolower($isRemovingPostage), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(CouponVersionTableMap::IS_REMOVING_POSTAGE, $isRemovingPostage, $comparison);
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
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByIsAvailableOnSpecialOffers($isAvailableOnSpecialOffers = null, $comparison = null)
    {
        if (is_string($isAvailableOnSpecialOffers)) {
            $is_available_on_special_offers = in_array(strtolower($isAvailableOnSpecialOffers), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(CouponVersionTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS, $isAvailableOnSpecialOffers, $comparison);
    }

    /**
     * Filter the query on the is_used column
     *
     * Example usage:
     * <code>
     * $query->filterByIsUsed(true); // WHERE is_used = true
     * $query->filterByIsUsed('yes'); // WHERE is_used = true
     * </code>
     *
     * @param     boolean|string $isUsed The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByIsUsed($isUsed = null, $comparison = null)
    {
        if (is_string($isUsed)) {
            $is_used = in_array(strtolower($isUsed), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(CouponVersionTableMap::IS_USED, $isUsed, $comparison);
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
     * @return ChildCouponVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CouponVersionTableMap::SERIALIZED_CONDITIONS, $serializedConditions, $comparison);
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
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByPerCustomerUsageCount($perCustomerUsageCount = null, $comparison = null)
    {
        if (is_string($perCustomerUsageCount)) {
            $per_customer_usage_count = in_array(strtolower($perCustomerUsageCount), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(CouponVersionTableMap::PER_CUSTOMER_USAGE_COUNT, $perCustomerUsageCount, $comparison);
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
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(CouponVersionTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(CouponVersionTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponVersionTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(CouponVersionTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(CouponVersionTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponVersionTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query on the version column
     *
     * Example usage:
     * <code>
     * $query->filterByVersion(1234); // WHERE version = 1234
     * $query->filterByVersion(array(12, 34)); // WHERE version IN (12, 34)
     * $query->filterByVersion(array('min' => 12)); // WHERE version > 12
     * </code>
     *
     * @param     mixed $version The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByVersion($version = null, $comparison = null)
    {
        if (is_array($version)) {
            $useMinMax = false;
            if (isset($version['min'])) {
                $this->addUsingAlias(CouponVersionTableMap::VERSION, $version['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($version['max'])) {
                $this->addUsingAlias(CouponVersionTableMap::VERSION, $version['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponVersionTableMap::VERSION, $version, $comparison);
    }

    /**
     * Filter the query on the version_created_at column
     *
     * Example usage:
     * <code>
     * $query->filterByVersionCreatedAt('2011-03-14'); // WHERE version_created_at = '2011-03-14'
     * $query->filterByVersionCreatedAt('now'); // WHERE version_created_at = '2011-03-14'
     * $query->filterByVersionCreatedAt(array('max' => 'yesterday')); // WHERE version_created_at > '2011-03-13'
     * </code>
     *
     * @param     mixed $versionCreatedAt The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByVersionCreatedAt($versionCreatedAt = null, $comparison = null)
    {
        if (is_array($versionCreatedAt)) {
            $useMinMax = false;
            if (isset($versionCreatedAt['min'])) {
                $this->addUsingAlias(CouponVersionTableMap::VERSION_CREATED_AT, $versionCreatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($versionCreatedAt['max'])) {
                $this->addUsingAlias(CouponVersionTableMap::VERSION_CREATED_AT, $versionCreatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponVersionTableMap::VERSION_CREATED_AT, $versionCreatedAt, $comparison);
    }

    /**
     * Filter the query on the version_created_by column
     *
     * Example usage:
     * <code>
     * $query->filterByVersionCreatedBy('fooValue');   // WHERE version_created_by = 'fooValue'
     * $query->filterByVersionCreatedBy('%fooValue%'); // WHERE version_created_by LIKE '%fooValue%'
     * </code>
     *
     * @param     string $versionCreatedBy The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByVersionCreatedBy($versionCreatedBy = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($versionCreatedBy)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $versionCreatedBy)) {
                $versionCreatedBy = str_replace('*', '%', $versionCreatedBy);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CouponVersionTableMap::VERSION_CREATED_BY, $versionCreatedBy, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Coupon object
     *
     * @param \Thelia\Model\Coupon|ObjectCollection $coupon The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function filterByCoupon($coupon, $comparison = null)
    {
        if ($coupon instanceof \Thelia\Model\Coupon) {
            return $this
                ->addUsingAlias(CouponVersionTableMap::ID, $coupon->getId(), $comparison);
        } elseif ($coupon instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(CouponVersionTableMap::ID, $coupon->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByCoupon() only accepts arguments of type \Thelia\Model\Coupon or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Coupon relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function joinCoupon($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Coupon');

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
            $this->addJoinObject($join, 'Coupon');
        }

        return $this;
    }

    /**
     * Use the Coupon relation Coupon object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CouponQuery A secondary query class using the current class as primary query
     */
    public function useCouponQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCoupon($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Coupon', '\Thelia\Model\CouponQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildCouponVersion $couponVersion Object to remove from the list of results
     *
     * @return ChildCouponVersionQuery The current query, for fluid interface
     */
    public function prune($couponVersion = null)
    {
        if ($couponVersion) {
            $this->addCond('pruneCond0', $this->getAliasedColName(CouponVersionTableMap::ID), $couponVersion->getId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(CouponVersionTableMap::VERSION), $couponVersion->getVersion(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

    /**
     * Deletes all rows from the coupon_version table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CouponVersionTableMap::DATABASE_NAME);
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
            CouponVersionTableMap::clearInstancePool();
            CouponVersionTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildCouponVersion or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildCouponVersion object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(CouponVersionTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(CouponVersionTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        CouponVersionTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            CouponVersionTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

} // CouponVersionQuery
