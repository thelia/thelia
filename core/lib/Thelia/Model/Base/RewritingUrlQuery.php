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
use Thelia\Model\RewritingUrl as ChildRewritingUrl;
use Thelia\Model\RewritingUrlQuery as ChildRewritingUrlQuery;
use Thelia\Model\Map\RewritingUrlTableMap;

/**
 * Base class that represents a query for the 'rewriting_url' table.
 *
 *
 *
 * @method     ChildRewritingUrlQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildRewritingUrlQuery orderByUrl($order = Criteria::ASC) Order by the url column
 * @method     ChildRewritingUrlQuery orderByView($order = Criteria::ASC) Order by the view column
 * @method     ChildRewritingUrlQuery orderByViewId($order = Criteria::ASC) Order by the view_id column
 * @method     ChildRewritingUrlQuery orderByViewLocale($order = Criteria::ASC) Order by the view_locale column
 * @method     ChildRewritingUrlQuery orderByRedirected($order = Criteria::ASC) Order by the redirected column
 * @method     ChildRewritingUrlQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildRewritingUrlQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildRewritingUrlQuery groupById() Group by the id column
 * @method     ChildRewritingUrlQuery groupByUrl() Group by the url column
 * @method     ChildRewritingUrlQuery groupByView() Group by the view column
 * @method     ChildRewritingUrlQuery groupByViewId() Group by the view_id column
 * @method     ChildRewritingUrlQuery groupByViewLocale() Group by the view_locale column
 * @method     ChildRewritingUrlQuery groupByRedirected() Group by the redirected column
 * @method     ChildRewritingUrlQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildRewritingUrlQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildRewritingUrlQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildRewritingUrlQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildRewritingUrlQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildRewritingUrlQuery leftJoinRewritingUrlRelatedByRedirected($relationAlias = null) Adds a LEFT JOIN clause to the query using the RewritingUrlRelatedByRedirected relation
 * @method     ChildRewritingUrlQuery rightJoinRewritingUrlRelatedByRedirected($relationAlias = null) Adds a RIGHT JOIN clause to the query using the RewritingUrlRelatedByRedirected relation
 * @method     ChildRewritingUrlQuery innerJoinRewritingUrlRelatedByRedirected($relationAlias = null) Adds a INNER JOIN clause to the query using the RewritingUrlRelatedByRedirected relation
 *
 * @method     ChildRewritingUrlQuery leftJoinRewritingUrlRelatedById($relationAlias = null) Adds a LEFT JOIN clause to the query using the RewritingUrlRelatedById relation
 * @method     ChildRewritingUrlQuery rightJoinRewritingUrlRelatedById($relationAlias = null) Adds a RIGHT JOIN clause to the query using the RewritingUrlRelatedById relation
 * @method     ChildRewritingUrlQuery innerJoinRewritingUrlRelatedById($relationAlias = null) Adds a INNER JOIN clause to the query using the RewritingUrlRelatedById relation
 *
 * @method     ChildRewritingUrlQuery leftJoinRewritingArgument($relationAlias = null) Adds a LEFT JOIN clause to the query using the RewritingArgument relation
 * @method     ChildRewritingUrlQuery rightJoinRewritingArgument($relationAlias = null) Adds a RIGHT JOIN clause to the query using the RewritingArgument relation
 * @method     ChildRewritingUrlQuery innerJoinRewritingArgument($relationAlias = null) Adds a INNER JOIN clause to the query using the RewritingArgument relation
 *
 * @method     ChildRewritingUrl findOne(ConnectionInterface $con = null) Return the first ChildRewritingUrl matching the query
 * @method     ChildRewritingUrl findOneOrCreate(ConnectionInterface $con = null) Return the first ChildRewritingUrl matching the query, or a new ChildRewritingUrl object populated from the query conditions when no match is found
 *
 * @method     ChildRewritingUrl findOneById(int $id) Return the first ChildRewritingUrl filtered by the id column
 * @method     ChildRewritingUrl findOneByUrl(string $url) Return the first ChildRewritingUrl filtered by the url column
 * @method     ChildRewritingUrl findOneByView(string $view) Return the first ChildRewritingUrl filtered by the view column
 * @method     ChildRewritingUrl findOneByViewId(string $view_id) Return the first ChildRewritingUrl filtered by the view_id column
 * @method     ChildRewritingUrl findOneByViewLocale(string $view_locale) Return the first ChildRewritingUrl filtered by the view_locale column
 * @method     ChildRewritingUrl findOneByRedirected(int $redirected) Return the first ChildRewritingUrl filtered by the redirected column
 * @method     ChildRewritingUrl findOneByCreatedAt(string $created_at) Return the first ChildRewritingUrl filtered by the created_at column
 * @method     ChildRewritingUrl findOneByUpdatedAt(string $updated_at) Return the first ChildRewritingUrl filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildRewritingUrl objects filtered by the id column
 * @method     array findByUrl(string $url) Return ChildRewritingUrl objects filtered by the url column
 * @method     array findByView(string $view) Return ChildRewritingUrl objects filtered by the view column
 * @method     array findByViewId(string $view_id) Return ChildRewritingUrl objects filtered by the view_id column
 * @method     array findByViewLocale(string $view_locale) Return ChildRewritingUrl objects filtered by the view_locale column
 * @method     array findByRedirected(int $redirected) Return ChildRewritingUrl objects filtered by the redirected column
 * @method     array findByCreatedAt(string $created_at) Return ChildRewritingUrl objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildRewritingUrl objects filtered by the updated_at column
 *
 */
abstract class RewritingUrlQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\RewritingUrlQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\RewritingUrl', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildRewritingUrlQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildRewritingUrlQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\RewritingUrlQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\RewritingUrlQuery();
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
     * @return ChildRewritingUrl|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = RewritingUrlTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(RewritingUrlTableMap::DATABASE_NAME);
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
     * @return   ChildRewritingUrl A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `URL`, `VIEW`, `VIEW_ID`, `VIEW_LOCALE`, `REDIRECTED`, `CREATED_AT`, `UPDATED_AT` FROM `rewriting_url` WHERE `ID` = :p0';
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
            $obj = new ChildRewritingUrl();
            $obj->hydrate($row);
            RewritingUrlTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildRewritingUrl|array|mixed the result, formatted by the current formatter
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
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(RewritingUrlTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(RewritingUrlTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(RewritingUrlTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(RewritingUrlTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RewritingUrlTableMap::ID, $id, $comparison);
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
     * @return ChildRewritingUrlQuery The current query, for fluid interface
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

        return $this->addUsingAlias(RewritingUrlTableMap::URL, $url, $comparison);
    }

    /**
     * Filter the query on the view column
     *
     * Example usage:
     * <code>
     * $query->filterByView('fooValue');   // WHERE view = 'fooValue'
     * $query->filterByView('%fooValue%'); // WHERE view LIKE '%fooValue%'
     * </code>
     *
     * @param     string $view The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function filterByView($view = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($view)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $view)) {
                $view = str_replace('*', '%', $view);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RewritingUrlTableMap::VIEW, $view, $comparison);
    }

    /**
     * Filter the query on the view_id column
     *
     * Example usage:
     * <code>
     * $query->filterByViewId('fooValue');   // WHERE view_id = 'fooValue'
     * $query->filterByViewId('%fooValue%'); // WHERE view_id LIKE '%fooValue%'
     * </code>
     *
     * @param     string $viewId The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function filterByViewId($viewId = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($viewId)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $viewId)) {
                $viewId = str_replace('*', '%', $viewId);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RewritingUrlTableMap::VIEW_ID, $viewId, $comparison);
    }

    /**
     * Filter the query on the view_locale column
     *
     * Example usage:
     * <code>
     * $query->filterByViewLocale('fooValue');   // WHERE view_locale = 'fooValue'
     * $query->filterByViewLocale('%fooValue%'); // WHERE view_locale LIKE '%fooValue%'
     * </code>
     *
     * @param     string $viewLocale The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function filterByViewLocale($viewLocale = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($viewLocale)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $viewLocale)) {
                $viewLocale = str_replace('*', '%', $viewLocale);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(RewritingUrlTableMap::VIEW_LOCALE, $viewLocale, $comparison);
    }

    /**
     * Filter the query on the redirected column
     *
     * Example usage:
     * <code>
     * $query->filterByRedirected(1234); // WHERE redirected = 1234
     * $query->filterByRedirected(array(12, 34)); // WHERE redirected IN (12, 34)
     * $query->filterByRedirected(array('min' => 12)); // WHERE redirected > 12
     * </code>
     *
     * @see       filterByRewritingUrlRelatedByRedirected()
     *
     * @param     mixed $redirected The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function filterByRedirected($redirected = null, $comparison = null)
    {
        if (is_array($redirected)) {
            $useMinMax = false;
            if (isset($redirected['min'])) {
                $this->addUsingAlias(RewritingUrlTableMap::REDIRECTED, $redirected['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($redirected['max'])) {
                $this->addUsingAlias(RewritingUrlTableMap::REDIRECTED, $redirected['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RewritingUrlTableMap::REDIRECTED, $redirected, $comparison);
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
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(RewritingUrlTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(RewritingUrlTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RewritingUrlTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(RewritingUrlTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(RewritingUrlTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(RewritingUrlTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\RewritingUrl object
     *
     * @param \Thelia\Model\RewritingUrl|ObjectCollection $rewritingUrl The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function filterByRewritingUrlRelatedByRedirected($rewritingUrl, $comparison = null)
    {
        if ($rewritingUrl instanceof \Thelia\Model\RewritingUrl) {
            return $this
                ->addUsingAlias(RewritingUrlTableMap::REDIRECTED, $rewritingUrl->getId(), $comparison);
        } elseif ($rewritingUrl instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(RewritingUrlTableMap::REDIRECTED, $rewritingUrl->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByRewritingUrlRelatedByRedirected() only accepts arguments of type \Thelia\Model\RewritingUrl or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the RewritingUrlRelatedByRedirected relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function joinRewritingUrlRelatedByRedirected($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('RewritingUrlRelatedByRedirected');

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
            $this->addJoinObject($join, 'RewritingUrlRelatedByRedirected');
        }

        return $this;
    }

    /**
     * Use the RewritingUrlRelatedByRedirected relation RewritingUrl object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\RewritingUrlQuery A secondary query class using the current class as primary query
     */
    public function useRewritingUrlRelatedByRedirectedQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinRewritingUrlRelatedByRedirected($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'RewritingUrlRelatedByRedirected', '\Thelia\Model\RewritingUrlQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\RewritingUrl object
     *
     * @param \Thelia\Model\RewritingUrl|ObjectCollection $rewritingUrl  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function filterByRewritingUrlRelatedById($rewritingUrl, $comparison = null)
    {
        if ($rewritingUrl instanceof \Thelia\Model\RewritingUrl) {
            return $this
                ->addUsingAlias(RewritingUrlTableMap::ID, $rewritingUrl->getRedirected(), $comparison);
        } elseif ($rewritingUrl instanceof ObjectCollection) {
            return $this
                ->useRewritingUrlRelatedByIdQuery()
                ->filterByPrimaryKeys($rewritingUrl->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByRewritingUrlRelatedById() only accepts arguments of type \Thelia\Model\RewritingUrl or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the RewritingUrlRelatedById relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function joinRewritingUrlRelatedById($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('RewritingUrlRelatedById');

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
            $this->addJoinObject($join, 'RewritingUrlRelatedById');
        }

        return $this;
    }

    /**
     * Use the RewritingUrlRelatedById relation RewritingUrl object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\RewritingUrlQuery A secondary query class using the current class as primary query
     */
    public function useRewritingUrlRelatedByIdQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinRewritingUrlRelatedById($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'RewritingUrlRelatedById', '\Thelia\Model\RewritingUrlQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\RewritingArgument object
     *
     * @param \Thelia\Model\RewritingArgument|ObjectCollection $rewritingArgument  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function filterByRewritingArgument($rewritingArgument, $comparison = null)
    {
        if ($rewritingArgument instanceof \Thelia\Model\RewritingArgument) {
            return $this
                ->addUsingAlias(RewritingUrlTableMap::ID, $rewritingArgument->getRewritingUrlId(), $comparison);
        } elseif ($rewritingArgument instanceof ObjectCollection) {
            return $this
                ->useRewritingArgumentQuery()
                ->filterByPrimaryKeys($rewritingArgument->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByRewritingArgument() only accepts arguments of type \Thelia\Model\RewritingArgument or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the RewritingArgument relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function joinRewritingArgument($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('RewritingArgument');

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
            $this->addJoinObject($join, 'RewritingArgument');
        }

        return $this;
    }

    /**
     * Use the RewritingArgument relation RewritingArgument object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\RewritingArgumentQuery A secondary query class using the current class as primary query
     */
    public function useRewritingArgumentQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinRewritingArgument($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'RewritingArgument', '\Thelia\Model\RewritingArgumentQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildRewritingUrl $rewritingUrl Object to remove from the list of results
     *
     * @return ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function prune($rewritingUrl = null)
    {
        if ($rewritingUrl) {
            $this->addUsingAlias(RewritingUrlTableMap::ID, $rewritingUrl->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the rewriting_url table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(RewritingUrlTableMap::DATABASE_NAME);
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
            RewritingUrlTableMap::clearInstancePool();
            RewritingUrlTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildRewritingUrl or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildRewritingUrl object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(RewritingUrlTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(RewritingUrlTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        RewritingUrlTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            RewritingUrlTableMap::clearRelatedInstancePool();
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
     * @return     ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(RewritingUrlTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(RewritingUrlTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(RewritingUrlTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(RewritingUrlTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(RewritingUrlTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildRewritingUrlQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(RewritingUrlTableMap::CREATED_AT);
    }

} // RewritingUrlQuery
