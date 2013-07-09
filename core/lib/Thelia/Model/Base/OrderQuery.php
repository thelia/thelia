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
 * @method     ChildOrderQuery orderByAddressInvoice($order = Criteria::ASC) Order by the address_invoice column
 * @method     ChildOrderQuery orderByAddressDelivery($order = Criteria::ASC) Order by the address_delivery column
 * @method     ChildOrderQuery orderByInvoiceDate($order = Criteria::ASC) Order by the invoice_date column
 * @method     ChildOrderQuery orderByCurrencyId($order = Criteria::ASC) Order by the currency_id column
 * @method     ChildOrderQuery orderByCurrencyRate($order = Criteria::ASC) Order by the currency_rate column
 * @method     ChildOrderQuery orderByTransaction($order = Criteria::ASC) Order by the transaction column
 * @method     ChildOrderQuery orderByDeliveryNum($order = Criteria::ASC) Order by the delivery_num column
 * @method     ChildOrderQuery orderByInvoice($order = Criteria::ASC) Order by the invoice column
 * @method     ChildOrderQuery orderByPostage($order = Criteria::ASC) Order by the postage column
 * @method     ChildOrderQuery orderByPayment($order = Criteria::ASC) Order by the payment column
 * @method     ChildOrderQuery orderByCarrier($order = Criteria::ASC) Order by the carrier column
 * @method     ChildOrderQuery orderByStatusId($order = Criteria::ASC) Order by the status_id column
 * @method     ChildOrderQuery orderByLang($order = Criteria::ASC) Order by the lang column
 * @method     ChildOrderQuery orderByCreatedAt($order = Criteria::ASC) Order by the created_at column
 * @method     ChildOrderQuery orderByUpdatedAt($order = Criteria::ASC) Order by the updated_at column
 *
 * @method     ChildOrderQuery groupById() Group by the id column
 * @method     ChildOrderQuery groupByRef() Group by the ref column
 * @method     ChildOrderQuery groupByCustomerId() Group by the customer_id column
 * @method     ChildOrderQuery groupByAddressInvoice() Group by the address_invoice column
 * @method     ChildOrderQuery groupByAddressDelivery() Group by the address_delivery column
 * @method     ChildOrderQuery groupByInvoiceDate() Group by the invoice_date column
 * @method     ChildOrderQuery groupByCurrencyId() Group by the currency_id column
 * @method     ChildOrderQuery groupByCurrencyRate() Group by the currency_rate column
 * @method     ChildOrderQuery groupByTransaction() Group by the transaction column
 * @method     ChildOrderQuery groupByDeliveryNum() Group by the delivery_num column
 * @method     ChildOrderQuery groupByInvoice() Group by the invoice column
 * @method     ChildOrderQuery groupByPostage() Group by the postage column
 * @method     ChildOrderQuery groupByPayment() Group by the payment column
 * @method     ChildOrderQuery groupByCarrier() Group by the carrier column
 * @method     ChildOrderQuery groupByStatusId() Group by the status_id column
 * @method     ChildOrderQuery groupByLang() Group by the lang column
 * @method     ChildOrderQuery groupByCreatedAt() Group by the created_at column
 * @method     ChildOrderQuery groupByUpdatedAt() Group by the updated_at column
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
 * @method     ChildOrderQuery leftJoinOrderAddressRelatedByAddressInvoice($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderAddressRelatedByAddressInvoice relation
 * @method     ChildOrderQuery rightJoinOrderAddressRelatedByAddressInvoice($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderAddressRelatedByAddressInvoice relation
 * @method     ChildOrderQuery innerJoinOrderAddressRelatedByAddressInvoice($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderAddressRelatedByAddressInvoice relation
 *
 * @method     ChildOrderQuery leftJoinOrderAddressRelatedByAddressDelivery($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderAddressRelatedByAddressDelivery relation
 * @method     ChildOrderQuery rightJoinOrderAddressRelatedByAddressDelivery($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderAddressRelatedByAddressDelivery relation
 * @method     ChildOrderQuery innerJoinOrderAddressRelatedByAddressDelivery($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderAddressRelatedByAddressDelivery relation
 *
 * @method     ChildOrderQuery leftJoinOrderStatus($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderStatus relation
 * @method     ChildOrderQuery rightJoinOrderStatus($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderStatus relation
 * @method     ChildOrderQuery innerJoinOrderStatus($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderStatus relation
 *
 * @method     ChildOrderQuery leftJoinOrderProduct($relationAlias = null) Adds a LEFT JOIN clause to the query using the OrderProduct relation
 * @method     ChildOrderQuery rightJoinOrderProduct($relationAlias = null) Adds a RIGHT JOIN clause to the query using the OrderProduct relation
 * @method     ChildOrderQuery innerJoinOrderProduct($relationAlias = null) Adds a INNER JOIN clause to the query using the OrderProduct relation
 *
 * @method     ChildOrderQuery leftJoinCouponOrder($relationAlias = null) Adds a LEFT JOIN clause to the query using the CouponOrder relation
 * @method     ChildOrderQuery rightJoinCouponOrder($relationAlias = null) Adds a RIGHT JOIN clause to the query using the CouponOrder relation
 * @method     ChildOrderQuery innerJoinCouponOrder($relationAlias = null) Adds a INNER JOIN clause to the query using the CouponOrder relation
 *
 * @method     ChildOrder findOne(ConnectionInterface $con = null) Return the first ChildOrder matching the query
 * @method     ChildOrder findOneOrCreate(ConnectionInterface $con = null) Return the first ChildOrder matching the query, or a new ChildOrder object populated from the query conditions when no match is found
 *
 * @method     ChildOrder findOneById(int $id) Return the first ChildOrder filtered by the id column
 * @method     ChildOrder findOneByRef(string $ref) Return the first ChildOrder filtered by the ref column
 * @method     ChildOrder findOneByCustomerId(int $customer_id) Return the first ChildOrder filtered by the customer_id column
 * @method     ChildOrder findOneByAddressInvoice(int $address_invoice) Return the first ChildOrder filtered by the address_invoice column
 * @method     ChildOrder findOneByAddressDelivery(int $address_delivery) Return the first ChildOrder filtered by the address_delivery column
 * @method     ChildOrder findOneByInvoiceDate(string $invoice_date) Return the first ChildOrder filtered by the invoice_date column
 * @method     ChildOrder findOneByCurrencyId(int $currency_id) Return the first ChildOrder filtered by the currency_id column
 * @method     ChildOrder findOneByCurrencyRate(double $currency_rate) Return the first ChildOrder filtered by the currency_rate column
 * @method     ChildOrder findOneByTransaction(string $transaction) Return the first ChildOrder filtered by the transaction column
 * @method     ChildOrder findOneByDeliveryNum(string $delivery_num) Return the first ChildOrder filtered by the delivery_num column
 * @method     ChildOrder findOneByInvoice(string $invoice) Return the first ChildOrder filtered by the invoice column
 * @method     ChildOrder findOneByPostage(double $postage) Return the first ChildOrder filtered by the postage column
 * @method     ChildOrder findOneByPayment(string $payment) Return the first ChildOrder filtered by the payment column
 * @method     ChildOrder findOneByCarrier(string $carrier) Return the first ChildOrder filtered by the carrier column
 * @method     ChildOrder findOneByStatusId(int $status_id) Return the first ChildOrder filtered by the status_id column
 * @method     ChildOrder findOneByLang(string $lang) Return the first ChildOrder filtered by the lang column
 * @method     ChildOrder findOneByCreatedAt(string $created_at) Return the first ChildOrder filtered by the created_at column
 * @method     ChildOrder findOneByUpdatedAt(string $updated_at) Return the first ChildOrder filtered by the updated_at column
 *
 * @method     array findById(int $id) Return ChildOrder objects filtered by the id column
 * @method     array findByRef(string $ref) Return ChildOrder objects filtered by the ref column
 * @method     array findByCustomerId(int $customer_id) Return ChildOrder objects filtered by the customer_id column
 * @method     array findByAddressInvoice(int $address_invoice) Return ChildOrder objects filtered by the address_invoice column
 * @method     array findByAddressDelivery(int $address_delivery) Return ChildOrder objects filtered by the address_delivery column
 * @method     array findByInvoiceDate(string $invoice_date) Return ChildOrder objects filtered by the invoice_date column
 * @method     array findByCurrencyId(int $currency_id) Return ChildOrder objects filtered by the currency_id column
 * @method     array findByCurrencyRate(double $currency_rate) Return ChildOrder objects filtered by the currency_rate column
 * @method     array findByTransaction(string $transaction) Return ChildOrder objects filtered by the transaction column
 * @method     array findByDeliveryNum(string $delivery_num) Return ChildOrder objects filtered by the delivery_num column
 * @method     array findByInvoice(string $invoice) Return ChildOrder objects filtered by the invoice column
 * @method     array findByPostage(double $postage) Return ChildOrder objects filtered by the postage column
 * @method     array findByPayment(string $payment) Return ChildOrder objects filtered by the payment column
 * @method     array findByCarrier(string $carrier) Return ChildOrder objects filtered by the carrier column
 * @method     array findByStatusId(int $status_id) Return ChildOrder objects filtered by the status_id column
 * @method     array findByLang(string $lang) Return ChildOrder objects filtered by the lang column
 * @method     array findByCreatedAt(string $created_at) Return ChildOrder objects filtered by the created_at column
 * @method     array findByUpdatedAt(string $updated_at) Return ChildOrder objects filtered by the updated_at column
 *
 */
abstract class OrderQuery extends ModelCriteria
{

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
        $sql = 'SELECT ID, REF, CUSTOMER_ID, ADDRESS_INVOICE, ADDRESS_DELIVERY, INVOICE_DATE, CURRENCY_ID, CURRENCY_RATE, TRANSACTION, DELIVERY_NUM, INVOICE, POSTAGE, PAYMENT, CARRIER, STATUS_ID, LANG, CREATED_AT, UPDATED_AT FROM order WHERE ID = :p0';
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
     * Filter the query on the address_invoice column
     *
     * Example usage:
     * <code>
     * $query->filterByAddressInvoice(1234); // WHERE address_invoice = 1234
     * $query->filterByAddressInvoice(array(12, 34)); // WHERE address_invoice IN (12, 34)
     * $query->filterByAddressInvoice(array('min' => 12)); // WHERE address_invoice > 12
     * </code>
     *
     * @see       filterByOrderAddressRelatedByAddressInvoice()
     *
     * @param     mixed $addressInvoice The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByAddressInvoice($addressInvoice = null, $comparison = null)
    {
        if (is_array($addressInvoice)) {
            $useMinMax = false;
            if (isset($addressInvoice['min'])) {
                $this->addUsingAlias(OrderTableMap::ADDRESS_INVOICE, $addressInvoice['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($addressInvoice['max'])) {
                $this->addUsingAlias(OrderTableMap::ADDRESS_INVOICE, $addressInvoice['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::ADDRESS_INVOICE, $addressInvoice, $comparison);
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
     * @see       filterByOrderAddressRelatedByAddressDelivery()
     *
     * @param     mixed $addressDelivery The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByAddressDelivery($addressDelivery = null, $comparison = null)
    {
        if (is_array($addressDelivery)) {
            $useMinMax = false;
            if (isset($addressDelivery['min'])) {
                $this->addUsingAlias(OrderTableMap::ADDRESS_DELIVERY, $addressDelivery['min'], Criteria::GREATER_EQUAL);
                $useMinMax = true;
            }
            if (isset($addressDelivery['max'])) {
                $this->addUsingAlias(OrderTableMap::ADDRESS_DELIVERY, $addressDelivery['max'], Criteria::LESS_EQUAL);
                $useMinMax = true;
            }
            if ($useMinMax) {
                return $this;
            }
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }
        }

        return $this->addUsingAlias(OrderTableMap::ADDRESS_DELIVERY, $addressDelivery, $comparison);
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
     * @return ChildOrderQuery The current query, for fluid interface
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

        return $this->addUsingAlias(OrderTableMap::TRANSACTION, $transaction, $comparison);
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
     * @return ChildOrderQuery The current query, for fluid interface
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

        return $this->addUsingAlias(OrderTableMap::DELIVERY_NUM, $deliveryNum, $comparison);
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
     * @return ChildOrderQuery The current query, for fluid interface
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

        return $this->addUsingAlias(OrderTableMap::INVOICE, $invoice, $comparison);
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
     * @return ChildOrderQuery The current query, for fluid interface
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

        return $this->addUsingAlias(OrderTableMap::PAYMENT, $payment, $comparison);
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
     * @return ChildOrderQuery The current query, for fluid interface
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

        return $this->addUsingAlias(OrderTableMap::CARRIER, $carrier, $comparison);
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
     * @return ChildOrderQuery The current query, for fluid interface
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

        return $this->addUsingAlias(OrderTableMap::LANG, $lang, $comparison);
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
    public function joinCurrency($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
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
    public function useCurrencyQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
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
    public function filterByOrderAddressRelatedByAddressInvoice($orderAddress, $comparison = null)
    {
        if ($orderAddress instanceof \Thelia\Model\OrderAddress) {
            return $this
                ->addUsingAlias(OrderTableMap::ADDRESS_INVOICE, $orderAddress->getId(), $comparison);
        } elseif ($orderAddress instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderTableMap::ADDRESS_INVOICE, $orderAddress->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByOrderAddressRelatedByAddressInvoice() only accepts arguments of type \Thelia\Model\OrderAddress or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderAddressRelatedByAddressInvoice relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function joinOrderAddressRelatedByAddressInvoice($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderAddressRelatedByAddressInvoice');

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
            $this->addJoinObject($join, 'OrderAddressRelatedByAddressInvoice');
        }

        return $this;
    }

    /**
     * Use the OrderAddressRelatedByAddressInvoice relation OrderAddress object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderAddressQuery A secondary query class using the current class as primary query
     */
    public function useOrderAddressRelatedByAddressInvoiceQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinOrderAddressRelatedByAddressInvoice($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderAddressRelatedByAddressInvoice', '\Thelia\Model\OrderAddressQuery');
    }

    /**
     * Filter the query by a related \Thelia\Model\OrderAddress object
     *
     * @param \Thelia\Model\OrderAddress|ObjectCollection $orderAddress The related object(s) to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByOrderAddressRelatedByAddressDelivery($orderAddress, $comparison = null)
    {
        if ($orderAddress instanceof \Thelia\Model\OrderAddress) {
            return $this
                ->addUsingAlias(OrderTableMap::ADDRESS_DELIVERY, $orderAddress->getId(), $comparison);
        } elseif ($orderAddress instanceof ObjectCollection) {
            if (null === $comparison) {
                $comparison = Criteria::IN;
            }

            return $this
                ->addUsingAlias(OrderTableMap::ADDRESS_DELIVERY, $orderAddress->toKeyValue('PrimaryKey', 'Id'), $comparison);
        } else {
            throw new PropelException('filterByOrderAddressRelatedByAddressDelivery() only accepts arguments of type \Thelia\Model\OrderAddress or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the OrderAddressRelatedByAddressDelivery relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function joinOrderAddressRelatedByAddressDelivery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        $tableMap = $this->getTableMap();
        $relationMap = $tableMap->getRelation('OrderAddressRelatedByAddressDelivery');

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
            $this->addJoinObject($join, 'OrderAddressRelatedByAddressDelivery');
        }

        return $this;
    }

    /**
     * Use the OrderAddressRelatedByAddressDelivery relation OrderAddress object
     *
     * @see useQuery()
     *
     * @param     string $relationAlias optional alias for the relation,
     *                                   to be used as main alias in the secondary query
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return   \Thelia\Model\OrderAddressQuery A secondary query class using the current class as primary query
     */
    public function useOrderAddressRelatedByAddressDeliveryQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinOrderAddressRelatedByAddressDelivery($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderAddressRelatedByAddressDelivery', '\Thelia\Model\OrderAddressQuery');
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
    public function joinOrderStatus($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
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
    public function useOrderStatusQuery($relationAlias = null, $joinType = Criteria::LEFT_JOIN)
    {
        return $this
            ->joinOrderStatus($relationAlias, $joinType)
            ->useQuery($relationAlias ? $relationAlias : 'OrderStatus', '\Thelia\Model\OrderStatusQuery');
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
     * Filter the query by a related \Thelia\Model\CouponOrder object
     *
     * @param \Thelia\Model\CouponOrder|ObjectCollection $couponOrder  the related object to use as filter
     * @param string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return ChildOrderQuery The current query, for fluid interface
     */
    public function filterByCouponOrder($couponOrder, $comparison = null)
    {
        if ($couponOrder instanceof \Thelia\Model\CouponOrder) {
            return $this
                ->addUsingAlias(OrderTableMap::ID, $couponOrder->getOrderId(), $comparison);
        } elseif ($couponOrder instanceof ObjectCollection) {
            return $this
                ->useCouponOrderQuery()
                ->filterByPrimaryKeys($couponOrder->getPrimaryKeys())
                ->endUse();
        } else {
            throw new PropelException('filterByCouponOrder() only accepts arguments of type \Thelia\Model\CouponOrder or Collection');
        }
    }

    /**
     * Adds a JOIN clause to the query using the CouponOrder relation
     *
     * @param     string $relationAlias optional alias for the relation
     * @param     string $joinType Accepted values are null, 'left join', 'right join', 'inner join'
     *
     * @return ChildOrderQuery The current query, for fluid interface
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
     * @see useQuery()
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

} // OrderQuery
