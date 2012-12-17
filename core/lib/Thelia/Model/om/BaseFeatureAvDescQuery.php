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
use Thelia\Model\FeatureAv;
use Thelia\Model\FeatureAvDesc;
use Thelia\Model\FeatureAvDescPeer;
use Thelia\Model\FeatureAvDescQuery;

/**
 * Base class that represents a query for the 'feature_av_desc' table.
 *
 *
 *
 * @method FeatureAvDescQuery orderById($order = Criteria::ASC) Order by the id column
 * @method FeatureAvDescQuery orderByFeatureAvId($order = Criteria::ASC) Order by the feature_av_id column
 * @method FeatureAvDescQuery orderByLang($order = Criteria::ASC) Order by the lang column
 * @method FeatureAvDescQuery orderByTitle($order = Criteria::ASC) Order by the title column
 * @method FeatureAvDescQuery orderByDescription($order = Criteria::ASC) Order by the description column
 * @method FeatureAvDescQuery orderByChapo($order = Criteria::ASC) Order by the chapo column
 *
 * @method FeatureAvDescQuery groupById() Group by the id column
 * @method FeatureAvDescQuery groupByFeatureAvId() Group by the feature_av_id column
 * @method FeatureAvDescQuery groupByLang() Group by the lang column
 * @method FeatureAvDescQuery groupByTitle() Group by the title column
 * @method FeatureAvDescQuery groupByDescription() Group by the description column
 * @method FeatureAvDescQuery groupByChapo() Group by the chapo column
 *
 * @method FeatureAvDescQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method FeatureAvDescQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method FeatureAvDescQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method FeatureAvDescQuery leftJoinFeatureAv($relationAlias = null) Adds a LEFT JOIN clause to the query using the FeatureAv relation
 * @method FeatureAvDescQuery rightJoinFeatureAv($relationAlias = null) Adds a RIGHT JOIN clause to the query using the FeatureAv relation
 * @method FeatureAvDescQuery innerJoinFeatureAv($relationAlias = null) Adds a INNER JOIN clause to the query using the FeatureAv relation
 *
 * @method FeatureAvDesc findOne(PropelPDO $con = null) Return the first FeatureAvDesc matching the query
 * @method FeatureAvDesc findOneOrCreate(PropelPDO $con = null) Return the first FeatureAvDesc matching the query, or a new FeatureAvDesc object populated from the query conditions when no match is found
 *
 * @method FeatureAvDesc findOneById(int $id) Return the first FeatureAvDesc filtered by the id column
 * @method FeatureAvDesc findOneByFeatureAvId(int $feature_av_id) Return the first FeatureAvDesc filtered by the feature_av_id column
 * @method FeatureAvDesc findOneByLang(string $lang) Return the first FeatureAvDesc filtered by the lang column
 * @method FeatureAvDesc findOneByTitle(string $title) Return the first FeatureAvDesc filtered by the title column
 * @method FeatureAvDesc findOneByDescription(string $description) Return the first FeatureAvDesc filtered by the description column
 * @method FeatureAvDesc findOneByChapo(string $chapo) Return the first FeatureAvDesc filtered by the chapo column
 *
 * @method array findById(int $id) Return FeatureAvDesc objects filtered by the id column
 * @method array findByFeatureAvId(int $feature_av_id) Return FeatureAvDesc objects filtered by the feature_av_id column
 * @method array findByLang(string $lang) Return FeatureAvDesc objects filtered by the lang column
 * @method array findByTitle(string $title) Return FeatureAvDesc objects filtered by the title column
 * @method array findByDescription(string $description) Return FeatureAvDesc objects filtered by the description column
 * @method array findByChapo(string $chapo) Return FeatureAvDesc objects filtered by the chapo column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseFeatureAvDescQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseFeatureAvDescQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'mydb', $modelName = 'Thelia\\Model\\FeatureAvDesc', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new FeatureAvDescQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     FeatureAvDescQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return FeatureAvDescQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof FeatureAvDescQuery) {
            return $criteria;
        }
        $query = new FeatureAvDescQuery();
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
     * @return   FeatureAvDesc|FeatureAvDesc[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = FeatureAvDescPeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(FeatureAvDescPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return   FeatureAvDesc A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `FEATURE_AV_ID`, `LANG`, `TITLE`, `DESCRIPTION`, `CHAPO` FROM `feature_av_desc` WHERE `ID` = :p0';
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
            $obj = new FeatureAvDesc();
            $obj->hydrate($row);
            FeatureAvDescPeer::addInstanceToPool($obj, (string) $key);
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
     * @return FeatureAvDesc|FeatureAvDesc[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|FeatureAvDesc[]|mixed the list of results, formatted by the current formatter
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
     * @return FeatureAvDescQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(FeatureAvDescPeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return FeatureAvDescQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(FeatureAvDescPeer::ID, $keys, Criteria::IN);
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
     * @return FeatureAvDescQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(FeatureAvDescPeer::ID, $id, $comparison);
    }

    /**
     * Filter the query on the feature_av_id column
     *
     * Example usage:
     * <code>
     * $query->filterByFeatureAvId(1234); // WHERE feature_av_id = 1234
     * $query->filterByFeatureAvId(array(12, 34)); // WHERE feature_av_id IN (12, 34)
     * $query->filterByFeatureAvId(array('min' => 12)); // WHERE feature_av_id > 12
     * </code>
     *
     * @param     mixed $featureAvId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return FeatureAvDescQuery The current query, for fluid interface
     */
    public function filterByFeatureAvId($featureAvId = null, $comparison = null)
    {
        if (is_array($featureAvId)) {
            $useMinMax = false;
            if (isset($featureAvId['min'])) {
                $this->addUsingAlias(FeatureAvDescPeer::FEATURE_AV_ID, $featureAvId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($featureAvId['max'])) {
                $this->addUsingAlias(FeatureAvDescPeer::FEATURE_AV_ID, $featureAvId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(FeatureAvDescPeer::FEATURE_AV_ID, $featureAvId, $comparison);
    }

    /**
     * Filter the query on the lang column
     *
     * Example usage:
     * <code>
     * $query->filterByLang('fooValue');   // WHERE lang = 'fooValue'
     * $query->filterByLang('%fooValue%'); // WHERE lang LIKE '%fooValue%'
     * </code>
     *
     * @param     string $lang The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return FeatureAvDescQuery The current query, for fluid interface
     */
    public function filterByLang($lang = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($lang)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $lang)) {
                $lang = str_replace('*', '%', $lang);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(FeatureAvDescPeer::LANG, $lang, $comparison);
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
     * @return FeatureAvDescQuery The current query, for fluid interface
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

        return $this->addUsingAlias(FeatureAvDescPeer::TITLE, $title, $comparison);
    }

    /**
     * Filter the query on the description column
     *
     * Example usage:
     * <code>
     * $query->filterByDescription('fooValue');   // WHERE description = 'fooValue'
     * $query->filterByDescription('%fooValue%'); // WHERE description LIKE '%fooValue%'
     * </code>
     *
     * @param     string $description The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return FeatureAvDescQuery The current query, for fluid interface
     */
    public function filterByDescription($description = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($description)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $description)) {
                $description = str_replace('*', '%', $description);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(FeatureAvDescPeer::DESCRIPTION, $description, $comparison);
    }

    /**
     * Filter the query on the chapo column
     *
     * Example usage:
     * <code>
     * $query->filterByChapo('fooValue');   // WHERE chapo = 'fooValue'
     * $query->filterByChapo('%fooValue%'); // WHERE chapo LIKE '%fooValue%'
     * </code>
     *
     * @param     string $chapo The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return FeatureAvDescQuery The current query, for fluid interface
     */
    public function filterByChapo($chapo = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($chapo)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $chapo)) {
                $chapo = str_replace('*', '%', $chapo);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(FeatureAvDescPeer::CHAPO, $chapo, $comparison);
    }

    /**
     * Filter the query by a related FeatureAv object
     *
     * @param   FeatureAv|PropelObjectCollection $featureAv  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   FeatureAvDescQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByFeatureAv($featureAv, $comparison = null)
    {
        if ($featureAv instanceof FeatureAv) {
            return $this
                ->addUsingAlias(FeatureAvDescPeer::FEATURE_AV_ID, $featureAv->getId(), $comparison);
        } elseif ($featureAv instanceof PropelObjectCollection) {
            return $this
                ->useFeatureAvQuery()
                ->filterByPrimaryKeys($featureAv->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByFeatureAv() only accepts arguments of type FeatureAv or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the FeatureAv relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return FeatureAvDescQuery The current query, for fluid interface
     */
    public function joinFeatureAv($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('FeatureAv');

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
            $this->addJoinObject($join, 'FeatureAv');
        }

        return $this;
    }

    /**
     * Use the FeatureAv relation FeatureAv object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\FeatureAvQuery A secondary query class using the current class as primary query
     */
    public function useFeatureAvQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinFeatureAv($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'FeatureAv', '\Thelia\Model\FeatureAvQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   FeatureAvDesc $featureAvDesc Object to remove from the list of results
     *
     * @return FeatureAvDescQuery The current query, for fluid interface
     */
    public function prune($featureAvDesc = null)
    {
        if ($featureAvDesc) {
            $this->addUsingAlias(FeatureAvDescPeer::ID, $featureAvDesc->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

}
