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
use Thelia\Model\Tax;
use Thelia\Model\TaxDesc;
use Thelia\Model\TaxPeer;
use Thelia\Model\TaxQuery;
use Thelia\Model\TaxRuleCountry;

/**
 * Base class that represents a query for the 'tax' table.
 *
 *
 *
 * @method TaxQuery orderById($order = Criteria::ASC) Order by the id column
 * @method TaxQuery orderByRate($order = Criteria::ASC) Order by the rate column
 * @method TaxQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method TaxQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method TaxQuery groupById() Group by the id column
 * @method TaxQuery groupByRate() Group by the rate column
 * @method TaxQuery groupByCreatedAt() Group by the created_at column
 * @method TaxQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method TaxQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method TaxQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method TaxQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method TaxQuery leftJoinTaxDesc($relationAlias = null) Adds a LEFT JOIN clause to the query using the TaxDesc relation
 * @method TaxQuery rightJoinTaxDesc($relationAlias = null) Adds a RIGHT JOIN clause to the query using the TaxDesc relation
 * @method TaxQuery innerJoinTaxDesc($relationAlias = null) Adds a INNER JOIN clause to the query using the TaxDesc relation
 *
 * @method TaxQuery leftJoinTaxRuleCountry($relationAlias = null) Adds a LEFT JOIN clause to the query using the TaxRuleCountry relation
 * @method TaxQuery rightJoinTaxRuleCountry($relationAlias = null) Adds a RIGHT JOIN clause to the query using the TaxRuleCountry relation
 * @method TaxQuery innerJoinTaxRuleCountry($relationAlias = null) Adds a INNER JOIN clause to the query using the TaxRuleCountry relation
 *
 * @method Tax findOne(PropelPDO $con = null) Return the first Tax matching the query
 * @method Tax findOneOrCreate(PropelPDO $con = null) Return the first Tax matching the query, or a new Tax object populated from the query conditions when no match is found
 *
 * @method Tax findOneById(int $id) Return the first Tax filtered by the id column
 * @method Tax findOneByRate(double $rate) Return the first Tax filtered by the rate column
 * @method Tax findOneByCreatedAt(string $created_at) Return the first Tax filtered by the created_at column
 * @method Tax findOneByUpdatedAt(string $updated_at) Return the first Tax filtered by the updated_at column
 *
 * @method array findById(int $id) Return Tax objects filtered by the id column
 * @method array findByRate(double $rate) Return Tax objects filtered by the rate column
 * @method array findByCreatedAt(string $created_at) Return Tax objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return Tax objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseTaxQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseTaxQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\Tax', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new TaxQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     TaxQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return TaxQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof TaxQuery) {
            return $criteria;
        }
        $query = new TaxQuery();
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
     * @return   Tax|Tax[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = TaxPeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(TaxPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return   Tax A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `RATE`, `CREATED_AT`, `UPDATED_AT` FROM `tax` WHERE `ID` = :p0';
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
            $obj = new Tax();
            $obj->hydrate($row);
            TaxPeer::addInstanceToPool($obj, (string) $key);
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
     * @return Tax|Tax[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|Tax[]|mixed the list of results, formatted by the current formatter
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
     * @return TaxQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(TaxPeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return TaxQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(TaxPeer::ID, $keys, Criteria::IN);
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
     * @return TaxQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(TaxPeer::ID, $id, $comparison);
    }

    /**
     * Filter the query on the rate column
     *
     * Example usage:
     * <code>
     * $query->filterByRate(1234); // WHERE rate = 1234
     * $query->filterByRate(array(12, 34)); // WHERE rate IN (12, 34)
     * $query->filterByRate(array('min' => 12)); // WHERE rate > 12
     * </code>
     *
     * @param     mixed $rate The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return TaxQuery The current query, for fluid interface
     */
    public function filterByRate($rate = null, $comparison = null)
    {
        if (is_array($rate)) {
            $useMinMax = false;
            if (isset($rate['min'])) {
                $this->addUsingAlias(TaxPeer::RATE, $rate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($rate['max'])) {
                $this->addUsingAlias(TaxPeer::RATE, $rate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TaxPeer::RATE, $rate, $comparison);
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
     * @return TaxQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(TaxPeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(TaxPeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TaxPeer::CREATED_AT, $createdAt, $comparison);
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
     * @return TaxQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(TaxPeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(TaxPeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TaxPeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related TaxDesc object
     *
     * @param   TaxDesc|PropelObjectCollection $taxDesc  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   TaxQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByTaxDesc($taxDesc, $comparison = null)
    {
        if ($taxDesc instanceof TaxDesc) {
            return $this
                ->addUsingAlias(TaxPeer::ID, $taxDesc->getTaxId(), $comparison);
        } elseif ($taxDesc instanceof PropelObjectCollection) {
            return $this
                ->useTaxDescQuery()
                ->filterByPrimaryKeys($taxDesc->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByTaxDesc() only accepts arguments of type TaxDesc or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the TaxDesc relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return TaxQuery The current query, for fluid interface
     */
    public function joinTaxDesc($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('TaxDesc');

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
            $this->addJoinObject($join, 'TaxDesc');
        }

        return $this;
    }

    /**
     * Use the TaxDesc relation TaxDesc object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\TaxDescQuery A secondary query class using the current class as primary query
     */
    public function useTaxDescQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinTaxDesc($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'TaxDesc', '\Thelia\Model\TaxDescQuery');
    }

    /**
     * Filter the query by a related TaxRuleCountry object
     *
     * @param   TaxRuleCountry|PropelObjectCollection $taxRuleCountry  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   TaxQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByTaxRuleCountry($taxRuleCountry, $comparison = null)
    {
        if ($taxRuleCountry instanceof TaxRuleCountry) {
            return $this
                ->addUsingAlias(TaxPeer::ID, $taxRuleCountry->getTaxId(), $comparison);
        } elseif ($taxRuleCountry instanceof PropelObjectCollection) {
            return $this
                ->useTaxRuleCountryQuery()
                ->filterByPrimaryKeys($taxRuleCountry->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByTaxRuleCountry() only accepts arguments of type TaxRuleCountry or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the TaxRuleCountry relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return TaxQuery The current query, for fluid interface
     */
    public function joinTaxRuleCountry($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
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
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\TaxRuleCountryQuery A secondary query class using the current class as primary query
     */
    public function useTaxRuleCountryQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinTaxRuleCountry($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'TaxRuleCountry', '\Thelia\Model\TaxRuleCountryQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   Tax $tax Object to remove from the list of results
     *
     * @return TaxQuery The current query, for fluid interface
     */
    public function prune($tax = null)
    {
        if ($tax) {
            $this->addUsingAlias(TaxPeer::ID, $tax->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

}
