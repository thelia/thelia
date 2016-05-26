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
use Thelia\Model\CustomerVersion as ChildCustomerVersion;
use Thelia\Model\CustomerVersionQuery as ChildCustomerVersionQuery;
use Thelia\Model\Map\CustomerVersionTableMap;

/**
 * Base class that represents a query for the 'customer_version' table.
 *
 *
 *
 * @method     ChildCustomerVersionQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildCustomerVersionQuery orderByTitleId($order = Criteria::ASC) Order by the title_id column
 * @method     ChildCustomerVersionQuery orderByLangId($order = Criteria::ASC) Order by the lang_id column
 * @method     ChildCustomerVersionQuery orderByRef($order = Criteria::ASC) Order by the ref column
 * @method     ChildCustomerVersionQuery orderByFirstname($order = Criteria::ASC) Order by the firstname column
 * @method     ChildCustomerVersionQuery orderByLastname($order = Criteria::ASC) Order by the lastname column
 * @method     ChildCustomerVersionQuery orderByEmail($order = Criteria::ASC) Order by the email column
 * @method     ChildCustomerVersionQuery orderByPassword($order = Criteria::ASC) Order by the password column
 * @method     ChildCustomerVersionQuery orderByAlgo($order = Criteria::ASC) Order by the algo column
 * @method     ChildCustomerVersionQuery orderByReseller($order = Criteria::ASC) Order by the reseller column
 * @method     ChildCustomerVersionQuery orderBySponsor($order = Criteria::ASC) Order by the sponsor column
 * @method     ChildCustomerVersionQuery orderByDiscount($order = Criteria::ASC) Order by the discount column
 * @method     ChildCustomerVersionQuery orderByRememberMeToken($order = Criteria::ASC) Order by the remember_me_token column
 * @method     ChildCustomerVersionQuery orderByRememberMeSerial($order = Criteria::ASC) Order by the remember_me_serial column
 * @method     ChildCustomerVersionQuery orderByEnable($order = Criteria::ASC) Order by the enable column
 * @method     ChildCustomerVersionQuery orderByConfirmationToken($order = Criteria::ASC) Order by the confirmation_token column
 * @method     ChildCustomerVersionQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildCustomerVersionQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 * @method     ChildCustomerVersionQuery orderByVersion($order = Criteria::ASC) Order by the version column
 * @method     ChildCustomerVersionQuery orderByVersionCreatedAt($order = Criteria::ASC) Order by the version_created_at column
 * @method     ChildCustomerVersionQuery orderByVersionCreatedBy($order = Criteria::ASC) Order by the version_created_by column
 * @method     ChildCustomerVersionQuery orderByOrderIds($order = Criteria::ASC) Order by the order_ids column
 * @method     ChildCustomerVersionQuery orderByOrderVersions($order = Criteria::ASC) Order by the order_versions column
 *
 * @method     ChildCustomerVersionQuery groupById() Group by the id column
 * @method     ChildCustomerVersionQuery groupByTitleId() Group by the title_id column
 * @method     ChildCustomerVersionQuery groupByLangId() Group by the lang_id column
 * @method     ChildCustomerVersionQuery groupByRef() Group by the ref column
 * @method     ChildCustomerVersionQuery groupByFirstname() Group by the firstname column
 * @method     ChildCustomerVersionQuery groupByLastname() Group by the lastname column
 * @method     ChildCustomerVersionQuery groupByEmail() Group by the email column
 * @method     ChildCustomerVersionQuery groupByPassword() Group by the password column
 * @method     ChildCustomerVersionQuery groupByAlgo() Group by the algo column
 * @method     ChildCustomerVersionQuery groupByReseller() Group by the reseller column
 * @method     ChildCustomerVersionQuery groupBySponsor() Group by the sponsor column
 * @method     ChildCustomerVersionQuery groupByDiscount() Group by the discount column
 * @method     ChildCustomerVersionQuery groupByRememberMeToken() Group by the remember_me_token column
 * @method     ChildCustomerVersionQuery groupByRememberMeSerial() Group by the remember_me_serial column
 * @method     ChildCustomerVersionQuery groupByEnable() Group by the enable column
 * @method     ChildCustomerVersionQuery groupByConfirmationToken() Group by the confirmation_token column
 * @method     ChildCustomerVersionQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildCustomerVersionQuery groupByUpdatedAt() Group by the updated_at column
 * @method     ChildCustomerVersionQuery groupByVersion() Group by the version column
 * @method     ChildCustomerVersionQuery groupByVersionCreatedAt() Group by the version_created_at column
 * @method     ChildCustomerVersionQuery groupByVersionCreatedBy() Group by the version_created_by column
 * @method     ChildCustomerVersionQuery groupByOrderIds() Group by the order_ids column
 * @method     ChildCustomerVersionQuery groupByOrderVersions() Group by the order_versions column
 *
 * @method     ChildCustomerVersionQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildCustomerVersionQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildCustomerVersionQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildCustomerVersionQuery leftJoinCustomer($relationAlias = null) Adds a LEFT JOIN clause to the query using the Customer relation
 * @method     ChildCustomerVersionQuery rightJoinCustomer($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Customer relation
 * @method     ChildCustomerVersionQuery innerJoinCustomer($relationAlias = null) Adds a INNER JOIN clause to the query using the Customer relation
 *
 * @method     ChildCustomerVersion findOne(ConnectionInterface $con = null) Return the first ChildCustomerVersion matching the query
 * @method     ChildCustomerVersion findOneOrCreate(ConnectionInterface $con = null) Return the first ChildCustomerVersion matching the query, or a new ChildCustomerVersion object populated from the query conditions when no match is found
 *
 * @method     ChildCustomerVersion findOneById(int $id) Return the first ChildCustomerVersion filtered by the id column
 * @method     ChildCustomerVersion findOneByTitleId(int $title_id) Return the first ChildCustomerVersion filtered by the title_id column
 * @method     ChildCustomerVersion findOneByLangId(int $lang_id) Return the first ChildCustomerVersion filtered by the lang_id column
 * @method     ChildCustomerVersion findOneByRef(string $ref) Return the first ChildCustomerVersion filtered by the ref column
 * @method     ChildCustomerVersion findOneByFirstname(string $firstname) Return the first ChildCustomerVersion filtered by the firstname column
 * @method     ChildCustomerVersion findOneByLastname(string $lastname) Return the first ChildCustomerVersion filtered by the lastname column
 * @method     ChildCustomerVersion findOneByEmail(string $email) Return the first ChildCustomerVersion filtered by the email column
 * @method     ChildCustomerVersion findOneByPassword(string $password) Return the first ChildCustomerVersion filtered by the password column
 * @method     ChildCustomerVersion findOneByAlgo(string $algo) Return the first ChildCustomerVersion filtered by the algo column
 * @method     ChildCustomerVersion findOneByReseller(int $reseller) Return the first ChildCustomerVersion filtered by the reseller column
 * @method     ChildCustomerVersion findOneBySponsor(string $sponsor) Return the first ChildCustomerVersion filtered by the sponsor column
 * @method     ChildCustomerVersion findOneByDiscount(string $discount) Return the first ChildCustomerVersion filtered by the discount column
 * @method     ChildCustomerVersion findOneByRememberMeToken(string $remember_me_token) Return the first ChildCustomerVersion filtered by the remember_me_token column
 * @method     ChildCustomerVersion findOneByRememberMeSerial(string $remember_me_serial) Return the first ChildCustomerVersion filtered by the remember_me_serial column
 * @method     ChildCustomerVersion findOneByEnable(int $enable) Return the first ChildCustomerVersion filtered by the enable column
 * @method     ChildCustomerVersion findOneByConfirmationToken(string $confirmation_token) Return the first ChildCustomerVersion filtered by the confirmation_token column
 * @method     ChildCustomerVersion findOneByCreatedAt(string $created_at) Return the first ChildCustomerVersion filtered by the created_at column
 * @method     ChildCustomerVersion findOneByUpdatedAt(string $updated_at) Return the first ChildCustomerVersion filtered by the updated_at column
 * @method     ChildCustomerVersion findOneByVersion(int $version) Return the first ChildCustomerVersion filtered by the version column
 * @method     ChildCustomerVersion findOneByVersionCreatedAt(string $version_created_at) Return the first ChildCustomerVersion filtered by the version_created_at column
 * @method     ChildCustomerVersion findOneByVersionCreatedBy(string $version_created_by) Return the first ChildCustomerVersion filtered by the version_created_by column
 * @method     ChildCustomerVersion findOneByOrderIds(array $order_ids) Return the first ChildCustomerVersion filtered by the order_ids column
 * @method     ChildCustomerVersion findOneByOrderVersions(array $order_versions) Return the first ChildCustomerVersion filtered by the order_versions column
 *
 * @method     array findById(int $id) Return ChildCustomerVersion objects filtered by the id column
 * @method     array findByTitleId(int $title_id) Return ChildCustomerVersion objects filtered by the title_id column
 * @method     array findByLangId(int $lang_id) Return ChildCustomerVersion objects filtered by the lang_id column
 * @method     array findByRef(string $ref) Return ChildCustomerVersion objects filtered by the ref column
 * @method     array findByFirstname(string $firstname) Return ChildCustomerVersion objects filtered by the firstname column
 * @method     array findByLastname(string $lastname) Return ChildCustomerVersion objects filtered by the lastname column
 * @method     array findByEmail(string $email) Return ChildCustomerVersion objects filtered by the email column
 * @method     array findByPassword(string $password) Return ChildCustomerVersion objects filtered by the password column
 * @method     array findByAlgo(string $algo) Return ChildCustomerVersion objects filtered by the algo column
 * @method     array findByReseller(int $reseller) Return ChildCustomerVersion objects filtered by the reseller column
 * @method     array findBySponsor(string $sponsor) Return ChildCustomerVersion objects filtered by the sponsor column
 * @method     array findByDiscount(string $discount) Return ChildCustomerVersion objects filtered by the discount column
 * @method     array findByRememberMeToken(string $remember_me_token) Return ChildCustomerVersion objects filtered by the remember_me_token column
 * @method     array findByRememberMeSerial(string $remember_me_serial) Return ChildCustomerVersion objects filtered by the remember_me_serial column
 * @method     array findByEnable(int $enable) Return ChildCustomerVersion objects filtered by the enable column
 * @method     array findByConfirmationToken(string $confirmation_token) Return ChildCustomerVersion objects filtered by the confirmation_token column
 * @method     array findByCreatedAt(string $created_at) Return ChildCustomerVersion objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildCustomerVersion objects filtered by the updated_at column
 * @method     array findByVersion(int $version) Return ChildCustomerVersion objects filtered by the version column
 * @method     array findByVersionCreatedAt(string $version_created_at) Return ChildCustomerVersion objects filtered by the version_created_at column
 * @method     array findByVersionCreatedBy(string $version_created_by) Return ChildCustomerVersion objects filtered by the version_created_by column
 * @method     array findByOrderIds(array $order_ids) Return ChildCustomerVersion objects filtered by the order_ids column
 * @method     array findByOrderVersions(array $order_versions) Return ChildCustomerVersion objects filtered by the order_versions column
 *
 */
abstract class CustomerVersionQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\CustomerVersionQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\CustomerVersion', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildCustomerVersionQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildCustomerVersionQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\CustomerVersionQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\CustomerVersionQuery();
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
     * $obj = $c->findPk(array(12, 34), $con);
     * </code>
     *
     * @param array[$id, $version] $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildCustomerVersion|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = CustomerVersionTableMap::getInstanceFromPool(serialize(array((string) $key[0], (string) $key[1]))))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(CustomerVersionTableMap::DATABASE_NAME);
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
     * @return   ChildCustomerVersion A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `TITLE_ID`, `LANG_ID`, `REF`, `FIRSTNAME`, `LASTNAME`, `EMAIL`, `PASSWORD`, `ALGO`, `RESELLER`, `SPONSOR`, `DISCOUNT`, `REMEMBER_ME_TOKEN`, `REMEMBER_ME_SERIAL`, `ENABLE`, `CONFIRMATION_TOKEN`, `CREATED_AT`, `UPDATED_AT`, `VERSION`, `VERSION_CREATED_AT`, `VERSION_CREATED_BY`, `ORDER_IDS`, `ORDER_VERSIONS` FROM `customer_version` WHERE `ID` = :p0 AND `VERSION` = :p1';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key[0], PDO::PARAM_INT);
            $stmt->bindValue(':p1', $key[1], PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $obj = new ChildCustomerVersion();
            $obj->hydrate($row);
            CustomerVersionTableMap::addInstanceToPool($obj, serialize(array((string) $key[0], (string) $key[1])));
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
     * @return ChildCustomerVersion|array|mixed the result, formatted by the current formatter
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
     * $objs = $c->findPks(array(array(12, 56), array(832, 123), array(123, 456)), $con);
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
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(CustomerVersionTableMap::ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(CustomerVersionTableMap::VERSION, $key[1], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(CustomerVersionTableMap::ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(CustomerVersionTableMap::VERSION, $key[1], Criteria::EQUAL);
            $cton0->addAnd($cton1);
            $this->addOr($cton0);
        }

        return $this;
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
     * @see       filterByCustomer()
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(CustomerVersionTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(CustomerVersionTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the title_id column
     *
     * Example usage:
     * <code>
     * $query->filterByTitleId(1234); // WHERE title_id = 1234
     * $query->filterByTitleId(array(12, 34)); // WHERE title_id IN (12, 34)
     * $query->filterByTitleId(array('min' => 12)); // WHERE title_id > 12
     * </code>
     *
     * @param     mixed $titleId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByTitleId($titleId = null, $comparison = null)
    {
        if (is_array($titleId)) {
            $useMinMax = false;
            if (isset($titleId['min'])) {
                $this->addUsingAlias(CustomerVersionTableMap::TITLE_ID, $titleId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($titleId['max'])) {
                $this->addUsingAlias(CustomerVersionTableMap::TITLE_ID, $titleId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::TITLE_ID, $titleId, $comparison);
    }

    /**
     * Filter the query on the lang_id column
     *
     * Example usage:
     * <code>
     * $query->filterByLangId(1234); // WHERE lang_id = 1234
     * $query->filterByLangId(array(12, 34)); // WHERE lang_id IN (12, 34)
     * $query->filterByLangId(array('min' => 12)); // WHERE lang_id > 12
     * </code>
     *
     * @param     mixed $langId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByLangId($langId = null, $comparison = null)
    {
        if (is_array($langId)) {
            $useMinMax = false;
            if (isset($langId['min'])) {
                $this->addUsingAlias(CustomerVersionTableMap::LANG_ID, $langId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($langId['max'])) {
                $this->addUsingAlias(CustomerVersionTableMap::LANG_ID, $langId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::LANG_ID, $langId, $comparison);
    }

    /**
     * Filter the query on the ref column
     *
     * Example usage:
     * <code>
     * $query->filterByRef('fooValue');   // WHERE ref = 'fooValue'
     * $query->filterByRef('%fooValue%'); // WHERE ref LIKE '%fooValue%'
     * </code>
     *
     * @param     string $ref The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByRef($ref = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($ref)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $ref)) {
                $ref = str_replace('*', '%', $ref);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::REF, $ref, $comparison);
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
     * @return ChildCustomerVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CustomerVersionTableMap::FIRSTNAME, $firstname, $comparison);
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
     * @return ChildCustomerVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CustomerVersionTableMap::LASTNAME, $lastname, $comparison);
    }

    /**
     * Filter the query on the email column
     *
     * Example usage:
     * <code>
     * $query->filterByEmail('fooValue');   // WHERE email = 'fooValue'
     * $query->filterByEmail('%fooValue%'); // WHERE email LIKE '%fooValue%'
     * </code>
     *
     * @param     string $email The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByEmail($email = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($email)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $email)) {
                $email = str_replace('*', '%', $email);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::EMAIL, $email, $comparison);
    }

    /**
     * Filter the query on the password column
     *
     * Example usage:
     * <code>
     * $query->filterByPassword('fooValue');   // WHERE password = 'fooValue'
     * $query->filterByPassword('%fooValue%'); // WHERE password LIKE '%fooValue%'
     * </code>
     *
     * @param     string $password The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByPassword($password = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($password)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $password)) {
                $password = str_replace('*', '%', $password);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::PASSWORD, $password, $comparison);
    }

    /**
     * Filter the query on the algo column
     *
     * Example usage:
     * <code>
     * $query->filterByAlgo('fooValue');   // WHERE algo = 'fooValue'
     * $query->filterByAlgo('%fooValue%'); // WHERE algo LIKE '%fooValue%'
     * </code>
     *
     * @param     string $algo The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByAlgo($algo = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($algo)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $algo)) {
                $algo = str_replace('*', '%', $algo);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::ALGO, $algo, $comparison);
    }

    /**
     * Filter the query on the reseller column
     *
     * Example usage:
     * <code>
     * $query->filterByReseller(1234); // WHERE reseller = 1234
     * $query->filterByReseller(array(12, 34)); // WHERE reseller IN (12, 34)
     * $query->filterByReseller(array('min' => 12)); // WHERE reseller > 12
     * </code>
     *
     * @param     mixed $reseller The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByReseller($reseller = null, $comparison = null)
    {
        if (is_array($reseller)) {
            $useMinMax = false;
            if (isset($reseller['min'])) {
                $this->addUsingAlias(CustomerVersionTableMap::RESELLER, $reseller['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($reseller['max'])) {
                $this->addUsingAlias(CustomerVersionTableMap::RESELLER, $reseller['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::RESELLER, $reseller, $comparison);
    }

    /**
     * Filter the query on the sponsor column
     *
     * Example usage:
     * <code>
     * $query->filterBySponsor('fooValue');   // WHERE sponsor = 'fooValue'
     * $query->filterBySponsor('%fooValue%'); // WHERE sponsor LIKE '%fooValue%'
     * </code>
     *
     * @param     string $sponsor The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterBySponsor($sponsor = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($sponsor)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $sponsor)) {
                $sponsor = str_replace('*', '%', $sponsor);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::SPONSOR, $sponsor, $comparison);
    }

    /**
     * Filter the query on the discount column
     *
     * Example usage:
     * <code>
     * $query->filterByDiscount(1234); // WHERE discount = 1234
     * $query->filterByDiscount(array(12, 34)); // WHERE discount IN (12, 34)
     * $query->filterByDiscount(array('min' => 12)); // WHERE discount > 12
     * </code>
     *
     * @param     mixed $discount The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByDiscount($discount = null, $comparison = null)
    {
        if (is_array($discount)) {
            $useMinMax = false;
            if (isset($discount['min'])) {
                $this->addUsingAlias(CustomerVersionTableMap::DISCOUNT, $discount['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($discount['max'])) {
                $this->addUsingAlias(CustomerVersionTableMap::DISCOUNT, $discount['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::DISCOUNT, $discount, $comparison);
    }

    /**
     * Filter the query on the remember_me_token column
     *
     * Example usage:
     * <code>
     * $query->filterByRememberMeToken('fooValue');   // WHERE remember_me_token = 'fooValue'
     * $query->filterByRememberMeToken('%fooValue%'); // WHERE remember_me_token LIKE '%fooValue%'
     * </code>
     *
     * @param     string $rememberMeToken The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByRememberMeToken($rememberMeToken = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($rememberMeToken)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $rememberMeToken)) {
                $rememberMeToken = str_replace('*', '%', $rememberMeToken);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::REMEMBER_ME_TOKEN, $rememberMeToken, $comparison);
    }

    /**
     * Filter the query on the remember_me_serial column
     *
     * Example usage:
     * <code>
     * $query->filterByRememberMeSerial('fooValue');   // WHERE remember_me_serial = 'fooValue'
     * $query->filterByRememberMeSerial('%fooValue%'); // WHERE remember_me_serial LIKE '%fooValue%'
     * </code>
     *
     * @param     string $rememberMeSerial The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByRememberMeSerial($rememberMeSerial = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($rememberMeSerial)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $rememberMeSerial)) {
                $rememberMeSerial = str_replace('*', '%', $rememberMeSerial);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::REMEMBER_ME_SERIAL, $rememberMeSerial, $comparison);
    }

    /**
     * Filter the query on the enable column
     *
     * Example usage:
     * <code>
     * $query->filterByEnable(1234); // WHERE enable = 1234
     * $query->filterByEnable(array(12, 34)); // WHERE enable IN (12, 34)
     * $query->filterByEnable(array('min' => 12)); // WHERE enable > 12
     * </code>
     *
     * @param     mixed $enable The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByEnable($enable = null, $comparison = null)
    {
        if (is_array($enable)) {
            $useMinMax = false;
            if (isset($enable['min'])) {
                $this->addUsingAlias(CustomerVersionTableMap::ENABLE, $enable['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($enable['max'])) {
                $this->addUsingAlias(CustomerVersionTableMap::ENABLE, $enable['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::ENABLE, $enable, $comparison);
    }

    /**
     * Filter the query on the confirmation_token column
     *
     * Example usage:
     * <code>
     * $query->filterByConfirmationToken('fooValue');   // WHERE confirmation_token = 'fooValue'
     * $query->filterByConfirmationToken('%fooValue%'); // WHERE confirmation_token LIKE '%fooValue%'
     * </code>
     *
     * @param     string $confirmationToken The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByConfirmationToken($confirmationToken = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($confirmationToken)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $confirmationToken)) {
                $confirmationToken = str_replace('*', '%', $confirmationToken);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::CONFIRMATION_TOKEN, $confirmationToken, $comparison);
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
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(CustomerVersionTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(CustomerVersionTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(CustomerVersionTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(CustomerVersionTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query on the version column
     *
     * Example usage:
     * <code>
     * $query->filterByVersion(1234); // WHERE version = 1234
     * $query->filterByVersion(array(12, 34)); // WHERE version IN (12, 34)
     * $query->filterByVersion(array('min' => 12)); // WHERE version > 12
     * </code>
     *
     * @param     mixed $version The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByVersion($version = null, $comparison = null)
    {
        if (is_array($version)) {
            $useMinMax = false;
            if (isset($version['min'])) {
                $this->addUsingAlias(CustomerVersionTableMap::VERSION, $version['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($version['max'])) {
                $this->addUsingAlias(CustomerVersionTableMap::VERSION, $version['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::VERSION, $version, $comparison);
    }

    /**
     * Filter the query on the version_created_at column
     *
     * Example usage:
     * <code>
     * $query->filterByVersionCreatedAt('2011-03-14'); // WHERE version_created_at = '2011-03-14'
     * $query->filterByVersionCreatedAt('now'); // WHERE version_created_at = '2011-03-14'
     * $query->filterByVersionCreatedAt(array('max' => 'yesterday')); // WHERE version_created_at > '2011-03-13'
     * </code>
     *
     * @param     mixed $versionCreatedAt The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByVersionCreatedAt($versionCreatedAt = null, $comparison = null)
    {
        if (is_array($versionCreatedAt)) {
            $useMinMax = false;
            if (isset($versionCreatedAt['min'])) {
                $this->addUsingAlias(CustomerVersionTableMap::VERSION_CREATED_AT, $versionCreatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($versionCreatedAt['max'])) {
                $this->addUsingAlias(CustomerVersionTableMap::VERSION_CREATED_AT, $versionCreatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::VERSION_CREATED_AT, $versionCreatedAt, $comparison);
    }

    /**
     * Filter the query on the version_created_by column
     *
     * Example usage:
     * <code>
     * $query->filterByVersionCreatedBy('fooValue');   // WHERE version_created_by = 'fooValue'
     * $query->filterByVersionCreatedBy('%fooValue%'); // WHERE version_created_by LIKE '%fooValue%'
     * </code>
     *
     * @param     string $versionCreatedBy The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByVersionCreatedBy($versionCreatedBy = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($versionCreatedBy)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $versionCreatedBy)) {
                $versionCreatedBy = str_replace('*', '%', $versionCreatedBy);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CustomerVersionTableMap::VERSION_CREATED_BY, $versionCreatedBy, $comparison);
    }

    /**
     * Filter the query on the order_ids column
     *
     * @param     array $orderIds The values to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByOrderIds($orderIds = null, $comparison = null)
    {
        $key = $this->getAliasedColName(CustomerVersionTableMap::ORDER_IDS);
        if (null === $comparison || $comparison == Criteria::CONTAINS_ALL) {
            foreach ($orderIds as $value) {
                $value = '%| ' . $value . ' |%';
                if ($this->containsKey($key)) {
                    $this->addAnd($key, $value, Criteria::LIKE);
                } else {
                    $this->add($key, $value, Criteria::LIKE);
                }
            }

            return $this;
        } elseif ($comparison == Criteria::CONTAINS_SOME) {
            foreach ($orderIds as $value) {
                $value = '%| ' . $value . ' |%';
                if ($this->containsKey($key)) {
                    $this->addOr($key, $value, Criteria::LIKE);
                } else {
                    $this->add($key, $value, Criteria::LIKE);
                }
            }

            return $this;
        } elseif ($comparison == Criteria::CONTAINS_NONE) {
            foreach ($orderIds as $value) {
                $value = '%| ' . $value . ' |%';
                if ($this->containsKey($key)) {
                    $this->addAnd($key, $value, Criteria::NOT_LIKE);
                } else {
                    $this->add($key, $value, Criteria::NOT_LIKE);
                }
            }
            $this->addOr($key, null, Criteria::ISNULL);

            return $this;
        }

        return $this->addUsingAlias(CustomerVersionTableMap::ORDER_IDS, $orderIds, $comparison);
    }

    /**
     * Filter the query on the order_ids column
     * @param     mixed $orderIds The value to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::CONTAINS_ALL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByOrderId($orderIds = null, $comparison = null)
    {
        if (null === $comparison || $comparison == Criteria::CONTAINS_ALL) {
            if (is_scalar($orderIds)) {
                $orderIds = '%| ' . $orderIds . ' |%';
                $comparison = Criteria::LIKE;
            }
        } elseif ($comparison == Criteria::CONTAINS_NONE) {
            $orderIds = '%| ' . $orderIds . ' |%';
            $comparison = Criteria::NOT_LIKE;
            $key = $this->getAliasedColName(CustomerVersionTableMap::ORDER_IDS);
            if ($this->containsKey($key)) {
                $this->addAnd($key, $orderIds, $comparison);
            } else {
                $this->addAnd($key, $orderIds, $comparison);
            }
            $this->addOr($key, null, Criteria::ISNULL);

            return $this;
        }

        return $this->addUsingAlias(CustomerVersionTableMap::ORDER_IDS, $orderIds, $comparison);
    }

    /**
     * Filter the query on the order_versions column
     *
     * @param     array $orderVersions The values to use as filter.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByOrderVersions($orderVersions = null, $comparison = null)
    {
        $key = $this->getAliasedColName(CustomerVersionTableMap::ORDER_VERSIONS);
        if (null === $comparison || $comparison == Criteria::CONTAINS_ALL) {
            foreach ($orderVersions as $value) {
                $value = '%| ' . $value . ' |%';
                if ($this->containsKey($key)) {
                    $this->addAnd($key, $value, Criteria::LIKE);
                } else {
                    $this->add($key, $value, Criteria::LIKE);
                }
            }

            return $this;
        } elseif ($comparison == Criteria::CONTAINS_SOME) {
            foreach ($orderVersions as $value) {
                $value = '%| ' . $value . ' |%';
                if ($this->containsKey($key)) {
                    $this->addOr($key, $value, Criteria::LIKE);
                } else {
                    $this->add($key, $value, Criteria::LIKE);
                }
            }

            return $this;
        } elseif ($comparison == Criteria::CONTAINS_NONE) {
            foreach ($orderVersions as $value) {
                $value = '%| ' . $value . ' |%';
                if ($this->containsKey($key)) {
                    $this->addAnd($key, $value, Criteria::NOT_LIKE);
                } else {
                    $this->add($key, $value, Criteria::NOT_LIKE);
                }
            }
            $this->addOr($key, null, Criteria::ISNULL);

            return $this;
        }

        return $this->addUsingAlias(CustomerVersionTableMap::ORDER_VERSIONS, $orderVersions, $comparison);
    }

    /**
     * Filter the query on the order_versions column
     * @param     mixed $orderVersions The value to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::CONTAINS_ALL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByOrderVersion($orderVersions = null, $comparison = null)
    {
        if (null === $comparison || $comparison == Criteria::CONTAINS_ALL) {
            if (is_scalar($orderVersions)) {
                $orderVersions = '%| ' . $orderVersions . ' |%';
                $comparison = Criteria::LIKE;
            }
        } elseif ($comparison == Criteria::CONTAINS_NONE) {
            $orderVersions = '%| ' . $orderVersions . ' |%';
            $comparison = Criteria::NOT_LIKE;
            $key = $this->getAliasedColName(CustomerVersionTableMap::ORDER_VERSIONS);
            if ($this->containsKey($key)) {
                $this->addAnd($key, $orderVersions, $comparison);
            } else {
                $this->addAnd($key, $orderVersions, $comparison);
            }
            $this->addOr($key, null, Criteria::ISNULL);

            return $this;
        }

        return $this->addUsingAlias(CustomerVersionTableMap::ORDER_VERSIONS, $orderVersions, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Customer object
     *
     * @param \Thelia\Model\Customer|ObjectCollection $customer The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function filterByCustomer($customer, $comparison = null)
    {
        if ($customer instanceof \Thelia\Model\Customer) {
            return $this
                ->addUsingAlias(CustomerVersionTableMap::ID, $customer->getId(), $comparison);
        } elseif ($customer instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(CustomerVersionTableMap::ID, $customer->toKeyValue('PrimaryKey', 'Id'), $comparison);
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
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function joinCustomer($relationAlias = null, $joinType = Criteria::INNER_JOIN)
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
    public function useCustomerQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCustomer($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Customer', '\Thelia\Model\CustomerQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildCustomerVersion $customerVersion Object to remove from the list of results
     *
     * @return ChildCustomerVersionQuery The current query, for fluid interface
     */
    public function prune($customerVersion = null)
    {
        if ($customerVersion) {
            $this->addCond('pruneCond0', $this->getAliasedColName(CustomerVersionTableMap::ID), $customerVersion->getId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(CustomerVersionTableMap::VERSION), $customerVersion->getVersion(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

    /**
     * Deletes all rows from the customer_version table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CustomerVersionTableMap::DATABASE_NAME);
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
            CustomerVersionTableMap::clearInstancePool();
            CustomerVersionTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildCustomerVersion or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildCustomerVersion object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(CustomerVersionTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(CustomerVersionTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        CustomerVersionTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            CustomerVersionTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

} // CustomerVersionQuery
