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
use Thelia\Model\Country;
use Thelia\Model\Tax;
use Thelia\Model\TaxRule;
use Thelia\Model\TaxRuleCountry;
use Thelia\Model\TaxRuleCountryPeer;
use Thelia\Model\TaxRuleCountryQuery;

/**
 * Base class that represents a query for the 'tax_rule_country' table.
 *
 *
 *
 * @method TaxRuleCountryQuery orderById($order = Criteria::ASC) Order by the id column
 * @method TaxRuleCountryQuery orderByTaxRuleId($order = Criteria::ASC) Order by the tax_rule_id column
 * @method TaxRuleCountryQuery orderByCountryId($order = Criteria::ASC) Order by the country_id column
 * @method TaxRuleCountryQuery orderByTaxId($order = Criteria::ASC) Order by the tax_id column
 * @method TaxRuleCountryQuery orderByNone($order = Criteria::ASC) Order by the none column
 * @method TaxRuleCountryQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method TaxRuleCountryQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method TaxRuleCountryQuery groupById() Group by the id column
 * @method TaxRuleCountryQuery groupByTaxRuleId() Group by the tax_rule_id column
 * @method TaxRuleCountryQuery groupByCountryId() Group by the country_id column
 * @method TaxRuleCountryQuery groupByTaxId() Group by the tax_id column
 * @method TaxRuleCountryQuery groupByNone() Group by the none column
 * @method TaxRuleCountryQuery groupByCreatedAt() Group by the created_at column
 * @method TaxRuleCountryQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method TaxRuleCountryQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method TaxRuleCountryQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method TaxRuleCountryQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method TaxRuleCountryQuery leftJoinTax($relationAlias = null) Adds a LEFT JOIN clause to the query using the Tax relation
 * @method TaxRuleCountryQuery rightJoinTax($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Tax relation
 * @method TaxRuleCountryQuery innerJoinTax($relationAlias = null) Adds a INNER JOIN clause to the query using the Tax relation
 *
 * @method TaxRuleCountryQuery leftJoinTaxRule($relationAlias = null) Adds a LEFT JOIN clause to the query using the TaxRule relation
 * @method TaxRuleCountryQuery rightJoinTaxRule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the TaxRule relation
 * @method TaxRuleCountryQuery innerJoinTaxRule($relationAlias = null) Adds a INNER JOIN clause to the query using the TaxRule relation
 *
 * @method TaxRuleCountryQuery leftJoinCountry($relationAlias = null) Adds a LEFT JOIN clause to the query using the Country relation
 * @method TaxRuleCountryQuery rightJoinCountry($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Country relation
 * @method TaxRuleCountryQuery innerJoinCountry($relationAlias = null) Adds a INNER JOIN clause to the query using the Country relation
 *
 * @method TaxRuleCountry findOne(PropelPDO $con = null) Return the first TaxRuleCountry matching the query
 * @method TaxRuleCountry findOneOrCreate(PropelPDO $con = null) Return the first TaxRuleCountry matching the query, or a new TaxRuleCountry object populated from the query conditions when no match is found
 *
 * @method TaxRuleCountry findOneById(int $id) Return the first TaxRuleCountry filtered by the id column
 * @method TaxRuleCountry findOneByTaxRuleId(int $tax_rule_id) Return the first TaxRuleCountry filtered by the tax_rule_id column
 * @method TaxRuleCountry findOneByCountryId(int $country_id) Return the first TaxRuleCountry filtered by the country_id column
 * @method TaxRuleCountry findOneByTaxId(int $tax_id) Return the first TaxRuleCountry filtered by the tax_id column
 * @method TaxRuleCountry findOneByNone(int $none) Return the first TaxRuleCountry filtered by the none column
 * @method TaxRuleCountry findOneByCreatedAt(string $created_at) Return the first TaxRuleCountry filtered by the created_at column
 * @method TaxRuleCountry findOneByUpdatedAt(string $updated_at) Return the first TaxRuleCountry filtered by the updated_at column
 *
 * @method array findById(int $id) Return TaxRuleCountry objects filtered by the id column
 * @method array findByTaxRuleId(int $tax_rule_id) Return TaxRuleCountry objects filtered by the tax_rule_id column
 * @method array findByCountryId(int $country_id) Return TaxRuleCountry objects filtered by the country_id column
 * @method array findByTaxId(int $tax_id) Return TaxRuleCountry objects filtered by the tax_id column
 * @method array findByNone(int $none) Return TaxRuleCountry objects filtered by the none column
 * @method array findByCreatedAt(string $created_at) Return TaxRuleCountry objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return TaxRuleCountry objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseTaxRuleCountryQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseTaxRuleCountryQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\TaxRuleCountry', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new TaxRuleCountryQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     TaxRuleCountryQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return TaxRuleCountryQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof TaxRuleCountryQuery) {
            return $criteria;
        }
        $query = new TaxRuleCountryQuery();
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
     * @return   TaxRuleCountry|TaxRuleCountry[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = TaxRuleCountryPeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(TaxRuleCountryPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return   TaxRuleCountry A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `TAX_RULE_ID`, `COUNTRY_ID`, `TAX_ID`, `NONE`, `CREATED_AT`, `UPDATED_AT` FROM `tax_rule_country` WHERE `ID` = :p0';
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
            $obj = new TaxRuleCountry();
            $obj->hydrate($row);
            TaxRuleCountryPeer::addInstanceToPool($obj, (string) $key);
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
     * @return TaxRuleCountry|TaxRuleCountry[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|TaxRuleCountry[]|mixed the list of results, formatted by the current formatter
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
     * @return TaxRuleCountryQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(TaxRuleCountryPeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return TaxRuleCountryQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(TaxRuleCountryPeer::ID, $keys, Criteria::IN);
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
     * @return TaxRuleCountryQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(TaxRuleCountryPeer::ID, $id, $comparison);
    }

    /**
     * Filter the query on the tax_rule_id column
     *
     * Example usage:
     * <code>
     * $query->filterByTaxRuleId(1234); // WHERE tax_rule_id = 1234
     * $query->filterByTaxRuleId(array(12, 34)); // WHERE tax_rule_id IN (12, 34)
     * $query->filterByTaxRuleId(array('min' => 12)); // WHERE tax_rule_id > 12
     * </code>
     *
     * @see       filterByTaxRule()
     *
     * @param     mixed $taxRuleId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return TaxRuleCountryQuery The current query, for fluid interface
     */
    public function filterByTaxRuleId($taxRuleId = null, $comparison = null)
    {
        if (is_array($taxRuleId)) {
            $useMinMax = false;
            if (isset($taxRuleId['min'])) {
                $this->addUsingAlias(TaxRuleCountryPeer::TAX_RULE_ID, $taxRuleId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($taxRuleId['max'])) {
                $this->addUsingAlias(TaxRuleCountryPeer::TAX_RULE_ID, $taxRuleId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TaxRuleCountryPeer::TAX_RULE_ID, $taxRuleId, $comparison);
    }

    /**
     * Filter the query on the country_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCountryId(1234); // WHERE country_id = 1234
     * $query->filterByCountryId(array(12, 34)); // WHERE country_id IN (12, 34)
     * $query->filterByCountryId(array('min' => 12)); // WHERE country_id > 12
     * </code>
     *
     * @see       filterByCountry()
     *
     * @param     mixed $countryId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return TaxRuleCountryQuery The current query, for fluid interface
     */
    public function filterByCountryId($countryId = null, $comparison = null)
    {
        if (is_array($countryId)) {
            $useMinMax = false;
            if (isset($countryId['min'])) {
                $this->addUsingAlias(TaxRuleCountryPeer::COUNTRY_ID, $countryId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($countryId['max'])) {
                $this->addUsingAlias(TaxRuleCountryPeer::COUNTRY_ID, $countryId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TaxRuleCountryPeer::COUNTRY_ID, $countryId, $comparison);
    }

    /**
     * Filter the query on the tax_id column
     *
     * Example usage:
     * <code>
     * $query->filterByTaxId(1234); // WHERE tax_id = 1234
     * $query->filterByTaxId(array(12, 34)); // WHERE tax_id IN (12, 34)
     * $query->filterByTaxId(array('min' => 12)); // WHERE tax_id > 12
     * </code>
     *
     * @see       filterByTax()
     *
     * @param     mixed $taxId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return TaxRuleCountryQuery The current query, for fluid interface
     */
    public function filterByTaxId($taxId = null, $comparison = null)
    {
        if (is_array($taxId)) {
            $useMinMax = false;
            if (isset($taxId['min'])) {
                $this->addUsingAlias(TaxRuleCountryPeer::TAX_ID, $taxId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($taxId['max'])) {
                $this->addUsingAlias(TaxRuleCountryPeer::TAX_ID, $taxId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TaxRuleCountryPeer::TAX_ID, $taxId, $comparison);
    }

    /**
     * Filter the query on the none column
     *
     * Example usage:
     * <code>
     * $query->filterByNone(1234); // WHERE none = 1234
     * $query->filterByNone(array(12, 34)); // WHERE none IN (12, 34)
     * $query->filterByNone(array('min' => 12)); // WHERE none > 12
     * </code>
     *
     * @param     mixed $none The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return TaxRuleCountryQuery The current query, for fluid interface
     */
    public function filterByNone($none = null, $comparison = null)
    {
        if (is_array($none)) {
            $useMinMax = false;
            if (isset($none['min'])) {
                $this->addUsingAlias(TaxRuleCountryPeer::NONE, $none['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($none['max'])) {
                $this->addUsingAlias(TaxRuleCountryPeer::NONE, $none['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TaxRuleCountryPeer::NONE, $none, $comparison);
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
     * @return TaxRuleCountryQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(TaxRuleCountryPeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(TaxRuleCountryPeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TaxRuleCountryPeer::CREATED_AT, $createdAt, $comparison);
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
     * @return TaxRuleCountryQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(TaxRuleCountryPeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(TaxRuleCountryPeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TaxRuleCountryPeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related Tax object
     *
     * @param   Tax|PropelObjectCollection $tax The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   TaxRuleCountryQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByTax($tax, $comparison = null)
    {
        if ($tax instanceof Tax) {
            return $this
                ->addUsingAlias(TaxRuleCountryPeer::TAX_ID, $tax->getId(), $comparison);
        } elseif ($tax instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(TaxRuleCountryPeer::TAX_ID, $tax->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByTax() only accepts arguments of type Tax or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Tax relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return TaxRuleCountryQuery The current query, for fluid interface
     */
    public function joinTax($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Tax');

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
            $this->addJoinObject($join, 'Tax');
        }

        return $this;
    }

    /**
     * Use the Tax relation Tax object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\TaxQuery A secondary query class using the current class as primary query
     */
    public function useTaxQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinTax($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Tax', '\Thelia\Model\TaxQuery');
    }

    /**
     * Filter the query by a related TaxRule object
     *
     * @param   TaxRule|PropelObjectCollection $taxRule The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   TaxRuleCountryQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByTaxRule($taxRule, $comparison = null)
    {
        if ($taxRule instanceof TaxRule) {
            return $this
                ->addUsingAlias(TaxRuleCountryPeer::TAX_RULE_ID, $taxRule->getId(), $comparison);
        } elseif ($taxRule instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(TaxRuleCountryPeer::TAX_RULE_ID, $taxRule->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByTaxRule() only accepts arguments of type TaxRule or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the TaxRule relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return TaxRuleCountryQuery The current query, for fluid interface
     */
    public function joinTaxRule($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('TaxRule');

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
            $this->addJoinObject($join, 'TaxRule');
        }

        return $this;
    }

    /**
     * Use the TaxRule relation TaxRule object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\TaxRuleQuery A secondary query class using the current class as primary query
     */
    public function useTaxRuleQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinTaxRule($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'TaxRule', '\Thelia\Model\TaxRuleQuery');
    }

    /**
     * Filter the query by a related Country object
     *
     * @param   Country|PropelObjectCollection $country The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   TaxRuleCountryQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByCountry($country, $comparison = null)
    {
        if ($country instanceof Country) {
            return $this
                ->addUsingAlias(TaxRuleCountryPeer::COUNTRY_ID, $country->getId(), $comparison);
        } elseif ($country instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(TaxRuleCountryPeer::COUNTRY_ID, $country->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByCountry() only accepts arguments of type Country or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Country relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return TaxRuleCountryQuery The current query, for fluid interface
     */
    public function joinCountry($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Country');

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
            $this->addJoinObject($join, 'Country');
        }

        return $this;
    }

    /**
     * Use the Country relation Country object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CountryQuery A secondary query class using the current class as primary query
     */
    public function useCountryQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinCountry($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Country', '\Thelia\Model\CountryQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   TaxRuleCountry $taxRuleCountry Object to remove from the list of results
     *
     * @return TaxRuleCountryQuery The current query, for fluid interface
     */
    public function prune($taxRuleCountry = null)
    {
        if ($taxRuleCountry) {
            $this->addUsingAlias(TaxRuleCountryPeer::ID, $taxRuleCountry->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    // timestampable behavior

    /**
     * Filter by the latest updated
     *
     * @param      int $nbDays Maximum age of the latest update in days
     *
     * @return     TaxRuleCountryQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(TaxRuleCountryPeer::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     TaxRuleCountryQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(TaxRuleCountryPeer::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     TaxRuleCountryQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(TaxRuleCountryPeer::UPDATED_AT);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     TaxRuleCountryQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(TaxRuleCountryPeer::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by create date desc
     *
     * @return     TaxRuleCountryQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(TaxRuleCountryPeer::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     TaxRuleCountryQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(TaxRuleCountryPeer::CREATED_AT);
    }
}
