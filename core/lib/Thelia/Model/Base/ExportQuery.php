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
use Thelia\Model\Export as ChildExport;
use Thelia\Model\ExportI18nQuery as ChildExportI18nQuery;
use Thelia\Model\ExportQuery as ChildExportQuery;
use Thelia\Model\Map\ExportTableMap;

/**
 * Base class that represents a query for the 'export' table.
 *
 *
 *
 * @method     ChildExportQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildExportQuery orderByRef($order = Criteria::ASC) Order by the ref column
 * @method     ChildExportQuery orderByPosition($order = Criteria::ASC) Order by the position column
 * @method     ChildExportQuery orderByExportCategoryId($order = Criteria::ASC) Order by the export_category_id column
 * @method     ChildExportQuery orderByHandleClass($order = Criteria::ASC) Order by the handle_class column
 * @method     ChildExportQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildExportQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildExportQuery groupById() Group by the id column
 * @method     ChildExportQuery groupByRef() Group by the ref column
 * @method     ChildExportQuery groupByPosition() Group by the position column
 * @method     ChildExportQuery groupByExportCategoryId() Group by the export_category_id column
 * @method     ChildExportQuery groupByHandleClass() Group by the handle_class column
 * @method     ChildExportQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildExportQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildExportQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildExportQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildExportQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildExportQuery leftJoinExportCategory($relationAlias = null) Adds a LEFT JOIN clause to the query using the ExportCategory relation
 * @method     ChildExportQuery rightJoinExportCategory($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ExportCategory relation
 * @method     ChildExportQuery innerJoinExportCategory($relationAlias = null) Adds a INNER JOIN clause to the query using the ExportCategory relation
 *
 * @method     ChildExportQuery leftJoinExportI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the ExportI18n relation
 * @method     ChildExportQuery rightJoinExportI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ExportI18n relation
 * @method     ChildExportQuery innerJoinExportI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the ExportI18n relation
 *
 * @method     ChildExport findOne(ConnectionInterface $con = null) Return the first ChildExport matching the query
 * @method     ChildExport findOneOrCreate(ConnectionInterface $con = null) Return the first ChildExport matching the query, or a new ChildExport object populated from the query conditions when no match is found
 *
 * @method     ChildExport findOneById(int $id) Return the first ChildExport filtered by the id column
 * @method     ChildExport findOneByRef(string $ref) Return the first ChildExport filtered by the ref column
 * @method     ChildExport findOneByPosition(int $position) Return the first ChildExport filtered by the position column
 * @method     ChildExport findOneByExportCategoryId(int $export_category_id) Return the first ChildExport filtered by the export_category_id column
 * @method     ChildExport findOneByHandleClass(string $handle_class) Return the first ChildExport filtered by the handle_class column
 * @method     ChildExport findOneByCreatedAt(string $created_at) Return the first ChildExport filtered by the created_at column
 * @method     ChildExport findOneByUpdatedAt(string $updated_at) Return the first ChildExport filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildExport objects filtered by the id column
 * @method     array findByRef(string $ref) Return ChildExport objects filtered by the ref column
 * @method     array findByPosition(int $position) Return ChildExport objects filtered by the position column
 * @method     array findByExportCategoryId(int $export_category_id) Return ChildExport objects filtered by the export_category_id column
 * @method     array findByHandleClass(string $handle_class) Return ChildExport objects filtered by the handle_class column
 * @method     array findByCreatedAt(string $created_at) Return ChildExport objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildExport objects filtered by the updated_at column
 *
 */
abstract class ExportQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\ExportQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Export', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildExportQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildExportQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\ExportQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\ExportQuery();
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
     * @return ChildExport|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = ExportTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(ExportTableMap::DATABASE_NAME);
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
     * @return   ChildExport A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `REF`, `POSITION`, `EXPORT_CATEGORY_ID`, `HANDLE_CLASS`, `CREATED_AT`, `UPDATED_AT` FROM `export` WHERE `ID` = :p0';
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
            $obj = new ChildExport();
            $obj->hydrate($row);
            ExportTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildExport|array|mixed the result, formatted by the current formatter
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
     * @return ChildExportQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(ExportTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildExportQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(ExportTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildExportQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(ExportTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(ExportTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ExportTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the ref column
     *
     * Example usage:
     * <code>
     * $query->filterByRef('fooValue');   // WHERE ref = 'fooValue'
     * $query->filterByRef('%fooValue%'); // WHERE ref LIKE '%fooValue%'
     * </code>
     *
     * @param     string $ref The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildExportQuery The current query, for fluid interface
     */
    public function filterByRef($ref = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($ref)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $ref)) {
                $ref = str_replace('*', '%', $ref);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(ExportTableMap::REF, $ref, $comparison);
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
     * @return ChildExportQuery The current query, for fluid interface
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        if (is_array($position)) {
            $useMinMax = false;
            if (isset($position['min'])) {
                $this->addUsingAlias(ExportTableMap::POSITION, $position['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($position['max'])) {
                $this->addUsingAlias(ExportTableMap::POSITION, $position['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ExportTableMap::POSITION, $position, $comparison);
    }

    /**
     * Filter the query on the export_category_id column
     *
     * Example usage:
     * <code>
     * $query->filterByExportCategoryId(1234); // WHERE export_category_id = 1234
     * $query->filterByExportCategoryId(array(12, 34)); // WHERE export_category_id IN (12, 34)
     * $query->filterByExportCategoryId(array('min' => 12)); // WHERE export_category_id > 12
     * </code>
     *
     * @see       filterByExportCategory()
     *
     * @param     mixed $exportCategoryId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildExportQuery The current query, for fluid interface
     */
    public function filterByExportCategoryId($exportCategoryId = null, $comparison = null)
    {
        if (is_array($exportCategoryId)) {
            $useMinMax = false;
            if (isset($exportCategoryId['min'])) {
                $this->addUsingAlias(ExportTableMap::EXPORT_CATEGORY_ID, $exportCategoryId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($exportCategoryId['max'])) {
                $this->addUsingAlias(ExportTableMap::EXPORT_CATEGORY_ID, $exportCategoryId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ExportTableMap::EXPORT_CATEGORY_ID, $exportCategoryId, $comparison);
    }

    /**
     * Filter the query on the handle_class column
     *
     * Example usage:
     * <code>
     * $query->filterByHandleClass('fooValue');   // WHERE handle_class = 'fooValue'
     * $query->filterByHandleClass('%fooValue%'); // WHERE handle_class LIKE '%fooValue%'
     * </code>
     *
     * @param     string $handleClass The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildExportQuery The current query, for fluid interface
     */
    public function filterByHandleClass($handleClass = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($handleClass)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $handleClass)) {
                $handleClass = str_replace('*', '%', $handleClass);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(ExportTableMap::HANDLE_CLASS, $handleClass, $comparison);
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
     * @return ChildExportQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(ExportTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(ExportTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ExportTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildExportQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(ExportTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(ExportTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ExportTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\ExportCategory object
     *
     * @param \Thelia\Model\ExportCategory|ObjectCollection $exportCategory The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildExportQuery The current query, for fluid interface
     */
    public function filterByExportCategory($exportCategory, $comparison = null)
    {
        if ($exportCategory instanceof \Thelia\Model\ExportCategory) {
            return $this
                ->addUsingAlias(ExportTableMap::EXPORT_CATEGORY_ID, $exportCategory->getId(), $comparison);
        } elseif ($exportCategory instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(ExportTableMap::EXPORT_CATEGORY_ID, $exportCategory->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByExportCategory() only accepts arguments of type \Thelia\Model\ExportCategory or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ExportCategory relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildExportQuery The current query, for fluid interface
     */
    public function joinExportCategory($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ExportCategory');

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
            $this->addJoinObject($join, 'ExportCategory');
        }

        return $this;
    }

    /**
     * Use the ExportCategory relation ExportCategory object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ExportCategoryQuery A secondary query class using the current class as primary query
     */
    public function useExportCategoryQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinExportCategory($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ExportCategory', '\Thelia\Model\ExportCategoryQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ExportI18n object
     *
     * @param \Thelia\Model\ExportI18n|ObjectCollection $exportI18n  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildExportQuery The current query, for fluid interface
     */
    public function filterByExportI18n($exportI18n, $comparison = null)
    {
        if ($exportI18n instanceof \Thelia\Model\ExportI18n) {
            return $this
                ->addUsingAlias(ExportTableMap::ID, $exportI18n->getId(), $comparison);
        } elseif ($exportI18n instanceof ObjectCollection) {
            return $this
                ->useExportI18nQuery()
                ->filterByPrimaryKeys($exportI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByExportI18n() only accepts arguments of type \Thelia\Model\ExportI18n or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ExportI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildExportQuery The current query, for fluid interface
     */
    public function joinExportI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ExportI18n');

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
            $this->addJoinObject($join, 'ExportI18n');
        }

        return $this;
    }

    /**
     * Use the ExportI18n relation ExportI18n object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ExportI18nQuery A secondary query class using the current class as primary query
     */
    public function useExportI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinExportI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ExportI18n', '\Thelia\Model\ExportI18nQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildExport $export Object to remove from the list of results
     *
     * @return ChildExportQuery The current query, for fluid interface
     */
    public function prune($export = null)
    {
        if ($export) {
            $this->addUsingAlias(ExportTableMap::ID, $export->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the export table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ExportTableMap::DATABASE_NAME);
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
            ExportTableMap::clearInstancePool();
            ExportTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildExport or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildExport object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(ExportTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(ExportTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        ExportTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            ExportTableMap::clearRelatedInstancePool();
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
     * @return     ChildExportQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(ExportTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildExportQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(ExportTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildExportQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(ExportTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildExportQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(ExportTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildExportQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(ExportTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildExportQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(ExportTableMap::CREATED_AT);
    }

    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildExportQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'ExportI18n';

        return $this
            ->joinExportI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildExportQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('ExportI18n');
        $this->with['ExportI18n']->setIsWithOneToMany(false);

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
     * @return    ChildExportI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ExportI18n', '\Thelia\Model\ExportI18nQuery');
    }

} // ExportQuery
