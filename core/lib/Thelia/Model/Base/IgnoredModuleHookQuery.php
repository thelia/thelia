<?php

namespace Thelia\Model\Base;

use \Exception;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveQuery\ModelJoin;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\IgnoredModuleHook as ChildIgnoredModuleHook;
use Thelia\Model\IgnoredModuleHookQuery as ChildIgnoredModuleHookQuery;
use Thelia\Model\Map\IgnoredModuleHookTableMap;

/**
 * Base class that represents a query for the 'ignored_module_hook' table.
 *
 *
 *
 * @method     ChildIgnoredModuleHookQuery orderByModuleId($order = Criteria::ASC) Order by the module_id column
 * @method     ChildIgnoredModuleHookQuery orderByHookId($order = Criteria::ASC) Order by the hook_id column
 * @method     ChildIgnoredModuleHookQuery orderByMethod($order = Criteria::ASC) Order by the method column
 * @method     ChildIgnoredModuleHookQuery orderByClassname($order = Criteria::ASC) Order by the classname column
 *
 * @method     ChildIgnoredModuleHookQuery groupByModuleId() Group by the module_id column
 * @method     ChildIgnoredModuleHookQuery groupByHookId() Group by the hook_id column
 * @method     ChildIgnoredModuleHookQuery groupByMethod() Group by the method column
 * @method     ChildIgnoredModuleHookQuery groupByClassname() Group by the classname column
 *
 * @method     ChildIgnoredModuleHookQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildIgnoredModuleHookQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildIgnoredModuleHookQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildIgnoredModuleHookQuery leftJoinModule($relationAlias = null) Adds a LEFT JOIN clause to the query using the Module relation
 * @method     ChildIgnoredModuleHookQuery rightJoinModule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Module relation
 * @method     ChildIgnoredModuleHookQuery innerJoinModule($relationAlias = null) Adds a INNER JOIN clause to the query using the Module relation
 *
 * @method     ChildIgnoredModuleHookQuery leftJoinHook($relationAlias = null) Adds a LEFT JOIN clause to the query using the Hook relation
 * @method     ChildIgnoredModuleHookQuery rightJoinHook($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Hook relation
 * @method     ChildIgnoredModuleHookQuery innerJoinHook($relationAlias = null) Adds a INNER JOIN clause to the query using the Hook relation
 *
 * @method     ChildIgnoredModuleHook findOne(ConnectionInterface $con = null) Return the first ChildIgnoredModuleHook matching the query
 * @method     ChildIgnoredModuleHook findOneOrCreate(ConnectionInterface $con = null) Return the first ChildIgnoredModuleHook matching the query, or a new ChildIgnoredModuleHook object populated from the query conditions when no match is found
 *
 * @method     ChildIgnoredModuleHook findOneByModuleId(int $module_id) Return the first ChildIgnoredModuleHook filtered by the module_id column
 * @method     ChildIgnoredModuleHook findOneByHookId(int $hook_id) Return the first ChildIgnoredModuleHook filtered by the hook_id column
 * @method     ChildIgnoredModuleHook findOneByMethod(string $method) Return the first ChildIgnoredModuleHook filtered by the method column
 * @method     ChildIgnoredModuleHook findOneByClassname(string $classname) Return the first ChildIgnoredModuleHook filtered by the classname column
 *
 * @method     array findByModuleId(int $module_id) Return ChildIgnoredModuleHook objects filtered by the module_id column
 * @method     array findByHookId(int $hook_id) Return ChildIgnoredModuleHook objects filtered by the hook_id column
 * @method     array findByMethod(string $method) Return ChildIgnoredModuleHook objects filtered by the method column
 * @method     array findByClassname(string $classname) Return ChildIgnoredModuleHook objects filtered by the classname column
 *
 */
abstract class IgnoredModuleHookQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\IgnoredModuleHookQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\IgnoredModuleHook', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildIgnoredModuleHookQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildIgnoredModuleHookQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\IgnoredModuleHookQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\IgnoredModuleHookQuery();
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
     * @return ChildIgnoredModuleHook|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        throw new \LogicException('The ChildIgnoredModuleHook class has no primary key');
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
        throw new \LogicException('The ChildIgnoredModuleHook class has no primary key');
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return ChildIgnoredModuleHookQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        throw new \LogicException('The ChildIgnoredModuleHook class has no primary key');
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildIgnoredModuleHookQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        throw new \LogicException('The ChildIgnoredModuleHook class has no primary key');
    }

    /**
     * Filter the query on the module_id column
     *
     * Example usage:
     * <code>
     * $query->filterByModuleId(1234); // WHERE module_id = 1234
     * $query->filterByModuleId(array(12, 34)); // WHERE module_id IN (12, 34)
     * $query->filterByModuleId(array('min' => 12)); // WHERE module_id > 12
     * </code>
     *
     * @see       filterByModule()
     *
     * @param     mixed $moduleId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildIgnoredModuleHookQuery The current query, for fluid interface
     */
    public function filterByModuleId($moduleId = null, $comparison = null)
    {
        if (is_array($moduleId)) {
            $useMinMax = false;
            if (isset($moduleId['min'])) {
                $this->addUsingAlias(IgnoredModuleHookTableMap::MODULE_ID, $moduleId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($moduleId['max'])) {
                $this->addUsingAlias(IgnoredModuleHookTableMap::MODULE_ID, $moduleId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(IgnoredModuleHookTableMap::MODULE_ID, $moduleId, $comparison);
    }

    /**
     * Filter the query on the hook_id column
     *
     * Example usage:
     * <code>
     * $query->filterByHookId(1234); // WHERE hook_id = 1234
     * $query->filterByHookId(array(12, 34)); // WHERE hook_id IN (12, 34)
     * $query->filterByHookId(array('min' => 12)); // WHERE hook_id > 12
     * </code>
     *
     * @see       filterByHook()
     *
     * @param     mixed $hookId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildIgnoredModuleHookQuery The current query, for fluid interface
     */
    public function filterByHookId($hookId = null, $comparison = null)
    {
        if (is_array($hookId)) {
            $useMinMax = false;
            if (isset($hookId['min'])) {
                $this->addUsingAlias(IgnoredModuleHookTableMap::HOOK_ID, $hookId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($hookId['max'])) {
                $this->addUsingAlias(IgnoredModuleHookTableMap::HOOK_ID, $hookId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(IgnoredModuleHookTableMap::HOOK_ID, $hookId, $comparison);
    }

    /**
     * Filter the query on the method column
     *
     * Example usage:
     * <code>
     * $query->filterByMethod('fooValue');   // WHERE method = 'fooValue'
     * $query->filterByMethod('%fooValue%'); // WHERE method LIKE '%fooValue%'
     * </code>
     *
     * @param     string $method The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildIgnoredModuleHookQuery The current query, for fluid interface
     */
    public function filterByMethod($method = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($method)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $method)) {
                $method = str_replace('*', '%', $method);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(IgnoredModuleHookTableMap::METHOD, $method, $comparison);
    }

    /**
     * Filter the query on the classname column
     *
     * Example usage:
     * <code>
     * $query->filterByClassname('fooValue');   // WHERE classname = 'fooValue'
     * $query->filterByClassname('%fooValue%'); // WHERE classname LIKE '%fooValue%'
     * </code>
     *
     * @param     string $classname The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildIgnoredModuleHookQuery The current query, for fluid interface
     */
    public function filterByClassname($classname = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($classname)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $classname)) {
                $classname = str_replace('*', '%', $classname);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(IgnoredModuleHookTableMap::CLASSNAME, $classname, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Module object
     *
     * @param \Thelia\Model\Module|ObjectCollection $module The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildIgnoredModuleHookQuery The current query, for fluid interface
     */
    public function filterByModule($module, $comparison = null)
    {
        if ($module instanceof \Thelia\Model\Module) {
            return $this
                ->addUsingAlias(IgnoredModuleHookTableMap::MODULE_ID, $module->getId(), $comparison);
        } elseif ($module instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(IgnoredModuleHookTableMap::MODULE_ID, $module->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByModule() only accepts arguments of type \Thelia\Model\Module or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Module relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildIgnoredModuleHookQuery The current query, for fluid interface
     */
    public function joinModule($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Module');

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
            $this->addJoinObject($join, 'Module');
        }

        return $this;
    }

    /**
     * Use the Module relation Module object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ModuleQuery A secondary query class using the current class as primary query
     */
    public function useModuleQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinModule($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Module', '\Thelia\Model\ModuleQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Hook object
     *
     * @param \Thelia\Model\Hook|ObjectCollection $hook The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildIgnoredModuleHookQuery The current query, for fluid interface
     */
    public function filterByHook($hook, $comparison = null)
    {
        if ($hook instanceof \Thelia\Model\Hook) {
            return $this
                ->addUsingAlias(IgnoredModuleHookTableMap::HOOK_ID, $hook->getId(), $comparison);
        } elseif ($hook instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(IgnoredModuleHookTableMap::HOOK_ID, $hook->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByHook() only accepts arguments of type \Thelia\Model\Hook or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Hook relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildIgnoredModuleHookQuery The current query, for fluid interface
     */
    public function joinHook($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Hook');

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
            $this->addJoinObject($join, 'Hook');
        }

        return $this;
    }

    /**
     * Use the Hook relation Hook object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\HookQuery A secondary query class using the current class as primary query
     */
    public function useHookQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinHook($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Hook', '\Thelia\Model\HookQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildIgnoredModuleHook $ignoredModuleHook Object to remove from the list of results
     *
     * @return ChildIgnoredModuleHookQuery The current query, for fluid interface
     */
    public function prune($ignoredModuleHook = null)
    {
        if ($ignoredModuleHook) {
            throw new \LogicException('ChildIgnoredModuleHook class has no primary key');

        }

        return $this;
    }

    /**
     * Deletes all rows from the ignored_module_hook table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(IgnoredModuleHookTableMap::DATABASE_NAME);
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
            IgnoredModuleHookTableMap::clearInstancePool();
            IgnoredModuleHookTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildIgnoredModuleHook or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildIgnoredModuleHook object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(IgnoredModuleHookTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(IgnoredModuleHookTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        IgnoredModuleHookTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            IgnoredModuleHookTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

} // IgnoredModuleHookQuery
