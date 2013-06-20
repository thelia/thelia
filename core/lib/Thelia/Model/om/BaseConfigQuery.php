<?php

namespace Thelia\Model\om;

use \Criteria;
use \Exception;
use \ModelCriteria;
use \ModelJoin;
use \PDO;
use \Propel;
use \PropelCollection;
use \PropelException;
use \PropelObjectCollection;
use \PropelPDO;
use Thelia\Model\Config;
use Thelia\Model\ConfigI18n;
use Thelia\Model\ConfigPeer;
use Thelia\Model\ConfigQuery;

/**
 * Base class that represents a query for the 'config' table.
 *
 *
 *
 * @method ConfigQuery orderById($order = Criteria::ASC) Order by the id column
 * @method ConfigQuery orderByName($order = Criteria::ASC) Order by the name column
 * @method ConfigQuery orderByValue($order = Criteria::ASC) Order by the value column
 * @method ConfigQuery orderBySecured($order = Criteria::ASC) Order by the secured column
 * @method ConfigQuery orderByHidden($order = Criteria::ASC) Order by the hidden column
 * @method ConfigQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method ConfigQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method ConfigQuery groupById() Group by the id column
 * @method ConfigQuery groupByName() Group by the name column
 * @method ConfigQuery groupByValue() Group by the value column
 * @method ConfigQuery groupBySecured() Group by the secured column
 * @method ConfigQuery groupByHidden() Group by the hidden column
 * @method ConfigQuery groupByCreatedAt() Group by the created_at column
 * @method ConfigQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method ConfigQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method ConfigQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method ConfigQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method ConfigQuery leftJoinConfigI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the ConfigI18n relation
 * @method ConfigQuery rightJoinConfigI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ConfigI18n relation
 * @method ConfigQuery innerJoinConfigI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the ConfigI18n relation
 *
 * @method Config findOne(PropelPDO $con = null) Return the first Config matching the query
 * @method Config findOneOrCreate(PropelPDO $con = null) Return the first Config matching the query, or a new Config object populated from the query conditions when no match is found
 *
 * @method Config findOneByName(string $name) Return the first Config filtered by the name column
 * @method Config findOneByValue(string $value) Return the first Config filtered by the value column
 * @method Config findOneBySecured(int $secured) Return the first Config filtered by the secured column
 * @method Config findOneByHidden(int $hidden) Return the first Config filtered by the hidden column
 * @method Config findOneByCreatedAt(string $created_at) Return the first Config filtered by the created_at column
 * @method Config findOneByUpdatedAt(string $updated_at) Return the first Config filtered by the updated_at column
 *
 * @method array findById(int $id) Return Config objects filtered by the id column
 * @method array findByName(string $name) Return Config objects filtered by the name column
 * @method array findByValue(string $value) Return Config objects filtered by the value column
 * @method array findBySecured(int $secured) Return Config objects filtered by the secured column
 * @method array findByHidden(int $hidden) Return Config objects filtered by the hidden column
 * @method array findByCreatedAt(string $created_at) Return Config objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return Config objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseConfigQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseConfigQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\Config', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ConfigQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param   ConfigQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return ConfigQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof ConfigQuery) {
            return $criteria;
        }
        $query = new ConfigQuery();
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
     * @return   Config|Config[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = ConfigPeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(ConfigPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * Alias of findPk to use instance pooling
     *
     * @param     mixed $key Primary key to use for the query
     * @param     PropelPDO $con A connection object
     *
     * @return                 Config A model object, or null if the key is not found
     * @throws PropelException
     */
     public function findOneById($key, $con = null)
     {
        return $this->findPk($key, $con);
     }

    /**
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     PropelPDO $con A connection object
     *
     * @return                 Config A model object, or null if the key is not found
     * @throws PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at` FROM `config` WHERE `id` = :p0';
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
            $obj = new Config();
            $obj->hydrate($row);
            ConfigPeer::addInstanceToPool($obj, (string) $key);
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
     * @return Config|Config[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|Config[]|mixed the list of results, formatted by the current formatter
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
     * @return ConfigQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(ConfigPeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ConfigQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(ConfigPeer::ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the id column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE id = 1234
     * $query->filterById(array(12, 34)); // WHERE id IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE id >= 12
     * $query->filterById(array('max' => 12)); // WHERE id <= 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ConfigQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(ConfigPeer::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(ConfigPeer::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ConfigPeer::ID, $id, $comparison);
    }

    /**
     * Filter the query on the name column
     *
     * Example usage:
     * <code>
     * $query->filterByName('fooValue');   // WHERE name = 'fooValue'
     * $query->filterByName('%fooValue%'); // WHERE name LIKE '%fooValue%'
     * </code>
     *
     * @param     string $name The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ConfigQuery The current query, for fluid interface
     */
    public function filterByName($name = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($name)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $name)) {
                $name = str_replace('*', '%', $name);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(ConfigPeer::NAME, $name, $comparison);
    }

    /**
     * Filter the query on the value column
     *
     * Example usage:
     * <code>
     * $query->filterByValue('fooValue');   // WHERE value = 'fooValue'
     * $query->filterByValue('%fooValue%'); // WHERE value LIKE '%fooValue%'
     * </code>
     *
     * @param     string $value The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ConfigQuery The current query, for fluid interface
     */
    public function filterByValue($value = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($value)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $value)) {
                $value = str_replace('*', '%', $value);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(ConfigPeer::VALUE, $value, $comparison);
    }

    /**
     * Filter the query on the secured column
     *
     * Example usage:
     * <code>
     * $query->filterBySecured(1234); // WHERE secured = 1234
     * $query->filterBySecured(array(12, 34)); // WHERE secured IN (12, 34)
     * $query->filterBySecured(array('min' => 12)); // WHERE secured >= 12
     * $query->filterBySecured(array('max' => 12)); // WHERE secured <= 12
     * </code>
     *
     * @param     mixed $secured The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ConfigQuery The current query, for fluid interface
     */
    public function filterBySecured($secured = null, $comparison = null)
    {
        if (is_array($secured)) {
            $useMinMax = false;
            if (isset($secured['min'])) {
                $this->addUsingAlias(ConfigPeer::SECURED, $secured['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($secured['max'])) {
                $this->addUsingAlias(ConfigPeer::SECURED, $secured['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ConfigPeer::SECURED, $secured, $comparison);
    }

    /**
     * Filter the query on the hidden column
     *
     * Example usage:
     * <code>
     * $query->filterByHidden(1234); // WHERE hidden = 1234
     * $query->filterByHidden(array(12, 34)); // WHERE hidden IN (12, 34)
     * $query->filterByHidden(array('min' => 12)); // WHERE hidden >= 12
     * $query->filterByHidden(array('max' => 12)); // WHERE hidden <= 12
     * </code>
     *
     * @param     mixed $hidden The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ConfigQuery The current query, for fluid interface
     */
    public function filterByHidden($hidden = null, $comparison = null)
    {
        if (is_array($hidden)) {
            $useMinMax = false;
            if (isset($hidden['min'])) {
                $this->addUsingAlias(ConfigPeer::HIDDEN, $hidden['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($hidden['max'])) {
                $this->addUsingAlias(ConfigPeer::HIDDEN, $hidden['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ConfigPeer::HIDDEN, $hidden, $comparison);
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
     * @return ConfigQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(ConfigPeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(ConfigPeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ConfigPeer::CREATED_AT, $createdAt, $comparison);
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
     * @return ConfigQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(ConfigPeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(ConfigPeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ConfigPeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related ConfigI18n object
     *
     * @param   ConfigI18n|PropelObjectCollection $configI18n  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return                 ConfigQuery The current query, for fluid interface
     * @throws PropelException - if the provided filter is invalid.
     */
    public function filterByConfigI18n($configI18n, $comparison = null)
    {
        if ($configI18n instanceof ConfigI18n) {
            return $this
                ->addUsingAlias(ConfigPeer::ID, $configI18n->getId(), $comparison);
        } elseif ($configI18n instanceof PropelObjectCollection) {
            return $this
                ->useConfigI18nQuery()
                ->filterByPrimaryKeys($configI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByConfigI18n() only accepts arguments of type ConfigI18n or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ConfigI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ConfigQuery The current query, for fluid interface
     */
    public function joinConfigI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ConfigI18n');

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
            $this->addJoinObject($join, 'ConfigI18n');
        }

        return $this;
    }

    /**
     * Use the ConfigI18n relation ConfigI18n object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ConfigI18nQuery A secondary query class using the current class as primary query
     */
    public function useConfigI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinConfigI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ConfigI18n', '\Thelia\Model\ConfigI18nQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   Config $config Object to remove from the list of results
     *
     * @return ConfigQuery The current query, for fluid interface
     */
    public function prune($config = null)
    {
        if ($config) {
            $this->addUsingAlias(ConfigPeer::ID, $config->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    // timestampable behavior

    /**
     * Filter by the latest updated
     *
     * @param      int $nbDays Maximum age of the latest update in days
     *
     * @return     ConfigQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(ConfigPeer::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ConfigQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(ConfigPeer::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ConfigQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(ConfigPeer::UPDATED_AT);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ConfigQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(ConfigPeer::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by create date desc
     *
     * @return     ConfigQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(ConfigPeer::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ConfigQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(ConfigPeer::CREATED_AT);
    }
    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ConfigQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'ConfigI18n';

        return $this
            ->joinConfigI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ConfigQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('ConfigI18n');
        $this->with['ConfigI18n']->setIsWithOneToMany(false);

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
     * @return    ConfigI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ConfigI18n', 'Thelia\Model\ConfigI18nQuery');
    }

}
