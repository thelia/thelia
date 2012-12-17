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
use Thelia\Model\CouponRule;
use Thelia\Model\CouponRulePeer;
use Thelia\Model\CouponRuleQuery;

/**
 * Base class that represents a query for the 'coupon_rule' table.
 *
 *
 *
 * @method CouponRuleQuery orderById($order = Criteria::ASC) Order by the id column
 * @method CouponRuleQuery orderByCouponId($order = Criteria::ASC) Order by the coupon_id column
 * @method CouponRuleQuery orderByController($order = Criteria::ASC) Order by the controller column
 * @method CouponRuleQuery orderByOperation($order = Criteria::ASC) Order by the operation column
 * @method CouponRuleQuery orderByValue($order = Criteria::ASC) Order by the value column
 * @method CouponRuleQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method CouponRuleQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method CouponRuleQuery groupById() Group by the id column
 * @method CouponRuleQuery groupByCouponId() Group by the coupon_id column
 * @method CouponRuleQuery groupByController() Group by the controller column
 * @method CouponRuleQuery groupByOperation() Group by the operation column
 * @method CouponRuleQuery groupByValue() Group by the value column
 * @method CouponRuleQuery groupByCreatedAt() Group by the created_at column
 * @method CouponRuleQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method CouponRuleQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method CouponRuleQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method CouponRuleQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method CouponRuleQuery leftJoinCoupon($relationAlias = null) Adds a LEFT JOIN clause to the query using the Coupon relation
 * @method CouponRuleQuery rightJoinCoupon($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Coupon relation
 * @method CouponRuleQuery innerJoinCoupon($relationAlias = null) Adds a INNER JOIN clause to the query using the Coupon relation
 *
 * @method CouponRule findOne(PropelPDO $con = null) Return the first CouponRule matching the query
 * @method CouponRule findOneOrCreate(PropelPDO $con = null) Return the first CouponRule matching the query, or a new CouponRule object populated from the query conditions when no match is found
 *
 * @method CouponRule findOneById(int $id) Return the first CouponRule filtered by the id column
 * @method CouponRule findOneByCouponId(int $coupon_id) Return the first CouponRule filtered by the coupon_id column
 * @method CouponRule findOneByController(string $controller) Return the first CouponRule filtered by the controller column
 * @method CouponRule findOneByOperation(string $operation) Return the first CouponRule filtered by the operation column
 * @method CouponRule findOneByValue(double $value) Return the first CouponRule filtered by the value column
 * @method CouponRule findOneByCreatedAt(string $created_at) Return the first CouponRule filtered by the created_at column
 * @method CouponRule findOneByUpdatedAt(string $updated_at) Return the first CouponRule filtered by the updated_at column
 *
 * @method array findById(int $id) Return CouponRule objects filtered by the id column
 * @method array findByCouponId(int $coupon_id) Return CouponRule objects filtered by the coupon_id column
 * @method array findByController(string $controller) Return CouponRule objects filtered by the controller column
 * @method array findByOperation(string $operation) Return CouponRule objects filtered by the operation column
 * @method array findByValue(double $value) Return CouponRule objects filtered by the value column
 * @method array findByCreatedAt(string $created_at) Return CouponRule objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return CouponRule objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseCouponRuleQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseCouponRuleQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\CouponRule', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new CouponRuleQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     CouponRuleQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return CouponRuleQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof CouponRuleQuery) {
            return $criteria;
        }
        $query = new CouponRuleQuery();
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
     * @return   CouponRule|CouponRule[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = CouponRulePeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(CouponRulePeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return   CouponRule A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `COUPON_ID`, `CONTROLLER`, `OPERATION`, `VALUE`, `CREATED_AT`, `UPDATED_AT` FROM `coupon_rule` WHERE `ID` = :p0';
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
            $obj = new CouponRule();
            $obj->hydrate($row);
            CouponRulePeer::addInstanceToPool($obj, (string) $key);
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
     * @return CouponRule|CouponRule[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|CouponRule[]|mixed the list of results, formatted by the current formatter
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
     * @return CouponRuleQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(CouponRulePeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return CouponRuleQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(CouponRulePeer::ID, $keys, Criteria::IN);
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
     * @return CouponRuleQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(CouponRulePeer::ID, $id, $comparison);
    }

    /**
     * Filter the query on the coupon_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCouponId(1234); // WHERE coupon_id = 1234
     * $query->filterByCouponId(array(12, 34)); // WHERE coupon_id IN (12, 34)
     * $query->filterByCouponId(array('min' => 12)); // WHERE coupon_id > 12
     * </code>
     *
     * @see       filterByCoupon()
     *
     * @param     mixed $couponId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CouponRuleQuery The current query, for fluid interface
     */
    public function filterByCouponId($couponId = null, $comparison = null)
    {
        if (is_array($couponId)) {
            $useMinMax = false;
            if (isset($couponId['min'])) {
                $this->addUsingAlias(CouponRulePeer::COUPON_ID, $couponId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($couponId['max'])) {
                $this->addUsingAlias(CouponRulePeer::COUPON_ID, $couponId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponRulePeer::COUPON_ID, $couponId, $comparison);
    }

    /**
     * Filter the query on the controller column
     *
     * Example usage:
     * <code>
     * $query->filterByController('fooValue');   // WHERE controller = 'fooValue'
     * $query->filterByController('%fooValue%'); // WHERE controller LIKE '%fooValue%'
     * </code>
     *
     * @param     string $controller The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CouponRuleQuery The current query, for fluid interface
     */
    public function filterByController($controller = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($controller)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $controller)) {
                $controller = str_replace('*', '%', $controller);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CouponRulePeer::CONTROLLER, $controller, $comparison);
    }

    /**
     * Filter the query on the operation column
     *
     * Example usage:
     * <code>
     * $query->filterByOperation('fooValue');   // WHERE operation = 'fooValue'
     * $query->filterByOperation('%fooValue%'); // WHERE operation LIKE '%fooValue%'
     * </code>
     *
     * @param     string $operation The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CouponRuleQuery The current query, for fluid interface
     */
    public function filterByOperation($operation = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($operation)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $operation)) {
                $operation = str_replace('*', '%', $operation);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CouponRulePeer::OPERATION, $operation, $comparison);
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
     * @return CouponRuleQuery The current query, for fluid interface
     */
    public function filterByValue($value = null, $comparison = null)
    {
        if (is_array($value)) {
            $useMinMax = false;
            if (isset($value['min'])) {
                $this->addUsingAlias(CouponRulePeer::VALUE, $value['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($value['max'])) {
                $this->addUsingAlias(CouponRulePeer::VALUE, $value['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponRulePeer::VALUE, $value, $comparison);
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
     * @return CouponRuleQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(CouponRulePeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(CouponRulePeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponRulePeer::CREATED_AT, $createdAt, $comparison);
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
     * @return CouponRuleQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(CouponRulePeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(CouponRulePeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponRulePeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related Coupon object
     *
     * @param   Coupon|PropelObjectCollection $coupon The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   CouponRuleQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByCoupon($coupon, $comparison = null)
    {
        if ($coupon instanceof Coupon) {
            return $this
                ->addUsingAlias(CouponRulePeer::COUPON_ID, $coupon->getId(), $comparison);
        } elseif ($coupon instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(CouponRulePeer::COUPON_ID, $coupon->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByCoupon() only accepts arguments of type Coupon or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Coupon relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return CouponRuleQuery The current query, for fluid interface
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
     * @see       useQuery()
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
     * @param   CouponRule $couponRule Object to remove from the list of results
     *
     * @return CouponRuleQuery The current query, for fluid interface
     */
    public function prune($couponRule = null)
    {
        if ($couponRule) {
            $this->addUsingAlias(CouponRulePeer::ID, $couponRule->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

}
