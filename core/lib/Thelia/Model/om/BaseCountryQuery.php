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
use Thelia\Model\Area;
use Thelia\Model\Country;
use Thelia\Model\CountryI18n;
use Thelia\Model\CountryPeer;
use Thelia\Model\CountryQuery;
use Thelia\Model\TaxRuleCountry;

/**
 * Base class that represents a query for the 'country' table.
 *
 *
 *
 * @method CountryQuery orderById($order = Criteria::ASC) Order by the id column
 * @method CountryQuery orderByAreaId($order = Criteria::ASC) Order by the area_id column
 * @method CountryQuery orderByIsocode($order = Criteria::ASC) Order by the isocode column
 * @method CountryQuery orderByIsoalpha2($order = Criteria::ASC) Order by the isoalpha2 column
 * @method CountryQuery orderByIsoalpha3($order = Criteria::ASC) Order by the isoalpha3 column
 * @method CountryQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method CountryQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method CountryQuery groupById() Group by the id column
 * @method CountryQuery groupByAreaId() Group by the area_id column
 * @method CountryQuery groupByIsocode() Group by the isocode column
 * @method CountryQuery groupByIsoalpha2() Group by the isoalpha2 column
 * @method CountryQuery groupByIsoalpha3() Group by the isoalpha3 column
 * @method CountryQuery groupByCreatedAt() Group by the created_at column
 * @method CountryQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method CountryQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method CountryQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method CountryQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method CountryQuery leftJoinArea($relationAlias = null) Adds a LEFT JOIN clause to the query using the Area relation
 * @method CountryQuery rightJoinArea($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Area relation
 * @method CountryQuery innerJoinArea($relationAlias = null) Adds a INNER JOIN clause to the query using the Area relation
 *
 * @method CountryQuery leftJoinTaxRuleCountry($relationAlias = null) Adds a LEFT JOIN clause to the query using the TaxRuleCountry relation
 * @method CountryQuery rightJoinTaxRuleCountry($relationAlias = null) Adds a RIGHT JOIN clause to the query using the TaxRuleCountry relation
 * @method CountryQuery innerJoinTaxRuleCountry($relationAlias = null) Adds a INNER JOIN clause to the query using the TaxRuleCountry relation
 *
 * @method CountryQuery leftJoinCountryI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the CountryI18n relation
 * @method CountryQuery rightJoinCountryI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CountryI18n relation
 * @method CountryQuery innerJoinCountryI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the CountryI18n relation
 *
 * @method Country findOne(PropelPDO $con = null) Return the first Country matching the query
 * @method Country findOneOrCreate(PropelPDO $con = null) Return the first Country matching the query, or a new Country object populated from the query conditions when no match is found
 *
 * @method Country findOneByAreaId(int $area_id) Return the first Country filtered by the area_id column
 * @method Country findOneByIsocode(string $isocode) Return the first Country filtered by the isocode column
 * @method Country findOneByIsoalpha2(string $isoalpha2) Return the first Country filtered by the isoalpha2 column
 * @method Country findOneByIsoalpha3(string $isoalpha3) Return the first Country filtered by the isoalpha3 column
 * @method Country findOneByCreatedAt(string $created_at) Return the first Country filtered by the created_at column
 * @method Country findOneByUpdatedAt(string $updated_at) Return the first Country filtered by the updated_at column
 *
 * @method array findById(int $id) Return Country objects filtered by the id column
 * @method array findByAreaId(int $area_id) Return Country objects filtered by the area_id column
 * @method array findByIsocode(string $isocode) Return Country objects filtered by the isocode column
 * @method array findByIsoalpha2(string $isoalpha2) Return Country objects filtered by the isoalpha2 column
 * @method array findByIsoalpha3(string $isoalpha3) Return Country objects filtered by the isoalpha3 column
 * @method array findByCreatedAt(string $created_at) Return Country objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return Country objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseCountryQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseCountryQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\Country', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new CountryQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param   CountryQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return CountryQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof CountryQuery) {
            return $criteria;
        }
        $query = new CountryQuery();
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
     * @return   Country|Country[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = CountryPeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(CountryPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return                 Country A model object, or null if the key is not found
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
     * @return                 Country A model object, or null if the key is not found
     * @throws PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `id`, `area_id`, `isocode`, `isoalpha2`, `isoalpha3`, `created_at`, `updated_at` FROM `country` WHERE `id` = :p0';
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
            $obj = new Country();
            $obj->hydrate($row);
            CountryPeer::addInstanceToPool($obj, (string) $key);
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
     * @return Country|Country[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|Country[]|mixed the list of results, formatted by the current formatter
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
     * @return CountryQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(CountryPeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return CountryQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(CountryPeer::ID, $keys, Criteria::IN);
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
     * @return CountryQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(CountryPeer::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(CountryPeer::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CountryPeer::ID, $id, $comparison);
    }

    /**
     * Filter the query on the area_id column
     *
     * Example usage:
     * <code>
     * $query->filterByAreaId(1234); // WHERE area_id = 1234
     * $query->filterByAreaId(array(12, 34)); // WHERE area_id IN (12, 34)
     * $query->filterByAreaId(array('min' => 12)); // WHERE area_id >= 12
     * $query->filterByAreaId(array('max' => 12)); // WHERE area_id <= 12
     * </code>
     *
     * @see       filterByArea()
     *
     * @param     mixed $areaId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CountryQuery The current query, for fluid interface
     */
    public function filterByAreaId($areaId = null, $comparison = null)
    {
        if (is_array($areaId)) {
            $useMinMax = false;
            if (isset($areaId['min'])) {
                $this->addUsingAlias(CountryPeer::AREA_ID, $areaId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($areaId['max'])) {
                $this->addUsingAlias(CountryPeer::AREA_ID, $areaId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CountryPeer::AREA_ID, $areaId, $comparison);
    }

    /**
     * Filter the query on the isocode column
     *
     * Example usage:
     * <code>
     * $query->filterByIsocode('fooValue');   // WHERE isocode = 'fooValue'
     * $query->filterByIsocode('%fooValue%'); // WHERE isocode LIKE '%fooValue%'
     * </code>
     *
     * @param     string $isocode The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CountryQuery The current query, for fluid interface
     */
    public function filterByIsocode($isocode = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($isocode)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $isocode)) {
                $isocode = str_replace('*', '%', $isocode);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CountryPeer::ISOCODE, $isocode, $comparison);
    }

    /**
     * Filter the query on the isoalpha2 column
     *
     * Example usage:
     * <code>
     * $query->filterByIsoalpha2('fooValue');   // WHERE isoalpha2 = 'fooValue'
     * $query->filterByIsoalpha2('%fooValue%'); // WHERE isoalpha2 LIKE '%fooValue%'
     * </code>
     *
     * @param     string $isoalpha2 The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CountryQuery The current query, for fluid interface
     */
    public function filterByIsoalpha2($isoalpha2 = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($isoalpha2)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $isoalpha2)) {
                $isoalpha2 = str_replace('*', '%', $isoalpha2);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CountryPeer::ISOALPHA2, $isoalpha2, $comparison);
    }

    /**
     * Filter the query on the isoalpha3 column
     *
     * Example usage:
     * <code>
     * $query->filterByIsoalpha3('fooValue');   // WHERE isoalpha3 = 'fooValue'
     * $query->filterByIsoalpha3('%fooValue%'); // WHERE isoalpha3 LIKE '%fooValue%'
     * </code>
     *
     * @param     string $isoalpha3 The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CountryQuery The current query, for fluid interface
     */
    public function filterByIsoalpha3($isoalpha3 = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($isoalpha3)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $isoalpha3)) {
                $isoalpha3 = str_replace('*', '%', $isoalpha3);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CountryPeer::ISOALPHA3, $isoalpha3, $comparison);
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
     * @return CountryQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(CountryPeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(CountryPeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CountryPeer::CREATED_AT, $createdAt, $comparison);
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
     * @return CountryQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(CountryPeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(CountryPeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CountryPeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related Area object
     *
     * @param   Area|PropelObjectCollection $area The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return                 CountryQuery The current query, for fluid interface
     * @throws PropelException - if the provided filter is invalid.
     */
    public function filterByArea($area, $comparison = null)
    {
        if ($area instanceof Area) {
            return $this
                ->addUsingAlias(CountryPeer::AREA_ID, $area->getId(), $comparison);
        } elseif ($area instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(CountryPeer::AREA_ID, $area->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByArea() only accepts arguments of type Area or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Area relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return CountryQuery The current query, for fluid interface
     */
    public function joinArea($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Area');

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
            $this->addJoinObject($join, 'Area');
        }

        return $this;
    }

    /**
     * Use the Area relation Area object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\AreaQuery A secondary query class using the current class as primary query
     */
    public function useAreaQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinArea($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Area', '\Thelia\Model\AreaQuery');
    }

    /**
     * Filter the query by a related TaxRuleCountry object
     *
     * @param   TaxRuleCountry|PropelObjectCollection $taxRuleCountry  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return                 CountryQuery The current query, for fluid interface
     * @throws PropelException - if the provided filter is invalid.
     */
    public function filterByTaxRuleCountry($taxRuleCountry, $comparison = null)
    {
        if ($taxRuleCountry instanceof TaxRuleCountry) {
            return $this
                ->addUsingAlias(CountryPeer::ID, $taxRuleCountry->getCountryId(), $comparison);
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
     * @return CountryQuery The current query, for fluid interface
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
     * Filter the query by a related CountryI18n object
     *
     * @param   CountryI18n|PropelObjectCollection $countryI18n  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return                 CountryQuery The current query, for fluid interface
     * @throws PropelException - if the provided filter is invalid.
     */
    public function filterByCountryI18n($countryI18n, $comparison = null)
    {
        if ($countryI18n instanceof CountryI18n) {
            return $this
                ->addUsingAlias(CountryPeer::ID, $countryI18n->getId(), $comparison);
        } elseif ($countryI18n instanceof PropelObjectCollection) {
            return $this
                ->useCountryI18nQuery()
                ->filterByPrimaryKeys($countryI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCountryI18n() only accepts arguments of type CountryI18n or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CountryI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return CountryQuery The current query, for fluid interface
     */
    public function joinCountryI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CountryI18n');

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
            $this->addJoinObject($join, 'CountryI18n');
        }

        return $this;
    }

    /**
     * Use the CountryI18n relation CountryI18n object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CountryI18nQuery A secondary query class using the current class as primary query
     */
    public function useCountryI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinCountryI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CountryI18n', '\Thelia\Model\CountryI18nQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   Country $country Object to remove from the list of results
     *
     * @return CountryQuery The current query, for fluid interface
     */
    public function prune($country = null)
    {
        if ($country) {
            $this->addUsingAlias(CountryPeer::ID, $country->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    // timestampable behavior

    /**
     * Filter by the latest updated
     *
     * @param      int $nbDays Maximum age of the latest update in days
     *
     * @return     CountryQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(CountryPeer::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     CountryQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(CountryPeer::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     CountryQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(CountryPeer::UPDATED_AT);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     CountryQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(CountryPeer::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by create date desc
     *
     * @return     CountryQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(CountryPeer::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     CountryQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(CountryPeer::CREATED_AT);
    }
    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    CountryQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'CountryI18n';

        return $this
            ->joinCountryI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    CountryQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('CountryI18n');
        $this->with['CountryI18n']->setIsWithOneToMany(false);

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
     * @return    CountryI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CountryI18n', 'Thelia\Model\CountryI18nQuery');
    }

}
