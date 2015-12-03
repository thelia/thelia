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
use Thelia\Model\Country as ChildCountry;
use Thelia\Model\CountryI18nQuery as ChildCountryI18nQuery;
use Thelia\Model\CountryQuery as ChildCountryQuery;
use Thelia\Model\Map\CountryTableMap;

/**
 * Base class that represents a query for the 'country' table.
 *
 *
 *
 * @method     ChildCountryQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildCountryQuery orderByVisible($order = Criteria::ASC) Order by the visible column
 * @method     ChildCountryQuery orderByIsocode($order = Criteria::ASC) Order by the isocode column
 * @method     ChildCountryQuery orderByIsoalpha2($order = Criteria::ASC) Order by the isoalpha2 column
 * @method     ChildCountryQuery orderByIsoalpha3($order = Criteria::ASC) Order by the isoalpha3 column
 * @method     ChildCountryQuery orderByHasStates($order = Criteria::ASC) Order by the has_states column
 * @method     ChildCountryQuery orderByNeedZipCode($order = Criteria::ASC) Order by the need_zip_code column
 * @method     ChildCountryQuery orderByZipCodeFormat($order = Criteria::ASC) Order by the zip_code_format column
 * @method     ChildCountryQuery orderByByDefault($order = Criteria::ASC) Order by the by_default column
 * @method     ChildCountryQuery orderByShopCountry($order = Criteria::ASC) Order by the shop_country column
 * @method     ChildCountryQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildCountryQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildCountryQuery groupById() Group by the id column
 * @method     ChildCountryQuery groupByVisible() Group by the visible column
 * @method     ChildCountryQuery groupByIsocode() Group by the isocode column
 * @method     ChildCountryQuery groupByIsoalpha2() Group by the isoalpha2 column
 * @method     ChildCountryQuery groupByIsoalpha3() Group by the isoalpha3 column
 * @method     ChildCountryQuery groupByHasStates() Group by the has_states column
 * @method     ChildCountryQuery groupByNeedZipCode() Group by the need_zip_code column
 * @method     ChildCountryQuery groupByZipCodeFormat() Group by the zip_code_format column
 * @method     ChildCountryQuery groupByByDefault() Group by the by_default column
 * @method     ChildCountryQuery groupByShopCountry() Group by the shop_country column
 * @method     ChildCountryQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildCountryQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildCountryQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildCountryQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildCountryQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildCountryQuery leftJoinState($relationAlias = null) Adds a LEFT JOIN clause to the query using the State relation
 * @method     ChildCountryQuery rightJoinState($relationAlias = null) Adds a RIGHT JOIN clause to the query using the State relation
 * @method     ChildCountryQuery innerJoinState($relationAlias = null) Adds a INNER JOIN clause to the query using the State relation
 *
 * @method     ChildCountryQuery leftJoinTaxRuleCountry($relationAlias = null) Adds a LEFT JOIN clause to the query using the TaxRuleCountry relation
 * @method     ChildCountryQuery rightJoinTaxRuleCountry($relationAlias = null) Adds a RIGHT JOIN clause to the query using the TaxRuleCountry relation
 * @method     ChildCountryQuery innerJoinTaxRuleCountry($relationAlias = null) Adds a INNER JOIN clause to the query using the TaxRuleCountry relation
 *
 * @method     ChildCountryQuery leftJoinAddress($relationAlias = null) Adds a LEFT JOIN clause to the query using the Address relation
 * @method     ChildCountryQuery rightJoinAddress($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Address relation
 * @method     ChildCountryQuery innerJoinAddress($relationAlias = null) Adds a INNER JOIN clause to the query using the Address relation
 *
 * @method     ChildCountryQuery leftJoinOrderAddress($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderAddress relation
 * @method     ChildCountryQuery rightJoinOrderAddress($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderAddress relation
 * @method     ChildCountryQuery innerJoinOrderAddress($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderAddress relation
 *
 * @method     ChildCountryQuery leftJoinCouponCountry($relationAlias = null) Adds a LEFT JOIN clause to the query using the CouponCountry relation
 * @method     ChildCountryQuery rightJoinCouponCountry($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CouponCountry relation
 * @method     ChildCountryQuery innerJoinCouponCountry($relationAlias = null) Adds a INNER JOIN clause to the query using the CouponCountry relation
 *
 * @method     ChildCountryQuery leftJoinOrderCouponCountry($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderCouponCountry relation
 * @method     ChildCountryQuery rightJoinOrderCouponCountry($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderCouponCountry relation
 * @method     ChildCountryQuery innerJoinOrderCouponCountry($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderCouponCountry relation
 *
 * @method     ChildCountryQuery leftJoinCountryArea($relationAlias = null) Adds a LEFT JOIN clause to the query using the CountryArea relation
 * @method     ChildCountryQuery rightJoinCountryArea($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CountryArea relation
 * @method     ChildCountryQuery innerJoinCountryArea($relationAlias = null) Adds a INNER JOIN clause to the query using the CountryArea relation
 *
 * @method     ChildCountryQuery leftJoinCountryI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the CountryI18n relation
 * @method     ChildCountryQuery rightJoinCountryI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CountryI18n relation
 * @method     ChildCountryQuery innerJoinCountryI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the CountryI18n relation
 *
 * @method     ChildCountry findOne(ConnectionInterface $con = null) Return the first ChildCountry matching the query
 * @method     ChildCountry findOneOrCreate(ConnectionInterface $con = null) Return the first ChildCountry matching the query, or a new ChildCountry object populated from the query conditions when no match is found
 *
 * @method     ChildCountry findOneById(int $id) Return the first ChildCountry filtered by the id column
 * @method     ChildCountry findOneByVisible(int $visible) Return the first ChildCountry filtered by the visible column
 * @method     ChildCountry findOneByIsocode(string $isocode) Return the first ChildCountry filtered by the isocode column
 * @method     ChildCountry findOneByIsoalpha2(string $isoalpha2) Return the first ChildCountry filtered by the isoalpha2 column
 * @method     ChildCountry findOneByIsoalpha3(string $isoalpha3) Return the first ChildCountry filtered by the isoalpha3 column
 * @method     ChildCountry findOneByHasStates(int $has_states) Return the first ChildCountry filtered by the has_states column
 * @method     ChildCountry findOneByNeedZipCode(int $need_zip_code) Return the first ChildCountry filtered by the need_zip_code column
 * @method     ChildCountry findOneByZipCodeFormat(string $zip_code_format) Return the first ChildCountry filtered by the zip_code_format column
 * @method     ChildCountry findOneByByDefault(int $by_default) Return the first ChildCountry filtered by the by_default column
 * @method     ChildCountry findOneByShopCountry(boolean $shop_country) Return the first ChildCountry filtered by the shop_country column
 * @method     ChildCountry findOneByCreatedAt(string $created_at) Return the first ChildCountry filtered by the created_at column
 * @method     ChildCountry findOneByUpdatedAt(string $updated_at) Return the first ChildCountry filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildCountry objects filtered by the id column
 * @method     array findByVisible(int $visible) Return ChildCountry objects filtered by the visible column
 * @method     array findByIsocode(string $isocode) Return ChildCountry objects filtered by the isocode column
 * @method     array findByIsoalpha2(string $isoalpha2) Return ChildCountry objects filtered by the isoalpha2 column
 * @method     array findByIsoalpha3(string $isoalpha3) Return ChildCountry objects filtered by the isoalpha3 column
 * @method     array findByHasStates(int $has_states) Return ChildCountry objects filtered by the has_states column
 * @method     array findByNeedZipCode(int $need_zip_code) Return ChildCountry objects filtered by the need_zip_code column
 * @method     array findByZipCodeFormat(string $zip_code_format) Return ChildCountry objects filtered by the zip_code_format column
 * @method     array findByByDefault(int $by_default) Return ChildCountry objects filtered by the by_default column
 * @method     array findByShopCountry(boolean $shop_country) Return ChildCountry objects filtered by the shop_country column
 * @method     array findByCreatedAt(string $created_at) Return ChildCountry objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildCountry objects filtered by the updated_at column
 *
 */
abstract class CountryQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\CountryQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Country', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildCountryQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildCountryQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\CountryQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\CountryQuery();
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
     * @return ChildCountry|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = CountryTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(CountryTableMap::DATABASE_NAME);
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
     * @return   ChildCountry A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `VISIBLE`, `ISOCODE`, `ISOALPHA2`, `ISOALPHA3`, `HAS_STATES`, `NEED_ZIP_CODE`, `ZIP_CODE_FORMAT`, `BY_DEFAULT`, `SHOP_COUNTRY`, `CREATED_AT`, `UPDATED_AT` FROM `country` WHERE `ID` = :p0';
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
            $obj = new ChildCountry();
            $obj->hydrate($row);
            CountryTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildCountry|array|mixed the result, formatted by the current formatter
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
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(CountryTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(CountryTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(CountryTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(CountryTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CountryTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the visible column
     *
     * Example usage:
     * <code>
     * $query->filterByVisible(1234); // WHERE visible = 1234
     * $query->filterByVisible(array(12, 34)); // WHERE visible IN (12, 34)
     * $query->filterByVisible(array('min' => 12)); // WHERE visible > 12
     * </code>
     *
     * @param     mixed $visible The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByVisible($visible = null, $comparison = null)
    {
        if (is_array($visible)) {
            $useMinMax = false;
            if (isset($visible['min'])) {
                $this->addUsingAlias(CountryTableMap::VISIBLE, $visible['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($visible['max'])) {
                $this->addUsingAlias(CountryTableMap::VISIBLE, $visible['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CountryTableMap::VISIBLE, $visible, $comparison);
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
     * @return ChildCountryQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CountryTableMap::ISOCODE, $isocode, $comparison);
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
     * @return ChildCountryQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CountryTableMap::ISOALPHA2, $isoalpha2, $comparison);
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
     * @return ChildCountryQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CountryTableMap::ISOALPHA3, $isoalpha3, $comparison);
    }

    /**
     * Filter the query on the has_states column
     *
     * Example usage:
     * <code>
     * $query->filterByHasStates(1234); // WHERE has_states = 1234
     * $query->filterByHasStates(array(12, 34)); // WHERE has_states IN (12, 34)
     * $query->filterByHasStates(array('min' => 12)); // WHERE has_states > 12
     * </code>
     *
     * @param     mixed $hasStates The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByHasStates($hasStates = null, $comparison = null)
    {
        if (is_array($hasStates)) {
            $useMinMax = false;
            if (isset($hasStates['min'])) {
                $this->addUsingAlias(CountryTableMap::HAS_STATES, $hasStates['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($hasStates['max'])) {
                $this->addUsingAlias(CountryTableMap::HAS_STATES, $hasStates['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CountryTableMap::HAS_STATES, $hasStates, $comparison);
    }

    /**
     * Filter the query on the need_zip_code column
     *
     * Example usage:
     * <code>
     * $query->filterByNeedZipCode(1234); // WHERE need_zip_code = 1234
     * $query->filterByNeedZipCode(array(12, 34)); // WHERE need_zip_code IN (12, 34)
     * $query->filterByNeedZipCode(array('min' => 12)); // WHERE need_zip_code > 12
     * </code>
     *
     * @param     mixed $needZipCode The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByNeedZipCode($needZipCode = null, $comparison = null)
    {
        if (is_array($needZipCode)) {
            $useMinMax = false;
            if (isset($needZipCode['min'])) {
                $this->addUsingAlias(CountryTableMap::NEED_ZIP_CODE, $needZipCode['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($needZipCode['max'])) {
                $this->addUsingAlias(CountryTableMap::NEED_ZIP_CODE, $needZipCode['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CountryTableMap::NEED_ZIP_CODE, $needZipCode, $comparison);
    }

    /**
     * Filter the query on the zip_code_format column
     *
     * Example usage:
     * <code>
     * $query->filterByZipCodeFormat('fooValue');   // WHERE zip_code_format = 'fooValue'
     * $query->filterByZipCodeFormat('%fooValue%'); // WHERE zip_code_format LIKE '%fooValue%'
     * </code>
     *
     * @param     string $zipCodeFormat The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByZipCodeFormat($zipCodeFormat = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($zipCodeFormat)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $zipCodeFormat)) {
                $zipCodeFormat = str_replace('*', '%', $zipCodeFormat);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CountryTableMap::ZIP_CODE_FORMAT, $zipCodeFormat, $comparison);
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
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByByDefault($byDefault = null, $comparison = null)
    {
        if (is_array($byDefault)) {
            $useMinMax = false;
            if (isset($byDefault['min'])) {
                $this->addUsingAlias(CountryTableMap::BY_DEFAULT, $byDefault['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($byDefault['max'])) {
                $this->addUsingAlias(CountryTableMap::BY_DEFAULT, $byDefault['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CountryTableMap::BY_DEFAULT, $byDefault, $comparison);
    }

    /**
     * Filter the query on the shop_country column
     *
     * Example usage:
     * <code>
     * $query->filterByShopCountry(true); // WHERE shop_country = true
     * $query->filterByShopCountry('yes'); // WHERE shop_country = true
     * </code>
     *
     * @param     boolean|string $shopCountry The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByShopCountry($shopCountry = null, $comparison = null)
    {
        if (is_string($shopCountry)) {
            $shop_country = in_array(strtolower($shopCountry), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(CountryTableMap::SHOP_COUNTRY, $shopCountry, $comparison);
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
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(CountryTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(CountryTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CountryTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(CountryTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(CountryTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CountryTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\State object
     *
     * @param \Thelia\Model\State|ObjectCollection $state  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByState($state, $comparison = null)
    {
        if ($state instanceof \Thelia\Model\State) {
            return $this
                ->addUsingAlias(CountryTableMap::ID, $state->getCountryId(), $comparison);
        } elseif ($state instanceof ObjectCollection) {
            return $this
                ->useStateQuery()
                ->filterByPrimaryKeys($state->getPrimaryKeys())
                ->endUse();
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
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function joinState($relationAlias = null, $joinType = Criteria::INNER_JOIN)
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
    public function useStateQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinState($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'State', '\Thelia\Model\StateQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\TaxRuleCountry object
     *
     * @param \Thelia\Model\TaxRuleCountry|ObjectCollection $taxRuleCountry  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByTaxRuleCountry($taxRuleCountry, $comparison = null)
    {
        if ($taxRuleCountry instanceof \Thelia\Model\TaxRuleCountry) {
            return $this
                ->addUsingAlias(CountryTableMap::ID, $taxRuleCountry->getCountryId(), $comparison);
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
     * @return ChildCountryQuery The current query, for fluid interface
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
     * Filter the query by a related \Thelia\Model\Address object
     *
     * @param \Thelia\Model\Address|ObjectCollection $address  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByAddress($address, $comparison = null)
    {
        if ($address instanceof \Thelia\Model\Address) {
            return $this
                ->addUsingAlias(CountryTableMap::ID, $address->getCountryId(), $comparison);
        } elseif ($address instanceof ObjectCollection) {
            return $this
                ->useAddressQuery()
                ->filterByPrimaryKeys($address->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByAddress() only accepts arguments of type \Thelia\Model\Address or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Address relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function joinAddress($relationAlias = null, $joinType = Criteria::INNER_JOIN)
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
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\AddressQuery A secondary query class using the current class as primary query
     */
    public function useAddressQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinAddress($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Address', '\Thelia\Model\AddressQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\OrderAddress object
     *
     * @param \Thelia\Model\OrderAddress|ObjectCollection $orderAddress  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByOrderAddress($orderAddress, $comparison = null)
    {
        if ($orderAddress instanceof \Thelia\Model\OrderAddress) {
            return $this
                ->addUsingAlias(CountryTableMap::ID, $orderAddress->getCountryId(), $comparison);
        } elseif ($orderAddress instanceof ObjectCollection) {
            return $this
                ->useOrderAddressQuery()
                ->filterByPrimaryKeys($orderAddress->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByOrderAddress() only accepts arguments of type \Thelia\Model\OrderAddress or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderAddress relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function joinOrderAddress($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderAddress');

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
            $this->addJoinObject($join, 'OrderAddress');
        }

        return $this;
    }

    /**
     * Use the OrderAddress relation OrderAddress object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderAddressQuery A secondary query class using the current class as primary query
     */
    public function useOrderAddressQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderAddress($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderAddress', '\Thelia\Model\OrderAddressQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\CouponCountry object
     *
     * @param \Thelia\Model\CouponCountry|ObjectCollection $couponCountry  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByCouponCountry($couponCountry, $comparison = null)
    {
        if ($couponCountry instanceof \Thelia\Model\CouponCountry) {
            return $this
                ->addUsingAlias(CountryTableMap::ID, $couponCountry->getCountryId(), $comparison);
        } elseif ($couponCountry instanceof ObjectCollection) {
            return $this
                ->useCouponCountryQuery()
                ->filterByPrimaryKeys($couponCountry->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCouponCountry() only accepts arguments of type \Thelia\Model\CouponCountry or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CouponCountry relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function joinCouponCountry($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CouponCountry');

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
            $this->addJoinObject($join, 'CouponCountry');
        }

        return $this;
    }

    /**
     * Use the CouponCountry relation CouponCountry object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CouponCountryQuery A secondary query class using the current class as primary query
     */
    public function useCouponCountryQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCouponCountry($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CouponCountry', '\Thelia\Model\CouponCountryQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\OrderCouponCountry object
     *
     * @param \Thelia\Model\OrderCouponCountry|ObjectCollection $orderCouponCountry  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByOrderCouponCountry($orderCouponCountry, $comparison = null)
    {
        if ($orderCouponCountry instanceof \Thelia\Model\OrderCouponCountry) {
            return $this
                ->addUsingAlias(CountryTableMap::ID, $orderCouponCountry->getCountryId(), $comparison);
        } elseif ($orderCouponCountry instanceof ObjectCollection) {
            return $this
                ->useOrderCouponCountryQuery()
                ->filterByPrimaryKeys($orderCouponCountry->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByOrderCouponCountry() only accepts arguments of type \Thelia\Model\OrderCouponCountry or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderCouponCountry relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function joinOrderCouponCountry($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderCouponCountry');

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
            $this->addJoinObject($join, 'OrderCouponCountry');
        }

        return $this;
    }

    /**
     * Use the OrderCouponCountry relation OrderCouponCountry object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderCouponCountryQuery A secondary query class using the current class as primary query
     */
    public function useOrderCouponCountryQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderCouponCountry($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderCouponCountry', '\Thelia\Model\OrderCouponCountryQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\CountryArea object
     *
     * @param \Thelia\Model\CountryArea|ObjectCollection $countryArea  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByCountryArea($countryArea, $comparison = null)
    {
        if ($countryArea instanceof \Thelia\Model\CountryArea) {
            return $this
                ->addUsingAlias(CountryTableMap::ID, $countryArea->getCountryId(), $comparison);
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
     * @return ChildCountryQuery The current query, for fluid interface
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
     * Filter the query by a related \Thelia\Model\CountryI18n object
     *
     * @param \Thelia\Model\CountryI18n|ObjectCollection $countryI18n  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByCountryI18n($countryI18n, $comparison = null)
    {
        if ($countryI18n instanceof \Thelia\Model\CountryI18n) {
            return $this
                ->addUsingAlias(CountryTableMap::ID, $countryI18n->getId(), $comparison);
        } elseif ($countryI18n instanceof ObjectCollection) {
            return $this
                ->useCountryI18nQuery()
                ->filterByPrimaryKeys($countryI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCountryI18n() only accepts arguments of type \Thelia\Model\CountryI18n or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CountryI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCountryQuery The current query, for fluid interface
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
     * @see useQuery()
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
     * Filter the query by a related Coupon object
     * using the coupon_country table as cross reference
     *
     * @param Coupon $coupon the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByCoupon($coupon, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useCouponCountryQuery()
            ->filterByCoupon($coupon, $comparison)
            ->endUse();
    }

    /**
     * Filter the query by a related OrderCoupon object
     * using the order_coupon_country table as cross reference
     *
     * @param OrderCoupon $orderCoupon the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByOrderCoupon($orderCoupon, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useOrderCouponCountryQuery()
            ->filterByOrderCoupon($orderCoupon, $comparison)
            ->endUse();
    }

    /**
     * Filter the query by a related Area object
     * using the country_area table as cross reference
     *
     * @param Area $area the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByArea($area, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useCountryAreaQuery()
            ->filterByArea($area, $comparison)
            ->endUse();
    }

    /**
     * Filter the query by a related State object
     * using the country_area table as cross reference
     *
     * @param State $state the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function filterByState($state, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useCountryAreaQuery()
            ->filterByState($state, $comparison)
            ->endUse();
    }

    /**
     * Exclude object from result
     *
     * @param   ChildCountry $country Object to remove from the list of results
     *
     * @return ChildCountryQuery The current query, for fluid interface
     */
    public function prune($country = null)
    {
        if ($country) {
            $this->addUsingAlias(CountryTableMap::ID, $country->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the country table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CountryTableMap::DATABASE_NAME);
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
            CountryTableMap::clearInstancePool();
            CountryTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildCountry or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildCountry object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(CountryTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(CountryTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        CountryTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            CountryTableMap::clearRelatedInstancePool();
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
     * @return     ChildCountryQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(CountryTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildCountryQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(CountryTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildCountryQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(CountryTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildCountryQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(CountryTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildCountryQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(CountryTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildCountryQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(CountryTableMap::CREATED_AT);
    }

    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildCountryQuery The current query, for fluid interface
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
     * @return    ChildCountryQuery The current query, for fluid interface
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
     * @return    ChildCountryI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CountryI18n', '\Thelia\Model\CountryI18nQuery');
    }

} // CountryQuery
