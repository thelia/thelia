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
use Thelia\Model\Tax as ChildTax;
use Thelia\Model\TaxI18nQuery as ChildTaxI18nQuery;
use Thelia\Model\TaxQuery as ChildTaxQuery;
use Thelia\Model\Map\TaxTableMap;

/**
 * Base class that represents a query for the 'tax' table.
 *
 *
 *
 * @method     ChildTaxQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildTaxQuery orderByType($order = Criteria::ASC) Order by the type column
 * @method     ChildTaxQuery orderBySerializedRequirements($order = Criteria::ASC) Order by the serialized_requirements column
 * @method     ChildTaxQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildTaxQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildTaxQuery groupById() Group by the id column
 * @method     ChildTaxQuery groupByType() Group by the type column
 * @method     ChildTaxQuery groupBySerializedRequirements() Group by the serialized_requirements column
 * @method     ChildTaxQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildTaxQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildTaxQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildTaxQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildTaxQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildTaxQuery leftJoinTaxRuleCountry($relationAlias = null) Adds a LEFT JOIN clause to the query using the TaxRuleCountry relation
 * @method     ChildTaxQuery rightJoinTaxRuleCountry($relationAlias = null) Adds a RIGHT JOIN clause to the query using the TaxRuleCountry relation
 * @method     ChildTaxQuery innerJoinTaxRuleCountry($relationAlias = null) Adds a INNER JOIN clause to the query using the TaxRuleCountry relation
 *
 * @method     ChildTaxQuery leftJoinTaxI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the TaxI18n relation
 * @method     ChildTaxQuery rightJoinTaxI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the TaxI18n relation
 * @method     ChildTaxQuery innerJoinTaxI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the TaxI18n relation
 *
 * @method     ChildTax findOne(ConnectionInterface $con = null) Return the first ChildTax matching the query
 * @method     ChildTax findOneOrCreate(ConnectionInterface $con = null) Return the first ChildTax matching the query, or a new ChildTax object populated from the query conditions when no match is found
 *
 * @method     ChildTax findOneById(int $id) Return the first ChildTax filtered by the id column
 * @method     ChildTax findOneByType(string $type) Return the first ChildTax filtered by the type column
 * @method     ChildTax findOneBySerializedRequirements(string $serialized_requirements) Return the first ChildTax filtered by the serialized_requirements column
 * @method     ChildTax findOneByCreatedAt(string $created_at) Return the first ChildTax filtered by the created_at column
 * @method     ChildTax findOneByUpdatedAt(string $updated_at) Return the first ChildTax filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildTax objects filtered by the id column
 * @method     array findByType(string $type) Return ChildTax objects filtered by the type column
 * @method     array findBySerializedRequirements(string $serialized_requirements) Return ChildTax objects filtered by the serialized_requirements column
 * @method     array findByCreatedAt(string $created_at) Return ChildTax objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildTax objects filtered by the updated_at column
 *
 */
abstract class TaxQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\TaxQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Tax', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildTaxQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildTaxQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\TaxQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\TaxQuery();
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
     * @return ChildTax|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = TaxTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(TaxTableMap::DATABASE_NAME);
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
     * @return   ChildTax A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `TYPE`, `SERIALIZED_REQUIREMENTS`, `CREATED_AT`, `UPDATED_AT` FROM `tax` WHERE `ID` = :p0';
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
            $obj = new ChildTax();
            $obj->hydrate($row);
            TaxTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildTax|array|mixed the result, formatted by the current formatter
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
     * @return ChildTaxQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(TaxTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildTaxQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(TaxTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildTaxQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(TaxTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(TaxTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TaxTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the type column
     *
     * Example usage:
     * <code>
     * $query->filterByType('fooValue');   // WHERE type = 'fooValue'
     * $query->filterByType('%fooValue%'); // WHERE type LIKE '%fooValue%'
     * </code>
     *
     * @param     string $type The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildTaxQuery The current query, for fluid interface
     */
    public function filterByType($type = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($type)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $type)) {
                $type = str_replace('*', '%', $type);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(TaxTableMap::TYPE, $type, $comparison);
    }

    /**
     * Filter the query on the serialized_requirements column
     *
     * Example usage:
     * <code>
     * $query->filterBySerializedRequirements('fooValue');   // WHERE serialized_requirements = 'fooValue'
     * $query->filterBySerializedRequirements('%fooValue%'); // WHERE serialized_requirements LIKE '%fooValue%'
     * </code>
     *
     * @param     string $serializedRequirements The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildTaxQuery The current query, for fluid interface
     */
    public function filterBySerializedRequirements($serializedRequirements = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($serializedRequirements)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $serializedRequirements)) {
                $serializedRequirements = str_replace('*', '%', $serializedRequirements);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(TaxTableMap::SERIALIZED_REQUIREMENTS, $serializedRequirements, $comparison);
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
     * @return ChildTaxQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(TaxTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(TaxTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TaxTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildTaxQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(TaxTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(TaxTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TaxTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\TaxRuleCountry object
     *
     * @param \Thelia\Model\TaxRuleCountry|ObjectCollection $taxRuleCountry  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildTaxQuery The current query, for fluid interface
     */
    public function filterByTaxRuleCountry($taxRuleCountry, $comparison = null)
    {
        if ($taxRuleCountry instanceof \Thelia\Model\TaxRuleCountry) {
            return $this
                ->addUsingAlias(TaxTableMap::ID, $taxRuleCountry->getTaxId(), $comparison);
        } elseif ($taxRuleCountry instanceof ObjectCollection) {
            return $this
                ->useTaxRuleCountryQuery()
                ->filterByPrimaryKeys($taxRuleCountry->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByTaxRuleCountry() only accepts arguments of type \Thelia\Model\TaxRuleCountry or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the TaxRuleCountry relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildTaxQuery The current query, for fluid interface
     */
    public function joinTaxRuleCountry($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('TaxRuleCountry');

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
            $this->addJoinObject($join, 'TaxRuleCountry');
        }

        return $this;
    }

    /**
     * Use the TaxRuleCountry relation TaxRuleCountry object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\TaxRuleCountryQuery A secondary query class using the current class as primary query
     */
    public function useTaxRuleCountryQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinTaxRuleCountry($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'TaxRuleCountry', '\Thelia\Model\TaxRuleCountryQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\TaxI18n object
     *
     * @param \Thelia\Model\TaxI18n|ObjectCollection $taxI18n  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildTaxQuery The current query, for fluid interface
     */
    public function filterByTaxI18n($taxI18n, $comparison = null)
    {
        if ($taxI18n instanceof \Thelia\Model\TaxI18n) {
            return $this
                ->addUsingAlias(TaxTableMap::ID, $taxI18n->getId(), $comparison);
        } elseif ($taxI18n instanceof ObjectCollection) {
            return $this
                ->useTaxI18nQuery()
                ->filterByPrimaryKeys($taxI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByTaxI18n() only accepts arguments of type \Thelia\Model\TaxI18n or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the TaxI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildTaxQuery The current query, for fluid interface
     */
    public function joinTaxI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('TaxI18n');

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
            $this->addJoinObject($join, 'TaxI18n');
        }

        return $this;
    }

    /**
     * Use the TaxI18n relation TaxI18n object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\TaxI18nQuery A secondary query class using the current class as primary query
     */
    public function useTaxI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinTaxI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'TaxI18n', '\Thelia\Model\TaxI18nQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildTax $tax Object to remove from the list of results
     *
     * @return ChildTaxQuery The current query, for fluid interface
     */
    public function prune($tax = null)
    {
        if ($tax) {
            $this->addUsingAlias(TaxTableMap::ID, $tax->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the tax table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(TaxTableMap::DATABASE_NAME);
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
            TaxTableMap::clearInstancePool();
            TaxTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildTax or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildTax object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(TaxTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(TaxTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        TaxTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            TaxTableMap::clearRelatedInstancePool();
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
     * @return     ChildTaxQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(TaxTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildTaxQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(TaxTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildTaxQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(TaxTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildTaxQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(TaxTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildTaxQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(TaxTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildTaxQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(TaxTableMap::CREATED_AT);
    }

    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildTaxQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'TaxI18n';

        return $this
            ->joinTaxI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildTaxQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('TaxI18n');
        $this->with['TaxI18n']->setIsWithOneToMany(false);

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
     * @return    ChildTaxI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'TaxI18n', '\Thelia\Model\TaxI18nQuery');
    }

} // TaxQuery
