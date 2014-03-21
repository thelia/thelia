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
use Thelia\Model\Content as ChildContent;
use Thelia\Model\ContentI18nQuery as ChildContentI18nQuery;
use Thelia\Model\ContentQuery as ChildContentQuery;
use Thelia\Model\Map\ContentTableMap;

/**
 * Base class that represents a query for the 'content' table.
 *
 *
 *
 * @method     ChildContentQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildContentQuery orderByVisible($order = Criteria::ASC) Order by the visible column
 * @method     ChildContentQuery orderByPosition($order = Criteria::ASC) Order by the position column
 * @method     ChildContentQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildContentQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 * @method     ChildContentQuery orderByVersion($order = Criteria::ASC) Order by the version column
 * @method     ChildContentQuery orderByVersionCreatedAt($order = Criteria::ASC) Order by the version_created_at column
 * @method     ChildContentQuery orderByVersionCreatedBy($order = Criteria::ASC) Order by the version_created_by column
 *
 * @method     ChildContentQuery groupById() Group by the id column
 * @method     ChildContentQuery groupByVisible() Group by the visible column
 * @method     ChildContentQuery groupByPosition() Group by the position column
 * @method     ChildContentQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildContentQuery groupByUpdatedAt() Group by the updated_at column
 * @method     ChildContentQuery groupByVersion() Group by the version column
 * @method     ChildContentQuery groupByVersionCreatedAt() Group by the version_created_at column
 * @method     ChildContentQuery groupByVersionCreatedBy() Group by the version_created_by column
 *
 * @method     ChildContentQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildContentQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildContentQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildContentQuery leftJoinContentFolder($relationAlias = null) Adds a LEFT JOIN clause to the query using the ContentFolder relation
 * @method     ChildContentQuery rightJoinContentFolder($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ContentFolder relation
 * @method     ChildContentQuery innerJoinContentFolder($relationAlias = null) Adds a INNER JOIN clause to the query using the ContentFolder relation
 *
 * @method     ChildContentQuery leftJoinContentImage($relationAlias = null) Adds a LEFT JOIN clause to the query using the ContentImage relation
 * @method     ChildContentQuery rightJoinContentImage($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ContentImage relation
 * @method     ChildContentQuery innerJoinContentImage($relationAlias = null) Adds a INNER JOIN clause to the query using the ContentImage relation
 *
 * @method     ChildContentQuery leftJoinContentDocument($relationAlias = null) Adds a LEFT JOIN clause to the query using the ContentDocument relation
 * @method     ChildContentQuery rightJoinContentDocument($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ContentDocument relation
 * @method     ChildContentQuery innerJoinContentDocument($relationAlias = null) Adds a INNER JOIN clause to the query using the ContentDocument relation
 *
 * @method     ChildContentQuery leftJoinProductAssociatedContent($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProductAssociatedContent relation
 * @method     ChildContentQuery rightJoinProductAssociatedContent($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProductAssociatedContent relation
 * @method     ChildContentQuery innerJoinProductAssociatedContent($relationAlias = null) Adds a INNER JOIN clause to the query using the ProductAssociatedContent relation
 *
 * @method     ChildContentQuery leftJoinCategoryAssociatedContent($relationAlias = null) Adds a LEFT JOIN clause to the query using the CategoryAssociatedContent relation
 * @method     ChildContentQuery rightJoinCategoryAssociatedContent($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CategoryAssociatedContent relation
 * @method     ChildContentQuery innerJoinCategoryAssociatedContent($relationAlias = null) Adds a INNER JOIN clause to the query using the CategoryAssociatedContent relation
 *
 * @method     ChildContentQuery leftJoinContentI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the ContentI18n relation
 * @method     ChildContentQuery rightJoinContentI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ContentI18n relation
 * @method     ChildContentQuery innerJoinContentI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the ContentI18n relation
 *
 * @method     ChildContentQuery leftJoinContentVersion($relationAlias = null) Adds a LEFT JOIN clause to the query using the ContentVersion relation
 * @method     ChildContentQuery rightJoinContentVersion($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ContentVersion relation
 * @method     ChildContentQuery innerJoinContentVersion($relationAlias = null) Adds a INNER JOIN clause to the query using the ContentVersion relation
 *
 * @method     ChildContent findOne(ConnectionInterface $con = null) Return the first ChildContent matching the query
 * @method     ChildContent findOneOrCreate(ConnectionInterface $con = null) Return the first ChildContent matching the query, or a new ChildContent object populated from the query conditions when no match is found
 *
 * @method     ChildContent findOneById(int $id) Return the first ChildContent filtered by the id column
 * @method     ChildContent findOneByVisible(int $visible) Return the first ChildContent filtered by the visible column
 * @method     ChildContent findOneByPosition(int $position) Return the first ChildContent filtered by the position column
 * @method     ChildContent findOneByCreatedAt(string $created_at) Return the first ChildContent filtered by the created_at column
 * @method     ChildContent findOneByUpdatedAt(string $updated_at) Return the first ChildContent filtered by the updated_at column
 * @method     ChildContent findOneByVersion(int $version) Return the first ChildContent filtered by the version column
 * @method     ChildContent findOneByVersionCreatedAt(string $version_created_at) Return the first ChildContent filtered by the version_created_at column
 * @method     ChildContent findOneByVersionCreatedBy(string $version_created_by) Return the first ChildContent filtered by the version_created_by column
 *
 * @method     array findById(int $id) Return ChildContent objects filtered by the id column
 * @method     array findByVisible(int $visible) Return ChildContent objects filtered by the visible column
 * @method     array findByPosition(int $position) Return ChildContent objects filtered by the position column
 * @method     array findByCreatedAt(string $created_at) Return ChildContent objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildContent objects filtered by the updated_at column
 * @method     array findByVersion(int $version) Return ChildContent objects filtered by the version column
 * @method     array findByVersionCreatedAt(string $version_created_at) Return ChildContent objects filtered by the version_created_at column
 * @method     array findByVersionCreatedBy(string $version_created_by) Return ChildContent objects filtered by the version_created_by column
 *
 */
abstract class ContentQuery extends ModelCriteria
{

    // versionable behavior

    /**
     * Whether the versioning is enabled
     */
    static $isVersioningEnabled = true;

    /**
     * Initializes internal state of \Thelia\Model\Base\ContentQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Content', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildContentQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildContentQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\ContentQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\ContentQuery();
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
     * @return ChildContent|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = ContentTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(ContentTableMap::DATABASE_NAME);
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
     * @return   ChildContent A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `VISIBLE`, `POSITION`, `CREATED_AT`, `UPDATED_AT`, `VERSION`, `VERSION_CREATED_AT`, `VERSION_CREATED_BY` FROM `content` WHERE `ID` = :p0';
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
            $obj = new ChildContent();
            $obj->hydrate($row);
            ContentTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildContent|array|mixed the result, formatted by the current formatter
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
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(ContentTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(ContentTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(ContentTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(ContentTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ContentTableMap::ID, $id, $comparison);
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
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByVisible($visible = null, $comparison = null)
    {
        if (is_array($visible)) {
            $useMinMax = false;
            if (isset($visible['min'])) {
                $this->addUsingAlias(ContentTableMap::VISIBLE, $visible['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($visible['max'])) {
                $this->addUsingAlias(ContentTableMap::VISIBLE, $visible['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ContentTableMap::VISIBLE, $visible, $comparison);
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
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        if (is_array($position)) {
            $useMinMax = false;
            if (isset($position['min'])) {
                $this->addUsingAlias(ContentTableMap::POSITION, $position['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($position['max'])) {
                $this->addUsingAlias(ContentTableMap::POSITION, $position['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ContentTableMap::POSITION, $position, $comparison);
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
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(ContentTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(ContentTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ContentTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(ContentTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(ContentTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ContentTableMap::UPDATED_AT, $updatedAt, $comparison);
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
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByVersion($version = null, $comparison = null)
    {
        if (is_array($version)) {
            $useMinMax = false;
            if (isset($version['min'])) {
                $this->addUsingAlias(ContentTableMap::VERSION, $version['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($version['max'])) {
                $this->addUsingAlias(ContentTableMap::VERSION, $version['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ContentTableMap::VERSION, $version, $comparison);
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
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByVersionCreatedAt($versionCreatedAt = null, $comparison = null)
    {
        if (is_array($versionCreatedAt)) {
            $useMinMax = false;
            if (isset($versionCreatedAt['min'])) {
                $this->addUsingAlias(ContentTableMap::VERSION_CREATED_AT, $versionCreatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($versionCreatedAt['max'])) {
                $this->addUsingAlias(ContentTableMap::VERSION_CREATED_AT, $versionCreatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ContentTableMap::VERSION_CREATED_AT, $versionCreatedAt, $comparison);
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
     * @return ChildContentQuery The current query, for fluid interface
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

        return $this->addUsingAlias(ContentTableMap::VERSION_CREATED_BY, $versionCreatedBy, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\ContentFolder object
     *
     * @param \Thelia\Model\ContentFolder|ObjectCollection $contentFolder  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByContentFolder($contentFolder, $comparison = null)
    {
        if ($contentFolder instanceof \Thelia\Model\ContentFolder) {
            return $this
                ->addUsingAlias(ContentTableMap::ID, $contentFolder->getContentId(), $comparison);
        } elseif ($contentFolder instanceof ObjectCollection) {
            return $this
                ->useContentFolderQuery()
                ->filterByPrimaryKeys($contentFolder->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByContentFolder() only accepts arguments of type \Thelia\Model\ContentFolder or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ContentFolder relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function joinContentFolder($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ContentFolder');

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
            $this->addJoinObject($join, 'ContentFolder');
        }

        return $this;
    }

    /**
     * Use the ContentFolder relation ContentFolder object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ContentFolderQuery A secondary query class using the current class as primary query
     */
    public function useContentFolderQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinContentFolder($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ContentFolder', '\Thelia\Model\ContentFolderQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ContentImage object
     *
     * @param \Thelia\Model\ContentImage|ObjectCollection $contentImage  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByContentImage($contentImage, $comparison = null)
    {
        if ($contentImage instanceof \Thelia\Model\ContentImage) {
            return $this
                ->addUsingAlias(ContentTableMap::ID, $contentImage->getContentId(), $comparison);
        } elseif ($contentImage instanceof ObjectCollection) {
            return $this
                ->useContentImageQuery()
                ->filterByPrimaryKeys($contentImage->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByContentImage() only accepts arguments of type \Thelia\Model\ContentImage or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ContentImage relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function joinContentImage($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ContentImage');

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
            $this->addJoinObject($join, 'ContentImage');
        }

        return $this;
    }

    /**
     * Use the ContentImage relation ContentImage object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ContentImageQuery A secondary query class using the current class as primary query
     */
    public function useContentImageQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinContentImage($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ContentImage', '\Thelia\Model\ContentImageQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ContentDocument object
     *
     * @param \Thelia\Model\ContentDocument|ObjectCollection $contentDocument  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByContentDocument($contentDocument, $comparison = null)
    {
        if ($contentDocument instanceof \Thelia\Model\ContentDocument) {
            return $this
                ->addUsingAlias(ContentTableMap::ID, $contentDocument->getContentId(), $comparison);
        } elseif ($contentDocument instanceof ObjectCollection) {
            return $this
                ->useContentDocumentQuery()
                ->filterByPrimaryKeys($contentDocument->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByContentDocument() only accepts arguments of type \Thelia\Model\ContentDocument or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ContentDocument relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function joinContentDocument($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ContentDocument');

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
            $this->addJoinObject($join, 'ContentDocument');
        }

        return $this;
    }

    /**
     * Use the ContentDocument relation ContentDocument object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ContentDocumentQuery A secondary query class using the current class as primary query
     */
    public function useContentDocumentQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinContentDocument($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ContentDocument', '\Thelia\Model\ContentDocumentQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ProductAssociatedContent object
     *
     * @param \Thelia\Model\ProductAssociatedContent|ObjectCollection $productAssociatedContent  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByProductAssociatedContent($productAssociatedContent, $comparison = null)
    {
        if ($productAssociatedContent instanceof \Thelia\Model\ProductAssociatedContent) {
            return $this
                ->addUsingAlias(ContentTableMap::ID, $productAssociatedContent->getContentId(), $comparison);
        } elseif ($productAssociatedContent instanceof ObjectCollection) {
            return $this
                ->useProductAssociatedContentQuery()
                ->filterByPrimaryKeys($productAssociatedContent->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByProductAssociatedContent() only accepts arguments of type \Thelia\Model\ProductAssociatedContent or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ProductAssociatedContent relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function joinProductAssociatedContent($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ProductAssociatedContent');

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
            $this->addJoinObject($join, 'ProductAssociatedContent');
        }

        return $this;
    }

    /**
     * Use the ProductAssociatedContent relation ProductAssociatedContent object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ProductAssociatedContentQuery A secondary query class using the current class as primary query
     */
    public function useProductAssociatedContentQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinProductAssociatedContent($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ProductAssociatedContent', '\Thelia\Model\ProductAssociatedContentQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\CategoryAssociatedContent object
     *
     * @param \Thelia\Model\CategoryAssociatedContent|ObjectCollection $categoryAssociatedContent  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByCategoryAssociatedContent($categoryAssociatedContent, $comparison = null)
    {
        if ($categoryAssociatedContent instanceof \Thelia\Model\CategoryAssociatedContent) {
            return $this
                ->addUsingAlias(ContentTableMap::ID, $categoryAssociatedContent->getContentId(), $comparison);
        } elseif ($categoryAssociatedContent instanceof ObjectCollection) {
            return $this
                ->useCategoryAssociatedContentQuery()
                ->filterByPrimaryKeys($categoryAssociatedContent->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCategoryAssociatedContent() only accepts arguments of type \Thelia\Model\CategoryAssociatedContent or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CategoryAssociatedContent relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function joinCategoryAssociatedContent($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CategoryAssociatedContent');

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
            $this->addJoinObject($join, 'CategoryAssociatedContent');
        }

        return $this;
    }

    /**
     * Use the CategoryAssociatedContent relation CategoryAssociatedContent object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CategoryAssociatedContentQuery A secondary query class using the current class as primary query
     */
    public function useCategoryAssociatedContentQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCategoryAssociatedContent($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CategoryAssociatedContent', '\Thelia\Model\CategoryAssociatedContentQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ContentI18n object
     *
     * @param \Thelia\Model\ContentI18n|ObjectCollection $contentI18n  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByContentI18n($contentI18n, $comparison = null)
    {
        if ($contentI18n instanceof \Thelia\Model\ContentI18n) {
            return $this
                ->addUsingAlias(ContentTableMap::ID, $contentI18n->getId(), $comparison);
        } elseif ($contentI18n instanceof ObjectCollection) {
            return $this
                ->useContentI18nQuery()
                ->filterByPrimaryKeys($contentI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByContentI18n() only accepts arguments of type \Thelia\Model\ContentI18n or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ContentI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function joinContentI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ContentI18n');

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
            $this->addJoinObject($join, 'ContentI18n');
        }

        return $this;
    }

    /**
     * Use the ContentI18n relation ContentI18n object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ContentI18nQuery A secondary query class using the current class as primary query
     */
    public function useContentI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinContentI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ContentI18n', '\Thelia\Model\ContentI18nQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ContentVersion object
     *
     * @param \Thelia\Model\ContentVersion|ObjectCollection $contentVersion  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByContentVersion($contentVersion, $comparison = null)
    {
        if ($contentVersion instanceof \Thelia\Model\ContentVersion) {
            return $this
                ->addUsingAlias(ContentTableMap::ID, $contentVersion->getId(), $comparison);
        } elseif ($contentVersion instanceof ObjectCollection) {
            return $this
                ->useContentVersionQuery()
                ->filterByPrimaryKeys($contentVersion->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByContentVersion() only accepts arguments of type \Thelia\Model\ContentVersion or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ContentVersion relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function joinContentVersion($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ContentVersion');

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
            $this->addJoinObject($join, 'ContentVersion');
        }

        return $this;
    }

    /**
     * Use the ContentVersion relation ContentVersion object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ContentVersionQuery A secondary query class using the current class as primary query
     */
    public function useContentVersionQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinContentVersion($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ContentVersion', '\Thelia\Model\ContentVersionQuery');
    }

    /**
     * Filter the query by a related Folder object
     * using the content_folder table as cross reference
     *
     * @param Folder $folder the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function filterByFolder($folder, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useContentFolderQuery()
            ->filterByFolder($folder, $comparison)
            ->endUse();
    }

    /**
     * Exclude object from result
     *
     * @param   ChildContent $content Object to remove from the list of results
     *
     * @return ChildContentQuery The current query, for fluid interface
     */
    public function prune($content = null)
    {
        if ($content) {
            $this->addUsingAlias(ContentTableMap::ID, $content->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the content table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ContentTableMap::DATABASE_NAME);
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
            ContentTableMap::clearInstancePool();
            ContentTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildContent or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildContent object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(ContentTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(ContentTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        ContentTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            ContentTableMap::clearRelatedInstancePool();
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
     * @return     ChildContentQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(ContentTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildContentQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(ContentTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildContentQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(ContentTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildContentQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(ContentTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildContentQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(ContentTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildContentQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(ContentTableMap::CREATED_AT);
    }

    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildContentQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'ContentI18n';

        return $this
            ->joinContentI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildContentQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('ContentI18n');
        $this->with['ContentI18n']->setIsWithOneToMany(false);

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
     * @return    ChildContentI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ContentI18n', '\Thelia\Model\ContentI18nQuery');
    }

    // versionable behavior

    /**
     * Checks whether versioning is enabled
     *
     * @return boolean
     */
    static public function isVersioningEnabled()
    {
        return self::$isVersioningEnabled;
    }

    /**
     * Enables versioning
     */
    static public function enableVersioning()
    {
        self::$isVersioningEnabled = true;
    }

    /**
     * Disables versioning
     */
    static public function disableVersioning()
    {
        self::$isVersioningEnabled = false;
    }

} // ContentQuery
