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
use Thelia\Model\Module as ChildModule;
use Thelia\Model\ModuleI18nQuery as ChildModuleI18nQuery;
use Thelia\Model\ModuleQuery as ChildModuleQuery;
use Thelia\Model\Map\ModuleTableMap;

/**
 * Base class that represents a query for the 'module' table.
 *
 *
 *
 * @method     ChildModuleQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildModuleQuery orderByCode($order = Criteria::ASC) Order by the code column
 * @method     ChildModuleQuery orderByVersion($order = Criteria::ASC) Order by the version column
 * @method     ChildModuleQuery orderByType($order = Criteria::ASC) Order by the type column
 * @method     ChildModuleQuery orderByCategory($order = Criteria::ASC) Order by the category column
 * @method     ChildModuleQuery orderByActivate($order = Criteria::ASC) Order by the activate column
 * @method     ChildModuleQuery orderByPosition($order = Criteria::ASC) Order by the position column
 * @method     ChildModuleQuery orderByFullNamespace($order = Criteria::ASC) Order by the full_namespace column
 * @method     ChildModuleQuery orderByMandatory($order = Criteria::ASC) Order by the mandatory column
 * @method     ChildModuleQuery orderByHidden($order = Criteria::ASC) Order by the hidden column
 * @method     ChildModuleQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildModuleQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildModuleQuery groupById() Group by the id column
 * @method     ChildModuleQuery groupByCode() Group by the code column
 * @method     ChildModuleQuery groupByVersion() Group by the version column
 * @method     ChildModuleQuery groupByType() Group by the type column
 * @method     ChildModuleQuery groupByCategory() Group by the category column
 * @method     ChildModuleQuery groupByActivate() Group by the activate column
 * @method     ChildModuleQuery groupByPosition() Group by the position column
 * @method     ChildModuleQuery groupByFullNamespace() Group by the full_namespace column
 * @method     ChildModuleQuery groupByMandatory() Group by the mandatory column
 * @method     ChildModuleQuery groupByHidden() Group by the hidden column
 * @method     ChildModuleQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildModuleQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method     ChildModuleQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildModuleQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildModuleQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildModuleQuery leftJoinOrderRelatedByPaymentModuleId($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderRelatedByPaymentModuleId relation
 * @method     ChildModuleQuery rightJoinOrderRelatedByPaymentModuleId($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderRelatedByPaymentModuleId relation
 * @method     ChildModuleQuery innerJoinOrderRelatedByPaymentModuleId($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderRelatedByPaymentModuleId relation
 *
 * @method     ChildModuleQuery leftJoinOrderRelatedByDeliveryModuleId($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderRelatedByDeliveryModuleId relation
 * @method     ChildModuleQuery rightJoinOrderRelatedByDeliveryModuleId($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderRelatedByDeliveryModuleId relation
 * @method     ChildModuleQuery innerJoinOrderRelatedByDeliveryModuleId($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderRelatedByDeliveryModuleId relation
 *
 * @method     ChildModuleQuery leftJoinAreaDeliveryModule($relationAlias = null) Adds a LEFT JOIN clause to the query using the AreaDeliveryModule relation
 * @method     ChildModuleQuery rightJoinAreaDeliveryModule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the AreaDeliveryModule relation
 * @method     ChildModuleQuery innerJoinAreaDeliveryModule($relationAlias = null) Adds a INNER JOIN clause to the query using the AreaDeliveryModule relation
 *
 * @method     ChildModuleQuery leftJoinProfileModule($relationAlias = null) Adds a LEFT JOIN clause to the query using the ProfileModule relation
 * @method     ChildModuleQuery rightJoinProfileModule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ProfileModule relation
 * @method     ChildModuleQuery innerJoinProfileModule($relationAlias = null) Adds a INNER JOIN clause to the query using the ProfileModule relation
 *
 * @method     ChildModuleQuery leftJoinModuleImage($relationAlias = null) Adds a LEFT JOIN clause to the query using the ModuleImage relation
 * @method     ChildModuleQuery rightJoinModuleImage($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ModuleImage relation
 * @method     ChildModuleQuery innerJoinModuleImage($relationAlias = null) Adds a INNER JOIN clause to the query using the ModuleImage relation
 *
 * @method     ChildModuleQuery leftJoinCouponModule($relationAlias = null) Adds a LEFT JOIN clause to the query using the CouponModule relation
 * @method     ChildModuleQuery rightJoinCouponModule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CouponModule relation
 * @method     ChildModuleQuery innerJoinCouponModule($relationAlias = null) Adds a INNER JOIN clause to the query using the CouponModule relation
 *
 * @method     ChildModuleQuery leftJoinOrderCouponModule($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderCouponModule relation
 * @method     ChildModuleQuery rightJoinOrderCouponModule($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderCouponModule relation
 * @method     ChildModuleQuery innerJoinOrderCouponModule($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderCouponModule relation
 *
 * @method     ChildModuleQuery leftJoinModuleHook($relationAlias = null) Adds a LEFT JOIN clause to the query using the ModuleHook relation
 * @method     ChildModuleQuery rightJoinModuleHook($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ModuleHook relation
 * @method     ChildModuleQuery innerJoinModuleHook($relationAlias = null) Adds a INNER JOIN clause to the query using the ModuleHook relation
 *
 * @method     ChildModuleQuery leftJoinModuleConfig($relationAlias = null) Adds a LEFT JOIN clause to the query using the ModuleConfig relation
 * @method     ChildModuleQuery rightJoinModuleConfig($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ModuleConfig relation
 * @method     ChildModuleQuery innerJoinModuleConfig($relationAlias = null) Adds a INNER JOIN clause to the query using the ModuleConfig relation
 *
 * @method     ChildModuleQuery leftJoinIgnoredModuleHook($relationAlias = null) Adds a LEFT JOIN clause to the query using the IgnoredModuleHook relation
 * @method     ChildModuleQuery rightJoinIgnoredModuleHook($relationAlias = null) Adds a RIGHT JOIN clause to the query using the IgnoredModuleHook relation
 * @method     ChildModuleQuery innerJoinIgnoredModuleHook($relationAlias = null) Adds a INNER JOIN clause to the query using the IgnoredModuleHook relation
 *
 * @method     ChildModuleQuery leftJoinModuleI18n($relationAlias = null) Adds a LEFT JOIN clause to the query using the ModuleI18n relation
 * @method     ChildModuleQuery rightJoinModuleI18n($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ModuleI18n relation
 * @method     ChildModuleQuery innerJoinModuleI18n($relationAlias = null) Adds a INNER JOIN clause to the query using the ModuleI18n relation
 *
 * @method     ChildModule findOne(ConnectionInterface $con = null) Return the first ChildModule matching the query
 * @method     ChildModule findOneOrCreate(ConnectionInterface $con = null) Return the first ChildModule matching the query, or a new ChildModule object populated from the query conditions when no match is found
 *
 * @method     ChildModule findOneById(int $id) Return the first ChildModule filtered by the id column
 * @method     ChildModule findOneByCode(string $code) Return the first ChildModule filtered by the code column
 * @method     ChildModule findOneByVersion(string $version) Return the first ChildModule filtered by the version column
 * @method     ChildModule findOneByType(int $type) Return the first ChildModule filtered by the type column
 * @method     ChildModule findOneByCategory(string $category) Return the first ChildModule filtered by the category column
 * @method     ChildModule findOneByActivate(int $activate) Return the first ChildModule filtered by the activate column
 * @method     ChildModule findOneByPosition(int $position) Return the first ChildModule filtered by the position column
 * @method     ChildModule findOneByFullNamespace(string $full_namespace) Return the first ChildModule filtered by the full_namespace column
 * @method     ChildModule findOneByMandatory(int $mandatory) Return the first ChildModule filtered by the mandatory column
 * @method     ChildModule findOneByHidden(int $hidden) Return the first ChildModule filtered by the hidden column
 * @method     ChildModule findOneByCreatedAt(string $created_at) Return the first ChildModule filtered by the created_at column
 * @method     ChildModule findOneByUpdatedAt(string $updated_at) Return the first ChildModule filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildModule objects filtered by the id column
 * @method     array findByCode(string $code) Return ChildModule objects filtered by the code column
 * @method     array findByVersion(string $version) Return ChildModule objects filtered by the version column
 * @method     array findByType(int $type) Return ChildModule objects filtered by the type column
 * @method     array findByCategory(string $category) Return ChildModule objects filtered by the category column
 * @method     array findByActivate(int $activate) Return ChildModule objects filtered by the activate column
 * @method     array findByPosition(int $position) Return ChildModule objects filtered by the position column
 * @method     array findByFullNamespace(string $full_namespace) Return ChildModule objects filtered by the full_namespace column
 * @method     array findByMandatory(int $mandatory) Return ChildModule objects filtered by the mandatory column
 * @method     array findByHidden(int $hidden) Return ChildModule objects filtered by the hidden column
 * @method     array findByCreatedAt(string $created_at) Return ChildModule objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildModule objects filtered by the updated_at column
 *
 */
abstract class ModuleQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\ModuleQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Module', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildModuleQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildModuleQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\ModuleQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\ModuleQuery();
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
     * @return ChildModule|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = ModuleTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(ModuleTableMap::DATABASE_NAME);
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
     * @return   ChildModule A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `CODE`, `VERSION`, `TYPE`, `CATEGORY`, `ACTIVATE`, `POSITION`, `FULL_NAMESPACE`, `MANDATORY`, `HIDDEN`, `CREATED_AT`, `UPDATED_AT` FROM `module` WHERE `ID` = :p0';
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
            $obj = new ChildModule();
            $obj->hydrate($row);
            ModuleTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildModule|array|mixed the result, formatted by the current formatter
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
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(ModuleTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(ModuleTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(ModuleTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(ModuleTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ModuleTableMap::ID, $id, $comparison);
    }

    /**
     * Filter the query on the code column
     *
     * Example usage:
     * <code>
     * $query->filterByCode('fooValue');   // WHERE code = 'fooValue'
     * $query->filterByCode('%fooValue%'); // WHERE code LIKE '%fooValue%'
     * </code>
     *
     * @param     string $code The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByCode($code = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($code)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $code)) {
                $code = str_replace('*', '%', $code);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(ModuleTableMap::CODE, $code, $comparison);
    }

    /**
     * Filter the query on the version column
     *
     * Example usage:
     * <code>
     * $query->filterByVersion('fooValue');   // WHERE version = 'fooValue'
     * $query->filterByVersion('%fooValue%'); // WHERE version LIKE '%fooValue%'
     * </code>
     *
     * @param     string $version The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByVersion($version = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($version)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $version)) {
                $version = str_replace('*', '%', $version);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(ModuleTableMap::VERSION, $version, $comparison);
    }

    /**
     * Filter the query on the type column
     *
     * Example usage:
     * <code>
     * $query->filterByType(1234); // WHERE type = 1234
     * $query->filterByType(array(12, 34)); // WHERE type IN (12, 34)
     * $query->filterByType(array('min' => 12)); // WHERE type > 12
     * </code>
     *
     * @param     mixed $type The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByType($type = null, $comparison = null)
    {
        if (is_array($type)) {
            $useMinMax = false;
            if (isset($type['min'])) {
                $this->addUsingAlias(ModuleTableMap::TYPE, $type['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($type['max'])) {
                $this->addUsingAlias(ModuleTableMap::TYPE, $type['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ModuleTableMap::TYPE, $type, $comparison);
    }

    /**
     * Filter the query on the category column
     *
     * Example usage:
     * <code>
     * $query->filterByCategory('fooValue');   // WHERE category = 'fooValue'
     * $query->filterByCategory('%fooValue%'); // WHERE category LIKE '%fooValue%'
     * </code>
     *
     * @param     string $category The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByCategory($category = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($category)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $category)) {
                $category = str_replace('*', '%', $category);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(ModuleTableMap::CATEGORY, $category, $comparison);
    }

    /**
     * Filter the query on the activate column
     *
     * Example usage:
     * <code>
     * $query->filterByActivate(1234); // WHERE activate = 1234
     * $query->filterByActivate(array(12, 34)); // WHERE activate IN (12, 34)
     * $query->filterByActivate(array('min' => 12)); // WHERE activate > 12
     * </code>
     *
     * @param     mixed $activate The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByActivate($activate = null, $comparison = null)
    {
        if (is_array($activate)) {
            $useMinMax = false;
            if (isset($activate['min'])) {
                $this->addUsingAlias(ModuleTableMap::ACTIVATE, $activate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($activate['max'])) {
                $this->addUsingAlias(ModuleTableMap::ACTIVATE, $activate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ModuleTableMap::ACTIVATE, $activate, $comparison);
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
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByPosition($position = null, $comparison = null)
    {
        if (is_array($position)) {
            $useMinMax = false;
            if (isset($position['min'])) {
                $this->addUsingAlias(ModuleTableMap::POSITION, $position['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($position['max'])) {
                $this->addUsingAlias(ModuleTableMap::POSITION, $position['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ModuleTableMap::POSITION, $position, $comparison);
    }

    /**
     * Filter the query on the full_namespace column
     *
     * Example usage:
     * <code>
     * $query->filterByFullNamespace('fooValue');   // WHERE full_namespace = 'fooValue'
     * $query->filterByFullNamespace('%fooValue%'); // WHERE full_namespace LIKE '%fooValue%'
     * </code>
     *
     * @param     string $fullNamespace The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByFullNamespace($fullNamespace = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($fullNamespace)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $fullNamespace)) {
                $fullNamespace = str_replace('*', '%', $fullNamespace);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(ModuleTableMap::FULL_NAMESPACE, $fullNamespace, $comparison);
    }

    /**
     * Filter the query on the mandatory column
     *
     * Example usage:
     * <code>
     * $query->filterByMandatory(1234); // WHERE mandatory = 1234
     * $query->filterByMandatory(array(12, 34)); // WHERE mandatory IN (12, 34)
     * $query->filterByMandatory(array('min' => 12)); // WHERE mandatory > 12
     * </code>
     *
     * @param     mixed $mandatory The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByMandatory($mandatory = null, $comparison = null)
    {
        if (is_array($mandatory)) {
            $useMinMax = false;
            if (isset($mandatory['min'])) {
                $this->addUsingAlias(ModuleTableMap::MANDATORY, $mandatory['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($mandatory['max'])) {
                $this->addUsingAlias(ModuleTableMap::MANDATORY, $mandatory['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ModuleTableMap::MANDATORY, $mandatory, $comparison);
    }

    /**
     * Filter the query on the hidden column
     *
     * Example usage:
     * <code>
     * $query->filterByHidden(1234); // WHERE hidden = 1234
     * $query->filterByHidden(array(12, 34)); // WHERE hidden IN (12, 34)
     * $query->filterByHidden(array('min' => 12)); // WHERE hidden > 12
     * </code>
     *
     * @param     mixed $hidden The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByHidden($hidden = null, $comparison = null)
    {
        if (is_array($hidden)) {
            $useMinMax = false;
            if (isset($hidden['min'])) {
                $this->addUsingAlias(ModuleTableMap::HIDDEN, $hidden['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($hidden['max'])) {
                $this->addUsingAlias(ModuleTableMap::HIDDEN, $hidden['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ModuleTableMap::HIDDEN, $hidden, $comparison);
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
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(ModuleTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(ModuleTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ModuleTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(ModuleTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(ModuleTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(ModuleTableMap::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Order object
     *
     * @param \Thelia\Model\Order|ObjectCollection $order  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByOrderRelatedByPaymentModuleId($order, $comparison = null)
    {
        if ($order instanceof \Thelia\Model\Order) {
            return $this
                ->addUsingAlias(ModuleTableMap::ID, $order->getPaymentModuleId(), $comparison);
        } elseif ($order instanceof ObjectCollection) {
            return $this
                ->useOrderRelatedByPaymentModuleIdQuery()
                ->filterByPrimaryKeys($order->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByOrderRelatedByPaymentModuleId() only accepts arguments of type \Thelia\Model\Order or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderRelatedByPaymentModuleId relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function joinOrderRelatedByPaymentModuleId($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderRelatedByPaymentModuleId');

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
            $this->addJoinObject($join, 'OrderRelatedByPaymentModuleId');
        }

        return $this;
    }

    /**
     * Use the OrderRelatedByPaymentModuleId relation Order object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderQuery A secondary query class using the current class as primary query
     */
    public function useOrderRelatedByPaymentModuleIdQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderRelatedByPaymentModuleId($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderRelatedByPaymentModuleId', '\Thelia\Model\OrderQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Order object
     *
     * @param \Thelia\Model\Order|ObjectCollection $order  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByOrderRelatedByDeliveryModuleId($order, $comparison = null)
    {
        if ($order instanceof \Thelia\Model\Order) {
            return $this
                ->addUsingAlias(ModuleTableMap::ID, $order->getDeliveryModuleId(), $comparison);
        } elseif ($order instanceof ObjectCollection) {
            return $this
                ->useOrderRelatedByDeliveryModuleIdQuery()
                ->filterByPrimaryKeys($order->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByOrderRelatedByDeliveryModuleId() only accepts arguments of type \Thelia\Model\Order or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderRelatedByDeliveryModuleId relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function joinOrderRelatedByDeliveryModuleId($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderRelatedByDeliveryModuleId');

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
            $this->addJoinObject($join, 'OrderRelatedByDeliveryModuleId');
        }

        return $this;
    }

    /**
     * Use the OrderRelatedByDeliveryModuleId relation Order object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderQuery A secondary query class using the current class as primary query
     */
    public function useOrderRelatedByDeliveryModuleIdQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderRelatedByDeliveryModuleId($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderRelatedByDeliveryModuleId', '\Thelia\Model\OrderQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\AreaDeliveryModule object
     *
     * @param \Thelia\Model\AreaDeliveryModule|ObjectCollection $areaDeliveryModule  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByAreaDeliveryModule($areaDeliveryModule, $comparison = null)
    {
        if ($areaDeliveryModule instanceof \Thelia\Model\AreaDeliveryModule) {
            return $this
                ->addUsingAlias(ModuleTableMap::ID, $areaDeliveryModule->getDeliveryModuleId(), $comparison);
        } elseif ($areaDeliveryModule instanceof ObjectCollection) {
            return $this
                ->useAreaDeliveryModuleQuery()
                ->filterByPrimaryKeys($areaDeliveryModule->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByAreaDeliveryModule() only accepts arguments of type \Thelia\Model\AreaDeliveryModule or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the AreaDeliveryModule relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function joinAreaDeliveryModule($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('AreaDeliveryModule');

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
            $this->addJoinObject($join, 'AreaDeliveryModule');
        }

        return $this;
    }

    /**
     * Use the AreaDeliveryModule relation AreaDeliveryModule object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\AreaDeliveryModuleQuery A secondary query class using the current class as primary query
     */
    public function useAreaDeliveryModuleQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinAreaDeliveryModule($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'AreaDeliveryModule', '\Thelia\Model\AreaDeliveryModuleQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ProfileModule object
     *
     * @param \Thelia\Model\ProfileModule|ObjectCollection $profileModule  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByProfileModule($profileModule, $comparison = null)
    {
        if ($profileModule instanceof \Thelia\Model\ProfileModule) {
            return $this
                ->addUsingAlias(ModuleTableMap::ID, $profileModule->getModuleId(), $comparison);
        } elseif ($profileModule instanceof ObjectCollection) {
            return $this
                ->useProfileModuleQuery()
                ->filterByPrimaryKeys($profileModule->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByProfileModule() only accepts arguments of type \Thelia\Model\ProfileModule or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ProfileModule relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function joinProfileModule($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ProfileModule');

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
            $this->addJoinObject($join, 'ProfileModule');
        }

        return $this;
    }

    /**
     * Use the ProfileModule relation ProfileModule object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ProfileModuleQuery A secondary query class using the current class as primary query
     */
    public function useProfileModuleQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinProfileModule($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ProfileModule', '\Thelia\Model\ProfileModuleQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ModuleImage object
     *
     * @param \Thelia\Model\ModuleImage|ObjectCollection $moduleImage  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByModuleImage($moduleImage, $comparison = null)
    {
        if ($moduleImage instanceof \Thelia\Model\ModuleImage) {
            return $this
                ->addUsingAlias(ModuleTableMap::ID, $moduleImage->getModuleId(), $comparison);
        } elseif ($moduleImage instanceof ObjectCollection) {
            return $this
                ->useModuleImageQuery()
                ->filterByPrimaryKeys($moduleImage->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByModuleImage() only accepts arguments of type \Thelia\Model\ModuleImage or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ModuleImage relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function joinModuleImage($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ModuleImage');

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
            $this->addJoinObject($join, 'ModuleImage');
        }

        return $this;
    }

    /**
     * Use the ModuleImage relation ModuleImage object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ModuleImageQuery A secondary query class using the current class as primary query
     */
    public function useModuleImageQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinModuleImage($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ModuleImage', '\Thelia\Model\ModuleImageQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\CouponModule object
     *
     * @param \Thelia\Model\CouponModule|ObjectCollection $couponModule  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByCouponModule($couponModule, $comparison = null)
    {
        if ($couponModule instanceof \Thelia\Model\CouponModule) {
            return $this
                ->addUsingAlias(ModuleTableMap::ID, $couponModule->getModuleId(), $comparison);
        } elseif ($couponModule instanceof ObjectCollection) {
            return $this
                ->useCouponModuleQuery()
                ->filterByPrimaryKeys($couponModule->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCouponModule() only accepts arguments of type \Thelia\Model\CouponModule or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CouponModule relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function joinCouponModule($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CouponModule');

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
            $this->addJoinObject($join, 'CouponModule');
        }

        return $this;
    }

    /**
     * Use the CouponModule relation CouponModule object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CouponModuleQuery A secondary query class using the current class as primary query
     */
    public function useCouponModuleQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCouponModule($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CouponModule', '\Thelia\Model\CouponModuleQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\OrderCouponModule object
     *
     * @param \Thelia\Model\OrderCouponModule|ObjectCollection $orderCouponModule  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByOrderCouponModule($orderCouponModule, $comparison = null)
    {
        if ($orderCouponModule instanceof \Thelia\Model\OrderCouponModule) {
            return $this
                ->addUsingAlias(ModuleTableMap::ID, $orderCouponModule->getModuleId(), $comparison);
        } elseif ($orderCouponModule instanceof ObjectCollection) {
            return $this
                ->useOrderCouponModuleQuery()
                ->filterByPrimaryKeys($orderCouponModule->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByOrderCouponModule() only accepts arguments of type \Thelia\Model\OrderCouponModule or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderCouponModule relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function joinOrderCouponModule($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderCouponModule');

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
            $this->addJoinObject($join, 'OrderCouponModule');
        }

        return $this;
    }

    /**
     * Use the OrderCouponModule relation OrderCouponModule object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderCouponModuleQuery A secondary query class using the current class as primary query
     */
    public function useOrderCouponModuleQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderCouponModule($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderCouponModule', '\Thelia\Model\OrderCouponModuleQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ModuleHook object
     *
     * @param \Thelia\Model\ModuleHook|ObjectCollection $moduleHook  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByModuleHook($moduleHook, $comparison = null)
    {
        if ($moduleHook instanceof \Thelia\Model\ModuleHook) {
            return $this
                ->addUsingAlias(ModuleTableMap::ID, $moduleHook->getModuleId(), $comparison);
        } elseif ($moduleHook instanceof ObjectCollection) {
            return $this
                ->useModuleHookQuery()
                ->filterByPrimaryKeys($moduleHook->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByModuleHook() only accepts arguments of type \Thelia\Model\ModuleHook or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ModuleHook relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function joinModuleHook($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ModuleHook');

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
            $this->addJoinObject($join, 'ModuleHook');
        }

        return $this;
    }

    /**
     * Use the ModuleHook relation ModuleHook object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ModuleHookQuery A secondary query class using the current class as primary query
     */
    public function useModuleHookQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinModuleHook($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ModuleHook', '\Thelia\Model\ModuleHookQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ModuleConfig object
     *
     * @param \Thelia\Model\ModuleConfig|ObjectCollection $moduleConfig  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByModuleConfig($moduleConfig, $comparison = null)
    {
        if ($moduleConfig instanceof \Thelia\Model\ModuleConfig) {
            return $this
                ->addUsingAlias(ModuleTableMap::ID, $moduleConfig->getModuleId(), $comparison);
        } elseif ($moduleConfig instanceof ObjectCollection) {
            return $this
                ->useModuleConfigQuery()
                ->filterByPrimaryKeys($moduleConfig->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByModuleConfig() only accepts arguments of type \Thelia\Model\ModuleConfig or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ModuleConfig relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function joinModuleConfig($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ModuleConfig');

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
            $this->addJoinObject($join, 'ModuleConfig');
        }

        return $this;
    }

    /**
     * Use the ModuleConfig relation ModuleConfig object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ModuleConfigQuery A secondary query class using the current class as primary query
     */
    public function useModuleConfigQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinModuleConfig($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ModuleConfig', '\Thelia\Model\ModuleConfigQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\IgnoredModuleHook object
     *
     * @param \Thelia\Model\IgnoredModuleHook|ObjectCollection $ignoredModuleHook  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByIgnoredModuleHook($ignoredModuleHook, $comparison = null)
    {
        if ($ignoredModuleHook instanceof \Thelia\Model\IgnoredModuleHook) {
            return $this
                ->addUsingAlias(ModuleTableMap::ID, $ignoredModuleHook->getModuleId(), $comparison);
        } elseif ($ignoredModuleHook instanceof ObjectCollection) {
            return $this
                ->useIgnoredModuleHookQuery()
                ->filterByPrimaryKeys($ignoredModuleHook->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByIgnoredModuleHook() only accepts arguments of type \Thelia\Model\IgnoredModuleHook or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the IgnoredModuleHook relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function joinIgnoredModuleHook($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('IgnoredModuleHook');

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
            $this->addJoinObject($join, 'IgnoredModuleHook');
        }

        return $this;
    }

    /**
     * Use the IgnoredModuleHook relation IgnoredModuleHook object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\IgnoredModuleHookQuery A secondary query class using the current class as primary query
     */
    public function useIgnoredModuleHookQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinIgnoredModuleHook($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'IgnoredModuleHook', '\Thelia\Model\IgnoredModuleHookQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\ModuleI18n object
     *
     * @param \Thelia\Model\ModuleI18n|ObjectCollection $moduleI18n  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByModuleI18n($moduleI18n, $comparison = null)
    {
        if ($moduleI18n instanceof \Thelia\Model\ModuleI18n) {
            return $this
                ->addUsingAlias(ModuleTableMap::ID, $moduleI18n->getId(), $comparison);
        } elseif ($moduleI18n instanceof ObjectCollection) {
            return $this
                ->useModuleI18nQuery()
                ->filterByPrimaryKeys($moduleI18n->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByModuleI18n() only accepts arguments of type \Thelia\Model\ModuleI18n or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ModuleI18n relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function joinModuleI18n($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ModuleI18n');

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
            $this->addJoinObject($join, 'ModuleI18n');
        }

        return $this;
    }

    /**
     * Use the ModuleI18n relation ModuleI18n object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ModuleI18nQuery A secondary query class using the current class as primary query
     */
    public function useModuleI18nQuery($relationAlias = null, $joinType = 'LEFT JOIN')
    {
        return $this
            ->joinModuleI18n($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ModuleI18n', '\Thelia\Model\ModuleI18nQuery');
    }

    /**
     * Filter the query by a related Coupon object
     * using the coupon_module table as cross reference
     *
     * @param Coupon $coupon the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByCoupon($coupon, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useCouponModuleQuery()
            ->filterByCoupon($coupon, $comparison)
            ->endUse();
    }

    /**
     * Filter the query by a related OrderCoupon object
     * using the order_coupon_module table as cross reference
     *
     * @param OrderCoupon $orderCoupon the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByOrderCoupon($orderCoupon, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useOrderCouponModuleQuery()
            ->filterByOrderCoupon($orderCoupon, $comparison)
            ->endUse();
    }

    /**
     * Filter the query by a related Hook object
     * using the ignored_module_hook table as cross reference
     *
     * @param Hook $hook the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function filterByHook($hook, $comparison = Criteria::EQUAL)
    {
        return $this
            ->useIgnoredModuleHookQuery()
            ->filterByHook($hook, $comparison)
            ->endUse();
    }

    /**
     * Exclude object from result
     *
     * @param   ChildModule $module Object to remove from the list of results
     *
     * @return ChildModuleQuery The current query, for fluid interface
     */
    public function prune($module = null)
    {
        if ($module) {
            $this->addUsingAlias(ModuleTableMap::ID, $module->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the module table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ModuleTableMap::DATABASE_NAME);
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
            ModuleTableMap::clearInstancePool();
            ModuleTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildModule or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildModule object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(ModuleTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(ModuleTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        ModuleTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            ModuleTableMap::clearRelatedInstancePool();
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
     * @return     ChildModuleQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(ModuleTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildModuleQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(ModuleTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildModuleQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(ModuleTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildModuleQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(ModuleTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildModuleQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(ModuleTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildModuleQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(ModuleTableMap::CREATED_AT);
    }

    // i18n behavior

    /**
     * Adds a JOIN clause to the query using the i18n relation
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildModuleQuery The current query, for fluid interface
     */
    public function joinI18n($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $relationName = $relationAlias ? $relationAlias : 'ModuleI18n';

        return $this
            ->joinModuleI18n($relationAlias, $joinType)
            ->addJoinCondition($relationName, $relationName . '.Locale = ?', $locale);
    }

    /**
     * Adds a JOIN clause to the query and hydrates the related I18n object.
     * Shortcut for $c->joinI18n($locale)->with()
     *
     * @param     string $locale Locale to use for the join condition, e.g. 'fr_FR'
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
     *
     * @return    ChildModuleQuery The current query, for fluid interface
     */
    public function joinWithI18n($locale = 'en_US', $joinType = Criteria::LEFT_JOIN)
    {
        $this
            ->joinI18n($locale, null, $joinType)
            ->with('ModuleI18n');
        $this->with['ModuleI18n']->setIsWithOneToMany(false);

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
     * @return    ChildModuleI18nQuery A secondary query class using the current class as primary query
     */
    public function useI18nQuery($locale = 'en_US', $relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinI18n($locale, $relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ModuleI18n', '\Thelia\Model\ModuleI18nQuery');
    }

} // ModuleQuery
