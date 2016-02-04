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
use Thelia\Model\Admin as ChildAdmin;
use Thelia\Model\AdminQuery as ChildAdminQuery;
use Thelia\Model\Map\AdminTableMap;

/**
 * Base class that represents a query for the 'admin' table.
 *
 *
 *
 * @method     ChildAdminQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildAdminQuery orderByProfileId($order = Criteria::ASC) Order by the profile_id column
 * @method     ChildAdminQuery orderByFirstname($order = Criteria::ASC) Order by the firstname column
 * @method     ChildAdminQuery orderByLastname($order = Criteria::ASC) Order by the lastname column
 * @method     ChildAdminQuery orderByLogin($order = Criteria::ASC) Order by the login column
 * @method     ChildAdminQuery orderByPassword($order = Criteria::ASC) Order by the password column
 * @method     ChildAdminQuery orderByLocale($order = Criteria::ASC) Order by the locale column
 * @method     ChildAdminQuery orderByAlgo($order = Criteria::ASC) Order by the algo column
 * @method     ChildAdminQuery orderBySalt($order = Criteria::ASC) Order by the salt column
 * @method     ChildAdminQuery orderByRememberMeToken($order = Criteria::ASC) Order by the remember_me_token column
 * @method     ChildAdminQuery orderByRememberMeSerial($order = Criteria::ASC) Order by the remember_me_serial column
 * @method     ChildAdminQuery orderByEmail($order = Criteria::ASC) Order by the email column
 * @method     ChildAdminQuery orderByPasswordRenewToken($order = Criteria::ASC) Order by the password_renew_token column
 * @method     ChildAdminQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildAdminQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildAdminQuery groupById() Group by the id column
 * @method     ChildAdminQuery groupByProfileId() Group by the profile_id column
 * @method     ChildAdminQuery groupByFirstname() Group by the firstname column
 * @method     ChildAdminQuery groupByLastname() Group by the lastname column
 * @method     ChildAdminQuery groupByLogin() Group by the login column
 * @method     ChildAdminQuery groupByPassword() Group by the password column
 * @method     ChildAdminQuery groupByLocale() Group by the locale column
 * @method     ChildAdminQuery groupByAlgo() Group by the algo column
 * @method     ChildAdminQuery groupBySalt() Group by the salt column
 * @method     ChildAdminQuery groupByRememberMeToken() Group by the remember_me_token column
 * @method     ChildAdminQuery groupByRememberMeSerial() Group by the remember_me_serial column
 * @method     ChildAdminQuery groupByEmail() Group by the email column
 * @method     ChildAdminQuery groupByPasswordRenewToken() Group by the password_renew_token column
 * @method     ChildAdminQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildAdminQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildAdminQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildAdminQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildAdminQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildAdminQuery leftJoinProfile($relationAlias = null) Adds a LEFT JOIN clause to the query using the Profile relation
 * @method     ChildAdminQuery rightJoinProfile($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Profile relation
 * @method     ChildAdminQuery innerJoinProfile($relationAlias = null) Adds a INNER JOIN clause to the query using the Profile relation
 *
 * @method     ChildAdmin findOne(ConnectionInterface $con = null) Return the first ChildAdmin matching the query
 * @method     ChildAdmin findOneOrCreate(ConnectionInterface $con = null) Return the first ChildAdmin matching the query, or a new ChildAdmin object populated from the query conditions when no match is found
 *
 * @method     ChildAdmin findOneById(int $id) Return the first ChildAdmin filtered by the id column
 * @method     ChildAdmin findOneByProfileId(int $profile_id) Return the first ChildAdmin filtered by the profile_id column
 * @method     ChildAdmin findOneByFirstname(string $firstname) Return the first ChildAdmin filtered by the firstname column
 * @method     ChildAdmin findOneByLastname(string $lastname) Return the first ChildAdmin filtered by the lastname column
 * @method     ChildAdmin findOneByLogin(string $login) Return the first ChildAdmin filtered by the login column
 * @method     ChildAdmin findOneByPassword(string $password) Return the first ChildAdmin filtered by the password column
 * @method     ChildAdmin findOneByLocale(string $locale) Return the first ChildAdmin filtered by the locale column
 * @method     ChildAdmin findOneByAlgo(string $algo) Return the first ChildAdmin filtered by the algo column
 * @method     ChildAdmin findOneBySalt(string $salt) Return the first ChildAdmin filtered by the salt column
 * @method     ChildAdmin findOneByRememberMeToken(string $remember_me_token) Return the first ChildAdmin filtered by the remember_me_token column
 * @method     ChildAdmin findOneByRememberMeSerial(string $remember_me_serial) Return the first ChildAdmin filtered by the remember_me_serial column
 * @method     ChildAdmin findOneByEmail(string $email) Return the first ChildAdmin filtered by the email column
 * @method     ChildAdmin findOneByPasswordRenewToken(string $password_renew_token) Return the first ChildAdmin filtered by the password_renew_token column
 * @method     ChildAdmin findOneByCreatedAt(string $created_at) Return the first ChildAdmin filtered by the created_at column
 * @method     ChildAdmin findOneByUpdatedAt(string $updated_at) Return the first ChildAdmin filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildAdmin objects filtered by the id column
 * @method     array findByProfileId(int $profile_id) Return ChildAdmin objects filtered by the profile_id column
 * @method     array findByFirstname(string $firstname) Return ChildAdmin objects filtered by the firstname column
 * @method     array findByLastname(string $lastname) Return ChildAdmin objects filtered by the lastname column
 * @method     array findByLogin(string $login) Return ChildAdmin objects filtered by the login column
 * @method     array findByPassword(string $password) Return ChildAdmin objects filtered by the password column
 * @method     array findByLocale(string $locale) Return ChildAdmin objects filtered by the locale column
 * @method     array findByAlgo(string $algo) Return ChildAdmin objects filtered by the algo column
 * @method     array findBySalt(string $salt) Return ChildAdmin objects filtered by the salt column
 * @method     array findByRememberMeToken(string $remember_me_token) Return ChildAdmin objects filtered by the remember_me_token column
 * @method     array findByRememberMeSerial(string $remember_me_serial) Return ChildAdmin objects filtered by the remember_me_serial column
 * @method     array findByEmail(string $email) Return ChildAdmin objects filtered by the email column
 * @method     array findByPasswordRenewToken(string $password_renew_token) Return ChildAdmin objects filtered by the password_renew_token column
 * @method     array findByCreatedAt(string $created_at) Return ChildAdmin objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildAdmin objects filtered by the updated_at column
 *
 */
abstract class AdminQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\AdminQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Admin', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildAdminQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildAdminQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\AdminQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\AdminQuery();
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
     * @return ChildAdmin|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = AdminTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(AdminTableMap::DATABASE_NAME);
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
     * @return   ChildAdmin A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `PROFILE_ID`, `FIRSTNAME`, `LASTNAME`, `LOGIN`, `PASSWORD`, `LOCALE`, `ALGO`, `SALT`, `REMEMBER_ME_TOKEN`, `REMEMBER_ME_SERIAL`, `EMAIL`, `PASSWORD_RENEW_TOKEN`, `CREATED_AT`, `UPDATED_AT` FROM `admin` WHERE `ID` = :p0';
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
            $obj = new ChildAdmin();
            $obj->hydrate($row);
            AdminTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildAdmin|array|mixed the result, formatted by the current formatter
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
     * @return ChildAdminQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(AdminTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildAdminQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(AdminTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildAdminQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(AdminTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(AdminTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AdminTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the profile_id column
     *
     * Example usage:
     * <code>
     * $query->filterByProfileId(1234); // WHERE profile_id = 1234
     * $query->filterByProfileId(array(12, 34)); // WHERE profile_id IN (12, 34)
     * $query->filterByProfileId(array('min' => 12)); // WHERE profile_id > 12
     * </code>
     *
     * @see       filterByProfile()
     *
     * @param     mixed $profileId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAdminQuery The current query, for fluid interface
     */
    public function filterByProfileId($profileId = null, $comparison = null)
    {
        if (is_array($profileId)) {
            $useMinMax = false;
            if (isset($profileId['min'])) {
                $this->addUsingAlias(AdminTableMap::PROFILE_ID, $profileId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($profileId['max'])) {
                $this->addUsingAlias(AdminTableMap::PROFILE_ID, $profileId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AdminTableMap::PROFILE_ID, $profileId, $comparison);
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
     * @return ChildAdminQuery The current query, for fluid interface
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

        return $this->addUsingAlias(AdminTableMap::FIRSTNAME, $firstname, $comparison);
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
     * @return ChildAdminQuery The current query, for fluid interface
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

        return $this->addUsingAlias(AdminTableMap::LASTNAME, $lastname, $comparison);
    }

    /**
     * Filter the query on the login column
     *
     * Example usage:
     * <code>
     * $query->filterByLogin('fooValue');   // WHERE login = 'fooValue'
     * $query->filterByLogin('%fooValue%'); // WHERE login LIKE '%fooValue%'
     * </code>
     *
     * @param     string $login The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAdminQuery The current query, for fluid interface
     */
    public function filterByLogin($login = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($login)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $login)) {
                $login = str_replace('*', '%', $login);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(AdminTableMap::LOGIN, $login, $comparison);
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
     * @return ChildAdminQuery The current query, for fluid interface
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

        return $this->addUsingAlias(AdminTableMap::PASSWORD, $password, $comparison);
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
     * @return ChildAdminQuery The current query, for fluid interface
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

        return $this->addUsingAlias(AdminTableMap::LOCALE, $locale, $comparison);
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
     * @return ChildAdminQuery The current query, for fluid interface
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

        return $this->addUsingAlias(AdminTableMap::ALGO, $algo, $comparison);
    }

    /**
     * Filter the query on the salt column
     *
     * Example usage:
     * <code>
     * $query->filterBySalt('fooValue');   // WHERE salt = 'fooValue'
     * $query->filterBySalt('%fooValue%'); // WHERE salt LIKE '%fooValue%'
     * </code>
     *
     * @param     string $salt The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAdminQuery The current query, for fluid interface
     */
    public function filterBySalt($salt = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($salt)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $salt)) {
                $salt = str_replace('*', '%', $salt);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(AdminTableMap::SALT, $salt, $comparison);
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
     * @return ChildAdminQuery The current query, for fluid interface
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

        return $this->addUsingAlias(AdminTableMap::REMEMBER_ME_TOKEN, $rememberMeToken, $comparison);
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
     * @return ChildAdminQuery The current query, for fluid interface
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

        return $this->addUsingAlias(AdminTableMap::REMEMBER_ME_SERIAL, $rememberMeSerial, $comparison);
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
     * @return ChildAdminQuery The current query, for fluid interface
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

        return $this->addUsingAlias(AdminTableMap::EMAIL, $email, $comparison);
    }

    /**
     * Filter the query on the password_renew_token column
     *
     * Example usage:
     * <code>
     * $query->filterByPasswordRenewToken('fooValue');   // WHERE password_renew_token = 'fooValue'
     * $query->filterByPasswordRenewToken('%fooValue%'); // WHERE password_renew_token LIKE '%fooValue%'
     * </code>
     *
     * @param     string $passwordRenewToken The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAdminQuery The current query, for fluid interface
     */
    public function filterByPasswordRenewToken($passwordRenewToken = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($passwordRenewToken)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $passwordRenewToken)) {
                $passwordRenewToken = str_replace('*', '%', $passwordRenewToken);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(AdminTableMap::PASSWORD_RENEW_TOKEN, $passwordRenewToken, $comparison);
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
     * @return ChildAdminQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(AdminTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(AdminTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AdminTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildAdminQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(AdminTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(AdminTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AdminTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Profile object
     *
     * @param \Thelia\Model\Profile|ObjectCollection $profile The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAdminQuery The current query, for fluid interface
     */
    public function filterByProfile($profile, $comparison = null)
    {
        if ($profile instanceof \Thelia\Model\Profile) {
            return $this
                ->addUsingAlias(AdminTableMap::PROFILE_ID, $profile->getId(), $comparison);
        } elseif ($profile instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(AdminTableMap::PROFILE_ID, $profile->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByProfile() only accepts arguments of type \Thelia\Model\Profile or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Profile relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildAdminQuery The current query, for fluid interface
     */
    public function joinProfile($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Profile');

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
            $this->addJoinObject($join, 'Profile');
        }

        return $this;
    }

    /**
     * Use the Profile relation Profile object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ProfileQuery A secondary query class using the current class as primary query
     */
    public function useProfileQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinProfile($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Profile', '\Thelia\Model\ProfileQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildAdmin $admin Object to remove from the list of results
     *
     * @return ChildAdminQuery The current query, for fluid interface
     */
    public function prune($admin = null)
    {
        if ($admin) {
            $this->addUsingAlias(AdminTableMap::ID, $admin->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the admin table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(AdminTableMap::DATABASE_NAME);
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
            AdminTableMap::clearInstancePool();
            AdminTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildAdmin or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildAdmin object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(AdminTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(AdminTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        AdminTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            AdminTableMap::clearRelatedInstancePool();
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
     * @return     ChildAdminQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(AdminTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildAdminQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(AdminTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildAdminQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(AdminTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildAdminQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(AdminTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildAdminQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(AdminTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildAdminQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(AdminTableMap::CREATED_AT);
    }

} // AdminQuery
