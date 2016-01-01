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
use Thelia\Model\OrderAddress as ChildOrderAddress;
use Thelia\Model\OrderAddressQuery as ChildOrderAddressQuery;
use Thelia\Model\Map\OrderAddressTableMap;

/**
 * Base class that represents a query for the 'order_address' table.
 *
 *
 *
 * @method     ChildOrderAddressQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildOrderAddressQuery orderByCustomerTitleId($order = Criteria::ASC) Order by the customer_title_id column
 * @method     ChildOrderAddressQuery orderByCompany($order = Criteria::ASC) Order by the company column
 * @method     ChildOrderAddressQuery orderByFirstname($order = Criteria::ASC) Order by the firstname column
 * @method     ChildOrderAddressQuery orderByLastname($order = Criteria::ASC) Order by the lastname column
 * @method     ChildOrderAddressQuery orderByAddress1($order = Criteria::ASC) Order by the address1 column
 * @method     ChildOrderAddressQuery orderByAddress2($order = Criteria::ASC) Order by the address2 column
 * @method     ChildOrderAddressQuery orderByAddress3($order = Criteria::ASC) Order by the address3 column
 * @method     ChildOrderAddressQuery orderByZipcode($order = Criteria::ASC) Order by the zipcode column
 * @method     ChildOrderAddressQuery orderByCity($order = Criteria::ASC) Order by the city column
 * @method     ChildOrderAddressQuery orderByPhone($order = Criteria::ASC) Order by the phone column
 * @method     ChildOrderAddressQuery orderByCellphone($order = Criteria::ASC) Order by the cellphone column
 * @method     ChildOrderAddressQuery orderByCountryId($order = Criteria::ASC) Order by the country_id column
 * @method     ChildOrderAddressQuery orderByStateId($order = Criteria::ASC) Order by the state_id column
 * @method     ChildOrderAddressQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildOrderAddressQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildOrderAddressQuery groupById() Group by the id column
 * @method     ChildOrderAddressQuery groupByCustomerTitleId() Group by the customer_title_id column
 * @method     ChildOrderAddressQuery groupByCompany() Group by the company column
 * @method     ChildOrderAddressQuery groupByFirstname() Group by the firstname column
 * @method     ChildOrderAddressQuery groupByLastname() Group by the lastname column
 * @method     ChildOrderAddressQuery groupByAddress1() Group by the address1 column
 * @method     ChildOrderAddressQuery groupByAddress2() Group by the address2 column
 * @method     ChildOrderAddressQuery groupByAddress3() Group by the address3 column
 * @method     ChildOrderAddressQuery groupByZipcode() Group by the zipcode column
 * @method     ChildOrderAddressQuery groupByCity() Group by the city column
 * @method     ChildOrderAddressQuery groupByPhone() Group by the phone column
 * @method     ChildOrderAddressQuery groupByCellphone() Group by the cellphone column
 * @method     ChildOrderAddressQuery groupByCountryId() Group by the country_id column
 * @method     ChildOrderAddressQuery groupByStateId() Group by the state_id column
 * @method     ChildOrderAddressQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildOrderAddressQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildOrderAddressQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildOrderAddressQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildOrderAddressQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildOrderAddressQuery leftJoinCustomerTitle($relationAlias = null) Adds a LEFT JOIN clause to the query using the CustomerTitle relation
 * @method     ChildOrderAddressQuery rightJoinCustomerTitle($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CustomerTitle relation
 * @method     ChildOrderAddressQuery innerJoinCustomerTitle($relationAlias = null) Adds a INNER JOIN clause to the query using the CustomerTitle relation
 *
 * @method     ChildOrderAddressQuery leftJoinCountry($relationAlias = null) Adds a LEFT JOIN clause to the query using the Country relation
 * @method     ChildOrderAddressQuery rightJoinCountry($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Country relation
 * @method     ChildOrderAddressQuery innerJoinCountry($relationAlias = null) Adds a INNER JOIN clause to the query using the Country relation
 *
 * @method     ChildOrderAddressQuery leftJoinState($relationAlias = null) Adds a LEFT JOIN clause to the query using the State relation
 * @method     ChildOrderAddressQuery rightJoinState($relationAlias = null) Adds a RIGHT JOIN clause to the query using the State relation
 * @method     ChildOrderAddressQuery innerJoinState($relationAlias = null) Adds a INNER JOIN clause to the query using the State relation
 *
 * @method     ChildOrderAddressQuery leftJoinOrderRelatedByInvoiceOrderAddressId($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderRelatedByInvoiceOrderAddressId relation
 * @method     ChildOrderAddressQuery rightJoinOrderRelatedByInvoiceOrderAddressId($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderRelatedByInvoiceOrderAddressId relation
 * @method     ChildOrderAddressQuery innerJoinOrderRelatedByInvoiceOrderAddressId($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderRelatedByInvoiceOrderAddressId relation
 *
 * @method     ChildOrderAddressQuery leftJoinOrderRelatedByDeliveryOrderAddressId($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderRelatedByDeliveryOrderAddressId relation
 * @method     ChildOrderAddressQuery rightJoinOrderRelatedByDeliveryOrderAddressId($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderRelatedByDeliveryOrderAddressId relation
 * @method     ChildOrderAddressQuery innerJoinOrderRelatedByDeliveryOrderAddressId($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderRelatedByDeliveryOrderAddressId relation
 *
 * @method     ChildOrderAddress findOne(ConnectionInterface $con = null) Return the first ChildOrderAddress matching the query
 * @method     ChildOrderAddress findOneOrCreate(ConnectionInterface $con = null) Return the first ChildOrderAddress matching the query, or a new ChildOrderAddress object populated from the query conditions when no match is found
 *
 * @method     ChildOrderAddress findOneById(int $id) Return the first ChildOrderAddress filtered by the id column
 * @method     ChildOrderAddress findOneByCustomerTitleId(int $customer_title_id) Return the first ChildOrderAddress filtered by the customer_title_id column
 * @method     ChildOrderAddress findOneByCompany(string $company) Return the first ChildOrderAddress filtered by the company column
 * @method     ChildOrderAddress findOneByFirstname(string $firstname) Return the first ChildOrderAddress filtered by the firstname column
 * @method     ChildOrderAddress findOneByLastname(string $lastname) Return the first ChildOrderAddress filtered by the lastname column
 * @method     ChildOrderAddress findOneByAddress1(string $address1) Return the first ChildOrderAddress filtered by the address1 column
 * @method     ChildOrderAddress findOneByAddress2(string $address2) Return the first ChildOrderAddress filtered by the address2 column
 * @method     ChildOrderAddress findOneByAddress3(string $address3) Return the first ChildOrderAddress filtered by the address3 column
 * @method     ChildOrderAddress findOneByZipcode(string $zipcode) Return the first ChildOrderAddress filtered by the zipcode column
 * @method     ChildOrderAddress findOneByCity(string $city) Return the first ChildOrderAddress filtered by the city column
 * @method     ChildOrderAddress findOneByPhone(string $phone) Return the first ChildOrderAddress filtered by the phone column
 * @method     ChildOrderAddress findOneByCellphone(string $cellphone) Return the first ChildOrderAddress filtered by the cellphone column
 * @method     ChildOrderAddress findOneByCountryId(int $country_id) Return the first ChildOrderAddress filtered by the country_id column
 * @method     ChildOrderAddress findOneByStateId(int $state_id) Return the first ChildOrderAddress filtered by the state_id column
 * @method     ChildOrderAddress findOneByCreatedAt(string $created_at) Return the first ChildOrderAddress filtered by the created_at column
 * @method     ChildOrderAddress findOneByUpdatedAt(string $updated_at) Return the first ChildOrderAddress filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildOrderAddress objects filtered by the id column
 * @method     array findByCustomerTitleId(int $customer_title_id) Return ChildOrderAddress objects filtered by the customer_title_id column
 * @method     array findByCompany(string $company) Return ChildOrderAddress objects filtered by the company column
 * @method     array findByFirstname(string $firstname) Return ChildOrderAddress objects filtered by the firstname column
 * @method     array findByLastname(string $lastname) Return ChildOrderAddress objects filtered by the lastname column
 * @method     array findByAddress1(string $address1) Return ChildOrderAddress objects filtered by the address1 column
 * @method     array findByAddress2(string $address2) Return ChildOrderAddress objects filtered by the address2 column
 * @method     array findByAddress3(string $address3) Return ChildOrderAddress objects filtered by the address3 column
 * @method     array findByZipcode(string $zipcode) Return ChildOrderAddress objects filtered by the zipcode column
 * @method     array findByCity(string $city) Return ChildOrderAddress objects filtered by the city column
 * @method     array findByPhone(string $phone) Return ChildOrderAddress objects filtered by the phone column
 * @method     array findByCellphone(string $cellphone) Return ChildOrderAddress objects filtered by the cellphone column
 * @method     array findByCountryId(int $country_id) Return ChildOrderAddress objects filtered by the country_id column
 * @method     array findByStateId(int $state_id) Return ChildOrderAddress objects filtered by the state_id column
 * @method     array findByCreatedAt(string $created_at) Return ChildOrderAddress objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildOrderAddress objects filtered by the updated_at column
 *
 */
abstract class OrderAddressQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\OrderAddressQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\OrderAddress', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildOrderAddressQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildOrderAddressQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\OrderAddressQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\OrderAddressQuery();
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
     * @return ChildOrderAddress|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = OrderAddressTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(OrderAddressTableMap::DATABASE_NAME);
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
     * @return   ChildOrderAddress A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `CUSTOMER_TITLE_ID`, `COMPANY`, `FIRSTNAME`, `LASTNAME`, `ADDRESS1`, `ADDRESS2`, `ADDRESS3`, `ZIPCODE`, `CITY`, `PHONE`, `CELLPHONE`, `COUNTRY_ID`, `STATE_ID`, `CREATED_AT`, `UPDATED_AT` FROM `order_address` WHERE `ID` = :p0';
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
            $obj = new ChildOrderAddress();
            $obj->hydrate($row);
            OrderAddressTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildOrderAddress|array|mixed the result, formatted by the current formatter
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
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(OrderAddressTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(OrderAddressTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(OrderAddressTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(OrderAddressTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::ID, $id, $comparison);
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
     * @see       filterByCustomerTitle()
     *
     * @param     mixed $customerTitleId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByCustomerTitleId($customerTitleId = null, $comparison = null)
    {
        if (is_array($customerTitleId)) {
            $useMinMax = false;
            if (isset($customerTitleId['min'])) {
                $this->addUsingAlias(OrderAddressTableMap::CUSTOMER_TITLE_ID, $customerTitleId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($customerTitleId['max'])) {
                $this->addUsingAlias(OrderAddressTableMap::CUSTOMER_TITLE_ID, $customerTitleId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::CUSTOMER_TITLE_ID, $customerTitleId, $comparison);
    }

    /**
     * Filter the query on the company column
     *
     * Example usage:
     * <code>
     * $query->filterByCompany('fooValue');   // WHERE company = 'fooValue'
     * $query->filterByCompany('%fooValue%'); // WHERE company LIKE '%fooValue%'
     * </code>
     *
     * @param     string $company The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByCompany($company = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($company)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $company)) {
                $company = str_replace('*', '%', $company);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::COMPANY, $company, $comparison);
    }

    /**
     * Filter the query on the firstname column
     *
     * Example usage:
     * <code>
     * $query->filterByFirstname('fooValue');   // WHERE firstname = 'fooValue'
     * $query->filterByFirstname('%fooValue%'); // WHERE firstname LIKE '%fooValue%'
     * </code>
     *
     * @param     string $firstname The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByFirstname($firstname = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($firstname)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $firstname)) {
                $firstname = str_replace('*', '%', $firstname);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::FIRSTNAME, $firstname, $comparison);
    }

    /**
     * Filter the query on the lastname column
     *
     * Example usage:
     * <code>
     * $query->filterByLastname('fooValue');   // WHERE lastname = 'fooValue'
     * $query->filterByLastname('%fooValue%'); // WHERE lastname LIKE '%fooValue%'
     * </code>
     *
     * @param     string $lastname The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByLastname($lastname = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($lastname)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $lastname)) {
                $lastname = str_replace('*', '%', $lastname);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::LASTNAME, $lastname, $comparison);
    }

    /**
     * Filter the query on the address1 column
     *
     * Example usage:
     * <code>
     * $query->filterByAddress1('fooValue');   // WHERE address1 = 'fooValue'
     * $query->filterByAddress1('%fooValue%'); // WHERE address1 LIKE '%fooValue%'
     * </code>
     *
     * @param     string $address1 The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByAddress1($address1 = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($address1)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $address1)) {
                $address1 = str_replace('*', '%', $address1);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::ADDRESS1, $address1, $comparison);
    }

    /**
     * Filter the query on the address2 column
     *
     * Example usage:
     * <code>
     * $query->filterByAddress2('fooValue');   // WHERE address2 = 'fooValue'
     * $query->filterByAddress2('%fooValue%'); // WHERE address2 LIKE '%fooValue%'
     * </code>
     *
     * @param     string $address2 The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByAddress2($address2 = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($address2)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $address2)) {
                $address2 = str_replace('*', '%', $address2);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::ADDRESS2, $address2, $comparison);
    }

    /**
     * Filter the query on the address3 column
     *
     * Example usage:
     * <code>
     * $query->filterByAddress3('fooValue');   // WHERE address3 = 'fooValue'
     * $query->filterByAddress3('%fooValue%'); // WHERE address3 LIKE '%fooValue%'
     * </code>
     *
     * @param     string $address3 The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByAddress3($address3 = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($address3)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $address3)) {
                $address3 = str_replace('*', '%', $address3);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::ADDRESS3, $address3, $comparison);
    }

    /**
     * Filter the query on the zipcode column
     *
     * Example usage:
     * <code>
     * $query->filterByZipcode('fooValue');   // WHERE zipcode = 'fooValue'
     * $query->filterByZipcode('%fooValue%'); // WHERE zipcode LIKE '%fooValue%'
     * </code>
     *
     * @param     string $zipcode The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByZipcode($zipcode = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($zipcode)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $zipcode)) {
                $zipcode = str_replace('*', '%', $zipcode);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::ZIPCODE, $zipcode, $comparison);
    }

    /**
     * Filter the query on the city column
     *
     * Example usage:
     * <code>
     * $query->filterByCity('fooValue');   // WHERE city = 'fooValue'
     * $query->filterByCity('%fooValue%'); // WHERE city LIKE '%fooValue%'
     * </code>
     *
     * @param     string $city The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByCity($city = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($city)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $city)) {
                $city = str_replace('*', '%', $city);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::CITY, $city, $comparison);
    }

    /**
     * Filter the query on the phone column
     *
     * Example usage:
     * <code>
     * $query->filterByPhone('fooValue');   // WHERE phone = 'fooValue'
     * $query->filterByPhone('%fooValue%'); // WHERE phone LIKE '%fooValue%'
     * </code>
     *
     * @param     string $phone The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByPhone($phone = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($phone)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $phone)) {
                $phone = str_replace('*', '%', $phone);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::PHONE, $phone, $comparison);
    }

    /**
     * Filter the query on the cellphone column
     *
     * Example usage:
     * <code>
     * $query->filterByCellphone('fooValue');   // WHERE cellphone = 'fooValue'
     * $query->filterByCellphone('%fooValue%'); // WHERE cellphone LIKE '%fooValue%'
     * </code>
     *
     * @param     string $cellphone The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByCellphone($cellphone = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($cellphone)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $cellphone)) {
                $cellphone = str_replace('*', '%', $cellphone);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::CELLPHONE, $cellphone, $comparison);
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
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByCountryId($countryId = null, $comparison = null)
    {
        if (is_array($countryId)) {
            $useMinMax = false;
            if (isset($countryId['min'])) {
                $this->addUsingAlias(OrderAddressTableMap::COUNTRY_ID, $countryId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($countryId['max'])) {
                $this->addUsingAlias(OrderAddressTableMap::COUNTRY_ID, $countryId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::COUNTRY_ID, $countryId, $comparison);
    }

    /**
     * Filter the query on the state_id column
     *
     * Example usage:
     * <code>
     * $query->filterByStateId(1234); // WHERE state_id = 1234
     * $query->filterByStateId(array(12, 34)); // WHERE state_id IN (12, 34)
     * $query->filterByStateId(array('min' => 12)); // WHERE state_id > 12
     * </code>
     *
     * @see       filterByState()
     *
     * @param     mixed $stateId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByStateId($stateId = null, $comparison = null)
    {
        if (is_array($stateId)) {
            $useMinMax = false;
            if (isset($stateId['min'])) {
                $this->addUsingAlias(OrderAddressTableMap::STATE_ID, $stateId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($stateId['max'])) {
                $this->addUsingAlias(OrderAddressTableMap::STATE_ID, $stateId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::STATE_ID, $stateId, $comparison);
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
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(OrderAddressTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(OrderAddressTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(OrderAddressTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(OrderAddressTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderAddressTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\CustomerTitle object
     *
     * @param \Thelia\Model\CustomerTitle|ObjectCollection $customerTitle The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByCustomerTitle($customerTitle, $comparison = null)
    {
        if ($customerTitle instanceof \Thelia\Model\CustomerTitle) {
            return $this
                ->addUsingAlias(OrderAddressTableMap::CUSTOMER_TITLE_ID, $customerTitle->getId(), $comparison);
        } elseif ($customerTitle instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderAddressTableMap::CUSTOMER_TITLE_ID, $customerTitle->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByCustomerTitle() only accepts arguments of type \Thelia\Model\CustomerTitle or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CustomerTitle relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function joinCustomerTitle($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
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
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CustomerTitleQuery A secondary query class using the current class as primary query
     */
    public function useCustomerTitleQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinCustomerTitle($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CustomerTitle', '\Thelia\Model\CustomerTitleQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Country object
     *
     * @param \Thelia\Model\Country|ObjectCollection $country The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByCountry($country, $comparison = null)
    {
        if ($country instanceof \Thelia\Model\Country) {
            return $this
                ->addUsingAlias(OrderAddressTableMap::COUNTRY_ID, $country->getId(), $comparison);
        } elseif ($country instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderAddressTableMap::COUNTRY_ID, $country->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByCountry() only accepts arguments of type \Thelia\Model\Country or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Country relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function joinCountry($relationAlias = null, $joinType = Criteria::INNER_JOIN)
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
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CountryQuery A secondary query class using the current class as primary query
     */
    public function useCountryQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCountry($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Country', '\Thelia\Model\CountryQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\State object
     *
     * @param \Thelia\Model\State|ObjectCollection $state The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByState($state, $comparison = null)
    {
        if ($state instanceof \Thelia\Model\State) {
            return $this
                ->addUsingAlias(OrderAddressTableMap::STATE_ID, $state->getId(), $comparison);
        } elseif ($state instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderAddressTableMap::STATE_ID, $state->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByState() only accepts arguments of type \Thelia\Model\State or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the State relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function joinState($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('State');

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
            $this->addJoinObject($join, 'State');
        }

        return $this;
    }

    /**
     * Use the State relation State object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\StateQuery A secondary query class using the current class as primary query
     */
    public function useStateQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinState($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'State', '\Thelia\Model\StateQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Order object
     *
     * @param \Thelia\Model\Order|ObjectCollection $order  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByOrderRelatedByInvoiceOrderAddressId($order, $comparison = null)
    {
        if ($order instanceof \Thelia\Model\Order) {
            return $this
                ->addUsingAlias(OrderAddressTableMap::ID, $order->getInvoiceOrderAddressId(), $comparison);
        } elseif ($order instanceof ObjectCollection) {
            return $this
                ->useOrderRelatedByInvoiceOrderAddressIdQuery()
                ->filterByPrimaryKeys($order->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByOrderRelatedByInvoiceOrderAddressId() only accepts arguments of type \Thelia\Model\Order or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderRelatedByInvoiceOrderAddressId relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function joinOrderRelatedByInvoiceOrderAddressId($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderRelatedByInvoiceOrderAddressId');

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
            $this->addJoinObject($join, 'OrderRelatedByInvoiceOrderAddressId');
        }

        return $this;
    }

    /**
     * Use the OrderRelatedByInvoiceOrderAddressId relation Order object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderQuery A secondary query class using the current class as primary query
     */
    public function useOrderRelatedByInvoiceOrderAddressIdQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderRelatedByInvoiceOrderAddressId($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderRelatedByInvoiceOrderAddressId', '\Thelia\Model\OrderQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Order object
     *
     * @param \Thelia\Model\Order|ObjectCollection $order  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function filterByOrderRelatedByDeliveryOrderAddressId($order, $comparison = null)
    {
        if ($order instanceof \Thelia\Model\Order) {
            return $this
                ->addUsingAlias(OrderAddressTableMap::ID, $order->getDeliveryOrderAddressId(), $comparison);
        } elseif ($order instanceof ObjectCollection) {
            return $this
                ->useOrderRelatedByDeliveryOrderAddressIdQuery()
                ->filterByPrimaryKeys($order->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByOrderRelatedByDeliveryOrderAddressId() only accepts arguments of type \Thelia\Model\Order or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderRelatedByDeliveryOrderAddressId relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function joinOrderRelatedByDeliveryOrderAddressId($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderRelatedByDeliveryOrderAddressId');

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
            $this->addJoinObject($join, 'OrderRelatedByDeliveryOrderAddressId');
        }

        return $this;
    }

    /**
     * Use the OrderRelatedByDeliveryOrderAddressId relation Order object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderQuery A secondary query class using the current class as primary query
     */
    public function useOrderRelatedByDeliveryOrderAddressIdQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderRelatedByDeliveryOrderAddressId($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderRelatedByDeliveryOrderAddressId', '\Thelia\Model\OrderQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildOrderAddress $orderAddress Object to remove from the list of results
     *
     * @return ChildOrderAddressQuery The current query, for fluid interface
     */
    public function prune($orderAddress = null)
    {
        if ($orderAddress) {
            $this->addUsingAlias(OrderAddressTableMap::ID, $orderAddress->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the order_address table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderAddressTableMap::DATABASE_NAME);
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
            OrderAddressTableMap::clearInstancePool();
            OrderAddressTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildOrderAddress or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildOrderAddress object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(OrderAddressTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(OrderAddressTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        OrderAddressTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            OrderAddressTableMap::clearRelatedInstancePool();
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
     * @return     ChildOrderAddressQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(OrderAddressTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildOrderAddressQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(OrderAddressTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildOrderAddressQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(OrderAddressTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildOrderAddressQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(OrderAddressTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildOrderAddressQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(OrderAddressTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildOrderAddressQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(OrderAddressTableMap::CREATED_AT);
    }

} // OrderAddressQuery
