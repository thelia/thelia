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
use Thelia\Model\CouponOrder;
use Thelia\Model\Currency;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;
use Thelia\Model\OrderPeer;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;

/**
 * Base class that represents a query for the 'order' table.
 *
 *
 *
 * @method OrderQuery orderById($order = Criteria::ASC) Order by the id column
 * @method OrderQuery orderByRef($order = Criteria::ASC) Order by the ref column
 * @method OrderQuery orderByCustomerId($order = Criteria::ASC) Order by the customer_id column
 * @method OrderQuery orderByAddressInvoice($order = Criteria::ASC) Order by the address_invoice column
 * @method OrderQuery orderByAddressDelivery($order = Criteria::ASC) Order by the address_delivery column
 * @method OrderQuery orderByInvoiceDate($order = Criteria::ASC) Order by the invoice_date column
 * @method OrderQuery orderByCurrencyId($order = Criteria::ASC) Order by the currency_id column
 * @method OrderQuery orderByCurrencyRate($order = Criteria::ASC) Order by the currency_rate column
 * @method OrderQuery orderByTransaction($order = Criteria::ASC) Order by the transaction column
 * @method OrderQuery orderByDeliveryNum($order = Criteria::ASC) Order by the delivery_num column
 * @method OrderQuery orderByInvoice($order = Criteria::ASC) Order by the invoice column
 * @method OrderQuery orderByPostage($order = Criteria::ASC) Order by the postage column
 * @method OrderQuery orderByPayment($order = Criteria::ASC) Order by the payment column
 * @method OrderQuery orderByCarrier($order = Criteria::ASC) Order by the carrier column
 * @method OrderQuery orderByStatusId($order = Criteria::ASC) Order by the status_id column
 * @method OrderQuery orderByLang($order = Criteria::ASC) Order by the lang column
 * @method OrderQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method OrderQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method OrderQuery groupById() Group by the id column
 * @method OrderQuery groupByRef() Group by the ref column
 * @method OrderQuery groupByCustomerId() Group by the customer_id column
 * @method OrderQuery groupByAddressInvoice() Group by the address_invoice column
 * @method OrderQuery groupByAddressDelivery() Group by the address_delivery column
 * @method OrderQuery groupByInvoiceDate() Group by the invoice_date column
 * @method OrderQuery groupByCurrencyId() Group by the currency_id column
 * @method OrderQuery groupByCurrencyRate() Group by the currency_rate column
 * @method OrderQuery groupByTransaction() Group by the transaction column
 * @method OrderQuery groupByDeliveryNum() Group by the delivery_num column
 * @method OrderQuery groupByInvoice() Group by the invoice column
 * @method OrderQuery groupByPostage() Group by the postage column
 * @method OrderQuery groupByPayment() Group by the payment column
 * @method OrderQuery groupByCarrier() Group by the carrier column
 * @method OrderQuery groupByStatusId() Group by the status_id column
 * @method OrderQuery groupByLang() Group by the lang column
 * @method OrderQuery groupByCreatedAt() Group by the created_at column
 * @method OrderQuery groupByUpdatedAt() Group by the updated_at column
 *
 * @method OrderQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method OrderQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method OrderQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method OrderQuery leftJoinCouponOrder($relationAlias = null) Adds a LEFT JOIN clause to the query using the CouponOrder relation
 * @method OrderQuery rightJoinCouponOrder($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CouponOrder relation
 * @method OrderQuery innerJoinCouponOrder($relationAlias = null) Adds a INNER JOIN clause to the query using the CouponOrder relation
 *
 * @method OrderQuery leftJoinOrderProduct($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderProduct relation
 * @method OrderQuery rightJoinOrderProduct($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderProduct relation
 * @method OrderQuery innerJoinOrderProduct($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderProduct relation
 *
 * @method OrderQuery leftJoinCurrency($relationAlias = null) Adds a LEFT JOIN clause to the query using the Currency relation
 * @method OrderQuery rightJoinCurrency($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Currency relation
 * @method OrderQuery innerJoinCurrency($relationAlias = null) Adds a INNER JOIN clause to the query using the Currency relation
 *
 * @method OrderQuery leftJoinCustomer($relationAlias = null) Adds a LEFT JOIN clause to the query using the Customer relation
 * @method OrderQuery rightJoinCustomer($relationAlias = null) Adds a RIGHT JOIN clause to the query using the Customer relation
 * @method OrderQuery innerJoinCustomer($relationAlias = null) Adds a INNER JOIN clause to the query using the Customer relation
 *
 * @method OrderQuery leftJoinOrderAddress($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderAddress relation
 * @method OrderQuery rightJoinOrderAddress($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderAddress relation
 * @method OrderQuery innerJoinOrderAddress($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderAddress relation
 *
 * @method OrderQuery leftJoinOrderAddress($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderAddress relation
 * @method OrderQuery rightJoinOrderAddress($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderAddress relation
 * @method OrderQuery innerJoinOrderAddress($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderAddress relation
 *
 * @method OrderQuery leftJoinOrderStatus($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderStatus relation
 * @method OrderQuery rightJoinOrderStatus($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderStatus relation
 * @method OrderQuery innerJoinOrderStatus($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderStatus relation
 *
 * @method Order findOne(PropelPDO $con = null) Return the first Order matching the query
 * @method Order findOneOrCreate(PropelPDO $con = null) Return the first Order matching the query, or a new Order object populated from the query conditions when no match is found
 *
 * @method Order findOneById(int $id) Return the first Order filtered by the id column
 * @method Order findOneByRef(string $ref) Return the first Order filtered by the ref column
 * @method Order findOneByCustomerId(int $customer_id) Return the first Order filtered by the customer_id column
 * @method Order findOneByAddressInvoice(int $address_invoice) Return the first Order filtered by the address_invoice column
 * @method Order findOneByAddressDelivery(int $address_delivery) Return the first Order filtered by the address_delivery column
 * @method Order findOneByInvoiceDate(string $invoice_date) Return the first Order filtered by the invoice_date column
 * @method Order findOneByCurrencyId(int $currency_id) Return the first Order filtered by the currency_id column
 * @method Order findOneByCurrencyRate(double $currency_rate) Return the first Order filtered by the currency_rate column
 * @method Order findOneByTransaction(string $transaction) Return the first Order filtered by the transaction column
 * @method Order findOneByDeliveryNum(string $delivery_num) Return the first Order filtered by the delivery_num column
 * @method Order findOneByInvoice(string $invoice) Return the first Order filtered by the invoice column
 * @method Order findOneByPostage(double $postage) Return the first Order filtered by the postage column
 * @method Order findOneByPayment(string $payment) Return the first Order filtered by the payment column
 * @method Order findOneByCarrier(string $carrier) Return the first Order filtered by the carrier column
 * @method Order findOneByStatusId(int $status_id) Return the first Order filtered by the status_id column
 * @method Order findOneByLang(string $lang) Return the first Order filtered by the lang column
 * @method Order findOneByCreatedAt(string $created_at) Return the first Order filtered by the created_at column
 * @method Order findOneByUpdatedAt(string $updated_at) Return the first Order filtered by the updated_at column
 *
 * @method array findById(int $id) Return Order objects filtered by the id column
 * @method array findByRef(string $ref) Return Order objects filtered by the ref column
 * @method array findByCustomerId(int $customer_id) Return Order objects filtered by the customer_id column
 * @method array findByAddressInvoice(int $address_invoice) Return Order objects filtered by the address_invoice column
 * @method array findByAddressDelivery(int $address_delivery) Return Order objects filtered by the address_delivery column
 * @method array findByInvoiceDate(string $invoice_date) Return Order objects filtered by the invoice_date column
 * @method array findByCurrencyId(int $currency_id) Return Order objects filtered by the currency_id column
 * @method array findByCurrencyRate(double $currency_rate) Return Order objects filtered by the currency_rate column
 * @method array findByTransaction(string $transaction) Return Order objects filtered by the transaction column
 * @method array findByDeliveryNum(string $delivery_num) Return Order objects filtered by the delivery_num column
 * @method array findByInvoice(string $invoice) Return Order objects filtered by the invoice column
 * @method array findByPostage(double $postage) Return Order objects filtered by the postage column
 * @method array findByPayment(string $payment) Return Order objects filtered by the payment column
 * @method array findByCarrier(string $carrier) Return Order objects filtered by the carrier column
 * @method array findByStatusId(int $status_id) Return Order objects filtered by the status_id column
 * @method array findByLang(string $lang) Return Order objects filtered by the lang column
 * @method array findByCreatedAt(string $created_at) Return Order objects filtered by the created_at column
 * @method array findByUpdatedAt(string $updated_at) Return Order objects filtered by the updated_at column
 *
 * @package    propel.generator.Thelia.Model.om
 */
abstract class BaseOrderQuery extends ModelCriteria
{
    /**
     * Initializes internal state of BaseOrderQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'mydb', $modelName = 'Thelia\\Model\\Order', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new OrderQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     OrderQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return OrderQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof OrderQuery) {
            return $criteria;
        }
        $query = new OrderQuery();
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
     * @return   Order|Order[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = OrderPeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
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
     * @return   Order A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT `ID`, `REF`, `CUSTOMER_ID`, `ADDRESS_INVOICE`, `ADDRESS_DELIVERY`, `INVOICE_DATE`, `CURRENCY_ID`, `CURRENCY_RATE`, `TRANSACTION`, `DELIVERY_NUM`, `INVOICE`, `POSTAGE`, `PAYMENT`, `CARRIER`, `STATUS_ID`, `LANG`, `CREATED_AT`, `UPDATED_AT` FROM `order` WHERE `ID` = :p0';
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
            $obj = new Order();
            $obj->hydrate($row);
            OrderPeer::addInstanceToPool($obj, (string) $key);
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
     * @return Order|Order[]|mixed the result, formatted by the current formatter
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
     * @return PropelObjectCollection|Order[]|mixed the list of results, formatted by the current formatter
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
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(OrderPeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(OrderPeer::ID, $keys, Criteria::IN);
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
     * @see       filterByCouponOrder()
     *
     * @see       filterByOrderProduct()
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(OrderPeer::ID, $id, $comparison);
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
     * @return OrderQuery The current query, for fluid interface
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

        return $this->addUsingAlias(OrderPeer::REF, $ref, $comparison);
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
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByCustomerId($customerId = null, $comparison = null)
    {
        if (is_array($customerId)) {
            $useMinMax = false;
            if (isset($customerId['min'])) {
                $this->addUsingAlias(OrderPeer::CUSTOMER_ID, $customerId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($customerId['max'])) {
                $this->addUsingAlias(OrderPeer::CUSTOMER_ID, $customerId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderPeer::CUSTOMER_ID, $customerId, $comparison);
    }

    /**
     * Filter the query on the address_invoice column
     *
     * Example usage:
     * <code>
     * $query->filterByAddressInvoice(1234); // WHERE address_invoice = 1234
     * $query->filterByAddressInvoice(array(12, 34)); // WHERE address_invoice IN (12, 34)
     * $query->filterByAddressInvoice(array('min' => 12)); // WHERE address_invoice > 12
     * </code>
     *
     * @param     mixed $addressInvoice The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByAddressInvoice($addressInvoice = null, $comparison = null)
    {
        if (is_array($addressInvoice)) {
            $useMinMax = false;
            if (isset($addressInvoice['min'])) {
                $this->addUsingAlias(OrderPeer::ADDRESS_INVOICE, $addressInvoice['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($addressInvoice['max'])) {
                $this->addUsingAlias(OrderPeer::ADDRESS_INVOICE, $addressInvoice['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderPeer::ADDRESS_INVOICE, $addressInvoice, $comparison);
    }

    /**
     * Filter the query on the address_delivery column
     *
     * Example usage:
     * <code>
     * $query->filterByAddressDelivery(1234); // WHERE address_delivery = 1234
     * $query->filterByAddressDelivery(array(12, 34)); // WHERE address_delivery IN (12, 34)
     * $query->filterByAddressDelivery(array('min' => 12)); // WHERE address_delivery > 12
     * </code>
     *
     * @param     mixed $addressDelivery The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByAddressDelivery($addressDelivery = null, $comparison = null)
    {
        if (is_array($addressDelivery)) {
            $useMinMax = false;
            if (isset($addressDelivery['min'])) {
                $this->addUsingAlias(OrderPeer::ADDRESS_DELIVERY, $addressDelivery['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($addressDelivery['max'])) {
                $this->addUsingAlias(OrderPeer::ADDRESS_DELIVERY, $addressDelivery['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderPeer::ADDRESS_DELIVERY, $addressDelivery, $comparison);
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
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByInvoiceDate($invoiceDate = null, $comparison = null)
    {
        if (is_array($invoiceDate)) {
            $useMinMax = false;
            if (isset($invoiceDate['min'])) {
                $this->addUsingAlias(OrderPeer::INVOICE_DATE, $invoiceDate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($invoiceDate['max'])) {
                $this->addUsingAlias(OrderPeer::INVOICE_DATE, $invoiceDate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderPeer::INVOICE_DATE, $invoiceDate, $comparison);
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
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByCurrencyId($currencyId = null, $comparison = null)
    {
        if (is_array($currencyId)) {
            $useMinMax = false;
            if (isset($currencyId['min'])) {
                $this->addUsingAlias(OrderPeer::CURRENCY_ID, $currencyId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($currencyId['max'])) {
                $this->addUsingAlias(OrderPeer::CURRENCY_ID, $currencyId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderPeer::CURRENCY_ID, $currencyId, $comparison);
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
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByCurrencyRate($currencyRate = null, $comparison = null)
    {
        if (is_array($currencyRate)) {
            $useMinMax = false;
            if (isset($currencyRate['min'])) {
                $this->addUsingAlias(OrderPeer::CURRENCY_RATE, $currencyRate['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($currencyRate['max'])) {
                $this->addUsingAlias(OrderPeer::CURRENCY_RATE, $currencyRate['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderPeer::CURRENCY_RATE, $currencyRate, $comparison);
    }

    /**
     * Filter the query on the transaction column
     *
     * Example usage:
     * <code>
     * $query->filterByTransaction('fooValue');   // WHERE transaction = 'fooValue'
     * $query->filterByTransaction('%fooValue%'); // WHERE transaction LIKE '%fooValue%'
     * </code>
     *
     * @param     string $transaction The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByTransaction($transaction = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($transaction)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $transaction)) {
                $transaction = str_replace('*', '%', $transaction);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderPeer::TRANSACTION, $transaction, $comparison);
    }

    /**
     * Filter the query on the delivery_num column
     *
     * Example usage:
     * <code>
     * $query->filterByDeliveryNum('fooValue');   // WHERE delivery_num = 'fooValue'
     * $query->filterByDeliveryNum('%fooValue%'); // WHERE delivery_num LIKE '%fooValue%'
     * </code>
     *
     * @param     string $deliveryNum The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByDeliveryNum($deliveryNum = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($deliveryNum)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $deliveryNum)) {
                $deliveryNum = str_replace('*', '%', $deliveryNum);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderPeer::DELIVERY_NUM, $deliveryNum, $comparison);
    }

    /**
     * Filter the query on the invoice column
     *
     * Example usage:
     * <code>
     * $query->filterByInvoice('fooValue');   // WHERE invoice = 'fooValue'
     * $query->filterByInvoice('%fooValue%'); // WHERE invoice LIKE '%fooValue%'
     * </code>
     *
     * @param     string $invoice The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByInvoice($invoice = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($invoice)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $invoice)) {
                $invoice = str_replace('*', '%', $invoice);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderPeer::INVOICE, $invoice, $comparison);
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
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByPostage($postage = null, $comparison = null)
    {
        if (is_array($postage)) {
            $useMinMax = false;
            if (isset($postage['min'])) {
                $this->addUsingAlias(OrderPeer::POSTAGE, $postage['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($postage['max'])) {
                $this->addUsingAlias(OrderPeer::POSTAGE, $postage['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderPeer::POSTAGE, $postage, $comparison);
    }

    /**
     * Filter the query on the payment column
     *
     * Example usage:
     * <code>
     * $query->filterByPayment('fooValue');   // WHERE payment = 'fooValue'
     * $query->filterByPayment('%fooValue%'); // WHERE payment LIKE '%fooValue%'
     * </code>
     *
     * @param     string $payment The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByPayment($payment = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($payment)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $payment)) {
                $payment = str_replace('*', '%', $payment);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderPeer::PAYMENT, $payment, $comparison);
    }

    /**
     * Filter the query on the carrier column
     *
     * Example usage:
     * <code>
     * $query->filterByCarrier('fooValue');   // WHERE carrier = 'fooValue'
     * $query->filterByCarrier('%fooValue%'); // WHERE carrier LIKE '%fooValue%'
     * </code>
     *
     * @param     string $carrier The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByCarrier($carrier = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($carrier)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $carrier)) {
                $carrier = str_replace('*', '%', $carrier);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderPeer::CARRIER, $carrier, $comparison);
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
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByStatusId($statusId = null, $comparison = null)
    {
        if (is_array($statusId)) {
            $useMinMax = false;
            if (isset($statusId['min'])) {
                $this->addUsingAlias(OrderPeer::STATUS_ID, $statusId['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($statusId['max'])) {
                $this->addUsingAlias(OrderPeer::STATUS_ID, $statusId['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderPeer::STATUS_ID, $statusId, $comparison);
    }

    /**
     * Filter the query on the lang column
     *
     * Example usage:
     * <code>
     * $query->filterByLang('fooValue');   // WHERE lang = 'fooValue'
     * $query->filterByLang('%fooValue%'); // WHERE lang LIKE '%fooValue%'
     * </code>
     *
     * @param     string $lang The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByLang($lang = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($lang)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $lang)) {
                $lang = str_replace('*', '%', $lang);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(OrderPeer::LANG, $lang, $comparison);
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
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByCreatedAt($createdAt = null, $comparison = null)
    {
        if (is_array($createdAt)) {
            $useMinMax = false;
            if (isset($createdAt['min'])) {
                $this->addUsingAlias(OrderPeer::CREATED_AT, $createdAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($createdAt['max'])) {
                $this->addUsingAlias(OrderPeer::CREATED_AT, $createdAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderPeer::CREATED_AT, $createdAt, $comparison);
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
     * @return OrderQuery The current query, for fluid interface
     */
    public function filterByUpdatedAt($updatedAt = null, $comparison = null)
    {
        if (is_array($updatedAt)) {
            $useMinMax = false;
            if (isset($updatedAt['min'])) {
                $this->addUsingAlias(OrderPeer::UPDATED_AT, $updatedAt['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($updatedAt['max'])) {
                $this->addUsingAlias(OrderPeer::UPDATED_AT, $updatedAt['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderPeer::UPDATED_AT, $updatedAt, $comparison);
    }

    /**
     * Filter the query by a related CouponOrder object
     *
     * @param   CouponOrder|PropelObjectCollection $couponOrder The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   OrderQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByCouponOrder($couponOrder, $comparison = null)
    {
        if ($couponOrder instanceof CouponOrder) {
            return $this
                ->addUsingAlias(OrderPeer::ID, $couponOrder->getOrderId(), $comparison);
        } elseif ($couponOrder instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderPeer::ID, $couponOrder->toKeyValue('PrimaryKey', 'OrderId'), $comparison);
        } else {
            throw new PropelException('filterByCouponOrder() only accepts arguments of type CouponOrder or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CouponOrder relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return OrderQuery The current query, for fluid interface
     */
    public function joinCouponOrder($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('CouponOrder');

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
            $this->addJoinObject($join, 'CouponOrder');
        }

        return $this;
    }

    /**
     * Use the CouponOrder relation CouponOrder object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\CouponOrderQuery A secondary query class using the current class as primary query
     */
    public function useCouponOrderQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinCouponOrder($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'CouponOrder', '\Thelia\Model\CouponOrderQuery');
    }

    /**
     * Filter the query by a related OrderProduct object
     *
     * @param   OrderProduct|PropelObjectCollection $orderProduct The related object(s) to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   OrderQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByOrderProduct($orderProduct, $comparison = null)
    {
        if ($orderProduct instanceof OrderProduct) {
            return $this
                ->addUsingAlias(OrderPeer::ID, $orderProduct->getOrderId(), $comparison);
        } elseif ($orderProduct instanceof PropelObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderPeer::ID, $orderProduct->toKeyValue('PrimaryKey', 'OrderId'), $comparison);
        } else {
            throw new PropelException('filterByOrderProduct() only accepts arguments of type OrderProduct or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderProduct relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return OrderQuery The current query, for fluid interface
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
     * @see       useQuery()
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
     * Filter the query by a related Currency object
     *
     * @param   Currency|PropelObjectCollection $currency  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   OrderQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByCurrency($currency, $comparison = null)
    {
        if ($currency instanceof Currency) {
            return $this
                ->addUsingAlias(OrderPeer::CURRENCY_ID, $currency->getId(), $comparison);
        } elseif ($currency instanceof PropelObjectCollection) {
            return $this
                ->useCurrencyQuery()
                ->filterByPrimaryKeys($currency->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCurrency() only accepts arguments of type Currency or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Currency relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return OrderQuery The current query, for fluid interface
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
     * @see       useQuery()
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
     * Filter the query by a related Customer object
     *
     * @param   Customer|PropelObjectCollection $customer  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   OrderQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByCustomer($customer, $comparison = null)
    {
        if ($customer instanceof Customer) {
            return $this
                ->addUsingAlias(OrderPeer::CUSTOMER_ID, $customer->getId(), $comparison);
        } elseif ($customer instanceof PropelObjectCollection) {
            return $this
                ->useCustomerQuery()
                ->filterByPrimaryKeys($customer->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCustomer() only accepts arguments of type Customer or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the Customer relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return OrderQuery The current query, for fluid interface
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
     * @see       useQuery()
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
     * Filter the query by a related OrderAddress object
     *
     * @param   OrderAddress|PropelObjectCollection $orderAddress  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   OrderQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByOrderAddress($orderAddress, $comparison = null)
    {
        if ($orderAddress instanceof OrderAddress) {
            return $this
                ->addUsingAlias(OrderPeer::ADDRESS_INVOICE, $orderAddress->getId(), $comparison);
        } elseif ($orderAddress instanceof PropelObjectCollection) {
            return $this
                ->useOrderAddressQuery()
                ->filterByPrimaryKeys($orderAddress->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByOrderAddress() only accepts arguments of type OrderAddress or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderAddress relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return OrderQuery The current query, for fluid interface
     */
    public function joinOrderAddress($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderAddress');

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
            $this->addJoinObject($join, 'OrderAddress');
        }

        return $this;
    }

    /**
     * Use the OrderAddress relation OrderAddress object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderAddressQuery A secondary query class using the current class as primary query
     */
    public function useOrderAddressQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderAddress($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderAddress', '\Thelia\Model\OrderAddressQuery');
    }

    /**
     * Filter the query by a related OrderAddress object
     *
     * @param   OrderAddress|PropelObjectCollection $orderAddress  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   OrderQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByOrderAddress($orderAddress, $comparison = null)
    {
        if ($orderAddress instanceof OrderAddress) {
            return $this
                ->addUsingAlias(OrderPeer::ADDRESS_DELIVERY, $orderAddress->getId(), $comparison);
        } elseif ($orderAddress instanceof PropelObjectCollection) {
            return $this
                ->useOrderAddressQuery()
                ->filterByPrimaryKeys($orderAddress->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByOrderAddress() only accepts arguments of type OrderAddress or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderAddress relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return OrderQuery The current query, for fluid interface
     */
    public function joinOrderAddress($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderAddress');

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
            $this->addJoinObject($join, 'OrderAddress');
        }

        return $this;
    }

    /**
     * Use the OrderAddress relation OrderAddress object
     *
     * @see       useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderAddressQuery A secondary query class using the current class as primary query
     */
    public function useOrderAddressQuery($relationAlias = null, $joinType = Criteria::INNER_JOIN)
    {
        return $this
            ->joinOrderAddress($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderAddress', '\Thelia\Model\OrderAddressQuery');
    }

    /**
     * Filter the query by a related OrderStatus object
     *
     * @param   OrderStatus|PropelObjectCollection $orderStatus  the related object to use as filter
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return   OrderQuery The current query, for fluid interface
     * @throws   PropelException - if the provided filter is invalid.
     */
    public function filterByOrderStatus($orderStatus, $comparison = null)
    {
        if ($orderStatus instanceof OrderStatus) {
            return $this
                ->addUsingAlias(OrderPeer::STATUS_ID, $orderStatus->getId(), $comparison);
        } elseif ($orderStatus instanceof PropelObjectCollection) {
            return $this
                ->useOrderStatusQuery()
                ->filterByPrimaryKeys($orderStatus->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByOrderStatus() only accepts arguments of type OrderStatus or PropelCollection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderStatus relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return OrderQuery The current query, for fluid interface
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
     * @see       useQuery()
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
     * Exclude object from result
     *
     * @param   Order $order Object to remove from the list of results
     *
     * @return OrderQuery The current query, for fluid interface
     */
    public function prune($order = null)
    {
        if ($order) {
            $this->addUsingAlias(OrderPeer::ID, $order->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

}
