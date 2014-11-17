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
use Thelia\Model\Api as ChildApi;
use Thelia\Model\ApiQuery as ChildApiQuery;
use Thelia\Model\Map\ApiTableMap;

/**
 * Base class that represents a query for the 'api' table.
 *
 *
 *
 * @method     ChildApiQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildApiQuery orderByLabel($order = Criteria::ASC) Order by the label column
 * @method     ChildApiQuery orderByApiKey($order = Criteria::ASC) Order by the api_key column
 * @method     ChildApiQuery orderByProfileId($order = Criteria::ASC) Order by the profile_id column
 * @method     ChildApiQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildApiQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildApiQuery groupById() Group by the id column
 * @method     ChildApiQuery groupByLabel() Group by the label column
 * @method     ChildApiQuery groupByApiKey() Group by the api_key column
 * @method     ChildApiQuery groupByProfileId() Group by the profile_id column
 * @method     ChildApiQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildApiQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildApiQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildApiQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildApiQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildApiQuery leftJoinProfile($relationAlias = null) Adds a LEFT JOIN clause to the query using the Profile relation
 * @method     ChildApiQuery rightJoinProfile($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Profile relation
 * @method     ChildApiQuery innerJoinProfile($relationAlias = null) Adds a INNER JOIN clause to the query using the Profile relation
 *
 * @method     ChildApi findOne(ConnectionInterface $con = null) Return the first ChildApi matching the query
 * @method     ChildApi findOneOrCreate(ConnectionInterface $con = null) Return the first ChildApi matching the query, or a new ChildApi object populated from the query conditions when no match is found
 *
 * @method     ChildApi findOneById(int $id) Return the first ChildApi filtered by the id column
 * @method     ChildApi findOneByLabel(string $label) Return the first ChildApi filtered by the label column
 * @method     ChildApi findOneByApiKey(string $api_key) Return the first ChildApi filtered by the api_key column
 * @method     ChildApi findOneByProfileId(int $profile_id) Return the first ChildApi filtered by the profile_id column
 * @method     ChildApi findOneByCreatedAt(string $created_at) Return the first ChildApi filtered by the created_at column
 * @method     ChildApi findOneByUpdatedAt(string $updated_at) Return the first ChildApi filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildApi objects filtered by the id column
 * @method     array findByLabel(string $label) Return ChildApi objects filtered by the label column
 * @method     array findByApiKey(string $api_key) Return ChildApi objects filtered by the api_key column
 * @method     array findByProfileId(int $profile_id) Return ChildApi objects filtered by the profile_id column
 * @method     array findByCreatedAt(string $created_at) Return ChildApi objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildApi objects filtered by the updated_at column
 *
 */
abstract class ApiQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\ApiQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Api', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildApiQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildApiQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\ApiQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\ApiQuery();
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
     * @return ChildApi|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = ApiTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(ApiTableMap::DATABASE_NAME);
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
     * @return   ChildApi A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `LABEL`, `API_KEY`, `PROFILE_ID`, `CREATED_AT`, `UPDATED_AT` FROM `api` WHERE `ID` = :p0';
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
            $obj = new ChildApi();
            $obj->hydrate($row);
            ApiTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildApi|array|mixed the result, formatted by the current formatter
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
     * @return ChildApiQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(ApiTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildApiQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(ApiTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildApiQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(ApiTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(ApiTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ApiTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the label column
     *
     * Example usage:
     * <code>
     * $query->filterByLabel('fooValue');   // WHERE label = 'fooValue'
     * $query->filterByLabel('%fooValue%'); // WHERE label LIKE '%fooValue%'
     * </code>
     *
     * @param     string $label The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildApiQuery The current query, for fluid interface
     */
    public function filterByLabel($label = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($label)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $label)) {
                $label = str_replace('*', '%', $label);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(ApiTableMap::LABEL, $label, $comparison);
    }

    /**
     * Filter the query on the api_key column
     *
     * Example usage:
     * <code>
     * $query->filterByApiKey('fooValue');   // WHERE api_key = 'fooValue'
     * $query->filterByApiKey('%fooValue%'); // WHERE api_key LIKE '%fooValue%'
     * </code>
     *
     * @param     string $apiKey The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildApiQuery The current query, for fluid interface
     */
    public function filterByApiKey($apiKey = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($apiKey)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $apiKey)) {
                $apiKey = str_replace('*', '%', $apiKey);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(ApiTableMap::API_KEY, $apiKey, $comparison);
    }

    /**
     * Filter the query on the profile_id column
     *
     * Example usage:
     * <code>
     * $query->filterByProfileId(1234); // WHERE profile_id = 1234
     * $query->filterByProfileId(array(12, 34)); // WHERE profile_id IN (12, 34)
     * $query->filterByProfileId(array('min' => 12)); // WHERE profile_id > 12
     * </code>
     *
     * @see       filterByProfile()
     *
     * @param     mixed $profileId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildApiQuery The current query, for fluid interface
     */
    public function filterByProfileId($profileId = null, $comparison = null)
    {
        if (is_array($profileId)) {
            $useMinMax = false;
            if (isset($profileId['min'])) {
                $this->addUsingAlias(ApiTableMap::PROFILE_ID, $profileId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($profileId['max'])) {
                $this->addUsingAlias(ApiTableMap::PROFILE_ID, $profileId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ApiTableMap::PROFILE_ID, $profileId, $comparison);
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
     * @return ChildApiQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(ApiTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(ApiTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ApiTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildApiQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(ApiTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(ApiTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ApiTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Profile object
     *
     * @param \Thelia\Model\Profile|ObjectCollection $profile The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildApiQuery The current query, for fluid interface
     */
    public function filterByProfile($profile, $comparison = null)
    {
        if ($profile instanceof \Thelia\Model\Profile) {
            return $this
                ->addUsingAlias(ApiTableMap::PROFILE_ID, $profile->getId(), $comparison);
        } elseif ($profile instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(ApiTableMap::PROFILE_ID, $profile->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByProfile() only accepts arguments of type \Thelia\Model\Profile or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Profile relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildApiQuery The current query, for fluid interface
     */
    public function joinProfile($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Profile');

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
            $this->addJoinObject($join, 'Profile');
        }

        return $this;
    }

    /**
     * Use the Profile relation Profile object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ProfileQuery A secondary query class using the current class as primary query
     */
    public function useProfileQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinProfile($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Profile', '\Thelia\Model\ProfileQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildApi $api Object to remove from the list of results
     *
     * @return ChildApiQuery The current query, for fluid interface
     */
    public function prune($api = null)
    {
        if ($api) {
            $this->addUsingAlias(ApiTableMap::ID, $api->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the api table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ApiTableMap::DATABASE_NAME);
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
            ApiTableMap::clearInstancePool();
            ApiTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildApi or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildApi object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(ApiTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(ApiTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        ApiTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            ApiTableMap::clearRelatedInstancePool();
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
     * @return     ChildApiQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(ApiTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildApiQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(ApiTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildApiQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(ApiTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildApiQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(ApiTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildApiQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(ApiTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildApiQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(ApiTableMap::CREATED_AT);
    }

} // ApiQuery
