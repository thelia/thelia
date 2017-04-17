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
use Thelia\Model\Lang as ChildLang;
use Thelia\Model\LangQuery as ChildLangQuery;
use Thelia\Model\Map\LangTableMap;

/**
 * Base class that represents a query for the 'lang' table.
 *
 *
 *
 * @method     ChildLangQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildLangQuery orderByTitle($order = Criteria::ASC) Order by the title column
 * @method     ChildLangQuery orderByCode($order = Criteria::ASC) Order by the code column
 * @method     ChildLangQuery orderByLocale($order = Criteria::ASC) Order by the locale column
 * @method     ChildLangQuery orderByUrl($order = Criteria::ASC) Order by the url column
 * @method     ChildLangQuery orderByDateFormat($order = Criteria::ASC) Order by the date_format column
 * @method     ChildLangQuery orderByTimeFormat($order = Criteria::ASC) Order by the time_format column
 * @method     ChildLangQuery orderByDatetimeFormat($order = Criteria::ASC) Order by the datetime_format column
 * @method     ChildLangQuery orderByDecimalSeparator($order = Criteria::ASC) Order by the decimal_separator column
 * @method     ChildLangQuery orderByThousandsSeparator($order = Criteria::ASC) Order by the thousands_separator column
 * @method     ChildLangQuery orderByActive($order = Criteria::ASC) Order by the active column
 * @method     ChildLangQuery orderByVisible($order = Criteria::ASC) Order by the visible column
 * @method     ChildLangQuery orderByDecimals($order = Criteria::ASC) Order by the decimals column
 * @method     ChildLangQuery orderByByDefault($order = Criteria::ASC) Order by the by_default column
 * @method     ChildLangQuery orderByPosition($order = Criteria::ASC) Order by the position column
 * @method     ChildLangQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildLangQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildLangQuery groupById() Group by the id column
 * @method     ChildLangQuery groupByTitle() Group by the title column
 * @method     ChildLangQuery groupByCode() Group by the code column
 * @method     ChildLangQuery groupByLocale() Group by the locale column
 * @method     ChildLangQuery groupByUrl() Group by the url column
 * @method     ChildLangQuery groupByDateFormat() Group by the date_format column
 * @method     ChildLangQuery groupByTimeFormat() Group by the time_format column
 * @method     ChildLangQuery groupByDatetimeFormat() Group by the datetime_format column
 * @method     ChildLangQuery groupByDecimalSeparator() Group by the decimal_separator column
 * @method     ChildLangQuery groupByThousandsSeparator() Group by the thousands_separator column
 * @method     ChildLangQuery groupByActive() Group by the active column
 * @method     ChildLangQuery groupByVisible() Group by the visible column
 * @method     ChildLangQuery groupByDecimals() Group by the decimals column
 * @method     ChildLangQuery groupByByDefault() Group by the by_default column
 * @method     ChildLangQuery groupByPosition() Group by the position column
 * @method     ChildLangQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildLangQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildLangQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildLangQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildLangQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildLangQuery leftJoinCustomer($relationAlias = null) Adds a LEFT JOIN clause to the query using the Customer relation
 * @method     ChildLangQuery rightJoinCustomer($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Customer relation
 * @method     ChildLangQuery innerJoinCustomer($relationAlias = null) Adds a INNER JOIN clause to the query using the Customer relation
 *
 * @method     ChildLangQuery leftJoinOrder($relationAlias = null) Adds a LEFT JOIN clause to the query using the Order relation
 * @method     ChildLangQuery rightJoinOrder($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Order relation
 * @method     ChildLangQuery innerJoinOrder($relationAlias = null) Adds a INNER JOIN clause to the query using the Order relation
 *
 * @method     ChildLang findOne(ConnectionInterface $con = null) Return the first ChildLang matching the query
 * @method     ChildLang findOneOrCreate(ConnectionInterface $con = null) Return the first ChildLang matching the query, or a new ChildLang object populated from the query conditions when no match is found
 *
 * @method     ChildLang findOneById(int $id) Return the first ChildLang filtered by the id column
 * @method     ChildLang findOneByTitle(string $title) Return the first ChildLang filtered by the title column
 * @method     ChildLang findOneByCode(string $code) Return the first ChildLang filtered by the code column
 * @method     ChildLang findOneByLocale(string $locale) Return the first ChildLang filtered by the locale column
 * @method     ChildLang findOneByUrl(string $url) Return the first ChildLang filtered by the url column
 * @method     ChildLang findOneByDateFormat(string $date_format) Return the first ChildLang filtered by the date_format column
 * @method     ChildLang findOneByTimeFormat(string $time_format) Return the first ChildLang filtered by the time_format column
 * @method     ChildLang findOneByDatetimeFormat(string $datetime_format) Return the first ChildLang filtered by the datetime_format column
 * @method     ChildLang findOneByDecimalSeparator(string $decimal_separator) Return the first ChildLang filtered by the decimal_separator column
 * @method     ChildLang findOneByThousandsSeparator(string $thousands_separator) Return the first ChildLang filtered by the thousands_separator column
 * @method     ChildLang findOneByActive(boolean $active) Return the first ChildLang filtered by the active column
 * @method     ChildLang findOneByVisible(int $visible) Return the first ChildLang filtered by the visible column
 * @method     ChildLang findOneByDecimals(string $decimals) Return the first ChildLang filtered by the decimals column
 * @method     ChildLang findOneByByDefault(int $by_default) Return the first ChildLang filtered by the by_default column
 * @method     ChildLang findOneByPosition(int $position) Return the first ChildLang filtered by the position column
 * @method     ChildLang findOneByCreatedAt(string $created_at) Return the first ChildLang filtered by the created_at column
 * @method     ChildLang findOneByUpdatedAt(string $updated_at) Return the first ChildLang filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildLang objects filtered by the id column
 * @method     array findByTitle(string $title) Return ChildLang objects filtered by the title column
 * @method     array findByCode(string $code) Return ChildLang objects filtered by the code column
 * @method     array findByLocale(string $locale) Return ChildLang objects filtered by the locale column
 * @method     array findByUrl(string $url) Return ChildLang objects filtered by the url column
 * @method     array findByDateFormat(string $date_format) Return ChildLang objects filtered by the date_format column
 * @method     array findByTimeFormat(string $time_format) Return ChildLang objects filtered by the time_format column
 * @method     array findByDatetimeFormat(string $datetime_format) Return ChildLang objects filtered by the datetime_format column
 * @method     array findByDecimalSeparator(string $decimal_separator) Return ChildLang objects filtered by the decimal_separator column
 * @method     array findByThousandsSeparator(string $thousands_separator) Return ChildLang objects filtered by the thousands_separator column
 * @method     array findByActive(boolean $active) Return ChildLang objects filtered by the active column
 * @method     array findByVisible(int $visible) Return ChildLang objects filtered by the visible column
 * @method     array findByDecimals(string $decimals) Return ChildLang objects filtered by the decimals column
 * @method     array findByByDefault(int $by_default) Return ChildLang objects filtered by the by_default column
 * @method     array findByPosition(int $position) Return ChildLang objects filtered by the position column
 * @method     array findByCreatedAt(string $created_at) Return ChildLang objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildLang objects filtered by the updated_at column
 *
 */
abstract class LangQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\LangQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Lang', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildLangQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildLangQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\LangQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\LangQuery();
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
     * @return ChildLang|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = LangTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(LangTableMap::DATABASE_NAME);
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
     * @return   ChildLang A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `TITLE`, `CODE`, `LOCALE`, `URL`, `DATE_FORMAT`, `TIME_FORMAT`, `DATETIME_FORMAT`, `DECIMAL_SEPARATOR`, `THOUSANDS_SEPARATOR`, `ACTIVE`, `VISIBLE`, `DECIMALS`, `BY_DEFAULT`, `POSITION`, `CREATED_AT`, `UPDATED_AT` FROM `lang` WHERE `ID` = :p0';
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
            $obj = new ChildLang();
            $obj->hydrate($row);
            LangTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildLang|array|mixed the result, formatted by the current formatter
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
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(LangTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(LangTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(LangTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(LangTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(LangTableMap::ID, $id, $comparison);
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
     * @return ChildLangQuery The current query, for fluid interface
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

        return $this->addUsingAlias(LangTableMap::TITLE, $title, $comparison);
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
     * @return ChildLangQuery The current query, for fluid interface
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

        return $this->addUsingAlias(LangTableMap::CODE, $code, $comparison);
    }

    /**
     * Filter the query on the locale column
     *
     * Example usage:
     * <code>
     * $query->filterByLocale('fooValue');   // WHERE locale = 'fooValue'
     * $query->filterByLocale('%fooValue%'); // WHERE locale LIKE '%fooValue%'
     * </code>
     *
     * @param     string $locale The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByLocale($locale = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($locale)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $locale)) {
                $locale = str_replace('*', '%', $locale);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(LangTableMap::LOCALE, $locale, $comparison);
    }

    /**
     * Filter the query on the url column
     *
     * Example usage:
     * <code>
     * $query->filterByUrl('fooValue');   // WHERE url = 'fooValue'
     * $query->filterByUrl('%fooValue%'); // WHERE url LIKE '%fooValue%'
     * </code>
     *
     * @param     string $url The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByUrl($url = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($url)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $url)) {
                $url = str_replace('*', '%', $url);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(LangTableMap::URL, $url, $comparison);
    }

    /**
     * Filter the query on the date_format column
     *
     * Example usage:
     * <code>
     * $query->filterByDateFormat('fooValue');   // WHERE date_format = 'fooValue'
     * $query->filterByDateFormat('%fooValue%'); // WHERE date_format LIKE '%fooValue%'
     * </code>
     *
     * @param     string $dateFormat The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByDateFormat($dateFormat = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($dateFormat)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $dateFormat)) {
                $dateFormat = str_replace('*', '%', $dateFormat);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(LangTableMap::DATE_FORMAT, $dateFormat, $comparison);
    }

    /**
     * Filter the query on the time_format column
     *
     * Example usage:
     * <code>
     * $query->filterByTimeFormat('fooValue');   // WHERE time_format = 'fooValue'
     * $query->filterByTimeFormat('%fooValue%'); // WHERE time_format LIKE '%fooValue%'
     * </code>
     *
     * @param     string $timeFormat The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByTimeFormat($timeFormat = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($timeFormat)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $timeFormat)) {
                $timeFormat = str_replace('*', '%', $timeFormat);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(LangTableMap::TIME_FORMAT, $timeFormat, $comparison);
    }

    /**
     * Filter the query on the datetime_format column
     *
     * Example usage:
     * <code>
     * $query->filterByDatetimeFormat('fooValue');   // WHERE datetime_format = 'fooValue'
     * $query->filterByDatetimeFormat('%fooValue%'); // WHERE datetime_format LIKE '%fooValue%'
     * </code>
     *
     * @param     string $datetimeFormat The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByDatetimeFormat($datetimeFormat = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($datetimeFormat)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $datetimeFormat)) {
                $datetimeFormat = str_replace('*', '%', $datetimeFormat);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(LangTableMap::DATETIME_FORMAT, $datetimeFormat, $comparison);
    }

    /**
     * Filter the query on the decimal_separator column
     *
     * Example usage:
     * <code>
     * $query->filterByDecimalSeparator('fooValue');   // WHERE decimal_separator = 'fooValue'
     * $query->filterByDecimalSeparator('%fooValue%'); // WHERE decimal_separator LIKE '%fooValue%'
     * </code>
     *
     * @param     string $decimalSeparator The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByDecimalSeparator($decimalSeparator = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($decimalSeparator)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $decimalSeparator)) {
                $decimalSeparator = str_replace('*', '%', $decimalSeparator);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(LangTableMap::DECIMAL_SEPARATOR, $decimalSeparator, $comparison);
    }

    /**
     * Filter the query on the thousands_separator column
     *
     * Example usage:
     * <code>
     * $query->filterByThousandsSeparator('fooValue');   // WHERE thousands_separator = 'fooValue'
     * $query->filterByThousandsSeparator('%fooValue%'); // WHERE thousands_separator LIKE '%fooValue%'
     * </code>
     *
     * @param     string $thousandsSeparator The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByThousandsSeparator($thousandsSeparator = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($thousandsSeparator)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $thousandsSeparator)) {
                $thousandsSeparator = str_replace('*', '%', $thousandsSeparator);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(LangTableMap::THOUSANDS_SEPARATOR, $thousandsSeparator, $comparison);
    }

    /**
     * Filter the query on the active column
     *
     * Example usage:
     * <code>
     * $query->filterByActive(true); // WHERE active = true
     * $query->filterByActive('yes'); // WHERE active = true
     * </code>
     *
     * @param     boolean|string $active The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByActive($active = null, $comparison = null)
    {
        if (is_string($active)) {
            $active = in_array(strtolower($active), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(LangTableMap::ACTIVE, $active, $comparison);
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
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByVisible($visible = null, $comparison = null)
    {
        if (is_array($visible)) {
            $useMinMax = false;
            if (isset($visible['min'])) {
                $this->addUsingAlias(LangTableMap::VISIBLE, $visible['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($visible['max'])) {
                $this->addUsingAlias(LangTableMap::VISIBLE, $visible['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(LangTableMap::VISIBLE, $visible, $comparison);
    }

    /**
     * Filter the query on the decimals column
     *
     * Example usage:
     * <code>
     * $query->filterByDecimals('fooValue');   // WHERE decimals = 'fooValue'
     * $query->filterByDecimals('%fooValue%'); // WHERE decimals LIKE '%fooValue%'
     * </code>
     *
     * @param     string $decimals The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByDecimals($decimals = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($decimals)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $decimals)) {
                $decimals = str_replace('*', '%', $decimals);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(LangTableMap::DECIMALS, $decimals, $comparison);
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
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByByDefault($byDefault = null, $comparison = null)
    {
        if (is_array($byDefault)) {
            $useMinMax = false;
            if (isset($byDefault['min'])) {
                $this->addUsingAlias(LangTableMap::BY_DEFAULT, $byDefault['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($byDefault['max'])) {
                $this->addUsingAlias(LangTableMap::BY_DEFAULT, $byDefault['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(LangTableMap::BY_DEFAULT, $byDefault, $comparison);
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
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        if (is_array($position)) {
            $useMinMax = false;
            if (isset($position['min'])) {
                $this->addUsingAlias(LangTableMap::POSITION, $position['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($position['max'])) {
                $this->addUsingAlias(LangTableMap::POSITION, $position['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(LangTableMap::POSITION, $position, $comparison);
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
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(LangTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(LangTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(LangTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(LangTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(LangTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(LangTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Customer object
     *
     * @param \Thelia\Model\Customer|ObjectCollection $customer  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByCustomer($customer, $comparison = null)
    {
        if ($customer instanceof \Thelia\Model\Customer) {
            return $this
                ->addUsingAlias(LangTableMap::ID, $customer->getLangId(), $comparison);
        } elseif ($customer instanceof ObjectCollection) {
            return $this
                ->useCustomerQuery()
                ->filterByPrimaryKeys($customer->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCustomer() only accepts arguments of type \Thelia\Model\Customer or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Customer relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildLangQuery The current query, for fluid interface
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
     * @see useQuery()
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
     * Filter the query by a related \Thelia\Model\Order object
     *
     * @param \Thelia\Model\Order|ObjectCollection $order  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function filterByOrder($order, $comparison = null)
    {
        if ($order instanceof \Thelia\Model\Order) {
            return $this
                ->addUsingAlias(LangTableMap::ID, $order->getLangId(), $comparison);
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
     * @return ChildLangQuery The current query, for fluid interface
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
     * Exclude object from result
     *
     * @param   ChildLang $lang Object to remove from the list of results
     *
     * @return ChildLangQuery The current query, for fluid interface
     */
    public function prune($lang = null)
    {
        if ($lang) {
            $this->addUsingAlias(LangTableMap::ID, $lang->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the lang table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(LangTableMap::DATABASE_NAME);
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
            LangTableMap::clearInstancePool();
            LangTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildLang or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildLang object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(LangTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(LangTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        LangTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            LangTableMap::clearRelatedInstancePool();
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
     * @return     ChildLangQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(LangTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildLangQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(LangTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildLangQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(LangTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildLangQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(LangTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildLangQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(LangTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildLangQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(LangTableMap::CREATED_AT);
    }

} // LangQuery
