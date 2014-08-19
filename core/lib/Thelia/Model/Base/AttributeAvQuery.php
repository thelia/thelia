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
use Thelia\Model\AttributeAv as ChildAttributeAv;
use Thelia\Model\AttributeAvI18nQuery as ChildAttributeAvI18nQuery;
use Thelia\Model\AttributeAvQuery as ChildAttributeAvQuery;
use Thelia\Model\Map\AttributeAvTableMap;

/**
 * Base class that represents a query for the 'attribute_av' table.
 *
 *
 *
 * @method     ChildAttributeAvQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildAttributeAvQuery orderByAttributeId($order = Criteria::ASC) Order by the attribute_id column
 * @method     ChildAttributeAvQuery orderByPosition($order = Criteria::ASC) Order by the position column
 * @method     ChildAttributeAvQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildAttributeAvQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildAttributeAvQuery groupById() Group by the id column
 * @method     ChildAttributeAvQuery groupByAttributeId() Group by the attribute_id column
 * @method     ChildAttributeAvQuery groupByPosition() Group by the position column
 * @method     ChildAttributeAvQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildAttributeAvQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildAttributeAvQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildAttributeAvQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildAttributeAvQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildAttributeAvQuery leftJoinAttribute($relationAlias = null) Adds a LEFT JOIN clause to the query using the Attribute relation
 * @method     ChildAttributeAvQuery rightJoinAttribute($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Attribute relation
 * @method     ChildAttributeAvQuery innerJoinAttribute($relationAlias = null) Adds a INNER JOIN clause to the query using the Attribute relation
 *
 * @method     ChildAttributeAvQuery leftJoinAttributeCombination($relationAlias = null) Adds a LEFT JOIN clause to the query using the AttributeCombination relation
 * @method     ChildAttributeAvQuery rightJoinAttributeCombination($relationAlias = null) Adds a RIGHT JOIN clause to the query using the AttributeCombination relation
 * @method     ChildAttributeAvQuery innerJoinAttributeCombination($relationAlias = null) Adds a INNER JOIN clause to the query using the AttributeCombination relation
 *
 * @method     ChildAttributeAvQuery leftJoinSaleProduct($relationAlias = null) Adds a LEFT JOIN clause to the query using the SaleProduct relation
 * @method     ChildAttributeAvQuery rightJoinSaleProduct($relationAlias = null) Adds a RIGHT JOIN clause to the query using the SaleProduct relation
 * @method     ChildAttributeAvQuery innerJoinSaleProduct($relationAlias = null) Adds a INNER JOIN clause to the query using the SaleProduct relation
 *
 * @method     ChildAttributeAvQuery leftJoinAttributeAvI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the AttributeAvI18n relation
 * @method     ChildAttributeAvQuery rightJoinAttributeAvI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the AttributeAvI18n relation
 * @method     ChildAttributeAvQuery innerJoinAttributeAvI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the AttributeAvI18n relation
 *
 * @method     ChildAttributeAv findOne(ConnectionInterface $con = null) Return the first ChildAttributeAv matching the query
 * @method     ChildAttributeAv findOneOrCreate(ConnectionInterface $con = null) Return the first ChildAttributeAv matching the query, or a new ChildAttributeAv object populated from the query conditions when no match is found
 *
 * @method     ChildAttributeAv findOneById(int $id) Return the first ChildAttributeAv filtered by the id column
 * @method     ChildAttributeAv findOneByAttributeId(int $attribute_id) Return the first ChildAttributeAv filtered by the attribute_id column
 * @method     ChildAttributeAv findOneByPosition(int $position) Return the first ChildAttributeAv filtered by the position column
 * @method     ChildAttributeAv findOneByCreatedAt(string $created_at) Return the first ChildAttributeAv filtered by the created_at column
 * @method     ChildAttributeAv findOneByUpdatedAt(string $updated_at) Return the first ChildAttributeAv filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildAttributeAv objects filtered by the id column
 * @method     array findByAttributeId(int $attribute_id) Return ChildAttributeAv objects filtered by the attribute_id column
 * @method     array findByPosition(int $position) Return ChildAttributeAv objects filtered by the position column
 * @method     array findByCreatedAt(string $created_at) Return ChildAttributeAv objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildAttributeAv objects filtered by the updated_at column
 *
 */
abstract class AttributeAvQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\AttributeAvQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\AttributeAv', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildAttributeAvQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildAttributeAvQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\AttributeAvQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\AttributeAvQuery();
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
     * @return ChildAttributeAv|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = AttributeAvTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(AttributeAvTableMap::DATABASE_NAME);
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
     * @return   ChildAttributeAv A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `ATTRIBUTE_ID`, `POSITION`, `CREATED_AT`, `UPDATED_AT` FROM `attribute_av` WHERE `ID` = :p0';
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
            $obj = new ChildAttributeAv();
            $obj->hydrate($row);
            AttributeAvTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildAttributeAv|array|mixed the result, formatted by the current formatter
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
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(AttributeAvTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(AttributeAvTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(AttributeAvTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(AttributeAvTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AttributeAvTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the attribute_id column
     *
     * Example usage:
     * <code>
     * $query->filterByAttributeId(1234); // WHERE attribute_id = 1234
     * $query->filterByAttributeId(array(12, 34)); // WHERE attribute_id IN (12, 34)
     * $query->filterByAttributeId(array('min' => 12)); // WHERE attribute_id > 12
     * </code>
     *
     * @see       filterByAttribute()
     *
     * @param     mixed $attributeId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function filterByAttributeId($attributeId = null, $comparison = null)
    {
        if (is_array($attributeId)) {
            $useMinMax = false;
            if (isset($attributeId['min'])) {
                $this->addUsingAlias(AttributeAvTableMap::ATTRIBUTE_ID, $attributeId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($attributeId['max'])) {
                $this->addUsingAlias(AttributeAvTableMap::ATTRIBUTE_ID, $attributeId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AttributeAvTableMap::ATTRIBUTE_ID, $attributeId, $comparison);
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
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        if (is_array($position)) {
            $useMinMax = false;
            if (isset($position['min'])) {
                $this->addUsingAlias(AttributeAvTableMap::POSITION, $position['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($position['max'])) {
                $this->addUsingAlias(AttributeAvTableMap::POSITION, $position['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AttributeAvTableMap::POSITION, $position, $comparison);
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
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(AttributeAvTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(AttributeAvTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AttributeAvTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(AttributeAvTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(AttributeAvTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AttributeAvTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Attribute object
     *
     * @param \Thelia\Model\Attribute|ObjectCollection $attribute The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function filterByAttribute($attribute, $comparison = null)
    {
        if ($attribute instanceof \Thelia\Model\Attribute) {
            return $this
                ->addUsingAlias(AttributeAvTableMap::ATTRIBUTE_ID, $attribute->getId(), $comparison);
        } elseif ($attribute instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(AttributeAvTableMap::ATTRIBUTE_ID, $attribute->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByAttribute() only accepts arguments of type \Thelia\Model\Attribute or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Attribute relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function joinAttribute($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Attribute');

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
            $this->addJoinObject($join, 'Attribute');
        }

        return $this;
    }

    /**
     * Use the Attribute relation Attribute object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\AttributeQuery A secondary query class using the current class as primary query
     */
    public function useAttributeQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinAttribute($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Attribute', '\Thelia\Model\AttributeQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\AttributeCombination object
     *
     * @param \Thelia\Model\AttributeCombination|ObjectCollection $attributeCombination  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function filterByAttributeCombination($attributeCombination, $comparison = null)
    {
        if ($attributeCombination instanceof \Thelia\Model\AttributeCombination) {
            return $this
                ->addUsingAlias(AttributeAvTableMap::ID, $attributeCombination->getAttributeAvId(), $comparison);
        } elseif ($attributeCombination instanceof ObjectCollection) {
            return $this
                ->useAttributeCombinationQuery()
                ->filterByPrimaryKeys($attributeCombination->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByAttributeCombination() only accepts arguments of type \Thelia\Model\AttributeCombination or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the AttributeCombination relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function joinAttributeCombination($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('AttributeCombination');

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
            $this->addJoinObject($join, 'AttributeCombination');
        }

        return $this;
    }

    /**
     * Use the AttributeCombination relation AttributeCombination object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\AttributeCombinationQuery A secondary query class using the current class as primary query
     */
    public function useAttributeCombinationQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinAttributeCombination($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'AttributeCombination', '\Thelia\Model\AttributeCombinationQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\SaleProduct object
     *
     * @param \Thelia\Model\SaleProduct|ObjectCollection $saleProduct  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function filterBySaleProduct($saleProduct, $comparison = null)
    {
        if ($saleProduct instanceof \Thelia\Model\SaleProduct) {
            return $this
                ->addUsingAlias(AttributeAvTableMap::ID, $saleProduct->getAttributeAvId(), $comparison);
        } elseif ($saleProduct instanceof ObjectCollection) {
            return $this
                ->useSaleProductQuery()
                ->filterByPrimaryKeys($saleProduct->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterBySaleProduct() only accepts arguments of type \Thelia\Model\SaleProduct or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the SaleProduct relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function joinSaleProduct($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('SaleProduct');

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
            $this->addJoinObject($join, 'SaleProduct');
        }

        return $this;
    }

    /**
     * Use the SaleProduct relation SaleProduct object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\SaleProductQuery A secondary query class using the current class as primary query
     */
    public function useSaleProductQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinSaleProduct($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'SaleProduct', '\Thelia\Model\SaleProductQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\AttributeAvI18n object
     *
     * @param \Thelia\Model\AttributeAvI18n|ObjectCollection $attributeAvI18n  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function filterByAttributeAvI18n($attributeAvI18n, $comparison = null)
    {
        if ($attributeAvI18n instanceof \Thelia\Model\AttributeAvI18n) {
            return $this
                ->addUsingAlias(AttributeAvTableMap::ID, $attributeAvI18n->getId(), $comparison);
        } elseif ($attributeAvI18n instanceof ObjectCollection) {
            return $this
                ->useAttributeAvI18nQuery()
                ->filterByPrimaryKeys($attributeAvI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByAttributeAvI18n() only accepts arguments of type \Thelia\Model\AttributeAvI18n or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the AttributeAvI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function joinAttributeAvI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('AttributeAvI18n');

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
            $this->addJoinObject($join, 'AttributeAvI18n');
        }

        return $this;
    }

    /**
     * Use the AttributeAvI18n relation AttributeAvI18n object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\AttributeAvI18nQuery A secondary query class using the current class as primary query
     */
    public function useAttributeAvI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinAttributeAvI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'AttributeAvI18n', '\Thelia\Model\AttributeAvI18nQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildAttributeAv $attributeAv Object to remove from the list of results
     *
     * @return ChildAttributeAvQuery The current query, for fluid interface
     */
    public function prune($attributeAv = null)
    {
        if ($attributeAv) {
            $this->addUsingAlias(AttributeAvTableMap::ID, $attributeAv->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the attribute_av table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(AttributeAvTableMap::DATABASE_NAME);
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
            AttributeAvTableMap::clearInstancePool();
            AttributeAvTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildAttributeAv or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildAttributeAv object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(AttributeAvTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(AttributeAvTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        AttributeAvTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            AttributeAvTableMap::clearRelatedInstancePool();
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
     * @return     ChildAttributeAvQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(AttributeAvTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildAttributeAvQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(AttributeAvTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildAttributeAvQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(AttributeAvTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildAttributeAvQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(AttributeAvTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildAttributeAvQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(AttributeAvTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildAttributeAvQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(AttributeAvTableMap::CREATED_AT);
    }

    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildAttributeAvQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'AttributeAvI18n';

        return $this
            ->joinAttributeAvI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildAttributeAvQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('AttributeAvI18n');
        $this->with['AttributeAvI18n']->setIsWithOneToMany(false);

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
     * @return    ChildAttributeAvI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'AttributeAvI18n', '\Thelia\Model\AttributeAvI18nQuery');
    }

} // AttributeAvQuery
