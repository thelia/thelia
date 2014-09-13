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
use Thelia\Model\ProductVersion as ChildProductVersion;
use Thelia\Model\ProductVersionQuery as ChildProductVersionQuery;
use Thelia\Model\Map\ProductVersionTableMap;

/**
 * Base class that represents a query for the 'product_version' table.
 *
 *
 *
 * @method     ChildProductVersionQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildProductVersionQuery orderByTaxRuleId($order = Criteria::ASC) Order by the tax_rule_id column
 * @method     ChildProductVersionQuery orderByRef($order = Criteria::ASC) Order by the ref column
 * @method     ChildProductVersionQuery orderByVisible($order = Criteria::ASC) Order by the visible column
 * @method     ChildProductVersionQuery orderByPosition($order = Criteria::ASC) Order by the position column
 * @method     ChildProductVersionQuery orderByTemplateId($order = Criteria::ASC) Order by the template_id column
 * @method     ChildProductVersionQuery orderByBrandId($order = Criteria::ASC) Order by the brand_id column
 * @method     ChildProductVersionQuery orderByVirtual($order = Criteria::ASC) Order by the virtual column
 * @method     ChildProductVersionQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildProductVersionQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 * @method     ChildProductVersionQuery orderByVersion($order = Criteria::ASC) Order by the version column
 * @method     ChildProductVersionQuery orderByVersionCreatedAt($order = Criteria::ASC) Order by the version_created_at column
 * @method     ChildProductVersionQuery orderByVersionCreatedBy($order = Criteria::ASC) Order by the version_created_by column
 *
 * @method     ChildProductVersionQuery groupById() Group by the id column
 * @method     ChildProductVersionQuery groupByTaxRuleId() Group by the tax_rule_id column
 * @method     ChildProductVersionQuery groupByRef() Group by the ref column
 * @method     ChildProductVersionQuery groupByVisible() Group by the visible column
 * @method     ChildProductVersionQuery groupByPosition() Group by the position column
 * @method     ChildProductVersionQuery groupByTemplateId() Group by the template_id column
 * @method     ChildProductVersionQuery groupByBrandId() Group by the brand_id column
 * @method     ChildProductVersionQuery groupByVirtual() Group by the virtual column
 * @method     ChildProductVersionQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildProductVersionQuery groupByUpdatedAt() Group by the updated_at column
 * @method     ChildProductVersionQuery groupByVersion() Group by the version column
 * @method     ChildProductVersionQuery groupByVersionCreatedAt() Group by the version_created_at column
 * @method     ChildProductVersionQuery groupByVersionCreatedBy() Group by the version_created_by column
 *
 * @method     ChildProductVersionQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildProductVersionQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildProductVersionQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildProductVersionQuery leftJoinProduct($relationAlias = null) Adds a LEFT JOIN clause to the query using the Product relation
 * @method     ChildProductVersionQuery rightJoinProduct($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Product relation
 * @method     ChildProductVersionQuery innerJoinProduct($relationAlias = null) Adds a INNER JOIN clause to the query using the Product relation
 *
 * @method     ChildProductVersion findOne(ConnectionInterface $con = null) Return the first ChildProductVersion matching the query
 * @method     ChildProductVersion findOneOrCreate(ConnectionInterface $con = null) Return the first ChildProductVersion matching the query, or a new ChildProductVersion object populated from the query conditions when no match is found
 *
 * @method     ChildProductVersion findOneById(int $id) Return the first ChildProductVersion filtered by the id column
 * @method     ChildProductVersion findOneByTaxRuleId(int $tax_rule_id) Return the first ChildProductVersion filtered by the tax_rule_id column
 * @method     ChildProductVersion findOneByRef(string $ref) Return the first ChildProductVersion filtered by the ref column
 * @method     ChildProductVersion findOneByVisible(int $visible) Return the first ChildProductVersion filtered by the visible column
 * @method     ChildProductVersion findOneByPosition(int $position) Return the first ChildProductVersion filtered by the position column
 * @method     ChildProductVersion findOneByTemplateId(int $template_id) Return the first ChildProductVersion filtered by the template_id column
 * @method     ChildProductVersion findOneByBrandId(int $brand_id) Return the first ChildProductVersion filtered by the brand_id column
 * @method     ChildProductVersion findOneByVirtual(int $virtual) Return the first ChildProductVersion filtered by the virtual column
 * @method     ChildProductVersion findOneByCreatedAt(string $created_at) Return the first ChildProductVersion filtered by the created_at column
 * @method     ChildProductVersion findOneByUpdatedAt(string $updated_at) Return the first ChildProductVersion filtered by the updated_at column
 * @method     ChildProductVersion findOneByVersion(int $version) Return the first ChildProductVersion filtered by the version column
 * @method     ChildProductVersion findOneByVersionCreatedAt(string $version_created_at) Return the first ChildProductVersion filtered by the version_created_at column
 * @method     ChildProductVersion findOneByVersionCreatedBy(string $version_created_by) Return the first ChildProductVersion filtered by the version_created_by column
 *
 * @method     array findById(int $id) Return ChildProductVersion objects filtered by the id column
 * @method     array findByTaxRuleId(int $tax_rule_id) Return ChildProductVersion objects filtered by the tax_rule_id column
 * @method     array findByRef(string $ref) Return ChildProductVersion objects filtered by the ref column
 * @method     array findByVisible(int $visible) Return ChildProductVersion objects filtered by the visible column
 * @method     array findByPosition(int $position) Return ChildProductVersion objects filtered by the position column
 * @method     array findByTemplateId(int $template_id) Return ChildProductVersion objects filtered by the template_id column
 * @method     array findByBrandId(int $brand_id) Return ChildProductVersion objects filtered by the brand_id column
 * @method     array findByVirtual(int $virtual) Return ChildProductVersion objects filtered by the virtual column
 * @method     array findByCreatedAt(string $created_at) Return ChildProductVersion objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildProductVersion objects filtered by the updated_at column
 * @method     array findByVersion(int $version) Return ChildProductVersion objects filtered by the version column
 * @method     array findByVersionCreatedAt(string $version_created_at) Return ChildProductVersion objects filtered by the version_created_at column
 * @method     array findByVersionCreatedBy(string $version_created_by) Return ChildProductVersion objects filtered by the version_created_by column
 *
 */
abstract class ProductVersionQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\ProductVersionQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\ProductVersion', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildProductVersionQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildProductVersionQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\ProductVersionQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\ProductVersionQuery();
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
     * @param array[$id, $version] $key Primary key to use for the query
     * @param ConnectionInterface $con an optional connection object
     *
     * @return ChildProductVersion|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = ProductVersionTableMap::getInstanceFromPool(serialize(array((string) $key[0], (string) $key[1]))))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(ProductVersionTableMap::DATABASE_NAME);
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
     * @return   ChildProductVersion A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `TAX_RULE_ID`, `REF`, `VISIBLE`, `POSITION`, `TEMPLATE_ID`, `BRAND_ID`, `VIRTUAL`, `CREATED_AT`, `UPDATED_AT`, `VERSION`, `VERSION_CREATED_AT`, `VERSION_CREATED_BY` FROM `product_version` WHERE `ID` = :p0 AND `VERSION` = :p1';
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
            $obj = new ChildProductVersion();
            $obj->hydrate($row);
            ProductVersionTableMap::addInstanceToPool($obj, serialize(array((string) $key[0], (string) $key[1])));
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
     * @return ChildProductVersion|array|mixed the result, formatted by the current formatter
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
     * @return ChildProductVersionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(ProductVersionTableMap::ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(ProductVersionTableMap::VERSION, $key[1], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildProductVersionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(ProductVersionTableMap::ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(ProductVersionTableMap::VERSION, $key[1], Criteria::EQUAL);
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
     * $query->filterById(array('min' => 12)); // WHERE id > 12
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
     * @return ChildProductVersionQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(ProductVersionTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(ProductVersionTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionTableMap::ID, $id, $comparison);
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
     * @param     mixed $taxRuleId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductVersionQuery The current query, for fluid interface
     */
    public function filterByTaxRuleId($taxRuleId = null, $comparison = null)
    {
        if (is_array($taxRuleId)) {
            $useMinMax = false;
            if (isset($taxRuleId['min'])) {
                $this->addUsingAlias(ProductVersionTableMap::TAX_RULE_ID, $taxRuleId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($taxRuleId['max'])) {
                $this->addUsingAlias(ProductVersionTableMap::TAX_RULE_ID, $taxRuleId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionTableMap::TAX_RULE_ID, $taxRuleId, $comparison);
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
     * @return ChildProductVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(ProductVersionTableMap::REF, $ref, $comparison);
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
     * @return ChildProductVersionQuery The current query, for fluid interface
     */
    public function filterByVisible($visible = null, $comparison = null)
    {
        if (is_array($visible)) {
            $useMinMax = false;
            if (isset($visible['min'])) {
                $this->addUsingAlias(ProductVersionTableMap::VISIBLE, $visible['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($visible['max'])) {
                $this->addUsingAlias(ProductVersionTableMap::VISIBLE, $visible['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionTableMap::VISIBLE, $visible, $comparison);
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
     * @return ChildProductVersionQuery The current query, for fluid interface
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        if (is_array($position)) {
            $useMinMax = false;
            if (isset($position['min'])) {
                $this->addUsingAlias(ProductVersionTableMap::POSITION, $position['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($position['max'])) {
                $this->addUsingAlias(ProductVersionTableMap::POSITION, $position['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionTableMap::POSITION, $position, $comparison);
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
     * @param     mixed $templateId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductVersionQuery The current query, for fluid interface
     */
    public function filterByTemplateId($templateId = null, $comparison = null)
    {
        if (is_array($templateId)) {
            $useMinMax = false;
            if (isset($templateId['min'])) {
                $this->addUsingAlias(ProductVersionTableMap::TEMPLATE_ID, $templateId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($templateId['max'])) {
                $this->addUsingAlias(ProductVersionTableMap::TEMPLATE_ID, $templateId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionTableMap::TEMPLATE_ID, $templateId, $comparison);
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
     * @param     mixed $brandId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductVersionQuery The current query, for fluid interface
     */
    public function filterByBrandId($brandId = null, $comparison = null)
    {
        if (is_array($brandId)) {
            $useMinMax = false;
            if (isset($brandId['min'])) {
                $this->addUsingAlias(ProductVersionTableMap::BRAND_ID, $brandId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($brandId['max'])) {
                $this->addUsingAlias(ProductVersionTableMap::BRAND_ID, $brandId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionTableMap::BRAND_ID, $brandId, $comparison);
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
     * @return ChildProductVersionQuery The current query, for fluid interface
     */
    public function filterByVirtual($virtual = null, $comparison = null)
    {
        if (is_array($virtual)) {
            $useMinMax = false;
            if (isset($virtual['min'])) {
                $this->addUsingAlias(ProductVersionTableMap::VIRTUAL, $virtual['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($virtual['max'])) {
                $this->addUsingAlias(ProductVersionTableMap::VIRTUAL, $virtual['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionTableMap::VIRTUAL, $virtual, $comparison);
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
     * @return ChildProductVersionQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(ProductVersionTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(ProductVersionTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildProductVersionQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(ProductVersionTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(ProductVersionTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionTableMap::UPDATED_AT, $updatedAt, $comparison);
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
     * @return ChildProductVersionQuery The current query, for fluid interface
     */
    public function filterByVersion($version = null, $comparison = null)
    {
        if (is_array($version)) {
            $useMinMax = false;
            if (isset($version['min'])) {
                $this->addUsingAlias(ProductVersionTableMap::VERSION, $version['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($version['max'])) {
                $this->addUsingAlias(ProductVersionTableMap::VERSION, $version['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionTableMap::VERSION, $version, $comparison);
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
     * @return ChildProductVersionQuery The current query, for fluid interface
     */
    public function filterByVersionCreatedAt($versionCreatedAt = null, $comparison = null)
    {
        if (is_array($versionCreatedAt)) {
            $useMinMax = false;
            if (isset($versionCreatedAt['min'])) {
                $this->addUsingAlias(ProductVersionTableMap::VERSION_CREATED_AT, $versionCreatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($versionCreatedAt['max'])) {
                $this->addUsingAlias(ProductVersionTableMap::VERSION_CREATED_AT, $versionCreatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ProductVersionTableMap::VERSION_CREATED_AT, $versionCreatedAt, $comparison);
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
     * @return ChildProductVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(ProductVersionTableMap::VERSION_CREATED_BY, $versionCreatedBy, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Product object
     *
     * @param \Thelia\Model\Product|ObjectCollection $product The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildProductVersionQuery The current query, for fluid interface
     */
    public function filterByProduct($product, $comparison = null)
    {
        if ($product instanceof \Thelia\Model\Product) {
            return $this
                ->addUsingAlias(ProductVersionTableMap::ID, $product->getId(), $comparison);
        } elseif ($product instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(ProductVersionTableMap::ID, $product->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByProduct() only accepts arguments of type \Thelia\Model\Product or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Product relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildProductVersionQuery The current query, for fluid interface
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
     * @see useQuery()
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
     * @param   ChildProductVersion $productVersion Object to remove from the list of results
     *
     * @return ChildProductVersionQuery The current query, for fluid interface
     */
    public function prune($productVersion = null)
    {
        if ($productVersion) {
            $this->addCond('pruneCond0', $this->getAliasedColName(ProductVersionTableMap::ID), $productVersion->getId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(ProductVersionTableMap::VERSION), $productVersion->getVersion(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

    /**
     * Deletes all rows from the product_version table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ProductVersionTableMap::DATABASE_NAME);
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
            ProductVersionTableMap::clearInstancePool();
            ProductVersionTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildProductVersion or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildProductVersion object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(ProductVersionTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(ProductVersionTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        ProductVersionTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            ProductVersionTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

} // ProductVersionQuery
