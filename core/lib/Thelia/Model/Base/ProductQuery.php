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
use Thelia\Model\Product as ChildProduct;
use Thelia\Model\ProductI18nQuery as ChildProductI18nQuery;
use Thelia\Model\ProductQuery as ChildProductQuery;
use Thelia\Model\Map\ProductTableMap;

/**
 * Base class that represents a query for the 'product' table.
 *
 *
 *
 * @method     ChildProductQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildProductQuery orderByTaxRuleId($order = Criteria::ASC) Order by the tax_rule_id column
 * @method     ChildProductQuery orderByRef($order = Criteria::ASC) Order by the ref column
 * @method     ChildProductQuery orderByVisible($order = Criteria::ASC) Order by the visible column
 * @method     ChildProductQuery orderByPosition($order = Criteria::ASC) Order by the position column
 * @method     ChildProductQuery orderByTemplateId($order = Criteria::ASC) Order by the template_id column
 * @method     ChildProductQuery orderByBrandId($order = Criteria::ASC) Order by the brand_id column
 * @method     ChildProductQuery orderByVirtual($order = Criteria::ASC) Order by the virtual column
 * @method     ChildProductQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildProductQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 * @method     ChildProductQuery orderByVersion($order = Criteria::ASC) Order by the version column
 * @method     ChildProductQuery orderByVersionCreatedAt($order = Criteria::ASC) Order by the version_created_at column
 * @method     ChildProductQuery orderByVersionCreatedBy($order = Criteria::ASC) Order by the version_created_by column
 *
 * @method     ChildProductQuery groupById() Group by the id column
 * @method     ChildProductQuery groupByTaxRuleId() Group by the tax_rule_id column
 * @method     ChildProductQuery groupByRef() Group by the ref column
 * @method     ChildProductQuery groupByVisible() Group by the visible column
 * @method     ChildProductQuery groupByPosition() Group by the position column
 * @method     ChildProductQuery groupByTemplateId() Group by the template_id column
 * @method     ChildProductQuery groupByBrandId() Group by the brand_id column
 * @method     ChildProductQuery groupByVirtual() Group by the virtual column
 * @method     ChildProductQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildProductQuery groupByUpdatedAt() Group by the updated_at column
 * @method     ChildProductQuery groupByVersion() Group by the version column
 * @method     ChildProductQuery groupByVersionCreatedAt() Group by the version_created_at column
 * @method     ChildProductQuery groupByVersionCreatedBy() Group by the version_created_by column
 *
 * @method     ChildProductQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildProductQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildProductQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildProductQuery leftJoinTaxRule($relationAlias = null) Adds a LEFT JOIN clause to the query using the TaxRule relation
 * @method     ChildProductQuery rightJoinTaxRule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the TaxRule relation
 * @method     ChildProductQuery innerJoinTaxRule($relationAlias = null) Adds a INNER JOIN clause to the query using the TaxRule relation
 *
 * @method     ChildProductQuery leftJoinTemplate($relationAlias = null) Adds a LEFT JOIN clause to the query using the Template relation
 * @method     ChildProductQuery rightJoinTemplate($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Template relation
 * @method     ChildProductQuery innerJoinTemplate($relationAlias = null) Adds a INNER JOIN clause to the query using the Template relation
 *
 * @method     ChildProductQuery leftJoinBrand($relationAlias = null) Adds a LEFT JOIN clause to the query using the Brand relation
 * @method     ChildProductQuery rightJoinBrand($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Brand relation
 * @method     ChildProductQuery innerJoinBrand($relationAlias = null) Adds a INNER JOIN clause to the query using the Brand relation
 *
 * @method     ChildProductQuery leftJoinProductCategory($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProductCategory relation
 * @method     ChildProductQuery rightJoinProductCategory($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProductCategory relation
 * @method     ChildProductQuery innerJoinProductCategory($relationAlias = null) Adds a INNER JOIN clause to the query using the ProductCategory relation
 *
 * @method     ChildProductQuery leftJoinFeatureProduct($relationAlias = null) Adds a LEFT JOIN clause to the query using the FeatureProduct relation
 * @method     ChildProductQuery rightJoinFeatureProduct($relationAlias = null) Adds a RIGHT JOIN clause to the query using the FeatureProduct relation
 * @method     ChildProductQuery innerJoinFeatureProduct($relationAlias = null) Adds a INNER JOIN clause to the query using the FeatureProduct relation
 *
 * @method     ChildProductQuery leftJoinProductSaleElements($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProductSaleElements relation
 * @method     ChildProductQuery rightJoinProductSaleElements($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProductSaleElements relation
 * @method     ChildProductQuery innerJoinProductSaleElements($relationAlias = null) Adds a INNER JOIN clause to the query using the ProductSaleElements relation
 *
 * @method     ChildProductQuery leftJoinProductImage($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProductImage relation
 * @method     ChildProductQuery rightJoinProductImage($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProductImage relation
 * @method     ChildProductQuery innerJoinProductImage($relationAlias = null) Adds a INNER JOIN clause to the query using the ProductImage relation
 *
 * @method     ChildProductQuery leftJoinProductDocument($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProductDocument relation
 * @method     ChildProductQuery rightJoinProductDocument($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProductDocument relation
 * @method     ChildProductQuery innerJoinProductDocument($relationAlias = null) Adds a INNER JOIN clause to the query using the ProductDocument relation
 *
 * @method     ChildProductQuery leftJoinAccessoryRelatedByProductId($relationAlias = null) Adds a LEFT JOIN clause to the query using the AccessoryRelatedByProductId relation
 * @method     ChildProductQuery rightJoinAccessoryRelatedByProductId($relationAlias = null) Adds a RIGHT JOIN clause to the query using the AccessoryRelatedByProductId relation
 * @method     ChildProductQuery innerJoinAccessoryRelatedByProductId($relationAlias = null) Adds a INNER JOIN clause to the query using the AccessoryRelatedByProductId relation
 *
 * @method     ChildProductQuery leftJoinAccessoryRelatedByAccessory($relationAlias = null) Adds a LEFT JOIN clause to the query using the AccessoryRelatedByAccessory relation
 * @method     ChildProductQuery rightJoinAccessoryRelatedByAccessory($relationAlias = null) Adds a RIGHT JOIN clause to the query using the AccessoryRelatedByAccessory relation
 * @method     ChildProductQuery innerJoinAccessoryRelatedByAccessory($relationAlias = null) Adds a INNER JOIN clause to the query using the AccessoryRelatedByAccessory relation
 *
 * @method     ChildProductQuery leftJoinCartItem($relationAlias = null) Adds a LEFT JOIN clause to the query using the CartItem relation
 * @method     ChildProductQuery rightJoinCartItem($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CartItem relation
 * @method     ChildProductQuery innerJoinCartItem($relationAlias = null) Adds a INNER JOIN clause to the query using the CartItem relation
 *
 * @method     ChildProductQuery leftJoinProductAssociatedContent($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProductAssociatedContent relation
 * @method     ChildProductQuery rightJoinProductAssociatedContent($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProductAssociatedContent relation
 * @method     ChildProductQuery innerJoinProductAssociatedContent($relationAlias = null) Adds a INNER JOIN clause to the query using the ProductAssociatedContent relation
 *
 * @method     ChildProductQuery leftJoinSaleProduct($relationAlias = null) Adds a LEFT JOIN clause to the query using the SaleProduct relation
 * @method     ChildProductQuery rightJoinSaleProduct($relationAlias = null) Adds a RIGHT JOIN clause to the query using the SaleProduct relation
 * @method     ChildProductQuery innerJoinSaleProduct($relationAlias = null) Adds a INNER JOIN clause to the query using the SaleProduct relation
 *
 * @method     ChildProductQuery leftJoinProductI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProductI18n relation
 * @method     ChildProductQuery rightJoinProductI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProductI18n relation
 * @method     ChildProductQuery innerJoinProductI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the ProductI18n relation
 *
 * @method     ChildProductQuery leftJoinProductVersion($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProductVersion relation
 * @method     ChildProductQuery rightJoinProductVersion($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProductVersion relation
 * @method     ChildProductQuery innerJoinProductVersion($relationAlias = null) Adds a INNER JOIN clause to the query using the ProductVersion relation
 *
 * @method     ChildProduct findOne(ConnectionInterface $con = null) Return the first ChildProduct matching the query
 * @method     ChildProduct findOneOrCreate(ConnectionInterface $con = null) Return the first ChildProduct matching the query, or a new ChildProduct object populated from the query conditions when no match is found
 *
 * @method     ChildProduct findOneById(int $id) Return the first ChildProduct filtered by the id column
 * @method     ChildProduct findOneByTaxRuleId(int $tax_rule_id) Return the first ChildProduct filtered by the tax_rule_id column
 * @method     ChildProduct findOneByRef(string $ref) Return the first ChildProduct filtered by the ref column
 * @method     ChildProduct findOneByVisible(int $visible) Return the first ChildProduct filtered by the visible column
 * @method     ChildProduct findOneByPosition(int $position) Return the first ChildProduct filtered by the position column
 * @method     ChildProduct findOneByTemplateId(int $template_id) Return the first ChildProduct filtered by the template_id column
 * @method     ChildProduct findOneByBrandId(int $brand_id) Return the first ChildProduct filtered by the brand_id column
 * @method     ChildProduct findOneByVirtual(int $virtual) Return the first ChildProduct filtered by the virtual column
 * @method     ChildProduct findOneByCreatedAt(string $created_at) Return the first ChildProduct filtered by the created_at column
 * @method     ChildProduct findOneByUpdatedAt(string $updated_at) Return the first ChildProduct filtered by the updated_at column
 * @method     ChildProduct findOneByVersion(int $version) Return the first ChildProduct filtered by the version column
 * @method     ChildProduct findOneByVersionCreatedAt(string $version_created_at) Return the first ChildProduct filtered by the version_created_at column
 * @method     ChildProduct findOneByVersionCreatedBy(string $version_created_by) Return the first ChildProduct filtered by the version_created_by column
 *
 * @method     array findById(int $id) Return ChildProduct objects filtered by the id column
 * @method     array findByTaxRuleId(int $tax_rule_id) Return ChildProduct objects filtered by the tax_rule_id column
 * @method     array findByRef(string $ref) Return ChildProduct objects filtered by the ref column
 * @method     array findByVisible(int $visible) Return ChildProduct objects filtered by the visible column
 * @method     array findByPosition(int $position) Return ChildProduct objects filtered by the position column
 * @method     array findByTemplateId(int $template_id) Return ChildProduct objects filtered by the template_id column
 * @method     array findByBrandId(int $brand_id) Return ChildProduct objects filtered by the brand_id column
 * @method     array findByVirtual(int $virtual) Return ChildProduct objects filtered by the virtual column
 * @method     array findByCreatedAt(string $created_at) Return ChildProduct objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildProduct objects filtered by the updated_at column
 * @method     array findByVersion(int $version) Return ChildProduct objects filtered by the version column
 * @method     array findByVersionCreatedAt(string $version_created_at) Return ChildProduct objects filtered by the version_created_at column
 * @method     array findByVersionCreatedBy(string $version_created_by) Return ChildProduct objects filtered by the version_created_by column
 *
 */
abstract class ProductQuery extends ModelCriteria
{

    // versionable behavior

    /**
     * Whether the versioning is enabled
     */
    static $isVersioningEnabled = true;

    /**
     * Initializes internal state of \Thelia\Model\Base\ProductQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Product', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildProductQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildProductQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\ProductQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\ProductQuery();
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
     * @return ChildProduct|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = ProductTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(ProductTableMap::DATABASE_NAME);
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
     * @return   ChildProduct A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `TAX_RULE_ID`, `REF`, `VISIBLE`, `POSITION`, `TEMPLATE_ID`, `BRAND_ID`, `VIRTUAL`, `CREATED_AT`, `UPDATED_AT`, `VERSION`, `VERSION_CREATED_AT`, `VERSION_CREATED_BY` FROM `product` WHERE `ID` = :p0';
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
            $obj = new ChildProduct();
            $obj->hydrate($row);
            ProductTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildProduct|array|mixed the result, formatted by the current formatter
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
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(ProductTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(ProductTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(ProductTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(ProductTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductTableMap::ID, $id, $comparison);
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
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByTaxRuleId($taxRuleId = null, $comparison = null)
    {
        if (is_array($taxRuleId)) {
            $useMinMax = false;
            if (isset($taxRuleId['min'])) {
                $this->addUsingAlias(ProductTableMap::TAX_RULE_ID, $taxRuleId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($taxRuleId['max'])) {
                $this->addUsingAlias(ProductTableMap::TAX_RULE_ID, $taxRuleId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductTableMap::TAX_RULE_ID, $taxRuleId, $comparison);
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
     * @return ChildProductQuery The current query, for fluid interface
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

        return $this->addUsingAlias(ProductTableMap::REF, $ref, $comparison);
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
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByVisible($visible = null, $comparison = null)
    {
        if (is_array($visible)) {
            $useMinMax = false;
            if (isset($visible['min'])) {
                $this->addUsingAlias(ProductTableMap::VISIBLE, $visible['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($visible['max'])) {
                $this->addUsingAlias(ProductTableMap::VISIBLE, $visible['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductTableMap::VISIBLE, $visible, $comparison);
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
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        if (is_array($position)) {
            $useMinMax = false;
            if (isset($position['min'])) {
                $this->addUsingAlias(ProductTableMap::POSITION, $position['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($position['max'])) {
                $this->addUsingAlias(ProductTableMap::POSITION, $position['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductTableMap::POSITION, $position, $comparison);
    }

    /**
     * Filter the query on the template_id column
     *
     * Example usage:
     * <code>
     * $query->filterByTemplateId(1234); // WHERE template_id = 1234
     * $query->filterByTemplateId(array(12, 34)); // WHERE template_id IN (12, 34)
     * $query->filterByTemplateId(array('min' => 12)); // WHERE template_id > 12
     * </code>
     *
     * @see       filterByTemplate()
     *
     * @param     mixed $templateId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByTemplateId($templateId = null, $comparison = null)
    {
        if (is_array($templateId)) {
            $useMinMax = false;
            if (isset($templateId['min'])) {
                $this->addUsingAlias(ProductTableMap::TEMPLATE_ID, $templateId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($templateId['max'])) {
                $this->addUsingAlias(ProductTableMap::TEMPLATE_ID, $templateId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductTableMap::TEMPLATE_ID, $templateId, $comparison);
    }

    /**
     * Filter the query on the brand_id column
     *
     * Example usage:
     * <code>
     * $query->filterByBrandId(1234); // WHERE brand_id = 1234
     * $query->filterByBrandId(array(12, 34)); // WHERE brand_id IN (12, 34)
     * $query->filterByBrandId(array('min' => 12)); // WHERE brand_id > 12
     * </code>
     *
     * @see       filterByBrand()
     *
     * @param     mixed $brandId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByBrandId($brandId = null, $comparison = null)
    {
        if (is_array($brandId)) {
            $useMinMax = false;
            if (isset($brandId['min'])) {
                $this->addUsingAlias(ProductTableMap::BRAND_ID, $brandId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($brandId['max'])) {
                $this->addUsingAlias(ProductTableMap::BRAND_ID, $brandId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductTableMap::BRAND_ID, $brandId, $comparison);
    }

    /**
     * Filter the query on the virtual column
     *
     * Example usage:
     * <code>
     * $query->filterByVirtual(1234); // WHERE virtual = 1234
     * $query->filterByVirtual(array(12, 34)); // WHERE virtual IN (12, 34)
     * $query->filterByVirtual(array('min' => 12)); // WHERE virtual > 12
     * </code>
     *
     * @param     mixed $virtual The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByVirtual($virtual = null, $comparison = null)
    {
        if (is_array($virtual)) {
            $useMinMax = false;
            if (isset($virtual['min'])) {
                $this->addUsingAlias(ProductTableMap::VIRTUAL, $virtual['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($virtual['max'])) {
                $this->addUsingAlias(ProductTableMap::VIRTUAL, $virtual['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductTableMap::VIRTUAL, $virtual, $comparison);
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
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(ProductTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(ProductTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(ProductTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(ProductTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductTableMap::UPDATED_AT, $updatedAt, $comparison);
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
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByVersion($version = null, $comparison = null)
    {
        if (is_array($version)) {
            $useMinMax = false;
            if (isset($version['min'])) {
                $this->addUsingAlias(ProductTableMap::VERSION, $version['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($version['max'])) {
                $this->addUsingAlias(ProductTableMap::VERSION, $version['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductTableMap::VERSION, $version, $comparison);
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
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByVersionCreatedAt($versionCreatedAt = null, $comparison = null)
    {
        if (is_array($versionCreatedAt)) {
            $useMinMax = false;
            if (isset($versionCreatedAt['min'])) {
                $this->addUsingAlias(ProductTableMap::VERSION_CREATED_AT, $versionCreatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($versionCreatedAt['max'])) {
                $this->addUsingAlias(ProductTableMap::VERSION_CREATED_AT, $versionCreatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductTableMap::VERSION_CREATED_AT, $versionCreatedAt, $comparison);
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
     * @return ChildProductQuery The current query, for fluid interface
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

        return $this->addUsingAlias(ProductTableMap::VERSION_CREATED_BY, $versionCreatedBy, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\TaxRule object
     *
     * @param \Thelia\Model\TaxRule|ObjectCollection $taxRule The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByTaxRule($taxRule, $comparison = null)
    {
        if ($taxRule instanceof \Thelia\Model\TaxRule) {
            return $this
                ->addUsingAlias(ProductTableMap::TAX_RULE_ID, $taxRule->getId(), $comparison);
        } elseif ($taxRule instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(ProductTableMap::TAX_RULE_ID, $taxRule->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByTaxRule() only accepts arguments of type \Thelia\Model\TaxRule or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the TaxRule relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProductQuery The current query, for fluid interface
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
     * @see useQuery()
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
     * Filter the query by a related \Thelia\Model\Template object
     *
     * @param \Thelia\Model\Template|ObjectCollection $template The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByTemplate($template, $comparison = null)
    {
        if ($template instanceof \Thelia\Model\Template) {
            return $this
                ->addUsingAlias(ProductTableMap::TEMPLATE_ID, $template->getId(), $comparison);
        } elseif ($template instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(ProductTableMap::TEMPLATE_ID, $template->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByTemplate() only accepts arguments of type \Thelia\Model\Template or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Template relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function joinTemplate($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Template');

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
            $this->addJoinObject($join, 'Template');
        }

        return $this;
    }

    /**
     * Use the Template relation Template object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\TemplateQuery A secondary query class using the current class as primary query
     */
    public function useTemplateQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinTemplate($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Template', '\Thelia\Model\TemplateQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Brand object
     *
     * @param \Thelia\Model\Brand|ObjectCollection $brand The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByBrand($brand, $comparison = null)
    {
        if ($brand instanceof \Thelia\Model\Brand) {
            return $this
                ->addUsingAlias(ProductTableMap::BRAND_ID, $brand->getId(), $comparison);
        } elseif ($brand instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(ProductTableMap::BRAND_ID, $brand->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByBrand() only accepts arguments of type \Thelia\Model\Brand or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Brand relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function joinBrand($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Brand');

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
            $this->addJoinObject($join, 'Brand');
        }

        return $this;
    }

    /**
     * Use the Brand relation Brand object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\BrandQuery A secondary query class using the current class as primary query
     */
    public function useBrandQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinBrand($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Brand', '\Thelia\Model\BrandQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ProductCategory object
     *
     * @param \Thelia\Model\ProductCategory|ObjectCollection $productCategory  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByProductCategory($productCategory, $comparison = null)
    {
        if ($productCategory instanceof \Thelia\Model\ProductCategory) {
            return $this
                ->addUsingAlias(ProductTableMap::ID, $productCategory->getProductId(), $comparison);
        } elseif ($productCategory instanceof ObjectCollection) {
            return $this
                ->useProductCategoryQuery()
                ->filterByPrimaryKeys($productCategory->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByProductCategory() only accepts arguments of type \Thelia\Model\ProductCategory or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ProductCategory relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProductQuery The current query, for fluid interface
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
     * @see useQuery()
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
     * Filter the query by a related \Thelia\Model\FeatureProduct object
     *
     * @param \Thelia\Model\FeatureProduct|ObjectCollection $featureProduct  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByFeatureProduct($featureProduct, $comparison = null)
    {
        if ($featureProduct instanceof \Thelia\Model\FeatureProduct) {
            return $this
                ->addUsingAlias(ProductTableMap::ID, $featureProduct->getProductId(), $comparison);
        } elseif ($featureProduct instanceof ObjectCollection) {
            return $this
                ->useFeatureProductQuery()
                ->filterByPrimaryKeys($featureProduct->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByFeatureProduct() only accepts arguments of type \Thelia\Model\FeatureProduct or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the FeatureProduct relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function joinFeatureProduct($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('FeatureProduct');

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
            $this->addJoinObject($join, 'FeatureProduct');
        }

        return $this;
    }

    /**
     * Use the FeatureProduct relation FeatureProduct object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\FeatureProductQuery A secondary query class using the current class as primary query
     */
    public function useFeatureProductQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinFeatureProduct($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'FeatureProduct', '\Thelia\Model\FeatureProductQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ProductSaleElements object
     *
     * @param \Thelia\Model\ProductSaleElements|ObjectCollection $productSaleElements  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByProductSaleElements($productSaleElements, $comparison = null)
    {
        if ($productSaleElements instanceof \Thelia\Model\ProductSaleElements) {
            return $this
                ->addUsingAlias(ProductTableMap::ID, $productSaleElements->getProductId(), $comparison);
        } elseif ($productSaleElements instanceof ObjectCollection) {
            return $this
                ->useProductSaleElementsQuery()
                ->filterByPrimaryKeys($productSaleElements->getPrimaryKeys())
                ->endUse();
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
     * @return ChildProductQuery The current query, for fluid interface
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
     * Filter the query by a related \Thelia\Model\ProductImage object
     *
     * @param \Thelia\Model\ProductImage|ObjectCollection $productImage  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByProductImage($productImage, $comparison = null)
    {
        if ($productImage instanceof \Thelia\Model\ProductImage) {
            return $this
                ->addUsingAlias(ProductTableMap::ID, $productImage->getProductId(), $comparison);
        } elseif ($productImage instanceof ObjectCollection) {
            return $this
                ->useProductImageQuery()
                ->filterByPrimaryKeys($productImage->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByProductImage() only accepts arguments of type \Thelia\Model\ProductImage or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ProductImage relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function joinProductImage($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ProductImage');

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
            $this->addJoinObject($join, 'ProductImage');
        }

        return $this;
    }

    /**
     * Use the ProductImage relation ProductImage object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ProductImageQuery A secondary query class using the current class as primary query
     */
    public function useProductImageQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinProductImage($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ProductImage', '\Thelia\Model\ProductImageQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ProductDocument object
     *
     * @param \Thelia\Model\ProductDocument|ObjectCollection $productDocument  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByProductDocument($productDocument, $comparison = null)
    {
        if ($productDocument instanceof \Thelia\Model\ProductDocument) {
            return $this
                ->addUsingAlias(ProductTableMap::ID, $productDocument->getProductId(), $comparison);
        } elseif ($productDocument instanceof ObjectCollection) {
            return $this
                ->useProductDocumentQuery()
                ->filterByPrimaryKeys($productDocument->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByProductDocument() only accepts arguments of type \Thelia\Model\ProductDocument or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ProductDocument relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function joinProductDocument($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ProductDocument');

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
            $this->addJoinObject($join, 'ProductDocument');
        }

        return $this;
    }

    /**
     * Use the ProductDocument relation ProductDocument object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ProductDocumentQuery A secondary query class using the current class as primary query
     */
    public function useProductDocumentQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinProductDocument($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ProductDocument', '\Thelia\Model\ProductDocumentQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Accessory object
     *
     * @param \Thelia\Model\Accessory|ObjectCollection $accessory  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByAccessoryRelatedByProductId($accessory, $comparison = null)
    {
        if ($accessory instanceof \Thelia\Model\Accessory) {
            return $this
                ->addUsingAlias(ProductTableMap::ID, $accessory->getProductId(), $comparison);
        } elseif ($accessory instanceof ObjectCollection) {
            return $this
                ->useAccessoryRelatedByProductIdQuery()
                ->filterByPrimaryKeys($accessory->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByAccessoryRelatedByProductId() only accepts arguments of type \Thelia\Model\Accessory or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the AccessoryRelatedByProductId relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProductQuery The current query, for fluid interface
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
     * @see useQuery()
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
     * Filter the query by a related \Thelia\Model\Accessory object
     *
     * @param \Thelia\Model\Accessory|ObjectCollection $accessory  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByAccessoryRelatedByAccessory($accessory, $comparison = null)
    {
        if ($accessory instanceof \Thelia\Model\Accessory) {
            return $this
                ->addUsingAlias(ProductTableMap::ID, $accessory->getAccessory(), $comparison);
        } elseif ($accessory instanceof ObjectCollection) {
            return $this
                ->useAccessoryRelatedByAccessoryQuery()
                ->filterByPrimaryKeys($accessory->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByAccessoryRelatedByAccessory() only accepts arguments of type \Thelia\Model\Accessory or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the AccessoryRelatedByAccessory relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProductQuery The current query, for fluid interface
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
     * @see useQuery()
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
     * Filter the query by a related \Thelia\Model\CartItem object
     *
     * @param \Thelia\Model\CartItem|ObjectCollection $cartItem  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByCartItem($cartItem, $comparison = null)
    {
        if ($cartItem instanceof \Thelia\Model\CartItem) {
            return $this
                ->addUsingAlias(ProductTableMap::ID, $cartItem->getProductId(), $comparison);
        } elseif ($cartItem instanceof ObjectCollection) {
            return $this
                ->useCartItemQuery()
                ->filterByPrimaryKeys($cartItem->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCartItem() only accepts arguments of type \Thelia\Model\CartItem or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CartItem relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function joinCartItem($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CartItem');

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
            $this->addJoinObject($join, 'CartItem');
        }

        return $this;
    }

    /**
     * Use the CartItem relation CartItem object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CartItemQuery A secondary query class using the current class as primary query
     */
    public function useCartItemQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCartItem($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CartItem', '\Thelia\Model\CartItemQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ProductAssociatedContent object
     *
     * @param \Thelia\Model\ProductAssociatedContent|ObjectCollection $productAssociatedContent  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByProductAssociatedContent($productAssociatedContent, $comparison = null)
    {
        if ($productAssociatedContent instanceof \Thelia\Model\ProductAssociatedContent) {
            return $this
                ->addUsingAlias(ProductTableMap::ID, $productAssociatedContent->getProductId(), $comparison);
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
     * @return ChildProductQuery The current query, for fluid interface
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
     * Filter the query by a related \Thelia\Model\SaleProduct object
     *
     * @param \Thelia\Model\SaleProduct|ObjectCollection $saleProduct  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterBySaleProduct($saleProduct, $comparison = null)
    {
        if ($saleProduct instanceof \Thelia\Model\SaleProduct) {
            return $this
                ->addUsingAlias(ProductTableMap::ID, $saleProduct->getProductId(), $comparison);
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
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function joinSaleProduct($relationAlias = null, $joinType = Criteria::INNER_JOIN)
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
    public function useSaleProductQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinSaleProduct($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'SaleProduct', '\Thelia\Model\SaleProductQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ProductI18n object
     *
     * @param \Thelia\Model\ProductI18n|ObjectCollection $productI18n  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByProductI18n($productI18n, $comparison = null)
    {
        if ($productI18n instanceof \Thelia\Model\ProductI18n) {
            return $this
                ->addUsingAlias(ProductTableMap::ID, $productI18n->getId(), $comparison);
        } elseif ($productI18n instanceof ObjectCollection) {
            return $this
                ->useProductI18nQuery()
                ->filterByPrimaryKeys($productI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByProductI18n() only accepts arguments of type \Thelia\Model\ProductI18n or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ProductI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProductQuery The current query, for fluid interface
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
     * @see useQuery()
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
     * Filter the query by a related \Thelia\Model\ProductVersion object
     *
     * @param \Thelia\Model\ProductVersion|ObjectCollection $productVersion  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByProductVersion($productVersion, $comparison = null)
    {
        if ($productVersion instanceof \Thelia\Model\ProductVersion) {
            return $this
                ->addUsingAlias(ProductTableMap::ID, $productVersion->getId(), $comparison);
        } elseif ($productVersion instanceof ObjectCollection) {
            return $this
                ->useProductVersionQuery()
                ->filterByPrimaryKeys($productVersion->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByProductVersion() only accepts arguments of type \Thelia\Model\ProductVersion or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ProductVersion relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProductQuery The current query, for fluid interface
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
     * @see useQuery()
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
     * Filter the query by a related Category object
     * using the product_category table as cross reference
     *
     * @param Category $category the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByCategory($category, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useProductCategoryQuery()
            ->filterByCategory($category, $comparison)
            ->endUse();
    }

    /**
     * Filter the query by a related Product object
     * using the accessory table as cross reference
     *
     * @param Product $product the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByProductRelatedByAccessory($product, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useAccessoryRelatedByProductIdQuery()
            ->filterByProductRelatedByAccessory($product, $comparison)
            ->endUse();
    }

    /**
     * Filter the query by a related Product object
     * using the accessory table as cross reference
     *
     * @param Product $product the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function filterByProductRelatedByProductId($product, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useAccessoryRelatedByAccessoryQuery()
            ->filterByProductRelatedByProductId($product, $comparison)
            ->endUse();
    }

    /**
     * Exclude object from result
     *
     * @param   ChildProduct $product Object to remove from the list of results
     *
     * @return ChildProductQuery The current query, for fluid interface
     */
    public function prune($product = null)
    {
        if ($product) {
            $this->addUsingAlias(ProductTableMap::ID, $product->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the product table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ProductTableMap::DATABASE_NAME);
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
            ProductTableMap::clearInstancePool();
            ProductTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildProduct or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildProduct object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(ProductTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(ProductTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        ProductTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            ProductTableMap::clearRelatedInstancePool();
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
     * @return     ChildProductQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(ProductTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildProductQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(ProductTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildProductQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(ProductTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildProductQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(ProductTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildProductQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(ProductTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildProductQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(ProductTableMap::CREATED_AT);
    }

    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildProductQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
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
     * @return    ChildProductQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
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
     * @return    ChildProductI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ProductI18n', '\Thelia\Model\ProductI18nQuery');
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

} // ProductQuery
