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
use Thelia\Model\Address;
use Thelia\Model\Customer;
use Thelia\Model\CustomerTitle;
use Thelia\Model\CustomerTitleI18n;
use Thelia\Model\CustomerTitlePeer;
use Thelia\Model\CustomerTitleQuery;

/**
 * Base class that represents a query for the 'customer_title' table.
 *
 *
 *
 * @method CustomerTitleQuery orderById($order = Criteria::ASC) Order by the id column
 * @method CustomerTitleQuery orderByByDefault($order = Criteria::ASC) Order by the by_default column
 * @method CustomerTitleQuery orderByPosition($order = Criteria::ASC) Order by the position column
 * @method CustomerTitleQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method CustomerTitleQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method CustomerTitleQuery groupById() Group by the id column
 * @method CustomerTitleQuery groupByByDefault() Group by the by_default column
 * @method CustomerTitleQuery groupByPosition() Group by the position column
 * @method CustomerTitleQuery groupByCreatedAt() Group by the created_at column
 * @method CustomerTitleQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method CustomerTitleQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method CustomerTitleQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method CustomerTitleQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method CustomerTitleQuery leftJoinCustomer($relationAlias = null) Adds a LEFT JOIN clause to the query using the Customer relation
 * @method CustomerTitleQuery rightJoinCustomer($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Customer relation
 * @method CustomerTitleQuery innerJoinCustomer($relationAlias = null) Adds a INNER JOIN clause to the query using the Customer relation
 *
 * @method CustomerTitleQuery leftJoinAddress($relationAlias = null) Adds a LEFT JOIN clause to the query using the Address relation
 * @method CustomerTitleQuery rightJoinAddress($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Address relation
 * @method CustomerTitleQuery innerJoinAddress($relationAlias = null) Adds a INNER JOIN clause to the query using the Address relation
 *
 * @method CustomerTitleQuery leftJoinCustomerTitleI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the CustomerTitleI18n relation
 * @method CustomerTitleQuery rightJoinCustomerTitleI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CustomerTitleI18n relation
 * @method CustomerTitleQuery innerJoinCustomerTitleI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the CustomerTitleI18n relation
 *
 * @method CustomerTitle findOne(PropelPDO $con = null) Return the first CustomerTitle matching the query
 * @method CustomerTitle findOneOrCreate(PropelPDO $con = null) Return the first CustomerTitle matching the query, or a new CustomerTitle object populated from the query conditions when no match is found
 *
 * @method CustomerTitle findOneById(int $id) Return the first CustomerTitle filtered by the id column
 * @method CustomerTitle findOneByByDefault(int $by_default) Return the first CustomerTitle filtered by the by_default column
 * @method CustomerTitle findOneByPosition(string $position) Return the first CustomerTitle filtered by the position column
 * @method CustomerTitle findOneByCreatedAt(string $created_at) Return the first CustomerTitle filtered by the created_at column
 * @method CustomerTitle findOneByUpdatedAt(string $updated_at) Return the first CustomerTitle filtered by the updated_at column
 *
 * @method array findById(int $id) Return CustomerTitle objects filtered by the id column
 * @method array findByByDefault(int $by_default) Return CustomerTitle objects filtered by the by_default column
 * @method array findByPosition(string $position) Return CustomerTitle objects filtered by the position column
 * @method array findByCreatedAt(string $created_at) Return CustomerTitle objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return CustomerTitle objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseCustomerTitleQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseCustomerTitleQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\CustomerTitle', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new CustomerTitleQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     CustomerTitleQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return CustomerTitleQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof CustomerTitleQuery) {
            return $criteria;
        }
        $query = new CustomerTitleQuery();
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
     * @return   CustomerTitle|CustomerTitle[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = CustomerTitlePeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(CustomerTitlePeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return   CustomerTitle A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `BY_DEFAULT`, `POSITION`, `CREATED_AT`, `UPDATED_AT` FROM `customer_title` WHERE `ID` = :p0';
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
            $obj = new CustomerTitle();
            $obj->hydrate($row);
            CustomerTitlePeer::addInstanceToPool($obj, (string) $key);
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
     * @return CustomerTitle|CustomerTitle[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|CustomerTitle[]|mixed the list of results, formatted by the current formatter
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
     * @return CustomerTitleQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(CustomerTitlePeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return CustomerTitleQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(CustomerTitlePeer::ID, $keys, Criteria::IN);
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
     * @return CustomerTitleQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(CustomerTitlePeer::ID, $id, $comparison);
    }

    /**
     * Filter the query on the by_default column
     *
     * Example usage:
     * <code>
     * $query->filterByByDefault(1234); // WHERE by_default = 1234
     * $query->filterByByDefault(array(12, 34)); // WHERE by_default IN (12, 34)
     * $query->filterByByDefault(array('min' => 12)); // WHERE by_default > 12
     * </code>
     *
     * @param     mixed $byDefault The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CustomerTitleQuery The current query, for fluid interface
     */
    public function filterByByDefault($byDefault = null, $comparison = null)
    {
        if (is_array($byDefault)) {
            $useMinMax = false;
            if (isset($byDefault['min'])) {
                $this->addUsingAlias(CustomerTitlePeer::BY_DEFAULT, $byDefault['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($byDefault['max'])) {
                $this->addUsingAlias(CustomerTitlePeer::BY_DEFAULT, $byDefault['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerTitlePeer::BY_DEFAULT, $byDefault, $comparison);
    }

    /**
     * Filter the query on the position column
     *
     * Example usage:
     * <code>
     * $query->filterByPosition('fooValue');   // WHERE position = 'fooValue'
     * $query->filterByPosition('%fooValue%'); // WHERE position LIKE '%fooValue%'
     * </code>
     *
     * @param     string $position The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CustomerTitleQuery The current query, for fluid interface
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($position)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $position)) {
                $position = str_replace('*', '%', $position);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CustomerTitlePeer::POSITION, $position, $comparison);
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
     * @return CustomerTitleQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(CustomerTitlePeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(CustomerTitlePeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerTitlePeer::CREATED_AT, $createdAt, $comparison);
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
     * @return CustomerTitleQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(CustomerTitlePeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(CustomerTitlePeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerTitlePeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related Customer object
     *
     * @param   Customer|PropelObjectCollection $customer  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   CustomerTitleQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByCustomer($customer, $comparison = null)
    {
        if ($customer instanceof Customer) {
            return $this
                ->addUsingAlias(CustomerTitlePeer::ID, $customer->getCustomerTitleId(), $comparison);
        } elseif ($customer instanceof PropelObjectCollection) {
            return $this
                ->useCustomerQuery()
                ->filterByPrimaryKeys($customer->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCustomer() only accepts arguments of type Customer or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Customer relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return CustomerTitleQuery The current query, for fluid interface
     */
    public function joinCustomer($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Customer');

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
            $this->addJoinObject($join, 'Customer');
        }

        return $this;
    }

    /**
     * Use the Customer relation Customer object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CustomerQuery A secondary query class using the current class as primary query
     */
    public function useCustomerQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinCustomer($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Customer', '\Thelia\Model\CustomerQuery');
    }

    /**
     * Filter the query by a related Address object
     *
     * @param   Address|PropelObjectCollection $address  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   CustomerTitleQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByAddress($address, $comparison = null)
    {
        if ($address instanceof Address) {
            return $this
                ->addUsingAlias(CustomerTitlePeer::ID, $address->getCustomerTitleId(), $comparison);
        } elseif ($address instanceof PropelObjectCollection) {
            return $this
                ->useAddressQuery()
                ->filterByPrimaryKeys($address->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByAddress() only accepts arguments of type Address or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Address relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return CustomerTitleQuery The current query, for fluid interface
     */
    public function joinAddress($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Address');

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
            $this->addJoinObject($join, 'Address');
        }

        return $this;
    }

    /**
     * Use the Address relation Address object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\AddressQuery A secondary query class using the current class as primary query
     */
    public function useAddressQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinAddress($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Address', '\Thelia\Model\AddressQuery');
    }

    /**
     * Filter the query by a related CustomerTitleI18n object
     *
     * @param   CustomerTitleI18n|PropelObjectCollection $customerTitleI18n  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   CustomerTitleQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByCustomerTitleI18n($customerTitleI18n, $comparison = null)
    {
        if ($customerTitleI18n instanceof CustomerTitleI18n) {
            return $this
                ->addUsingAlias(CustomerTitlePeer::ID, $customerTitleI18n->getId(), $comparison);
        } elseif ($customerTitleI18n instanceof PropelObjectCollection) {
            return $this
                ->useCustomerTitleI18nQuery()
                ->filterByPrimaryKeys($customerTitleI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCustomerTitleI18n() only accepts arguments of type CustomerTitleI18n or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CustomerTitleI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return CustomerTitleQuery The current query, for fluid interface
     */
    public function joinCustomerTitleI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CustomerTitleI18n');

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
            $this->addJoinObject($join, 'CustomerTitleI18n');
        }

        return $this;
    }

    /**
     * Use the CustomerTitleI18n relation CustomerTitleI18n object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CustomerTitleI18nQuery A secondary query class using the current class as primary query
     */
    public function useCustomerTitleI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinCustomerTitleI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CustomerTitleI18n', '\Thelia\Model\CustomerTitleI18nQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   CustomerTitle $customerTitle Object to remove from the list of results
     *
     * @return CustomerTitleQuery The current query, for fluid interface
     */
    public function prune($customerTitle = null)
    {
        if ($customerTitle) {
            $this->addUsingAlias(CustomerTitlePeer::ID, $customerTitle->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    // timestampable behavior

    /**
     * Filter by the latest updated
     *
     * @param      int $nbDays Maximum age of the latest update in days
     *
     * @return     CustomerTitleQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(CustomerTitlePeer::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     CustomerTitleQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(CustomerTitlePeer::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     CustomerTitleQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(CustomerTitlePeer::UPDATED_AT);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     CustomerTitleQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(CustomerTitlePeer::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by create date desc
     *
     * @return     CustomerTitleQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(CustomerTitlePeer::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     CustomerTitleQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(CustomerTitlePeer::CREATED_AT);
    }
    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    CustomerTitleQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_EN', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'CustomerTitleI18n';

        return $this
            ->joinCustomerTitleI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    CustomerTitleQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_EN', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('CustomerTitleI18n');
        $this->with['CustomerTitleI18n']->setIsWithOneToMany(false);

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
     * @return    CustomerTitleI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_EN', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CustomerTitleI18n', 'Thelia\Model\CustomerTitleI18nQuery');
    }

}
