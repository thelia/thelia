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
use Thelia\Model\Order as ChildOrder;
use Thelia\Model\OrderQuery as ChildOrderQuery;
use Thelia\Model\Map\OrderTableMap;

/**
 * Base class that represents a query for the 'order' table.
 *
 *
 *
 * @method     ChildOrderQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildOrderQuery orderByRef($order = Criteria::ASC) Order by the ref column
 * @method     ChildOrderQuery orderByCustomerId($order = Criteria::ASC) Order by the customer_id column
 * @method     ChildOrderQuery orderByInvoiceOrderAddressId($order = Criteria::ASC) Order by the invoice_order_address_id column
 * @method     ChildOrderQuery orderByDeliveryOrderAddressId($order = Criteria::ASC) Order by the delivery_order_address_id column
 * @method     ChildOrderQuery orderByInvoiceDate($order = Criteria::ASC) Order by the invoice_date column
 * @method     ChildOrderQuery orderByCurrencyId($order = Criteria::ASC) Order by the currency_id column
 * @method     ChildOrderQuery orderByCurrencyRate($order = Criteria::ASC) Order by the currency_rate column
 * @method     ChildOrderQuery orderByTransactionRef($order = Criteria::ASC) Order by the transaction_ref column
 * @method     ChildOrderQuery orderByDeliveryRef($order = Criteria::ASC) Order by the delivery_ref column
 * @method     ChildOrderQuery orderByInvoiceRef($order = Criteria::ASC) Order by the invoice_ref column
 * @method     ChildOrderQuery orderByDiscount($order = Criteria::ASC) Order by the discount column
 * @method     ChildOrderQuery orderByPostage($order = Criteria::ASC) Order by the postage column
 * @method     ChildOrderQuery orderByPostageTax($order = Criteria::ASC) Order by the postage_tax column
 * @method     ChildOrderQuery orderByPostageTaxRuleTitle($order = Criteria::ASC) Order by the postage_tax_rule_title column
 * @method     ChildOrderQuery orderByPaymentModuleId($order = Criteria::ASC) Order by the payment_module_id column
 * @method     ChildOrderQuery orderByDeliveryModuleId($order = Criteria::ASC) Order by the delivery_module_id column
 * @method     ChildOrderQuery orderByStatusId($order = Criteria::ASC) Order by the status_id column
 * @method     ChildOrderQuery orderByLangId($order = Criteria::ASC) Order by the lang_id column
 * @method     ChildOrderQuery orderByCartId($order = Criteria::ASC) Order by the cart_id column
 * @method     ChildOrderQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildOrderQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 * @method     ChildOrderQuery orderByVersion($order = Criteria::ASC) Order by the version column
 * @method     ChildOrderQuery orderByVersionCreatedAt($order = Criteria::ASC) Order by the version_created_at column
 * @method     ChildOrderQuery orderByVersionCreatedBy($order = Criteria::ASC) Order by the version_created_by column
 *
 * @method     ChildOrderQuery groupById() Group by the id column
 * @method     ChildOrderQuery groupByRef() Group by the ref column
 * @method     ChildOrderQuery groupByCustomerId() Group by the customer_id column
 * @method     ChildOrderQuery groupByInvoiceOrderAddressId() Group by the invoice_order_address_id column
 * @method     ChildOrderQuery groupByDeliveryOrderAddressId() Group by the delivery_order_address_id column
 * @method     ChildOrderQuery groupByInvoiceDate() Group by the invoice_date column
 * @method     ChildOrderQuery groupByCurrencyId() Group by the currency_id column
 * @method     ChildOrderQuery groupByCurrencyRate() Group by the currency_rate column
 * @method     ChildOrderQuery groupByTransactionRef() Group by the transaction_ref column
 * @method     ChildOrderQuery groupByDeliveryRef() Group by the delivery_ref column
 * @method     ChildOrderQuery groupByInvoiceRef() Group by the invoice_ref column
 * @method     ChildOrderQuery groupByDiscount() Group by the discount column
 * @method     ChildOrderQuery groupByPostage() Group by the postage column
 * @method     ChildOrderQuery groupByPostageTax() Group by the postage_tax column
 * @method     ChildOrderQuery groupByPostageTaxRuleTitle() Group by the postage_tax_rule_title column
 * @method     ChildOrderQuery groupByPaymentModuleId() Group by the payment_module_id column
 * @method     ChildOrderQuery groupByDeliveryModuleId() Group by the delivery_module_id column
 * @method     ChildOrderQuery groupByStatusId() Group by the status_id column
 * @method     ChildOrderQuery groupByLangId() Group by the lang_id column
 * @method     ChildOrderQuery groupByCartId() Group by the cart_id column
 * @method     ChildOrderQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildOrderQuery groupByUpdatedAt() Group by the updated_at column
 * @method     ChildOrderQuery groupByVersion() Group by the version column
 * @method     ChildOrderQuery groupByVersionCreatedAt() Group by the version_created_at column
 * @method     ChildOrderQuery groupByVersionCreatedBy() Group by the version_created_by column
 *
 * @method     ChildOrderQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildOrderQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildOrderQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildOrderQuery leftJoinCurrency($relationAlias = null) Adds a LEFT JOIN clause to the query using the Currency relation
 * @method     ChildOrderQuery rightJoinCurrency($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Currency relation
 * @method     ChildOrderQuery innerJoinCurrency($relationAlias = null) Adds a INNER JOIN clause to the query using the Currency relation
 *
 * @method     ChildOrderQuery leftJoinCustomer($relationAlias = null) Adds a LEFT JOIN clause to the query using the Customer relation
 * @method     ChildOrderQuery rightJoinCustomer($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Customer relation
 * @method     ChildOrderQuery innerJoinCustomer($relationAlias = null) Adds a INNER JOIN clause to the query using the Customer relation
 *
 * @method     ChildOrderQuery leftJoinOrderAddressRelatedByInvoiceOrderAddressId($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderAddressRelatedByInvoiceOrderAddressId relation
 * @method     ChildOrderQuery rightJoinOrderAddressRelatedByInvoiceOrderAddressId($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderAddressRelatedByInvoiceOrderAddressId relation
 * @method     ChildOrderQuery innerJoinOrderAddressRelatedByInvoiceOrderAddressId($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderAddressRelatedByInvoiceOrderAddressId relation
 *
 * @method     ChildOrderQuery leftJoinOrderAddressRelatedByDeliveryOrderAddressId($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderAddressRelatedByDeliveryOrderAddressId relation
 * @method     ChildOrderQuery rightJoinOrderAddressRelatedByDeliveryOrderAddressId($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderAddressRelatedByDeliveryOrderAddressId relation
 * @method     ChildOrderQuery innerJoinOrderAddressRelatedByDeliveryOrderAddressId($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderAddressRelatedByDeliveryOrderAddressId relation
 *
 * @method     ChildOrderQuery leftJoinOrderStatus($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderStatus relation
 * @method     ChildOrderQuery rightJoinOrderStatus($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderStatus relation
 * @method     ChildOrderQuery innerJoinOrderStatus($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderStatus relation
 *
 * @method     ChildOrderQuery leftJoinModuleRelatedByPaymentModuleId($relationAlias = null) Adds a LEFT JOIN clause to the query using the ModuleRelatedByPaymentModuleId relation
 * @method     ChildOrderQuery rightJoinModuleRelatedByPaymentModuleId($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ModuleRelatedByPaymentModuleId relation
 * @method     ChildOrderQuery innerJoinModuleRelatedByPaymentModuleId($relationAlias = null) Adds a INNER JOIN clause to the query using the ModuleRelatedByPaymentModuleId relation
 *
 * @method     ChildOrderQuery leftJoinModuleRelatedByDeliveryModuleId($relationAlias = null) Adds a LEFT JOIN clause to the query using the ModuleRelatedByDeliveryModuleId relation
 * @method     ChildOrderQuery rightJoinModuleRelatedByDeliveryModuleId($relationAlias = null) Adds a RIGHT JOIN clause to the query using the ModuleRelatedByDeliveryModuleId relation
 * @method     ChildOrderQuery innerJoinModuleRelatedByDeliveryModuleId($relationAlias = null) Adds a INNER JOIN clause to the query using the ModuleRelatedByDeliveryModuleId relation
 *
 * @method     ChildOrderQuery leftJoinLang($relationAlias = null) Adds a LEFT JOIN clause to the query using the Lang relation
 * @method     ChildOrderQuery rightJoinLang($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Lang relation
 * @method     ChildOrderQuery innerJoinLang($relationAlias = null) Adds a INNER JOIN clause to the query using the Lang relation
 *
 * @method     ChildOrderQuery leftJoinOrderProduct($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderProduct relation
 * @method     ChildOrderQuery rightJoinOrderProduct($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderProduct relation
 * @method     ChildOrderQuery innerJoinOrderProduct($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderProduct relation
 *
 * @method     ChildOrderQuery leftJoinOrderCoupon($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderCoupon relation
 * @method     ChildOrderQuery rightJoinOrderCoupon($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderCoupon relation
 * @method     ChildOrderQuery innerJoinOrderCoupon($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderCoupon relation
 *
 * @method     ChildOrderQuery leftJoinOrderVersion($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderVersion relation
 * @method     ChildOrderQuery rightJoinOrderVersion($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderVersion relation
 * @method     ChildOrderQuery innerJoinOrderVersion($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderVersion relation
 *
 * @method     ChildOrder findOne(ConnectionInterface $con = null) Return the first ChildOrder matching the query
 * @method     ChildOrder findOneOrCreate(ConnectionInterface $con = null) Return the first ChildOrder matching the query, or a new ChildOrder object populated from the query conditions when no match is found
 *
 * @method     ChildOrder findOneById(int $id) Return the first ChildOrder filtered by the id column
 * @method     ChildOrder findOneByRef(string $ref) Return the first ChildOrder filtered by the ref column
 * @method     ChildOrder findOneByCustomerId(int $customer_id) Return the first ChildOrder filtered by the customer_id column
 * @method     ChildOrder findOneByInvoiceOrderAddressId(int $invoice_order_address_id) Return the first ChildOrder filtered by the invoice_order_address_id column
 * @method     ChildOrder findOneByDeliveryOrderAddressId(int $delivery_order_address_id) Return the first ChildOrder filtered by the delivery_order_address_id column
 * @method     ChildOrder findOneByInvoiceDate(string $invoice_date) Return the first ChildOrder filtered by the invoice_date column
 * @method     ChildOrder findOneByCurrencyId(int $currency_id) Return the first ChildOrder filtered by the currency_id column
 * @method     ChildOrder findOneByCurrencyRate(double $currency_rate) Return the first ChildOrder filtered by the currency_rate column
 * @method     ChildOrder findOneByTransactionRef(string $transaction_ref) Return the first ChildOrder filtered by the transaction_ref column
 * @method     ChildOrder findOneByDeliveryRef(string $delivery_ref) Return the first ChildOrder filtered by the delivery_ref column
 * @method     ChildOrder findOneByInvoiceRef(string $invoice_ref) Return the first ChildOrder filtered by the invoice_ref column
 * @method     ChildOrder findOneByDiscount(string $discount) Return the first ChildOrder filtered by the discount column
 * @method     ChildOrder findOneByPostage(string $postage) Return the first ChildOrder filtered by the postage column
 * @method     ChildOrder findOneByPostageTax(string $postage_tax) Return the first ChildOrder filtered by the postage_tax column
 * @method     ChildOrder findOneByPostageTaxRuleTitle(string $postage_tax_rule_title) Return the first ChildOrder filtered by the postage_tax_rule_title column
 * @method     ChildOrder findOneByPaymentModuleId(int $payment_module_id) Return the first ChildOrder filtered by the payment_module_id column
 * @method     ChildOrder findOneByDeliveryModuleId(int $delivery_module_id) Return the first ChildOrder filtered by the delivery_module_id column
 * @method     ChildOrder findOneByStatusId(int $status_id) Return the first ChildOrder filtered by the status_id column
 * @method     ChildOrder findOneByLangId(int $lang_id) Return the first ChildOrder filtered by the lang_id column
 * @method     ChildOrder findOneByCartId(int $cart_id) Return the first ChildOrder filtered by the cart_id column
 * @method     ChildOrder findOneByCreatedAt(string $created_at) Return the first ChildOrder filtered by the created_at column
 * @method     ChildOrder findOneByUpdatedAt(string $updated_at) Return the first ChildOrder filtered by the updated_at column
 * @method     ChildOrder findOneByVersion(int $version) Return the first ChildOrder filtered by the version column
 * @method     ChildOrder findOneByVersionCreatedAt(string $version_created_at) Return the first ChildOrder filtered by the version_created_at column
 * @method     ChildOrder findOneByVersionCreatedBy(string $version_created_by) Return the first ChildOrder filtered by the version_created_by column
 *
 * @method     array findById(int $id) Return ChildOrder objects filtered by the id column
 * @method     array findByRef(string $ref) Return ChildOrder objects filtered by the ref column
 * @method     array findByCustomerId(int $customer_id) Return ChildOrder objects filtered by the customer_id column
 * @method     array findByInvoiceOrderAddressId(int $invoice_order_address_id) Return ChildOrder objects filtered by the invoice_order_address_id column
 * @method     array findByDeliveryOrderAddressId(int $delivery_order_address_id) Return ChildOrder objects filtered by the delivery_order_address_id column
 * @method     array findByInvoiceDate(string $invoice_date) Return ChildOrder objects filtered by the invoice_date column
 * @method     array findByCurrencyId(int $currency_id) Return ChildOrder objects filtered by the currency_id column
 * @method     array findByCurrencyRate(double $currency_rate) Return ChildOrder objects filtered by the currency_rate column
 * @method     array findByTransactionRef(string $transaction_ref) Return ChildOrder objects filtered by the transaction_ref column
 * @method     array findByDeliveryRef(string $delivery_ref) Return ChildOrder objects filtered by the delivery_ref column
 * @method     array findByInvoiceRef(string $invoice_ref) Return ChildOrder objects filtered by the invoice_ref column
 * @method     array findByDiscount(string $discount) Return ChildOrder objects filtered by the discount column
 * @method     array findByPostage(string $postage) Return ChildOrder objects filtered by the postage column
 * @method     array findByPostageTax(string $postage_tax) Return ChildOrder objects filtered by the postage_tax column
 * @method     array findByPostageTaxRuleTitle(string $postage_tax_rule_title) Return ChildOrder objects filtered by the postage_tax_rule_title column
 * @method     array findByPaymentModuleId(int $payment_module_id) Return ChildOrder objects filtered by the payment_module_id column
 * @method     array findByDeliveryModuleId(int $delivery_module_id) Return ChildOrder objects filtered by the delivery_module_id column
 * @method     array findByStatusId(int $status_id) Return ChildOrder objects filtered by the status_id column
 * @method     array findByLangId(int $lang_id) Return ChildOrder objects filtered by the lang_id column
 * @method     array findByCartId(int $cart_id) Return ChildOrder objects filtered by the cart_id column
 * @method     array findByCreatedAt(string $created_at) Return ChildOrder objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildOrder objects filtered by the updated_at column
 * @method     array findByVersion(int $version) Return ChildOrder objects filtered by the version column
 * @method     array findByVersionCreatedAt(string $version_created_at) Return ChildOrder objects filtered by the version_created_at column
 * @method     array findByVersionCreatedBy(string $version_created_by) Return ChildOrder objects filtered by the version_created_by column
 *
 */
abstract class OrderQuery extends ModelCriteria
{

    // versionable behavior

    /**
     * Whether the versioning is enabled
     */
    static $isVersioningEnabled = true;

    /**
     * Initializes internal state of \Thelia\Model\Base\OrderQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\Order', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildOrderQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildOrderQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\OrderQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\OrderQuery();
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
     * @return ChildOrder|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = OrderTableMap::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(OrderTableMap::DATABASE_NAME);
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
     * @return   ChildOrder A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `REF`, `CUSTOMER_ID`, `INVOICE_ORDER_ADDRESS_ID`, `DELIVERY_ORDER_ADDRESS_ID`, `INVOICE_DATE`, `CURRENCY_ID`, `CURRENCY_RATE`, `TRANSACTION_REF`, `DELIVERY_REF`, `INVOICE_REF`, `DISCOUNT`, `POSTAGE`, `POSTAGE_TAX`, `POSTAGE_TAX_RULE_TITLE`, `PAYMENT_MODULE_ID`, `DELIVERY_MODULE_ID`, `STATUS_ID`, `LANG_ID`, `CART_ID`, `CREATED_AT`, `UPDATED_AT`, `VERSION`, `VERSION_CREATED_AT`, `VERSION_CREATED_BY` FROM `order` WHERE `ID` = :p0';
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
            $obj = new ChildOrder();
            $obj->hydrate($row);
            OrderTableMap::addInstanceToPool($obj, (string) $key);
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
     * @return ChildOrder|array|mixed the result, formatted by the current formatter
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
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(OrderTableMap::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(OrderTableMap::ID, $keys, Criteria::IN);
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
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(OrderTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(OrderTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::ID, $id, $comparison);
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
     * @return ChildOrderQuery The current query, for fluid interface
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

        return $this->addUsingAlias(OrderTableMap::REF, $ref, $comparison);
    }

    /**
     * Filter the query on the customer_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCustomerId(1234); // WHERE customer_id = 1234
     * $query->filterByCustomerId(array(12, 34)); // WHERE customer_id IN (12, 34)
     * $query->filterByCustomerId(array('min' => 12)); // WHERE customer_id > 12
     * </code>
     *
     * @see       filterByCustomer()
     *
     * @param     mixed $customerId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByCustomerId($customerId = null, $comparison = null)
    {
        if (is_array($customerId)) {
            $useMinMax = false;
            if (isset($customerId['min'])) {
                $this->addUsingAlias(OrderTableMap::CUSTOMER_ID, $customerId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($customerId['max'])) {
                $this->addUsingAlias(OrderTableMap::CUSTOMER_ID, $customerId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::CUSTOMER_ID, $customerId, $comparison);
    }

    /**
     * Filter the query on the invoice_order_address_id column
     *
     * Example usage:
     * <code>
     * $query->filterByInvoiceOrderAddressId(1234); // WHERE invoice_order_address_id = 1234
     * $query->filterByInvoiceOrderAddressId(array(12, 34)); // WHERE invoice_order_address_id IN (12, 34)
     * $query->filterByInvoiceOrderAddressId(array('min' => 12)); // WHERE invoice_order_address_id > 12
     * </code>
     *
     * @see       filterByOrderAddressRelatedByInvoiceOrderAddressId()
     *
     * @param     mixed $invoiceOrderAddressId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByInvoiceOrderAddressId($invoiceOrderAddressId = null, $comparison = null)
    {
        if (is_array($invoiceOrderAddressId)) {
            $useMinMax = false;
            if (isset($invoiceOrderAddressId['min'])) {
                $this->addUsingAlias(OrderTableMap::INVOICE_ORDER_ADDRESS_ID, $invoiceOrderAddressId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($invoiceOrderAddressId['max'])) {
                $this->addUsingAlias(OrderTableMap::INVOICE_ORDER_ADDRESS_ID, $invoiceOrderAddressId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::INVOICE_ORDER_ADDRESS_ID, $invoiceOrderAddressId, $comparison);
    }

    /**
     * Filter the query on the delivery_order_address_id column
     *
     * Example usage:
     * <code>
     * $query->filterByDeliveryOrderAddressId(1234); // WHERE delivery_order_address_id = 1234
     * $query->filterByDeliveryOrderAddressId(array(12, 34)); // WHERE delivery_order_address_id IN (12, 34)
     * $query->filterByDeliveryOrderAddressId(array('min' => 12)); // WHERE delivery_order_address_id > 12
     * </code>
     *
     * @see       filterByOrderAddressRelatedByDeliveryOrderAddressId()
     *
     * @param     mixed $deliveryOrderAddressId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByDeliveryOrderAddressId($deliveryOrderAddressId = null, $comparison = null)
    {
        if (is_array($deliveryOrderAddressId)) {
            $useMinMax = false;
            if (isset($deliveryOrderAddressId['min'])) {
                $this->addUsingAlias(OrderTableMap::DELIVERY_ORDER_ADDRESS_ID, $deliveryOrderAddressId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($deliveryOrderAddressId['max'])) {
                $this->addUsingAlias(OrderTableMap::DELIVERY_ORDER_ADDRESS_ID, $deliveryOrderAddressId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::DELIVERY_ORDER_ADDRESS_ID, $deliveryOrderAddressId, $comparison);
    }

    /**
     * Filter the query on the invoice_date column
     *
     * Example usage:
     * <code>
     * $query->filterByInvoiceDate('2011-03-14'); // WHERE invoice_date = '2011-03-14'
     * $query->filterByInvoiceDate('now'); // WHERE invoice_date = '2011-03-14'
     * $query->filterByInvoiceDate(array('max' => 'yesterday')); // WHERE invoice_date > '2011-03-13'
     * </code>
     *
     * @param     mixed $invoiceDate The value to use as filter.
     *              Values can be integers (unix timestamps), DateTime objects, or strings.
     *              Empty strings are treated as NULL.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByInvoiceDate($invoiceDate = null, $comparison = null)
    {
        if (is_array($invoiceDate)) {
            $useMinMax = false;
            if (isset($invoiceDate['min'])) {
                $this->addUsingAlias(OrderTableMap::INVOICE_DATE, $invoiceDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($invoiceDate['max'])) {
                $this->addUsingAlias(OrderTableMap::INVOICE_DATE, $invoiceDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::INVOICE_DATE, $invoiceDate, $comparison);
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
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByCurrencyId($currencyId = null, $comparison = null)
    {
        if (is_array($currencyId)) {
            $useMinMax = false;
            if (isset($currencyId['min'])) {
                $this->addUsingAlias(OrderTableMap::CURRENCY_ID, $currencyId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($currencyId['max'])) {
                $this->addUsingAlias(OrderTableMap::CURRENCY_ID, $currencyId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::CURRENCY_ID, $currencyId, $comparison);
    }

    /**
     * Filter the query on the currency_rate column
     *
     * Example usage:
     * <code>
     * $query->filterByCurrencyRate(1234); // WHERE currency_rate = 1234
     * $query->filterByCurrencyRate(array(12, 34)); // WHERE currency_rate IN (12, 34)
     * $query->filterByCurrencyRate(array('min' => 12)); // WHERE currency_rate > 12
     * </code>
     *
     * @param     mixed $currencyRate The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByCurrencyRate($currencyRate = null, $comparison = null)
    {
        if (is_array($currencyRate)) {
            $useMinMax = false;
            if (isset($currencyRate['min'])) {
                $this->addUsingAlias(OrderTableMap::CURRENCY_RATE, $currencyRate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($currencyRate['max'])) {
                $this->addUsingAlias(OrderTableMap::CURRENCY_RATE, $currencyRate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::CURRENCY_RATE, $currencyRate, $comparison);
    }

    /**
     * Filter the query on the transaction_ref column
     *
     * Example usage:
     * <code>
     * $query->filterByTransactionRef('fooValue');   // WHERE transaction_ref = 'fooValue'
     * $query->filterByTransactionRef('%fooValue%'); // WHERE transaction_ref LIKE '%fooValue%'
     * </code>
     *
     * @param     string $transactionRef The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByTransactionRef($transactionRef = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($transactionRef)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $transactionRef)) {
                $transactionRef = str_replace('*', '%', $transactionRef);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderTableMap::TRANSACTION_REF, $transactionRef, $comparison);
    }

    /**
     * Filter the query on the delivery_ref column
     *
     * Example usage:
     * <code>
     * $query->filterByDeliveryRef('fooValue');   // WHERE delivery_ref = 'fooValue'
     * $query->filterByDeliveryRef('%fooValue%'); // WHERE delivery_ref LIKE '%fooValue%'
     * </code>
     *
     * @param     string $deliveryRef The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByDeliveryRef($deliveryRef = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($deliveryRef)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $deliveryRef)) {
                $deliveryRef = str_replace('*', '%', $deliveryRef);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderTableMap::DELIVERY_REF, $deliveryRef, $comparison);
    }

    /**
     * Filter the query on the invoice_ref column
     *
     * Example usage:
     * <code>
     * $query->filterByInvoiceRef('fooValue');   // WHERE invoice_ref = 'fooValue'
     * $query->filterByInvoiceRef('%fooValue%'); // WHERE invoice_ref LIKE '%fooValue%'
     * </code>
     *
     * @param     string $invoiceRef The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByInvoiceRef($invoiceRef = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($invoiceRef)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $invoiceRef)) {
                $invoiceRef = str_replace('*', '%', $invoiceRef);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderTableMap::INVOICE_REF, $invoiceRef, $comparison);
    }

    /**
     * Filter the query on the discount column
     *
     * Example usage:
     * <code>
     * $query->filterByDiscount(1234); // WHERE discount = 1234
     * $query->filterByDiscount(array(12, 34)); // WHERE discount IN (12, 34)
     * $query->filterByDiscount(array('min' => 12)); // WHERE discount > 12
     * </code>
     *
     * @param     mixed $discount The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByDiscount($discount = null, $comparison = null)
    {
        if (is_array($discount)) {
            $useMinMax = false;
            if (isset($discount['min'])) {
                $this->addUsingAlias(OrderTableMap::DISCOUNT, $discount['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($discount['max'])) {
                $this->addUsingAlias(OrderTableMap::DISCOUNT, $discount['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::DISCOUNT, $discount, $comparison);
    }

    /**
     * Filter the query on the postage column
     *
     * Example usage:
     * <code>
     * $query->filterByPostage(1234); // WHERE postage = 1234
     * $query->filterByPostage(array(12, 34)); // WHERE postage IN (12, 34)
     * $query->filterByPostage(array('min' => 12)); // WHERE postage > 12
     * </code>
     *
     * @param     mixed $postage The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByPostage($postage = null, $comparison = null)
    {
        if (is_array($postage)) {
            $useMinMax = false;
            if (isset($postage['min'])) {
                $this->addUsingAlias(OrderTableMap::POSTAGE, $postage['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($postage['max'])) {
                $this->addUsingAlias(OrderTableMap::POSTAGE, $postage['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::POSTAGE, $postage, $comparison);
    }

    /**
     * Filter the query on the postage_tax column
     *
     * Example usage:
     * <code>
     * $query->filterByPostageTax(1234); // WHERE postage_tax = 1234
     * $query->filterByPostageTax(array(12, 34)); // WHERE postage_tax IN (12, 34)
     * $query->filterByPostageTax(array('min' => 12)); // WHERE postage_tax > 12
     * </code>
     *
     * @param     mixed $postageTax The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByPostageTax($postageTax = null, $comparison = null)
    {
        if (is_array($postageTax)) {
            $useMinMax = false;
            if (isset($postageTax['min'])) {
                $this->addUsingAlias(OrderTableMap::POSTAGE_TAX, $postageTax['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($postageTax['max'])) {
                $this->addUsingAlias(OrderTableMap::POSTAGE_TAX, $postageTax['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::POSTAGE_TAX, $postageTax, $comparison);
    }

    /**
     * Filter the query on the postage_tax_rule_title column
     *
     * Example usage:
     * <code>
     * $query->filterByPostageTaxRuleTitle('fooValue');   // WHERE postage_tax_rule_title = 'fooValue'
     * $query->filterByPostageTaxRuleTitle('%fooValue%'); // WHERE postage_tax_rule_title LIKE '%fooValue%'
     * </code>
     *
     * @param     string $postageTaxRuleTitle The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByPostageTaxRuleTitle($postageTaxRuleTitle = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($postageTaxRuleTitle)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $postageTaxRuleTitle)) {
                $postageTaxRuleTitle = str_replace('*', '%', $postageTaxRuleTitle);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderTableMap::POSTAGE_TAX_RULE_TITLE, $postageTaxRuleTitle, $comparison);
    }

    /**
     * Filter the query on the payment_module_id column
     *
     * Example usage:
     * <code>
     * $query->filterByPaymentModuleId(1234); // WHERE payment_module_id = 1234
     * $query->filterByPaymentModuleId(array(12, 34)); // WHERE payment_module_id IN (12, 34)
     * $query->filterByPaymentModuleId(array('min' => 12)); // WHERE payment_module_id > 12
     * </code>
     *
     * @see       filterByModuleRelatedByPaymentModuleId()
     *
     * @param     mixed $paymentModuleId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByPaymentModuleId($paymentModuleId = null, $comparison = null)
    {
        if (is_array($paymentModuleId)) {
            $useMinMax = false;
            if (isset($paymentModuleId['min'])) {
                $this->addUsingAlias(OrderTableMap::PAYMENT_MODULE_ID, $paymentModuleId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($paymentModuleId['max'])) {
                $this->addUsingAlias(OrderTableMap::PAYMENT_MODULE_ID, $paymentModuleId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::PAYMENT_MODULE_ID, $paymentModuleId, $comparison);
    }

    /**
     * Filter the query on the delivery_module_id column
     *
     * Example usage:
     * <code>
     * $query->filterByDeliveryModuleId(1234); // WHERE delivery_module_id = 1234
     * $query->filterByDeliveryModuleId(array(12, 34)); // WHERE delivery_module_id IN (12, 34)
     * $query->filterByDeliveryModuleId(array('min' => 12)); // WHERE delivery_module_id > 12
     * </code>
     *
     * @see       filterByModuleRelatedByDeliveryModuleId()
     *
     * @param     mixed $deliveryModuleId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByDeliveryModuleId($deliveryModuleId = null, $comparison = null)
    {
        if (is_array($deliveryModuleId)) {
            $useMinMax = false;
            if (isset($deliveryModuleId['min'])) {
                $this->addUsingAlias(OrderTableMap::DELIVERY_MODULE_ID, $deliveryModuleId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($deliveryModuleId['max'])) {
                $this->addUsingAlias(OrderTableMap::DELIVERY_MODULE_ID, $deliveryModuleId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::DELIVERY_MODULE_ID, $deliveryModuleId, $comparison);
    }

    /**
     * Filter the query on the status_id column
     *
     * Example usage:
     * <code>
     * $query->filterByStatusId(1234); // WHERE status_id = 1234
     * $query->filterByStatusId(array(12, 34)); // WHERE status_id IN (12, 34)
     * $query->filterByStatusId(array('min' => 12)); // WHERE status_id > 12
     * </code>
     *
     * @see       filterByOrderStatus()
     *
     * @param     mixed $statusId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByStatusId($statusId = null, $comparison = null)
    {
        if (is_array($statusId)) {
            $useMinMax = false;
            if (isset($statusId['min'])) {
                $this->addUsingAlias(OrderTableMap::STATUS_ID, $statusId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($statusId['max'])) {
                $this->addUsingAlias(OrderTableMap::STATUS_ID, $statusId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::STATUS_ID, $statusId, $comparison);
    }

    /**
     * Filter the query on the lang_id column
     *
     * Example usage:
     * <code>
     * $query->filterByLangId(1234); // WHERE lang_id = 1234
     * $query->filterByLangId(array(12, 34)); // WHERE lang_id IN (12, 34)
     * $query->filterByLangId(array('min' => 12)); // WHERE lang_id > 12
     * </code>
     *
     * @see       filterByLang()
     *
     * @param     mixed $langId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByLangId($langId = null, $comparison = null)
    {
        if (is_array($langId)) {
            $useMinMax = false;
            if (isset($langId['min'])) {
                $this->addUsingAlias(OrderTableMap::LANG_ID, $langId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($langId['max'])) {
                $this->addUsingAlias(OrderTableMap::LANG_ID, $langId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::LANG_ID, $langId, $comparison);
    }

    /**
     * Filter the query on the cart_id column
     *
     * Example usage:
     * <code>
     * $query->filterByCartId(1234); // WHERE cart_id = 1234
     * $query->filterByCartId(array(12, 34)); // WHERE cart_id IN (12, 34)
     * $query->filterByCartId(array('min' => 12)); // WHERE cart_id > 12
     * </code>
     *
     * @param     mixed $cartId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByCartId($cartId = null, $comparison = null)
    {
        if (is_array($cartId)) {
            $useMinMax = false;
            if (isset($cartId['min'])) {
                $this->addUsingAlias(OrderTableMap::CART_ID, $cartId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($cartId['max'])) {
                $this->addUsingAlias(OrderTableMap::CART_ID, $cartId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::CART_ID, $cartId, $comparison);
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
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(OrderTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(OrderTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(OrderTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(OrderTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::UPDATED_AT, $updatedAt, $comparison);
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
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByVersion($version = null, $comparison = null)
    {
        if (is_array($version)) {
            $useMinMax = false;
            if (isset($version['min'])) {
                $this->addUsingAlias(OrderTableMap::VERSION, $version['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($version['max'])) {
                $this->addUsingAlias(OrderTableMap::VERSION, $version['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::VERSION, $version, $comparison);
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
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByVersionCreatedAt($versionCreatedAt = null, $comparison = null)
    {
        if (is_array($versionCreatedAt)) {
            $useMinMax = false;
            if (isset($versionCreatedAt['min'])) {
                $this->addUsingAlias(OrderTableMap::VERSION_CREATED_AT, $versionCreatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($versionCreatedAt['max'])) {
                $this->addUsingAlias(OrderTableMap::VERSION_CREATED_AT, $versionCreatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::VERSION_CREATED_AT, $versionCreatedAt, $comparison);
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
     * @return ChildOrderQuery The current query, for fluid interface
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

        return $this->addUsingAlias(OrderTableMap::VERSION_CREATED_BY, $versionCreatedBy, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Currency object
     *
     * @param \Thelia\Model\Currency|ObjectCollection $currency The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByCurrency($currency, $comparison = null)
    {
        if ($currency instanceof \Thelia\Model\Currency) {
            return $this
                ->addUsingAlias(OrderTableMap::CURRENCY_ID, $currency->getId(), $comparison);
        } elseif ($currency instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderTableMap::CURRENCY_ID, $currency->toKeyValue('PrimaryKey', 'Id'), $comparison);
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
     * @return ChildOrderQuery The current query, for fluid interface
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
     * Filter the query by a related \Thelia\Model\Customer object
     *
     * @param \Thelia\Model\Customer|ObjectCollection $customer The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByCustomer($customer, $comparison = null)
    {
        if ($customer instanceof \Thelia\Model\Customer) {
            return $this
                ->addUsingAlias(OrderTableMap::CUSTOMER_ID, $customer->getId(), $comparison);
        } elseif ($customer instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderTableMap::CUSTOMER_ID, $customer->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByCustomer() only accepts arguments of type \Thelia\Model\Customer or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Customer relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function joinCustomer($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Customer');

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
            $this->addJoinObject($join, 'Customer');
        }

        return $this;
    }

    /**
     * Use the Customer relation Customer object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CustomerQuery A secondary query class using the current class as primary query
     */
    public function useCustomerQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCustomer($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Customer', '\Thelia\Model\CustomerQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\OrderAddress object
     *
     * @param \Thelia\Model\OrderAddress|ObjectCollection $orderAddress The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByOrderAddressRelatedByInvoiceOrderAddressId($orderAddress, $comparison = null)
    {
        if ($orderAddress instanceof \Thelia\Model\OrderAddress) {
            return $this
                ->addUsingAlias(OrderTableMap::INVOICE_ORDER_ADDRESS_ID, $orderAddress->getId(), $comparison);
        } elseif ($orderAddress instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderTableMap::INVOICE_ORDER_ADDRESS_ID, $orderAddress->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByOrderAddressRelatedByInvoiceOrderAddressId() only accepts arguments of type \Thelia\Model\OrderAddress or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderAddressRelatedByInvoiceOrderAddressId relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function joinOrderAddressRelatedByInvoiceOrderAddressId($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderAddressRelatedByInvoiceOrderAddressId');

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
            $this->addJoinObject($join, 'OrderAddressRelatedByInvoiceOrderAddressId');
        }

        return $this;
    }

    /**
     * Use the OrderAddressRelatedByInvoiceOrderAddressId relation OrderAddress object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderAddressQuery A secondary query class using the current class as primary query
     */
    public function useOrderAddressRelatedByInvoiceOrderAddressIdQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderAddressRelatedByInvoiceOrderAddressId($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderAddressRelatedByInvoiceOrderAddressId', '\Thelia\Model\OrderAddressQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\OrderAddress object
     *
     * @param \Thelia\Model\OrderAddress|ObjectCollection $orderAddress The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByOrderAddressRelatedByDeliveryOrderAddressId($orderAddress, $comparison = null)
    {
        if ($orderAddress instanceof \Thelia\Model\OrderAddress) {
            return $this
                ->addUsingAlias(OrderTableMap::DELIVERY_ORDER_ADDRESS_ID, $orderAddress->getId(), $comparison);
        } elseif ($orderAddress instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderTableMap::DELIVERY_ORDER_ADDRESS_ID, $orderAddress->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByOrderAddressRelatedByDeliveryOrderAddressId() only accepts arguments of type \Thelia\Model\OrderAddress or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderAddressRelatedByDeliveryOrderAddressId relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function joinOrderAddressRelatedByDeliveryOrderAddressId($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderAddressRelatedByDeliveryOrderAddressId');

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
            $this->addJoinObject($join, 'OrderAddressRelatedByDeliveryOrderAddressId');
        }

        return $this;
    }

    /**
     * Use the OrderAddressRelatedByDeliveryOrderAddressId relation OrderAddress object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderAddressQuery A secondary query class using the current class as primary query
     */
    public function useOrderAddressRelatedByDeliveryOrderAddressIdQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderAddressRelatedByDeliveryOrderAddressId($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderAddressRelatedByDeliveryOrderAddressId', '\Thelia\Model\OrderAddressQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\OrderStatus object
     *
     * @param \Thelia\Model\OrderStatus|ObjectCollection $orderStatus The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByOrderStatus($orderStatus, $comparison = null)
    {
        if ($orderStatus instanceof \Thelia\Model\OrderStatus) {
            return $this
                ->addUsingAlias(OrderTableMap::STATUS_ID, $orderStatus->getId(), $comparison);
        } elseif ($orderStatus instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderTableMap::STATUS_ID, $orderStatus->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByOrderStatus() only accepts arguments of type \Thelia\Model\OrderStatus or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderStatus relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function joinOrderStatus($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderStatus');

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
            $this->addJoinObject($join, 'OrderStatus');
        }

        return $this;
    }

    /**
     * Use the OrderStatus relation OrderStatus object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderStatusQuery A secondary query class using the current class as primary query
     */
    public function useOrderStatusQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderStatus($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderStatus', '\Thelia\Model\OrderStatusQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Module object
     *
     * @param \Thelia\Model\Module|ObjectCollection $module The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByModuleRelatedByPaymentModuleId($module, $comparison = null)
    {
        if ($module instanceof \Thelia\Model\Module) {
            return $this
                ->addUsingAlias(OrderTableMap::PAYMENT_MODULE_ID, $module->getId(), $comparison);
        } elseif ($module instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderTableMap::PAYMENT_MODULE_ID, $module->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByModuleRelatedByPaymentModuleId() only accepts arguments of type \Thelia\Model\Module or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ModuleRelatedByPaymentModuleId relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function joinModuleRelatedByPaymentModuleId($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ModuleRelatedByPaymentModuleId');

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
            $this->addJoinObject($join, 'ModuleRelatedByPaymentModuleId');
        }

        return $this;
    }

    /**
     * Use the ModuleRelatedByPaymentModuleId relation Module object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ModuleQuery A secondary query class using the current class as primary query
     */
    public function useModuleRelatedByPaymentModuleIdQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinModuleRelatedByPaymentModuleId($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ModuleRelatedByPaymentModuleId', '\Thelia\Model\ModuleQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Module object
     *
     * @param \Thelia\Model\Module|ObjectCollection $module The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByModuleRelatedByDeliveryModuleId($module, $comparison = null)
    {
        if ($module instanceof \Thelia\Model\Module) {
            return $this
                ->addUsingAlias(OrderTableMap::DELIVERY_MODULE_ID, $module->getId(), $comparison);
        } elseif ($module instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderTableMap::DELIVERY_MODULE_ID, $module->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByModuleRelatedByDeliveryModuleId() only accepts arguments of type \Thelia\Model\Module or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the ModuleRelatedByDeliveryModuleId relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function joinModuleRelatedByDeliveryModuleId($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('ModuleRelatedByDeliveryModuleId');

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
            $this->addJoinObject($join, 'ModuleRelatedByDeliveryModuleId');
        }

        return $this;
    }

    /**
     * Use the ModuleRelatedByDeliveryModuleId relation Module object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\ModuleQuery A secondary query class using the current class as primary query
     */
    public function useModuleRelatedByDeliveryModuleIdQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinModuleRelatedByDeliveryModuleId($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'ModuleRelatedByDeliveryModuleId', '\Thelia\Model\ModuleQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\Lang object
     *
     * @param \Thelia\Model\Lang|ObjectCollection $lang The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByLang($lang, $comparison = null)
    {
        if ($lang instanceof \Thelia\Model\Lang) {
            return $this
                ->addUsingAlias(OrderTableMap::LANG_ID, $lang->getId(), $comparison);
        } elseif ($lang instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderTableMap::LANG_ID, $lang->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByLang() only accepts arguments of type \Thelia\Model\Lang or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Lang relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function joinLang($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Lang');

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
            $this->addJoinObject($join, 'Lang');
        }

        return $this;
    }

    /**
     * Use the Lang relation Lang object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\LangQuery A secondary query class using the current class as primary query
     */
    public function useLangQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinLang($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Lang', '\Thelia\Model\LangQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\OrderProduct object
     *
     * @param \Thelia\Model\OrderProduct|ObjectCollection $orderProduct  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByOrderProduct($orderProduct, $comparison = null)
    {
        if ($orderProduct instanceof \Thelia\Model\OrderProduct) {
            return $this
                ->addUsingAlias(OrderTableMap::ID, $orderProduct->getOrderId(), $comparison);
        } elseif ($orderProduct instanceof ObjectCollection) {
            return $this
                ->useOrderProductQuery()
                ->filterByPrimaryKeys($orderProduct->getPrimaryKeys())
                ->endUse();
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
     * @return ChildOrderQuery The current query, for fluid interface
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
     * Filter the query by a related \Thelia\Model\OrderCoupon object
     *
     * @param \Thelia\Model\OrderCoupon|ObjectCollection $orderCoupon  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByOrderCoupon($orderCoupon, $comparison = null)
    {
        if ($orderCoupon instanceof \Thelia\Model\OrderCoupon) {
            return $this
                ->addUsingAlias(OrderTableMap::ID, $orderCoupon->getOrderId(), $comparison);
        } elseif ($orderCoupon instanceof ObjectCollection) {
            return $this
                ->useOrderCouponQuery()
                ->filterByPrimaryKeys($orderCoupon->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByOrderCoupon() only accepts arguments of type \Thelia\Model\OrderCoupon or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderCoupon relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function joinOrderCoupon($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderCoupon');

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
            $this->addJoinObject($join, 'OrderCoupon');
        }

        return $this;
    }

    /**
     * Use the OrderCoupon relation OrderCoupon object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderCouponQuery A secondary query class using the current class as primary query
     */
    public function useOrderCouponQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderCoupon($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderCoupon', '\Thelia\Model\OrderCouponQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\OrderVersion object
     *
     * @param \Thelia\Model\OrderVersion|ObjectCollection $orderVersion  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByOrderVersion($orderVersion, $comparison = null)
    {
        if ($orderVersion instanceof \Thelia\Model\OrderVersion) {
            return $this
                ->addUsingAlias(OrderTableMap::ID, $orderVersion->getId(), $comparison);
        } elseif ($orderVersion instanceof ObjectCollection) {
            return $this
                ->useOrderVersionQuery()
                ->filterByPrimaryKeys($orderVersion->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByOrderVersion() only accepts arguments of type \Thelia\Model\OrderVersion or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderVersion relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function joinOrderVersion($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderVersion');

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
            $this->addJoinObject($join, 'OrderVersion');
        }

        return $this;
    }

    /**
     * Use the OrderVersion relation OrderVersion object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderVersionQuery A secondary query class using the current class as primary query
     */
    public function useOrderVersionQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderVersion($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderVersion', '\Thelia\Model\OrderVersionQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildOrder $order Object to remove from the list of results
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function prune($order = null)
    {
        if ($order) {
            $this->addUsingAlias(OrderTableMap::ID, $order->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

    /**
     * Deletes all rows from the order table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderTableMap::DATABASE_NAME);
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
            OrderTableMap::clearInstancePool();
            OrderTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildOrder or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildOrder object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(OrderTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(OrderTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        OrderTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            OrderTableMap::clearRelatedInstancePool();
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
     * @return     ChildOrderQuery The current query, for fluid interface
     */
    public function recentlyUpdated($nbDays = 7)
    {
        return $this->addUsingAlias(OrderTableMap::UPDATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Filter by the latest created
     *
     * @param      int $nbDays Maximum age of in days
     *
     * @return     ChildOrderQuery The current query, for fluid interface
     */
    public function recentlyCreated($nbDays = 7)
    {
        return $this->addUsingAlias(OrderTableMap::CREATED_AT, time() - $nbDays * 24 * 60 * 60, Criteria::GREATER_EQUAL);
    }

    /**
     * Order by update date desc
     *
     * @return     ChildOrderQuery The current query, for fluid interface
     */
    public function lastUpdatedFirst()
    {
        return $this->addDescendingOrderByColumn(OrderTableMap::UPDATED_AT);
    }

    /**
     * Order by update date asc
     *
     * @return     ChildOrderQuery The current query, for fluid interface
     */
    public function firstUpdatedFirst()
    {
        return $this->addAscendingOrderByColumn(OrderTableMap::UPDATED_AT);
    }

    /**
     * Order by create date desc
     *
     * @return     ChildOrderQuery The current query, for fluid interface
     */
    public function lastCreatedFirst()
    {
        return $this->addDescendingOrderByColumn(OrderTableMap::CREATED_AT);
    }

    /**
     * Order by create date asc
     *
     * @return     ChildOrderQuery The current query, for fluid interface
     */
    public function firstCreatedFirst()
    {
        return $this->addAscendingOrderByColumn(OrderTableMap::CREATED_AT);
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

} // OrderQuery
