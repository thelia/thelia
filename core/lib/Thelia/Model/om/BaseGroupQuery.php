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
use Thelia\Model\AdminGroup;
use Thelia\Model\Group;
use Thelia\Model\GroupDesc;
use Thelia\Model\GroupModule;
use Thelia\Model\GroupPeer;
use Thelia\Model\GroupQuery;
use Thelia\Model\GroupResource;

/**
 * Base class that represents a query for the 'group' table.
 *
 *
 *
 * @method GroupQuery orderById($order = Criteria::ASC) Order by the id column
 * @method GroupQuery orderByCode($order = Criteria::ASC) Order by the code column
 * @method GroupQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method GroupQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method GroupQuery groupById() Group by the id column
 * @method GroupQuery groupByCode() Group by the code column
 * @method GroupQuery groupByCreatedAt() Group by the created_at column
 * @method GroupQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method GroupQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method GroupQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method GroupQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method GroupQuery leftJoinGroupDesc($relationAlias = null) Adds a LEFT JOIN clause to the query using the GroupDesc relation
 * @method GroupQuery rightJoinGroupDesc($relationAlias = null) Adds a RIGHT JOIN clause to the query using the GroupDesc relation
 * @method GroupQuery innerJoinGroupDesc($relationAlias = null) Adds a INNER JOIN clause to the query using the GroupDesc relation
 *
 * @method GroupQuery leftJoinAdminGroup($relationAlias = null) Adds a LEFT JOIN clause to the query using the AdminGroup relation
 * @method GroupQuery rightJoinAdminGroup($relationAlias = null) Adds a RIGHT JOIN clause to the query using the AdminGroup relation
 * @method GroupQuery innerJoinAdminGroup($relationAlias = null) Adds a INNER JOIN clause to the query using the AdminGroup relation
 *
 * @method GroupQuery leftJoinGroupResource($relationAlias = null) Adds a LEFT JOIN clause to the query using the GroupResource relation
 * @method GroupQuery rightJoinGroupResource($relationAlias = null) Adds a RIGHT JOIN clause to the query using the GroupResource relation
 * @method GroupQuery innerJoinGroupResource($relationAlias = null) Adds a INNER JOIN clause to the query using the GroupResource relation
 *
 * @method GroupQuery leftJoinGroupModule($relationAlias = null) Adds a LEFT JOIN clause to the query using the GroupModule relation
 * @method GroupQuery rightJoinGroupModule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the GroupModule relation
 * @method GroupQuery innerJoinGroupModule($relationAlias = null) Adds a INNER JOIN clause to the query using the GroupModule relation
 *
 * @method Group findOne(PropelPDO $con = null) Return the first Group matching the query
 * @method Group findOneOrCreate(PropelPDO $con = null) Return the first Group matching the query, or a new Group object populated from the query conditions when no match is found
 *
 * @method Group findOneById(int $id) Return the first Group filtered by the id column
 * @method Group findOneByCode(string $code) Return the first Group filtered by the code column
 * @method Group findOneByCreatedAt(string $created_at) Return the first Group filtered by the created_at column
 * @method Group findOneByUpdatedAt(string $updated_at) Return the first Group filtered by the updated_at column
 *
 * @method array findById(int $id) Return Group objects filtered by the id column
 * @method array findByCode(string $code) Return Group objects filtered by the code column
 * @method array findByCreatedAt(string $created_at) Return Group objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return Group objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseGroupQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseGroupQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\Group', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new GroupQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     GroupQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return GroupQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof GroupQuery) {
            return $criteria;
        }
        $query = new GroupQuery();
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
     * @return   Group|Group[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = GroupPeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(GroupPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return   Group A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `CODE`, `CREATED_AT`, `UPDATED_AT` FROM `group` WHERE `ID` = :p0';
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
            $obj = new Group();
            $obj->hydrate($row);
            GroupPeer::addInstanceToPool($obj, (string) $key);
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
     * @return Group|Group[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|Group[]|mixed the list of results, formatted by the current formatter
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
     * @return GroupQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(GroupPeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return GroupQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(GroupPeer::ID, $keys, Criteria::IN);
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
     * @return GroupQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(GroupPeer::ID, $id, $comparison);
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
     * @return GroupQuery The current query, for fluid interface
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

        return $this->addUsingAlias(GroupPeer::CODE, $code, $comparison);
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
     * @return GroupQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(GroupPeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(GroupPeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupPeer::CREATED_AT, $createdAt, $comparison);
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
     * @return GroupQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(GroupPeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(GroupPeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupPeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related GroupDesc object
     *
     * @param   GroupDesc|PropelObjectCollection $groupDesc  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   GroupQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByGroupDesc($groupDesc, $comparison = null)
    {
        if ($groupDesc instanceof GroupDesc) {
            return $this
                ->addUsingAlias(GroupPeer::ID, $groupDesc->getGroupId(), $comparison);
        } elseif ($groupDesc instanceof PropelObjectCollection) {
            return $this
                ->useGroupDescQuery()
                ->filterByPrimaryKeys($groupDesc->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByGroupDesc() only accepts arguments of type GroupDesc or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the GroupDesc relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return GroupQuery The current query, for fluid interface
     */
    public function joinGroupDesc($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('GroupDesc');

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
            $this->addJoinObject($join, 'GroupDesc');
        }

        return $this;
    }

    /**
     * Use the GroupDesc relation GroupDesc object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\GroupDescQuery A secondary query class using the current class as primary query
     */
    public function useGroupDescQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinGroupDesc($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'GroupDesc', '\Thelia\Model\GroupDescQuery');
    }

    /**
     * Filter the query by a related AdminGroup object
     *
     * @param   AdminGroup|PropelObjectCollection $adminGroup  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   GroupQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByAdminGroup($adminGroup, $comparison = null)
    {
        if ($adminGroup instanceof AdminGroup) {
            return $this
                ->addUsingAlias(GroupPeer::ID, $adminGroup->getGroupId(), $comparison);
        } elseif ($adminGroup instanceof PropelObjectCollection) {
            return $this
                ->useAdminGroupQuery()
                ->filterByPrimaryKeys($adminGroup->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByAdminGroup() only accepts arguments of type AdminGroup or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the AdminGroup relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return GroupQuery The current query, for fluid interface
     */
    public function joinAdminGroup($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
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
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\AdminGroupQuery A secondary query class using the current class as primary query
     */
    public function useAdminGroupQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinAdminGroup($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'AdminGroup', '\Thelia\Model\AdminGroupQuery');
    }

    /**
     * Filter the query by a related GroupResource object
     *
     * @param   GroupResource|PropelObjectCollection $groupResource  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   GroupQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByGroupResource($groupResource, $comparison = null)
    {
        if ($groupResource instanceof GroupResource) {
            return $this
                ->addUsingAlias(GroupPeer::ID, $groupResource->getGroupId(), $comparison);
        } elseif ($groupResource instanceof PropelObjectCollection) {
            return $this
                ->useGroupResourceQuery()
                ->filterByPrimaryKeys($groupResource->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByGroupResource() only accepts arguments of type GroupResource or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the GroupResource relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return GroupQuery The current query, for fluid interface
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
     * @see       useQuery()
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
     * Filter the query by a related GroupModule object
     *
     * @param   GroupModule|PropelObjectCollection $groupModule  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   GroupQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByGroupModule($groupModule, $comparison = null)
    {
        if ($groupModule instanceof GroupModule) {
            return $this
                ->addUsingAlias(GroupPeer::ID, $groupModule->getGroupId(), $comparison);
        } elseif ($groupModule instanceof PropelObjectCollection) {
            return $this
                ->useGroupModuleQuery()
                ->filterByPrimaryKeys($groupModule->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByGroupModule() only accepts arguments of type GroupModule or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the GroupModule relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return GroupQuery The current query, for fluid interface
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
     * @see       useQuery()
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
     * Exclude object from result
     *
     * @param   Group $group Object to remove from the list of results
     *
     * @return GroupQuery The current query, for fluid interface
     */
    public function prune($group = null)
    {
        if ($group) {
            $this->addUsingAlias(GroupPeer::ID, $group->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    // timestampable behavior

    /**
     * Filter by the latest updated
     *
     * @param      int $nbDays Maximum age of the latest update in days
     *
     * @return     GroupQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(GroupPeer::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     GroupQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(GroupPeer::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     GroupQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(GroupPeer::UPDATED_AT);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     GroupQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(GroupPeer::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by create date desc
     *
     * @return     GroupQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(GroupPeer::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     GroupQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(GroupPeer::CREATED_AT);
    }
}
