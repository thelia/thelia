<?php

namespace Thelia\Model\om;

use \Criteria;
use \Exception;
use \ModelCriteria;
use \PDO;
use \Propel;
use \PropelException;
use \PropelObjectCollection;
use \PropelPDO;
use Thelia\Model\AdminLog;
use Thelia\Model\AdminLogPeer;
use Thelia\Model\AdminLogQuery;

/**
 * Base class that represents a query for the 'admin_log' table.
 *
 *
 *
 * @method AdminLogQuery orderById($order = Criteria::ASC) Order by the id column
 * @method AdminLogQuery orderByAdminLogin($order = Criteria::ASC) Order by the admin_login column
 * @method AdminLogQuery orderByAdminFirstname($order = Criteria::ASC) Order by the admin_firstname column
 * @method AdminLogQuery orderByAdminLastname($order = Criteria::ASC) Order by the admin_lastname column
 * @method AdminLogQuery orderByAction($order = Criteria::ASC) Order by the action column
 * @method AdminLogQuery orderByRequest($order = Criteria::ASC) Order by the request column
 * @method AdminLogQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method AdminLogQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method AdminLogQuery groupById() Group by the id column
 * @method AdminLogQuery groupByAdminLogin() Group by the admin_login column
 * @method AdminLogQuery groupByAdminFirstname() Group by the admin_firstname column
 * @method AdminLogQuery groupByAdminLastname() Group by the admin_lastname column
 * @method AdminLogQuery groupByAction() Group by the action column
 * @method AdminLogQuery groupByRequest() Group by the request column
 * @method AdminLogQuery groupByCreatedAt() Group by the created_at column
 * @method AdminLogQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method AdminLogQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method AdminLogQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method AdminLogQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method AdminLog findOne(PropelPDO $con = null) Return the first AdminLog matching the query
 * @method AdminLog findOneOrCreate(PropelPDO $con = null) Return the first AdminLog matching the query, or a new AdminLog object populated from the query conditions when no match is found
 *
 * @method AdminLog findOneById(int $id) Return the first AdminLog filtered by the id column
 * @method AdminLog findOneByAdminLogin(string $admin_login) Return the first AdminLog filtered by the admin_login column
 * @method AdminLog findOneByAdminFirstname(string $admin_firstname) Return the first AdminLog filtered by the admin_firstname column
 * @method AdminLog findOneByAdminLastname(string $admin_lastname) Return the first AdminLog filtered by the admin_lastname column
 * @method AdminLog findOneByAction(string $action) Return the first AdminLog filtered by the action column
 * @method AdminLog findOneByRequest(string $request) Return the first AdminLog filtered by the request column
 * @method AdminLog findOneByCreatedAt(string $created_at) Return the first AdminLog filtered by the created_at column
 * @method AdminLog findOneByUpdatedAt(string $updated_at) Return the first AdminLog filtered by the updated_at column
 *
 * @method array findById(int $id) Return AdminLog objects filtered by the id column
 * @method array findByAdminLogin(string $admin_login) Return AdminLog objects filtered by the admin_login column
 * @method array findByAdminFirstname(string $admin_firstname) Return AdminLog objects filtered by the admin_firstname column
 * @method array findByAdminLastname(string $admin_lastname) Return AdminLog objects filtered by the admin_lastname column
 * @method array findByAction(string $action) Return AdminLog objects filtered by the action column
 * @method array findByRequest(string $request) Return AdminLog objects filtered by the request column
 * @method array findByCreatedAt(string $created_at) Return AdminLog objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return AdminLog objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseAdminLogQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseAdminLogQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'mydb', $modelName = 'Thelia\\Model\\AdminLog', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new AdminLogQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     AdminLogQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return AdminLogQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof AdminLogQuery) {
            return $criteria;
        }
        $query = new AdminLogQuery();
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
     * @return   AdminLog|AdminLog[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = AdminLogPeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(AdminLogPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return   AdminLog A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `ADMIN_LOGIN`, `ADMIN_FIRSTNAME`, `ADMIN_LASTNAME`, `ACTION`, `REQUEST`, `CREATED_AT`, `UPDATED_AT` FROM `admin_log` WHERE `ID` = :p0';
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
            $obj = new AdminLog();
            $obj->hydrate($row);
            AdminLogPeer::addInstanceToPool($obj, (string) $key);
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
     * @return AdminLog|AdminLog[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|AdminLog[]|mixed the list of results, formatted by the current formatter
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
     * @return AdminLogQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(AdminLogPeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return AdminLogQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(AdminLogPeer::ID, $keys, Criteria::IN);
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
     * @return AdminLogQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(AdminLogPeer::ID, $id, $comparison);
    }

    /**
     * Filter the query on the admin_login column
     *
     * Example usage:
     * <code>
     * $query->filterByAdminLogin('fooValue');   // WHERE admin_login = 'fooValue'
     * $query->filterByAdminLogin('%fooValue%'); // WHERE admin_login LIKE '%fooValue%'
     * </code>
     *
     * @param     string $adminLogin The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return AdminLogQuery The current query, for fluid interface
     */
    public function filterByAdminLogin($adminLogin = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($adminLogin)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $adminLogin)) {
                $adminLogin = str_replace('*', '%', $adminLogin);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(AdminLogPeer::ADMIN_LOGIN, $adminLogin, $comparison);
    }

    /**
     * Filter the query on the admin_firstname column
     *
     * Example usage:
     * <code>
     * $query->filterByAdminFirstname('fooValue');   // WHERE admin_firstname = 'fooValue'
     * $query->filterByAdminFirstname('%fooValue%'); // WHERE admin_firstname LIKE '%fooValue%'
     * </code>
     *
     * @param     string $adminFirstname The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return AdminLogQuery The current query, for fluid interface
     */
    public function filterByAdminFirstname($adminFirstname = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($adminFirstname)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $adminFirstname)) {
                $adminFirstname = str_replace('*', '%', $adminFirstname);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(AdminLogPeer::ADMIN_FIRSTNAME, $adminFirstname, $comparison);
    }

    /**
     * Filter the query on the admin_lastname column
     *
     * Example usage:
     * <code>
     * $query->filterByAdminLastname('fooValue');   // WHERE admin_lastname = 'fooValue'
     * $query->filterByAdminLastname('%fooValue%'); // WHERE admin_lastname LIKE '%fooValue%'
     * </code>
     *
     * @param     string $adminLastname The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return AdminLogQuery The current query, for fluid interface
     */
    public function filterByAdminLastname($adminLastname = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($adminLastname)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $adminLastname)) {
                $adminLastname = str_replace('*', '%', $adminLastname);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(AdminLogPeer::ADMIN_LASTNAME, $adminLastname, $comparison);
    }

    /**
     * Filter the query on the action column
     *
     * Example usage:
     * <code>
     * $query->filterByAction('fooValue');   // WHERE action = 'fooValue'
     * $query->filterByAction('%fooValue%'); // WHERE action LIKE '%fooValue%'
     * </code>
     *
     * @param     string $action The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return AdminLogQuery The current query, for fluid interface
     */
    public function filterByAction($action = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($action)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $action)) {
                $action = str_replace('*', '%', $action);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(AdminLogPeer::ACTION, $action, $comparison);
    }

    /**
     * Filter the query on the request column
     *
     * Example usage:
     * <code>
     * $query->filterByRequest('fooValue');   // WHERE request = 'fooValue'
     * $query->filterByRequest('%fooValue%'); // WHERE request LIKE '%fooValue%'
     * </code>
     *
     * @param     string $request The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return AdminLogQuery The current query, for fluid interface
     */
    public function filterByRequest($request = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($request)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $request)) {
                $request = str_replace('*', '%', $request);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(AdminLogPeer::REQUEST, $request, $comparison);
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
     * @return AdminLogQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(AdminLogPeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(AdminLogPeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AdminLogPeer::CREATED_AT, $createdAt, $comparison);
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
     * @return AdminLogQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(AdminLogPeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(AdminLogPeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AdminLogPeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   AdminLog $adminLog Object to remove from the list of results
     *
     * @return AdminLogQuery The current query, for fluid interface
     */
    public function prune($adminLog = null)
    {
        if ($adminLog) {
            $this->addUsingAlias(AdminLogPeer::ID, $adminLog->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

}
