<?php

namespace Thelia\Model\Base;

use \Exception;
use \PDO;
use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\FormFirewall as ChildFormFirewall;
use Thelia\Model\FormFirewallQuery as ChildFormFirewallQuery;
use Thelia\Model\Map\FormFirewallTableMap;

/**
 * Base class that represents a query for the 'form_firewall' table.
 *
 *
 *
 * @method     ChildFormFirewallQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildFormFirewallQuery orderByFormName($order = Criteria::ASC) Order by the form_name column
 * @method     ChildFormFirewallQuery orderByIpAddress($order = Criteria::ASC) Order by the ip_address column
 * @method     ChildFormFirewallQuery orderByAttempts($order = Criteria::ASC) Order by the attempts column
 * @method     ChildFormFirewallQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildFormFirewallQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildFormFirewallQuery groupById() Group by the id column
 * @method     ChildFormFirewallQuery groupByFormName() Group by the form_name column
 * @method     ChildFormFirewallQuery groupByIpAddress() Group by the ip_address column
 * @method     ChildFormFirewallQuery groupByAttempts() Group by the attempts column
 * @method     ChildFormFirewallQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildFormFirewallQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildFormFirewallQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildFormFirewallQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildFormFirewallQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildFormFirewall findOne(ConnectionInterface $con = null) Return the first ChildFormFirewall matching the query
 * @method     ChildFormFirewall findOneOrCreate(ConnectionInterface $con = null) Return the first ChildFormFirewall matching the query, or a new ChildFormFirewall object populated from the query conditions when no match is found
 *
 * @method     ChildFormFirewall findOneById(int $id) Return the first ChildFormFirewall filtered by the id column
 * @method     ChildFormFirewall findOneByFormName(string $form_name) Return the first ChildFormFirewall filtered by the form_name column
 * @method     ChildFormFirewall findOneByIpAddress(string $ip_address) Return the first ChildFormFirewall filtered by the ip_address column
 * @method     ChildFormFirewall findOneByAttempts(int $attempts) Return the first ChildFormFirewall filtered by the attempts column
 * @method     ChildFormFirewall findOneByCreatedAt(string $created_at) Return the first ChildFormFirewall filtered by the created_at column
 * @method     ChildFormFirewall findOneByUpdatedAt(string $updated_at) Return the first ChildFormFirewall filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildFormFirewall objects filtered by the id column
 * @method     array findByFormName(string $form_name) Return ChildFormFirewall objects filtered by the form_name column
 * @method     array findByIpAddress(string $ip_address) Return ChildFormFirewall objects filtered by the ip_address column
 * @method     array findByAttempts(int $attempts) Return ChildFormFirewall objects filtered by the attempts column
 * @method     array findByCreatedAt(string $created_at) Return ChildFormFirewall objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildFormFirewall objects filtered by the updated_at column
 *
 */
abstract class FormFirewallQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\FormFirewallQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\FormFirewall', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildFormFirewallQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildFormFirewallQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\FormFirewallQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\FormFirewallQuery();
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
     * @return ChildFormFirewall|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = FormFirewallTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(FormFirewallTableMap::DATABASE_NAME);
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
     * @return   ChildFormFirewall A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `FORM_NAME`, `IP_ADDRESS`, `ATTEMPTS`, `CREATED_AT`, `UPDATED_AT` FROM `form_firewall` WHERE `ID` = :p0';
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
            $obj = new ChildFormFirewall();
            $obj->hydrate($row);
            FormFirewallTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildFormFirewall|array|mixed the result, formatted by the current formatter
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
     * @return ChildFormFirewallQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(FormFirewallTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildFormFirewallQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(FormFirewallTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildFormFirewallQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(FormFirewallTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(FormFirewallTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FormFirewallTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the form_name column
     *
     * Example usage:
     * <code>
     * $query->filterByFormName('fooValue');   // WHERE form_name = 'fooValue'
     * $query->filterByFormName('%fooValue%'); // WHERE form_name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $formName The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildFormFirewallQuery The current query, for fluid interface
     */
    public function filterByFormName($formName = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($formName)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $formName)) {
                $formName = str_replace('*', '%', $formName);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(FormFirewallTableMap::FORM_NAME, $formName, $comparison);
    }

    /**
     * Filter the query on the ip_address column
     *
     * Example usage:
     * <code>
     * $query->filterByIpAddress('fooValue');   // WHERE ip_address = 'fooValue'
     * $query->filterByIpAddress('%fooValue%'); // WHERE ip_address LIKE '%fooValue%'
     * </code>
     *
     * @param     string $ipAddress The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildFormFirewallQuery The current query, for fluid interface
     */
    public function filterByIpAddress($ipAddress = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($ipAddress)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $ipAddress)) {
                $ipAddress = str_replace('*', '%', $ipAddress);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(FormFirewallTableMap::IP_ADDRESS, $ipAddress, $comparison);
    }

    /**
     * Filter the query on the attempts column
     *
     * Example usage:
     * <code>
     * $query->filterByAttempts(1234); // WHERE attempts = 1234
     * $query->filterByAttempts(array(12, 34)); // WHERE attempts IN (12, 34)
     * $query->filterByAttempts(array('min' => 12)); // WHERE attempts > 12
     * </code>
     *
     * @param     mixed $attempts The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildFormFirewallQuery The current query, for fluid interface
     */
    public function filterByAttempts($attempts = null, $comparison = null)
    {
        if (is_array($attempts)) {
            $useMinMax = false;
            if (isset($attempts['min'])) {
                $this->addUsingAlias(FormFirewallTableMap::ATTEMPTS, $attempts['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($attempts['max'])) {
                $this->addUsingAlias(FormFirewallTableMap::ATTEMPTS, $attempts['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FormFirewallTableMap::ATTEMPTS, $attempts, $comparison);
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
     * @return ChildFormFirewallQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(FormFirewallTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(FormFirewallTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FormFirewallTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildFormFirewallQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(FormFirewallTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(FormFirewallTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FormFirewallTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   ChildFormFirewall $formFirewall Object to remove from the list of results
     *
     * @return ChildFormFirewallQuery The current query, for fluid interface
     */
    public function prune($formFirewall = null)
    {
        if ($formFirewall) {
            $this->addUsingAlias(FormFirewallTableMap::ID, $formFirewall->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the form_firewall table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(FormFirewallTableMap::DATABASE_NAME);
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
            FormFirewallTableMap::clearInstancePool();
            FormFirewallTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildFormFirewall or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildFormFirewall object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(FormFirewallTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(FormFirewallTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        FormFirewallTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            FormFirewallTableMap::clearRelatedInstancePool();
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
     * @return     ChildFormFirewallQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(FormFirewallTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildFormFirewallQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(FormFirewallTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildFormFirewallQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(FormFirewallTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildFormFirewallQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(FormFirewallTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildFormFirewallQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(FormFirewallTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildFormFirewallQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(FormFirewallTableMap::CREATED_AT);
    }

} // FormFirewallQuery
