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
use Thelia\Model\GroupModule;
use Thelia\Model\Module;
use Thelia\Model\ModuleI18n;
use Thelia\Model\ModulePeer;
use Thelia\Model\ModuleQuery;

/**
 * Base class that represents a query for the 'module' table.
 *
 *
 *
 * @method ModuleQuery orderById($order = Criteria::ASC) Order by the id column
 * @method ModuleQuery orderByCode($order = Criteria::ASC) Order by the code column
 * @method ModuleQuery orderBy type($order = Criteria::ASC) Order by the  type column
 * @method ModuleQuery orderByActivate($order = Criteria::ASC) Order by the activate column
 * @method ModuleQuery orderByPosition($order = Criteria::ASC) Order by the position column
 * @method ModuleQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method ModuleQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method ModuleQuery groupById() Group by the id column
 * @method ModuleQuery groupByCode() Group by the code column
 * @method ModuleQuery groupBy type() Group by the  type column
 * @method ModuleQuery groupByActivate() Group by the activate column
 * @method ModuleQuery groupByPosition() Group by the position column
 * @method ModuleQuery groupByCreatedAt() Group by the created_at column
 * @method ModuleQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method ModuleQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method ModuleQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method ModuleQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method ModuleQuery leftJoinGroupModule($relationAlias = null) Adds a LEFT JOIN clause to the query using the GroupModule relation
 * @method ModuleQuery rightJoinGroupModule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the GroupModule relation
 * @method ModuleQuery innerJoinGroupModule($relationAlias = null) Adds a INNER JOIN clause to the query using the GroupModule relation
 *
 * @method ModuleQuery leftJoinModuleI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the ModuleI18n relation
 * @method ModuleQuery rightJoinModuleI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ModuleI18n relation
 * @method ModuleQuery innerJoinModuleI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the ModuleI18n relation
 *
 * @method Module findOne(PropelPDO $con = null) Return the first Module matching the query
 * @method Module findOneOrCreate(PropelPDO $con = null) Return the first Module matching the query, or a new Module object populated from the query conditions when no match is found
 *
 * @method Module findOneByCode(string $code) Return the first Module filtered by the code column
 * @method Module findOneBy type(int $ type) Return the first Module filtered by the  type column
 * @method Module findOneByActivate(int $activate) Return the first Module filtered by the activate column
 * @method Module findOneByPosition(int $position) Return the first Module filtered by the position column
 * @method Module findOneByCreatedAt(string $created_at) Return the first Module filtered by the created_at column
 * @method Module findOneByUpdatedAt(string $updated_at) Return the first Module filtered by the updated_at column
 *
 * @method array findById(int $id) Return Module objects filtered by the id column
 * @method array findByCode(string $code) Return Module objects filtered by the code column
 * @method array findBy type(int $ type) Return Module objects filtered by the  type column
 * @method array findByActivate(int $activate) Return Module objects filtered by the activate column
 * @method array findByPosition(int $position) Return Module objects filtered by the position column
 * @method array findByCreatedAt(string $created_at) Return Module objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return Module objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseModuleQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseModuleQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\Module', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ModuleQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param   ModuleQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return ModuleQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof ModuleQuery) {
            return $criteria;
        }
        $query = new ModuleQuery();
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
     * @return   Module|Module[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = ModulePeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(ModulePeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * Alias of findPk to use instance pooling
     *
     * @param     mixed $key Primary key to use for the query
     * @param     PropelPDO $con A connection object
     *
     * @return                 Module A model object, or null if the key is not found
     * @throws PropelException
     */
     public function findOneById($key, $con = null)
     {
        return $this->findPk($key, $con);
     }

    /**
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     PropelPDO $con A connection object
     *
     * @return                 Module A model object, or null if the key is not found
     * @throws PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `id`, `code`, ` type`, `activate`, `position`, `created_at`, `updated_at` FROM `module` WHERE `id` = :p0';
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
            $obj = new Module();
            $obj->hydrate($row);
            ModulePeer::addInstanceToPool($obj, (string) $key);
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
     * @return Module|Module[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|Module[]|mixed the list of results, formatted by the current formatter
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
     * @return ModuleQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(ModulePeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ModuleQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(ModulePeer::ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the id column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE id = 1234
     * $query->filterById(array(12, 34)); // WHERE id IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE id >= 12
     * $query->filterById(array('max' => 12)); // WHERE id <= 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ModuleQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(ModulePeer::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(ModulePeer::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ModulePeer::ID, $id, $comparison);
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
     * @return ModuleQuery The current query, for fluid interface
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

        return $this->addUsingAlias(ModulePeer::CODE, $code, $comparison);
    }

    /**
     * Filter the query on the  type column
     *
     * Example usage:
     * <code>
     * $query->filterBy type(1234); // WHERE  type = 1234
     * $query->filterBy type(array(12, 34)); // WHERE  type IN (12, 34)
     * $query->filterBy type(array('min' => 12)); // WHERE  type >= 12
     * $query->filterBy type(array('max' => 12)); // WHERE  type <= 12
     * </code>
     *
     * @param     mixed $ type The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ModuleQuery The current query, for fluid interface
     */
    public function filterBy type($ type = null, $comparison = null)
    {
        if (is_array($ type)) {
            $useMinMax = false;
            if (isset($ type['min'])) {
                $this->addUsingAlias(ModulePeer:: TYPE, $ type['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($ type['max'])) {
                $this->addUsingAlias(ModulePeer:: TYPE, $ type['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ModulePeer:: TYPE, $ type, $comparison);
    }

    /**
     * Filter the query on the activate column
     *
     * Example usage:
     * <code>
     * $query->filterByActivate(1234); // WHERE activate = 1234
     * $query->filterByActivate(array(12, 34)); // WHERE activate IN (12, 34)
     * $query->filterByActivate(array('min' => 12)); // WHERE activate >= 12
     * $query->filterByActivate(array('max' => 12)); // WHERE activate <= 12
     * </code>
     *
     * @param     mixed $activate The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ModuleQuery The current query, for fluid interface
     */
    public function filterByActivate($activate = null, $comparison = null)
    {
        if (is_array($activate)) {
            $useMinMax = false;
            if (isset($activate['min'])) {
                $this->addUsingAlias(ModulePeer::ACTIVATE, $activate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($activate['max'])) {
                $this->addUsingAlias(ModulePeer::ACTIVATE, $activate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ModulePeer::ACTIVATE, $activate, $comparison);
    }

    /**
     * Filter the query on the position column
     *
     * Example usage:
     * <code>
     * $query->filterByPosition(1234); // WHERE position = 1234
     * $query->filterByPosition(array(12, 34)); // WHERE position IN (12, 34)
     * $query->filterByPosition(array('min' => 12)); // WHERE position >= 12
     * $query->filterByPosition(array('max' => 12)); // WHERE position <= 12
     * </code>
     *
     * @param     mixed $position The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ModuleQuery The current query, for fluid interface
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        if (is_array($position)) {
            $useMinMax = false;
            if (isset($position['min'])) {
                $this->addUsingAlias(ModulePeer::POSITION, $position['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($position['max'])) {
                $this->addUsingAlias(ModulePeer::POSITION, $position['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ModulePeer::POSITION, $position, $comparison);
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
     * @return ModuleQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(ModulePeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(ModulePeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ModulePeer::CREATED_AT, $createdAt, $comparison);
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
     * @return ModuleQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(ModulePeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(ModulePeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ModulePeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related GroupModule object
     *
     * @param   GroupModule|PropelObjectCollection $groupModule  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return                 ModuleQuery The current query, for fluid interface
     * @throws PropelException - if the provided filter is invalid.
     */
    public function filterByGroupModule($groupModule, $comparison = null)
    {
        if ($groupModule instanceof GroupModule) {
            return $this
                ->addUsingAlias(ModulePeer::ID, $groupModule->getModuleId(), $comparison);
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
     * @return ModuleQuery The current query, for fluid interface
     */
    public function joinGroupModule($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
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
    public function useGroupModuleQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinGroupModule($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'GroupModule', '\Thelia\Model\GroupModuleQuery');
    }

    /**
     * Filter the query by a related ModuleI18n object
     *
     * @param   ModuleI18n|PropelObjectCollection $moduleI18n  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return                 ModuleQuery The current query, for fluid interface
     * @throws PropelException - if the provided filter is invalid.
     */
    public function filterByModuleI18n($moduleI18n, $comparison = null)
    {
        if ($moduleI18n instanceof ModuleI18n) {
            return $this
                ->addUsingAlias(ModulePeer::ID, $moduleI18n->getId(), $comparison);
        } elseif ($moduleI18n instanceof PropelObjectCollection) {
            return $this
                ->useModuleI18nQuery()
                ->filterByPrimaryKeys($moduleI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByModuleI18n() only accepts arguments of type ModuleI18n or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ModuleI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ModuleQuery The current query, for fluid interface
     */
    public function joinModuleI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ModuleI18n');

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
            $this->addJoinObject($join, 'ModuleI18n');
        }

        return $this;
    }

    /**
     * Use the ModuleI18n relation ModuleI18n object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ModuleI18nQuery A secondary query class using the current class as primary query
     */
    public function useModuleI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinModuleI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ModuleI18n', '\Thelia\Model\ModuleI18nQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   Module $module Object to remove from the list of results
     *
     * @return ModuleQuery The current query, for fluid interface
     */
    public function prune($module = null)
    {
        if ($module) {
            $this->addUsingAlias(ModulePeer::ID, $module->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    // timestampable behavior

    /**
     * Filter by the latest updated
     *
     * @param      int $nbDays Maximum age of the latest update in days
     *
     * @return     ModuleQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(ModulePeer::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ModuleQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(ModulePeer::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ModuleQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(ModulePeer::UPDATED_AT);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ModuleQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(ModulePeer::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by create date desc
     *
     * @return     ModuleQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(ModulePeer::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ModuleQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(ModulePeer::CREATED_AT);
    }
    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ModuleQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'ModuleI18n';

        return $this
            ->joinModuleI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ModuleQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('ModuleI18n');
        $this->with['ModuleI18n']->setIsWithOneToMany(false);

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
     * @return    ModuleI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ModuleI18n', 'Thelia\Model\ModuleI18nQuery');
    }

}
