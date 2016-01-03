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
use Thelia\Model\OrderVersion as ChildOrderVersion;
use Thelia\Model\OrderVersionQuery as ChildOrderVersionQuery;
use Thelia\Model\Map\OrderVersionTableMap;

/**
 * Base class that represents a query for the 'order_version' table.
 *
 *
 *
 * @method     ChildOrderVersionQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     ChildOrderVersionQuery orderByRef($order = Criteria::ASC) Order by the ref column
 * @method     ChildOrderVersionQuery orderByCustomerId($order = Criteria::ASC) Order by the customer_id column
 * @method     ChildOrderVersionQuery orderByInvoiceOrderAddressId($order = Criteria::ASC) Order by the invoice_order_address_id column
 * @method     ChildOrderVersionQuery orderByDeliveryOrderAddressId($order = Criteria::ASC) Order by the delivery_order_address_id column
 * @method     ChildOrderVersionQuery orderByInvoiceDate($order = Criteria::ASC) Order by the invoice_date column
 * @method     ChildOrderVersionQuery orderByCurrencyId($order = Criteria::ASC) Order by the currency_id column
 * @method     ChildOrderVersionQuery orderByCurrencyRate($order = Criteria::ASC) Order by the currency_rate column
 * @method     ChildOrderVersionQuery orderByTransactionRef($order = Criteria::ASC) Order by the transaction_ref column
 * @method     ChildOrderVersionQuery orderByDeliveryRef($order = Criteria::ASC) Order by the delivery_ref column
 * @method     ChildOrderVersionQuery orderByInvoiceRef($order = Criteria::ASC) Order by the invoice_ref column
 * @method     ChildOrderVersionQuery orderByDiscount($order = Criteria::ASC) Order by the discount column
 * @method     ChildOrderVersionQuery orderByPostage($order = Criteria::ASC) Order by the postage column
 * @method     ChildOrderVersionQuery orderByPostageTax($order = Criteria::ASC) Order by the postage_tax column
 * @method     ChildOrderVersionQuery orderByPostageTaxRuleTitle($order = Criteria::ASC) Order by the postage_tax_rule_title column
 * @method     ChildOrderVersionQuery orderByPaymentModuleId($order = Criteria::ASC) Order by the payment_module_id column
 * @method     ChildOrderVersionQuery orderByDeliveryModuleId($order = Criteria::ASC) Order by the delivery_module_id column
 * @method     ChildOrderVersionQuery orderByStatusId($order = Criteria::ASC) Order by the status_id column
 * @method     ChildOrderVersionQuery orderByLangId($order = Criteria::ASC) Order by the lang_id column
 * @method     ChildOrderVersionQuery orderByCartId($order = Criteria::ASC) Order by the cart_id column
 * @method     ChildOrderVersionQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildOrderVersionQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 * @method     ChildOrderVersionQuery orderByVersion($order = Criteria::ASC) Order by the version column
 * @method     ChildOrderVersionQuery orderByVersionCreatedAt($order = Criteria::ASC) Order by the version_created_at column
 * @method     ChildOrderVersionQuery orderByVersionCreatedBy($order = Criteria::ASC) Order by the version_created_by column
 * @method     ChildOrderVersionQuery orderByCustomerIdVersion($order = Criteria::ASC) Order by the customer_id_version column
 *
 * @method     ChildOrderVersionQuery groupById() Group by the id column
 * @method     ChildOrderVersionQuery groupByRef() Group by the ref column
 * @method     ChildOrderVersionQuery groupByCustomerId() Group by the customer_id column
 * @method     ChildOrderVersionQuery groupByInvoiceOrderAddressId() Group by the invoice_order_address_id column
 * @method     ChildOrderVersionQuery groupByDeliveryOrderAddressId() Group by the delivery_order_address_id column
 * @method     ChildOrderVersionQuery groupByInvoiceDate() Group by the invoice_date column
 * @method     ChildOrderVersionQuery groupByCurrencyId() Group by the currency_id column
 * @method     ChildOrderVersionQuery groupByCurrencyRate() Group by the currency_rate column
 * @method     ChildOrderVersionQuery groupByTransactionRef() Group by the transaction_ref column
 * @method     ChildOrderVersionQuery groupByDeliveryRef() Group by the delivery_ref column
 * @method     ChildOrderVersionQuery groupByInvoiceRef() Group by the invoice_ref column
 * @method     ChildOrderVersionQuery groupByDiscount() Group by the discount column
 * @method     ChildOrderVersionQuery groupByPostage() Group by the postage column
 * @method     ChildOrderVersionQuery groupByPostageTax() Group by the postage_tax column
 * @method     ChildOrderVersionQuery groupByPostageTaxRuleTitle() Group by the postage_tax_rule_title column
 * @method     ChildOrderVersionQuery groupByPaymentModuleId() Group by the payment_module_id column
 * @method     ChildOrderVersionQuery groupByDeliveryModuleId() Group by the delivery_module_id column
 * @method     ChildOrderVersionQuery groupByStatusId() Group by the status_id column
 * @method     ChildOrderVersionQuery groupByLangId() Group by the lang_id column
 * @method     ChildOrderVersionQuery groupByCartId() Group by the cart_id column
 * @method     ChildOrderVersionQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildOrderVersionQuery groupByUpdatedAt() Group by the updated_at column
 * @method     ChildOrderVersionQuery groupByVersion() Group by the version column
 * @method     ChildOrderVersionQuery groupByVersionCreatedAt() Group by the version_created_at column
 * @method     ChildOrderVersionQuery groupByVersionCreatedBy() Group by the version_created_by column
 * @method     ChildOrderVersionQuery groupByCustomerIdVersion() Group by the customer_id_version column
 *
 * @method     ChildOrderVersionQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     ChildOrderVersionQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     ChildOrderVersionQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     ChildOrderVersionQuery leftJoinOrder($relationAlias = null) Adds a LEFT JOIN clause to the query using the Order relation
 * @method     ChildOrderVersionQuery rightJoinOrder($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Order relation
 * @method     ChildOrderVersionQuery innerJoinOrder($relationAlias = null) Adds a INNER JOIN clause to the query using the Order relation
 *
 * @method     ChildOrderVersion findOne(ConnectionInterface $con = null) Return the first ChildOrderVersion matching the query
 * @method     ChildOrderVersion findOneOrCreate(ConnectionInterface $con = null) Return the first ChildOrderVersion matching the query, or a new ChildOrderVersion object populated from the query conditions when no match is found
 *
 * @method     ChildOrderVersion findOneById(int $id) Return the first ChildOrderVersion filtered by the id column
 * @method     ChildOrderVersion findOneByRef(string $ref) Return the first ChildOrderVersion filtered by the ref column
 * @method     ChildOrderVersion findOneByCustomerId(int $customer_id) Return the first ChildOrderVersion filtered by the customer_id column
 * @method     ChildOrderVersion findOneByInvoiceOrderAddressId(int $invoice_order_address_id) Return the first ChildOrderVersion filtered by the invoice_order_address_id column
 * @method     ChildOrderVersion findOneByDeliveryOrderAddressId(int $delivery_order_address_id) Return the first ChildOrderVersion filtered by the delivery_order_address_id column
 * @method     ChildOrderVersion findOneByInvoiceDate(string $invoice_date) Return the first ChildOrderVersion filtered by the invoice_date column
 * @method     ChildOrderVersion findOneByCurrencyId(int $currency_id) Return the first ChildOrderVersion filtered by the currency_id column
 * @method     ChildOrderVersion findOneByCurrencyRate(double $currency_rate) Return the first ChildOrderVersion filtered by the currency_rate column
 * @method     ChildOrderVersion findOneByTransactionRef(string $transaction_ref) Return the first ChildOrderVersion filtered by the transaction_ref column
 * @method     ChildOrderVersion findOneByDeliveryRef(string $delivery_ref) Return the first ChildOrderVersion filtered by the delivery_ref column
 * @method     ChildOrderVersion findOneByInvoiceRef(string $invoice_ref) Return the first ChildOrderVersion filtered by the invoice_ref column
 * @method     ChildOrderVersion findOneByDiscount(string $discount) Return the first ChildOrderVersion filtered by the discount column
 * @method     ChildOrderVersion findOneByPostage(string $postage) Return the first ChildOrderVersion filtered by the postage column
 * @method     ChildOrderVersion findOneByPostageTax(string $postage_tax) Return the first ChildOrderVersion filtered by the postage_tax column
 * @method     ChildOrderVersion findOneByPostageTaxRuleTitle(string $postage_tax_rule_title) Return the first ChildOrderVersion filtered by the postage_tax_rule_title column
 * @method     ChildOrderVersion findOneByPaymentModuleId(int $payment_module_id) Return the first ChildOrderVersion filtered by the payment_module_id column
 * @method     ChildOrderVersion findOneByDeliveryModuleId(int $delivery_module_id) Return the first ChildOrderVersion filtered by the delivery_module_id column
 * @method     ChildOrderVersion findOneByStatusId(int $status_id) Return the first ChildOrderVersion filtered by the status_id column
 * @method     ChildOrderVersion findOneByLangId(int $lang_id) Return the first ChildOrderVersion filtered by the lang_id column
 * @method     ChildOrderVersion findOneByCartId(int $cart_id) Return the first ChildOrderVersion filtered by the cart_id column
 * @method     ChildOrderVersion findOneByCreatedAt(string $created_at) Return the first ChildOrderVersion filtered by the created_at column
 * @method     ChildOrderVersion findOneByUpdatedAt(string $updated_at) Return the first ChildOrderVersion filtered by the updated_at column
 * @method     ChildOrderVersion findOneByVersion(int $version) Return the first ChildOrderVersion filtered by the version column
 * @method     ChildOrderVersion findOneByVersionCreatedAt(string $version_created_at) Return the first ChildOrderVersion filtered by the version_created_at column
 * @method     ChildOrderVersion findOneByVersionCreatedBy(string $version_created_by) Return the first ChildOrderVersion filtered by the version_created_by column
 * @method     ChildOrderVersion findOneByCustomerIdVersion(int $customer_id_version) Return the first ChildOrderVersion filtered by the customer_id_version column
 *
 * @method     array findById(int $id) Return ChildOrderVersion objects filtered by the id column
 * @method     array findByRef(string $ref) Return ChildOrderVersion objects filtered by the ref column
 * @method     array findByCustomerId(int $customer_id) Return ChildOrderVersion objects filtered by the customer_id column
 * @method     array findByInvoiceOrderAddressId(int $invoice_order_address_id) Return ChildOrderVersion objects filtered by the invoice_order_address_id column
 * @method     array findByDeliveryOrderAddressId(int $delivery_order_address_id) Return ChildOrderVersion objects filtered by the delivery_order_address_id column
 * @method     array findByInvoiceDate(string $invoice_date) Return ChildOrderVersion objects filtered by the invoice_date column
 * @method     array findByCurrencyId(int $currency_id) Return ChildOrderVersion objects filtered by the currency_id column
 * @method     array findByCurrencyRate(double $currency_rate) Return ChildOrderVersion objects filtered by the currency_rate column
 * @method     array findByTransactionRef(string $transaction_ref) Return ChildOrderVersion objects filtered by the transaction_ref column
 * @method     array findByDeliveryRef(string $delivery_ref) Return ChildOrderVersion objects filtered by the delivery_ref column
 * @method     array findByInvoiceRef(string $invoice_ref) Return ChildOrderVersion objects filtered by the invoice_ref column
 * @method     array findByDiscount(string $discount) Return ChildOrderVersion objects filtered by the discount column
 * @method     array findByPostage(string $postage) Return ChildOrderVersion objects filtered by the postage column
 * @method     array findByPostageTax(string $postage_tax) Return ChildOrderVersion objects filtered by the postage_tax column
 * @method     array findByPostageTaxRuleTitle(string $postage_tax_rule_title) Return ChildOrderVersion objects filtered by the postage_tax_rule_title column
 * @method     array findByPaymentModuleId(int $payment_module_id) Return ChildOrderVersion objects filtered by the payment_module_id column
 * @method     array findByDeliveryModuleId(int $delivery_module_id) Return ChildOrderVersion objects filtered by the delivery_module_id column
 * @method     array findByStatusId(int $status_id) Return ChildOrderVersion objects filtered by the status_id column
 * @method     array findByLangId(int $lang_id) Return ChildOrderVersion objects filtered by the lang_id column
 * @method     array findByCartId(int $cart_id) Return ChildOrderVersion objects filtered by the cart_id column
 * @method     array findByCreatedAt(string $created_at) Return ChildOrderVersion objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildOrderVersion objects filtered by the updated_at column
 * @method     array findByVersion(int $version) Return ChildOrderVersion objects filtered by the version column
 * @method     array findByVersionCreatedAt(string $version_created_at) Return ChildOrderVersion objects filtered by the version_created_at column
 * @method     array findByVersionCreatedBy(string $version_created_by) Return ChildOrderVersion objects filtered by the version_created_by column
 * @method     array findByCustomerIdVersion(int $customer_id_version) Return ChildOrderVersion objects filtered by the customer_id_version column
 *
 */
abstract class OrderVersionQuery extends ModelCriteria
{

    /**
     * Initializes internal state of \Thelia\Model\Base\OrderVersionQuery object.
     *
     * @param     string $dbName The database name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'thelia', $modelName = '\\Thelia\\Model\\OrderVersion', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new ChildOrderVersionQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     Criteria $criteria Optional Criteria to build the query from
     *
     * @return ChildOrderVersionQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof \Thelia\Model\OrderVersionQuery) {
            return $criteria;
        }
        $query = new \Thelia\Model\OrderVersionQuery();
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
     * @return ChildOrderVersion|array|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = OrderVersionTableMap::getInstanceFromPool(serialize(array((string) $key[0], (string) $key[1]))))) && !$this->formatter) {
            // the object is already in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getServiceContainer()->getReadConnection(OrderVersionTableMap::DATABASE_NAME);
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
     * @return   ChildOrderVersion A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `REF`, `CUSTOMER_ID`, `INVOICE_ORDER_ADDRESS_ID`, `DELIVERY_ORDER_ADDRESS_ID`, `INVOICE_DATE`, `CURRENCY_ID`, `CURRENCY_RATE`, `TRANSACTION_REF`, `DELIVERY_REF`, `INVOICE_REF`, `DISCOUNT`, `POSTAGE`, `POSTAGE_TAX`, `POSTAGE_TAX_RULE_TITLE`, `PAYMENT_MODULE_ID`, `DELIVERY_MODULE_ID`, `STATUS_ID`, `LANG_ID`, `CART_ID`, `CREATED_AT`, `UPDATED_AT`, `VERSION`, `VERSION_CREATED_AT`, `VERSION_CREATED_BY`, `CUSTOMER_ID_VERSION` FROM `order_version` WHERE `ID` = :p0 AND `VERSION` = :p1';
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
            $obj = new ChildOrderVersion();
            $obj->hydrate($row);
            OrderVersionTableMap::addInstanceToPool($obj, serialize(array((string) $key[0], (string) $key[1])));
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
     * @return ChildOrderVersion|array|mixed the result, formatted by the current formatter
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {
        $this->addUsingAlias(OrderVersionTableMap::ID, $key[0], Criteria::EQUAL);
        $this->addUsingAlias(OrderVersionTableMap::VERSION, $key[1], Criteria::EQUAL);

        return $this;
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {
        if (empty($keys)) {
            return $this->add(null, '1<>1', Criteria::CUSTOM);
        }
        foreach ($keys as $key) {
            $cton0 = $this->getNewCriterion(OrderVersionTableMap::ID, $key[0], Criteria::EQUAL);
            $cton1 = $this->getNewCriterion(OrderVersionTableMap::VERSION, $key[1], Criteria::EQUAL);
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
     * @see       filterByOrder()
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id)) {
            $useMinMax = false;
            if (isset($id['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::ID, $id['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($id['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::ID, $id['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::ID, $id, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(OrderVersionTableMap::REF, $ref, $comparison);
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
     * @param     mixed $customerId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByCustomerId($customerId = null, $comparison = null)
    {
        if (is_array($customerId)) {
            $useMinMax = false;
            if (isset($customerId['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::CUSTOMER_ID, $customerId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($customerId['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::CUSTOMER_ID, $customerId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::CUSTOMER_ID, $customerId, $comparison);
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
     * @param     mixed $invoiceOrderAddressId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByInvoiceOrderAddressId($invoiceOrderAddressId = null, $comparison = null)
    {
        if (is_array($invoiceOrderAddressId)) {
            $useMinMax = false;
            if (isset($invoiceOrderAddressId['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::INVOICE_ORDER_ADDRESS_ID, $invoiceOrderAddressId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($invoiceOrderAddressId['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::INVOICE_ORDER_ADDRESS_ID, $invoiceOrderAddressId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::INVOICE_ORDER_ADDRESS_ID, $invoiceOrderAddressId, $comparison);
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
     * @param     mixed $deliveryOrderAddressId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByDeliveryOrderAddressId($deliveryOrderAddressId = null, $comparison = null)
    {
        if (is_array($deliveryOrderAddressId)) {
            $useMinMax = false;
            if (isset($deliveryOrderAddressId['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::DELIVERY_ORDER_ADDRESS_ID, $deliveryOrderAddressId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($deliveryOrderAddressId['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::DELIVERY_ORDER_ADDRESS_ID, $deliveryOrderAddressId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::DELIVERY_ORDER_ADDRESS_ID, $deliveryOrderAddressId, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByInvoiceDate($invoiceDate = null, $comparison = null)
    {
        if (is_array($invoiceDate)) {
            $useMinMax = false;
            if (isset($invoiceDate['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::INVOICE_DATE, $invoiceDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($invoiceDate['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::INVOICE_DATE, $invoiceDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::INVOICE_DATE, $invoiceDate, $comparison);
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
     * @param     mixed $currencyId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByCurrencyId($currencyId = null, $comparison = null)
    {
        if (is_array($currencyId)) {
            $useMinMax = false;
            if (isset($currencyId['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::CURRENCY_ID, $currencyId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($currencyId['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::CURRENCY_ID, $currencyId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::CURRENCY_ID, $currencyId, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByCurrencyRate($currencyRate = null, $comparison = null)
    {
        if (is_array($currencyRate)) {
            $useMinMax = false;
            if (isset($currencyRate['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::CURRENCY_RATE, $currencyRate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($currencyRate['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::CURRENCY_RATE, $currencyRate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::CURRENCY_RATE, $currencyRate, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(OrderVersionTableMap::TRANSACTION_REF, $transactionRef, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(OrderVersionTableMap::DELIVERY_REF, $deliveryRef, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(OrderVersionTableMap::INVOICE_REF, $invoiceRef, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByDiscount($discount = null, $comparison = null)
    {
        if (is_array($discount)) {
            $useMinMax = false;
            if (isset($discount['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::DISCOUNT, $discount['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($discount['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::DISCOUNT, $discount['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::DISCOUNT, $discount, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByPostage($postage = null, $comparison = null)
    {
        if (is_array($postage)) {
            $useMinMax = false;
            if (isset($postage['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::POSTAGE, $postage['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($postage['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::POSTAGE, $postage['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::POSTAGE, $postage, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByPostageTax($postageTax = null, $comparison = null)
    {
        if (is_array($postageTax)) {
            $useMinMax = false;
            if (isset($postageTax['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::POSTAGE_TAX, $postageTax['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($postageTax['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::POSTAGE_TAX, $postageTax['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::POSTAGE_TAX, $postageTax, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(OrderVersionTableMap::POSTAGE_TAX_RULE_TITLE, $postageTaxRuleTitle, $comparison);
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
     * @param     mixed $paymentModuleId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByPaymentModuleId($paymentModuleId = null, $comparison = null)
    {
        if (is_array($paymentModuleId)) {
            $useMinMax = false;
            if (isset($paymentModuleId['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::PAYMENT_MODULE_ID, $paymentModuleId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($paymentModuleId['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::PAYMENT_MODULE_ID, $paymentModuleId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::PAYMENT_MODULE_ID, $paymentModuleId, $comparison);
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
     * @param     mixed $deliveryModuleId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByDeliveryModuleId($deliveryModuleId = null, $comparison = null)
    {
        if (is_array($deliveryModuleId)) {
            $useMinMax = false;
            if (isset($deliveryModuleId['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::DELIVERY_MODULE_ID, $deliveryModuleId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($deliveryModuleId['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::DELIVERY_MODULE_ID, $deliveryModuleId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::DELIVERY_MODULE_ID, $deliveryModuleId, $comparison);
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
     * @param     mixed $statusId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByStatusId($statusId = null, $comparison = null)
    {
        if (is_array($statusId)) {
            $useMinMax = false;
            if (isset($statusId['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::STATUS_ID, $statusId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($statusId['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::STATUS_ID, $statusId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::STATUS_ID, $statusId, $comparison);
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
     * @param     mixed $langId The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByLangId($langId = null, $comparison = null)
    {
        if (is_array($langId)) {
            $useMinMax = false;
            if (isset($langId['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::LANG_ID, $langId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($langId['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::LANG_ID, $langId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::LANG_ID, $langId, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByCartId($cartId = null, $comparison = null)
    {
        if (is_array($cartId)) {
            $useMinMax = false;
            if (isset($cartId['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::CART_ID, $cartId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($cartId['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::CART_ID, $cartId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::CART_ID, $cartId, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::CREATED_AT, $createdAt, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::UPDATED_AT, $updatedAt, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByVersion($version = null, $comparison = null)
    {
        if (is_array($version)) {
            $useMinMax = false;
            if (isset($version['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::VERSION, $version['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($version['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::VERSION, $version['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::VERSION, $version, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByVersionCreatedAt($versionCreatedAt = null, $comparison = null)
    {
        if (is_array($versionCreatedAt)) {
            $useMinMax = false;
            if (isset($versionCreatedAt['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::VERSION_CREATED_AT, $versionCreatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($versionCreatedAt['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::VERSION_CREATED_AT, $versionCreatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::VERSION_CREATED_AT, $versionCreatedAt, $comparison);
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
     * @return ChildOrderVersionQuery The current query, for fluid interface
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

        return $this->addUsingAlias(OrderVersionTableMap::VERSION_CREATED_BY, $versionCreatedBy, $comparison);
    }

    /**
     * Filter the query on the customer_id_version column
     *
     * Example usage:
     * <code>
     * $query->filterByCustomerIdVersion(1234); // WHERE customer_id_version = 1234
     * $query->filterByCustomerIdVersion(array(12, 34)); // WHERE customer_id_version IN (12, 34)
     * $query->filterByCustomerIdVersion(array('min' => 12)); // WHERE customer_id_version > 12
     * </code>
     *
     * @param     mixed $customerIdVersion The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByCustomerIdVersion($customerIdVersion = null, $comparison = null)
    {
        if (is_array($customerIdVersion)) {
            $useMinMax = false;
            if (isset($customerIdVersion['min'])) {
                $this->addUsingAlias(OrderVersionTableMap::CUSTOMER_ID_VERSION, $customerIdVersion['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($customerIdVersion['max'])) {
                $this->addUsingAlias(OrderVersionTableMap::CUSTOMER_ID_VERSION, $customerIdVersion['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderVersionTableMap::CUSTOMER_ID_VERSION, $customerIdVersion, $comparison);
    }

    /**
     * Filter the query by a related \Thelia\Model\Order object
     *
     * @param \Thelia\Model\Order|ObjectCollection $order The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function filterByOrder($order, $comparison = null)
    {
        if ($order instanceof \Thelia\Model\Order) {
            return $this
                ->addUsingAlias(OrderVersionTableMap::ID, $order->getId(), $comparison);
        } elseif ($order instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderVersionTableMap::ID, $order->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByOrder() only accepts arguments of type \Thelia\Model\Order or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Order relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function joinOrder($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('Order');

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
            $this->addJoinObject($join, 'Order');
        }

        return $this;
    }

    /**
     * Use the Order relation Order object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderQuery A secondary query class using the current class as primary query
     */
    public function useOrderQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrder($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'Order', '\Thelia\Model\OrderQuery');
    }

    /**
     * Exclude object from result
     *
     * @param   ChildOrderVersion $orderVersion Object to remove from the list of results
     *
     * @return ChildOrderVersionQuery The current query, for fluid interface
     */
    public function prune($orderVersion = null)
    {
        if ($orderVersion) {
            $this->addCond('pruneCond0', $this->getAliasedColName(OrderVersionTableMap::ID), $orderVersion->getId(), Criteria::NOT_EQUAL);
            $this->addCond('pruneCond1', $this->getAliasedColName(OrderVersionTableMap::VERSION), $orderVersion->getVersion(), Criteria::NOT_EQUAL);
            $this->combine(array('pruneCond0', 'pruneCond1'), Criteria::LOGICAL_OR);
        }

        return $this;
    }

    /**
     * Deletes all rows from the order_version table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public function doDeleteAll(ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderVersionTableMap::DATABASE_NAME);
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
            OrderVersionTableMap::clearInstancePool();
            OrderVersionTableMap::clearRelatedInstancePool();

            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $affectedRows;
    }

    /**
     * Performs a DELETE on the database, given a ChildOrderVersion or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ChildOrderVersion object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(OrderVersionTableMap::DATABASE_NAME);
        }

        $criteria = $this;

        // Set the correct dbName
        $criteria->setDbName(OrderVersionTableMap::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();


        OrderVersionTableMap::removeInstanceFromPool($criteria);

            $affectedRows += ModelCriteria::delete($con);
            OrderVersionTableMap::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

} // OrderVersionQuery
