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
use Thelia\Model\Group as ChildGroup;
use Thelia\Model\GroupI18nQuery as ChildGroupI18nQuery;
use Thelia\Model\GroupQuery as ChildGroupQuery;
use Thelia\Model\Map\GroupTableMap;

/**
 * Base class that represents a query for the 'group' table.
 *
 *
 *
 * @method     ChildGroupQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildGroupQuery orderByCode($order = Criteria::ASC) Order by the code column
 * @method     ChildGroupQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildGroupQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildGroupQuery groupById() Group by the id column
 * @method     ChildGroupQuery groupByCode() Group by the code column
 * @method     ChildGroupQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildGroupQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildGroupQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildGroupQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildGroupQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildGroupQuery leftJoinAdminGroup($relationAlias = null) Adds a LEFT JOIN clause to the query using the AdminGroup relation
 * @method     ChildGroupQuery rightJoinAdminGroup($relationAlias = null) Adds a RIGHT JOIN clause to the query using the AdminGroup relation
 * @method     ChildGroupQuery innerJoinAdminGroup($relationAlias = null) Adds a INNER JOIN clause to the query using the AdminGroup relation
 *
 * @method     ChildGroupQuery leftJoinGroupResource($relationAlias = null) Adds a LEFT JOIN clause to the query using the GroupResource relation
 * @method     ChildGroupQuery rightJoinGroupResource($relationAlias = null) Adds a RIGHT JOIN clause to the query using the GroupResource relation
 * @method     ChildGroupQuery innerJoinGroupResource($relationAlias = null) Adds a INNER JOIN clause to the query using the GroupResource relation
 *
 * @method     ChildGroupQuery leftJoinGroupModule($relationAlias = null) Adds a LEFT JOIN clause to the query using the GroupModule relation
 * @method     ChildGroupQuery rightJoinGroupModule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the GroupModule relation
 * @method     ChildGroupQuery innerJoinGroupModule($relationAlias = null) Adds a INNER JOIN clause to the query using the GroupModule relation
 *
 * @method     ChildGroupQuery leftJoinGroupI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the GroupI18n relation
 * @method     ChildGroupQuery rightJoinGroupI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the GroupI18n relation
 * @method     ChildGroupQuery innerJoinGroupI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the GroupI18n relation
 *
 * @method     ChildGroup findOne(ConnectionInterface $con = null) Return the first ChildGroup matching the query
 * @method     ChildGroup findOneOrCreate(ConnectionInterface $con = null) Return the first ChildGroup matching the query, or a new ChildGroup object populated from the query conditions when no match is found
 *
 * @method     ChildGroup findOneById(int $id) Return the first ChildGroup filtered by the id column
 * @method     ChildGroup findOneByCode(string $code) Return the first ChildGroup filtered by the code column
 * @method     ChildGroup findOneByCreatedAt(string $created_at) Return the first ChildGroup filtered by the created_at column
 * @method     ChildGroup findOneByUpdatedAt(string $updated_at) Return the first ChildGroup filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildGroup objects filtered by the id column
 * @method     array findByCode(string $code) Return ChildGroup objects filtered by the code column
 * @method     array findByCreatedAt(string $created_at) Return ChildGroup objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildGroup objects filtered by the updated_at column
 *
 */
abstract class GroupQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\GroupQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Group', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildGroupQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildGroupQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\GroupQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\GroupQuery();
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
     * @return ChildGroup|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = GroupTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(GroupTableMap::DATABASE_NAME);
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
     * @return   ChildGroup A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT ID, CODE, CREATED_AT, UPDATED_AT FROM group WHERE ID = :p0';
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
            $obj = new ChildGroup();
            $obj->hydrate($row);
            GroupTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildGroup|array|mixed the result, formatted by the current formatter
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
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(GroupTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(GroupTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(GroupTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(GroupTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupTableMap::ID, $id, $comparison);
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
     * @return ChildGroupQuery The current query, for fluid interface
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

        return $this->addUsingAlias(GroupTableMap::CODE, $code, $comparison);
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
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(GroupTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(GroupTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(GroupTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(GroupTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\AdminGroup object
     *
     * @param \Thelia\Model\AdminGroup|ObjectCollection $adminGroup  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function filterByAdminGroup($adminGroup, $comparison = null)
    {
        if ($adminGroup instanceof \Thelia\Model\AdminGroup) {
            return $this
                ->addUsingAlias(GroupTableMap::ID, $adminGroup->getGroupId(), $comparison);
        } elseif ($adminGroup instanceof ObjectCollection) {
            return $this
                ->useAdminGroupQuery()
                ->filterByPrimaryKeys($adminGroup->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByAdminGroup() only accepts arguments of type \Thelia\Model\AdminGroup or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the AdminGroup relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function joinAdminGroup($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('AdminGroup');

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
            $this->addJoinObject($join, 'AdminGroup');
        }

        return $this;
    }

    /**
     * Use the AdminGroup relation AdminGroup object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\AdminGroupQuery A secondary query class using the current class as primary query
     */
    public function useAdminGroupQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinAdminGroup($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'AdminGroup', '\Thelia\Model\AdminGroupQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\GroupResource object
     *
     * @param \Thelia\Model\GroupResource|ObjectCollection $groupResource  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function filterByGroupResource($groupResource, $comparison = null)
    {
        if ($groupResource instanceof \Thelia\Model\GroupResource) {
            return $this
                ->addUsingAlias(GroupTableMap::ID, $groupResource->getGroupId(), $comparison);
        } elseif ($groupResource instanceof ObjectCollection) {
            return $this
                ->useGroupResourceQuery()
                ->filterByPrimaryKeys($groupResource->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByGroupResource() only accepts arguments of type \Thelia\Model\GroupResource or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the GroupResource relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function joinGroupResource($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('GroupResource');

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
            $this->addJoinObject($join, 'GroupResource');
        }

        return $this;
    }

    /**
     * Use the GroupResource relation GroupResource object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\GroupResourceQuery A secondary query class using the current class as primary query
     */
    public function useGroupResourceQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinGroupResource($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'GroupResource', '\Thelia\Model\GroupResourceQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\GroupModule object
     *
     * @param \Thelia\Model\GroupModule|ObjectCollection $groupModule  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function filterByGroupModule($groupModule, $comparison = null)
    {
        if ($groupModule instanceof \Thelia\Model\GroupModule) {
            return $this
                ->addUsingAlias(GroupTableMap::ID, $groupModule->getGroupId(), $comparison);
        } elseif ($groupModule instanceof ObjectCollection) {
            return $this
                ->useGroupModuleQuery()
                ->filterByPrimaryKeys($groupModule->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByGroupModule() only accepts arguments of type \Thelia\Model\GroupModule or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the GroupModule relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function joinGroupModule($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('GroupModule');

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
            $this->addJoinObject($join, 'GroupModule');
        }

        return $this;
    }

    /**
     * Use the GroupModule relation GroupModule object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\GroupModuleQuery A secondary query class using the current class as primary query
     */
    public function useGroupModuleQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinGroupModule($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'GroupModule', '\Thelia\Model\GroupModuleQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\GroupI18n object
     *
     * @param \Thelia\Model\GroupI18n|ObjectCollection $groupI18n  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function filterByGroupI18n($groupI18n, $comparison = null)
    {
        if ($groupI18n instanceof \Thelia\Model\GroupI18n) {
            return $this
                ->addUsingAlias(GroupTableMap::ID, $groupI18n->getId(), $comparison);
        } elseif ($groupI18n instanceof ObjectCollection) {
            return $this
                ->useGroupI18nQuery()
                ->filterByPrimaryKeys($groupI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByGroupI18n() only accepts arguments of type \Thelia\Model\GroupI18n or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the GroupI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function joinGroupI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('GroupI18n');

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
            $this->addJoinObject($join, 'GroupI18n');
        }

        return $this;
    }

    /**
     * Use the GroupI18n relation GroupI18n object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\GroupI18nQuery A secondary query class using the current class as primary query
     */
    public function useGroupI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinGroupI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'GroupI18n', '\Thelia\Model\GroupI18nQuery');
    }

    /**
     * Filter the query by a related Admin object
     * using the admin_group table as cross reference
     *
     * @param Admin $admin the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function filterByAdmin($admin, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useAdminGroupQuery()
            ->filterByAdmin($admin, $comparison)
            ->endUse();
    }

    /**
     * Filter the query by a related Resource object
     * using the group_resource table as cross reference
     *
     * @param Resource $resource the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function filterByResource($resource, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useGroupResourceQuery()
            ->filterByResource($resource, $comparison)
            ->endUse();
    }

    /**
     * Exclude object from result
     *
     * @param   ChildGroup $group Object to remove from the list of results
     *
     * @return ChildGroupQuery The current query, for fluid interface
     */
    public function prune($group = null)
    {
        if ($group) {
            $this->addUsingAlias(GroupTableMap::ID, $group->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the group table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(GroupTableMap::DATABASE_NAME);
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
            GroupTableMap::clearInstancePool();
            GroupTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildGroup or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildGroup object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(GroupTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(GroupTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        GroupTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            GroupTableMap::clearRelatedInstancePool();
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
     * @return     ChildGroupQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(GroupTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildGroupQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(GroupTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildGroupQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(GroupTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildGroupQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(GroupTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildGroupQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(GroupTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildGroupQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(GroupTableMap::CREATED_AT);
    }

    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildGroupQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_EN', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'GroupI18n';

        return $this
            ->joinGroupI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildGroupQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_EN', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('GroupI18n');
        $this->with['GroupI18n']->setIsWithOneToMany(false);

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
     * @return    ChildGroupI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_EN', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'GroupI18n', '\Thelia\Model\GroupI18nQuery');
    }

} // GroupQuery
