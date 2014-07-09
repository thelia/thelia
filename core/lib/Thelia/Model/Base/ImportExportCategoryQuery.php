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
use Thelia\Model\ImportExportCategory as ChildImportExportCategory;
use Thelia\Model\ImportExportCategoryI18nQuery as ChildImportExportCategoryI18nQuery;
use Thelia\Model\ImportExportCategoryQuery as ChildImportExportCategoryQuery;
use Thelia\Model\Map\ImportExportCategoryTableMap;

/**
 * Base class that represents a query for the 'import_export_category' table.
 *
 *
 *
 * @method     ChildImportExportCategoryQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildImportExportCategoryQuery orderByPosition($order = Criteria::ASC) Order by the position column
 * @method     ChildImportExportCategoryQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildImportExportCategoryQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildImportExportCategoryQuery groupById() Group by the id column
 * @method     ChildImportExportCategoryQuery groupByPosition() Group by the position column
 * @method     ChildImportExportCategoryQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildImportExportCategoryQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildImportExportCategoryQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildImportExportCategoryQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildImportExportCategoryQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildImportExportCategoryQuery leftJoinImportExportType($relationAlias = null) Adds a LEFT JOIN clause to the query using the ImportExportType relation
 * @method     ChildImportExportCategoryQuery rightJoinImportExportType($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ImportExportType relation
 * @method     ChildImportExportCategoryQuery innerJoinImportExportType($relationAlias = null) Adds a INNER JOIN clause to the query using the ImportExportType relation
 *
 * @method     ChildImportExportCategoryQuery leftJoinImportExportCategoryI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the ImportExportCategoryI18n relation
 * @method     ChildImportExportCategoryQuery rightJoinImportExportCategoryI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ImportExportCategoryI18n relation
 * @method     ChildImportExportCategoryQuery innerJoinImportExportCategoryI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the ImportExportCategoryI18n relation
 *
 * @method     ChildImportExportCategory findOne(ConnectionInterface $con = null) Return the first ChildImportExportCategory matching the query
 * @method     ChildImportExportCategory findOneOrCreate(ConnectionInterface $con = null) Return the first ChildImportExportCategory matching the query, or a new ChildImportExportCategory object populated from the query conditions when no match is found
 *
 * @method     ChildImportExportCategory findOneById(int $id) Return the first ChildImportExportCategory filtered by the id column
 * @method     ChildImportExportCategory findOneByPosition(int $position) Return the first ChildImportExportCategory filtered by the position column
 * @method     ChildImportExportCategory findOneByCreatedAt(string $created_at) Return the first ChildImportExportCategory filtered by the created_at column
 * @method     ChildImportExportCategory findOneByUpdatedAt(string $updated_at) Return the first ChildImportExportCategory filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildImportExportCategory objects filtered by the id column
 * @method     array findByPosition(int $position) Return ChildImportExportCategory objects filtered by the position column
 * @method     array findByCreatedAt(string $created_at) Return ChildImportExportCategory objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildImportExportCategory objects filtered by the updated_at column
 *
 */
abstract class ImportExportCategoryQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\ImportExportCategoryQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\ImportExportCategory', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildImportExportCategoryQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildImportExportCategoryQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\ImportExportCategoryQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\ImportExportCategoryQuery();
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
     * @return ChildImportExportCategory|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = ImportExportCategoryTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(ImportExportCategoryTableMap::DATABASE_NAME);
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
     * @return   ChildImportExportCategory A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `POSITION`, `CREATED_AT`, `UPDATED_AT` FROM `import_export_category` WHERE `ID` = :p0';
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
            $obj = new ChildImportExportCategory();
            $obj->hydrate($row);
            ImportExportCategoryTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildImportExportCategory|array|mixed the result, formatted by the current formatter
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
     * @return ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(ImportExportCategoryTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(ImportExportCategoryTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(ImportExportCategoryTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(ImportExportCategoryTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ImportExportCategoryTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the position column
     *
     * Example usage:
     * <code>
     * $query->filterByPosition(1234); // WHERE position = 1234
     * $query->filterByPosition(array(12, 34)); // WHERE position IN (12, 34)
     * $query->filterByPosition(array('min' => 12)); // WHERE position > 12
     * </code>
     *
     * @param     mixed $position The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        if (is_array($position)) {
            $useMinMax = false;
            if (isset($position['min'])) {
                $this->addUsingAlias(ImportExportCategoryTableMap::POSITION, $position['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($position['max'])) {
                $this->addUsingAlias(ImportExportCategoryTableMap::POSITION, $position['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ImportExportCategoryTableMap::POSITION, $position, $comparison);
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
     * @return ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(ImportExportCategoryTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(ImportExportCategoryTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ImportExportCategoryTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(ImportExportCategoryTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(ImportExportCategoryTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ImportExportCategoryTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\ImportExportType object
     *
     * @param \Thelia\Model\ImportExportType|ObjectCollection $importExportType  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function filterByImportExportType($importExportType, $comparison = null)
    {
        if ($importExportType instanceof \Thelia\Model\ImportExportType) {
            return $this
                ->addUsingAlias(ImportExportCategoryTableMap::ID, $importExportType->getImportExportCategoryId(), $comparison);
        } elseif ($importExportType instanceof ObjectCollection) {
            return $this
                ->useImportExportTypeQuery()
                ->filterByPrimaryKeys($importExportType->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByImportExportType() only accepts arguments of type \Thelia\Model\ImportExportType or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ImportExportType relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function joinImportExportType($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ImportExportType');

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
            $this->addJoinObject($join, 'ImportExportType');
        }

        return $this;
    }

    /**
     * Use the ImportExportType relation ImportExportType object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ImportExportTypeQuery A secondary query class using the current class as primary query
     */
    public function useImportExportTypeQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinImportExportType($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ImportExportType', '\Thelia\Model\ImportExportTypeQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ImportExportCategoryI18n object
     *
     * @param \Thelia\Model\ImportExportCategoryI18n|ObjectCollection $importExportCategoryI18n  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function filterByImportExportCategoryI18n($importExportCategoryI18n, $comparison = null)
    {
        if ($importExportCategoryI18n instanceof \Thelia\Model\ImportExportCategoryI18n) {
            return $this
                ->addUsingAlias(ImportExportCategoryTableMap::ID, $importExportCategoryI18n->getId(), $comparison);
        } elseif ($importExportCategoryI18n instanceof ObjectCollection) {
            return $this
                ->useImportExportCategoryI18nQuery()
                ->filterByPrimaryKeys($importExportCategoryI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByImportExportCategoryI18n() only accepts arguments of type \Thelia\Model\ImportExportCategoryI18n or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ImportExportCategoryI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function joinImportExportCategoryI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ImportExportCategoryI18n');

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
            $this->addJoinObject($join, 'ImportExportCategoryI18n');
        }

        return $this;
    }

    /**
     * Use the ImportExportCategoryI18n relation ImportExportCategoryI18n object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ImportExportCategoryI18nQuery A secondary query class using the current class as primary query
     */
    public function useImportExportCategoryI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinImportExportCategoryI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ImportExportCategoryI18n', '\Thelia\Model\ImportExportCategoryI18nQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildImportExportCategory $importExportCategory Object to remove from the list of results
     *
     * @return ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function prune($importExportCategory = null)
    {
        if ($importExportCategory) {
            $this->addUsingAlias(ImportExportCategoryTableMap::ID, $importExportCategory->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the import_export_category table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ImportExportCategoryTableMap::DATABASE_NAME);
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
            ImportExportCategoryTableMap::clearInstancePool();
            ImportExportCategoryTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildImportExportCategory or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildImportExportCategory object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(ImportExportCategoryTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(ImportExportCategoryTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        ImportExportCategoryTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            ImportExportCategoryTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'ImportExportCategoryI18n';

        return $this
            ->joinImportExportCategoryI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('ImportExportCategoryI18n');
        $this->with['ImportExportCategoryI18n']->setIsWithOneToMany(false);

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
     * @return    ChildImportExportCategoryI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ImportExportCategoryI18n', '\Thelia\Model\ImportExportCategoryI18nQuery');
    }

    // timestampable behavior

    /**
     * Filter by the latest updated
     *
     * @param      int $nbDays Maximum age of the latest update in days
     *
     * @return     ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(ImportExportCategoryTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(ImportExportCategoryTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(ImportExportCategoryTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(ImportExportCategoryTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(ImportExportCategoryTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildImportExportCategoryQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(ImportExportCategoryTableMap::CREATED_AT);
    }

} // ImportExportCategoryQuery
