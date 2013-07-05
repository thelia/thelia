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
use Thelia\Model\OrderFeature as ChildOrderFeature;
use Thelia\Model\OrderFeatureQuery as ChildOrderFeatureQuery;
use Thelia\Model\Map\OrderFeatureTableMap;

/**
 * Base class that represents a query for the 'order_feature' table.
 *
 *
 *
 * @method     ChildOrderFeatureQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildOrderFeatureQuery orderByOrderProductId($order = Criteria::ASC) Order by the order_product_id column
 * @method     ChildOrderFeatureQuery orderByFeatureDesc($order = Criteria::ASC) Order by the feature_desc column
 * @method     ChildOrderFeatureQuery orderByFeatureAvDesc($order = Criteria::ASC) Order by the feature_av_desc column
 * @method     ChildOrderFeatureQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildOrderFeatureQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildOrderFeatureQuery groupById() Group by the id column
 * @method     ChildOrderFeatureQuery groupByOrderProductId() Group by the order_product_id column
 * @method     ChildOrderFeatureQuery groupByFeatureDesc() Group by the feature_desc column
 * @method     ChildOrderFeatureQuery groupByFeatureAvDesc() Group by the feature_av_desc column
 * @method     ChildOrderFeatureQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildOrderFeatureQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildOrderFeatureQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildOrderFeatureQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildOrderFeatureQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildOrderFeatureQuery leftJoinOrderProduct($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderProduct relation
 * @method     ChildOrderFeatureQuery rightJoinOrderProduct($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderProduct relation
 * @method     ChildOrderFeatureQuery innerJoinOrderProduct($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderProduct relation
 *
 * @method     ChildOrderFeature findOne(ConnectionInterface $con = null) Return the first ChildOrderFeature matching the query
 * @method     ChildOrderFeature findOneOrCreate(ConnectionInterface $con = null) Return the first ChildOrderFeature matching the query, or a new ChildOrderFeature object populated from the query conditions when no match is found
 *
 * @method     ChildOrderFeature findOneById(int $id) Return the first ChildOrderFeature filtered by the id column
 * @method     ChildOrderFeature findOneByOrderProductId(int $order_product_id) Return the first ChildOrderFeature filtered by the order_product_id column
 * @method     ChildOrderFeature findOneByFeatureDesc(string $feature_desc) Return the first ChildOrderFeature filtered by the feature_desc column
 * @method     ChildOrderFeature findOneByFeatureAvDesc(string $feature_av_desc) Return the first ChildOrderFeature filtered by the feature_av_desc column
 * @method     ChildOrderFeature findOneByCreatedAt(string $created_at) Return the first ChildOrderFeature filtered by the created_at column
 * @method     ChildOrderFeature findOneByUpdatedAt(string $updated_at) Return the first ChildOrderFeature filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildOrderFeature objects filtered by the id column
 * @method     array findByOrderProductId(int $order_product_id) Return ChildOrderFeature objects filtered by the order_product_id column
 * @method     array findByFeatureDesc(string $feature_desc) Return ChildOrderFeature objects filtered by the feature_desc column
 * @method     array findByFeatureAvDesc(string $feature_av_desc) Return ChildOrderFeature objects filtered by the feature_av_desc column
 * @method     array findByCreatedAt(string $created_at) Return ChildOrderFeature objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildOrderFeature objects filtered by the updated_at column
 *
 */
abstract class OrderFeatureQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\OrderFeatureQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\OrderFeature', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildOrderFeatureQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildOrderFeatureQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\OrderFeatureQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\OrderFeatureQuery();
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
     * @return ChildOrderFeature|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = OrderFeatureTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(OrderFeatureTableMap::DATABASE_NAME);
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
     * @return   ChildOrderFeature A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT ID, ORDER_PRODUCT_ID, FEATURE_DESC, FEATURE_AV_DESC, CREATED_AT, UPDATED_AT FROM order_feature WHERE ID = :p0';
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
            $obj = new ChildOrderFeature();
            $obj->hydrate($row);
            OrderFeatureTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildOrderFeature|array|mixed the result, formatted by the current formatter
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
     * @return ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(OrderFeatureTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(OrderFeatureTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(OrderFeatureTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(OrderFeatureTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderFeatureTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the order_product_id column
     *
     * Example usage:
     * <code>
     * $query->filterByOrderProductId(1234); // WHERE order_product_id = 1234
     * $query->filterByOrderProductId(array(12, 34)); // WHERE order_product_id IN (12, 34)
     * $query->filterByOrderProductId(array('min' => 12)); // WHERE order_product_id > 12
     * </code>
     *
     * @see       filterByOrderProduct()
     *
     * @param     mixed $orderProductId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function filterByOrderProductId($orderProductId = null, $comparison = null)
    {
        if (is_array($orderProductId)) {
            $useMinMax = false;
            if (isset($orderProductId['min'])) {
                $this->addUsingAlias(OrderFeatureTableMap::ORDER_PRODUCT_ID, $orderProductId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($orderProductId['max'])) {
                $this->addUsingAlias(OrderFeatureTableMap::ORDER_PRODUCT_ID, $orderProductId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderFeatureTableMap::ORDER_PRODUCT_ID, $orderProductId, $comparison);
    }

    /**
     * Filter the query on the feature_desc column
     *
     * Example usage:
     * <code>
     * $query->filterByFeatureDesc('fooValue');   // WHERE feature_desc = 'fooValue'
     * $query->filterByFeatureDesc('%fooValue%'); // WHERE feature_desc LIKE '%fooValue%'
     * </code>
     *
     * @param     string $featureDesc The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function filterByFeatureDesc($featureDesc = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($featureDesc)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $featureDesc)) {
                $featureDesc = str_replace('*', '%', $featureDesc);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderFeatureTableMap::FEATURE_DESC, $featureDesc, $comparison);
    }

    /**
     * Filter the query on the feature_av_desc column
     *
     * Example usage:
     * <code>
     * $query->filterByFeatureAvDesc('fooValue');   // WHERE feature_av_desc = 'fooValue'
     * $query->filterByFeatureAvDesc('%fooValue%'); // WHERE feature_av_desc LIKE '%fooValue%'
     * </code>
     *
     * @param     string $featureAvDesc The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function filterByFeatureAvDesc($featureAvDesc = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($featureAvDesc)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $featureAvDesc)) {
                $featureAvDesc = str_replace('*', '%', $featureAvDesc);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderFeatureTableMap::FEATURE_AV_DESC, $featureAvDesc, $comparison);
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
     * @return ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(OrderFeatureTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(OrderFeatureTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderFeatureTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(OrderFeatureTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(OrderFeatureTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderFeatureTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\OrderProduct object
     *
     * @param \Thelia\Model\OrderProduct|ObjectCollection $orderProduct The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function filterByOrderProduct($orderProduct, $comparison = null)
    {
        if ($orderProduct instanceof \Thelia\Model\OrderProduct) {
            return $this
                ->addUsingAlias(OrderFeatureTableMap::ORDER_PRODUCT_ID, $orderProduct->getId(), $comparison);
        } elseif ($orderProduct instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderFeatureTableMap::ORDER_PRODUCT_ID, $orderProduct->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByOrderProduct() only accepts arguments of type \Thelia\Model\OrderProduct or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderProduct relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function joinOrderProduct($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderProduct');

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
            $this->addJoinObject($join, 'OrderProduct');
        }

        return $this;
    }

    /**
     * Use the OrderProduct relation OrderProduct object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderProductQuery A secondary query class using the current class as primary query
     */
    public function useOrderProductQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderProduct($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderProduct', '\Thelia\Model\OrderProductQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildOrderFeature $orderFeature Object to remove from the list of results
     *
     * @return ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function prune($orderFeature = null)
    {
        if ($orderFeature) {
            $this->addUsingAlias(OrderFeatureTableMap::ID, $orderFeature->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the order_feature table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderFeatureTableMap::DATABASE_NAME);
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
            OrderFeatureTableMap::clearInstancePool();
            OrderFeatureTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildOrderFeature or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildOrderFeature object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(OrderFeatureTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(OrderFeatureTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        OrderFeatureTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            OrderFeatureTableMap::clearRelatedInstancePool();
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
     * @return     ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(OrderFeatureTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(OrderFeatureTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(OrderFeatureTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(OrderFeatureTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(OrderFeatureTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildOrderFeatureQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(OrderFeatureTableMap::CREATED_AT);
    }

} // OrderFeatureQuery
