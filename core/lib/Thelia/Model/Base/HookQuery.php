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
use Thelia\Model\Hook as ChildHook;
use Thelia\Model\HookI18nQuery as ChildHookI18nQuery;
use Thelia\Model\HookQuery as ChildHookQuery;
use Thelia\Model\Map\HookTableMap;

/**
 * Base class that represents a query for the 'hook' table.
 *
 *
 *
 * @method     ChildHookQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildHookQuery orderByCode($order = Criteria::ASC) Order by the code column
 * @method     ChildHookQuery orderByType($order = Criteria::ASC) Order by the type column
 * @method     ChildHookQuery orderByByModule($order = Criteria::ASC) Order by the by_module column
 * @method     ChildHookQuery orderByNative($order = Criteria::ASC) Order by the native column
 * @method     ChildHookQuery orderByActivate($order = Criteria::ASC) Order by the activate column
 * @method     ChildHookQuery orderByBlock($order = Criteria::ASC) Order by the block column
 * @method     ChildHookQuery orderByPosition($order = Criteria::ASC) Order by the position column
 * @method     ChildHookQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildHookQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildHookQuery groupById() Group by the id column
 * @method     ChildHookQuery groupByCode() Group by the code column
 * @method     ChildHookQuery groupByType() Group by the type column
 * @method     ChildHookQuery groupByByModule() Group by the by_module column
 * @method     ChildHookQuery groupByNative() Group by the native column
 * @method     ChildHookQuery groupByActivate() Group by the activate column
 * @method     ChildHookQuery groupByBlock() Group by the block column
 * @method     ChildHookQuery groupByPosition() Group by the position column
 * @method     ChildHookQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildHookQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildHookQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildHookQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildHookQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildHookQuery leftJoinModuleHook($relationAlias = null) Adds a LEFT JOIN clause to the query using the ModuleHook relation
 * @method     ChildHookQuery rightJoinModuleHook($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ModuleHook relation
 * @method     ChildHookQuery innerJoinModuleHook($relationAlias = null) Adds a INNER JOIN clause to the query using the ModuleHook relation
 *
 * @method     ChildHookQuery leftJoinIgnoredModuleHook($relationAlias = null) Adds a LEFT JOIN clause to the query using the IgnoredModuleHook relation
 * @method     ChildHookQuery rightJoinIgnoredModuleHook($relationAlias = null) Adds a RIGHT JOIN clause to the query using the IgnoredModuleHook relation
 * @method     ChildHookQuery innerJoinIgnoredModuleHook($relationAlias = null) Adds a INNER JOIN clause to the query using the IgnoredModuleHook relation
 *
 * @method     ChildHookQuery leftJoinHookI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the HookI18n relation
 * @method     ChildHookQuery rightJoinHookI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the HookI18n relation
 * @method     ChildHookQuery innerJoinHookI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the HookI18n relation
 *
 * @method     ChildHook findOne(ConnectionInterface $con = null) Return the first ChildHook matching the query
 * @method     ChildHook findOneOrCreate(ConnectionInterface $con = null) Return the first ChildHook matching the query, or a new ChildHook object populated from the query conditions when no match is found
 *
 * @method     ChildHook findOneById(int $id) Return the first ChildHook filtered by the id column
 * @method     ChildHook findOneByCode(string $code) Return the first ChildHook filtered by the code column
 * @method     ChildHook findOneByType(int $type) Return the first ChildHook filtered by the type column
 * @method     ChildHook findOneByByModule(boolean $by_module) Return the first ChildHook filtered by the by_module column
 * @method     ChildHook findOneByNative(boolean $native) Return the first ChildHook filtered by the native column
 * @method     ChildHook findOneByActivate(boolean $activate) Return the first ChildHook filtered by the activate column
 * @method     ChildHook findOneByBlock(boolean $block) Return the first ChildHook filtered by the block column
 * @method     ChildHook findOneByPosition(int $position) Return the first ChildHook filtered by the position column
 * @method     ChildHook findOneByCreatedAt(string $created_at) Return the first ChildHook filtered by the created_at column
 * @method     ChildHook findOneByUpdatedAt(string $updated_at) Return the first ChildHook filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildHook objects filtered by the id column
 * @method     array findByCode(string $code) Return ChildHook objects filtered by the code column
 * @method     array findByType(int $type) Return ChildHook objects filtered by the type column
 * @method     array findByByModule(boolean $by_module) Return ChildHook objects filtered by the by_module column
 * @method     array findByNative(boolean $native) Return ChildHook objects filtered by the native column
 * @method     array findByActivate(boolean $activate) Return ChildHook objects filtered by the activate column
 * @method     array findByBlock(boolean $block) Return ChildHook objects filtered by the block column
 * @method     array findByPosition(int $position) Return ChildHook objects filtered by the position column
 * @method     array findByCreatedAt(string $created_at) Return ChildHook objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildHook objects filtered by the updated_at column
 *
 */
abstract class HookQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\HookQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Hook', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildHookQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildHookQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\HookQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\HookQuery();
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
     * @return ChildHook|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = HookTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(HookTableMap::DATABASE_NAME);
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
     * @return   ChildHook A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `CODE`, `TYPE`, `BY_MODULE`, `NATIVE`, `ACTIVATE`, `BLOCK`, `POSITION`, `CREATED_AT`, `UPDATED_AT` FROM `hook` WHERE `ID` = :p0';
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
            $obj = new ChildHook();
            $obj->hydrate($row);
            HookTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildHook|array|mixed the result, formatted by the current formatter
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
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(HookTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(HookTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(HookTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(HookTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(HookTableMap::ID, $id, $comparison);
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
     * @return ChildHookQuery The current query, for fluid interface
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

        return $this->addUsingAlias(HookTableMap::CODE, $code, $comparison);
    }

    /**
     * Filter the query on the type column
     *
     * Example usage:
     * <code>
     * $query->filterByType(1234); // WHERE type = 1234
     * $query->filterByType(array(12, 34)); // WHERE type IN (12, 34)
     * $query->filterByType(array('min' => 12)); // WHERE type > 12
     * </code>
     *
     * @param     mixed $type The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function filterByType($type = null, $comparison = null)
    {
        if (is_array($type)) {
            $useMinMax = false;
            if (isset($type['min'])) {
                $this->addUsingAlias(HookTableMap::TYPE, $type['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($type['max'])) {
                $this->addUsingAlias(HookTableMap::TYPE, $type['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(HookTableMap::TYPE, $type, $comparison);
    }

    /**
     * Filter the query on the by_module column
     *
     * Example usage:
     * <code>
     * $query->filterByByModule(true); // WHERE by_module = true
     * $query->filterByByModule('yes'); // WHERE by_module = true
     * </code>
     *
     * @param     boolean|string $byModule The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function filterByByModule($byModule = null, $comparison = null)
    {
        if (is_string($byModule)) {
            $by_module = in_array(strtolower($byModule), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(HookTableMap::BY_MODULE, $byModule, $comparison);
    }

    /**
     * Filter the query on the native column
     *
     * Example usage:
     * <code>
     * $query->filterByNative(true); // WHERE native = true
     * $query->filterByNative('yes'); // WHERE native = true
     * </code>
     *
     * @param     boolean|string $native The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function filterByNative($native = null, $comparison = null)
    {
        if (is_string($native)) {
            $native = in_array(strtolower($native), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(HookTableMap::NATIVE, $native, $comparison);
    }

    /**
     * Filter the query on the activate column
     *
     * Example usage:
     * <code>
     * $query->filterByActivate(true); // WHERE activate = true
     * $query->filterByActivate('yes'); // WHERE activate = true
     * </code>
     *
     * @param     boolean|string $activate The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function filterByActivate($activate = null, $comparison = null)
    {
        if (is_string($activate)) {
            $activate = in_array(strtolower($activate), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(HookTableMap::ACTIVATE, $activate, $comparison);
    }

    /**
     * Filter the query on the block column
     *
     * Example usage:
     * <code>
     * $query->filterByBlock(true); // WHERE block = true
     * $query->filterByBlock('yes'); // WHERE block = true
     * </code>
     *
     * @param     boolean|string $block The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function filterByBlock($block = null, $comparison = null)
    {
        if (is_string($block)) {
            $block = in_array(strtolower($block), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(HookTableMap::BLOCK, $block, $comparison);
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
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        if (is_array($position)) {
            $useMinMax = false;
            if (isset($position['min'])) {
                $this->addUsingAlias(HookTableMap::POSITION, $position['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($position['max'])) {
                $this->addUsingAlias(HookTableMap::POSITION, $position['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(HookTableMap::POSITION, $position, $comparison);
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
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(HookTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(HookTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(HookTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(HookTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(HookTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(HookTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\ModuleHook object
     *
     * @param \Thelia\Model\ModuleHook|ObjectCollection $moduleHook  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function filterByModuleHook($moduleHook, $comparison = null)
    {
        if ($moduleHook instanceof \Thelia\Model\ModuleHook) {
            return $this
                ->addUsingAlias(HookTableMap::ID, $moduleHook->getHookId(), $comparison);
        } elseif ($moduleHook instanceof ObjectCollection) {
            return $this
                ->useModuleHookQuery()
                ->filterByPrimaryKeys($moduleHook->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByModuleHook() only accepts arguments of type \Thelia\Model\ModuleHook or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ModuleHook relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function joinModuleHook($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ModuleHook');

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
            $this->addJoinObject($join, 'ModuleHook');
        }

        return $this;
    }

    /**
     * Use the ModuleHook relation ModuleHook object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ModuleHookQuery A secondary query class using the current class as primary query
     */
    public function useModuleHookQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinModuleHook($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ModuleHook', '\Thelia\Model\ModuleHookQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\IgnoredModuleHook object
     *
     * @param \Thelia\Model\IgnoredModuleHook|ObjectCollection $ignoredModuleHook  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function filterByIgnoredModuleHook($ignoredModuleHook, $comparison = null)
    {
        if ($ignoredModuleHook instanceof \Thelia\Model\IgnoredModuleHook) {
            return $this
                ->addUsingAlias(HookTableMap::ID, $ignoredModuleHook->getHookId(), $comparison);
        } elseif ($ignoredModuleHook instanceof ObjectCollection) {
            return $this
                ->useIgnoredModuleHookQuery()
                ->filterByPrimaryKeys($ignoredModuleHook->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByIgnoredModuleHook() only accepts arguments of type \Thelia\Model\IgnoredModuleHook or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the IgnoredModuleHook relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function joinIgnoredModuleHook($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('IgnoredModuleHook');

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
            $this->addJoinObject($join, 'IgnoredModuleHook');
        }

        return $this;
    }

    /**
     * Use the IgnoredModuleHook relation IgnoredModuleHook object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\IgnoredModuleHookQuery A secondary query class using the current class as primary query
     */
    public function useIgnoredModuleHookQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinIgnoredModuleHook($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'IgnoredModuleHook', '\Thelia\Model\IgnoredModuleHookQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\HookI18n object
     *
     * @param \Thelia\Model\HookI18n|ObjectCollection $hookI18n  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function filterByHookI18n($hookI18n, $comparison = null)
    {
        if ($hookI18n instanceof \Thelia\Model\HookI18n) {
            return $this
                ->addUsingAlias(HookTableMap::ID, $hookI18n->getId(), $comparison);
        } elseif ($hookI18n instanceof ObjectCollection) {
            return $this
                ->useHookI18nQuery()
                ->filterByPrimaryKeys($hookI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByHookI18n() only accepts arguments of type \Thelia\Model\HookI18n or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the HookI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function joinHookI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('HookI18n');

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
            $this->addJoinObject($join, 'HookI18n');
        }

        return $this;
    }

    /**
     * Use the HookI18n relation HookI18n object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\HookI18nQuery A secondary query class using the current class as primary query
     */
    public function useHookI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinHookI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'HookI18n', '\Thelia\Model\HookI18nQuery');
    }

    /**
     * Filter the query by a related Module object
     * using the ignored_module_hook table as cross reference
     *
     * @param Module $module the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function filterByModule($module, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useIgnoredModuleHookQuery()
            ->filterByModule($module, $comparison)
            ->endUse();
    }

    /**
     * Exclude object from result
     *
     * @param   ChildHook $hook Object to remove from the list of results
     *
     * @return ChildHookQuery The current query, for fluid interface
     */
    public function prune($hook = null)
    {
        if ($hook) {
            $this->addUsingAlias(HookTableMap::ID, $hook->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the hook table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(HookTableMap::DATABASE_NAME);
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
            HookTableMap::clearInstancePool();
            HookTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildHook or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildHook object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(HookTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(HookTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        HookTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            HookTableMap::clearRelatedInstancePool();
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
     * @return     ChildHookQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(HookTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildHookQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(HookTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildHookQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(HookTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildHookQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(HookTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildHookQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(HookTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildHookQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(HookTableMap::CREATED_AT);
    }

    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildHookQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'HookI18n';

        return $this
            ->joinHookI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildHookQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('HookI18n');
        $this->with['HookI18n']->setIsWithOneToMany(false);

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
     * @return    ChildHookI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'HookI18n', '\Thelia\Model\HookI18nQuery');
    }

} // HookQuery
