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
use Thelia\Model\Profile as ChildProfile;
use Thelia\Model\ProfileI18nQuery as ChildProfileI18nQuery;
use Thelia\Model\ProfileQuery as ChildProfileQuery;
use Thelia\Model\Map\ProfileTableMap;

/**
 * Base class that represents a query for the 'profile' table.
 *
 *
 *
 * @method     ChildProfileQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildProfileQuery orderByCode($order = Criteria::ASC) Order by the code column
 * @method     ChildProfileQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildProfileQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildProfileQuery groupById() Group by the id column
 * @method     ChildProfileQuery groupByCode() Group by the code column
 * @method     ChildProfileQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildProfileQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildProfileQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildProfileQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildProfileQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildProfileQuery leftJoinAdmin($relationAlias = null) Adds a LEFT JOIN clause to the query using the Admin relation
 * @method     ChildProfileQuery rightJoinAdmin($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Admin relation
 * @method     ChildProfileQuery innerJoinAdmin($relationAlias = null) Adds a INNER JOIN clause to the query using the Admin relation
 *
 * @method     ChildProfileQuery leftJoinProfileResource($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProfileResource relation
 * @method     ChildProfileQuery rightJoinProfileResource($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProfileResource relation
 * @method     ChildProfileQuery innerJoinProfileResource($relationAlias = null) Adds a INNER JOIN clause to the query using the ProfileResource relation
 *
 * @method     ChildProfileQuery leftJoinProfileModule($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProfileModule relation
 * @method     ChildProfileQuery rightJoinProfileModule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProfileModule relation
 * @method     ChildProfileQuery innerJoinProfileModule($relationAlias = null) Adds a INNER JOIN clause to the query using the ProfileModule relation
 *
 * @method     ChildProfileQuery leftJoinApi($relationAlias = null) Adds a LEFT JOIN clause to the query using the Api relation
 * @method     ChildProfileQuery rightJoinApi($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Api relation
 * @method     ChildProfileQuery innerJoinApi($relationAlias = null) Adds a INNER JOIN clause to the query using the Api relation
 *
 * @method     ChildProfileQuery leftJoinProfileI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProfileI18n relation
 * @method     ChildProfileQuery rightJoinProfileI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProfileI18n relation
 * @method     ChildProfileQuery innerJoinProfileI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the ProfileI18n relation
 *
 * @method     ChildProfile findOne(ConnectionInterface $con = null) Return the first ChildProfile matching the query
 * @method     ChildProfile findOneOrCreate(ConnectionInterface $con = null) Return the first ChildProfile matching the query, or a new ChildProfile object populated from the query conditions when no match is found
 *
 * @method     ChildProfile findOneById(int $id) Return the first ChildProfile filtered by the id column
 * @method     ChildProfile findOneByCode(string $code) Return the first ChildProfile filtered by the code column
 * @method     ChildProfile findOneByCreatedAt(string $created_at) Return the first ChildProfile filtered by the created_at column
 * @method     ChildProfile findOneByUpdatedAt(string $updated_at) Return the first ChildProfile filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildProfile objects filtered by the id column
 * @method     array findByCode(string $code) Return ChildProfile objects filtered by the code column
 * @method     array findByCreatedAt(string $created_at) Return ChildProfile objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildProfile objects filtered by the updated_at column
 *
 */
abstract class ProfileQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\ProfileQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Profile', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildProfileQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildProfileQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\ProfileQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\ProfileQuery();
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
     * @return ChildProfile|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = ProfileTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(ProfileTableMap::DATABASE_NAME);
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
     * @return   ChildProfile A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `CODE`, `CREATED_AT`, `UPDATED_AT` FROM `profile` WHERE `ID` = :p0';
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
            $obj = new ChildProfile();
            $obj->hydrate($row);
            ProfileTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildProfile|array|mixed the result, formatted by the current formatter
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
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(ProfileTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(ProfileTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(ProfileTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(ProfileTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProfileTableMap::ID, $id, $comparison);
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
     * @return ChildProfileQuery The current query, for fluid interface
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

        return $this->addUsingAlias(ProfileTableMap::CODE, $code, $comparison);
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
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(ProfileTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(ProfileTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProfileTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(ProfileTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(ProfileTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProfileTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Admin object
     *
     * @param \Thelia\Model\Admin|ObjectCollection $admin  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function filterByAdmin($admin, $comparison = null)
    {
        if ($admin instanceof \Thelia\Model\Admin) {
            return $this
                ->addUsingAlias(ProfileTableMap::ID, $admin->getProfileId(), $comparison);
        } elseif ($admin instanceof ObjectCollection) {
            return $this
                ->useAdminQuery()
                ->filterByPrimaryKeys($admin->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByAdmin() only accepts arguments of type \Thelia\Model\Admin or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Admin relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function joinAdmin($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Admin');

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
            $this->addJoinObject($join, 'Admin');
        }

        return $this;
    }

    /**
     * Use the Admin relation Admin object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\AdminQuery A secondary query class using the current class as primary query
     */
    public function useAdminQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinAdmin($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Admin', '\Thelia\Model\AdminQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ProfileResource object
     *
     * @param \Thelia\Model\ProfileResource|ObjectCollection $profileResource  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function filterByProfileResource($profileResource, $comparison = null)
    {
        if ($profileResource instanceof \Thelia\Model\ProfileResource) {
            return $this
                ->addUsingAlias(ProfileTableMap::ID, $profileResource->getProfileId(), $comparison);
        } elseif ($profileResource instanceof ObjectCollection) {
            return $this
                ->useProfileResourceQuery()
                ->filterByPrimaryKeys($profileResource->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByProfileResource() only accepts arguments of type \Thelia\Model\ProfileResource or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ProfileResource relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function joinProfileResource($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ProfileResource');

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
            $this->addJoinObject($join, 'ProfileResource');
        }

        return $this;
    }

    /**
     * Use the ProfileResource relation ProfileResource object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ProfileResourceQuery A secondary query class using the current class as primary query
     */
    public function useProfileResourceQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinProfileResource($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ProfileResource', '\Thelia\Model\ProfileResourceQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ProfileModule object
     *
     * @param \Thelia\Model\ProfileModule|ObjectCollection $profileModule  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function filterByProfileModule($profileModule, $comparison = null)
    {
        if ($profileModule instanceof \Thelia\Model\ProfileModule) {
            return $this
                ->addUsingAlias(ProfileTableMap::ID, $profileModule->getProfileId(), $comparison);
        } elseif ($profileModule instanceof ObjectCollection) {
            return $this
                ->useProfileModuleQuery()
                ->filterByPrimaryKeys($profileModule->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByProfileModule() only accepts arguments of type \Thelia\Model\ProfileModule or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ProfileModule relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function joinProfileModule($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ProfileModule');

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
            $this->addJoinObject($join, 'ProfileModule');
        }

        return $this;
    }

    /**
     * Use the ProfileModule relation ProfileModule object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ProfileModuleQuery A secondary query class using the current class as primary query
     */
    public function useProfileModuleQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinProfileModule($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ProfileModule', '\Thelia\Model\ProfileModuleQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Api object
     *
     * @param \Thelia\Model\Api|ObjectCollection $api  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function filterByApi($api, $comparison = null)
    {
        if ($api instanceof \Thelia\Model\Api) {
            return $this
                ->addUsingAlias(ProfileTableMap::ID, $api->getProfileId(), $comparison);
        } elseif ($api instanceof ObjectCollection) {
            return $this
                ->useApiQuery()
                ->filterByPrimaryKeys($api->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByApi() only accepts arguments of type \Thelia\Model\Api or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Api relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function joinApi($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Api');

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
            $this->addJoinObject($join, 'Api');
        }

        return $this;
    }

    /**
     * Use the Api relation Api object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ApiQuery A secondary query class using the current class as primary query
     */
    public function useApiQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinApi($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Api', '\Thelia\Model\ApiQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ProfileI18n object
     *
     * @param \Thelia\Model\ProfileI18n|ObjectCollection $profileI18n  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function filterByProfileI18n($profileI18n, $comparison = null)
    {
        if ($profileI18n instanceof \Thelia\Model\ProfileI18n) {
            return $this
                ->addUsingAlias(ProfileTableMap::ID, $profileI18n->getId(), $comparison);
        } elseif ($profileI18n instanceof ObjectCollection) {
            return $this
                ->useProfileI18nQuery()
                ->filterByPrimaryKeys($profileI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByProfileI18n() only accepts arguments of type \Thelia\Model\ProfileI18n or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ProfileI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function joinProfileI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ProfileI18n');

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
            $this->addJoinObject($join, 'ProfileI18n');
        }

        return $this;
    }

    /**
     * Use the ProfileI18n relation ProfileI18n object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ProfileI18nQuery A secondary query class using the current class as primary query
     */
    public function useProfileI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinProfileI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ProfileI18n', '\Thelia\Model\ProfileI18nQuery');
    }

    /**
     * Filter the query by a related Resource object
     * using the profile_resource table as cross reference
     *
     * @param Resource $resource the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function filterByResource($resource, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useProfileResourceQuery()
            ->filterByResource($resource, $comparison)
            ->endUse();
    }

    /**
     * Exclude object from result
     *
     * @param   ChildProfile $profile Object to remove from the list of results
     *
     * @return ChildProfileQuery The current query, for fluid interface
     */
    public function prune($profile = null)
    {
        if ($profile) {
            $this->addUsingAlias(ProfileTableMap::ID, $profile->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the profile table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ProfileTableMap::DATABASE_NAME);
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
            ProfileTableMap::clearInstancePool();
            ProfileTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildProfile or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildProfile object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(ProfileTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(ProfileTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        ProfileTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            ProfileTableMap::clearRelatedInstancePool();
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
     * @return     ChildProfileQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(ProfileTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildProfileQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(ProfileTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildProfileQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(ProfileTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildProfileQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(ProfileTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildProfileQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(ProfileTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildProfileQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(ProfileTableMap::CREATED_AT);
    }

    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildProfileQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'ProfileI18n';

        return $this
            ->joinProfileI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildProfileQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('ProfileI18n');
        $this->with['ProfileI18n']->setIsWithOneToMany(false);

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
     * @return    ChildProfileI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ProfileI18n', '\Thelia\Model\ProfileI18nQuery');
    }

} // ProfileQuery
