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
use Thelia\Model\CustomerTitle;
use Thelia\Model\CustomerTitleDesc;
use Thelia\Model\CustomerTitleDescPeer;
use Thelia\Model\CustomerTitleDescQuery;

/**
 * Base class that represents a query for the 'customer_title_desc' table.
 *
 *
 *
 * @method CustomerTitleDescQuery orderById($order = Criteria::ASC) Order by the id column
 * @method CustomerTitleDescQuery orderByCustomerTitleId($order = Criteria::ASC) Order by the customer_title_id column
 * @method CustomerTitleDescQuery orderByLang($order = Criteria::ASC) Order by the lang column
 * @method CustomerTitleDescQuery orderByShort($order = Criteria::ASC) Order by the short column
 * @method CustomerTitleDescQuery orderByLong($order = Criteria::ASC) Order by the long column
 * @method CustomerTitleDescQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method CustomerTitleDescQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method CustomerTitleDescQuery groupById() Group by the id column
 * @method CustomerTitleDescQuery groupByCustomerTitleId() Group by the customer_title_id column
 * @method CustomerTitleDescQuery groupByLang() Group by the lang column
 * @method CustomerTitleDescQuery groupByShort() Group by the short column
 * @method CustomerTitleDescQuery groupByLong() Group by the long column
 * @method CustomerTitleDescQuery groupByCreatedAt() Group by the created_at column
 * @method CustomerTitleDescQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method CustomerTitleDescQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method CustomerTitleDescQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method CustomerTitleDescQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method CustomerTitleDescQuery leftJoinCustomerTitle($relationAlias = null) Adds a LEFT JOIN clause to the query using the CustomerTitle relation
 * @method CustomerTitleDescQuery rightJoinCustomerTitle($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CustomerTitle relation
 * @method CustomerTitleDescQuery innerJoinCustomerTitle($relationAlias = null) Adds a INNER JOIN clause to the query using the CustomerTitle relation
 *
 * @method CustomerTitleDesc findOne(PropelPDO $con = null) Return the first CustomerTitleDesc matching the query
 * @method CustomerTitleDesc findOneOrCreate(PropelPDO $con = null) Return the first CustomerTitleDesc matching the query, or a new CustomerTitleDesc object populated from the query conditions when no match is found
 *
 * @method CustomerTitleDesc findOneById(int $id) Return the first CustomerTitleDesc filtered by the id column
 * @method CustomerTitleDesc findOneByCustomerTitleId(int $customer_title_id) Return the first CustomerTitleDesc filtered by the customer_title_id column
 * @method CustomerTitleDesc findOneByLang(string $lang) Return the first CustomerTitleDesc filtered by the lang column
 * @method CustomerTitleDesc findOneByShort(string $short) Return the first CustomerTitleDesc filtered by the short column
 * @method CustomerTitleDesc findOneByLong(string $long) Return the first CustomerTitleDesc filtered by the long column
 * @method CustomerTitleDesc findOneByCreatedAt(string $created_at) Return the first CustomerTitleDesc filtered by the created_at column
 * @method CustomerTitleDesc findOneByUpdatedAt(string $updated_at) Return the first CustomerTitleDesc filtered by the updated_at column
 *
 * @method array findById(int $id) Return CustomerTitleDesc objects filtered by the id column
 * @method array findByCustomerTitleId(int $customer_title_id) Return CustomerTitleDesc objects filtered by the customer_title_id column
 * @method array findByLang(string $lang) Return CustomerTitleDesc objects filtered by the lang column
 * @method array findByShort(string $short) Return CustomerTitleDesc objects filtered by the short column
 * @method array findByLong(string $long) Return CustomerTitleDesc objects filtered by the long column
 * @method array findByCreatedAt(string $created_at) Return CustomerTitleDesc objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return CustomerTitleDesc objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseCustomerTitleDescQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseCustomerTitleDescQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'mydb', $modelName = 'Thelia\\Model\\CustomerTitleDesc', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new CustomerTitleDescQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     CustomerTitleDescQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return CustomerTitleDescQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof CustomerTitleDescQuery) {
            return $criteria;
        }
        $query = new CustomerTitleDescQuery();
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
     * @return   CustomerTitleDesc|CustomerTitleDesc[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = CustomerTitleDescPeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(CustomerTitleDescPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return   CustomerTitleDesc A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `CUSTOMER_TITLE_ID`, `LANG`, `SHORT`, `LONG`, `CREATED_AT`, `UPDATED_AT` FROM `customer_title_desc` WHERE `ID` = :p0';
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
            $obj = new CustomerTitleDesc();
            $obj->hydrate($row);
            CustomerTitleDescPeer::addInstanceToPool($obj, (string) $key);
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
     * @return CustomerTitleDesc|CustomerTitleDesc[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|CustomerTitleDesc[]|mixed the list of results, formatted by the current formatter
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
     * @return CustomerTitleDescQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(CustomerTitleDescPeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return CustomerTitleDescQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(CustomerTitleDescPeer::ID, $keys, Criteria::IN);
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
     * @return CustomerTitleDescQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(CustomerTitleDescPeer::ID, $id, $comparison);
    }

    /**
     * Filter the query on the customer_title_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCustomerTitleId(1234); // WHERE customer_title_id = 1234
     * $query->filterByCustomerTitleId(array(12, 34)); // WHERE customer_title_id IN (12, 34)
     * $query->filterByCustomerTitleId(array('min' => 12)); // WHERE customer_title_id > 12
     * </code>
     *
     * @param     mixed $customerTitleId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CustomerTitleDescQuery The current query, for fluid interface
     */
    public function filterByCustomerTitleId($customerTitleId = null, $comparison = null)
    {
        if (is_array($customerTitleId)) {
            $useMinMax = false;
            if (isset($customerTitleId['min'])) {
                $this->addUsingAlias(CustomerTitleDescPeer::CUSTOMER_TITLE_ID, $customerTitleId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($customerTitleId['max'])) {
                $this->addUsingAlias(CustomerTitleDescPeer::CUSTOMER_TITLE_ID, $customerTitleId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerTitleDescPeer::CUSTOMER_TITLE_ID, $customerTitleId, $comparison);
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
     * @return CustomerTitleDescQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CustomerTitleDescPeer::LANG, $lang, $comparison);
    }

    /**
     * Filter the query on the short column
     *
     * Example usage:
     * <code>
     * $query->filterByShort('fooValue');   // WHERE short = 'fooValue'
     * $query->filterByShort('%fooValue%'); // WHERE short LIKE '%fooValue%'
     * </code>
     *
     * @param     string $short The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CustomerTitleDescQuery The current query, for fluid interface
     */
    public function filterByShort($short = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($short)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $short)) {
                $short = str_replace('*', '%', $short);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CustomerTitleDescPeer::SHORT, $short, $comparison);
    }

    /**
     * Filter the query on the long column
     *
     * Example usage:
     * <code>
     * $query->filterByLong('fooValue');   // WHERE long = 'fooValue'
     * $query->filterByLong('%fooValue%'); // WHERE long LIKE '%fooValue%'
     * </code>
     *
     * @param     string $long The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CustomerTitleDescQuery The current query, for fluid interface
     */
    public function filterByLong($long = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($long)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $long)) {
                $long = str_replace('*', '%', $long);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CustomerTitleDescPeer::LONG, $long, $comparison);
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
     * @return CustomerTitleDescQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(CustomerTitleDescPeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(CustomerTitleDescPeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerTitleDescPeer::CREATED_AT, $createdAt, $comparison);
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
     * @return CustomerTitleDescQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(CustomerTitleDescPeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(CustomerTitleDescPeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerTitleDescPeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related CustomerTitle object
     *
     * @param   CustomerTitle|PropelObjectCollection $customerTitle  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   CustomerTitleDescQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByCustomerTitle($customerTitle, $comparison = null)
    {
        if ($customerTitle instanceof CustomerTitle) {
            return $this
                ->addUsingAlias(CustomerTitleDescPeer::CUSTOMER_TITLE_ID, $customerTitle->getId(), $comparison);
        } elseif ($customerTitle instanceof PropelObjectCollection) {
            return $this
                ->useCustomerTitleQuery()
                ->filterByPrimaryKeys($customerTitle->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCustomerTitle() only accepts arguments of type CustomerTitle or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CustomerTitle relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return CustomerTitleDescQuery The current query, for fluid interface
     */
    public function joinCustomerTitle($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CustomerTitle');

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
            $this->addJoinObject($join, 'CustomerTitle');
        }

        return $this;
    }

    /**
     * Use the CustomerTitle relation CustomerTitle object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CustomerTitleQuery A secondary query class using the current class as primary query
     */
    public function useCustomerTitleQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCustomerTitle($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CustomerTitle', '\Thelia\Model\CustomerTitleQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   CustomerTitleDesc $customerTitleDesc Object to remove from the list of results
     *
     * @return CustomerTitleDescQuery The current query, for fluid interface
     */
    public function prune($customerTitleDesc = null)
    {
        if ($customerTitleDesc) {
            $this->addUsingAlias(CustomerTitleDescPeer::ID, $customerTitleDesc->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

}
