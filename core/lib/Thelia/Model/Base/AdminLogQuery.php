<?php

namespace Thelia\Model\Base;

use \Exception;
use \PDO;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\AdminLog as ChildAdminLog;
use Thelia\Model\AdminLogQuery as ChildAdminLogQuery;
use Thelia\Model\Map\AdminLogTableMap;

/**
 * Base class that represents a query for the 'admin_log' table.
 *
 *
 *
 * @method     ChildAdminLogQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildAdminLogQuery orderByAdminLogin($order = Criteria::ASC) Order by the admin_login column
 * @method     ChildAdminLogQuery orderByAdminFirstname($order = Criteria::ASC) Order by the admin_firstname column
 * @method     ChildAdminLogQuery orderByAdminLastname($order = Criteria::ASC) Order by the admin_lastname column
 * @method     ChildAdminLogQuery orderByResource($order = Criteria::ASC) Order by the resource column
 * @method     ChildAdminLogQuery orderByResourceId($order = Criteria::ASC) Order by the resource_id column
 * @method     ChildAdminLogQuery orderByAction($order = Criteria::ASC) Order by the action column
 * @method     ChildAdminLogQuery orderByMessage($order = Criteria::ASC) Order by the message column
 * @method     ChildAdminLogQuery orderByRequest($order = Criteria::ASC) Order by the request column
 * @method     ChildAdminLogQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildAdminLogQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildAdminLogQuery groupById() Group by the id column
 * @method     ChildAdminLogQuery groupByAdminLogin() Group by the admin_login column
 * @method     ChildAdminLogQuery groupByAdminFirstname() Group by the admin_firstname column
 * @method     ChildAdminLogQuery groupByAdminLastname() Group by the admin_lastname column
 * @method     ChildAdminLogQuery groupByResource() Group by the resource column
 * @method     ChildAdminLogQuery groupByResourceId() Group by the resource_id column
 * @method     ChildAdminLogQuery groupByAction() Group by the action column
 * @method     ChildAdminLogQuery groupByMessage() Group by the message column
 * @method     ChildAdminLogQuery groupByRequest() Group by the request column
 * @method     ChildAdminLogQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildAdminLogQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildAdminLogQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildAdminLogQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildAdminLogQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildAdminLog findOne(ConnectionInterface $con = null) Return the first ChildAdminLog matching the query
 * @method     ChildAdminLog findOneOrCreate(ConnectionInterface $con = null) Return the first ChildAdminLog matching the query, or a new ChildAdminLog object populated from the query conditions when no match is found
 *
 * @method     ChildAdminLog findOneById(int $id) Return the first ChildAdminLog filtered by the id column
 * @method     ChildAdminLog findOneByAdminLogin(string $admin_login) Return the first ChildAdminLog filtered by the admin_login column
 * @method     ChildAdminLog findOneByAdminFirstname(string $admin_firstname) Return the first ChildAdminLog filtered by the admin_firstname column
 * @method     ChildAdminLog findOneByAdminLastname(string $admin_lastname) Return the first ChildAdminLog filtered by the admin_lastname column
 * @method     ChildAdminLog findOneByResource(string $resource) Return the first ChildAdminLog filtered by the resource column
 * @method     ChildAdminLog findOneByResourceId(int $resource_id) Return the first ChildAdminLog filtered by the resource_id column
 * @method     ChildAdminLog findOneByAction(string $action) Return the first ChildAdminLog filtered by the action column
 * @method     ChildAdminLog findOneByMessage(string $message) Return the first ChildAdminLog filtered by the message column
 * @method     ChildAdminLog findOneByRequest(string $request) Return the first ChildAdminLog filtered by the request column
 * @method     ChildAdminLog findOneByCreatedAt(string $created_at) Return the first ChildAdminLog filtered by the created_at column
 * @method     ChildAdminLog findOneByUpdatedAt(string $updated_at) Return the first ChildAdminLog filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildAdminLog objects filtered by the id column
 * @method     array findByAdminLogin(string $admin_login) Return ChildAdminLog objects filtered by the admin_login column
 * @method     array findByAdminFirstname(string $admin_firstname) Return ChildAdminLog objects filtered by the admin_firstname column
 * @method     array findByAdminLastname(string $admin_lastname) Return ChildAdminLog objects filtered by the admin_lastname column
 * @method     array findByResource(string $resource) Return ChildAdminLog objects filtered by the resource column
 * @method     array findByResourceId(int $resource_id) Return ChildAdminLog objects filtered by the resource_id column
 * @method     array findByAction(string $action) Return ChildAdminLog objects filtered by the action column
 * @method     array findByMessage(string $message) Return ChildAdminLog objects filtered by the message column
 * @method     array findByRequest(string $request) Return ChildAdminLog objects filtered by the request column
 * @method     array findByCreatedAt(string $created_at) Return ChildAdminLog objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildAdminLog objects filtered by the updated_at column
 *
 */
abstract class AdminLogQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\AdminLogQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\AdminLog', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildAdminLogQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildAdminLogQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\AdminLogQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\AdminLogQuery();
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
     * @return ChildAdminLog|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = AdminLogTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(AdminLogTableMap::DATABASE_NAME);
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
     * @return   ChildAdminLog A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `ADMIN_LOGIN`, `ADMIN_FIRSTNAME`, `ADMIN_LASTNAME`, `RESOURCE`, `RESOURCE_ID`, `ACTION`, `MESSAGE`, `REQUEST`, `CREATED_AT`, `UPDATED_AT` FROM `admin_log` WHERE `ID` = :p0';
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
            $obj = new ChildAdminLog();
            $obj->hydrate($row);
            AdminLogTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildAdminLog|array|mixed the result, formatted by the current formatter
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
     * @return ChildAdminLogQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(AdminLogTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildAdminLogQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(AdminLogTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildAdminLogQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(AdminLogTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(AdminLogTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AdminLogTableMap::ID, $id, $comparison);
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
     * @return ChildAdminLogQuery The current query, for fluid interface
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

        return $this->addUsingAlias(AdminLogTableMap::ADMIN_LOGIN, $adminLogin, $comparison);
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
     * @return ChildAdminLogQuery The current query, for fluid interface
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

        return $this->addUsingAlias(AdminLogTableMap::ADMIN_FIRSTNAME, $adminFirstname, $comparison);
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
     * @return ChildAdminLogQuery The current query, for fluid interface
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

        return $this->addUsingAlias(AdminLogTableMap::ADMIN_LASTNAME, $adminLastname, $comparison);
    }

    /**
     * Filter the query on the resource column
     *
     * Example usage:
     * <code>
     * $query->filterByResource('fooValue');   // WHERE resource = 'fooValue'
     * $query->filterByResource('%fooValue%'); // WHERE resource LIKE '%fooValue%'
     * </code>
     *
     * @param     string $resource The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAdminLogQuery The current query, for fluid interface
     */
    public function filterByResource($resource = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($resource)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $resource)) {
                $resource = str_replace('*', '%', $resource);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(AdminLogTableMap::RESOURCE, $resource, $comparison);
    }

    /**
     * Filter the query on the resource_id column
     *
     * Example usage:
     * <code>
     * $query->filterByResourceId(1234); // WHERE resource_id = 1234
     * $query->filterByResourceId(array(12, 34)); // WHERE resource_id IN (12, 34)
     * $query->filterByResourceId(array('min' => 12)); // WHERE resource_id > 12
     * </code>
     *
     * @param     mixed $resourceId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAdminLogQuery The current query, for fluid interface
     */
    public function filterByResourceId($resourceId = null, $comparison = null)
    {
        if (is_array($resourceId)) {
            $useMinMax = false;
            if (isset($resourceId['min'])) {
                $this->addUsingAlias(AdminLogTableMap::RESOURCE_ID, $resourceId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($resourceId['max'])) {
                $this->addUsingAlias(AdminLogTableMap::RESOURCE_ID, $resourceId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AdminLogTableMap::RESOURCE_ID, $resourceId, $comparison);
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
     * @return ChildAdminLogQuery The current query, for fluid interface
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

        return $this->addUsingAlias(AdminLogTableMap::ACTION, $action, $comparison);
    }

    /**
     * Filter the query on the message column
     *
     * Example usage:
     * <code>
     * $query->filterByMessage('fooValue');   // WHERE message = 'fooValue'
     * $query->filterByMessage('%fooValue%'); // WHERE message LIKE '%fooValue%'
     * </code>
     *
     * @param     string $message The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAdminLogQuery The current query, for fluid interface
     */
    public function filterByMessage($message = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($message)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $message)) {
                $message = str_replace('*', '%', $message);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(AdminLogTableMap::MESSAGE, $message, $comparison);
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
     * @return ChildAdminLogQuery The current query, for fluid interface
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

        return $this->addUsingAlias(AdminLogTableMap::REQUEST, $request, $comparison);
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
     * @return ChildAdminLogQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(AdminLogTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(AdminLogTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AdminLogTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildAdminLogQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(AdminLogTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(AdminLogTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AdminLogTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildAdminLog $adminLog Object to remove from the list of results
     *
     * @return ChildAdminLogQuery The current query, for fluid interface
     */
    public function prune($adminLog = null)
    {
        if ($adminLog) {
            $this->addUsingAlias(AdminLogTableMap::ID, $adminLog->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the admin_log table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(AdminLogTableMap::DATABASE_NAME);
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
            AdminLogTableMap::clearInstancePool();
            AdminLogTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildAdminLog or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildAdminLog object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(AdminLogTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(AdminLogTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        AdminLogTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            AdminLogTableMap::clearRelatedInstancePool();
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
     * @return     ChildAdminLogQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(AdminLogTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildAdminLogQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(AdminLogTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildAdminLogQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(AdminLogTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildAdminLogQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(AdminLogTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildAdminLogQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(AdminLogTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildAdminLogQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(AdminLogTableMap::CREATED_AT);
    }

} // AdminLogQuery
