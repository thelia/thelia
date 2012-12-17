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
use Thelia\Model\Group;
use Thelia\Model\GroupResource;
use Thelia\Model\GroupResourcePeer;
use Thelia\Model\GroupResourceQuery;
use Thelia\Model\Resource;

/**
 * Base class that represents a query for the 'group_resource' table.
 *
 *
 *
 * @method GroupResourceQuery orderById($order = Criteria::ASC) Order by the id column
 * @method GroupResourceQuery orderByGroupId($order = Criteria::ASC) Order by the group_id column
 * @method GroupResourceQuery orderByResourceId($order = Criteria::ASC) Order by the resource_id column
 * @method GroupResourceQuery orderByRead($order = Criteria::ASC) Order by the read column
 * @method GroupResourceQuery orderByWrite($order = Criteria::ASC) Order by the write column
 * @method GroupResourceQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method GroupResourceQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method GroupResourceQuery groupById() Group by the id column
 * @method GroupResourceQuery groupByGroupId() Group by the group_id column
 * @method GroupResourceQuery groupByResourceId() Group by the resource_id column
 * @method GroupResourceQuery groupByRead() Group by the read column
 * @method GroupResourceQuery groupByWrite() Group by the write column
 * @method GroupResourceQuery groupByCreatedAt() Group by the created_at column
 * @method GroupResourceQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method GroupResourceQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method GroupResourceQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method GroupResourceQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method GroupResourceQuery leftJoinGroup($relationAlias = null) Adds a LEFT JOIN clause to the query using the Group relation
 * @method GroupResourceQuery rightJoinGroup($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Group relation
 * @method GroupResourceQuery innerJoinGroup($relationAlias = null) Adds a INNER JOIN clause to the query using the Group relation
 *
 * @method GroupResourceQuery leftJoinResource($relationAlias = null) Adds a LEFT JOIN clause to the query using the Resource relation
 * @method GroupResourceQuery rightJoinResource($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Resource relation
 * @method GroupResourceQuery innerJoinResource($relationAlias = null) Adds a INNER JOIN clause to the query using the Resource relation
 *
 * @method GroupResource findOne(PropelPDO $con = null) Return the first GroupResource matching the query
 * @method GroupResource findOneOrCreate(PropelPDO $con = null) Return the first GroupResource matching the query, or a new GroupResource object populated from the query conditions when no match is found
 *
 * @method GroupResource findOneById(int $id) Return the first GroupResource filtered by the id column
 * @method GroupResource findOneByGroupId(int $group_id) Return the first GroupResource filtered by the group_id column
 * @method GroupResource findOneByResourceId(int $resource_id) Return the first GroupResource filtered by the resource_id column
 * @method GroupResource findOneByRead(int $read) Return the first GroupResource filtered by the read column
 * @method GroupResource findOneByWrite(int $write) Return the first GroupResource filtered by the write column
 * @method GroupResource findOneByCreatedAt(string $created_at) Return the first GroupResource filtered by the created_at column
 * @method GroupResource findOneByUpdatedAt(string $updated_at) Return the first GroupResource filtered by the updated_at column
 *
 * @method array findById(int $id) Return GroupResource objects filtered by the id column
 * @method array findByGroupId(int $group_id) Return GroupResource objects filtered by the group_id column
 * @method array findByResourceId(int $resource_id) Return GroupResource objects filtered by the resource_id column
 * @method array findByRead(int $read) Return GroupResource objects filtered by the read column
 * @method array findByWrite(int $write) Return GroupResource objects filtered by the write column
 * @method array findByCreatedAt(string $created_at) Return GroupResource objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return GroupResource objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseGroupResourceQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseGroupResourceQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\GroupResource', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new GroupResourceQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     GroupResourceQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return GroupResourceQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof GroupResourceQuery) {
            return $criteria;
        }
        $query = new GroupResourceQuery();
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
     * @return   GroupResource|GroupResource[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = GroupResourcePeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(GroupResourcePeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return   GroupResource A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `GROUP_ID`, `RESOURCE_ID`, `READ`, `WRITE`, `CREATED_AT`, `UPDATED_AT` FROM `group_resource` WHERE `ID` = :p0';
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
            $obj = new GroupResource();
            $obj->hydrate($row);
            GroupResourcePeer::addInstanceToPool($obj, (string) $key);
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
     * @return GroupResource|GroupResource[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|GroupResource[]|mixed the list of results, formatted by the current formatter
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
     * @return GroupResourceQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(GroupResourcePeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return GroupResourceQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(GroupResourcePeer::ID, $keys, Criteria::IN);
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
     * @return GroupResourceQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(GroupResourcePeer::ID, $id, $comparison);
    }

    /**
     * Filter the query on the group_id column
     *
     * Example usage:
     * <code>
     * $query->filterByGroupId(1234); // WHERE group_id = 1234
     * $query->filterByGroupId(array(12, 34)); // WHERE group_id IN (12, 34)
     * $query->filterByGroupId(array('min' => 12)); // WHERE group_id > 12
     * </code>
     *
     * @see       filterByGroup()
     *
     * @param     mixed $groupId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return GroupResourceQuery The current query, for fluid interface
     */
    public function filterByGroupId($groupId = null, $comparison = null)
    {
        if (is_array($groupId)) {
            $useMinMax = false;
            if (isset($groupId['min'])) {
                $this->addUsingAlias(GroupResourcePeer::GROUP_ID, $groupId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($groupId['max'])) {
                $this->addUsingAlias(GroupResourcePeer::GROUP_ID, $groupId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupResourcePeer::GROUP_ID, $groupId, $comparison);
    }

    /**
     * Filter the query on the resource_id column
     *
     * Example usage:
     * <code>
     * $query->filterByResourceId(1234); // WHERE resource_id = 1234
     * $query->filterByResourceId(array(12, 34)); // WHERE resource_id IN (12, 34)
     * $query->filterByResourceId(array('min' => 12)); // WHERE resource_id > 12
     * </code>
     *
     * @see       filterByResource()
     *
     * @param     mixed $resourceId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return GroupResourceQuery The current query, for fluid interface
     */
    public function filterByResourceId($resourceId = null, $comparison = null)
    {
        if (is_array($resourceId)) {
            $useMinMax = false;
            if (isset($resourceId['min'])) {
                $this->addUsingAlias(GroupResourcePeer::RESOURCE_ID, $resourceId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($resourceId['max'])) {
                $this->addUsingAlias(GroupResourcePeer::RESOURCE_ID, $resourceId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupResourcePeer::RESOURCE_ID, $resourceId, $comparison);
    }

    /**
     * Filter the query on the read column
     *
     * Example usage:
     * <code>
     * $query->filterByRead(1234); // WHERE read = 1234
     * $query->filterByRead(array(12, 34)); // WHERE read IN (12, 34)
     * $query->filterByRead(array('min' => 12)); // WHERE read > 12
     * </code>
     *
     * @param     mixed $read The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return GroupResourceQuery The current query, for fluid interface
     */
    public function filterByRead($read = null, $comparison = null)
    {
        if (is_array($read)) {
            $useMinMax = false;
            if (isset($read['min'])) {
                $this->addUsingAlias(GroupResourcePeer::READ, $read['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($read['max'])) {
                $this->addUsingAlias(GroupResourcePeer::READ, $read['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupResourcePeer::READ, $read, $comparison);
    }

    /**
     * Filter the query on the write column
     *
     * Example usage:
     * <code>
     * $query->filterByWrite(1234); // WHERE write = 1234
     * $query->filterByWrite(array(12, 34)); // WHERE write IN (12, 34)
     * $query->filterByWrite(array('min' => 12)); // WHERE write > 12
     * </code>
     *
     * @param     mixed $write The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return GroupResourceQuery The current query, for fluid interface
     */
    public function filterByWrite($write = null, $comparison = null)
    {
        if (is_array($write)) {
            $useMinMax = false;
            if (isset($write['min'])) {
                $this->addUsingAlias(GroupResourcePeer::WRITE, $write['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($write['max'])) {
                $this->addUsingAlias(GroupResourcePeer::WRITE, $write['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupResourcePeer::WRITE, $write, $comparison);
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
     * @return GroupResourceQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(GroupResourcePeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(GroupResourcePeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupResourcePeer::CREATED_AT, $createdAt, $comparison);
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
     * @return GroupResourceQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(GroupResourcePeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(GroupResourcePeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(GroupResourcePeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related Group object
     *
     * @param   Group|PropelObjectCollection $group The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   GroupResourceQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByGroup($group, $comparison = null)
    {
        if ($group instanceof Group) {
            return $this
                ->addUsingAlias(GroupResourcePeer::GROUP_ID, $group->getId(), $comparison);
        } elseif ($group instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(GroupResourcePeer::GROUP_ID, $group->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByGroup() only accepts arguments of type Group or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Group relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return GroupResourceQuery The current query, for fluid interface
     */
    public function joinGroup($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Group');

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
            $this->addJoinObject($join, 'Group');
        }

        return $this;
    }

    /**
     * Use the Group relation Group object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\GroupQuery A secondary query class using the current class as primary query
     */
    public function useGroupQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinGroup($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Group', '\Thelia\Model\GroupQuery');
    }

    /**
     * Filter the query by a related Resource object
     *
     * @param   Resource|PropelObjectCollection $resource The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   GroupResourceQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByResource($resource, $comparison = null)
    {
        if ($resource instanceof Resource) {
            return $this
                ->addUsingAlias(GroupResourcePeer::RESOURCE_ID, $resource->getId(), $comparison);
        } elseif ($resource instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(GroupResourcePeer::RESOURCE_ID, $resource->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByResource() only accepts arguments of type Resource or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Resource relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return GroupResourceQuery The current query, for fluid interface
     */
    public function joinResource($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Resource');

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
            $this->addJoinObject($join, 'Resource');
        }

        return $this;
    }

    /**
     * Use the Resource relation Resource object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ResourceQuery A secondary query class using the current class as primary query
     */
    public function useResourceQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinResource($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Resource', '\Thelia\Model\ResourceQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   GroupResource $groupResource Object to remove from the list of results
     *
     * @return GroupResourceQuery The current query, for fluid interface
     */
    public function prune($groupResource = null)
    {
        if ($groupResource) {
            $this->addUsingAlias(GroupResourcePeer::ID, $groupResource->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

}
