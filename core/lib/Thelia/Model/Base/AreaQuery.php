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
use Thelia\Model\Area as ChildArea;
use Thelia\Model\AreaQuery as ChildAreaQuery;
use Thelia\Model\Map\AreaTableMap;

/**
 * Base class that represents a query for the 'area' table.
 *
 *
 *
 * @method     ChildAreaQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildAreaQuery orderByName($order = Criteria::ASC) Order by the name column
 * @method     ChildAreaQuery orderByPostage($order = Criteria::ASC) Order by the postage column
 * @method     ChildAreaQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildAreaQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildAreaQuery groupById() Group by the id column
 * @method     ChildAreaQuery groupByName() Group by the name column
 * @method     ChildAreaQuery groupByPostage() Group by the postage column
 * @method     ChildAreaQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildAreaQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildAreaQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildAreaQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildAreaQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildAreaQuery leftJoinAreaDeliveryModule($relationAlias = null) Adds a LEFT JOIN clause to the query using the AreaDeliveryModule relation
 * @method     ChildAreaQuery rightJoinAreaDeliveryModule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the AreaDeliveryModule relation
 * @method     ChildAreaQuery innerJoinAreaDeliveryModule($relationAlias = null) Adds a INNER JOIN clause to the query using the AreaDeliveryModule relation
 *
 * @method     ChildAreaQuery leftJoinCountryArea($relationAlias = null) Adds a LEFT JOIN clause to the query using the CountryArea relation
 * @method     ChildAreaQuery rightJoinCountryArea($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CountryArea relation
 * @method     ChildAreaQuery innerJoinCountryArea($relationAlias = null) Adds a INNER JOIN clause to the query using the CountryArea relation
 *
 * @method     ChildArea findOne(ConnectionInterface $con = null) Return the first ChildArea matching the query
 * @method     ChildArea findOneOrCreate(ConnectionInterface $con = null) Return the first ChildArea matching the query, or a new ChildArea object populated from the query conditions when no match is found
 *
 * @method     ChildArea findOneById(int $id) Return the first ChildArea filtered by the id column
 * @method     ChildArea findOneByName(string $name) Return the first ChildArea filtered by the name column
 * @method     ChildArea findOneByPostage(double $postage) Return the first ChildArea filtered by the postage column
 * @method     ChildArea findOneByCreatedAt(string $created_at) Return the first ChildArea filtered by the created_at column
 * @method     ChildArea findOneByUpdatedAt(string $updated_at) Return the first ChildArea filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildArea objects filtered by the id column
 * @method     array findByName(string $name) Return ChildArea objects filtered by the name column
 * @method     array findByPostage(double $postage) Return ChildArea objects filtered by the postage column
 * @method     array findByCreatedAt(string $created_at) Return ChildArea objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildArea objects filtered by the updated_at column
 *
 */
abstract class AreaQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\AreaQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Area', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildAreaQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildAreaQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\AreaQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\AreaQuery();
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
     * @return ChildArea|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = AreaTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(AreaTableMap::DATABASE_NAME);
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
     * @return   ChildArea A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `NAME`, `POSTAGE`, `CREATED_AT`, `UPDATED_AT` FROM `area` WHERE `ID` = :p0';
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
            $obj = new ChildArea();
            $obj->hydrate($row);
            AreaTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildArea|array|mixed the result, formatted by the current formatter
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
     * @return ChildAreaQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(AreaTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildAreaQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(AreaTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildAreaQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(AreaTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(AreaTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AreaTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the name column
     *
     * Example usage:
     * <code>
     * $query->filterByName('fooValue');   // WHERE name = 'fooValue'
     * $query->filterByName('%fooValue%'); // WHERE name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $name The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAreaQuery The current query, for fluid interface
     */
    public function filterByName($name = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($name)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $name)) {
                $name = str_replace('*', '%', $name);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(AreaTableMap::NAME, $name, $comparison);
    }

    /**
     * Filter the query on the postage column
     *
     * Example usage:
     * <code>
     * $query->filterByPostage(1234); // WHERE postage = 1234
     * $query->filterByPostage(array(12, 34)); // WHERE postage IN (12, 34)
     * $query->filterByPostage(array('min' => 12)); // WHERE postage > 12
     * </code>
     *
     * @param     mixed $postage The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAreaQuery The current query, for fluid interface
     */
    public function filterByPostage($postage = null, $comparison = null)
    {
        if (is_array($postage)) {
            $useMinMax = false;
            if (isset($postage['min'])) {
                $this->addUsingAlias(AreaTableMap::POSTAGE, $postage['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($postage['max'])) {
                $this->addUsingAlias(AreaTableMap::POSTAGE, $postage['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AreaTableMap::POSTAGE, $postage, $comparison);
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
     * @return ChildAreaQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(AreaTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(AreaTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AreaTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildAreaQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(AreaTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(AreaTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AreaTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\AreaDeliveryModule object
     *
     * @param \Thelia\Model\AreaDeliveryModule|ObjectCollection $areaDeliveryModule  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAreaQuery The current query, for fluid interface
     */
    public function filterByAreaDeliveryModule($areaDeliveryModule, $comparison = null)
    {
        if ($areaDeliveryModule instanceof \Thelia\Model\AreaDeliveryModule) {
            return $this
                ->addUsingAlias(AreaTableMap::ID, $areaDeliveryModule->getAreaId(), $comparison);
        } elseif ($areaDeliveryModule instanceof ObjectCollection) {
            return $this
                ->useAreaDeliveryModuleQuery()
                ->filterByPrimaryKeys($areaDeliveryModule->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByAreaDeliveryModule() only accepts arguments of type \Thelia\Model\AreaDeliveryModule or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the AreaDeliveryModule relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildAreaQuery The current query, for fluid interface
     */
    public function joinAreaDeliveryModule($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('AreaDeliveryModule');

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
            $this->addJoinObject($join, 'AreaDeliveryModule');
        }

        return $this;
    }

    /**
     * Use the AreaDeliveryModule relation AreaDeliveryModule object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\AreaDeliveryModuleQuery A secondary query class using the current class as primary query
     */
    public function useAreaDeliveryModuleQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinAreaDeliveryModule($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'AreaDeliveryModule', '\Thelia\Model\AreaDeliveryModuleQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\CountryArea object
     *
     * @param \Thelia\Model\CountryArea|ObjectCollection $countryArea  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAreaQuery The current query, for fluid interface
     */
    public function filterByCountryArea($countryArea, $comparison = null)
    {
        if ($countryArea instanceof \Thelia\Model\CountryArea) {
            return $this
                ->addUsingAlias(AreaTableMap::ID, $countryArea->getAreaId(), $comparison);
        } elseif ($countryArea instanceof ObjectCollection) {
            return $this
                ->useCountryAreaQuery()
                ->filterByPrimaryKeys($countryArea->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCountryArea() only accepts arguments of type \Thelia\Model\CountryArea or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CountryArea relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildAreaQuery The current query, for fluid interface
     */
    public function joinCountryArea($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CountryArea');

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
            $this->addJoinObject($join, 'CountryArea');
        }

        return $this;
    }

    /**
     * Use the CountryArea relation CountryArea object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CountryAreaQuery A secondary query class using the current class as primary query
     */
    public function useCountryAreaQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCountryArea($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CountryArea', '\Thelia\Model\CountryAreaQuery');
    }

    /**
     * Filter the query by a related Country object
     * using the country_area table as cross reference
     *
     * @param Country $country the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAreaQuery The current query, for fluid interface
     */
    public function filterByCountry($country, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useCountryAreaQuery()
            ->filterByCountry($country, $comparison)
            ->endUse();
    }

    /**
     * Exclude object from result
     *
     * @param   ChildArea $area Object to remove from the list of results
     *
     * @return ChildAreaQuery The current query, for fluid interface
     */
    public function prune($area = null)
    {
        if ($area) {
            $this->addUsingAlias(AreaTableMap::ID, $area->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the area table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(AreaTableMap::DATABASE_NAME);
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
            AreaTableMap::clearInstancePool();
            AreaTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildArea or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildArea object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(AreaTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(AreaTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        AreaTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            AreaTableMap::clearRelatedInstancePool();
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
     * @return     ChildAreaQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(AreaTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildAreaQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(AreaTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildAreaQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(AreaTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildAreaQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(AreaTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildAreaQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(AreaTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildAreaQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(AreaTableMap::CREATED_AT);
    }

} // AreaQuery
