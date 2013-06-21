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
use Thelia\Model\Product;
use Thelia\Model\ProductVersion;
use Thelia\Model\ProductVersionPeer;
use Thelia\Model\ProductVersionQuery;

/**
 * Base class that represents a query for the 'product_version' table.
 *
 *
 *
 * @method ProductVersionQuery orderById($order = Criteria::ASC) Order by the id column
 * @method ProductVersionQuery orderByTaxRuleId($order = Criteria::ASC) Order by the tax_rule_id column
 * @method ProductVersionQuery orderByRef($order = Criteria::ASC) Order by the ref column
 * @method ProductVersionQuery orderByPrice($order = Criteria::ASC) Order by the price column
 * @method ProductVersionQuery orderByPrice2($order = Criteria::ASC) Order by the price2 column
 * @method ProductVersionQuery orderByEcotax($order = Criteria::ASC) Order by the ecotax column
 * @method ProductVersionQuery orderByNewness($order = Criteria::ASC) Order by the newness column
 * @method ProductVersionQuery orderByPromo($order = Criteria::ASC) Order by the promo column
 * @method ProductVersionQuery orderByQuantity($order = Criteria::ASC) Order by the quantity column
 * @method ProductVersionQuery orderByVisible($order = Criteria::ASC) Order by the visible column
 * @method ProductVersionQuery orderByWeight($order = Criteria::ASC) Order by the weight column
 * @method ProductVersionQuery orderByPosition($order = Criteria::ASC) Order by the position column
 * @method ProductVersionQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method ProductVersionQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 * @method ProductVersionQuery orderByVersion($order = Criteria::ASC) Order by the version column
 * @method ProductVersionQuery orderByVersionCreatedAt($order = Criteria::ASC) Order by the version_created_at column
 * @method ProductVersionQuery orderByVersionCreatedBy($order = Criteria::ASC) Order by the version_created_by column
 *
 * @method ProductVersionQuery groupById() Group by the id column
 * @method ProductVersionQuery groupByTaxRuleId() Group by the tax_rule_id column
 * @method ProductVersionQuery groupByRef() Group by the ref column
 * @method ProductVersionQuery groupByPrice() Group by the price column
 * @method ProductVersionQuery groupByPrice2() Group by the price2 column
 * @method ProductVersionQuery groupByEcotax() Group by the ecotax column
 * @method ProductVersionQuery groupByNewness() Group by the newness column
 * @method ProductVersionQuery groupByPromo() Group by the promo column
 * @method ProductVersionQuery groupByQuantity() Group by the quantity column
 * @method ProductVersionQuery groupByVisible() Group by the visible column
 * @method ProductVersionQuery groupByWeight() Group by the weight column
 * @method ProductVersionQuery groupByPosition() Group by the position column
 * @method ProductVersionQuery groupByCreatedAt() Group by the created_at column
 * @method ProductVersionQuery groupByUpdatedAt() Group by the updated_at column
 * @method ProductVersionQuery groupByVersion() Group by the version column
 * @method ProductVersionQuery groupByVersionCreatedAt() Group by the version_created_at column
 * @method ProductVersionQuery groupByVersionCreatedBy() Group by the version_created_by column
 *
 * @method ProductVersionQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method ProductVersionQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method ProductVersionQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method ProductVersionQuery leftJoinProduct($relationAlias = null) Adds a LEFT JOIN clause to the query using the Product relation
 * @method ProductVersionQuery rightJoinProduct($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Product relation
 * @method ProductVersionQuery innerJoinProduct($relationAlias = null) Adds a INNER JOIN clause to the query using the Product relation
 *
 * @method ProductVersion findOne(PropelPDO $con = null) Return the first ProductVersion matching the query
 * @method ProductVersion findOneOrCreate(PropelPDO $con = null) Return the first ProductVersion matching the query, or a new ProductVersion object populated from the query conditions when no match is found
 *
 * @method ProductVersion findOneById(int $id) Return the first ProductVersion filtered by the id column
 * @method ProductVersion findOneByTaxRuleId(int $tax_rule_id) Return the first ProductVersion filtered by the tax_rule_id column
 * @method ProductVersion findOneByRef(string $ref) Return the first ProductVersion filtered by the ref column
 * @method ProductVersion findOneByPrice(double $price) Return the first ProductVersion filtered by the price column
 * @method ProductVersion findOneByPrice2(double $price2) Return the first ProductVersion filtered by the price2 column
 * @method ProductVersion findOneByEcotax(double $ecotax) Return the first ProductVersion filtered by the ecotax column
 * @method ProductVersion findOneByNewness(int $newness) Return the first ProductVersion filtered by the newness column
 * @method ProductVersion findOneByPromo(int $promo) Return the first ProductVersion filtered by the promo column
 * @method ProductVersion findOneByQuantity(int $quantity) Return the first ProductVersion filtered by the quantity column
 * @method ProductVersion findOneByVisible(int $visible) Return the first ProductVersion filtered by the visible column
 * @method ProductVersion findOneByWeight(double $weight) Return the first ProductVersion filtered by the weight column
 * @method ProductVersion findOneByPosition(int $position) Return the first ProductVersion filtered by the position column
 * @method ProductVersion findOneByCreatedAt(string $created_at) Return the first ProductVersion filtered by the created_at column
 * @method ProductVersion findOneByUpdatedAt(string $updated_at) Return the first ProductVersion filtered by the updated_at column
 * @method ProductVersion findOneByVersion(int $version) Return the first ProductVersion filtered by the version column
 * @method ProductVersion findOneByVersionCreatedAt(string $version_created_at) Return the first ProductVersion filtered by the version_created_at column
 * @method ProductVersion findOneByVersionCreatedBy(string $version_created_by) Return the first ProductVersion filtered by the version_created_by column
 *
 * @method array findById(int $id) Return ProductVersion objects filtered by the id column
 * @method array findByTaxRuleId(int $tax_rule_id) Return ProductVersion objects filtered by the tax_rule_id column
 * @method array findByRef(string $ref) Return ProductVersion objects filtered by the ref column
 * @method array findByPrice(double $price) Return ProductVersion objects filtered by the price column
 * @method array findByPrice2(double $price2) Return ProductVersion objects filtered by the price2 column
 * @method array findByEcotax(double $ecotax) Return ProductVersion objects filtered by the ecotax column
 * @method array findByNewness(int $newness) Return ProductVersion objects filtered by the newness column
 * @method array findByPromo(int $promo) Return ProductVersion objects filtered by the promo column
 * @method array findByQuantity(int $quantity) Return ProductVersion objects filtered by the quantity column
 * @method array findByVisible(int $visible) Return ProductVersion objects filtered by the visible column
 * @method array findByWeight(double $weight) Return ProductVersion objects filtered by the weight column
 * @method array findByPosition(int $position) Return ProductVersion objects filtered by the position column
 * @method array findByCreatedAt(string $created_at) Return ProductVersion objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return ProductVersion objects filtered by the updated_at column
 * @method array findByVersion(int $version) Return ProductVersion objects filtered by the version column
 * @method array findByVersionCreatedAt(string $version_created_at) Return ProductVersion objects filtered by the version_created_at column
 * @method array findByVersionCreatedBy(string $version_created_by) Return ProductVersion objects filtered by the version_created_by column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseProductVersionQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseProductVersionQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\ProductVersion', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ProductVersionQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param   ProductVersionQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return ProductVersionQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof ProductVersionQuery) {
            return $criteria;
        }
        $query = new ProductVersionQuery();
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
                         A Primary key composition: [$id, $version]
     * @param     PropelPDO $con an optional connection object
     *
     * @return   ProductVersion|ProductVersion[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = ProductVersionPeer::getInstanceFromPool(serialize(array((string) $key[0], (string) $key[1]))))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(ProductVersionPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return                 ProductVersion A model object, or null if the key is not found
     * @throws PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `id`, `tax_rule_id`, `ref`, `price`, `price2`, `ecotax`, `newness`, `promo`, `quantity`, `visible`, `weight`, `position`, `created_at`, `updated_at`, `version`, `version_created_at`, `version_created_by` FROM `product_version` WHERE `id` = :p0 AND `version` = :p1';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key[0], PDO::PARAM_INT);
            $stmt->bindValue(':p1', $key[1], PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $obj = new ProductVersion();
            $obj->hydrate($row);
            ProductVersionPeer::addInstanceToPool($obj, serialize(array((string) $key[0], (string) $key[1])));
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
     * @return ProductVersion|ProductVersion[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|ProductVersion[]|mixed the list of results, formatted by the current formatter
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
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(ProductVersionPeer::ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(ProductVersionPeer::VERSION, $key[1], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(ProductVersionPeer::ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(ProductVersionPeer::VERSION, $key[1], Criteria::EQUAL);
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
     * @see       filterByProduct()
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(ProductVersionPeer::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(ProductVersionPeer::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::ID, $id, $comparison);
    }

    /**
     * Filter the query on the tax_rule_id column
     *
     * Example usage:
     * <code>
     * $query->filterByTaxRuleId(1234); // WHERE tax_rule_id = 1234
     * $query->filterByTaxRuleId(array(12, 34)); // WHERE tax_rule_id IN (12, 34)
     * $query->filterByTaxRuleId(array('min' => 12)); // WHERE tax_rule_id >= 12
     * $query->filterByTaxRuleId(array('max' => 12)); // WHERE tax_rule_id <= 12
     * </code>
     *
     * @param     mixed $taxRuleId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByTaxRuleId($taxRuleId = null, $comparison = null)
    {
        if (is_array($taxRuleId)) {
            $useMinMax = false;
            if (isset($taxRuleId['min'])) {
                $this->addUsingAlias(ProductVersionPeer::TAX_RULE_ID, $taxRuleId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($taxRuleId['max'])) {
                $this->addUsingAlias(ProductVersionPeer::TAX_RULE_ID, $taxRuleId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::TAX_RULE_ID, $taxRuleId, $comparison);
    }

    /**
     * Filter the query on the ref column
     *
     * Example usage:
     * <code>
     * $query->filterByRef('fooValue');   // WHERE ref = 'fooValue'
     * $query->filterByRef('%fooValue%'); // WHERE ref LIKE '%fooValue%'
     * </code>
     *
     * @param     string $ref The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByRef($ref = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($ref)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $ref)) {
                $ref = str_replace('*', '%', $ref);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::REF, $ref, $comparison);
    }

    /**
     * Filter the query on the price column
     *
     * Example usage:
     * <code>
     * $query->filterByPrice(1234); // WHERE price = 1234
     * $query->filterByPrice(array(12, 34)); // WHERE price IN (12, 34)
     * $query->filterByPrice(array('min' => 12)); // WHERE price >= 12
     * $query->filterByPrice(array('max' => 12)); // WHERE price <= 12
     * </code>
     *
     * @param     mixed $price The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByPrice($price = null, $comparison = null)
    {
        if (is_array($price)) {
            $useMinMax = false;
            if (isset($price['min'])) {
                $this->addUsingAlias(ProductVersionPeer::PRICE, $price['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($price['max'])) {
                $this->addUsingAlias(ProductVersionPeer::PRICE, $price['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::PRICE, $price, $comparison);
    }

    /**
     * Filter the query on the price2 column
     *
     * Example usage:
     * <code>
     * $query->filterByPrice2(1234); // WHERE price2 = 1234
     * $query->filterByPrice2(array(12, 34)); // WHERE price2 IN (12, 34)
     * $query->filterByPrice2(array('min' => 12)); // WHERE price2 >= 12
     * $query->filterByPrice2(array('max' => 12)); // WHERE price2 <= 12
     * </code>
     *
     * @param     mixed $price2 The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByPrice2($price2 = null, $comparison = null)
    {
        if (is_array($price2)) {
            $useMinMax = false;
            if (isset($price2['min'])) {
                $this->addUsingAlias(ProductVersionPeer::PRICE2, $price2['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($price2['max'])) {
                $this->addUsingAlias(ProductVersionPeer::PRICE2, $price2['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::PRICE2, $price2, $comparison);
    }

    /**
     * Filter the query on the ecotax column
     *
     * Example usage:
     * <code>
     * $query->filterByEcotax(1234); // WHERE ecotax = 1234
     * $query->filterByEcotax(array(12, 34)); // WHERE ecotax IN (12, 34)
     * $query->filterByEcotax(array('min' => 12)); // WHERE ecotax >= 12
     * $query->filterByEcotax(array('max' => 12)); // WHERE ecotax <= 12
     * </code>
     *
     * @param     mixed $ecotax The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByEcotax($ecotax = null, $comparison = null)
    {
        if (is_array($ecotax)) {
            $useMinMax = false;
            if (isset($ecotax['min'])) {
                $this->addUsingAlias(ProductVersionPeer::ECOTAX, $ecotax['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($ecotax['max'])) {
                $this->addUsingAlias(ProductVersionPeer::ECOTAX, $ecotax['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::ECOTAX, $ecotax, $comparison);
    }

    /**
     * Filter the query on the newness column
     *
     * Example usage:
     * <code>
     * $query->filterByNewness(1234); // WHERE newness = 1234
     * $query->filterByNewness(array(12, 34)); // WHERE newness IN (12, 34)
     * $query->filterByNewness(array('min' => 12)); // WHERE newness >= 12
     * $query->filterByNewness(array('max' => 12)); // WHERE newness <= 12
     * </code>
     *
     * @param     mixed $newness The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByNewness($newness = null, $comparison = null)
    {
        if (is_array($newness)) {
            $useMinMax = false;
            if (isset($newness['min'])) {
                $this->addUsingAlias(ProductVersionPeer::NEWNESS, $newness['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($newness['max'])) {
                $this->addUsingAlias(ProductVersionPeer::NEWNESS, $newness['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::NEWNESS, $newness, $comparison);
    }

    /**
     * Filter the query on the promo column
     *
     * Example usage:
     * <code>
     * $query->filterByPromo(1234); // WHERE promo = 1234
     * $query->filterByPromo(array(12, 34)); // WHERE promo IN (12, 34)
     * $query->filterByPromo(array('min' => 12)); // WHERE promo >= 12
     * $query->filterByPromo(array('max' => 12)); // WHERE promo <= 12
     * </code>
     *
     * @param     mixed $promo The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByPromo($promo = null, $comparison = null)
    {
        if (is_array($promo)) {
            $useMinMax = false;
            if (isset($promo['min'])) {
                $this->addUsingAlias(ProductVersionPeer::PROMO, $promo['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($promo['max'])) {
                $this->addUsingAlias(ProductVersionPeer::PROMO, $promo['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::PROMO, $promo, $comparison);
    }

    /**
     * Filter the query on the quantity column
     *
     * Example usage:
     * <code>
     * $query->filterByQuantity(1234); // WHERE quantity = 1234
     * $query->filterByQuantity(array(12, 34)); // WHERE quantity IN (12, 34)
     * $query->filterByQuantity(array('min' => 12)); // WHERE quantity >= 12
     * $query->filterByQuantity(array('max' => 12)); // WHERE quantity <= 12
     * </code>
     *
     * @param     mixed $quantity The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByQuantity($quantity = null, $comparison = null)
    {
        if (is_array($quantity)) {
            $useMinMax = false;
            if (isset($quantity['min'])) {
                $this->addUsingAlias(ProductVersionPeer::QUANTITY, $quantity['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($quantity['max'])) {
                $this->addUsingAlias(ProductVersionPeer::QUANTITY, $quantity['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::QUANTITY, $quantity, $comparison);
    }

    /**
     * Filter the query on the visible column
     *
     * Example usage:
     * <code>
     * $query->filterByVisible(1234); // WHERE visible = 1234
     * $query->filterByVisible(array(12, 34)); // WHERE visible IN (12, 34)
     * $query->filterByVisible(array('min' => 12)); // WHERE visible >= 12
     * $query->filterByVisible(array('max' => 12)); // WHERE visible <= 12
     * </code>
     *
     * @param     mixed $visible The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByVisible($visible = null, $comparison = null)
    {
        if (is_array($visible)) {
            $useMinMax = false;
            if (isset($visible['min'])) {
                $this->addUsingAlias(ProductVersionPeer::VISIBLE, $visible['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($visible['max'])) {
                $this->addUsingAlias(ProductVersionPeer::VISIBLE, $visible['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::VISIBLE, $visible, $comparison);
    }

    /**
     * Filter the query on the weight column
     *
     * Example usage:
     * <code>
     * $query->filterByWeight(1234); // WHERE weight = 1234
     * $query->filterByWeight(array(12, 34)); // WHERE weight IN (12, 34)
     * $query->filterByWeight(array('min' => 12)); // WHERE weight >= 12
     * $query->filterByWeight(array('max' => 12)); // WHERE weight <= 12
     * </code>
     *
     * @param     mixed $weight The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByWeight($weight = null, $comparison = null)
    {
        if (is_array($weight)) {
            $useMinMax = false;
            if (isset($weight['min'])) {
                $this->addUsingAlias(ProductVersionPeer::WEIGHT, $weight['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($weight['max'])) {
                $this->addUsingAlias(ProductVersionPeer::WEIGHT, $weight['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::WEIGHT, $weight, $comparison);
    }

    /**
     * Filter the query on the position column
     *
     * Example usage:
     * <code>
     * $query->filterByPosition(1234); // WHERE position = 1234
     * $query->filterByPosition(array(12, 34)); // WHERE position IN (12, 34)
     * $query->filterByPosition(array('min' => 12)); // WHERE position >= 12
     * $query->filterByPosition(array('max' => 12)); // WHERE position <= 12
     * </code>
     *
     * @param     mixed $position The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        if (is_array($position)) {
            $useMinMax = false;
            if (isset($position['min'])) {
                $this->addUsingAlias(ProductVersionPeer::POSITION, $position['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($position['max'])) {
                $this->addUsingAlias(ProductVersionPeer::POSITION, $position['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::POSITION, $position, $comparison);
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
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(ProductVersionPeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(ProductVersionPeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::CREATED_AT, $createdAt, $comparison);
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
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(ProductVersionPeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(ProductVersionPeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query on the version column
     *
     * Example usage:
     * <code>
     * $query->filterByVersion(1234); // WHERE version = 1234
     * $query->filterByVersion(array(12, 34)); // WHERE version IN (12, 34)
     * $query->filterByVersion(array('min' => 12)); // WHERE version >= 12
     * $query->filterByVersion(array('max' => 12)); // WHERE version <= 12
     * </code>
     *
     * @param     mixed $version The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByVersion($version = null, $comparison = null)
    {
        if (is_array($version)) {
            $useMinMax = false;
            if (isset($version['min'])) {
                $this->addUsingAlias(ProductVersionPeer::VERSION, $version['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($version['max'])) {
                $this->addUsingAlias(ProductVersionPeer::VERSION, $version['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::VERSION, $version, $comparison);
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
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function filterByVersionCreatedAt($versionCreatedAt = null, $comparison = null)
    {
        if (is_array($versionCreatedAt)) {
            $useMinMax = false;
            if (isset($versionCreatedAt['min'])) {
                $this->addUsingAlias(ProductVersionPeer::VERSION_CREATED_AT, $versionCreatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($versionCreatedAt['max'])) {
                $this->addUsingAlias(ProductVersionPeer::VERSION_CREATED_AT, $versionCreatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionPeer::VERSION_CREATED_AT, $versionCreatedAt, $comparison);
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
     * @return ProductVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(ProductVersionPeer::VERSION_CREATED_BY, $versionCreatedBy, $comparison);
    }

    /**
     * Filter the query by a related Product object
     *
     * @param   Product|PropelObjectCollection $product The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return                 ProductVersionQuery The current query, for fluid interface
     * @throws PropelException - if the provided filter is invalid.
     */
    public function filterByProduct($product, $comparison = null)
    {
        if ($product instanceof Product) {
            return $this
                ->addUsingAlias(ProductVersionPeer::ID, $product->getId(), $comparison);
        } elseif ($product instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(ProductVersionPeer::ID, $product->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByProduct() only accepts arguments of type Product or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Product relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function joinProduct($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Product');

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
            $this->addJoinObject($join, 'Product');
        }

        return $this;
    }

    /**
     * Use the Product relation Product object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ProductQuery A secondary query class using the current class as primary query
     */
    public function useProductQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinProduct($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Product', '\Thelia\Model\ProductQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ProductVersion $productVersion Object to remove from the list of results
     *
     * @return ProductVersionQuery The current query, for fluid interface
     */
    public function prune($productVersion = null)
    {
        if ($productVersion) {
            $this->addCond('pruneCond0', $this->getAliasedColName(ProductVersionPeer::ID), $productVersion->getId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(ProductVersionPeer::VERSION), $productVersion->getVersion(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

}
