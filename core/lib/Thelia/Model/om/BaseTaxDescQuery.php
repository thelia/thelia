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
use Thelia\Model\TaxDescPeer;
use Thelia\Model\TaxDescQuery;

/**
 * Base class that represents a query for the 'tax_desc' table.
 *
 *
 *
 * @method TaxDescQuery orderById($order = Criteria::ASC) Order by the id column
 * @method TaxDescQuery orderByTaxId($order = Criteria::ASC) Order by the tax_id column
 * @method TaxDescQuery orderByLang($order = Criteria::ASC) Order by the lang column
 * @method TaxDescQuery orderByTitle($order = Criteria::ASC) Order by the title column
 * @method TaxDescQuery orderByDescription($order = Criteria::ASC) Order by the description column
 * @method TaxDescQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method TaxDescQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method TaxDescQuery groupById() Group by the id column
 * @method TaxDescQuery groupByTaxId() Group by the tax_id column
 * @method TaxDescQuery groupByLang() Group by the lang column
 * @method TaxDescQuery groupByTitle() Group by the title column
 * @method TaxDescQuery groupByDescription() Group by the description column
 * @method TaxDescQuery groupByCreatedAt() Group by the created_at column
 * @method TaxDescQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method TaxDescQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method TaxDescQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method TaxDescQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method TaxDescQuery leftJoinTax($relationAlias = null) Adds a LEFT JOIN clause to the query using the Tax relation
 * @method TaxDescQuery rightJoinTax($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Tax relation
 * @method TaxDescQuery innerJoinTax($relationAlias = null) Adds a INNER JOIN clause to the query using the Tax relation
 *
 * @method TaxDesc findOne(PropelPDO $con = null) Return the first TaxDesc matching the query
 * @method TaxDesc findOneOrCreate(PropelPDO $con = null) Return the first TaxDesc matching the query, or a new TaxDesc object populated from the query conditions when no match is found
 *
 * @method TaxDesc findOneById(int $id) Return the first TaxDesc filtered by the id column
 * @method TaxDesc findOneByTaxId(int $tax_id) Return the first TaxDesc filtered by the tax_id column
 * @method TaxDesc findOneByLang(string $lang) Return the first TaxDesc filtered by the lang column
 * @method TaxDesc findOneByTitle(string $title) Return the first TaxDesc filtered by the title column
 * @method TaxDesc findOneByDescription(string $description) Return the first TaxDesc filtered by the description column
 * @method TaxDesc findOneByCreatedAt(string $created_at) Return the first TaxDesc filtered by the created_at column
 * @method TaxDesc findOneByUpdatedAt(string $updated_at) Return the first TaxDesc filtered by the updated_at column
 *
 * @method array findById(int $id) Return TaxDesc objects filtered by the id column
 * @method array findByTaxId(int $tax_id) Return TaxDesc objects filtered by the tax_id column
 * @method array findByLang(string $lang) Return TaxDesc objects filtered by the lang column
 * @method array findByTitle(string $title) Return TaxDesc objects filtered by the title column
 * @method array findByDescription(string $description) Return TaxDesc objects filtered by the description column
 * @method array findByCreatedAt(string $created_at) Return TaxDesc objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return TaxDesc objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseTaxDescQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseTaxDescQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\TaxDesc', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new TaxDescQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     TaxDescQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return TaxDescQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof TaxDescQuery) {
            return $criteria;
        }
        $query = new TaxDescQuery();
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
     * @return   TaxDesc|TaxDesc[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = TaxDescPeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(TaxDescPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return   TaxDesc A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `TAX_ID`, `LANG`, `TITLE`, `DESCRIPTION`, `CREATED_AT`, `UPDATED_AT` FROM `tax_desc` WHERE `ID` = :p0';
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
            $obj = new TaxDesc();
            $obj->hydrate($row);
            TaxDescPeer::addInstanceToPool($obj, (string) $key);
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
     * @return TaxDesc|TaxDesc[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|TaxDesc[]|mixed the list of results, formatted by the current formatter
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
     * @return TaxDescQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(TaxDescPeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return TaxDescQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(TaxDescPeer::ID, $keys, Criteria::IN);
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
     * @return TaxDescQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(TaxDescPeer::ID, $id, $comparison);
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
     * @return TaxDescQuery The current query, for fluid interface
     */
    public function filterByTaxId($taxId = null, $comparison = null)
    {
        if (is_array($taxId)) {
            $useMinMax = false;
            if (isset($taxId['min'])) {
                $this->addUsingAlias(TaxDescPeer::TAX_ID, $taxId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($taxId['max'])) {
                $this->addUsingAlias(TaxDescPeer::TAX_ID, $taxId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TaxDescPeer::TAX_ID, $taxId, $comparison);
    }

    /**
     * Filter the query on the lang column
     *
     * Example usage:
     * <code>
     * $query->filterByLang('fooValue');   // WHERE lang = 'fooValue'
     * $query->filterByLang('%fooValue%'); // WHERE lang LIKE '%fooValue%'
     * </code>
     *
     * @param     string $lang The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return TaxDescQuery The current query, for fluid interface
     */
    public function filterByLang($lang = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($lang)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $lang)) {
                $lang = str_replace('*', '%', $lang);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(TaxDescPeer::LANG, $lang, $comparison);
    }

    /**
     * Filter the query on the title column
     *
     * Example usage:
     * <code>
     * $query->filterByTitle('fooValue');   // WHERE title = 'fooValue'
     * $query->filterByTitle('%fooValue%'); // WHERE title LIKE '%fooValue%'
     * </code>
     *
     * @param     string $title The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return TaxDescQuery The current query, for fluid interface
     */
    public function filterByTitle($title = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($title)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $title)) {
                $title = str_replace('*', '%', $title);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(TaxDescPeer::TITLE, $title, $comparison);
    }

    /**
     * Filter the query on the description column
     *
     * Example usage:
     * <code>
     * $query->filterByDescription('fooValue');   // WHERE description = 'fooValue'
     * $query->filterByDescription('%fooValue%'); // WHERE description LIKE '%fooValue%'
     * </code>
     *
     * @param     string $description The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return TaxDescQuery The current query, for fluid interface
     */
    public function filterByDescription($description = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($description)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $description)) {
                $description = str_replace('*', '%', $description);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(TaxDescPeer::DESCRIPTION, $description, $comparison);
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
     * @return TaxDescQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(TaxDescPeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(TaxDescPeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TaxDescPeer::CREATED_AT, $createdAt, $comparison);
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
     * @return TaxDescQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(TaxDescPeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(TaxDescPeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(TaxDescPeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related Tax object
     *
     * @param   Tax|PropelObjectCollection $tax The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   TaxDescQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByTax($tax, $comparison = null)
    {
        if ($tax instanceof Tax) {
            return $this
                ->addUsingAlias(TaxDescPeer::TAX_ID, $tax->getId(), $comparison);
        } elseif ($tax instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(TaxDescPeer::TAX_ID, $tax->toKeyValue('PrimaryKey', 'Id'), $comparison);
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
     * @return TaxDescQuery The current query, for fluid interface
     */
    public function joinTax($relationAlias = null, $joinType = Criteria::INNER_JOIN)
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
    public function useTaxQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinTax($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Tax', '\Thelia\Model\TaxQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   TaxDesc $taxDesc Object to remove from the list of results
     *
     * @return TaxDescQuery The current query, for fluid interface
     */
    public function prune($taxDesc = null)
    {
        if ($taxDesc) {
            $this->addUsingAlias(TaxDescPeer::ID, $taxDesc->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    // timestampable behavior

    /**
     * Filter by the latest updated
     *
     * @param      int $nbDays Maximum age of the latest update in days
     *
     * @return     TaxDescQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(TaxDescPeer::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     TaxDescQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(TaxDescPeer::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     TaxDescQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(TaxDescPeer::UPDATED_AT);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     TaxDescQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(TaxDescPeer::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by create date desc
     *
     * @return     TaxDescQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(TaxDescPeer::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     TaxDescQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(TaxDescPeer::CREATED_AT);
    }
}
