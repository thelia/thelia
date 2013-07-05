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
use Thelia\Model\CouponRule as ChildCouponRule;
use Thelia\Model\CouponRuleQuery as ChildCouponRuleQuery;
use Thelia\Model\Map\CouponRuleTableMap;

/**
 * Base class that represents a query for the 'coupon_rule' table.
 *
 *
 *
 * @method     ChildCouponRuleQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildCouponRuleQuery orderByCouponId($order = Criteria::ASC) Order by the coupon_id column
 * @method     ChildCouponRuleQuery orderByController($order = Criteria::ASC) Order by the controller column
 * @method     ChildCouponRuleQuery orderByOperation($order = Criteria::ASC) Order by the operation column
 * @method     ChildCouponRuleQuery orderByValue($order = Criteria::ASC) Order by the value column
 * @method     ChildCouponRuleQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildCouponRuleQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildCouponRuleQuery groupById() Group by the id column
 * @method     ChildCouponRuleQuery groupByCouponId() Group by the coupon_id column
 * @method     ChildCouponRuleQuery groupByController() Group by the controller column
 * @method     ChildCouponRuleQuery groupByOperation() Group by the operation column
 * @method     ChildCouponRuleQuery groupByValue() Group by the value column
 * @method     ChildCouponRuleQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildCouponRuleQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildCouponRuleQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildCouponRuleQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildCouponRuleQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildCouponRuleQuery leftJoinCoupon($relationAlias = null) Adds a LEFT JOIN clause to the query using the Coupon relation
 * @method     ChildCouponRuleQuery rightJoinCoupon($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Coupon relation
 * @method     ChildCouponRuleQuery innerJoinCoupon($relationAlias = null) Adds a INNER JOIN clause to the query using the Coupon relation
 *
 * @method     ChildCouponRule findOne(ConnectionInterface $con = null) Return the first ChildCouponRule matching the query
 * @method     ChildCouponRule findOneOrCreate(ConnectionInterface $con = null) Return the first ChildCouponRule matching the query, or a new ChildCouponRule object populated from the query conditions when no match is found
 *
 * @method     ChildCouponRule findOneById(int $id) Return the first ChildCouponRule filtered by the id column
 * @method     ChildCouponRule findOneByCouponId(int $coupon_id) Return the first ChildCouponRule filtered by the coupon_id column
 * @method     ChildCouponRule findOneByController(string $controller) Return the first ChildCouponRule filtered by the controller column
 * @method     ChildCouponRule findOneByOperation(string $operation) Return the first ChildCouponRule filtered by the operation column
 * @method     ChildCouponRule findOneByValue(double $value) Return the first ChildCouponRule filtered by the value column
 * @method     ChildCouponRule findOneByCreatedAt(string $created_at) Return the first ChildCouponRule filtered by the created_at column
 * @method     ChildCouponRule findOneByUpdatedAt(string $updated_at) Return the first ChildCouponRule filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildCouponRule objects filtered by the id column
 * @method     array findByCouponId(int $coupon_id) Return ChildCouponRule objects filtered by the coupon_id column
 * @method     array findByController(string $controller) Return ChildCouponRule objects filtered by the controller column
 * @method     array findByOperation(string $operation) Return ChildCouponRule objects filtered by the operation column
 * @method     array findByValue(double $value) Return ChildCouponRule objects filtered by the value column
 * @method     array findByCreatedAt(string $created_at) Return ChildCouponRule objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildCouponRule objects filtered by the updated_at column
 *
 */
abstract class CouponRuleQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\CouponRuleQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\CouponRule', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildCouponRuleQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildCouponRuleQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\CouponRuleQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\CouponRuleQuery();
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
     * @return ChildCouponRule|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = CouponRuleTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(CouponRuleTableMap::DATABASE_NAME);
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
     * @return   ChildCouponRule A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT ID, COUPON_ID, CONTROLLER, OPERATION, VALUE, CREATED_AT, UPDATED_AT FROM coupon_rule WHERE ID = :p0';
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
            $obj = new ChildCouponRule();
            $obj->hydrate($row);
            CouponRuleTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildCouponRule|array|mixed the result, formatted by the current formatter
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
     * @return ChildCouponRuleQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(CouponRuleTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildCouponRuleQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(CouponRuleTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildCouponRuleQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(CouponRuleTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(CouponRuleTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponRuleTableMap::ID, $id, $comparison);
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
     * @return ChildCouponRuleQuery The current query, for fluid interface
     */
    public function filterByCouponId($couponId = null, $comparison = null)
    {
        if (is_array($couponId)) {
            $useMinMax = false;
            if (isset($couponId['min'])) {
                $this->addUsingAlias(CouponRuleTableMap::COUPON_ID, $couponId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($couponId['max'])) {
                $this->addUsingAlias(CouponRuleTableMap::COUPON_ID, $couponId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponRuleTableMap::COUPON_ID, $couponId, $comparison);
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
     * @return ChildCouponRuleQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CouponRuleTableMap::CONTROLLER, $controller, $comparison);
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
     * @return ChildCouponRuleQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CouponRuleTableMap::OPERATION, $operation, $comparison);
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
     * @return ChildCouponRuleQuery The current query, for fluid interface
     */
    public function filterByValue($value = null, $comparison = null)
    {
        if (is_array($value)) {
            $useMinMax = false;
            if (isset($value['min'])) {
                $this->addUsingAlias(CouponRuleTableMap::VALUE, $value['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($value['max'])) {
                $this->addUsingAlias(CouponRuleTableMap::VALUE, $value['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponRuleTableMap::VALUE, $value, $comparison);
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
     * @return ChildCouponRuleQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(CouponRuleTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(CouponRuleTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponRuleTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildCouponRuleQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(CouponRuleTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(CouponRuleTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CouponRuleTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Coupon object
     *
     * @param \Thelia\Model\Coupon|ObjectCollection $coupon The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCouponRuleQuery The current query, for fluid interface
     */
    public function filterByCoupon($coupon, $comparison = null)
    {
        if ($coupon instanceof \Thelia\Model\Coupon) {
            return $this
                ->addUsingAlias(CouponRuleTableMap::COUPON_ID, $coupon->getId(), $comparison);
        } elseif ($coupon instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(CouponRuleTableMap::COUPON_ID, $coupon->toKeyValue('PrimaryKey', 'Id'), $comparison);
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
     * @return ChildCouponRuleQuery The current query, for fluid interface
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
     * @param   ChildCouponRule $couponRule Object to remove from the list of results
     *
     * @return ChildCouponRuleQuery The current query, for fluid interface
     */
    public function prune($couponRule = null)
    {
        if ($couponRule) {
            $this->addUsingAlias(CouponRuleTableMap::ID, $couponRule->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the coupon_rule table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CouponRuleTableMap::DATABASE_NAME);
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
            CouponRuleTableMap::clearInstancePool();
            CouponRuleTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildCouponRule or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildCouponRule object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(CouponRuleTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(CouponRuleTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        CouponRuleTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            CouponRuleTableMap::clearRelatedInstancePool();
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
     * @return     ChildCouponRuleQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(CouponRuleTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildCouponRuleQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(CouponRuleTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildCouponRuleQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(CouponRuleTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildCouponRuleQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(CouponRuleTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildCouponRuleQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(CouponRuleTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildCouponRuleQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(CouponRuleTableMap::CREATED_AT);
    }

} // CouponRuleQuery
