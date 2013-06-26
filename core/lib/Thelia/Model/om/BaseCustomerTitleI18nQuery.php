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
use Thelia\Model\CustomerTitle;
use Thelia\Model\CustomerTitleI18n;
use Thelia\Model\CustomerTitleI18nPeer;
use Thelia\Model\CustomerTitleI18nQuery;

/**
 * Base class that represents a query for the 'customer_title_i18n' table.
 *
 *
 *
 * @method CustomerTitleI18nQuery orderById($order = Criteria::ASC) Order by the id column
 * @method CustomerTitleI18nQuery orderByLocale($order = Criteria::ASC) Order by the locale column
 * @method CustomerTitleI18nQuery orderByShort($order = Criteria::ASC) Order by the short column
 * @method CustomerTitleI18nQuery orderByLong($order = Criteria::ASC) Order by the long column
 *
 * @method CustomerTitleI18nQuery groupById() Group by the id column
 * @method CustomerTitleI18nQuery groupByLocale() Group by the locale column
 * @method CustomerTitleI18nQuery groupByShort() Group by the short column
 * @method CustomerTitleI18nQuery groupByLong() Group by the long column
 *
 * @method CustomerTitleI18nQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method CustomerTitleI18nQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method CustomerTitleI18nQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method CustomerTitleI18nQuery leftJoinCustomerTitle($relationAlias = null) Adds a LEFT JOIN clause to the query using the CustomerTitle relation
 * @method CustomerTitleI18nQuery rightJoinCustomerTitle($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CustomerTitle relation
 * @method CustomerTitleI18nQuery innerJoinCustomerTitle($relationAlias = null) Adds a INNER JOIN clause to the query using the CustomerTitle relation
 *
 * @method CustomerTitleI18n findOne(PropelPDO $con = null) Return the first CustomerTitleI18n matching the query
 * @method CustomerTitleI18n findOneOrCreate(PropelPDO $con = null) Return the first CustomerTitleI18n matching the query, or a new CustomerTitleI18n object populated from the query conditions when no match is found
 *
 * @method CustomerTitleI18n findOneById(int $id) Return the first CustomerTitleI18n filtered by the id column
 * @method CustomerTitleI18n findOneByLocale(string $locale) Return the first CustomerTitleI18n filtered by the locale column
 * @method CustomerTitleI18n findOneByShort(string $short) Return the first CustomerTitleI18n filtered by the short column
 * @method CustomerTitleI18n findOneByLong(string $long) Return the first CustomerTitleI18n filtered by the long column
 *
 * @method array findById(int $id) Return CustomerTitleI18n objects filtered by the id column
 * @method array findByLocale(string $locale) Return CustomerTitleI18n objects filtered by the locale column
 * @method array findByShort(string $short) Return CustomerTitleI18n objects filtered by the short column
 * @method array findByLong(string $long) Return CustomerTitleI18n objects filtered by the long column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseCustomerTitleI18nQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseCustomerTitleI18nQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\CustomerTitleI18n', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new CustomerTitleI18nQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param   CustomerTitleI18nQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return CustomerTitleI18nQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof CustomerTitleI18nQuery) {
            return $criteria;
        }
        $query = new CustomerTitleI18nQuery();
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
     * @param array $key Primary key to use for the query
                         A Primary key composition: [$id, $locale]
     * @param     PropelPDO $con an optional connection object
     *
     * @return   CustomerTitleI18n|CustomerTitleI18n[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = CustomerTitleI18nPeer::getInstanceFromPool(serialize(array((string) $key[0], (string) $key[1]))))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(CustomerTitleI18nPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return                 CustomerTitleI18n A model object, or null if the key is not found
     * @throws PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `id`, `locale`, `short`, `long` FROM `customer_title_i18n` WHERE `id` = :p0 AND `locale` = :p1';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key[0], PDO::PARAM_INT);
            $stmt->bindValue(':p1', $key[1], PDO::PARAM_STR);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $obj = new CustomerTitleI18n();
            $obj->hydrate($row);
            CustomerTitleI18nPeer::addInstanceToPool($obj, serialize(array((string) $key[0], (string) $key[1])));
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
     * @return CustomerTitleI18n|CustomerTitleI18n[]|mixed the result, formatted by the current formatter
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
     * $objs = $c->findPks(array(array(12, 56), array(832, 123), array(123, 456)), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     PropelPDO $con an optional connection object
     *
     * @return PropelObjectCollection|CustomerTitleI18n[]|mixed the list of results, formatted by the current formatter
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
     * @return CustomerTitleI18nQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(CustomerTitleI18nPeer::ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(CustomerTitleI18nPeer::LOCALE, $key[1], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return CustomerTitleI18nQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(CustomerTitleI18nPeer::ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(CustomerTitleI18nPeer::LOCALE, $key[1], Criteria::EQUAL);
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
     * $query->filterById(array('min' => 12)); // WHERE id >= 12
     * $query->filterById(array('max' => 12)); // WHERE id <= 12
     * </code>
     *
     * @see       filterByCustomerTitle()
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CustomerTitleI18nQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(CustomerTitleI18nPeer::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(CustomerTitleI18nPeer::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(CustomerTitleI18nPeer::ID, $id, $comparison);
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
     * @return CustomerTitleI18nQuery The current query, for fluid interface
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

        return $this->addUsingAlias(CustomerTitleI18nPeer::LOCALE, $locale, $comparison);
    }

    /**
     * Filter the query on the short column
     *
     * Example usage:
     * <code>
     * $query->filterByShort('fooValue');   // WHERE short = 'fooValue'
     * $query->filterByShort('%fooValue%'); // WHERE short LIKE '%fooValue%'
     * </code>
     *
     * @param     string $short The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CustomerTitleI18nQuery The current query, for fluid interface
     */
    public function filterByShort($short = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($short)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $short)) {
                $short = str_replace('*', '%', $short);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CustomerTitleI18nPeer::SHORT, $short, $comparison);
    }

    /**
     * Filter the query on the long column
     *
     * Example usage:
     * <code>
     * $query->filterByLong('fooValue');   // WHERE long = 'fooValue'
     * $query->filterByLong('%fooValue%'); // WHERE long LIKE '%fooValue%'
     * </code>
     *
     * @param     string $long The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return CustomerTitleI18nQuery The current query, for fluid interface
     */
    public function filterByLong($long = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($long)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $long)) {
                $long = str_replace('*', '%', $long);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(CustomerTitleI18nPeer::LONG, $long, $comparison);
    }

    /**
     * Filter the query by a related CustomerTitle object
     *
     * @param   CustomerTitle|PropelObjectCollection $customerTitle The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return                 CustomerTitleI18nQuery The current query, for fluid interface
     * @throws PropelException - if the provided filter is invalid.
     */
    public function filterByCustomerTitle($customerTitle, $comparison = null)
    {
        if ($customerTitle instanceof CustomerTitle) {
            return $this
                ->addUsingAlias(CustomerTitleI18nPeer::ID, $customerTitle->getId(), $comparison);
        } elseif ($customerTitle instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(CustomerTitleI18nPeer::ID, $customerTitle->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByCustomerTitle() only accepts arguments of type CustomerTitle or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CustomerTitle relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return CustomerTitleI18nQuery The current query, for fluid interface
     */
    public function joinCustomerTitle($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CustomerTitle');

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
            $this->addJoinObject($join, 'CustomerTitle');
        }

        return $this;
    }

    /**
     * Use the CustomerTitle relation CustomerTitle object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CustomerTitleQuery A secondary query class using the current class as primary query
     */
    public function useCustomerTitleQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinCustomerTitle($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CustomerTitle', '\Thelia\Model\CustomerTitleQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   CustomerTitleI18n $customerTitleI18n Object to remove from the list of results
     *
     * @return CustomerTitleI18nQuery The current query, for fluid interface
     */
    public function prune($customerTitleI18n = null)
    {
        if ($customerTitleI18n) {
            $this->addCond('pruneCond0', $this->getAliasedColName(CustomerTitleI18nPeer::ID), $customerTitleI18n->getId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(CustomerTitleI18nPeer::LOCALE), $customerTitleI18n->getLocale(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

}
