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
use Thelia\Model\Accessory;
use Thelia\Model\ContentAssoc;
use Thelia\Model\Document;
use Thelia\Model\FeatureProd;
use Thelia\Model\Image;
use Thelia\Model\Product;
use Thelia\Model\ProductCategory;
use Thelia\Model\ProductI18n;
use Thelia\Model\ProductPeer;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductVersion;
use Thelia\Model\Rewriting;
use Thelia\Model\Stock;
use Thelia\Model\TaxRule;

/**
 * Base class that represents a query for the 'product' table.
 *
 *
 *
 * @method ProductQuery orderById($order = Criteria::ASC) Order by the id column
 * @method ProductQuery orderByTaxRuleId($order = Criteria::ASC) Order by the tax_rule_id column
 * @method ProductQuery orderByRef($order = Criteria::ASC) Order by the ref column
 * @method ProductQuery orderByPrice($order = Criteria::ASC) Order by the price column
 * @method ProductQuery orderByPrice2($order = Criteria::ASC) Order by the price2 column
 * @method ProductQuery orderByEcotax($order = Criteria::ASC) Order by the ecotax column
 * @method ProductQuery orderByNewness($order = Criteria::ASC) Order by the newness column
 * @method ProductQuery orderByPromo($order = Criteria::ASC) Order by the promo column
 * @method ProductQuery orderByStock($order = Criteria::ASC) Order by the stock column
 * @method ProductQuery orderByVisible($order = Criteria::ASC) Order by the visible column
 * @method ProductQuery orderByWeight($order = Criteria::ASC) Order by the weight column
 * @method ProductQuery orderByPosition($order = Criteria::ASC) Order by the position column
 * @method ProductQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method ProductQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 * @method ProductQuery orderByVersion($order = Criteria::ASC) Order by the version column
 * @method ProductQuery orderByVersionCreatedAt($order = Criteria::ASC) Order by the version_created_at column
 * @method ProductQuery orderByVersionCreatedBy($order = Criteria::ASC) Order by the version_created_by column
 *
 * @method ProductQuery groupById() Group by the id column
 * @method ProductQuery groupByTaxRuleId() Group by the tax_rule_id column
 * @method ProductQuery groupByRef() Group by the ref column
 * @method ProductQuery groupByPrice() Group by the price column
 * @method ProductQuery groupByPrice2() Group by the price2 column
 * @method ProductQuery groupByEcotax() Group by the ecotax column
 * @method ProductQuery groupByNewness() Group by the newness column
 * @method ProductQuery groupByPromo() Group by the promo column
 * @method ProductQuery groupByStock() Group by the stock column
 * @method ProductQuery groupByVisible() Group by the visible column
 * @method ProductQuery groupByWeight() Group by the weight column
 * @method ProductQuery groupByPosition() Group by the position column
 * @method ProductQuery groupByCreatedAt() Group by the created_at column
 * @method ProductQuery groupByUpdatedAt() Group by the updated_at column
 * @method ProductQuery groupByVersion() Group by the version column
 * @method ProductQuery groupByVersionCreatedAt() Group by the version_created_at column
 * @method ProductQuery groupByVersionCreatedBy() Group by the version_created_by column
 *
 * @method ProductQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method ProductQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method ProductQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method ProductQuery leftJoinTaxRule($relationAlias = null) Adds a LEFT JOIN clause to the query using the TaxRule relation
 * @method ProductQuery rightJoinTaxRule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the TaxRule relation
 * @method ProductQuery innerJoinTaxRule($relationAlias = null) Adds a INNER JOIN clause to the query using the TaxRule relation
 *
 * @method ProductQuery leftJoinProductCategory($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProductCategory relation
 * @method ProductQuery rightJoinProductCategory($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProductCategory relation
 * @method ProductQuery innerJoinProductCategory($relationAlias = null) Adds a INNER JOIN clause to the query using the ProductCategory relation
 *
 * @method ProductQuery leftJoinFeatureProd($relationAlias = null) Adds a LEFT JOIN clause to the query using the FeatureProd relation
 * @method ProductQuery rightJoinFeatureProd($relationAlias = null) Adds a RIGHT JOIN clause to the query using the FeatureProd relation
 * @method ProductQuery innerJoinFeatureProd($relationAlias = null) Adds a INNER JOIN clause to the query using the FeatureProd relation
 *
 * @method ProductQuery leftJoinStock($relationAlias = null) Adds a LEFT JOIN clause to the query using the Stock relation
 * @method ProductQuery rightJoinStock($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Stock relation
 * @method ProductQuery innerJoinStock($relationAlias = null) Adds a INNER JOIN clause to the query using the Stock relation
 *
 * @method ProductQuery leftJoinContentAssoc($relationAlias = null) Adds a LEFT JOIN clause to the query using the ContentAssoc relation
 * @method ProductQuery rightJoinContentAssoc($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ContentAssoc relation
 * @method ProductQuery innerJoinContentAssoc($relationAlias = null) Adds a INNER JOIN clause to the query using the ContentAssoc relation
 *
 * @method ProductQuery leftJoinImage($relationAlias = null) Adds a LEFT JOIN clause to the query using the Image relation
 * @method ProductQuery rightJoinImage($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Image relation
 * @method ProductQuery innerJoinImage($relationAlias = null) Adds a INNER JOIN clause to the query using the Image relation
 *
 * @method ProductQuery leftJoinDocument($relationAlias = null) Adds a LEFT JOIN clause to the query using the Document relation
 * @method ProductQuery rightJoinDocument($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Document relation
 * @method ProductQuery innerJoinDocument($relationAlias = null) Adds a INNER JOIN clause to the query using the Document relation
 *
 * @method ProductQuery leftJoinAccessoryRelatedByProductId($relationAlias = null) Adds a LEFT JOIN clause to the query using the AccessoryRelatedByProductId relation
 * @method ProductQuery rightJoinAccessoryRelatedByProductId($relationAlias = null) Adds a RIGHT JOIN clause to the query using the AccessoryRelatedByProductId relation
 * @method ProductQuery innerJoinAccessoryRelatedByProductId($relationAlias = null) Adds a INNER JOIN clause to the query using the AccessoryRelatedByProductId relation
 *
 * @method ProductQuery leftJoinAccessoryRelatedByAccessory($relationAlias = null) Adds a LEFT JOIN clause to the query using the AccessoryRelatedByAccessory relation
 * @method ProductQuery rightJoinAccessoryRelatedByAccessory($relationAlias = null) Adds a RIGHT JOIN clause to the query using the AccessoryRelatedByAccessory relation
 * @method ProductQuery innerJoinAccessoryRelatedByAccessory($relationAlias = null) Adds a INNER JOIN clause to the query using the AccessoryRelatedByAccessory relation
 *
 * @method ProductQuery leftJoinRewriting($relationAlias = null) Adds a LEFT JOIN clause to the query using the Rewriting relation
 * @method ProductQuery rightJoinRewriting($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Rewriting relation
 * @method ProductQuery innerJoinRewriting($relationAlias = null) Adds a INNER JOIN clause to the query using the Rewriting relation
 *
 * @method ProductQuery leftJoinProductI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProductI18n relation
 * @method ProductQuery rightJoinProductI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProductI18n relation
 * @method ProductQuery innerJoinProductI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the ProductI18n relation
 *
 * @method ProductQuery leftJoinProductVersion($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProductVersion relation
 * @method ProductQuery rightJoinProductVersion($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProductVersion relation
 * @method ProductQuery innerJoinProductVersion($relationAlias = null) Adds a INNER JOIN clause to the query using the ProductVersion relation
 *
 * @method Product findOne(PropelPDO $con = null) Return the first Product matching the query
 * @method Product findOneOrCreate(PropelPDO $con = null) Return the first Product matching the query, or a new Product object populated from the query conditions when no match is found
 *
 * @method Product findOneById(int $id) Return the first Product filtered by the id column
 * @method Product findOneByTaxRuleId(int $tax_rule_id) Return the first Product filtered by the tax_rule_id column
 * @method Product findOneByRef(string $ref) Return the first Product filtered by the ref column
 * @method Product findOneByPrice(double $price) Return the first Product filtered by the price column
 * @method Product findOneByPrice2(double $price2) Return the first Product filtered by the price2 column
 * @method Product findOneByEcotax(double $ecotax) Return the first Product filtered by the ecotax column
 * @method Product findOneByNewness(int $newness) Return the first Product filtered by the newness column
 * @method Product findOneByPromo(int $promo) Return the first Product filtered by the promo column
 * @method Product findOneByStock(int $stock) Return the first Product filtered by the stock column
 * @method Product findOneByVisible(int $visible) Return the first Product filtered by the visible column
 * @method Product findOneByWeight(double $weight) Return the first Product filtered by the weight column
 * @method Product findOneByPosition(int $position) Return the first Product filtered by the position column
 * @method Product findOneByCreatedAt(string $created_at) Return the first Product filtered by the created_at column
 * @method Product findOneByUpdatedAt(string $updated_at) Return the first Product filtered by the updated_at column
 * @method Product findOneByVersion(int $version) Return the first Product filtered by the version column
 * @method Product findOneByVersionCreatedAt(string $version_created_at) Return the first Product filtered by the version_created_at column
 * @method Product findOneByVersionCreatedBy(string $version_created_by) Return the first Product filtered by the version_created_by column
 *
 * @method array findById(int $id) Return Product objects filtered by the id column
 * @method array findByTaxRuleId(int $tax_rule_id) Return Product objects filtered by the tax_rule_id column
 * @method array findByRef(string $ref) Return Product objects filtered by the ref column
 * @method array findByPrice(double $price) Return Product objects filtered by the price column
 * @method array findByPrice2(double $price2) Return Product objects filtered by the price2 column
 * @method array findByEcotax(double $ecotax) Return Product objects filtered by the ecotax column
 * @method array findByNewness(int $newness) Return Product objects filtered by the newness column
 * @method array findByPromo(int $promo) Return Product objects filtered by the promo column
 * @method array findByStock(int $stock) Return Product objects filtered by the stock column
 * @method array findByVisible(int $visible) Return Product objects filtered by the visible column
 * @method array findByWeight(double $weight) Return Product objects filtered by the weight column
 * @method array findByPosition(int $position) Return Product objects filtered by the position column
 * @method array findByCreatedAt(string $created_at) Return Product objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return Product objects filtered by the updated_at column
 * @method array findByVersion(int $version) Return Product objects filtered by the version column
 * @method array findByVersionCreatedAt(string $version_created_at) Return Product objects filtered by the version_created_at column
 * @method array findByVersionCreatedBy(string $version_created_by) Return Product objects filtered by the version_created_by column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseProductQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseProductQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = 'Thelia\\Model\\Product', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ProductQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     ProductQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return ProductQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof ProductQuery) {
            return $criteria;
        }
        $query = new ProductQuery();
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
     * @return   Product|Product[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = ProductPeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(ProductPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return   Product A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `TAX_RULE_ID`, `REF`, `PRICE`, `PRICE2`, `ECOTAX`, `NEWNESS`, `PROMO`, `STOCK`, `VISIBLE`, `WEIGHT`, `POSITION`, `CREATED_AT`, `UPDATED_AT`, `VERSION`, `VERSION_CREATED_AT`, `VERSION_CREATED_BY` FROM `product` WHERE `ID` = :p0';
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
            $obj = new Product();
            $obj->hydrate($row);
            ProductPeer::addInstanceToPool($obj, (string) $key);
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
     * @return Product|Product[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|Product[]|mixed the list of results, formatted by the current formatter
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
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(ProductPeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(ProductPeer::ID, $keys, Criteria::IN);
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
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(ProductPeer::ID, $id, $comparison);
    }

    /**
     * Filter the query on the tax_rule_id column
     *
     * Example usage:
     * <code>
     * $query->filterByTaxRuleId(1234); // WHERE tax_rule_id = 1234
     * $query->filterByTaxRuleId(array(12, 34)); // WHERE tax_rule_id IN (12, 34)
     * $query->filterByTaxRuleId(array('min' => 12)); // WHERE tax_rule_id > 12
     * </code>
     *
     * @see       filterByTaxRule()
     *
     * @param     mixed $taxRuleId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByTaxRuleId($taxRuleId = null, $comparison = null)
    {
        if (is_array($taxRuleId)) {
            $useMinMax = false;
            if (isset($taxRuleId['min'])) {
                $this->addUsingAlias(ProductPeer::TAX_RULE_ID, $taxRuleId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($taxRuleId['max'])) {
                $this->addUsingAlias(ProductPeer::TAX_RULE_ID, $taxRuleId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPeer::TAX_RULE_ID, $taxRuleId, $comparison);
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
     * @return ProductQuery The current query, for fluid interface
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

        return $this->addUsingAlias(ProductPeer::REF, $ref, $comparison);
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
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByPrice($price = null, $comparison = null)
    {
        if (is_array($price)) {
            $useMinMax = false;
            if (isset($price['min'])) {
                $this->addUsingAlias(ProductPeer::PRICE, $price['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($price['max'])) {
                $this->addUsingAlias(ProductPeer::PRICE, $price['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPeer::PRICE, $price, $comparison);
    }

    /**
     * Filter the query on the price2 column
     *
     * Example usage:
     * <code>
     * $query->filterByPrice2(1234); // WHERE price2 = 1234
     * $query->filterByPrice2(array(12, 34)); // WHERE price2 IN (12, 34)
     * $query->filterByPrice2(array('min' => 12)); // WHERE price2 > 12
     * </code>
     *
     * @param     mixed $price2 The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByPrice2($price2 = null, $comparison = null)
    {
        if (is_array($price2)) {
            $useMinMax = false;
            if (isset($price2['min'])) {
                $this->addUsingAlias(ProductPeer::PRICE2, $price2['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($price2['max'])) {
                $this->addUsingAlias(ProductPeer::PRICE2, $price2['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPeer::PRICE2, $price2, $comparison);
    }

    /**
     * Filter the query on the ecotax column
     *
     * Example usage:
     * <code>
     * $query->filterByEcotax(1234); // WHERE ecotax = 1234
     * $query->filterByEcotax(array(12, 34)); // WHERE ecotax IN (12, 34)
     * $query->filterByEcotax(array('min' => 12)); // WHERE ecotax > 12
     * </code>
     *
     * @param     mixed $ecotax The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByEcotax($ecotax = null, $comparison = null)
    {
        if (is_array($ecotax)) {
            $useMinMax = false;
            if (isset($ecotax['min'])) {
                $this->addUsingAlias(ProductPeer::ECOTAX, $ecotax['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($ecotax['max'])) {
                $this->addUsingAlias(ProductPeer::ECOTAX, $ecotax['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPeer::ECOTAX, $ecotax, $comparison);
    }

    /**
     * Filter the query on the newness column
     *
     * Example usage:
     * <code>
     * $query->filterByNewness(1234); // WHERE newness = 1234
     * $query->filterByNewness(array(12, 34)); // WHERE newness IN (12, 34)
     * $query->filterByNewness(array('min' => 12)); // WHERE newness > 12
     * </code>
     *
     * @param     mixed $newness The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByNewness($newness = null, $comparison = null)
    {
        if (is_array($newness)) {
            $useMinMax = false;
            if (isset($newness['min'])) {
                $this->addUsingAlias(ProductPeer::NEWNESS, $newness['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($newness['max'])) {
                $this->addUsingAlias(ProductPeer::NEWNESS, $newness['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPeer::NEWNESS, $newness, $comparison);
    }

    /**
     * Filter the query on the promo column
     *
     * Example usage:
     * <code>
     * $query->filterByPromo(1234); // WHERE promo = 1234
     * $query->filterByPromo(array(12, 34)); // WHERE promo IN (12, 34)
     * $query->filterByPromo(array('min' => 12)); // WHERE promo > 12
     * </code>
     *
     * @param     mixed $promo The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByPromo($promo = null, $comparison = null)
    {
        if (is_array($promo)) {
            $useMinMax = false;
            if (isset($promo['min'])) {
                $this->addUsingAlias(ProductPeer::PROMO, $promo['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($promo['max'])) {
                $this->addUsingAlias(ProductPeer::PROMO, $promo['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPeer::PROMO, $promo, $comparison);
    }

    /**
     * Filter the query on the stock column
     *
     * Example usage:
     * <code>
     * $query->filterByStock(1234); // WHERE stock = 1234
     * $query->filterByStock(array(12, 34)); // WHERE stock IN (12, 34)
     * $query->filterByStock(array('min' => 12)); // WHERE stock > 12
     * </code>
     *
     * @param     mixed $stock The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByStock($stock = null, $comparison = null)
    {
        if (is_array($stock)) {
            $useMinMax = false;
            if (isset($stock['min'])) {
                $this->addUsingAlias(ProductPeer::STOCK, $stock['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($stock['max'])) {
                $this->addUsingAlias(ProductPeer::STOCK, $stock['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPeer::STOCK, $stock, $comparison);
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
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByVisible($visible = null, $comparison = null)
    {
        if (is_array($visible)) {
            $useMinMax = false;
            if (isset($visible['min'])) {
                $this->addUsingAlias(ProductPeer::VISIBLE, $visible['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($visible['max'])) {
                $this->addUsingAlias(ProductPeer::VISIBLE, $visible['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPeer::VISIBLE, $visible, $comparison);
    }

    /**
     * Filter the query on the weight column
     *
     * Example usage:
     * <code>
     * $query->filterByWeight(1234); // WHERE weight = 1234
     * $query->filterByWeight(array(12, 34)); // WHERE weight IN (12, 34)
     * $query->filterByWeight(array('min' => 12)); // WHERE weight > 12
     * </code>
     *
     * @param     mixed $weight The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByWeight($weight = null, $comparison = null)
    {
        if (is_array($weight)) {
            $useMinMax = false;
            if (isset($weight['min'])) {
                $this->addUsingAlias(ProductPeer::WEIGHT, $weight['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($weight['max'])) {
                $this->addUsingAlias(ProductPeer::WEIGHT, $weight['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPeer::WEIGHT, $weight, $comparison);
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
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        if (is_array($position)) {
            $useMinMax = false;
            if (isset($position['min'])) {
                $this->addUsingAlias(ProductPeer::POSITION, $position['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($position['max'])) {
                $this->addUsingAlias(ProductPeer::POSITION, $position['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPeer::POSITION, $position, $comparison);
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
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(ProductPeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(ProductPeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPeer::CREATED_AT, $createdAt, $comparison);
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
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(ProductPeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(ProductPeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPeer::UPDATED_AT, $updatedAt, $comparison);
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
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByVersion($version = null, $comparison = null)
    {
        if (is_array($version)) {
            $useMinMax = false;
            if (isset($version['min'])) {
                $this->addUsingAlias(ProductPeer::VERSION, $version['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($version['max'])) {
                $this->addUsingAlias(ProductPeer::VERSION, $version['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPeer::VERSION, $version, $comparison);
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
     * @return ProductQuery The current query, for fluid interface
     */
    public function filterByVersionCreatedAt($versionCreatedAt = null, $comparison = null)
    {
        if (is_array($versionCreatedAt)) {
            $useMinMax = false;
            if (isset($versionCreatedAt['min'])) {
                $this->addUsingAlias(ProductPeer::VERSION_CREATED_AT, $versionCreatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($versionCreatedAt['max'])) {
                $this->addUsingAlias(ProductPeer::VERSION_CREATED_AT, $versionCreatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductPeer::VERSION_CREATED_AT, $versionCreatedAt, $comparison);
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
     * @return ProductQuery The current query, for fluid interface
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

        return $this->addUsingAlias(ProductPeer::VERSION_CREATED_BY, $versionCreatedBy, $comparison);
    }

    /**
     * Filter the query by a related TaxRule object
     *
     * @param   TaxRule|PropelObjectCollection $taxRule The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   ProductQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByTaxRule($taxRule, $comparison = null)
    {
        if ($taxRule instanceof TaxRule) {
            return $this
                ->addUsingAlias(ProductPeer::TAX_RULE_ID, $taxRule->getId(), $comparison);
        } elseif ($taxRule instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(ProductPeer::TAX_RULE_ID, $taxRule->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByTaxRule() only accepts arguments of type TaxRule or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the TaxRule relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function joinTaxRule($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('TaxRule');

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
            $this->addJoinObject($join, 'TaxRule');
        }

        return $this;
    }

    /**
     * Use the TaxRule relation TaxRule object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\TaxRuleQuery A secondary query class using the current class as primary query
     */
    public function useTaxRuleQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinTaxRule($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'TaxRule', '\Thelia\Model\TaxRuleQuery');
    }

    /**
     * Filter the query by a related ProductCategory object
     *
     * @param   ProductCategory|PropelObjectCollection $productCategory  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   ProductQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByProductCategory($productCategory, $comparison = null)
    {
        if ($productCategory instanceof ProductCategory) {
            return $this
                ->addUsingAlias(ProductPeer::ID, $productCategory->getProductId(), $comparison);
        } elseif ($productCategory instanceof PropelObjectCollection) {
            return $this
                ->useProductCategoryQuery()
                ->filterByPrimaryKeys($productCategory->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByProductCategory() only accepts arguments of type ProductCategory or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ProductCategory relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function joinProductCategory($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ProductCategory');

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
            $this->addJoinObject($join, 'ProductCategory');
        }

        return $this;
    }

    /**
     * Use the ProductCategory relation ProductCategory object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ProductCategoryQuery A secondary query class using the current class as primary query
     */
    public function useProductCategoryQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinProductCategory($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ProductCategory', '\Thelia\Model\ProductCategoryQuery');
    }

    /**
     * Filter the query by a related FeatureProd object
     *
     * @param   FeatureProd|PropelObjectCollection $featureProd  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   ProductQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByFeatureProd($featureProd, $comparison = null)
    {
        if ($featureProd instanceof FeatureProd) {
            return $this
                ->addUsingAlias(ProductPeer::ID, $featureProd->getProductId(), $comparison);
        } elseif ($featureProd instanceof PropelObjectCollection) {
            return $this
                ->useFeatureProdQuery()
                ->filterByPrimaryKeys($featureProd->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByFeatureProd() only accepts arguments of type FeatureProd or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the FeatureProd relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function joinFeatureProd($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('FeatureProd');

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
            $this->addJoinObject($join, 'FeatureProd');
        }

        return $this;
    }

    /**
     * Use the FeatureProd relation FeatureProd object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\FeatureProdQuery A secondary query class using the current class as primary query
     */
    public function useFeatureProdQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinFeatureProd($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'FeatureProd', '\Thelia\Model\FeatureProdQuery');
    }

    /**
     * Filter the query by a related Stock object
     *
     * @param   Stock|PropelObjectCollection $stock  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   ProductQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByStock($stock, $comparison = null)
    {
        if ($stock instanceof Stock) {
            return $this
                ->addUsingAlias(ProductPeer::ID, $stock->getProductId(), $comparison);
        } elseif ($stock instanceof PropelObjectCollection) {
            return $this
                ->useStockQuery()
                ->filterByPrimaryKeys($stock->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByStock() only accepts arguments of type Stock or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Stock relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function joinStock($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Stock');

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
            $this->addJoinObject($join, 'Stock');
        }

        return $this;
    }

    /**
     * Use the Stock relation Stock object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\StockQuery A secondary query class using the current class as primary query
     */
    public function useStockQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinStock($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Stock', '\Thelia\Model\StockQuery');
    }

    /**
     * Filter the query by a related ContentAssoc object
     *
     * @param   ContentAssoc|PropelObjectCollection $contentAssoc  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   ProductQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByContentAssoc($contentAssoc, $comparison = null)
    {
        if ($contentAssoc instanceof ContentAssoc) {
            return $this
                ->addUsingAlias(ProductPeer::ID, $contentAssoc->getProductId(), $comparison);
        } elseif ($contentAssoc instanceof PropelObjectCollection) {
            return $this
                ->useContentAssocQuery()
                ->filterByPrimaryKeys($contentAssoc->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByContentAssoc() only accepts arguments of type ContentAssoc or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ContentAssoc relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function joinContentAssoc($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ContentAssoc');

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
            $this->addJoinObject($join, 'ContentAssoc');
        }

        return $this;
    }

    /**
     * Use the ContentAssoc relation ContentAssoc object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ContentAssocQuery A secondary query class using the current class as primary query
     */
    public function useContentAssocQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinContentAssoc($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ContentAssoc', '\Thelia\Model\ContentAssocQuery');
    }

    /**
     * Filter the query by a related Image object
     *
     * @param   Image|PropelObjectCollection $image  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   ProductQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByImage($image, $comparison = null)
    {
        if ($image instanceof Image) {
            return $this
                ->addUsingAlias(ProductPeer::ID, $image->getProductId(), $comparison);
        } elseif ($image instanceof PropelObjectCollection) {
            return $this
                ->useImageQuery()
                ->filterByPrimaryKeys($image->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByImage() only accepts arguments of type Image or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Image relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function joinImage($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Image');

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
            $this->addJoinObject($join, 'Image');
        }

        return $this;
    }

    /**
     * Use the Image relation Image object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ImageQuery A secondary query class using the current class as primary query
     */
    public function useImageQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinImage($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Image', '\Thelia\Model\ImageQuery');
    }

    /**
     * Filter the query by a related Document object
     *
     * @param   Document|PropelObjectCollection $document  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   ProductQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByDocument($document, $comparison = null)
    {
        if ($document instanceof Document) {
            return $this
                ->addUsingAlias(ProductPeer::ID, $document->getProductId(), $comparison);
        } elseif ($document instanceof PropelObjectCollection) {
            return $this
                ->useDocumentQuery()
                ->filterByPrimaryKeys($document->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByDocument() only accepts arguments of type Document or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Document relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function joinDocument($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Document');

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
            $this->addJoinObject($join, 'Document');
        }

        return $this;
    }

    /**
     * Use the Document relation Document object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\DocumentQuery A secondary query class using the current class as primary query
     */
    public function useDocumentQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinDocument($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Document', '\Thelia\Model\DocumentQuery');
    }

    /**
     * Filter the query by a related Accessory object
     *
     * @param   Accessory|PropelObjectCollection $accessory  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   ProductQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByAccessoryRelatedByProductId($accessory, $comparison = null)
    {
        if ($accessory instanceof Accessory) {
            return $this
                ->addUsingAlias(ProductPeer::ID, $accessory->getProductId(), $comparison);
        } elseif ($accessory instanceof PropelObjectCollection) {
            return $this
                ->useAccessoryRelatedByProductIdQuery()
                ->filterByPrimaryKeys($accessory->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByAccessoryRelatedByProductId() only accepts arguments of type Accessory or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the AccessoryRelatedByProductId relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function joinAccessoryRelatedByProductId($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('AccessoryRelatedByProductId');

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
            $this->addJoinObject($join, 'AccessoryRelatedByProductId');
        }

        return $this;
    }

    /**
     * Use the AccessoryRelatedByProductId relation Accessory object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\AccessoryQuery A secondary query class using the current class as primary query
     */
    public function useAccessoryRelatedByProductIdQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinAccessoryRelatedByProductId($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'AccessoryRelatedByProductId', '\Thelia\Model\AccessoryQuery');
    }

    /**
     * Filter the query by a related Accessory object
     *
     * @param   Accessory|PropelObjectCollection $accessory  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   ProductQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByAccessoryRelatedByAccessory($accessory, $comparison = null)
    {
        if ($accessory instanceof Accessory) {
            return $this
                ->addUsingAlias(ProductPeer::ID, $accessory->getAccessory(), $comparison);
        } elseif ($accessory instanceof PropelObjectCollection) {
            return $this
                ->useAccessoryRelatedByAccessoryQuery()
                ->filterByPrimaryKeys($accessory->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByAccessoryRelatedByAccessory() only accepts arguments of type Accessory or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the AccessoryRelatedByAccessory relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function joinAccessoryRelatedByAccessory($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('AccessoryRelatedByAccessory');

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
            $this->addJoinObject($join, 'AccessoryRelatedByAccessory');
        }

        return $this;
    }

    /**
     * Use the AccessoryRelatedByAccessory relation Accessory object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\AccessoryQuery A secondary query class using the current class as primary query
     */
    public function useAccessoryRelatedByAccessoryQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinAccessoryRelatedByAccessory($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'AccessoryRelatedByAccessory', '\Thelia\Model\AccessoryQuery');
    }

    /**
     * Filter the query by a related Rewriting object
     *
     * @param   Rewriting|PropelObjectCollection $rewriting  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   ProductQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByRewriting($rewriting, $comparison = null)
    {
        if ($rewriting instanceof Rewriting) {
            return $this
                ->addUsingAlias(ProductPeer::ID, $rewriting->getProductId(), $comparison);
        } elseif ($rewriting instanceof PropelObjectCollection) {
            return $this
                ->useRewritingQuery()
                ->filterByPrimaryKeys($rewriting->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByRewriting() only accepts arguments of type Rewriting or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Rewriting relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function joinRewriting($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Rewriting');

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
            $this->addJoinObject($join, 'Rewriting');
        }

        return $this;
    }

    /**
     * Use the Rewriting relation Rewriting object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\RewritingQuery A secondary query class using the current class as primary query
     */
    public function useRewritingQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinRewriting($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Rewriting', '\Thelia\Model\RewritingQuery');
    }

    /**
     * Filter the query by a related ProductI18n object
     *
     * @param   ProductI18n|PropelObjectCollection $productI18n  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   ProductQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByProductI18n($productI18n, $comparison = null)
    {
        if ($productI18n instanceof ProductI18n) {
            return $this
                ->addUsingAlias(ProductPeer::ID, $productI18n->getId(), $comparison);
        } elseif ($productI18n instanceof PropelObjectCollection) {
            return $this
                ->useProductI18nQuery()
                ->filterByPrimaryKeys($productI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByProductI18n() only accepts arguments of type ProductI18n or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ProductI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function joinProductI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ProductI18n');

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
            $this->addJoinObject($join, 'ProductI18n');
        }

        return $this;
    }

    /**
     * Use the ProductI18n relation ProductI18n object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ProductI18nQuery A secondary query class using the current class as primary query
     */
    public function useProductI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinProductI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ProductI18n', '\Thelia\Model\ProductI18nQuery');
    }

    /**
     * Filter the query by a related ProductVersion object
     *
     * @param   ProductVersion|PropelObjectCollection $productVersion  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   ProductQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByProductVersion($productVersion, $comparison = null)
    {
        if ($productVersion instanceof ProductVersion) {
            return $this
                ->addUsingAlias(ProductPeer::ID, $productVersion->getId(), $comparison);
        } elseif ($productVersion instanceof PropelObjectCollection) {
            return $this
                ->useProductVersionQuery()
                ->filterByPrimaryKeys($productVersion->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByProductVersion() only accepts arguments of type ProductVersion or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ProductVersion relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function joinProductVersion($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ProductVersion');

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
            $this->addJoinObject($join, 'ProductVersion');
        }

        return $this;
    }

    /**
     * Use the ProductVersion relation ProductVersion object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ProductVersionQuery A secondary query class using the current class as primary query
     */
    public function useProductVersionQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinProductVersion($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ProductVersion', '\Thelia\Model\ProductVersionQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   Product $product Object to remove from the list of results
     *
     * @return ProductQuery The current query, for fluid interface
     */
    public function prune($product = null)
    {
        if ($product) {
            $this->addUsingAlias(ProductPeer::ID, $product->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    // timestampable behavior

    /**
     * Filter by the latest updated
     *
     * @param      int $nbDays Maximum age of the latest update in days
     *
     * @return     ProductQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(ProductPeer::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ProductQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(ProductPeer::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ProductQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(ProductPeer::UPDATED_AT);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ProductQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(ProductPeer::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by create date desc
     *
     * @return     ProductQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(ProductPeer::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ProductQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(ProductPeer::CREATED_AT);
    }
    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ProductQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_EN', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'ProductI18n';

        return $this
            ->joinProductI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ProductQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_EN', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('ProductI18n');
        $this->with['ProductI18n']->setIsWithOneToMany(false);

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
     * @return    ProductI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_EN', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ProductI18n', 'Thelia\Model\ProductI18nQuery');
    }

}
