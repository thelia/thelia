<?php

namespace Thelia\Model\om;

use \Criteria;
use \Exception;
use \ModelCriteria;
use \ModelJoin;
use \PDO;
use \Propel;
use \PropelCollection;
use \PropelException;
use \PropelObjectCollection;
use \PropelPDO;
use Thelia\Model\Coupon;
use Thelia\Model\CouponPeer;
use Thelia\Model\CouponQuery;
use Thelia\Model\CouponRule;

/**
 * Base class that represents a query for the 'coupon' table.
 *
 *
 *
 * @method CouponQuery orderById($order = Criteria::ASC) Order by the id column
 * @method CouponQuery orderByCode($order = Criteria::ASC) Order by the code column
 * @method CouponQuery orderByAction($order = Criteria::ASC) Order by the action column
 * @method CouponQuery orderByValue($order = Criteria::ASC) Order by the value column
 * @method CouponQuery orderByUsed($order = Criteria::ASC) Order by the used column
 * @method CouponQuery orderByAvailableSince($order = Criteria::ASC) Order by the available_since column
 * @method CouponQuery orderByDateLimit($order = Criteria::ASC) Order by the date_limit column
 * @method CouponQuery orderByActivate($order = Criteria::ASC) Order by the activate column
 * @method CouponQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method CouponQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method CouponQuery groupById() Group by the id column
 * @method CouponQuery groupByCode() Group by the code column
 * @method CouponQuery groupByAction() Group by the action column
 * @method CouponQuery groupByValue() Group by the value column
 * @method CouponQuery groupByUsed() Group by the used column
 * @method CouponQuery groupByAvailableSince() Group by the available_since column
 * @method CouponQuery groupByDateLimit() Group by the date_limit column
 * @method CouponQuery groupByActivate() Group by the activate column
 * @method CouponQuery groupByCreatedAt() Group by the created_at column
 * @method CouponQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method CouponQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method CouponQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method CouponQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method CouponQuery leftJoinCouponRule($relationAlias = null) Adds a LEFT JOIN clause to the query using the CouponRule relation
 * @method CouponQuery rightJoinCouponRule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CouponRule relation
 * @method CouponQuery innerJoinCouponRule($relationAlias = null) Adds a INNER JOIN clause to the query using the CouponRule relation
 *
 * @method Coupon findOne(PropelPDO $con = null) Return the first Coupon matching the query
 * @method Coupon findOneOrCreate(PropelPDO $con = null) Return the first Coupon matching the query, or a new Coupon object populated from the query conditions when no match is found
 *
 * @method Coupon findOneById(int $id) Return the first Coupon filtered by the id column
 * @method Coupon findOneByCode(string $code) Return the first Coupon filtered by the code column
 * @method Coupon findOneByAction(string $action) Return the first Coupon filtered by the action column
 * @method Coupon findOneByValue(double $value) Return the first Coupon filtered by the value column
 * @method Coupon findOneByUsed(int $used) Return the first Coupon filtered by the used column
 * @method Coupon findOneByAvailableSince(string $available_since) Return the first Coupon filtered by the available_since column
 * @method Coupon findOneByDateLimit(string $date_limit) Return the first Coupon filtered by the date_limit column
 * @method Coupon findOneByActivate(int $activate) Return the first Coupon filtered by the activate column
 * @method Coupon findOneByCreatedAt(string $created_at) Return the first Coupon filtered by the created_at column
 * @method Coupon findOneByUpdatedAt(string $updated_at) Return the first Coupon filtered by the updated_at column
 *
 * @method array findById(int $id) Return Coupon objects filtered by the id column
 * @method array findByCode(string $code) Return Coupon objects filtered by the code column
 * @method array findByAction(string $action) Return Coupon objects filtered by the action column
 * @method array findByValue(double $value) Return Coupon objects filtered by the value column
 * @method array findByUsed(int $used) Return Coupon objects filtered by the used column
 * @method array findByAvailableSince(string $available_since) Return Coupon objects filtered by the available_since column
 * @method array findByDateLimit(string $date_limit) Return Coupon objects filtered by the date_limit column
 * @method array findByActivate(int $activate) Return Coupon objects filtered by the activate column
 * @method array findByCreatedAt(string $created_at) Return Coupon objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return Coupon objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseCouponQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseCouponQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\Coupon', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new CouponQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     CouponQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return CouponQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof CouponQuery) {
            return $criteria;
        }
        $query = new CouponQuery();
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
     * @param     PropelPDO $con an optional connection object
     *
     * @return   Coupon|Coupon[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = CouponPeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(CouponPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @param     PropelPDO $con A connection object
     *
     * @return   Coupon A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `CODE`, `ACTION`, `VALUE`, `USED`, `AVAILABLE_SINCE`, `DATE_LIMIT`, `ACTIVATE`, `CREATED_AT`, `UPDATED_AT` FROM `coupon` WHERE `ID` = :p0';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $obj = new Coupon();
            $obj->hydrate($row);
            CouponPeer::addInstanceToPool($obj, (string) $key);
        }
        $stmt->closeCursor();

        return $obj;
    }

    /**
     * Find object by primary key.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     PropelPDO $con A connection object
     *
     * @return Coupon|Coupon[]|mixed the result, formatted by the current formatter
     */
    protected function findPkComplex($key, $con)
    {
        // As the query uses a PK condition, no limit(1) is necessary.
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $stmt = $criteria
            ->filterByPrimaryKey($key)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->formatOne($stmt);
    }

    /**
     * Find objects by primary key
     * <code>
     * $objs = $c->findPks(array(12, 56, 832), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     PropelPDO $con an optional connection object
     *
     * @return PropelObjectCollection|Coupon[]|mixed the list of results, formatted by the current formatter
     */
    public function findPks($keys, $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection($this->getDbName(), Propel::CONNECTION_READ);
        }
        $this->basePreSelect($con);
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $stmt = $criteria
            ->filterByPrimaryKeys($keys)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->format($stmt);
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return CouponQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(CouponPeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return CouponQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(CouponPeer::ID, $keys, Criteria::IN);
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
     * @return CouponQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(CouponPeer::ID, $id, $comparison);
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
     * @return CouponQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CouponPeer::CODE, $code, $comparison);
    }

    /**
     * Filter the query on the action column
     *
     * Example usage:
     * <code>
     * $query->filterByAction('fooValue');   // WHERE action = 'fooValue'
     * $query->filterByAction('%fooValue%'); // WHERE action LIKE '%fooValue%'
     * </code>
     *
     * @param     string $action The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CouponQuery The current query, for fluid interface
     */
    public function filterByAction($action = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($action)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $action)) {
                $action = str_replace('*', '%', $action);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CouponPeer::ACTION, $action, $comparison);
    }

    /**
     * Filter the query on the value column
     *
     * Example usage:
     * <code>
     * $query->filterByValue(1234); // WHERE value = 1234
     * $query->filterByValue(array(12, 34)); // WHERE value IN (12, 34)
     * $query->filterByValue(array('min' => 12)); // WHERE value > 12
     * </code>
     *
     * @param     mixed $value The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CouponQuery The current query, for fluid interface
     */
    public function filterByValue($value = null, $comparison = null)
    {
        if (is_array($value)) {
            $useMinMax = false;
            if (isset($value['min'])) {
                $this->addUsingAlias(CouponPeer::VALUE, $value['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($value['max'])) {
                $this->addUsingAlias(CouponPeer::VALUE, $value['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponPeer::VALUE, $value, $comparison);
    }

    /**
     * Filter the query on the used column
     *
     * Example usage:
     * <code>
     * $query->filterByUsed(1234); // WHERE used = 1234
     * $query->filterByUsed(array(12, 34)); // WHERE used IN (12, 34)
     * $query->filterByUsed(array('min' => 12)); // WHERE used > 12
     * </code>
     *
     * @param     mixed $used The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CouponQuery The current query, for fluid interface
     */
    public function filterByUsed($used = null, $comparison = null)
    {
        if (is_array($used)) {
            $useMinMax = false;
            if (isset($used['min'])) {
                $this->addUsingAlias(CouponPeer::USED, $used['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($used['max'])) {
                $this->addUsingAlias(CouponPeer::USED, $used['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponPeer::USED, $used, $comparison);
    }

    /**
     * Filter the query on the available_since column
     *
     * Example usage:
     * <code>
     * $query->filterByAvailableSince('2011-03-14'); // WHERE available_since = '2011-03-14'
     * $query->filterByAvailableSince('now'); // WHERE available_since = '2011-03-14'
     * $query->filterByAvailableSince(array('max' => 'yesterday')); // WHERE available_since > '2011-03-13'
     * </code>
     *
     * @param     mixed $availableSince The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CouponQuery The current query, for fluid interface
     */
    public function filterByAvailableSince($availableSince = null, $comparison = null)
    {
        if (is_array($availableSince)) {
            $useMinMax = false;
            if (isset($availableSince['min'])) {
                $this->addUsingAlias(CouponPeer::AVAILABLE_SINCE, $availableSince['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($availableSince['max'])) {
                $this->addUsingAlias(CouponPeer::AVAILABLE_SINCE, $availableSince['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponPeer::AVAILABLE_SINCE, $availableSince, $comparison);
    }

    /**
     * Filter the query on the date_limit column
     *
     * Example usage:
     * <code>
     * $query->filterByDateLimit('2011-03-14'); // WHERE date_limit = '2011-03-14'
     * $query->filterByDateLimit('now'); // WHERE date_limit = '2011-03-14'
     * $query->filterByDateLimit(array('max' => 'yesterday')); // WHERE date_limit > '2011-03-13'
     * </code>
     *
     * @param     mixed $dateLimit The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CouponQuery The current query, for fluid interface
     */
    public function filterByDateLimit($dateLimit = null, $comparison = null)
    {
        if (is_array($dateLimit)) {
            $useMinMax = false;
            if (isset($dateLimit['min'])) {
                $this->addUsingAlias(CouponPeer::DATE_LIMIT, $dateLimit['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($dateLimit['max'])) {
                $this->addUsingAlias(CouponPeer::DATE_LIMIT, $dateLimit['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponPeer::DATE_LIMIT, $dateLimit, $comparison);
    }

    /**
     * Filter the query on the activate column
     *
     * Example usage:
     * <code>
     * $query->filterByActivate(1234); // WHERE activate = 1234
     * $query->filterByActivate(array(12, 34)); // WHERE activate IN (12, 34)
     * $query->filterByActivate(array('min' => 12)); // WHERE activate > 12
     * </code>
     *
     * @param     mixed $activate The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CouponQuery The current query, for fluid interface
     */
    public function filterByActivate($activate = null, $comparison = null)
    {
        if (is_array($activate)) {
            $useMinMax = false;
            if (isset($activate['min'])) {
                $this->addUsingAlias(CouponPeer::ACTIVATE, $activate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($activate['max'])) {
                $this->addUsingAlias(CouponPeer::ACTIVATE, $activate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponPeer::ACTIVATE, $activate, $comparison);
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
     * @return CouponQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(CouponPeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(CouponPeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponPeer::CREATED_AT, $createdAt, $comparison);
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
     * @return CouponQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(CouponPeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(CouponPeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponPeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related CouponRule object
     *
     * @param   CouponRule|PropelObjectCollection $couponRule  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   CouponQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByCouponRule($couponRule, $comparison = null)
    {
        if ($couponRule instanceof CouponRule) {
            return $this
                ->addUsingAlias(CouponPeer::ID, $couponRule->getCouponId(), $comparison);
        } elseif ($couponRule instanceof PropelObjectCollection) {
            return $this
                ->useCouponRuleQuery()
                ->filterByPrimaryKeys($couponRule->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCouponRule() only accepts arguments of type CouponRule or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CouponRule relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return CouponQuery The current query, for fluid interface
     */
    public function joinCouponRule($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CouponRule');

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
            $this->addJoinObject($join, 'CouponRule');
        }

        return $this;
    }

    /**
     * Use the CouponRule relation CouponRule object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CouponRuleQuery A secondary query class using the current class as primary query
     */
    public function useCouponRuleQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCouponRule($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CouponRule', '\Thelia\Model\CouponRuleQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   Coupon $coupon Object to remove from the list of results
     *
     * @return CouponQuery The current query, for fluid interface
     */
    public function prune($coupon = null)
    {
        if ($coupon) {
            $this->addUsingAlias(CouponPeer::ID, $coupon->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

}
