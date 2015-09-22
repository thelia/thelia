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
use Thelia\Model\ProductPrice as ChildProductPrice;
use Thelia\Model\ProductPriceQuery as ChildProductPriceQuery;
use Thelia\Model\Map\ProductPriceTableMap;

/**
 * Base class that represents a query for the 'product_price' table.
 *
 *
 *
 * @method     ChildProductPriceQuery orderByProductSaleElementsId($order = Criteria::ASC) Order by the product_sale_elements_id column
 * @method     ChildProductPriceQuery orderByCurrencyId($order = Criteria::ASC) Order by the currency_id column
 * @method     ChildProductPriceQuery orderByPrice($order = Criteria::ASC) Order by the price column
 * @method     ChildProductPriceQuery orderByPromoPrice($order = Criteria::ASC) Order by the promo_price column
 * @method     ChildProductPriceQuery orderByFromDefaultCurrency($order = Criteria::ASC) Order by the from_default_currency column
 * @method     ChildProductPriceQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildProductPriceQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildProductPriceQuery groupByProductSaleElementsId() Group by the product_sale_elements_id column
 * @method     ChildProductPriceQuery groupByCurrencyId() Group by the currency_id column
 * @method     ChildProductPriceQuery groupByPrice() Group by the price column
 * @method     ChildProductPriceQuery groupByPromoPrice() Group by the promo_price column
 * @method     ChildProductPriceQuery groupByFromDefaultCurrency() Group by the from_default_currency column
 * @method     ChildProductPriceQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildProductPriceQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildProductPriceQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildProductPriceQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildProductPriceQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildProductPriceQuery leftJoinProductSaleElements($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProductSaleElements relation
 * @method     ChildProductPriceQuery rightJoinProductSaleElements($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProductSaleElements relation
 * @method     ChildProductPriceQuery innerJoinProductSaleElements($relationAlias = null) Adds a INNER JOIN clause to the query using the ProductSaleElements relation
 *
 * @method     ChildProductPriceQuery leftJoinCurrency($relationAlias = null) Adds a LEFT JOIN clause to the query using the Currency relation
 * @method     ChildProductPriceQuery rightJoinCurrency($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Currency relation
 * @method     ChildProductPriceQuery innerJoinCurrency($relationAlias = null) Adds a INNER JOIN clause to the query using the Currency relation
 *
 * @method     ChildProductPrice findOne(ConnectionInterface $con = null) Return the first ChildProductPrice matching the query
 * @method     ChildProductPrice findOneOrCreate(ConnectionInterface $con = null) Return the first ChildProductPrice matching the query, or a new ChildProductPrice object populated from the query conditions when no match is found
 *
 * @method     ChildProductPrice findOneByProductSaleElementsId(int $product_sale_elements_id) Return the first ChildProductPrice filtered by the product_sale_elements_id column
 * @method     ChildProductPrice findOneByCurrencyId(int $currency_id) Return the first ChildProductPrice filtered by the currency_id column
 * @method     ChildProductPrice findOneByPrice(string $price) Return the first ChildProductPrice filtered by the price column
 * @method     ChildProductPrice findOneByPromoPrice(string $promo_price) Return the first ChildProductPrice filtered by the promo_price column
 * @method     ChildProductPrice findOneByFromDefaultCurrency(boolean $from_default_currency) Return the first ChildProductPrice filtered by the from_default_currency column
 * @method     ChildProductPrice findOneByCreatedAt(string $created_at) Return the first ChildProductPrice filtered by the created_at column
 * @method     ChildProductPrice findOneByUpdatedAt(string $updated_at) Return the first ChildProductPrice filtered by the updated_at column
 *
 * @method     array findByProductSaleElementsId(int $product_sale_elements_id) Return ChildProductPrice objects filtered by the product_sale_elements_id column
 * @method     array findByCurrencyId(int $currency_id) Return ChildProductPrice objects filtered by the currency_id column
 * @method     array findByPrice(string $price) Return ChildProductPrice objects filtered by the price column
 * @method     array findByPromoPrice(string $promo_price) Return ChildProductPrice objects filtered by the promo_price column
 * @method     array findByFromDefaultCurrency(boolean $from_default_currency) Return ChildProductPrice objects filtered by the from_default_currency column
 * @method     array findByCreatedAt(string $created_at) Return ChildProductPrice objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildProductPrice objects filtered by the updated_at column
 *
 */
abstract class ProductPriceQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\ProductPriceQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\ProductPrice', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildProductPriceQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildProductPriceQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\ProductPriceQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\ProductPriceQuery();
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
     * @param array[$product_sale_elements_id, $currency_id] $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildProductPrice|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = ProductPriceTableMap::getInstanceFromPool(serialize(array((string) $key[0], (string) $key[1]))))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(ProductPriceTableMap::DATABASE_NAME);
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
     * @return   ChildProductPrice A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `PRODUCT_SALE_ELEMENTS_ID`, `CURRENCY_ID`, `PRICE`, `PROMO_PRICE`, `FROM_DEFAULT_CURRENCY`, `CREATED_AT`, `UPDATED_AT` FROM `product_price` WHERE `PRODUCT_SALE_ELEMENTS_ID` = :p0 AND `CURRENCY_ID` = :p1';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key[0], PDO::PARAM_INT);
            $stmt->bindValue(':p1', $key[1], PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $obj = new ChildProductPrice();
            $obj->hydrate($row);
            ProductPriceTableMap::addInstanceToPool($obj, serialize(array((string) $key[0], (string) $key[1])));
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
     * @return ChildProductPrice|array|mixed the result, formatted by the current formatter
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
     * $objs = $c->findPks(array(array(12, 56), array(832, 123), array(123, 456)), $con);
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
     * @return ChildProductPriceQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(ProductPriceTableMap::PRODUCT_SALE_ELEMENTS_ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(ProductPriceTableMap::CURRENCY_ID, $key[1], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildProductPriceQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(ProductPriceTableMap::PRODUCT_SALE_ELEMENTS_ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(ProductPriceTableMap::CURRENCY_ID, $key[1], Criteria::EQUAL);
            $cton0->addAnd($cton1);
            $this->addOr($cton0);
        }

        return $this;
    }

    /**
     * Filter the query on the product_sale_elements_id column
     *
     * Example usage:
     * <code>
     * $query->filterByProductSaleElementsId(1234); // WHERE product_sale_elements_id = 1234
     * $query->filterByProductSaleElementsId(array(12, 34)); // WHERE product_sale_elements_id IN (12, 34)
     * $query->filterByProductSaleElementsId(array('min' => 12)); // WHERE product_sale_elements_id > 12
     * </code>
     *
     * @see       filterByProductSaleElements()
     *
     * @param     mixed $productSaleElementsId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductPriceQuery The current query, for fluid interface
     */
    public function filterByProductSaleElementsId($productSaleElementsId = null, $comparison = null)
    {
        if (is_array($productSaleElementsId)) {
            $useMinMax = false;
            if (isset($productSaleElementsId['min'])) {
                $this->addUsingAlias(ProductPriceTableMap::PRODUCT_SALE_ELEMENTS_ID, $productSaleElementsId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($productSaleElementsId['max'])) {
                $this->addUsingAlias(ProductPriceTableMap::PRODUCT_SALE_ELEMENTS_ID, $productSaleElementsId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPriceTableMap::PRODUCT_SALE_ELEMENTS_ID, $productSaleElementsId, $comparison);
    }

    /**
     * Filter the query on the currency_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCurrencyId(1234); // WHERE currency_id = 1234
     * $query->filterByCurrencyId(array(12, 34)); // WHERE currency_id IN (12, 34)
     * $query->filterByCurrencyId(array('min' => 12)); // WHERE currency_id > 12
     * </code>
     *
     * @see       filterByCurrency()
     *
     * @param     mixed $currencyId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductPriceQuery The current query, for fluid interface
     */
    public function filterByCurrencyId($currencyId = null, $comparison = null)
    {
        if (is_array($currencyId)) {
            $useMinMax = false;
            if (isset($currencyId['min'])) {
                $this->addUsingAlias(ProductPriceTableMap::CURRENCY_ID, $currencyId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($currencyId['max'])) {
                $this->addUsingAlias(ProductPriceTableMap::CURRENCY_ID, $currencyId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPriceTableMap::CURRENCY_ID, $currencyId, $comparison);
    }

    /**
     * Filter the query on the price column
     *
     * Example usage:
     * <code>
     * $query->filterByPrice(1234); // WHERE price = 1234
     * $query->filterByPrice(array(12, 34)); // WHERE price IN (12, 34)
     * $query->filterByPrice(array('min' => 12)); // WHERE price > 12
     * </code>
     *
     * @param     mixed $price The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductPriceQuery The current query, for fluid interface
     */
    public function filterByPrice($price = null, $comparison = null)
    {
        if (is_array($price)) {
            $useMinMax = false;
            if (isset($price['min'])) {
                $this->addUsingAlias(ProductPriceTableMap::PRICE, $price['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($price['max'])) {
                $this->addUsingAlias(ProductPriceTableMap::PRICE, $price['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPriceTableMap::PRICE, $price, $comparison);
    }

    /**
     * Filter the query on the promo_price column
     *
     * Example usage:
     * <code>
     * $query->filterByPromoPrice(1234); // WHERE promo_price = 1234
     * $query->filterByPromoPrice(array(12, 34)); // WHERE promo_price IN (12, 34)
     * $query->filterByPromoPrice(array('min' => 12)); // WHERE promo_price > 12
     * </code>
     *
     * @param     mixed $promoPrice The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductPriceQuery The current query, for fluid interface
     */
    public function filterByPromoPrice($promoPrice = null, $comparison = null)
    {
        if (is_array($promoPrice)) {
            $useMinMax = false;
            if (isset($promoPrice['min'])) {
                $this->addUsingAlias(ProductPriceTableMap::PROMO_PRICE, $promoPrice['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($promoPrice['max'])) {
                $this->addUsingAlias(ProductPriceTableMap::PROMO_PRICE, $promoPrice['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPriceTableMap::PROMO_PRICE, $promoPrice, $comparison);
    }

    /**
     * Filter the query on the from_default_currency column
     *
     * Example usage:
     * <code>
     * $query->filterByFromDefaultCurrency(true); // WHERE from_default_currency = true
     * $query->filterByFromDefaultCurrency('yes'); // WHERE from_default_currency = true
     * </code>
     *
     * @param     boolean|string $fromDefaultCurrency The value to use as filter.
     *              Non-boolean arguments are converted using the following rules:
     *                * 1, '1', 'true',  'on',  and 'yes' are converted to boolean true
     *                * 0, '0', 'false', 'off', and 'no'  are converted to boolean false
     *              Check on string values is case insensitive (so 'FaLsE' is seen as 'false').
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductPriceQuery The current query, for fluid interface
     */
    public function filterByFromDefaultCurrency($fromDefaultCurrency = null, $comparison = null)
    {
        if (is_string($fromDefaultCurrency)) {
            $from_default_currency = in_array(strtolower($fromDefaultCurrency), array('false', 'off', '-', 'no', 'n', '0', '')) ? false : true;
        }

        return $this->addUsingAlias(ProductPriceTableMap::FROM_DEFAULT_CURRENCY, $fromDefaultCurrency, $comparison);
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
     * @return ChildProductPriceQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(ProductPriceTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(ProductPriceTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPriceTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildProductPriceQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(ProductPriceTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(ProductPriceTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPriceTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\ProductSaleElements object
     *
     * @param \Thelia\Model\ProductSaleElements|ObjectCollection $productSaleElements The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductPriceQuery The current query, for fluid interface
     */
    public function filterByProductSaleElements($productSaleElements, $comparison = null)
    {
        if ($productSaleElements instanceof \Thelia\Model\ProductSaleElements) {
            return $this
                ->addUsingAlias(ProductPriceTableMap::PRODUCT_SALE_ELEMENTS_ID, $productSaleElements->getId(), $comparison);
        } elseif ($productSaleElements instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(ProductPriceTableMap::PRODUCT_SALE_ELEMENTS_ID, $productSaleElements->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByProductSaleElements() only accepts arguments of type \Thelia\Model\ProductSaleElements or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ProductSaleElements relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProductPriceQuery The current query, for fluid interface
     */
    public function joinProductSaleElements($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ProductSaleElements');

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
            $this->addJoinObject($join, 'ProductSaleElements');
        }

        return $this;
    }

    /**
     * Use the ProductSaleElements relation ProductSaleElements object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ProductSaleElementsQuery A secondary query class using the current class as primary query
     */
    public function useProductSaleElementsQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinProductSaleElements($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ProductSaleElements', '\Thelia\Model\ProductSaleElementsQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Currency object
     *
     * @param \Thelia\Model\Currency|ObjectCollection $currency The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductPriceQuery The current query, for fluid interface
     */
    public function filterByCurrency($currency, $comparison = null)
    {
        if ($currency instanceof \Thelia\Model\Currency) {
            return $this
                ->addUsingAlias(ProductPriceTableMap::CURRENCY_ID, $currency->getId(), $comparison);
        } elseif ($currency instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(ProductPriceTableMap::CURRENCY_ID, $currency->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByCurrency() only accepts arguments of type \Thelia\Model\Currency or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Currency relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProductPriceQuery The current query, for fluid interface
     */
    public function joinCurrency($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Currency');

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
            $this->addJoinObject($join, 'Currency');
        }

        return $this;
    }

    /**
     * Use the Currency relation Currency object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CurrencyQuery A secondary query class using the current class as primary query
     */
    public function useCurrencyQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCurrency($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Currency', '\Thelia\Model\CurrencyQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildProductPrice $productPrice Object to remove from the list of results
     *
     * @return ChildProductPriceQuery The current query, for fluid interface
     */
    public function prune($productPrice = null)
    {
        if ($productPrice) {
            $this->addCond('pruneCond0', $this->getAliasedColName(ProductPriceTableMap::PRODUCT_SALE_ELEMENTS_ID), $productPrice->getProductSaleElementsId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(ProductPriceTableMap::CURRENCY_ID), $productPrice->getCurrencyId(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

    /**
     * Deletes all rows from the product_price table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ProductPriceTableMap::DATABASE_NAME);
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
            ProductPriceTableMap::clearInstancePool();
            ProductPriceTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildProductPrice or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildProductPrice object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(ProductPriceTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(ProductPriceTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        ProductPriceTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            ProductPriceTableMap::clearRelatedInstancePool();
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
     * @return     ChildProductPriceQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(ProductPriceTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildProductPriceQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(ProductPriceTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildProductPriceQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(ProductPriceTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildProductPriceQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(ProductPriceTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildProductPriceQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(ProductPriceTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildProductPriceQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(ProductPriceTableMap::CREATED_AT);
    }

} // ProductPriceQuery
