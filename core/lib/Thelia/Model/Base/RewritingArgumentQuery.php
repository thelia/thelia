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
use Thelia\Model\RewritingArgument as ChildRewritingArgument;
use Thelia\Model\RewritingArgumentQuery as ChildRewritingArgumentQuery;
use Thelia\Model\Map\RewritingArgumentTableMap;

/**
 * Base class that represents a query for the 'rewriting_argument' table.
 *
 *
 *
 * @method     ChildRewritingArgumentQuery orderByRewritingUrlId($order = Criteria::ASC) Order by the rewriting_url_id column
 * @method     ChildRewritingArgumentQuery orderByParameter($order = Criteria::ASC) Order by the parameter column
 * @method     ChildRewritingArgumentQuery orderByValue($order = Criteria::ASC) Order by the value column
 * @method     ChildRewritingArgumentQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildRewritingArgumentQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildRewritingArgumentQuery groupByRewritingUrlId() Group by the rewriting_url_id column
 * @method     ChildRewritingArgumentQuery groupByParameter() Group by the parameter column
 * @method     ChildRewritingArgumentQuery groupByValue() Group by the value column
 * @method     ChildRewritingArgumentQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildRewritingArgumentQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildRewritingArgumentQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildRewritingArgumentQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildRewritingArgumentQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildRewritingArgumentQuery leftJoinRewritingUrl($relationAlias = null) Adds a LEFT JOIN clause to the query using the RewritingUrl relation
 * @method     ChildRewritingArgumentQuery rightJoinRewritingUrl($relationAlias = null) Adds a RIGHT JOIN clause to the query using the RewritingUrl relation
 * @method     ChildRewritingArgumentQuery innerJoinRewritingUrl($relationAlias = null) Adds a INNER JOIN clause to the query using the RewritingUrl relation
 *
 * @method     ChildRewritingArgument findOne(ConnectionInterface $con = null) Return the first ChildRewritingArgument matching the query
 * @method     ChildRewritingArgument findOneOrCreate(ConnectionInterface $con = null) Return the first ChildRewritingArgument matching the query, or a new ChildRewritingArgument object populated from the query conditions when no match is found
 *
 * @method     ChildRewritingArgument findOneByRewritingUrlId(int $rewriting_url_id) Return the first ChildRewritingArgument filtered by the rewriting_url_id column
 * @method     ChildRewritingArgument findOneByParameter(string $parameter) Return the first ChildRewritingArgument filtered by the parameter column
 * @method     ChildRewritingArgument findOneByValue(string $value) Return the first ChildRewritingArgument filtered by the value column
 * @method     ChildRewritingArgument findOneByCreatedAt(string $created_at) Return the first ChildRewritingArgument filtered by the created_at column
 * @method     ChildRewritingArgument findOneByUpdatedAt(string $updated_at) Return the first ChildRewritingArgument filtered by the updated_at column
 *
 * @method     array findByRewritingUrlId(int $rewriting_url_id) Return ChildRewritingArgument objects filtered by the rewriting_url_id column
 * @method     array findByParameter(string $parameter) Return ChildRewritingArgument objects filtered by the parameter column
 * @method     array findByValue(string $value) Return ChildRewritingArgument objects filtered by the value column
 * @method     array findByCreatedAt(string $created_at) Return ChildRewritingArgument objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildRewritingArgument objects filtered by the updated_at column
 *
 */
abstract class RewritingArgumentQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\RewritingArgumentQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\RewritingArgument', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildRewritingArgumentQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildRewritingArgumentQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\RewritingArgumentQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\RewritingArgumentQuery();
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
     * $obj = $c->findPk(array(12, 34, 56), $con);
     * </code>
     *
     * @param array[$rewriting_url_id, $parameter, $value] $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildRewritingArgument|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = RewritingArgumentTableMap::getInstanceFromPool(serialize(array((string) $key[0], (string) $key[1], (string) $key[2]))))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(RewritingArgumentTableMap::DATABASE_NAME);
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
     * @return   ChildRewritingArgument A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `REWRITING_URL_ID`, `PARAMETER`, `VALUE`, `CREATED_AT`, `UPDATED_AT` FROM `rewriting_argument` WHERE `REWRITING_URL_ID` = :p0 AND `PARAMETER` = :p1 AND `VALUE` = :p2';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key[0], PDO::PARAM_INT);
            $stmt->bindValue(':p1', $key[1], PDO::PARAM_STR);
            $stmt->bindValue(':p2', $key[2], PDO::PARAM_STR);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $obj = new ChildRewritingArgument();
            $obj->hydrate($row);
            RewritingArgumentTableMap::addInstanceToPool($obj, serialize(array((string) $key[0], (string) $key[1], (string) $key[2])));
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
     * @return ChildRewritingArgument|array|mixed the result, formatted by the current formatter
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
     * @return ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(RewritingArgumentTableMap::REWRITING_URL_ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(RewritingArgumentTableMap::PARAMETER, $key[1], Criteria::EQUAL);
        $this->addUsingAlias(RewritingArgumentTableMap::VALUE, $key[2], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(RewritingArgumentTableMap::REWRITING_URL_ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(RewritingArgumentTableMap::PARAMETER, $key[1], Criteria::EQUAL);
            $cton0->addAnd($cton1);
            $cton2 = $this->getNewCriterion(RewritingArgumentTableMap::VALUE, $key[2], Criteria::EQUAL);
            $cton0->addAnd($cton2);
            $this->addOr($cton0);
        }

        return $this;
    }

    /**
     * Filter the query on the rewriting_url_id column
     *
     * Example usage:
     * <code>
     * $query->filterByRewritingUrlId(1234); // WHERE rewriting_url_id = 1234
     * $query->filterByRewritingUrlId(array(12, 34)); // WHERE rewriting_url_id IN (12, 34)
     * $query->filterByRewritingUrlId(array('min' => 12)); // WHERE rewriting_url_id > 12
     * </code>
     *
     * @see       filterByRewritingUrl()
     *
     * @param     mixed $rewritingUrlId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function filterByRewritingUrlId($rewritingUrlId = null, $comparison = null)
    {
        if (is_array($rewritingUrlId)) {
            $useMinMax = false;
            if (isset($rewritingUrlId['min'])) {
                $this->addUsingAlias(RewritingArgumentTableMap::REWRITING_URL_ID, $rewritingUrlId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($rewritingUrlId['max'])) {
                $this->addUsingAlias(RewritingArgumentTableMap::REWRITING_URL_ID, $rewritingUrlId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RewritingArgumentTableMap::REWRITING_URL_ID, $rewritingUrlId, $comparison);
    }

    /**
     * Filter the query on the parameter column
     *
     * Example usage:
     * <code>
     * $query->filterByParameter('fooValue');   // WHERE parameter = 'fooValue'
     * $query->filterByParameter('%fooValue%'); // WHERE parameter LIKE '%fooValue%'
     * </code>
     *
     * @param     string $parameter The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function filterByParameter($parameter = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($parameter)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $parameter)) {
                $parameter = str_replace('*', '%', $parameter);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RewritingArgumentTableMap::PARAMETER, $parameter, $comparison);
    }

    /**
     * Filter the query on the value column
     *
     * Example usage:
     * <code>
     * $query->filterByValue('fooValue');   // WHERE value = 'fooValue'
     * $query->filterByValue('%fooValue%'); // WHERE value LIKE '%fooValue%'
     * </code>
     *
     * @param     string $value The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function filterByValue($value = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($value)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $value)) {
                $value = str_replace('*', '%', $value);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RewritingArgumentTableMap::VALUE, $value, $comparison);
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
     * @return ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(RewritingArgumentTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(RewritingArgumentTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RewritingArgumentTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(RewritingArgumentTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(RewritingArgumentTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RewritingArgumentTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\RewritingUrl object
     *
     * @param \Thelia\Model\RewritingUrl|ObjectCollection $rewritingUrl The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function filterByRewritingUrl($rewritingUrl, $comparison = null)
    {
        if ($rewritingUrl instanceof \Thelia\Model\RewritingUrl) {
            return $this
                ->addUsingAlias(RewritingArgumentTableMap::REWRITING_URL_ID, $rewritingUrl->getId(), $comparison);
        } elseif ($rewritingUrl instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(RewritingArgumentTableMap::REWRITING_URL_ID, $rewritingUrl->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByRewritingUrl() only accepts arguments of type \Thelia\Model\RewritingUrl or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the RewritingUrl relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function joinRewritingUrl($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('RewritingUrl');

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
            $this->addJoinObject($join, 'RewritingUrl');
        }

        return $this;
    }

    /**
     * Use the RewritingUrl relation RewritingUrl object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\RewritingUrlQuery A secondary query class using the current class as primary query
     */
    public function useRewritingUrlQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinRewritingUrl($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'RewritingUrl', '\Thelia\Model\RewritingUrlQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildRewritingArgument $rewritingArgument Object to remove from the list of results
     *
     * @return ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function prune($rewritingArgument = null)
    {
        if ($rewritingArgument) {
            $this->addCond('pruneCond0', $this->getAliasedColName(RewritingArgumentTableMap::REWRITING_URL_ID), $rewritingArgument->getRewritingUrlId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(RewritingArgumentTableMap::PARAMETER), $rewritingArgument->getParameter(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond2', $this->getAliasedColName(RewritingArgumentTableMap::VALUE), $rewritingArgument->getValue(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1', 'pruneCond2'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

    /**
     * Deletes all rows from the rewriting_argument table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(RewritingArgumentTableMap::DATABASE_NAME);
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
            RewritingArgumentTableMap::clearInstancePool();
            RewritingArgumentTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildRewritingArgument or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildRewritingArgument object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(RewritingArgumentTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(RewritingArgumentTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        RewritingArgumentTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            RewritingArgumentTableMap::clearRelatedInstancePool();
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
     * @return     ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(RewritingArgumentTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(RewritingArgumentTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(RewritingArgumentTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(RewritingArgumentTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(RewritingArgumentTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildRewritingArgumentQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(RewritingArgumentTableMap::CREATED_AT);
    }

} // RewritingArgumentQuery
