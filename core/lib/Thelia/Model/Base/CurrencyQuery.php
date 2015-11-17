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
use Thelia\Model\Currency as ChildCurrency;
use Thelia\Model\CurrencyI18nQuery as ChildCurrencyI18nQuery;
use Thelia\Model\CurrencyQuery as ChildCurrencyQuery;
use Thelia\Model\Map\CurrencyTableMap;

/**
 * Base class that represents a query for the 'currency' table.
 *
 *
 *
 * @method     ChildCurrencyQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildCurrencyQuery orderByCode($order = Criteria::ASC) Order by the code column
 * @method     ChildCurrencyQuery orderBySymbol($order = Criteria::ASC) Order by the symbol column
 * @method     ChildCurrencyQuery orderByFormat($order = Criteria::ASC) Order by the format column
 * @method     ChildCurrencyQuery orderByRate($order = Criteria::ASC) Order by the rate column
 * @method     ChildCurrencyQuery orderByVisible($order = Criteria::ASC) Order by the visible column
 * @method     ChildCurrencyQuery orderByPosition($order = Criteria::ASC) Order by the position column
 * @method     ChildCurrencyQuery orderByByDefault($order = Criteria::ASC) Order by the by_default column
 * @method     ChildCurrencyQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildCurrencyQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildCurrencyQuery groupById() Group by the id column
 * @method     ChildCurrencyQuery groupByCode() Group by the code column
 * @method     ChildCurrencyQuery groupBySymbol() Group by the symbol column
 * @method     ChildCurrencyQuery groupByFormat() Group by the format column
 * @method     ChildCurrencyQuery groupByRate() Group by the rate column
 * @method     ChildCurrencyQuery groupByVisible() Group by the visible column
 * @method     ChildCurrencyQuery groupByPosition() Group by the position column
 * @method     ChildCurrencyQuery groupByByDefault() Group by the by_default column
 * @method     ChildCurrencyQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildCurrencyQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildCurrencyQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildCurrencyQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildCurrencyQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildCurrencyQuery leftJoinOrder($relationAlias = null) Adds a LEFT JOIN clause to the query using the Order relation
 * @method     ChildCurrencyQuery rightJoinOrder($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Order relation
 * @method     ChildCurrencyQuery innerJoinOrder($relationAlias = null) Adds a INNER JOIN clause to the query using the Order relation
 *
 * @method     ChildCurrencyQuery leftJoinCart($relationAlias = null) Adds a LEFT JOIN clause to the query using the Cart relation
 * @method     ChildCurrencyQuery rightJoinCart($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Cart relation
 * @method     ChildCurrencyQuery innerJoinCart($relationAlias = null) Adds a INNER JOIN clause to the query using the Cart relation
 *
 * @method     ChildCurrencyQuery leftJoinProductPrice($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProductPrice relation
 * @method     ChildCurrencyQuery rightJoinProductPrice($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProductPrice relation
 * @method     ChildCurrencyQuery innerJoinProductPrice($relationAlias = null) Adds a INNER JOIN clause to the query using the ProductPrice relation
 *
 * @method     ChildCurrencyQuery leftJoinSaleOffsetCurrency($relationAlias = null) Adds a LEFT JOIN clause to the query using the SaleOffsetCurrency relation
 * @method     ChildCurrencyQuery rightJoinSaleOffsetCurrency($relationAlias = null) Adds a RIGHT JOIN clause to the query using the SaleOffsetCurrency relation
 * @method     ChildCurrencyQuery innerJoinSaleOffsetCurrency($relationAlias = null) Adds a INNER JOIN clause to the query using the SaleOffsetCurrency relation
 *
 * @method     ChildCurrencyQuery leftJoinCurrencyI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the CurrencyI18n relation
 * @method     ChildCurrencyQuery rightJoinCurrencyI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CurrencyI18n relation
 * @method     ChildCurrencyQuery innerJoinCurrencyI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the CurrencyI18n relation
 *
 * @method     ChildCurrency findOne(ConnectionInterface $con = null) Return the first ChildCurrency matching the query
 * @method     ChildCurrency findOneOrCreate(ConnectionInterface $con = null) Return the first ChildCurrency matching the query, or a new ChildCurrency object populated from the query conditions when no match is found
 *
 * @method     ChildCurrency findOneById(int $id) Return the first ChildCurrency filtered by the id column
 * @method     ChildCurrency findOneByCode(string $code) Return the first ChildCurrency filtered by the code column
 * @method     ChildCurrency findOneBySymbol(string $symbol) Return the first ChildCurrency filtered by the symbol column
 * @method     ChildCurrency findOneByFormat(string $format) Return the first ChildCurrency filtered by the format column
 * @method     ChildCurrency findOneByRate(double $rate) Return the first ChildCurrency filtered by the rate column
 * @method     ChildCurrency findOneByVisible(int $visible) Return the first ChildCurrency filtered by the visible column
 * @method     ChildCurrency findOneByPosition(int $position) Return the first ChildCurrency filtered by the position column
 * @method     ChildCurrency findOneByByDefault(int $by_default) Return the first ChildCurrency filtered by the by_default column
 * @method     ChildCurrency findOneByCreatedAt(string $created_at) Return the first ChildCurrency filtered by the created_at column
 * @method     ChildCurrency findOneByUpdatedAt(string $updated_at) Return the first ChildCurrency filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildCurrency objects filtered by the id column
 * @method     array findByCode(string $code) Return ChildCurrency objects filtered by the code column
 * @method     array findBySymbol(string $symbol) Return ChildCurrency objects filtered by the symbol column
 * @method     array findByFormat(string $format) Return ChildCurrency objects filtered by the format column
 * @method     array findByRate(double $rate) Return ChildCurrency objects filtered by the rate column
 * @method     array findByVisible(int $visible) Return ChildCurrency objects filtered by the visible column
 * @method     array findByPosition(int $position) Return ChildCurrency objects filtered by the position column
 * @method     array findByByDefault(int $by_default) Return ChildCurrency objects filtered by the by_default column
 * @method     array findByCreatedAt(string $created_at) Return ChildCurrency objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildCurrency objects filtered by the updated_at column
 *
 */
abstract class CurrencyQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\CurrencyQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Currency', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildCurrencyQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildCurrencyQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\CurrencyQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\CurrencyQuery();
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
     * @return ChildCurrency|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = CurrencyTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(CurrencyTableMap::DATABASE_NAME);
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
     * @return   ChildCurrency A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `CODE`, `SYMBOL`, `FORMAT`, `RATE`, `VISIBLE`, `POSITION`, `BY_DEFAULT`, `CREATED_AT`, `UPDATED_AT` FROM `currency` WHERE `ID` = :p0';
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
            $obj = new ChildCurrency();
            $obj->hydrate($row);
            CurrencyTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildCurrency|array|mixed the result, formatted by the current formatter
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
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(CurrencyTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(CurrencyTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(CurrencyTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(CurrencyTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CurrencyTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the code column
     *
     * Example usage:
     * <code>
     * $query->filterByCode('fooValue');   // WHERE code = 'fooValue'
     * $query->filterByCode('%fooValue%'); // WHERE code LIKE '%fooValue%'
     * </code>
     *
     * @param     string $code The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterByCode($code = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($code)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $code)) {
                $code = str_replace('*', '%', $code);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CurrencyTableMap::CODE, $code, $comparison);
    }

    /**
     * Filter the query on the symbol column
     *
     * Example usage:
     * <code>
     * $query->filterBySymbol('fooValue');   // WHERE symbol = 'fooValue'
     * $query->filterBySymbol('%fooValue%'); // WHERE symbol LIKE '%fooValue%'
     * </code>
     *
     * @param     string $symbol The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterBySymbol($symbol = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($symbol)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $symbol)) {
                $symbol = str_replace('*', '%', $symbol);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CurrencyTableMap::SYMBOL, $symbol, $comparison);
    }

    /**
     * Filter the query on the format column
     *
     * Example usage:
     * <code>
     * $query->filterByFormat('fooValue');   // WHERE format = 'fooValue'
     * $query->filterByFormat('%fooValue%'); // WHERE format LIKE '%fooValue%'
     * </code>
     *
     * @param     string $format The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterByFormat($format = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($format)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $format)) {
                $format = str_replace('*', '%', $format);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CurrencyTableMap::FORMAT, $format, $comparison);
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
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterByRate($rate = null, $comparison = null)
    {
        if (is_array($rate)) {
            $useMinMax = false;
            if (isset($rate['min'])) {
                $this->addUsingAlias(CurrencyTableMap::RATE, $rate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($rate['max'])) {
                $this->addUsingAlias(CurrencyTableMap::RATE, $rate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CurrencyTableMap::RATE, $rate, $comparison);
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
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterByVisible($visible = null, $comparison = null)
    {
        if (is_array($visible)) {
            $useMinMax = false;
            if (isset($visible['min'])) {
                $this->addUsingAlias(CurrencyTableMap::VISIBLE, $visible['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($visible['max'])) {
                $this->addUsingAlias(CurrencyTableMap::VISIBLE, $visible['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CurrencyTableMap::VISIBLE, $visible, $comparison);
    }

    /**
     * Filter the query on the position column
     *
     * Example usage:
     * <code>
     * $query->filterByPosition(1234); // WHERE position = 1234
     * $query->filterByPosition(array(12, 34)); // WHERE position IN (12, 34)
     * $query->filterByPosition(array('min' => 12)); // WHERE position > 12
     * </code>
     *
     * @param     mixed $position The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        if (is_array($position)) {
            $useMinMax = false;
            if (isset($position['min'])) {
                $this->addUsingAlias(CurrencyTableMap::POSITION, $position['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($position['max'])) {
                $this->addUsingAlias(CurrencyTableMap::POSITION, $position['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CurrencyTableMap::POSITION, $position, $comparison);
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
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterByByDefault($byDefault = null, $comparison = null)
    {
        if (is_array($byDefault)) {
            $useMinMax = false;
            if (isset($byDefault['min'])) {
                $this->addUsingAlias(CurrencyTableMap::BY_DEFAULT, $byDefault['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($byDefault['max'])) {
                $this->addUsingAlias(CurrencyTableMap::BY_DEFAULT, $byDefault['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CurrencyTableMap::BY_DEFAULT, $byDefault, $comparison);
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
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(CurrencyTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(CurrencyTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CurrencyTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(CurrencyTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(CurrencyTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CurrencyTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Order object
     *
     * @param \Thelia\Model\Order|ObjectCollection $order  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterByOrder($order, $comparison = null)
    {
        if ($order instanceof \Thelia\Model\Order) {
            return $this
                ->addUsingAlias(CurrencyTableMap::ID, $order->getCurrencyId(), $comparison);
        } elseif ($order instanceof ObjectCollection) {
            return $this
                ->useOrderQuery()
                ->filterByPrimaryKeys($order->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByOrder() only accepts arguments of type \Thelia\Model\Order or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Order relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function joinOrder($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Order');

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
            $this->addJoinObject($join, 'Order');
        }

        return $this;
    }

    /**
     * Use the Order relation Order object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderQuery A secondary query class using the current class as primary query
     */
    public function useOrderQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrder($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Order', '\Thelia\Model\OrderQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Cart object
     *
     * @param \Thelia\Model\Cart|ObjectCollection $cart  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterByCart($cart, $comparison = null)
    {
        if ($cart instanceof \Thelia\Model\Cart) {
            return $this
                ->addUsingAlias(CurrencyTableMap::ID, $cart->getCurrencyId(), $comparison);
        } elseif ($cart instanceof ObjectCollection) {
            return $this
                ->useCartQuery()
                ->filterByPrimaryKeys($cart->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCart() only accepts arguments of type \Thelia\Model\Cart or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Cart relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function joinCart($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Cart');

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
            $this->addJoinObject($join, 'Cart');
        }

        return $this;
    }

    /**
     * Use the Cart relation Cart object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CartQuery A secondary query class using the current class as primary query
     */
    public function useCartQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinCart($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Cart', '\Thelia\Model\CartQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ProductPrice object
     *
     * @param \Thelia\Model\ProductPrice|ObjectCollection $productPrice  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterByProductPrice($productPrice, $comparison = null)
    {
        if ($productPrice instanceof \Thelia\Model\ProductPrice) {
            return $this
                ->addUsingAlias(CurrencyTableMap::ID, $productPrice->getCurrencyId(), $comparison);
        } elseif ($productPrice instanceof ObjectCollection) {
            return $this
                ->useProductPriceQuery()
                ->filterByPrimaryKeys($productPrice->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByProductPrice() only accepts arguments of type \Thelia\Model\ProductPrice or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ProductPrice relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function joinProductPrice($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ProductPrice');

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
            $this->addJoinObject($join, 'ProductPrice');
        }

        return $this;
    }

    /**
     * Use the ProductPrice relation ProductPrice object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ProductPriceQuery A secondary query class using the current class as primary query
     */
    public function useProductPriceQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinProductPrice($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ProductPrice', '\Thelia\Model\ProductPriceQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\SaleOffsetCurrency object
     *
     * @param \Thelia\Model\SaleOffsetCurrency|ObjectCollection $saleOffsetCurrency  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterBySaleOffsetCurrency($saleOffsetCurrency, $comparison = null)
    {
        if ($saleOffsetCurrency instanceof \Thelia\Model\SaleOffsetCurrency) {
            return $this
                ->addUsingAlias(CurrencyTableMap::ID, $saleOffsetCurrency->getCurrencyId(), $comparison);
        } elseif ($saleOffsetCurrency instanceof ObjectCollection) {
            return $this
                ->useSaleOffsetCurrencyQuery()
                ->filterByPrimaryKeys($saleOffsetCurrency->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterBySaleOffsetCurrency() only accepts arguments of type \Thelia\Model\SaleOffsetCurrency or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the SaleOffsetCurrency relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function joinSaleOffsetCurrency($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('SaleOffsetCurrency');

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
            $this->addJoinObject($join, 'SaleOffsetCurrency');
        }

        return $this;
    }

    /**
     * Use the SaleOffsetCurrency relation SaleOffsetCurrency object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\SaleOffsetCurrencyQuery A secondary query class using the current class as primary query
     */
    public function useSaleOffsetCurrencyQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinSaleOffsetCurrency($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'SaleOffsetCurrency', '\Thelia\Model\SaleOffsetCurrencyQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\CurrencyI18n object
     *
     * @param \Thelia\Model\CurrencyI18n|ObjectCollection $currencyI18n  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function filterByCurrencyI18n($currencyI18n, $comparison = null)
    {
        if ($currencyI18n instanceof \Thelia\Model\CurrencyI18n) {
            return $this
                ->addUsingAlias(CurrencyTableMap::ID, $currencyI18n->getId(), $comparison);
        } elseif ($currencyI18n instanceof ObjectCollection) {
            return $this
                ->useCurrencyI18nQuery()
                ->filterByPrimaryKeys($currencyI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCurrencyI18n() only accepts arguments of type \Thelia\Model\CurrencyI18n or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CurrencyI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function joinCurrencyI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CurrencyI18n');

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
            $this->addJoinObject($join, 'CurrencyI18n');
        }

        return $this;
    }

    /**
     * Use the CurrencyI18n relation CurrencyI18n object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CurrencyI18nQuery A secondary query class using the current class as primary query
     */
    public function useCurrencyI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinCurrencyI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CurrencyI18n', '\Thelia\Model\CurrencyI18nQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildCurrency $currency Object to remove from the list of results
     *
     * @return ChildCurrencyQuery The current query, for fluid interface
     */
    public function prune($currency = null)
    {
        if ($currency) {
            $this->addUsingAlias(CurrencyTableMap::ID, $currency->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the currency table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CurrencyTableMap::DATABASE_NAME);
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
            CurrencyTableMap::clearInstancePool();
            CurrencyTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildCurrency or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildCurrency object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(CurrencyTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(CurrencyTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        CurrencyTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            CurrencyTableMap::clearRelatedInstancePool();
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
     * @return     ChildCurrencyQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(CurrencyTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildCurrencyQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(CurrencyTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildCurrencyQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(CurrencyTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildCurrencyQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(CurrencyTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildCurrencyQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(CurrencyTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildCurrencyQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(CurrencyTableMap::CREATED_AT);
    }

    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildCurrencyQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'CurrencyI18n';

        return $this
            ->joinCurrencyI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildCurrencyQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('CurrencyI18n');
        $this->with['CurrencyI18n']->setIsWithOneToMany(false);

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
     * @return    ChildCurrencyI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CurrencyI18n', '\Thelia\Model\CurrencyI18nQuery');
    }

} // CurrencyQuery
