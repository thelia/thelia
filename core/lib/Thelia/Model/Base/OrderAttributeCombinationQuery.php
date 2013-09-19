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
use Thelia\Model\OrderAttributeCombination as ChildOrderAttributeCombination;
use Thelia\Model\OrderAttributeCombinationQuery as ChildOrderAttributeCombinationQuery;
use Thelia\Model\Map\OrderAttributeCombinationTableMap;

/**
 * Base class that represents a query for the 'order_attribute_combination' table.
 *
 *
 *
 * @method     ChildOrderAttributeCombinationQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildOrderAttributeCombinationQuery orderByOrderProductId($order = Criteria::ASC) Order by the order_product_id column
 * @method     ChildOrderAttributeCombinationQuery orderByAttributeTitle($order = Criteria::ASC) Order by the attribute_title column
 * @method     ChildOrderAttributeCombinationQuery orderByAttributeChapo($order = Criteria::ASC) Order by the attribute_chapo column
 * @method     ChildOrderAttributeCombinationQuery orderByAttributeDescription($order = Criteria::ASC) Order by the attribute_description column
 * @method     ChildOrderAttributeCombinationQuery orderByAttributePostscriptumn($order = Criteria::ASC) Order by the attribute_postscriptumn column
 * @method     ChildOrderAttributeCombinationQuery orderByAttributeAvTitle($order = Criteria::ASC) Order by the attribute_av_title column
 * @method     ChildOrderAttributeCombinationQuery orderByAttributeAvChapo($order = Criteria::ASC) Order by the attribute_av_chapo column
 * @method     ChildOrderAttributeCombinationQuery orderByAttributeAvDescription($order = Criteria::ASC) Order by the attribute_av_description column
 * @method     ChildOrderAttributeCombinationQuery orderByAttributeAvPostscriptum($order = Criteria::ASC) Order by the attribute_av_postscriptum column
 * @method     ChildOrderAttributeCombinationQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildOrderAttributeCombinationQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildOrderAttributeCombinationQuery groupById() Group by the id column
 * @method     ChildOrderAttributeCombinationQuery groupByOrderProductId() Group by the order_product_id column
 * @method     ChildOrderAttributeCombinationQuery groupByAttributeTitle() Group by the attribute_title column
 * @method     ChildOrderAttributeCombinationQuery groupByAttributeChapo() Group by the attribute_chapo column
 * @method     ChildOrderAttributeCombinationQuery groupByAttributeDescription() Group by the attribute_description column
 * @method     ChildOrderAttributeCombinationQuery groupByAttributePostscriptumn() Group by the attribute_postscriptumn column
 * @method     ChildOrderAttributeCombinationQuery groupByAttributeAvTitle() Group by the attribute_av_title column
 * @method     ChildOrderAttributeCombinationQuery groupByAttributeAvChapo() Group by the attribute_av_chapo column
 * @method     ChildOrderAttributeCombinationQuery groupByAttributeAvDescription() Group by the attribute_av_description column
 * @method     ChildOrderAttributeCombinationQuery groupByAttributeAvPostscriptum() Group by the attribute_av_postscriptum column
 * @method     ChildOrderAttributeCombinationQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildOrderAttributeCombinationQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildOrderAttributeCombinationQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildOrderAttributeCombinationQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildOrderAttributeCombinationQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildOrderAttributeCombinationQuery leftJoinOrderProduct($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderProduct relation
 * @method     ChildOrderAttributeCombinationQuery rightJoinOrderProduct($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderProduct relation
 * @method     ChildOrderAttributeCombinationQuery innerJoinOrderProduct($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderProduct relation
 *
 * @method     ChildOrderAttributeCombination findOne(ConnectionInterface $con = null) Return the first ChildOrderAttributeCombination matching the query
 * @method     ChildOrderAttributeCombination findOneOrCreate(ConnectionInterface $con = null) Return the first ChildOrderAttributeCombination matching the query, or a new ChildOrderAttributeCombination object populated from the query conditions when no match is found
 *
 * @method     ChildOrderAttributeCombination findOneById(int $id) Return the first ChildOrderAttributeCombination filtered by the id column
 * @method     ChildOrderAttributeCombination findOneByOrderProductId(int $order_product_id) Return the first ChildOrderAttributeCombination filtered by the order_product_id column
 * @method     ChildOrderAttributeCombination findOneByAttributeTitle(string $attribute_title) Return the first ChildOrderAttributeCombination filtered by the attribute_title column
 * @method     ChildOrderAttributeCombination findOneByAttributeChapo(string $attribute_chapo) Return the first ChildOrderAttributeCombination filtered by the attribute_chapo column
 * @method     ChildOrderAttributeCombination findOneByAttributeDescription(string $attribute_description) Return the first ChildOrderAttributeCombination filtered by the attribute_description column
 * @method     ChildOrderAttributeCombination findOneByAttributePostscriptumn(string $attribute_postscriptumn) Return the first ChildOrderAttributeCombination filtered by the attribute_postscriptumn column
 * @method     ChildOrderAttributeCombination findOneByAttributeAvTitle(string $attribute_av_title) Return the first ChildOrderAttributeCombination filtered by the attribute_av_title column
 * @method     ChildOrderAttributeCombination findOneByAttributeAvChapo(string $attribute_av_chapo) Return the first ChildOrderAttributeCombination filtered by the attribute_av_chapo column
 * @method     ChildOrderAttributeCombination findOneByAttributeAvDescription(string $attribute_av_description) Return the first ChildOrderAttributeCombination filtered by the attribute_av_description column
 * @method     ChildOrderAttributeCombination findOneByAttributeAvPostscriptum(string $attribute_av_postscriptum) Return the first ChildOrderAttributeCombination filtered by the attribute_av_postscriptum column
 * @method     ChildOrderAttributeCombination findOneByCreatedAt(string $created_at) Return the first ChildOrderAttributeCombination filtered by the created_at column
 * @method     ChildOrderAttributeCombination findOneByUpdatedAt(string $updated_at) Return the first ChildOrderAttributeCombination filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildOrderAttributeCombination objects filtered by the id column
 * @method     array findByOrderProductId(int $order_product_id) Return ChildOrderAttributeCombination objects filtered by the order_product_id column
 * @method     array findByAttributeTitle(string $attribute_title) Return ChildOrderAttributeCombination objects filtered by the attribute_title column
 * @method     array findByAttributeChapo(string $attribute_chapo) Return ChildOrderAttributeCombination objects filtered by the attribute_chapo column
 * @method     array findByAttributeDescription(string $attribute_description) Return ChildOrderAttributeCombination objects filtered by the attribute_description column
 * @method     array findByAttributePostscriptumn(string $attribute_postscriptumn) Return ChildOrderAttributeCombination objects filtered by the attribute_postscriptumn column
 * @method     array findByAttributeAvTitle(string $attribute_av_title) Return ChildOrderAttributeCombination objects filtered by the attribute_av_title column
 * @method     array findByAttributeAvChapo(string $attribute_av_chapo) Return ChildOrderAttributeCombination objects filtered by the attribute_av_chapo column
 * @method     array findByAttributeAvDescription(string $attribute_av_description) Return ChildOrderAttributeCombination objects filtered by the attribute_av_description column
 * @method     array findByAttributeAvPostscriptum(string $attribute_av_postscriptum) Return ChildOrderAttributeCombination objects filtered by the attribute_av_postscriptum column
 * @method     array findByCreatedAt(string $created_at) Return ChildOrderAttributeCombination objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildOrderAttributeCombination objects filtered by the updated_at column
 *
 */
abstract class OrderAttributeCombinationQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\OrderAttributeCombinationQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\OrderAttributeCombination', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildOrderAttributeCombinationQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildOrderAttributeCombinationQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\OrderAttributeCombinationQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\OrderAttributeCombinationQuery();
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
     * @return ChildOrderAttributeCombination|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = OrderAttributeCombinationTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(OrderAttributeCombinationTableMap::DATABASE_NAME);
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
     * @return   ChildOrderAttributeCombination A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT ID, ORDER_PRODUCT_ID, ATTRIBUTE_TITLE, ATTRIBUTE_CHAPO, ATTRIBUTE_DESCRIPTION, ATTRIBUTE_POSTSCRIPTUMN, ATTRIBUTE_AV_TITLE, ATTRIBUTE_AV_CHAPO, ATTRIBUTE_AV_DESCRIPTION, ATTRIBUTE_AV_POSTSCRIPTUM, CREATED_AT, UPDATED_AT FROM order_attribute_combination WHERE ID = :p0';
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
            $obj = new ChildOrderAttributeCombination();
            $obj->hydrate($row);
            OrderAttributeCombinationTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildOrderAttributeCombination|array|mixed the result, formatted by the current formatter
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
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(OrderAttributeCombinationTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(OrderAttributeCombinationTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(OrderAttributeCombinationTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(OrderAttributeCombinationTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderAttributeCombinationTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the order_product_id column
     *
     * Example usage:
     * <code>
     * $query->filterByOrderProductId(1234); // WHERE order_product_id = 1234
     * $query->filterByOrderProductId(array(12, 34)); // WHERE order_product_id IN (12, 34)
     * $query->filterByOrderProductId(array('min' => 12)); // WHERE order_product_id > 12
     * </code>
     *
     * @see       filterByOrderProduct()
     *
     * @param     mixed $orderProductId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByOrderProductId($orderProductId = null, $comparison = null)
    {
        if (is_array($orderProductId)) {
            $useMinMax = false;
            if (isset($orderProductId['min'])) {
                $this->addUsingAlias(OrderAttributeCombinationTableMap::ORDER_PRODUCT_ID, $orderProductId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($orderProductId['max'])) {
                $this->addUsingAlias(OrderAttributeCombinationTableMap::ORDER_PRODUCT_ID, $orderProductId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderAttributeCombinationTableMap::ORDER_PRODUCT_ID, $orderProductId, $comparison);
    }

    /**
     * Filter the query on the attribute_title column
     *
     * Example usage:
     * <code>
     * $query->filterByAttributeTitle('fooValue');   // WHERE attribute_title = 'fooValue'
     * $query->filterByAttributeTitle('%fooValue%'); // WHERE attribute_title LIKE '%fooValue%'
     * </code>
     *
     * @param     string $attributeTitle The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByAttributeTitle($attributeTitle = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($attributeTitle)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $attributeTitle)) {
                $attributeTitle = str_replace('*', '%', $attributeTitle);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAttributeCombinationTableMap::ATTRIBUTE_TITLE, $attributeTitle, $comparison);
    }

    /**
     * Filter the query on the attribute_chapo column
     *
     * Example usage:
     * <code>
     * $query->filterByAttributeChapo('fooValue');   // WHERE attribute_chapo = 'fooValue'
     * $query->filterByAttributeChapo('%fooValue%'); // WHERE attribute_chapo LIKE '%fooValue%'
     * </code>
     *
     * @param     string $attributeChapo The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByAttributeChapo($attributeChapo = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($attributeChapo)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $attributeChapo)) {
                $attributeChapo = str_replace('*', '%', $attributeChapo);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAttributeCombinationTableMap::ATTRIBUTE_CHAPO, $attributeChapo, $comparison);
    }

    /**
     * Filter the query on the attribute_description column
     *
     * Example usage:
     * <code>
     * $query->filterByAttributeDescription('fooValue');   // WHERE attribute_description = 'fooValue'
     * $query->filterByAttributeDescription('%fooValue%'); // WHERE attribute_description LIKE '%fooValue%'
     * </code>
     *
     * @param     string $attributeDescription The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByAttributeDescription($attributeDescription = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($attributeDescription)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $attributeDescription)) {
                $attributeDescription = str_replace('*', '%', $attributeDescription);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAttributeCombinationTableMap::ATTRIBUTE_DESCRIPTION, $attributeDescription, $comparison);
    }

    /**
     * Filter the query on the attribute_postscriptumn column
     *
     * Example usage:
     * <code>
     * $query->filterByAttributePostscriptumn('fooValue');   // WHERE attribute_postscriptumn = 'fooValue'
     * $query->filterByAttributePostscriptumn('%fooValue%'); // WHERE attribute_postscriptumn LIKE '%fooValue%'
     * </code>
     *
     * @param     string $attributePostscriptumn The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByAttributePostscriptumn($attributePostscriptumn = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($attributePostscriptumn)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $attributePostscriptumn)) {
                $attributePostscriptumn = str_replace('*', '%', $attributePostscriptumn);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAttributeCombinationTableMap::ATTRIBUTE_POSTSCRIPTUMN, $attributePostscriptumn, $comparison);
    }

    /**
     * Filter the query on the attribute_av_title column
     *
     * Example usage:
     * <code>
     * $query->filterByAttributeAvTitle('fooValue');   // WHERE attribute_av_title = 'fooValue'
     * $query->filterByAttributeAvTitle('%fooValue%'); // WHERE attribute_av_title LIKE '%fooValue%'
     * </code>
     *
     * @param     string $attributeAvTitle The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByAttributeAvTitle($attributeAvTitle = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($attributeAvTitle)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $attributeAvTitle)) {
                $attributeAvTitle = str_replace('*', '%', $attributeAvTitle);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAttributeCombinationTableMap::ATTRIBUTE_AV_TITLE, $attributeAvTitle, $comparison);
    }

    /**
     * Filter the query on the attribute_av_chapo column
     *
     * Example usage:
     * <code>
     * $query->filterByAttributeAvChapo('fooValue');   // WHERE attribute_av_chapo = 'fooValue'
     * $query->filterByAttributeAvChapo('%fooValue%'); // WHERE attribute_av_chapo LIKE '%fooValue%'
     * </code>
     *
     * @param     string $attributeAvChapo The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByAttributeAvChapo($attributeAvChapo = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($attributeAvChapo)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $attributeAvChapo)) {
                $attributeAvChapo = str_replace('*', '%', $attributeAvChapo);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAttributeCombinationTableMap::ATTRIBUTE_AV_CHAPO, $attributeAvChapo, $comparison);
    }

    /**
     * Filter the query on the attribute_av_description column
     *
     * Example usage:
     * <code>
     * $query->filterByAttributeAvDescription('fooValue');   // WHERE attribute_av_description = 'fooValue'
     * $query->filterByAttributeAvDescription('%fooValue%'); // WHERE attribute_av_description LIKE '%fooValue%'
     * </code>
     *
     * @param     string $attributeAvDescription The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByAttributeAvDescription($attributeAvDescription = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($attributeAvDescription)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $attributeAvDescription)) {
                $attributeAvDescription = str_replace('*', '%', $attributeAvDescription);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAttributeCombinationTableMap::ATTRIBUTE_AV_DESCRIPTION, $attributeAvDescription, $comparison);
    }

    /**
     * Filter the query on the attribute_av_postscriptum column
     *
     * Example usage:
     * <code>
     * $query->filterByAttributeAvPostscriptum('fooValue');   // WHERE attribute_av_postscriptum = 'fooValue'
     * $query->filterByAttributeAvPostscriptum('%fooValue%'); // WHERE attribute_av_postscriptum LIKE '%fooValue%'
     * </code>
     *
     * @param     string $attributeAvPostscriptum The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByAttributeAvPostscriptum($attributeAvPostscriptum = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($attributeAvPostscriptum)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $attributeAvPostscriptum)) {
                $attributeAvPostscriptum = str_replace('*', '%', $attributeAvPostscriptum);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderAttributeCombinationTableMap::ATTRIBUTE_AV_POSTSCRIPTUM, $attributeAvPostscriptum, $comparison);
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
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(OrderAttributeCombinationTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(OrderAttributeCombinationTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderAttributeCombinationTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(OrderAttributeCombinationTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(OrderAttributeCombinationTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderAttributeCombinationTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\OrderProduct object
     *
     * @param \Thelia\Model\OrderProduct|ObjectCollection $orderProduct The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function filterByOrderProduct($orderProduct, $comparison = null)
    {
        if ($orderProduct instanceof \Thelia\Model\OrderProduct) {
            return $this
                ->addUsingAlias(OrderAttributeCombinationTableMap::ORDER_PRODUCT_ID, $orderProduct->getId(), $comparison);
        } elseif ($orderProduct instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderAttributeCombinationTableMap::ORDER_PRODUCT_ID, $orderProduct->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByOrderProduct() only accepts arguments of type \Thelia\Model\OrderProduct or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderProduct relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function joinOrderProduct($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderProduct');

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
            $this->addJoinObject($join, 'OrderProduct');
        }

        return $this;
    }

    /**
     * Use the OrderProduct relation OrderProduct object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderProductQuery A secondary query class using the current class as primary query
     */
    public function useOrderProductQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderProduct($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderProduct', '\Thelia\Model\OrderProductQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildOrderAttributeCombination $orderAttributeCombination Object to remove from the list of results
     *
     * @return ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function prune($orderAttributeCombination = null)
    {
        if ($orderAttributeCombination) {
            $this->addUsingAlias(OrderAttributeCombinationTableMap::ID, $orderAttributeCombination->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the order_attribute_combination table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderAttributeCombinationTableMap::DATABASE_NAME);
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
            OrderAttributeCombinationTableMap::clearInstancePool();
            OrderAttributeCombinationTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildOrderAttributeCombination or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildOrderAttributeCombination object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(OrderAttributeCombinationTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(OrderAttributeCombinationTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        OrderAttributeCombinationTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            OrderAttributeCombinationTableMap::clearRelatedInstancePool();
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
     * @return     ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(OrderAttributeCombinationTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(OrderAttributeCombinationTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(OrderAttributeCombinationTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(OrderAttributeCombinationTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(OrderAttributeCombinationTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildOrderAttributeCombinationQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(OrderAttributeCombinationTableMap::CREATED_AT);
    }

} // OrderAttributeCombinationQuery
