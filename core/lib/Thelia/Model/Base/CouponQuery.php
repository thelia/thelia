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
use Thelia\Model\Coupon as ChildCoupon;
use Thelia\Model\CouponI18nQuery as ChildCouponI18nQuery;
use Thelia\Model\CouponQuery as ChildCouponQuery;
use Thelia\Model\Map\CouponTableMap;

/**
 * Base class that represents a query for the 'coupon' table.
 *
 *
 *
 * @method     ChildCouponQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildCouponQuery orderByCode($order = Criteria::ASC) Order by the code column
 * @method     ChildCouponQuery orderByType($order = Criteria::ASC) Order by the type column
 * @method     ChildCouponQuery orderBySerializedEffects($order = Criteria::ASC) Order by the serialized_effects column
 * @method     ChildCouponQuery orderByIsEnabled($order = Criteria::ASC) Order by the is_enabled column
 * @method     ChildCouponQuery orderByStartDate($order = Criteria::ASC) Order by the start_date column
 * @method     ChildCouponQuery orderByExpirationDate($order = Criteria::ASC) Order by the expiration_date column
 * @method     ChildCouponQuery orderByMaxUsage($order = Criteria::ASC) Order by the max_usage column
 * @method     ChildCouponQuery orderByIsCumulative($order = Criteria::ASC) Order by the is_cumulative column
 * @method     ChildCouponQuery orderByIsRemovingPostage($order = Criteria::ASC) Order by the is_removing_postage column
 * @method     ChildCouponQuery orderByIsAvailableOnSpecialOffers($order = Criteria::ASC) Order by the is_available_on_special_offers column
 * @method     ChildCouponQuery orderByIsUsed($order = Criteria::ASC) Order by the is_used column
 * @method     ChildCouponQuery orderBySerializedConditions($order = Criteria::ASC) Order by the serialized_conditions column
 * @method     ChildCouponQuery orderByPerCustomerUsageCount($order = Criteria::ASC) Order by the per_customer_usage_count column
 * @method     ChildCouponQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildCouponQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 * @method     ChildCouponQuery orderByVersion($order = Criteria::ASC) Order by the version column
 * @method     ChildCouponQuery orderByVersionCreatedAt($order = Criteria::ASC) Order by the version_created_at column
 * @method     ChildCouponQuery orderByVersionCreatedBy($order = Criteria::ASC) Order by the version_created_by column
 *
 * @method     ChildCouponQuery groupById() Group by the id column
 * @method     ChildCouponQuery groupByCode() Group by the code column
 * @method     ChildCouponQuery groupByType() Group by the type column
 * @method     ChildCouponQuery groupBySerializedEffects() Group by the serialized_effects column
 * @method     ChildCouponQuery groupByIsEnabled() Group by the is_enabled column
 * @method     ChildCouponQuery groupByStartDate() Group by the start_date column
 * @method     ChildCouponQuery groupByExpirationDate() Group by the expiration_date column
 * @method     ChildCouponQuery groupByMaxUsage() Group by the max_usage column
 * @method     ChildCouponQuery groupByIsCumulative() Group by the is_cumulative column
 * @method     ChildCouponQuery groupByIsRemovingPostage() Group by the is_removing_postage column
 * @method     ChildCouponQuery groupByIsAvailableOnSpecialOffers() Group by the is_available_on_special_offers column
 * @method     ChildCouponQuery groupByIsUsed() Group by the is_used column
 * @method     ChildCouponQuery groupBySerializedConditions() Group by the serialized_conditions column
 * @method     ChildCouponQuery groupByPerCustomerUsageCount() Group by the per_customer_usage_count column
 * @method     ChildCouponQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildCouponQuery groupByUpdatedAt() Group by the updated_at column
 * @method     ChildCouponQuery groupByVersion() Group by the version column
 * @method     ChildCouponQuery groupByVersionCreatedAt() Group by the version_created_at column
 * @method     ChildCouponQuery groupByVersionCreatedBy() Group by the version_created_by column
 *
 * @method     ChildCouponQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildCouponQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildCouponQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildCouponQuery leftJoinCouponCountry($relationAlias = null) Adds a LEFT JOIN clause to the query using the CouponCountry relation
 * @method     ChildCouponQuery rightJoinCouponCountry($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CouponCountry relation
 * @method     ChildCouponQuery innerJoinCouponCountry($relationAlias = null) Adds a INNER JOIN clause to the query using the CouponCountry relation
 *
 * @method     ChildCouponQuery leftJoinCouponModule($relationAlias = null) Adds a LEFT JOIN clause to the query using the CouponModule relation
 * @method     ChildCouponQuery rightJoinCouponModule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CouponModule relation
 * @method     ChildCouponQuery innerJoinCouponModule($relationAlias = null) Adds a INNER JOIN clause to the query using the CouponModule relation
 *
 * @method     ChildCouponQuery leftJoinCouponCustomerCount($relationAlias = null) Adds a LEFT JOIN clause to the query using the CouponCustomerCount relation
 * @method     ChildCouponQuery rightJoinCouponCustomerCount($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CouponCustomerCount relation
 * @method     ChildCouponQuery innerJoinCouponCustomerCount($relationAlias = null) Adds a INNER JOIN clause to the query using the CouponCustomerCount relation
 *
 * @method     ChildCouponQuery leftJoinCouponI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the CouponI18n relation
 * @method     ChildCouponQuery rightJoinCouponI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CouponI18n relation
 * @method     ChildCouponQuery innerJoinCouponI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the CouponI18n relation
 *
 * @method     ChildCouponQuery leftJoinCouponVersion($relationAlias = null) Adds a LEFT JOIN clause to the query using the CouponVersion relation
 * @method     ChildCouponQuery rightJoinCouponVersion($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CouponVersion relation
 * @method     ChildCouponQuery innerJoinCouponVersion($relationAlias = null) Adds a INNER JOIN clause to the query using the CouponVersion relation
 *
 * @method     ChildCoupon findOne(ConnectionInterface $con = null) Return the first ChildCoupon matching the query
 * @method     ChildCoupon findOneOrCreate(ConnectionInterface $con = null) Return the first ChildCoupon matching the query, or a new ChildCoupon object populated from the query conditions when no match is found
 *
 * @method     ChildCoupon findOneById(int $id) Return the first ChildCoupon filtered by the id column
 * @method     ChildCoupon findOneByCode(string $code) Return the first ChildCoupon filtered by the code column
 * @method     ChildCoupon findOneByType(string $type) Return the first ChildCoupon filtered by the type column
 * @method     ChildCoupon findOneBySerializedEffects(string $serialized_effects) Return the first ChildCoupon filtered by the serialized_effects column
 * @method     ChildCoupon findOneByIsEnabled(boolean $is_enabled) Return the first ChildCoupon filtered by the is_enabled column
 * @method     ChildCoupon findOneByStartDate(string $start_date) Return the first ChildCoupon filtered by the start_date column
 * @method     ChildCoupon findOneByExpirationDate(string $expiration_date) Return the first ChildCoupon filtered by the expiration_date column
 * @method     ChildCoupon findOneByMaxUsage(int $max_usage) Return the first ChildCoupon filtered by the max_usage column
 * @method     ChildCoupon findOneByIsCumulative(boolean $is_cumulative) Return the first ChildCoupon filtered by the is_cumulative column
 * @method     ChildCoupon findOneByIsRemovingPostage(boolean $is_removing_postage) Return the first ChildCoupon filtered by the is_removing_postage column
 * @method     ChildCoupon findOneByIsAvailableOnSpecialOffers(boolean $is_available_on_special_offers) Return the first ChildCoupon filtered by the is_available_on_special_offers column
 * @method     ChildCoupon findOneByIsUsed(boolean $is_used) Return the first ChildCoupon filtered by the is_used column
 * @method     ChildCoupon findOneBySerializedConditions(string $serialized_conditions) Return the first ChildCoupon filtered by the serialized_conditions column
 * @method     ChildCoupon findOneByPerCustomerUsageCount(boolean $per_customer_usage_count) Return the first ChildCoupon filtered by the per_customer_usage_count column
 * @method     ChildCoupon findOneByCreatedAt(string $created_at) Return the first ChildCoupon filtered by the created_at column
 * @method     ChildCoupon findOneByUpdatedAt(string $updated_at) Return the first ChildCoupon filtered by the updated_at column
 * @method     ChildCoupon findOneByVersion(int $version) Return the first ChildCoupon filtered by the version column
 * @method     ChildCoupon findOneByVersionCreatedAt(string $version_created_at) Return the first ChildCoupon filtered by the version_created_at column
 * @method     ChildCoupon findOneByVersionCreatedBy(string $version_created_by) Return the first ChildCoupon filtered by the version_created_by column
 *
 * @method     array findById(int $id) Return ChildCoupon objects filtered by the id column
 * @method     array findByCode(string $code) Return ChildCoupon objects filtered by the code column
 * @method     array findByType(string $type) Return ChildCoupon objects filtered by the type column
 * @method     array findBySerializedEffects(string $serialized_effects) Return ChildCoupon objects filtered by the serialized_effects column
 * @method     array findByIsEnabled(boolean $is_enabled) Return ChildCoupon objects filtered by the is_enabled column
 * @method     array findByStartDate(string $start_date) Return ChildCoupon objects filtered by the start_date column
 * @method     array findByExpirationDate(string $expiration_date) Return ChildCoupon objects filtered by the expiration_date column
 * @method     array findByMaxUsage(int $max_usage) Return ChildCoupon objects filtered by the max_usage column
 * @method     array findByIsCumulative(boolean $is_cumulative) Return ChildCoupon objects filtered by the is_cumulative column
 * @method     array findByIsRemovingPostage(boolean $is_removing_postage) Return ChildCoupon objects filtered by the is_removing_postage column
 * @method     array findByIsAvailableOnSpecialOffers(boolean $is_available_on_special_offers) Return ChildCoupon objects filtered by the is_available_on_special_offers column
 * @method     array findByIsUsed(boolean $is_used) Return ChildCoupon objects filtered by the is_used column
 * @method     array findBySerializedConditions(string $serialized_conditions) Return ChildCoupon objects filtered by the serialized_conditions column
 * @method     array findByPerCustomerUsageCount(boolean $per_customer_usage_count) Return ChildCoupon objects filtered by the per_customer_usage_count column
 * @method     array findByCreatedAt(string $created_at) Return ChildCoupon objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildCoupon objects filtered by the updated_at column
 * @method     array findByVersion(int $version) Return ChildCoupon objects filtered by the version column
 * @method     array findByVersionCreatedAt(string $version_created_at) Return ChildCoupon objects filtered by the version_created_at column
 * @method     array findByVersionCreatedBy(string $version_created_by) Return ChildCoupon objects filtered by the version_created_by column
 *
 */
abstract class CouponQuery extends ModelCriteria
{

    // versionable behavior

    /**
     * Whether the versioning is enabled
     */
    static $isVersioningEnabled = true;

    /**
     * Initializes internal state of \Thelia\Model\Base\CouponQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Coupon', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildCouponQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildCouponQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\CouponQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\CouponQuery();
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
     * @return ChildCoupon|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = CouponTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(CouponTableMap::DATABASE_NAME);
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
     * @return   ChildCoupon A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `CODE`, `TYPE`, `SERIALIZED_EFFECTS`, `IS_ENABLED`, `START_DATE`, `EXPIRATION_DATE`, `MAX_USAGE`, `IS_CUMULATIVE`, `IS_REMOVING_POSTAGE`, `IS_AVAILABLE_ON_SPECIAL_OFFERS`, `IS_USED`, `SERIALIZED_CONDITIONS`, `PER_CUSTOMER_USAGE_COUNT`, `CREATED_AT`, `UPDATED_AT`, `VERSION`, `VERSION_CREATED_AT`, `VERSION_CREATED_BY` FROM `coupon` WHERE `ID` = :p0';
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
            $obj = new ChildCoupon();
            $obj->hydrate($row);
            CouponTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildCoupon|array|mixed the result, formatted by the current formatter
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
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(CouponTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(CouponTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(CouponTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(CouponTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponTableMap::ID, $id, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CouponTableMap::CODE, $code, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CouponTableMap::TYPE, $type, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CouponTableMap::SERIALIZED_EFFECTS, $serializedEffects, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByIsEnabled($isEnabled = null, $comparison = null)
    {
        if (is_string($isEnabled)) {
            $is_enabled = in_array(strtolower($isEnabled), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(CouponTableMap::IS_ENABLED, $isEnabled, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByStartDate($startDate = null, $comparison = null)
    {
        if (is_array($startDate)) {
            $useMinMax = false;
            if (isset($startDate['min'])) {
                $this->addUsingAlias(CouponTableMap::START_DATE, $startDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($startDate['max'])) {
                $this->addUsingAlias(CouponTableMap::START_DATE, $startDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponTableMap::START_DATE, $startDate, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByExpirationDate($expirationDate = null, $comparison = null)
    {
        if (is_array($expirationDate)) {
            $useMinMax = false;
            if (isset($expirationDate['min'])) {
                $this->addUsingAlias(CouponTableMap::EXPIRATION_DATE, $expirationDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($expirationDate['max'])) {
                $this->addUsingAlias(CouponTableMap::EXPIRATION_DATE, $expirationDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponTableMap::EXPIRATION_DATE, $expirationDate, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByMaxUsage($maxUsage = null, $comparison = null)
    {
        if (is_array($maxUsage)) {
            $useMinMax = false;
            if (isset($maxUsage['min'])) {
                $this->addUsingAlias(CouponTableMap::MAX_USAGE, $maxUsage['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($maxUsage['max'])) {
                $this->addUsingAlias(CouponTableMap::MAX_USAGE, $maxUsage['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponTableMap::MAX_USAGE, $maxUsage, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByIsCumulative($isCumulative = null, $comparison = null)
    {
        if (is_string($isCumulative)) {
            $is_cumulative = in_array(strtolower($isCumulative), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(CouponTableMap::IS_CUMULATIVE, $isCumulative, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByIsRemovingPostage($isRemovingPostage = null, $comparison = null)
    {
        if (is_string($isRemovingPostage)) {
            $is_removing_postage = in_array(strtolower($isRemovingPostage), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(CouponTableMap::IS_REMOVING_POSTAGE, $isRemovingPostage, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByIsAvailableOnSpecialOffers($isAvailableOnSpecialOffers = null, $comparison = null)
    {
        if (is_string($isAvailableOnSpecialOffers)) {
            $is_available_on_special_offers = in_array(strtolower($isAvailableOnSpecialOffers), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(CouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS, $isAvailableOnSpecialOffers, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByIsUsed($isUsed = null, $comparison = null)
    {
        if (is_string($isUsed)) {
            $is_used = in_array(strtolower($isUsed), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(CouponTableMap::IS_USED, $isUsed, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CouponTableMap::SERIALIZED_CONDITIONS, $serializedConditions, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByPerCustomerUsageCount($perCustomerUsageCount = null, $comparison = null)
    {
        if (is_string($perCustomerUsageCount)) {
            $per_customer_usage_count = in_array(strtolower($perCustomerUsageCount), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(CouponTableMap::PER_CUSTOMER_USAGE_COUNT, $perCustomerUsageCount, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(CouponTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(CouponTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(CouponTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(CouponTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponTableMap::UPDATED_AT, $updatedAt, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByVersion($version = null, $comparison = null)
    {
        if (is_array($version)) {
            $useMinMax = false;
            if (isset($version['min'])) {
                $this->addUsingAlias(CouponTableMap::VERSION, $version['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($version['max'])) {
                $this->addUsingAlias(CouponTableMap::VERSION, $version['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponTableMap::VERSION, $version, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByVersionCreatedAt($versionCreatedAt = null, $comparison = null)
    {
        if (is_array($versionCreatedAt)) {
            $useMinMax = false;
            if (isset($versionCreatedAt['min'])) {
                $this->addUsingAlias(CouponTableMap::VERSION_CREATED_AT, $versionCreatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($versionCreatedAt['max'])) {
                $this->addUsingAlias(CouponTableMap::VERSION_CREATED_AT, $versionCreatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponTableMap::VERSION_CREATED_AT, $versionCreatedAt, $comparison);
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
     * @return ChildCouponQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CouponTableMap::VERSION_CREATED_BY, $versionCreatedBy, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\CouponCountry object
     *
     * @param \Thelia\Model\CouponCountry|ObjectCollection $couponCountry  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByCouponCountry($couponCountry, $comparison = null)
    {
        if ($couponCountry instanceof \Thelia\Model\CouponCountry) {
            return $this
                ->addUsingAlias(CouponTableMap::ID, $couponCountry->getCouponId(), $comparison);
        } elseif ($couponCountry instanceof ObjectCollection) {
            return $this
                ->useCouponCountryQuery()
                ->filterByPrimaryKeys($couponCountry->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCouponCountry() only accepts arguments of type \Thelia\Model\CouponCountry or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CouponCountry relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function joinCouponCountry($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CouponCountry');

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
            $this->addJoinObject($join, 'CouponCountry');
        }

        return $this;
    }

    /**
     * Use the CouponCountry relation CouponCountry object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CouponCountryQuery A secondary query class using the current class as primary query
     */
    public function useCouponCountryQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCouponCountry($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CouponCountry', '\Thelia\Model\CouponCountryQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\CouponModule object
     *
     * @param \Thelia\Model\CouponModule|ObjectCollection $couponModule  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByCouponModule($couponModule, $comparison = null)
    {
        if ($couponModule instanceof \Thelia\Model\CouponModule) {
            return $this
                ->addUsingAlias(CouponTableMap::ID, $couponModule->getCouponId(), $comparison);
        } elseif ($couponModule instanceof ObjectCollection) {
            return $this
                ->useCouponModuleQuery()
                ->filterByPrimaryKeys($couponModule->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCouponModule() only accepts arguments of type \Thelia\Model\CouponModule or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CouponModule relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function joinCouponModule($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CouponModule');

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
            $this->addJoinObject($join, 'CouponModule');
        }

        return $this;
    }

    /**
     * Use the CouponModule relation CouponModule object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CouponModuleQuery A secondary query class using the current class as primary query
     */
    public function useCouponModuleQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCouponModule($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CouponModule', '\Thelia\Model\CouponModuleQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\CouponCustomerCount object
     *
     * @param \Thelia\Model\CouponCustomerCount|ObjectCollection $couponCustomerCount  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByCouponCustomerCount($couponCustomerCount, $comparison = null)
    {
        if ($couponCustomerCount instanceof \Thelia\Model\CouponCustomerCount) {
            return $this
                ->addUsingAlias(CouponTableMap::ID, $couponCustomerCount->getCouponId(), $comparison);
        } elseif ($couponCustomerCount instanceof ObjectCollection) {
            return $this
                ->useCouponCustomerCountQuery()
                ->filterByPrimaryKeys($couponCustomerCount->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCouponCustomerCount() only accepts arguments of type \Thelia\Model\CouponCustomerCount or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CouponCustomerCount relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function joinCouponCustomerCount($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CouponCustomerCount');

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
            $this->addJoinObject($join, 'CouponCustomerCount');
        }

        return $this;
    }

    /**
     * Use the CouponCustomerCount relation CouponCustomerCount object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CouponCustomerCountQuery A secondary query class using the current class as primary query
     */
    public function useCouponCustomerCountQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCouponCustomerCount($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CouponCustomerCount', '\Thelia\Model\CouponCustomerCountQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\CouponI18n object
     *
     * @param \Thelia\Model\CouponI18n|ObjectCollection $couponI18n  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByCouponI18n($couponI18n, $comparison = null)
    {
        if ($couponI18n instanceof \Thelia\Model\CouponI18n) {
            return $this
                ->addUsingAlias(CouponTableMap::ID, $couponI18n->getId(), $comparison);
        } elseif ($couponI18n instanceof ObjectCollection) {
            return $this
                ->useCouponI18nQuery()
                ->filterByPrimaryKeys($couponI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCouponI18n() only accepts arguments of type \Thelia\Model\CouponI18n or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CouponI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function joinCouponI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CouponI18n');

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
            $this->addJoinObject($join, 'CouponI18n');
        }

        return $this;
    }

    /**
     * Use the CouponI18n relation CouponI18n object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CouponI18nQuery A secondary query class using the current class as primary query
     */
    public function useCouponI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinCouponI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CouponI18n', '\Thelia\Model\CouponI18nQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\CouponVersion object
     *
     * @param \Thelia\Model\CouponVersion|ObjectCollection $couponVersion  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByCouponVersion($couponVersion, $comparison = null)
    {
        if ($couponVersion instanceof \Thelia\Model\CouponVersion) {
            return $this
                ->addUsingAlias(CouponTableMap::ID, $couponVersion->getId(), $comparison);
        } elseif ($couponVersion instanceof ObjectCollection) {
            return $this
                ->useCouponVersionQuery()
                ->filterByPrimaryKeys($couponVersion->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCouponVersion() only accepts arguments of type \Thelia\Model\CouponVersion or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CouponVersion relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function joinCouponVersion($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CouponVersion');

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
            $this->addJoinObject($join, 'CouponVersion');
        }

        return $this;
    }

    /**
     * Use the CouponVersion relation CouponVersion object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CouponVersionQuery A secondary query class using the current class as primary query
     */
    public function useCouponVersionQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCouponVersion($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CouponVersion', '\Thelia\Model\CouponVersionQuery');
    }

    /**
     * Filter the query by a related Country object
     * using the coupon_country table as cross reference
     *
     * @param Country $country the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByCountry($country, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useCouponCountryQuery()
            ->filterByCountry($country, $comparison)
            ->endUse();
    }

    /**
     * Filter the query by a related Module object
     * using the coupon_module table as cross reference
     *
     * @param Module $module the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByModule($module, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useCouponModuleQuery()
            ->filterByModule($module, $comparison)
            ->endUse();
    }

    /**
     * Filter the query by a related Customer object
     * using the coupon_customer_count table as cross reference
     *
     * @param Customer $customer the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function filterByCustomer($customer, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useCouponCustomerCountQuery()
            ->filterByCustomer($customer, $comparison)
            ->endUse();
    }

    /**
     * Exclude object from result
     *
     * @param   ChildCoupon $coupon Object to remove from the list of results
     *
     * @return ChildCouponQuery The current query, for fluid interface
     */
    public function prune($coupon = null)
    {
        if ($coupon) {
            $this->addUsingAlias(CouponTableMap::ID, $coupon->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the coupon table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CouponTableMap::DATABASE_NAME);
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
            CouponTableMap::clearInstancePool();
            CouponTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildCoupon or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildCoupon object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(CouponTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(CouponTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        CouponTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            CouponTableMap::clearRelatedInstancePool();
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
     * @return     ChildCouponQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(CouponTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildCouponQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(CouponTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildCouponQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(CouponTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildCouponQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(CouponTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildCouponQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(CouponTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildCouponQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(CouponTableMap::CREATED_AT);
    }

    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildCouponQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'CouponI18n';

        return $this
            ->joinCouponI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildCouponQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('CouponI18n');
        $this->with['CouponI18n']->setIsWithOneToMany(false);

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
     * @return    ChildCouponI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CouponI18n', '\Thelia\Model\CouponI18nQuery');
    }

    // versionable behavior

    /**
     * Checks whether versioning is enabled
     *
     * @return boolean
     */
    static public function isVersioningEnabled()
    {
        return self::$isVersioningEnabled;
    }

    /**
     * Enables versioning
     */
    static public function enableVersioning()
    {
        self::$isVersioningEnabled = true;
    }

    /**
     * Disables versioning
     */
    static public function disableVersioning()
    {
        self::$isVersioningEnabled = false;
    }

} // CouponQuery
