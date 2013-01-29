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
use Thelia\Model\Attribute;
use Thelia\Model\AttributeAv;
use Thelia\Model\AttributeCombination;
use Thelia\Model\AttributeCombinationPeer;
use Thelia\Model\AttributeCombinationQuery;
use Thelia\Model\Combination;

/**
 * Base class that represents a query for the 'attribute_combination' table.
 *
 *
 *
 * @method AttributeCombinationQuery orderById($order = Criteria::ASC) Order by the id column
 * @method AttributeCombinationQuery orderByAttributeId($order = Criteria::ASC) Order by the attribute_id column
 * @method AttributeCombinationQuery orderByCombinationId($order = Criteria::ASC) Order by the combination_id column
 * @method AttributeCombinationQuery orderByAttributeAvId($order = Criteria::ASC) Order by the attribute_av_id column
 * @method AttributeCombinationQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method AttributeCombinationQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method AttributeCombinationQuery groupById() Group by the id column
 * @method AttributeCombinationQuery groupByAttributeId() Group by the attribute_id column
 * @method AttributeCombinationQuery groupByCombinationId() Group by the combination_id column
 * @method AttributeCombinationQuery groupByAttributeAvId() Group by the attribute_av_id column
 * @method AttributeCombinationQuery groupByCreatedAt() Group by the created_at column
 * @method AttributeCombinationQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method AttributeCombinationQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method AttributeCombinationQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method AttributeCombinationQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method AttributeCombinationQuery leftJoinAttribute($relationAlias = null) Adds a LEFT JOIN clause to the query using the Attribute relation
 * @method AttributeCombinationQuery rightJoinAttribute($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Attribute relation
 * @method AttributeCombinationQuery innerJoinAttribute($relationAlias = null) Adds a INNER JOIN clause to the query using the Attribute relation
 *
 * @method AttributeCombinationQuery leftJoinAttributeAv($relationAlias = null) Adds a LEFT JOIN clause to the query using the AttributeAv relation
 * @method AttributeCombinationQuery rightJoinAttributeAv($relationAlias = null) Adds a RIGHT JOIN clause to the query using the AttributeAv relation
 * @method AttributeCombinationQuery innerJoinAttributeAv($relationAlias = null) Adds a INNER JOIN clause to the query using the AttributeAv relation
 *
 * @method AttributeCombinationQuery leftJoinCombination($relationAlias = null) Adds a LEFT JOIN clause to the query using the Combination relation
 * @method AttributeCombinationQuery rightJoinCombination($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Combination relation
 * @method AttributeCombinationQuery innerJoinCombination($relationAlias = null) Adds a INNER JOIN clause to the query using the Combination relation
 *
 * @method AttributeCombination findOne(PropelPDO $con = null) Return the first AttributeCombination matching the query
 * @method AttributeCombination findOneOrCreate(PropelPDO $con = null) Return the first AttributeCombination matching the query, or a new AttributeCombination object populated from the query conditions when no match is found
 *
 * @method AttributeCombination findOneById(int $id) Return the first AttributeCombination filtered by the id column
 * @method AttributeCombination findOneByAttributeId(int $attribute_id) Return the first AttributeCombination filtered by the attribute_id column
 * @method AttributeCombination findOneByCombinationId(int $combination_id) Return the first AttributeCombination filtered by the combination_id column
 * @method AttributeCombination findOneByAttributeAvId(int $attribute_av_id) Return the first AttributeCombination filtered by the attribute_av_id column
 * @method AttributeCombination findOneByCreatedAt(string $created_at) Return the first AttributeCombination filtered by the created_at column
 * @method AttributeCombination findOneByUpdatedAt(string $updated_at) Return the first AttributeCombination filtered by the updated_at column
 *
 * @method array findById(int $id) Return AttributeCombination objects filtered by the id column
 * @method array findByAttributeId(int $attribute_id) Return AttributeCombination objects filtered by the attribute_id column
 * @method array findByCombinationId(int $combination_id) Return AttributeCombination objects filtered by the combination_id column
 * @method array findByAttributeAvId(int $attribute_av_id) Return AttributeCombination objects filtered by the attribute_av_id column
 * @method array findByCreatedAt(string $created_at) Return AttributeCombination objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return AttributeCombination objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseAttributeCombinationQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseAttributeCombinationQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\AttributeCombination', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new AttributeCombinationQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     AttributeCombinationQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return AttributeCombinationQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof AttributeCombinationQuery) {
            return $criteria;
        }
        $query = new AttributeCombinationQuery();
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
     * $obj = $c->findPk(array(12, 34, 56, 78), $con);
     * </code>
     *
     * @param array $key Primary key to use for the query
                         A Primary key composition: [$id, $attribute_id, $combination_id, $attribute_av_id]
     * @param     PropelPDO $con an optional connection object
     *
     * @return   AttributeCombination|AttributeCombination[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = AttributeCombinationPeer::getInstanceFromPool(serialize(array((string) $key[0], (string) $key[1], (string) $key[2], (string) $key[3]))))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(AttributeCombinationPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return   AttributeCombination A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `ATTRIBUTE_ID`, `COMBINATION_ID`, `ATTRIBUTE_AV_ID`, `CREATED_AT`, `UPDATED_AT` FROM `attribute_combination` WHERE `ID` = :p0 AND `ATTRIBUTE_ID` = :p1 AND `COMBINATION_ID` = :p2 AND `ATTRIBUTE_AV_ID` = :p3';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key[0], PDO::PARAM_INT);
            $stmt->bindValue(':p1', $key[1], PDO::PARAM_INT);
            $stmt->bindValue(':p2', $key[2], PDO::PARAM_INT);
            $stmt->bindValue(':p3', $key[3], PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $obj = new AttributeCombination();
            $obj->hydrate($row);
            AttributeCombinationPeer::addInstanceToPool($obj, serialize(array((string) $key[0], (string) $key[1], (string) $key[2], (string) $key[3])));
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
     * @return AttributeCombination|AttributeCombination[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|AttributeCombination[]|mixed the list of results, formatted by the current formatter
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
     * @return AttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(AttributeCombinationPeer::ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(AttributeCombinationPeer::ATTRIBUTE_ID, $key[1], Criteria::EQUAL);
        $this->addUsingAlias(AttributeCombinationPeer::COMBINATION_ID, $key[2], Criteria::EQUAL);
        $this->addUsingAlias(AttributeCombinationPeer::ATTRIBUTE_AV_ID, $key[3], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return AttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(AttributeCombinationPeer::ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(AttributeCombinationPeer::ATTRIBUTE_ID, $key[1], Criteria::EQUAL);
            $cton0->addAnd($cton1);
            $cton2 = $this->getNewCriterion(AttributeCombinationPeer::COMBINATION_ID, $key[2], Criteria::EQUAL);
            $cton0->addAnd($cton2);
            $cton3 = $this->getNewCriterion(AttributeCombinationPeer::ATTRIBUTE_AV_ID, $key[3], Criteria::EQUAL);
            $cton0->addAnd($cton3);
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
     * $query->filterById(array('min' => 12)); // WHERE id > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return AttributeCombinationQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(AttributeCombinationPeer::ID, $id, $comparison);
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
     * @return AttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByAttributeId($attributeId = null, $comparison = null)
    {
        if (is_array($attributeId) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(AttributeCombinationPeer::ATTRIBUTE_ID, $attributeId, $comparison);
    }

    /**
     * Filter the query on the combination_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCombinationId(1234); // WHERE combination_id = 1234
     * $query->filterByCombinationId(array(12, 34)); // WHERE combination_id IN (12, 34)
     * $query->filterByCombinationId(array('min' => 12)); // WHERE combination_id > 12
     * </code>
     *
     * @see       filterByCombination()
     *
     * @param     mixed $combinationId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return AttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByCombinationId($combinationId = null, $comparison = null)
    {
        if (is_array($combinationId) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(AttributeCombinationPeer::COMBINATION_ID, $combinationId, $comparison);
    }

    /**
     * Filter the query on the attribute_av_id column
     *
     * Example usage:
     * <code>
     * $query->filterByAttributeAvId(1234); // WHERE attribute_av_id = 1234
     * $query->filterByAttributeAvId(array(12, 34)); // WHERE attribute_av_id IN (12, 34)
     * $query->filterByAttributeAvId(array('min' => 12)); // WHERE attribute_av_id > 12
     * </code>
     *
     * @see       filterByAttributeAv()
     *
     * @param     mixed $attributeAvId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return AttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByAttributeAvId($attributeAvId = null, $comparison = null)
    {
        if (is_array($attributeAvId) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(AttributeCombinationPeer::ATTRIBUTE_AV_ID, $attributeAvId, $comparison);
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
     * @return AttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(AttributeCombinationPeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(AttributeCombinationPeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AttributeCombinationPeer::CREATED_AT, $createdAt, $comparison);
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
     * @return AttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(AttributeCombinationPeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(AttributeCombinationPeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(AttributeCombinationPeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related Attribute object
     *
     * @param   Attribute|PropelObjectCollection $attribute The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   AttributeCombinationQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByAttribute($attribute, $comparison = null)
    {
        if ($attribute instanceof Attribute) {
            return $this
                ->addUsingAlias(AttributeCombinationPeer::ATTRIBUTE_ID, $attribute->getId(), $comparison);
        } elseif ($attribute instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(AttributeCombinationPeer::ATTRIBUTE_ID, $attribute->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByAttribute() only accepts arguments of type Attribute or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Attribute relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return AttributeCombinationQuery The current query, for fluid interface
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
     * @see       useQuery()
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
     * Filter the query by a related AttributeAv object
     *
     * @param   AttributeAv|PropelObjectCollection $attributeAv The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   AttributeCombinationQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByAttributeAv($attributeAv, $comparison = null)
    {
        if ($attributeAv instanceof AttributeAv) {
            return $this
                ->addUsingAlias(AttributeCombinationPeer::ATTRIBUTE_AV_ID, $attributeAv->getId(), $comparison);
        } elseif ($attributeAv instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(AttributeCombinationPeer::ATTRIBUTE_AV_ID, $attributeAv->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByAttributeAv() only accepts arguments of type AttributeAv or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the AttributeAv relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return AttributeCombinationQuery The current query, for fluid interface
     */
    public function joinAttributeAv($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('AttributeAv');

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
            $this->addJoinObject($join, 'AttributeAv');
        }

        return $this;
    }

    /**
     * Use the AttributeAv relation AttributeAv object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\AttributeAvQuery A secondary query class using the current class as primary query
     */
    public function useAttributeAvQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinAttributeAv($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'AttributeAv', '\Thelia\Model\AttributeAvQuery');
    }

    /**
     * Filter the query by a related Combination object
     *
     * @param   Combination|PropelObjectCollection $combination The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   AttributeCombinationQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByCombination($combination, $comparison = null)
    {
        if ($combination instanceof Combination) {
            return $this
                ->addUsingAlias(AttributeCombinationPeer::COMBINATION_ID, $combination->getId(), $comparison);
        } elseif ($combination instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(AttributeCombinationPeer::COMBINATION_ID, $combination->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByCombination() only accepts arguments of type Combination or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Combination relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return AttributeCombinationQuery The current query, for fluid interface
     */
    public function joinCombination($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Combination');

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
            $this->addJoinObject($join, 'Combination');
        }

        return $this;
    }

    /**
     * Use the Combination relation Combination object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CombinationQuery A secondary query class using the current class as primary query
     */
    public function useCombinationQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCombination($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Combination', '\Thelia\Model\CombinationQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   AttributeCombination $attributeCombination Object to remove from the list of results
     *
     * @return AttributeCombinationQuery The current query, for fluid interface
     */
    public function prune($attributeCombination = null)
    {
        if ($attributeCombination) {
            $this->addCond('pruneCond0', $this->getAliasedColName(AttributeCombinationPeer::ID), $attributeCombination->getId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(AttributeCombinationPeer::ATTRIBUTE_ID), $attributeCombination->getAttributeId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond2', $this->getAliasedColName(AttributeCombinationPeer::COMBINATION_ID), $attributeCombination->getCombinationId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond3', $this->getAliasedColName(AttributeCombinationPeer::ATTRIBUTE_AV_ID), $attributeCombination->getAttributeAvId(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1', 'pruneCond2', 'pruneCond3'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

    // timestampable behavior

    /**
     * Filter by the latest updated
     *
     * @param      int $nbDays Maximum age of the latest update in days
     *
     * @return     AttributeCombinationQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(AttributeCombinationPeer::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     AttributeCombinationQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(AttributeCombinationPeer::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     AttributeCombinationQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(AttributeCombinationPeer::UPDATED_AT);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     AttributeCombinationQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(AttributeCombinationPeer::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by create date desc
     *
     * @return     AttributeCombinationQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(AttributeCombinationPeer::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     AttributeCombinationQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(AttributeCombinationPeer::CREATED_AT);
    }
}
