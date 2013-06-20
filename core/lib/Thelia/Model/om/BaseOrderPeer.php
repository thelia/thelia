<?php

namespace Thelia\Model\om;

use \BasePeer;
use \Criteria;
use \PDO;
use \PDOStatement;
use \Propel;
use \PropelException;
use \PropelPDO;
use Thelia\Model\CouponOrderPeer;
use Thelia\Model\CurrencyPeer;
use Thelia\Model\CustomerPeer;
use Thelia\Model\Order;
use Thelia\Model\OrderAddressPeer;
use Thelia\Model\OrderPeer;
use Thelia\Model\OrderProductPeer;
use Thelia\Model\OrderStatusPeer;
use Thelia\Model\map\OrderTableMap;

/**
 * Base static class for performing query and update operations on the 'order' table.
 *
 *
 *
 * @package propel.generator.Thelia.Model.om
 */
abstract class BaseOrderPeer
{

    /** the default database name for this class */
    const DATABASE_NAME = 'thelia';

    /** the table name for this class */
    const TABLE_NAME = 'order';

    /** the related Propel class for this table */
    const OM_CLASS = 'Thelia\\Model\\Order';

    /** the related TableMap class for this table */
    const TM_CLASS = 'OrderTableMap';

    /** The total number of columns. */
    const NUM_COLUMNS = 18;

    /** The number of lazy-loaded columns. */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /** The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS) */
    const NUM_HYDRATE_COLUMNS = 18;

    /** the column name for the id field */
    const ID = 'order.id';

    /** the column name for the ref field */
    const REF = 'order.ref';

    /** the column name for the customer_id field */
    const CUSTOMER_ID = 'order.customer_id';

    /** the column name for the address_invoice field */
    const ADDRESS_INVOICE = 'order.address_invoice';

    /** the column name for the address_delivery field */
    const ADDRESS_DELIVERY = 'order.address_delivery';

    /** the column name for the invoice_date field */
    const INVOICE_DATE = 'order.invoice_date';

    /** the column name for the currency_id field */
    const CURRENCY_ID = 'order.currency_id';

    /** the column name for the currency_rate field */
    const CURRENCY_RATE = 'order.currency_rate';

    /** the column name for the transaction field */
    const TRANSACTION = 'order.transaction';

    /** the column name for the delivery_num field */
    const DELIVERY_NUM = 'order.delivery_num';

    /** the column name for the invoice field */
    const INVOICE = 'order.invoice';

    /** the column name for the postage field */
    const POSTAGE = 'order.postage';

    /** the column name for the payment field */
    const PAYMENT = 'order.payment';

    /** the column name for the carrier field */
    const CARRIER = 'order.carrier';

    /** the column name for the status_id field */
    const STATUS_ID = 'order.status_id';

    /** the column name for the lang field */
    const LANG = 'order.lang';

    /** the column name for the created_at field */
    const CREATED_AT = 'order.created_at';

    /** the column name for the updated_at field */
    const UPDATED_AT = 'order.updated_at';

    /** The default string format for model objects of the related table **/
    const DEFAULT_STRING_FORMAT = 'YAML';

    /**
     * An identiy map to hold any loaded instances of Order objects.
     * This must be public so that other peer classes can access this when hydrating from JOIN
     * queries.
     * @var        array Order[]
     */
    public static $instances = array();


    /**
     * holds an array of fieldnames
     *
     * first dimension keys are the type constants
     * e.g. OrderPeer::$fieldNames[OrderPeer::TYPE_PHPNAME][0] = 'Id'
     */
    protected static $fieldNames = array (
        BasePeer::TYPE_PHPNAME => array ('Id', 'Ref', 'CustomerId', 'AddressInvoice', 'AddressDelivery', 'InvoiceDate', 'CurrencyId', 'CurrencyRate', 'Transaction', 'DeliveryNum', 'Invoice', 'Postage', 'Payment', 'Carrier', 'StatusId', 'Lang', 'CreatedAt', 'UpdatedAt', ),
        BasePeer::TYPE_STUDLYPHPNAME => array ('id', 'ref', 'customerId', 'addressInvoice', 'addressDelivery', 'invoiceDate', 'currencyId', 'currencyRate', 'transaction', 'deliveryNum', 'invoice', 'postage', 'payment', 'carrier', 'statusId', 'lang', 'createdAt', 'updatedAt', ),
        BasePeer::TYPE_COLNAME => array (OrderPeer::ID, OrderPeer::REF, OrderPeer::CUSTOMER_ID, OrderPeer::ADDRESS_INVOICE, OrderPeer::ADDRESS_DELIVERY, OrderPeer::INVOICE_DATE, OrderPeer::CURRENCY_ID, OrderPeer::CURRENCY_RATE, OrderPeer::TRANSACTION, OrderPeer::DELIVERY_NUM, OrderPeer::INVOICE, OrderPeer::POSTAGE, OrderPeer::PAYMENT, OrderPeer::CARRIER, OrderPeer::STATUS_ID, OrderPeer::LANG, OrderPeer::CREATED_AT, OrderPeer::UPDATED_AT, ),
        BasePeer::TYPE_RAW_COLNAME => array ('ID', 'REF', 'CUSTOMER_ID', 'ADDRESS_INVOICE', 'ADDRESS_DELIVERY', 'INVOICE_DATE', 'CURRENCY_ID', 'CURRENCY_RATE', 'TRANSACTION', 'DELIVERY_NUM', 'INVOICE', 'POSTAGE', 'PAYMENT', 'CARRIER', 'STATUS_ID', 'LANG', 'CREATED_AT', 'UPDATED_AT', ),
        BasePeer::TYPE_FIELDNAME => array ('id', 'ref', 'customer_id', 'address_invoice', 'address_delivery', 'invoice_date', 'currency_id', 'currency_rate', 'transaction', 'delivery_num', 'invoice', 'postage', 'payment', 'carrier', 'status_id', 'lang', 'created_at', 'updated_at', ),
        BasePeer::TYPE_NUM => array (0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. OrderPeer::$fieldNames[BasePeer::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        BasePeer::TYPE_PHPNAME => array ('Id' => 0, 'Ref' => 1, 'CustomerId' => 2, 'AddressInvoice' => 3, 'AddressDelivery' => 4, 'InvoiceDate' => 5, 'CurrencyId' => 6, 'CurrencyRate' => 7, 'Transaction' => 8, 'DeliveryNum' => 9, 'Invoice' => 10, 'Postage' => 11, 'Payment' => 12, 'Carrier' => 13, 'StatusId' => 14, 'Lang' => 15, 'CreatedAt' => 16, 'UpdatedAt' => 17, ),
        BasePeer::TYPE_STUDLYPHPNAME => array ('id' => 0, 'ref' => 1, 'customerId' => 2, 'addressInvoice' => 3, 'addressDelivery' => 4, 'invoiceDate' => 5, 'currencyId' => 6, 'currencyRate' => 7, 'transaction' => 8, 'deliveryNum' => 9, 'invoice' => 10, 'postage' => 11, 'payment' => 12, 'carrier' => 13, 'statusId' => 14, 'lang' => 15, 'createdAt' => 16, 'updatedAt' => 17, ),
        BasePeer::TYPE_COLNAME => array (OrderPeer::ID => 0, OrderPeer::REF => 1, OrderPeer::CUSTOMER_ID => 2, OrderPeer::ADDRESS_INVOICE => 3, OrderPeer::ADDRESS_DELIVERY => 4, OrderPeer::INVOICE_DATE => 5, OrderPeer::CURRENCY_ID => 6, OrderPeer::CURRENCY_RATE => 7, OrderPeer::TRANSACTION => 8, OrderPeer::DELIVERY_NUM => 9, OrderPeer::INVOICE => 10, OrderPeer::POSTAGE => 11, OrderPeer::PAYMENT => 12, OrderPeer::CARRIER => 13, OrderPeer::STATUS_ID => 14, OrderPeer::LANG => 15, OrderPeer::CREATED_AT => 16, OrderPeer::UPDATED_AT => 17, ),
        BasePeer::TYPE_RAW_COLNAME => array ('ID' => 0, 'REF' => 1, 'CUSTOMER_ID' => 2, 'ADDRESS_INVOICE' => 3, 'ADDRESS_DELIVERY' => 4, 'INVOICE_DATE' => 5, 'CURRENCY_ID' => 6, 'CURRENCY_RATE' => 7, 'TRANSACTION' => 8, 'DELIVERY_NUM' => 9, 'INVOICE' => 10, 'POSTAGE' => 11, 'PAYMENT' => 12, 'CARRIER' => 13, 'STATUS_ID' => 14, 'LANG' => 15, 'CREATED_AT' => 16, 'UPDATED_AT' => 17, ),
        BasePeer::TYPE_FIELDNAME => array ('id' => 0, 'ref' => 1, 'customer_id' => 2, 'address_invoice' => 3, 'address_delivery' => 4, 'invoice_date' => 5, 'currency_id' => 6, 'currency_rate' => 7, 'transaction' => 8, 'delivery_num' => 9, 'invoice' => 10, 'postage' => 11, 'payment' => 12, 'carrier' => 13, 'status_id' => 14, 'lang' => 15, 'created_at' => 16, 'updated_at' => 17, ),
        BasePeer::TYPE_NUM => array (0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, )
    );

    /**
     * Translates a fieldname to another type
     *
     * @param      string $name field name
     * @param      string $fromType One of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME
     *                         BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM
     * @param      string $toType   One of the class type constants
     * @return string          translated name of the field.
     * @throws PropelException - if the specified name could not be found in the fieldname mappings.
     */
    public static function translateFieldName($name, $fromType, $toType)
    {
        $toNames = OrderPeer::getFieldNames($toType);
        $key = isset(OrderPeer::$fieldKeys[$fromType][$name]) ? OrderPeer::$fieldKeys[$fromType][$name] : null;
        if ($key === null) {
            throw new PropelException("'$name' could not be found in the field names of type '$fromType'. These are: " . print_r(OrderPeer::$fieldKeys[$fromType], true));
        }

        return $toNames[$key];
    }

    /**
     * Returns an array of field names.
     *
     * @param      string $type The type of fieldnames to return:
     *                      One of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME
     *                      BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM
     * @return array           A list of field names
     * @throws PropelException - if the type is not valid.
     */
    public static function getFieldNames($type = BasePeer::TYPE_PHPNAME)
    {
        if (!array_key_exists($type, OrderPeer::$fieldNames)) {
            throw new PropelException('Method getFieldNames() expects the parameter $type to be one of the class constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME, BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM. ' . $type . ' was given.');
        }

        return OrderPeer::$fieldNames[$type];
    }

    /**
     * Convenience method which changes table.column to alias.column.
     *
     * Using this method you can maintain SQL abstraction while using column aliases.
     * <code>
     *		$c->addAlias("alias1", TablePeer::TABLE_NAME);
     *		$c->addJoin(TablePeer::alias("alias1", TablePeer::PRIMARY_KEY_COLUMN), TablePeer::PRIMARY_KEY_COLUMN);
     * </code>
     * @param      string $alias The alias for the current table.
     * @param      string $column The column name for current table. (i.e. OrderPeer::COLUMN_NAME).
     * @return string
     */
    public static function alias($alias, $column)
    {
        return str_replace(OrderPeer::TABLE_NAME.'.', $alias.'.', $column);
    }

    /**
     * Add all the columns needed to create a new object.
     *
     * Note: any columns that were marked with lazyLoad="true" in the
     * XML schema will not be added to the select list and only loaded
     * on demand.
     *
     * @param      Criteria $criteria object containing the columns to add.
     * @param      string   $alias    optional table alias
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function addSelectColumns(Criteria $criteria, $alias = null)
    {
        if (null === $alias) {
            $criteria->addSelectColumn(OrderPeer::ID);
            $criteria->addSelectColumn(OrderPeer::REF);
            $criteria->addSelectColumn(OrderPeer::CUSTOMER_ID);
            $criteria->addSelectColumn(OrderPeer::ADDRESS_INVOICE);
            $criteria->addSelectColumn(OrderPeer::ADDRESS_DELIVERY);
            $criteria->addSelectColumn(OrderPeer::INVOICE_DATE);
            $criteria->addSelectColumn(OrderPeer::CURRENCY_ID);
            $criteria->addSelectColumn(OrderPeer::CURRENCY_RATE);
            $criteria->addSelectColumn(OrderPeer::TRANSACTION);
            $criteria->addSelectColumn(OrderPeer::DELIVERY_NUM);
            $criteria->addSelectColumn(OrderPeer::INVOICE);
            $criteria->addSelectColumn(OrderPeer::POSTAGE);
            $criteria->addSelectColumn(OrderPeer::PAYMENT);
            $criteria->addSelectColumn(OrderPeer::CARRIER);
            $criteria->addSelectColumn(OrderPeer::STATUS_ID);
            $criteria->addSelectColumn(OrderPeer::LANG);
            $criteria->addSelectColumn(OrderPeer::CREATED_AT);
            $criteria->addSelectColumn(OrderPeer::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.id');
            $criteria->addSelectColumn($alias . '.ref');
            $criteria->addSelectColumn($alias . '.customer_id');
            $criteria->addSelectColumn($alias . '.address_invoice');
            $criteria->addSelectColumn($alias . '.address_delivery');
            $criteria->addSelectColumn($alias . '.invoice_date');
            $criteria->addSelectColumn($alias . '.currency_id');
            $criteria->addSelectColumn($alias . '.currency_rate');
            $criteria->addSelectColumn($alias . '.transaction');
            $criteria->addSelectColumn($alias . '.delivery_num');
            $criteria->addSelectColumn($alias . '.invoice');
            $criteria->addSelectColumn($alias . '.postage');
            $criteria->addSelectColumn($alias . '.payment');
            $criteria->addSelectColumn($alias . '.carrier');
            $criteria->addSelectColumn($alias . '.status_id');
            $criteria->addSelectColumn($alias . '.lang');
            $criteria->addSelectColumn($alias . '.created_at');
            $criteria->addSelectColumn($alias . '.updated_at');
        }
    }

    /**
     * Returns the number of rows matching criteria.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO $con
     * @return int Number of matching rows.
     */
    public static function doCount(Criteria $criteria, $distinct = false, PropelPDO $con = null)
    {
        // we may modify criteria, so copy it first
        $criteria = clone $criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        $criteria->setPrimaryTableName(OrderPeer::TABLE_NAME);

        if ($distinct && !in_array(Criteria::DISTINCT, $criteria->getSelectModifiers())) {
            $criteria->setDistinct();
        }

        if (!$criteria->hasSelectClause()) {
            OrderPeer::addSelectColumns($criteria);
        }

        $criteria->clearOrderByColumns(); // ORDER BY won't ever affect the count
        $criteria->setDbName(OrderPeer::DATABASE_NAME); // Set the correct dbName

        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }
        // BasePeer returns a PDOStatement
        $stmt = BasePeer::doCount($criteria, $con);

        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $count = (int) $row[0];
        } else {
            $count = 0; // no rows returned; we infer that means 0 matches.
        }
        $stmt->closeCursor();

        return $count;
    }
    /**
     * Selects one object from the DB.
     *
     * @param      Criteria $criteria object used to create the SELECT statement.
     * @param      PropelPDO $con
     * @return                 Order
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelectOne(Criteria $criteria, PropelPDO $con = null)
    {
        $critcopy = clone $criteria;
        $critcopy->setLimit(1);
        $objects = OrderPeer::doSelect($critcopy, $con);
        if ($objects) {
            return $objects[0];
        }

        return null;
    }
    /**
     * Selects several row from the DB.
     *
     * @param      Criteria $criteria The Criteria object used to build the SELECT statement.
     * @param      PropelPDO $con
     * @return array           Array of selected Objects
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelect(Criteria $criteria, PropelPDO $con = null)
    {
        return OrderPeer::populateObjects(OrderPeer::doSelectStmt($criteria, $con));
    }
    /**
     * Prepares the Criteria object and uses the parent doSelect() method to execute a PDOStatement.
     *
     * Use this method directly if you want to work with an executed statement directly (for example
     * to perform your own object hydration).
     *
     * @param      Criteria $criteria The Criteria object used to build the SELECT statement.
     * @param      PropelPDO $con The connection to use
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     * @return PDOStatement The executed PDOStatement object.
     * @see        BasePeer::doSelect()
     */
    public static function doSelectStmt(Criteria $criteria, PropelPDO $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        if (!$criteria->hasSelectClause()) {
            $criteria = clone $criteria;
            OrderPeer::addSelectColumns($criteria);
        }

        // Set the correct dbName
        $criteria->setDbName(OrderPeer::DATABASE_NAME);

        // BasePeer returns a PDOStatement
        return BasePeer::doSelect($criteria, $con);
    }
    /**
     * Adds an object to the instance pool.
     *
     * Propel keeps cached copies of objects in an instance pool when they are retrieved
     * from the database.  In some cases -- especially when you override doSelect*()
     * methods in your stub classes -- you may need to explicitly add objects
     * to the cache in order to ensure that the same objects are always returned by doSelect*()
     * and retrieveByPK*() calls.
     *
     * @param      Order $obj A Order object.
     * @param      string $key (optional) key to use for instance map (for performance boost if key was already calculated externally).
     */
    public static function addInstanceToPool($obj, $key = null)
    {
        if (Propel::isInstancePoolingEnabled()) {
            if ($key === null) {
                $key = (string) $obj->getId();
            } // if key === null
            OrderPeer::$instances[$key] = $obj;
        }
    }

    /**
     * Removes an object from the instance pool.
     *
     * Propel keeps cached copies of objects in an instance pool when they are retrieved
     * from the database.  In some cases -- especially when you override doDelete
     * methods in your stub classes -- you may need to explicitly remove objects
     * from the cache in order to prevent returning objects that no longer exist.
     *
     * @param      mixed $value A Order object or a primary key value.
     *
     * @return void
     * @throws PropelException - if the value is invalid.
     */
    public static function removeInstanceFromPool($value)
    {
        if (Propel::isInstancePoolingEnabled() && $value !== null) {
            if (is_object($value) && $value instanceof Order) {
                $key = (string) $value->getId();
            } elseif (is_scalar($value)) {
                // assume we've been passed a primary key
                $key = (string) $value;
            } else {
                $e = new PropelException("Invalid value passed to removeInstanceFromPool().  Expected primary key or Order object; got " . (is_object($value) ? get_class($value) . ' object.' : var_export($value,true)));
                throw $e;
            }

            unset(OrderPeer::$instances[$key]);
        }
    } // removeInstanceFromPool()

    /**
     * Retrieves a string version of the primary key from the DB resultset row that can be used to uniquely identify a row in this table.
     *
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, a serialize()d version of the primary key will be returned.
     *
     * @param      string $key The key (@see getPrimaryKeyHash()) for this instance.
     * @return   Order Found object or null if 1) no instance exists for specified key or 2) instance pooling has been disabled.
     * @see        getPrimaryKeyHash()
     */
    public static function getInstanceFromPool($key)
    {
        if (Propel::isInstancePoolingEnabled()) {
            if (isset(OrderPeer::$instances[$key])) {
                return OrderPeer::$instances[$key];
            }
        }

        return null; // just to be explicit
    }

    /**
     * Clear the instance pool.
     *
     * @return void
     */
    public static function clearInstancePool($and_clear_all_references = false)
    {
      if ($and_clear_all_references)
      {
        foreach (OrderPeer::$instances as $instance)
        {
          $instance->clearAllReferences(true);
        }
      }
        OrderPeer::$instances = array();
    }

    /**
     * Method to invalidate the instance pool of all tables related to order
     * by a foreign key with ON DELETE CASCADE
     */
    public static function clearRelatedInstancePool()
    {
        // Invalidate objects in OrderProductPeer instance pool,
        // since one or more of them may be deleted by ON DELETE CASCADE/SETNULL rule.
        OrderProductPeer::clearInstancePool();
        // Invalidate objects in CouponOrderPeer instance pool,
        // since one or more of them may be deleted by ON DELETE CASCADE/SETNULL rule.
        CouponOrderPeer::clearInstancePool();
    }

    /**
     * Retrieves a string version of the primary key from the DB resultset row that can be used to uniquely identify a row in this table.
     *
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, a serialize()d version of the primary key will be returned.
     *
     * @param      array $row PropelPDO resultset row.
     * @param      int $startcol The 0-based offset for reading from the resultset row.
     * @return string A string version of PK or null if the components of primary key in result array are all null.
     */
    public static function getPrimaryKeyHashFromRow($row, $startcol = 0)
    {
        // If the PK cannot be derived from the row, return null.
        if ($row[$startcol] === null) {
            return null;
        }

        return (string) $row[$startcol];
    }

    /**
     * Retrieves the primary key from the DB resultset row
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, an array of the primary key columns will be returned.
     *
     * @param      array $row PropelPDO resultset row.
     * @param      int $startcol The 0-based offset for reading from the resultset row.
     * @return mixed The primary key of the row
     */
    public static function getPrimaryKeyFromRow($row, $startcol = 0)
    {

        return (int) $row[$startcol];
    }

    /**
     * The returned array will contain objects of the default type or
     * objects that inherit from the default.
     *
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function populateObjects(PDOStatement $stmt)
    {
        $results = array();

        // set the class once to avoid overhead in the loop
        $cls = OrderPeer::getOMClass();
        // populate the object(s)
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $key = OrderPeer::getPrimaryKeyHashFromRow($row, 0);
            if (null !== ($obj = OrderPeer::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                OrderPeer::addInstanceToPool($obj, $key);
            } // if key exists
        }
        $stmt->closeCursor();

        return $results;
    }
    /**
     * Populates an object of the default type or an object that inherit from the default.
     *
     * @param      array $row PropelPDO resultset row.
     * @param      int $startcol The 0-based offset for reading from the resultset row.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     * @return array (Order object, last column rank)
     */
    public static function populateObject($row, $startcol = 0)
    {
        $key = OrderPeer::getPrimaryKeyHashFromRow($row, $startcol);
        if (null !== ($obj = OrderPeer::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $startcol, true); // rehydrate
            $col = $startcol + OrderPeer::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = OrderPeer::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $startcol);
            OrderPeer::addInstanceToPool($obj, $key);
        }

        return array($obj, $col);
    }


    /**
     * Returns the number of rows matching criteria, joining the related Currency table
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return int Number of matching rows.
     */
    public static function doCountJoinCurrency(Criteria $criteria, $distinct = false, PropelPDO $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        // we're going to modify criteria, so copy it first
        $criteria = clone $criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        $criteria->setPrimaryTableName(OrderPeer::TABLE_NAME);

        if ($distinct && !in_array(Criteria::DISTINCT, $criteria->getSelectModifiers())) {
            $criteria->setDistinct();
        }

        if (!$criteria->hasSelectClause()) {
            OrderPeer::addSelectColumns($criteria);
        }

        $criteria->clearOrderByColumns(); // ORDER BY won't ever affect the count

        // Set the correct dbName
        $criteria->setDbName(OrderPeer::DATABASE_NAME);

        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        $criteria->addJoin(OrderPeer::CURRENCY_ID, CurrencyPeer::ID, $join_behavior);

        $stmt = BasePeer::doCount($criteria, $con);

        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $count = (int) $row[0];
        } else {
            $count = 0; // no rows returned; we infer that means 0 matches.
        }
        $stmt->closeCursor();

        return $count;
    }


    /**
     * Returns the number of rows matching criteria, joining the related Customer table
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return int Number of matching rows.
     */
    public static function doCountJoinCustomer(Criteria $criteria, $distinct = false, PropelPDO $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        // we're going to modify criteria, so copy it first
        $criteria = clone $criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        $criteria->setPrimaryTableName(OrderPeer::TABLE_NAME);

        if ($distinct && !in_array(Criteria::DISTINCT, $criteria->getSelectModifiers())) {
            $criteria->setDistinct();
        }

        if (!$criteria->hasSelectClause()) {
            OrderPeer::addSelectColumns($criteria);
        }

        $criteria->clearOrderByColumns(); // ORDER BY won't ever affect the count

        // Set the correct dbName
        $criteria->setDbName(OrderPeer::DATABASE_NAME);

        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        $criteria->addJoin(OrderPeer::CUSTOMER_ID, CustomerPeer::ID, $join_behavior);

        $stmt = BasePeer::doCount($criteria, $con);

        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $count = (int) $row[0];
        } else {
            $count = 0; // no rows returned; we infer that means 0 matches.
        }
        $stmt->closeCursor();

        return $count;
    }


    /**
     * Returns the number of rows matching criteria, joining the related OrderAddressRelatedByAddressInvoice table
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return int Number of matching rows.
     */
    public static function doCountJoinOrderAddressRelatedByAddressInvoice(Criteria $criteria, $distinct = false, PropelPDO $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        // we're going to modify criteria, so copy it first
        $criteria = clone $criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        $criteria->setPrimaryTableName(OrderPeer::TABLE_NAME);

        if ($distinct && !in_array(Criteria::DISTINCT, $criteria->getSelectModifiers())) {
            $criteria->setDistinct();
        }

        if (!$criteria->hasSelectClause()) {
            OrderPeer::addSelectColumns($criteria);
        }

        $criteria->clearOrderByColumns(); // ORDER BY won't ever affect the count

        // Set the correct dbName
        $criteria->setDbName(OrderPeer::DATABASE_NAME);

        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        $criteria->addJoin(OrderPeer::ADDRESS_INVOICE, OrderAddressPeer::ID, $join_behavior);

        $stmt = BasePeer::doCount($criteria, $con);

        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $count = (int) $row[0];
        } else {
            $count = 0; // no rows returned; we infer that means 0 matches.
        }
        $stmt->closeCursor();

        return $count;
    }


    /**
     * Returns the number of rows matching criteria, joining the related OrderAddressRelatedByAddressDelivery table
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return int Number of matching rows.
     */
    public static function doCountJoinOrderAddressRelatedByAddressDelivery(Criteria $criteria, $distinct = false, PropelPDO $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        // we're going to modify criteria, so copy it first
        $criteria = clone $criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        $criteria->setPrimaryTableName(OrderPeer::TABLE_NAME);

        if ($distinct && !in_array(Criteria::DISTINCT, $criteria->getSelectModifiers())) {
            $criteria->setDistinct();
        }

        if (!$criteria->hasSelectClause()) {
            OrderPeer::addSelectColumns($criteria);
        }

        $criteria->clearOrderByColumns(); // ORDER BY won't ever affect the count

        // Set the correct dbName
        $criteria->setDbName(OrderPeer::DATABASE_NAME);

        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        $criteria->addJoin(OrderPeer::ADDRESS_DELIVERY, OrderAddressPeer::ID, $join_behavior);

        $stmt = BasePeer::doCount($criteria, $con);

        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $count = (int) $row[0];
        } else {
            $count = 0; // no rows returned; we infer that means 0 matches.
        }
        $stmt->closeCursor();

        return $count;
    }


    /**
     * Returns the number of rows matching criteria, joining the related OrderStatus table
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return int Number of matching rows.
     */
    public static function doCountJoinOrderStatus(Criteria $criteria, $distinct = false, PropelPDO $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        // we're going to modify criteria, so copy it first
        $criteria = clone $criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        $criteria->setPrimaryTableName(OrderPeer::TABLE_NAME);

        if ($distinct && !in_array(Criteria::DISTINCT, $criteria->getSelectModifiers())) {
            $criteria->setDistinct();
        }

        if (!$criteria->hasSelectClause()) {
            OrderPeer::addSelectColumns($criteria);
        }

        $criteria->clearOrderByColumns(); // ORDER BY won't ever affect the count

        // Set the correct dbName
        $criteria->setDbName(OrderPeer::DATABASE_NAME);

        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        $criteria->addJoin(OrderPeer::STATUS_ID, OrderStatusPeer::ID, $join_behavior);

        $stmt = BasePeer::doCount($criteria, $con);

        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $count = (int) $row[0];
        } else {
            $count = 0; // no rows returned; we infer that means 0 matches.
        }
        $stmt->closeCursor();

        return $count;
    }


    /**
     * Selects a collection of Order objects pre-filled with their Currency objects.
     * @param      Criteria  $criteria
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return array           Array of Order objects.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelectJoinCurrency(Criteria $criteria, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $criteria = clone $criteria;

        // Set the correct dbName if it has not been overridden
        if ($criteria->getDbName() == Propel::getDefaultDB()) {
            $criteria->setDbName(OrderPeer::DATABASE_NAME);
        }

        OrderPeer::addSelectColumns($criteria);
        $startcol = OrderPeer::NUM_HYDRATE_COLUMNS;
        CurrencyPeer::addSelectColumns($criteria);

        $criteria->addJoin(OrderPeer::CURRENCY_ID, CurrencyPeer::ID, $join_behavior);

        $stmt = BasePeer::doSelect($criteria, $con);
        $results = array();

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $key1 = OrderPeer::getPrimaryKeyHashFromRow($row, 0);
            if (null !== ($obj1 = OrderPeer::getInstanceFromPool($key1))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj1->hydrate($row, 0, true); // rehydrate
            } else {

                $cls = OrderPeer::getOMClass();

                $obj1 = new $cls();
                $obj1->hydrate($row);
                OrderPeer::addInstanceToPool($obj1, $key1);
            } // if $obj1 already loaded

            $key2 = CurrencyPeer::getPrimaryKeyHashFromRow($row, $startcol);
            if ($key2 !== null) {
                $obj2 = CurrencyPeer::getInstanceFromPool($key2);
                if (!$obj2) {

                    $cls = CurrencyPeer::getOMClass();

                    $obj2 = new $cls();
                    $obj2->hydrate($row, $startcol);
                    CurrencyPeer::addInstanceToPool($obj2, $key2);
                } // if obj2 already loaded

                // Add the $obj1 (Order) to $obj2 (Currency)
                $obj2->addOrder($obj1);

            } // if joined row was not null

            $results[] = $obj1;
        }
        $stmt->closeCursor();

        return $results;
    }


    /**
     * Selects a collection of Order objects pre-filled with their Customer objects.
     * @param      Criteria  $criteria
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return array           Array of Order objects.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelectJoinCustomer(Criteria $criteria, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $criteria = clone $criteria;

        // Set the correct dbName if it has not been overridden
        if ($criteria->getDbName() == Propel::getDefaultDB()) {
            $criteria->setDbName(OrderPeer::DATABASE_NAME);
        }

        OrderPeer::addSelectColumns($criteria);
        $startcol = OrderPeer::NUM_HYDRATE_COLUMNS;
        CustomerPeer::addSelectColumns($criteria);

        $criteria->addJoin(OrderPeer::CUSTOMER_ID, CustomerPeer::ID, $join_behavior);

        $stmt = BasePeer::doSelect($criteria, $con);
        $results = array();

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $key1 = OrderPeer::getPrimaryKeyHashFromRow($row, 0);
            if (null !== ($obj1 = OrderPeer::getInstanceFromPool($key1))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj1->hydrate($row, 0, true); // rehydrate
            } else {

                $cls = OrderPeer::getOMClass();

                $obj1 = new $cls();
                $obj1->hydrate($row);
                OrderPeer::addInstanceToPool($obj1, $key1);
            } // if $obj1 already loaded

            $key2 = CustomerPeer::getPrimaryKeyHashFromRow($row, $startcol);
            if ($key2 !== null) {
                $obj2 = CustomerPeer::getInstanceFromPool($key2);
                if (!$obj2) {

                    $cls = CustomerPeer::getOMClass();

                    $obj2 = new $cls();
                    $obj2->hydrate($row, $startcol);
                    CustomerPeer::addInstanceToPool($obj2, $key2);
                } // if obj2 already loaded

                // Add the $obj1 (Order) to $obj2 (Customer)
                $obj2->addOrder($obj1);

            } // if joined row was not null

            $results[] = $obj1;
        }
        $stmt->closeCursor();

        return $results;
    }


    /**
     * Selects a collection of Order objects pre-filled with their OrderAddress objects.
     * @param      Criteria  $criteria
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return array           Array of Order objects.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelectJoinOrderAddressRelatedByAddressInvoice(Criteria $criteria, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $criteria = clone $criteria;

        // Set the correct dbName if it has not been overridden
        if ($criteria->getDbName() == Propel::getDefaultDB()) {
            $criteria->setDbName(OrderPeer::DATABASE_NAME);
        }

        OrderPeer::addSelectColumns($criteria);
        $startcol = OrderPeer::NUM_HYDRATE_COLUMNS;
        OrderAddressPeer::addSelectColumns($criteria);

        $criteria->addJoin(OrderPeer::ADDRESS_INVOICE, OrderAddressPeer::ID, $join_behavior);

        $stmt = BasePeer::doSelect($criteria, $con);
        $results = array();

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $key1 = OrderPeer::getPrimaryKeyHashFromRow($row, 0);
            if (null !== ($obj1 = OrderPeer::getInstanceFromPool($key1))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj1->hydrate($row, 0, true); // rehydrate
            } else {

                $cls = OrderPeer::getOMClass();

                $obj1 = new $cls();
                $obj1->hydrate($row);
                OrderPeer::addInstanceToPool($obj1, $key1);
            } // if $obj1 already loaded

            $key2 = OrderAddressPeer::getPrimaryKeyHashFromRow($row, $startcol);
            if ($key2 !== null) {
                $obj2 = OrderAddressPeer::getInstanceFromPool($key2);
                if (!$obj2) {

                    $cls = OrderAddressPeer::getOMClass();

                    $obj2 = new $cls();
                    $obj2->hydrate($row, $startcol);
                    OrderAddressPeer::addInstanceToPool($obj2, $key2);
                } // if obj2 already loaded

                // Add the $obj1 (Order) to $obj2 (OrderAddress)
                $obj2->addOrderRelatedByAddressInvoice($obj1);

            } // if joined row was not null

            $results[] = $obj1;
        }
        $stmt->closeCursor();

        return $results;
    }


    /**
     * Selects a collection of Order objects pre-filled with their OrderAddress objects.
     * @param      Criteria  $criteria
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return array           Array of Order objects.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelectJoinOrderAddressRelatedByAddressDelivery(Criteria $criteria, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $criteria = clone $criteria;

        // Set the correct dbName if it has not been overridden
        if ($criteria->getDbName() == Propel::getDefaultDB()) {
            $criteria->setDbName(OrderPeer::DATABASE_NAME);
        }

        OrderPeer::addSelectColumns($criteria);
        $startcol = OrderPeer::NUM_HYDRATE_COLUMNS;
        OrderAddressPeer::addSelectColumns($criteria);

        $criteria->addJoin(OrderPeer::ADDRESS_DELIVERY, OrderAddressPeer::ID, $join_behavior);

        $stmt = BasePeer::doSelect($criteria, $con);
        $results = array();

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $key1 = OrderPeer::getPrimaryKeyHashFromRow($row, 0);
            if (null !== ($obj1 = OrderPeer::getInstanceFromPool($key1))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj1->hydrate($row, 0, true); // rehydrate
            } else {

                $cls = OrderPeer::getOMClass();

                $obj1 = new $cls();
                $obj1->hydrate($row);
                OrderPeer::addInstanceToPool($obj1, $key1);
            } // if $obj1 already loaded

            $key2 = OrderAddressPeer::getPrimaryKeyHashFromRow($row, $startcol);
            if ($key2 !== null) {
                $obj2 = OrderAddressPeer::getInstanceFromPool($key2);
                if (!$obj2) {

                    $cls = OrderAddressPeer::getOMClass();

                    $obj2 = new $cls();
                    $obj2->hydrate($row, $startcol);
                    OrderAddressPeer::addInstanceToPool($obj2, $key2);
                } // if obj2 already loaded

                // Add the $obj1 (Order) to $obj2 (OrderAddress)
                $obj2->addOrderRelatedByAddressDelivery($obj1);

            } // if joined row was not null

            $results[] = $obj1;
        }
        $stmt->closeCursor();

        return $results;
    }


    /**
     * Selects a collection of Order objects pre-filled with their OrderStatus objects.
     * @param      Criteria  $criteria
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return array           Array of Order objects.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelectJoinOrderStatus(Criteria $criteria, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $criteria = clone $criteria;

        // Set the correct dbName if it has not been overridden
        if ($criteria->getDbName() == Propel::getDefaultDB()) {
            $criteria->setDbName(OrderPeer::DATABASE_NAME);
        }

        OrderPeer::addSelectColumns($criteria);
        $startcol = OrderPeer::NUM_HYDRATE_COLUMNS;
        OrderStatusPeer::addSelectColumns($criteria);

        $criteria->addJoin(OrderPeer::STATUS_ID, OrderStatusPeer::ID, $join_behavior);

        $stmt = BasePeer::doSelect($criteria, $con);
        $results = array();

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $key1 = OrderPeer::getPrimaryKeyHashFromRow($row, 0);
            if (null !== ($obj1 = OrderPeer::getInstanceFromPool($key1))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj1->hydrate($row, 0, true); // rehydrate
            } else {

                $cls = OrderPeer::getOMClass();

                $obj1 = new $cls();
                $obj1->hydrate($row);
                OrderPeer::addInstanceToPool($obj1, $key1);
            } // if $obj1 already loaded

            $key2 = OrderStatusPeer::getPrimaryKeyHashFromRow($row, $startcol);
            if ($key2 !== null) {
                $obj2 = OrderStatusPeer::getInstanceFromPool($key2);
                if (!$obj2) {

                    $cls = OrderStatusPeer::getOMClass();

                    $obj2 = new $cls();
                    $obj2->hydrate($row, $startcol);
                    OrderStatusPeer::addInstanceToPool($obj2, $key2);
                } // if obj2 already loaded

                // Add the $obj1 (Order) to $obj2 (OrderStatus)
                $obj2->addOrder($obj1);

            } // if joined row was not null

            $results[] = $obj1;
        }
        $stmt->closeCursor();

        return $results;
    }


    /**
     * Returns the number of rows matching criteria, joining all related tables
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return int Number of matching rows.
     */
    public static function doCountJoinAll(Criteria $criteria, $distinct = false, PropelPDO $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        // we're going to modify criteria, so copy it first
        $criteria = clone $criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        $criteria->setPrimaryTableName(OrderPeer::TABLE_NAME);

        if ($distinct && !in_array(Criteria::DISTINCT, $criteria->getSelectModifiers())) {
            $criteria->setDistinct();
        }

        if (!$criteria->hasSelectClause()) {
            OrderPeer::addSelectColumns($criteria);
        }

        $criteria->clearOrderByColumns(); // ORDER BY won't ever affect the count

        // Set the correct dbName
        $criteria->setDbName(OrderPeer::DATABASE_NAME);

        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        $criteria->addJoin(OrderPeer::CURRENCY_ID, CurrencyPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::CUSTOMER_ID, CustomerPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_INVOICE, OrderAddressPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_DELIVERY, OrderAddressPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::STATUS_ID, OrderStatusPeer::ID, $join_behavior);

        $stmt = BasePeer::doCount($criteria, $con);

        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $count = (int) $row[0];
        } else {
            $count = 0; // no rows returned; we infer that means 0 matches.
        }
        $stmt->closeCursor();

        return $count;
    }

    /**
     * Selects a collection of Order objects pre-filled with all related objects.
     *
     * @param      Criteria  $criteria
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return array           Array of Order objects.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelectJoinAll(Criteria $criteria, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $criteria = clone $criteria;

        // Set the correct dbName if it has not been overridden
        if ($criteria->getDbName() == Propel::getDefaultDB()) {
            $criteria->setDbName(OrderPeer::DATABASE_NAME);
        }

        OrderPeer::addSelectColumns($criteria);
        $startcol2 = OrderPeer::NUM_HYDRATE_COLUMNS;

        CurrencyPeer::addSelectColumns($criteria);
        $startcol3 = $startcol2 + CurrencyPeer::NUM_HYDRATE_COLUMNS;

        CustomerPeer::addSelectColumns($criteria);
        $startcol4 = $startcol3 + CustomerPeer::NUM_HYDRATE_COLUMNS;

        OrderAddressPeer::addSelectColumns($criteria);
        $startcol5 = $startcol4 + OrderAddressPeer::NUM_HYDRATE_COLUMNS;

        OrderAddressPeer::addSelectColumns($criteria);
        $startcol6 = $startcol5 + OrderAddressPeer::NUM_HYDRATE_COLUMNS;

        OrderStatusPeer::addSelectColumns($criteria);
        $startcol7 = $startcol6 + OrderStatusPeer::NUM_HYDRATE_COLUMNS;

        $criteria->addJoin(OrderPeer::CURRENCY_ID, CurrencyPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::CUSTOMER_ID, CustomerPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_INVOICE, OrderAddressPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_DELIVERY, OrderAddressPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::STATUS_ID, OrderStatusPeer::ID, $join_behavior);

        $stmt = BasePeer::doSelect($criteria, $con);
        $results = array();

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $key1 = OrderPeer::getPrimaryKeyHashFromRow($row, 0);
            if (null !== ($obj1 = OrderPeer::getInstanceFromPool($key1))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj1->hydrate($row, 0, true); // rehydrate
            } else {
                $cls = OrderPeer::getOMClass();

                $obj1 = new $cls();
                $obj1->hydrate($row);
                OrderPeer::addInstanceToPool($obj1, $key1);
            } // if obj1 already loaded

            // Add objects for joined Currency rows

            $key2 = CurrencyPeer::getPrimaryKeyHashFromRow($row, $startcol2);
            if ($key2 !== null) {
                $obj2 = CurrencyPeer::getInstanceFromPool($key2);
                if (!$obj2) {

                    $cls = CurrencyPeer::getOMClass();

                    $obj2 = new $cls();
                    $obj2->hydrate($row, $startcol2);
                    CurrencyPeer::addInstanceToPool($obj2, $key2);
                } // if obj2 loaded

                // Add the $obj1 (Order) to the collection in $obj2 (Currency)
                $obj2->addOrder($obj1);
            } // if joined row not null

            // Add objects for joined Customer rows

            $key3 = CustomerPeer::getPrimaryKeyHashFromRow($row, $startcol3);
            if ($key3 !== null) {
                $obj3 = CustomerPeer::getInstanceFromPool($key3);
                if (!$obj3) {

                    $cls = CustomerPeer::getOMClass();

                    $obj3 = new $cls();
                    $obj3->hydrate($row, $startcol3);
                    CustomerPeer::addInstanceToPool($obj3, $key3);
                } // if obj3 loaded

                // Add the $obj1 (Order) to the collection in $obj3 (Customer)
                $obj3->addOrder($obj1);
            } // if joined row not null

            // Add objects for joined OrderAddress rows

            $key4 = OrderAddressPeer::getPrimaryKeyHashFromRow($row, $startcol4);
            if ($key4 !== null) {
                $obj4 = OrderAddressPeer::getInstanceFromPool($key4);
                if (!$obj4) {

                    $cls = OrderAddressPeer::getOMClass();

                    $obj4 = new $cls();
                    $obj4->hydrate($row, $startcol4);
                    OrderAddressPeer::addInstanceToPool($obj4, $key4);
                } // if obj4 loaded

                // Add the $obj1 (Order) to the collection in $obj4 (OrderAddress)
                $obj4->addOrderRelatedByAddressInvoice($obj1);
            } // if joined row not null

            // Add objects for joined OrderAddress rows

            $key5 = OrderAddressPeer::getPrimaryKeyHashFromRow($row, $startcol5);
            if ($key5 !== null) {
                $obj5 = OrderAddressPeer::getInstanceFromPool($key5);
                if (!$obj5) {

                    $cls = OrderAddressPeer::getOMClass();

                    $obj5 = new $cls();
                    $obj5->hydrate($row, $startcol5);
                    OrderAddressPeer::addInstanceToPool($obj5, $key5);
                } // if obj5 loaded

                // Add the $obj1 (Order) to the collection in $obj5 (OrderAddress)
                $obj5->addOrderRelatedByAddressDelivery($obj1);
            } // if joined row not null

            // Add objects for joined OrderStatus rows

            $key6 = OrderStatusPeer::getPrimaryKeyHashFromRow($row, $startcol6);
            if ($key6 !== null) {
                $obj6 = OrderStatusPeer::getInstanceFromPool($key6);
                if (!$obj6) {

                    $cls = OrderStatusPeer::getOMClass();

                    $obj6 = new $cls();
                    $obj6->hydrate($row, $startcol6);
                    OrderStatusPeer::addInstanceToPool($obj6, $key6);
                } // if obj6 loaded

                // Add the $obj1 (Order) to the collection in $obj6 (OrderStatus)
                $obj6->addOrder($obj1);
            } // if joined row not null

            $results[] = $obj1;
        }
        $stmt->closeCursor();

        return $results;
    }


    /**
     * Returns the number of rows matching criteria, joining the related Currency table
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return int Number of matching rows.
     */
    public static function doCountJoinAllExceptCurrency(Criteria $criteria, $distinct = false, PropelPDO $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        // we're going to modify criteria, so copy it first
        $criteria = clone $criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        $criteria->setPrimaryTableName(OrderPeer::TABLE_NAME);

        if ($distinct && !in_array(Criteria::DISTINCT, $criteria->getSelectModifiers())) {
            $criteria->setDistinct();
        }

        if (!$criteria->hasSelectClause()) {
            OrderPeer::addSelectColumns($criteria);
        }

        $criteria->clearOrderByColumns(); // ORDER BY should not affect count

        // Set the correct dbName
        $criteria->setDbName(OrderPeer::DATABASE_NAME);

        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        $criteria->addJoin(OrderPeer::CUSTOMER_ID, CustomerPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_INVOICE, OrderAddressPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_DELIVERY, OrderAddressPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::STATUS_ID, OrderStatusPeer::ID, $join_behavior);

        $stmt = BasePeer::doCount($criteria, $con);

        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $count = (int) $row[0];
        } else {
            $count = 0; // no rows returned; we infer that means 0 matches.
        }
        $stmt->closeCursor();

        return $count;
    }


    /**
     * Returns the number of rows matching criteria, joining the related Customer table
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return int Number of matching rows.
     */
    public static function doCountJoinAllExceptCustomer(Criteria $criteria, $distinct = false, PropelPDO $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        // we're going to modify criteria, so copy it first
        $criteria = clone $criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        $criteria->setPrimaryTableName(OrderPeer::TABLE_NAME);

        if ($distinct && !in_array(Criteria::DISTINCT, $criteria->getSelectModifiers())) {
            $criteria->setDistinct();
        }

        if (!$criteria->hasSelectClause()) {
            OrderPeer::addSelectColumns($criteria);
        }

        $criteria->clearOrderByColumns(); // ORDER BY should not affect count

        // Set the correct dbName
        $criteria->setDbName(OrderPeer::DATABASE_NAME);

        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        $criteria->addJoin(OrderPeer::CURRENCY_ID, CurrencyPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_INVOICE, OrderAddressPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_DELIVERY, OrderAddressPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::STATUS_ID, OrderStatusPeer::ID, $join_behavior);

        $stmt = BasePeer::doCount($criteria, $con);

        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $count = (int) $row[0];
        } else {
            $count = 0; // no rows returned; we infer that means 0 matches.
        }
        $stmt->closeCursor();

        return $count;
    }


    /**
     * Returns the number of rows matching criteria, joining the related OrderAddressRelatedByAddressInvoice table
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return int Number of matching rows.
     */
    public static function doCountJoinAllExceptOrderAddressRelatedByAddressInvoice(Criteria $criteria, $distinct = false, PropelPDO $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        // we're going to modify criteria, so copy it first
        $criteria = clone $criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        $criteria->setPrimaryTableName(OrderPeer::TABLE_NAME);

        if ($distinct && !in_array(Criteria::DISTINCT, $criteria->getSelectModifiers())) {
            $criteria->setDistinct();
        }

        if (!$criteria->hasSelectClause()) {
            OrderPeer::addSelectColumns($criteria);
        }

        $criteria->clearOrderByColumns(); // ORDER BY should not affect count

        // Set the correct dbName
        $criteria->setDbName(OrderPeer::DATABASE_NAME);

        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        $criteria->addJoin(OrderPeer::CURRENCY_ID, CurrencyPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::CUSTOMER_ID, CustomerPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::STATUS_ID, OrderStatusPeer::ID, $join_behavior);

        $stmt = BasePeer::doCount($criteria, $con);

        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $count = (int) $row[0];
        } else {
            $count = 0; // no rows returned; we infer that means 0 matches.
        }
        $stmt->closeCursor();

        return $count;
    }


    /**
     * Returns the number of rows matching criteria, joining the related OrderAddressRelatedByAddressDelivery table
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return int Number of matching rows.
     */
    public static function doCountJoinAllExceptOrderAddressRelatedByAddressDelivery(Criteria $criteria, $distinct = false, PropelPDO $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        // we're going to modify criteria, so copy it first
        $criteria = clone $criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        $criteria->setPrimaryTableName(OrderPeer::TABLE_NAME);

        if ($distinct && !in_array(Criteria::DISTINCT, $criteria->getSelectModifiers())) {
            $criteria->setDistinct();
        }

        if (!$criteria->hasSelectClause()) {
            OrderPeer::addSelectColumns($criteria);
        }

        $criteria->clearOrderByColumns(); // ORDER BY should not affect count

        // Set the correct dbName
        $criteria->setDbName(OrderPeer::DATABASE_NAME);

        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        $criteria->addJoin(OrderPeer::CURRENCY_ID, CurrencyPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::CUSTOMER_ID, CustomerPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::STATUS_ID, OrderStatusPeer::ID, $join_behavior);

        $stmt = BasePeer::doCount($criteria, $con);

        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $count = (int) $row[0];
        } else {
            $count = 0; // no rows returned; we infer that means 0 matches.
        }
        $stmt->closeCursor();

        return $count;
    }


    /**
     * Returns the number of rows matching criteria, joining the related OrderStatus table
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return int Number of matching rows.
     */
    public static function doCountJoinAllExceptOrderStatus(Criteria $criteria, $distinct = false, PropelPDO $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        // we're going to modify criteria, so copy it first
        $criteria = clone $criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        $criteria->setPrimaryTableName(OrderPeer::TABLE_NAME);

        if ($distinct && !in_array(Criteria::DISTINCT, $criteria->getSelectModifiers())) {
            $criteria->setDistinct();
        }

        if (!$criteria->hasSelectClause()) {
            OrderPeer::addSelectColumns($criteria);
        }

        $criteria->clearOrderByColumns(); // ORDER BY should not affect count

        // Set the correct dbName
        $criteria->setDbName(OrderPeer::DATABASE_NAME);

        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        $criteria->addJoin(OrderPeer::CURRENCY_ID, CurrencyPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::CUSTOMER_ID, CustomerPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_INVOICE, OrderAddressPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_DELIVERY, OrderAddressPeer::ID, $join_behavior);

        $stmt = BasePeer::doCount($criteria, $con);

        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $count = (int) $row[0];
        } else {
            $count = 0; // no rows returned; we infer that means 0 matches.
        }
        $stmt->closeCursor();

        return $count;
    }


    /**
     * Selects a collection of Order objects pre-filled with all related objects except Currency.
     *
     * @param      Criteria  $criteria
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return array           Array of Order objects.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelectJoinAllExceptCurrency(Criteria $criteria, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $criteria = clone $criteria;

        // Set the correct dbName if it has not been overridden
        // $criteria->getDbName() will return the same object if not set to another value
        // so == check is okay and faster
        if ($criteria->getDbName() == Propel::getDefaultDB()) {
            $criteria->setDbName(OrderPeer::DATABASE_NAME);
        }

        OrderPeer::addSelectColumns($criteria);
        $startcol2 = OrderPeer::NUM_HYDRATE_COLUMNS;

        CustomerPeer::addSelectColumns($criteria);
        $startcol3 = $startcol2 + CustomerPeer::NUM_HYDRATE_COLUMNS;

        OrderAddressPeer::addSelectColumns($criteria);
        $startcol4 = $startcol3 + OrderAddressPeer::NUM_HYDRATE_COLUMNS;

        OrderAddressPeer::addSelectColumns($criteria);
        $startcol5 = $startcol4 + OrderAddressPeer::NUM_HYDRATE_COLUMNS;

        OrderStatusPeer::addSelectColumns($criteria);
        $startcol6 = $startcol5 + OrderStatusPeer::NUM_HYDRATE_COLUMNS;

        $criteria->addJoin(OrderPeer::CUSTOMER_ID, CustomerPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_INVOICE, OrderAddressPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_DELIVERY, OrderAddressPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::STATUS_ID, OrderStatusPeer::ID, $join_behavior);


        $stmt = BasePeer::doSelect($criteria, $con);
        $results = array();

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $key1 = OrderPeer::getPrimaryKeyHashFromRow($row, 0);
            if (null !== ($obj1 = OrderPeer::getInstanceFromPool($key1))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj1->hydrate($row, 0, true); // rehydrate
            } else {
                $cls = OrderPeer::getOMClass();

                $obj1 = new $cls();
                $obj1->hydrate($row);
                OrderPeer::addInstanceToPool($obj1, $key1);
            } // if obj1 already loaded

                // Add objects for joined Customer rows

                $key2 = CustomerPeer::getPrimaryKeyHashFromRow($row, $startcol2);
                if ($key2 !== null) {
                    $obj2 = CustomerPeer::getInstanceFromPool($key2);
                    if (!$obj2) {

                        $cls = CustomerPeer::getOMClass();

                    $obj2 = new $cls();
                    $obj2->hydrate($row, $startcol2);
                    CustomerPeer::addInstanceToPool($obj2, $key2);
                } // if $obj2 already loaded

                // Add the $obj1 (Order) to the collection in $obj2 (Customer)
                $obj2->addOrder($obj1);

            } // if joined row is not null

                // Add objects for joined OrderAddress rows

                $key3 = OrderAddressPeer::getPrimaryKeyHashFromRow($row, $startcol3);
                if ($key3 !== null) {
                    $obj3 = OrderAddressPeer::getInstanceFromPool($key3);
                    if (!$obj3) {

                        $cls = OrderAddressPeer::getOMClass();

                    $obj3 = new $cls();
                    $obj3->hydrate($row, $startcol3);
                    OrderAddressPeer::addInstanceToPool($obj3, $key3);
                } // if $obj3 already loaded

                // Add the $obj1 (Order) to the collection in $obj3 (OrderAddress)
                $obj3->addOrderRelatedByAddressInvoice($obj1);

            } // if joined row is not null

                // Add objects for joined OrderAddress rows

                $key4 = OrderAddressPeer::getPrimaryKeyHashFromRow($row, $startcol4);
                if ($key4 !== null) {
                    $obj4 = OrderAddressPeer::getInstanceFromPool($key4);
                    if (!$obj4) {

                        $cls = OrderAddressPeer::getOMClass();

                    $obj4 = new $cls();
                    $obj4->hydrate($row, $startcol4);
                    OrderAddressPeer::addInstanceToPool($obj4, $key4);
                } // if $obj4 already loaded

                // Add the $obj1 (Order) to the collection in $obj4 (OrderAddress)
                $obj4->addOrderRelatedByAddressDelivery($obj1);

            } // if joined row is not null

                // Add objects for joined OrderStatus rows

                $key5 = OrderStatusPeer::getPrimaryKeyHashFromRow($row, $startcol5);
                if ($key5 !== null) {
                    $obj5 = OrderStatusPeer::getInstanceFromPool($key5);
                    if (!$obj5) {

                        $cls = OrderStatusPeer::getOMClass();

                    $obj5 = new $cls();
                    $obj5->hydrate($row, $startcol5);
                    OrderStatusPeer::addInstanceToPool($obj5, $key5);
                } // if $obj5 already loaded

                // Add the $obj1 (Order) to the collection in $obj5 (OrderStatus)
                $obj5->addOrder($obj1);

            } // if joined row is not null

            $results[] = $obj1;
        }
        $stmt->closeCursor();

        return $results;
    }


    /**
     * Selects a collection of Order objects pre-filled with all related objects except Customer.
     *
     * @param      Criteria  $criteria
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return array           Array of Order objects.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelectJoinAllExceptCustomer(Criteria $criteria, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $criteria = clone $criteria;

        // Set the correct dbName if it has not been overridden
        // $criteria->getDbName() will return the same object if not set to another value
        // so == check is okay and faster
        if ($criteria->getDbName() == Propel::getDefaultDB()) {
            $criteria->setDbName(OrderPeer::DATABASE_NAME);
        }

        OrderPeer::addSelectColumns($criteria);
        $startcol2 = OrderPeer::NUM_HYDRATE_COLUMNS;

        CurrencyPeer::addSelectColumns($criteria);
        $startcol3 = $startcol2 + CurrencyPeer::NUM_HYDRATE_COLUMNS;

        OrderAddressPeer::addSelectColumns($criteria);
        $startcol4 = $startcol3 + OrderAddressPeer::NUM_HYDRATE_COLUMNS;

        OrderAddressPeer::addSelectColumns($criteria);
        $startcol5 = $startcol4 + OrderAddressPeer::NUM_HYDRATE_COLUMNS;

        OrderStatusPeer::addSelectColumns($criteria);
        $startcol6 = $startcol5 + OrderStatusPeer::NUM_HYDRATE_COLUMNS;

        $criteria->addJoin(OrderPeer::CURRENCY_ID, CurrencyPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_INVOICE, OrderAddressPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_DELIVERY, OrderAddressPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::STATUS_ID, OrderStatusPeer::ID, $join_behavior);


        $stmt = BasePeer::doSelect($criteria, $con);
        $results = array();

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $key1 = OrderPeer::getPrimaryKeyHashFromRow($row, 0);
            if (null !== ($obj1 = OrderPeer::getInstanceFromPool($key1))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj1->hydrate($row, 0, true); // rehydrate
            } else {
                $cls = OrderPeer::getOMClass();

                $obj1 = new $cls();
                $obj1->hydrate($row);
                OrderPeer::addInstanceToPool($obj1, $key1);
            } // if obj1 already loaded

                // Add objects for joined Currency rows

                $key2 = CurrencyPeer::getPrimaryKeyHashFromRow($row, $startcol2);
                if ($key2 !== null) {
                    $obj2 = CurrencyPeer::getInstanceFromPool($key2);
                    if (!$obj2) {

                        $cls = CurrencyPeer::getOMClass();

                    $obj2 = new $cls();
                    $obj2->hydrate($row, $startcol2);
                    CurrencyPeer::addInstanceToPool($obj2, $key2);
                } // if $obj2 already loaded

                // Add the $obj1 (Order) to the collection in $obj2 (Currency)
                $obj2->addOrder($obj1);

            } // if joined row is not null

                // Add objects for joined OrderAddress rows

                $key3 = OrderAddressPeer::getPrimaryKeyHashFromRow($row, $startcol3);
                if ($key3 !== null) {
                    $obj3 = OrderAddressPeer::getInstanceFromPool($key3);
                    if (!$obj3) {

                        $cls = OrderAddressPeer::getOMClass();

                    $obj3 = new $cls();
                    $obj3->hydrate($row, $startcol3);
                    OrderAddressPeer::addInstanceToPool($obj3, $key3);
                } // if $obj3 already loaded

                // Add the $obj1 (Order) to the collection in $obj3 (OrderAddress)
                $obj3->addOrderRelatedByAddressInvoice($obj1);

            } // if joined row is not null

                // Add objects for joined OrderAddress rows

                $key4 = OrderAddressPeer::getPrimaryKeyHashFromRow($row, $startcol4);
                if ($key4 !== null) {
                    $obj4 = OrderAddressPeer::getInstanceFromPool($key4);
                    if (!$obj4) {

                        $cls = OrderAddressPeer::getOMClass();

                    $obj4 = new $cls();
                    $obj4->hydrate($row, $startcol4);
                    OrderAddressPeer::addInstanceToPool($obj4, $key4);
                } // if $obj4 already loaded

                // Add the $obj1 (Order) to the collection in $obj4 (OrderAddress)
                $obj4->addOrderRelatedByAddressDelivery($obj1);

            } // if joined row is not null

                // Add objects for joined OrderStatus rows

                $key5 = OrderStatusPeer::getPrimaryKeyHashFromRow($row, $startcol5);
                if ($key5 !== null) {
                    $obj5 = OrderStatusPeer::getInstanceFromPool($key5);
                    if (!$obj5) {

                        $cls = OrderStatusPeer::getOMClass();

                    $obj5 = new $cls();
                    $obj5->hydrate($row, $startcol5);
                    OrderStatusPeer::addInstanceToPool($obj5, $key5);
                } // if $obj5 already loaded

                // Add the $obj1 (Order) to the collection in $obj5 (OrderStatus)
                $obj5->addOrder($obj1);

            } // if joined row is not null

            $results[] = $obj1;
        }
        $stmt->closeCursor();

        return $results;
    }


    /**
     * Selects a collection of Order objects pre-filled with all related objects except OrderAddressRelatedByAddressInvoice.
     *
     * @param      Criteria  $criteria
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return array           Array of Order objects.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelectJoinAllExceptOrderAddressRelatedByAddressInvoice(Criteria $criteria, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $criteria = clone $criteria;

        // Set the correct dbName if it has not been overridden
        // $criteria->getDbName() will return the same object if not set to another value
        // so == check is okay and faster
        if ($criteria->getDbName() == Propel::getDefaultDB()) {
            $criteria->setDbName(OrderPeer::DATABASE_NAME);
        }

        OrderPeer::addSelectColumns($criteria);
        $startcol2 = OrderPeer::NUM_HYDRATE_COLUMNS;

        CurrencyPeer::addSelectColumns($criteria);
        $startcol3 = $startcol2 + CurrencyPeer::NUM_HYDRATE_COLUMNS;

        CustomerPeer::addSelectColumns($criteria);
        $startcol4 = $startcol3 + CustomerPeer::NUM_HYDRATE_COLUMNS;

        OrderStatusPeer::addSelectColumns($criteria);
        $startcol5 = $startcol4 + OrderStatusPeer::NUM_HYDRATE_COLUMNS;

        $criteria->addJoin(OrderPeer::CURRENCY_ID, CurrencyPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::CUSTOMER_ID, CustomerPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::STATUS_ID, OrderStatusPeer::ID, $join_behavior);


        $stmt = BasePeer::doSelect($criteria, $con);
        $results = array();

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $key1 = OrderPeer::getPrimaryKeyHashFromRow($row, 0);
            if (null !== ($obj1 = OrderPeer::getInstanceFromPool($key1))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj1->hydrate($row, 0, true); // rehydrate
            } else {
                $cls = OrderPeer::getOMClass();

                $obj1 = new $cls();
                $obj1->hydrate($row);
                OrderPeer::addInstanceToPool($obj1, $key1);
            } // if obj1 already loaded

                // Add objects for joined Currency rows

                $key2 = CurrencyPeer::getPrimaryKeyHashFromRow($row, $startcol2);
                if ($key2 !== null) {
                    $obj2 = CurrencyPeer::getInstanceFromPool($key2);
                    if (!$obj2) {

                        $cls = CurrencyPeer::getOMClass();

                    $obj2 = new $cls();
                    $obj2->hydrate($row, $startcol2);
                    CurrencyPeer::addInstanceToPool($obj2, $key2);
                } // if $obj2 already loaded

                // Add the $obj1 (Order) to the collection in $obj2 (Currency)
                $obj2->addOrder($obj1);

            } // if joined row is not null

                // Add objects for joined Customer rows

                $key3 = CustomerPeer::getPrimaryKeyHashFromRow($row, $startcol3);
                if ($key3 !== null) {
                    $obj3 = CustomerPeer::getInstanceFromPool($key3);
                    if (!$obj3) {

                        $cls = CustomerPeer::getOMClass();

                    $obj3 = new $cls();
                    $obj3->hydrate($row, $startcol3);
                    CustomerPeer::addInstanceToPool($obj3, $key3);
                } // if $obj3 already loaded

                // Add the $obj1 (Order) to the collection in $obj3 (Customer)
                $obj3->addOrder($obj1);

            } // if joined row is not null

                // Add objects for joined OrderStatus rows

                $key4 = OrderStatusPeer::getPrimaryKeyHashFromRow($row, $startcol4);
                if ($key4 !== null) {
                    $obj4 = OrderStatusPeer::getInstanceFromPool($key4);
                    if (!$obj4) {

                        $cls = OrderStatusPeer::getOMClass();

                    $obj4 = new $cls();
                    $obj4->hydrate($row, $startcol4);
                    OrderStatusPeer::addInstanceToPool($obj4, $key4);
                } // if $obj4 already loaded

                // Add the $obj1 (Order) to the collection in $obj4 (OrderStatus)
                $obj4->addOrder($obj1);

            } // if joined row is not null

            $results[] = $obj1;
        }
        $stmt->closeCursor();

        return $results;
    }


    /**
     * Selects a collection of Order objects pre-filled with all related objects except OrderAddressRelatedByAddressDelivery.
     *
     * @param      Criteria  $criteria
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return array           Array of Order objects.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelectJoinAllExceptOrderAddressRelatedByAddressDelivery(Criteria $criteria, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $criteria = clone $criteria;

        // Set the correct dbName if it has not been overridden
        // $criteria->getDbName() will return the same object if not set to another value
        // so == check is okay and faster
        if ($criteria->getDbName() == Propel::getDefaultDB()) {
            $criteria->setDbName(OrderPeer::DATABASE_NAME);
        }

        OrderPeer::addSelectColumns($criteria);
        $startcol2 = OrderPeer::NUM_HYDRATE_COLUMNS;

        CurrencyPeer::addSelectColumns($criteria);
        $startcol3 = $startcol2 + CurrencyPeer::NUM_HYDRATE_COLUMNS;

        CustomerPeer::addSelectColumns($criteria);
        $startcol4 = $startcol3 + CustomerPeer::NUM_HYDRATE_COLUMNS;

        OrderStatusPeer::addSelectColumns($criteria);
        $startcol5 = $startcol4 + OrderStatusPeer::NUM_HYDRATE_COLUMNS;

        $criteria->addJoin(OrderPeer::CURRENCY_ID, CurrencyPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::CUSTOMER_ID, CustomerPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::STATUS_ID, OrderStatusPeer::ID, $join_behavior);


        $stmt = BasePeer::doSelect($criteria, $con);
        $results = array();

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $key1 = OrderPeer::getPrimaryKeyHashFromRow($row, 0);
            if (null !== ($obj1 = OrderPeer::getInstanceFromPool($key1))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj1->hydrate($row, 0, true); // rehydrate
            } else {
                $cls = OrderPeer::getOMClass();

                $obj1 = new $cls();
                $obj1->hydrate($row);
                OrderPeer::addInstanceToPool($obj1, $key1);
            } // if obj1 already loaded

                // Add objects for joined Currency rows

                $key2 = CurrencyPeer::getPrimaryKeyHashFromRow($row, $startcol2);
                if ($key2 !== null) {
                    $obj2 = CurrencyPeer::getInstanceFromPool($key2);
                    if (!$obj2) {

                        $cls = CurrencyPeer::getOMClass();

                    $obj2 = new $cls();
                    $obj2->hydrate($row, $startcol2);
                    CurrencyPeer::addInstanceToPool($obj2, $key2);
                } // if $obj2 already loaded

                // Add the $obj1 (Order) to the collection in $obj2 (Currency)
                $obj2->addOrder($obj1);

            } // if joined row is not null

                // Add objects for joined Customer rows

                $key3 = CustomerPeer::getPrimaryKeyHashFromRow($row, $startcol3);
                if ($key3 !== null) {
                    $obj3 = CustomerPeer::getInstanceFromPool($key3);
                    if (!$obj3) {

                        $cls = CustomerPeer::getOMClass();

                    $obj3 = new $cls();
                    $obj3->hydrate($row, $startcol3);
                    CustomerPeer::addInstanceToPool($obj3, $key3);
                } // if $obj3 already loaded

                // Add the $obj1 (Order) to the collection in $obj3 (Customer)
                $obj3->addOrder($obj1);

            } // if joined row is not null

                // Add objects for joined OrderStatus rows

                $key4 = OrderStatusPeer::getPrimaryKeyHashFromRow($row, $startcol4);
                if ($key4 !== null) {
                    $obj4 = OrderStatusPeer::getInstanceFromPool($key4);
                    if (!$obj4) {

                        $cls = OrderStatusPeer::getOMClass();

                    $obj4 = new $cls();
                    $obj4->hydrate($row, $startcol4);
                    OrderStatusPeer::addInstanceToPool($obj4, $key4);
                } // if $obj4 already loaded

                // Add the $obj1 (Order) to the collection in $obj4 (OrderStatus)
                $obj4->addOrder($obj1);

            } // if joined row is not null

            $results[] = $obj1;
        }
        $stmt->closeCursor();

        return $results;
    }


    /**
     * Selects a collection of Order objects pre-filled with all related objects except OrderStatus.
     *
     * @param      Criteria  $criteria
     * @param      PropelPDO $con
     * @param      String    $join_behavior the type of joins to use, defaults to Criteria::LEFT_JOIN
     * @return array           Array of Order objects.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelectJoinAllExceptOrderStatus(Criteria $criteria, $con = null, $join_behavior = Criteria::LEFT_JOIN)
    {
        $criteria = clone $criteria;

        // Set the correct dbName if it has not been overridden
        // $criteria->getDbName() will return the same object if not set to another value
        // so == check is okay and faster
        if ($criteria->getDbName() == Propel::getDefaultDB()) {
            $criteria->setDbName(OrderPeer::DATABASE_NAME);
        }

        OrderPeer::addSelectColumns($criteria);
        $startcol2 = OrderPeer::NUM_HYDRATE_COLUMNS;

        CurrencyPeer::addSelectColumns($criteria);
        $startcol3 = $startcol2 + CurrencyPeer::NUM_HYDRATE_COLUMNS;

        CustomerPeer::addSelectColumns($criteria);
        $startcol4 = $startcol3 + CustomerPeer::NUM_HYDRATE_COLUMNS;

        OrderAddressPeer::addSelectColumns($criteria);
        $startcol5 = $startcol4 + OrderAddressPeer::NUM_HYDRATE_COLUMNS;

        OrderAddressPeer::addSelectColumns($criteria);
        $startcol6 = $startcol5 + OrderAddressPeer::NUM_HYDRATE_COLUMNS;

        $criteria->addJoin(OrderPeer::CURRENCY_ID, CurrencyPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::CUSTOMER_ID, CustomerPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_INVOICE, OrderAddressPeer::ID, $join_behavior);

        $criteria->addJoin(OrderPeer::ADDRESS_DELIVERY, OrderAddressPeer::ID, $join_behavior);


        $stmt = BasePeer::doSelect($criteria, $con);
        $results = array();

        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $key1 = OrderPeer::getPrimaryKeyHashFromRow($row, 0);
            if (null !== ($obj1 = OrderPeer::getInstanceFromPool($key1))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj1->hydrate($row, 0, true); // rehydrate
            } else {
                $cls = OrderPeer::getOMClass();

                $obj1 = new $cls();
                $obj1->hydrate($row);
                OrderPeer::addInstanceToPool($obj1, $key1);
            } // if obj1 already loaded

                // Add objects for joined Currency rows

                $key2 = CurrencyPeer::getPrimaryKeyHashFromRow($row, $startcol2);
                if ($key2 !== null) {
                    $obj2 = CurrencyPeer::getInstanceFromPool($key2);
                    if (!$obj2) {

                        $cls = CurrencyPeer::getOMClass();

                    $obj2 = new $cls();
                    $obj2->hydrate($row, $startcol2);
                    CurrencyPeer::addInstanceToPool($obj2, $key2);
                } // if $obj2 already loaded

                // Add the $obj1 (Order) to the collection in $obj2 (Currency)
                $obj2->addOrder($obj1);

            } // if joined row is not null

                // Add objects for joined Customer rows

                $key3 = CustomerPeer::getPrimaryKeyHashFromRow($row, $startcol3);
                if ($key3 !== null) {
                    $obj3 = CustomerPeer::getInstanceFromPool($key3);
                    if (!$obj3) {

                        $cls = CustomerPeer::getOMClass();

                    $obj3 = new $cls();
                    $obj3->hydrate($row, $startcol3);
                    CustomerPeer::addInstanceToPool($obj3, $key3);
                } // if $obj3 already loaded

                // Add the $obj1 (Order) to the collection in $obj3 (Customer)
                $obj3->addOrder($obj1);

            } // if joined row is not null

                // Add objects for joined OrderAddress rows

                $key4 = OrderAddressPeer::getPrimaryKeyHashFromRow($row, $startcol4);
                if ($key4 !== null) {
                    $obj4 = OrderAddressPeer::getInstanceFromPool($key4);
                    if (!$obj4) {

                        $cls = OrderAddressPeer::getOMClass();

                    $obj4 = new $cls();
                    $obj4->hydrate($row, $startcol4);
                    OrderAddressPeer::addInstanceToPool($obj4, $key4);
                } // if $obj4 already loaded

                // Add the $obj1 (Order) to the collection in $obj4 (OrderAddress)
                $obj4->addOrderRelatedByAddressInvoice($obj1);

            } // if joined row is not null

                // Add objects for joined OrderAddress rows

                $key5 = OrderAddressPeer::getPrimaryKeyHashFromRow($row, $startcol5);
                if ($key5 !== null) {
                    $obj5 = OrderAddressPeer::getInstanceFromPool($key5);
                    if (!$obj5) {

                        $cls = OrderAddressPeer::getOMClass();

                    $obj5 = new $cls();
                    $obj5->hydrate($row, $startcol5);
                    OrderAddressPeer::addInstanceToPool($obj5, $key5);
                } // if $obj5 already loaded

                // Add the $obj1 (Order) to the collection in $obj5 (OrderAddress)
                $obj5->addOrderRelatedByAddressDelivery($obj1);

            } // if joined row is not null

            $results[] = $obj1;
        }
        $stmt->closeCursor();

        return $results;
    }

    /**
     * Returns the TableMap related to this peer.
     * This method is not needed for general use but a specific application could have a need.
     * @return TableMap
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function getTableMap()
    {
        return Propel::getDatabaseMap(OrderPeer::DATABASE_NAME)->getTable(OrderPeer::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this peer class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getDatabaseMap(BaseOrderPeer::DATABASE_NAME);
      if (!$dbMap->hasTable(BaseOrderPeer::TABLE_NAME)) {
        $dbMap->addTableObject(new OrderTableMap());
      }
    }

    /**
     * The class that the Peer will make instances of.
     *
     *
     * @return string ClassName
     */
    public static function getOMClass($row = 0, $colnum = 0)
    {
        return OrderPeer::OM_CLASS;
    }

    /**
     * Performs an INSERT on the database, given a Order or Criteria object.
     *
     * @param      mixed $values Criteria or Order object containing data that is used to create the INSERT statement.
     * @param      PropelPDO $con the PropelPDO connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doInsert($values, PropelPDO $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        if ($values instanceof Criteria) {
            $criteria = clone $values; // rename for clarity
        } else {
            $criteria = $values->buildCriteria(); // build Criteria from Order object
        }

        if ($criteria->containsKey(OrderPeer::ID) && $criteria->keyContainsValue(OrderPeer::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.OrderPeer::ID.')');
        }


        // Set the correct dbName
        $criteria->setDbName(OrderPeer::DATABASE_NAME);

        try {
            // use transaction because $criteria could contain info
            // for more than one table (I guess, conceivably)
            $con->beginTransaction();
            $pk = BasePeer::doInsert($criteria, $con);
            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $pk;
    }

    /**
     * Performs an UPDATE on the database, given a Order or Criteria object.
     *
     * @param      mixed $values Criteria or Order object containing data that is used to create the UPDATE statement.
     * @param      PropelPDO $con The connection to use (specify PropelPDO connection object to exert more control over transactions).
     * @return int             The number of affected rows (if supported by underlying database driver).
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doUpdate($values, PropelPDO $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $selectCriteria = new Criteria(OrderPeer::DATABASE_NAME);

        if ($values instanceof Criteria) {
            $criteria = clone $values; // rename for clarity

            $comparison = $criteria->getComparison(OrderPeer::ID);
            $value = $criteria->remove(OrderPeer::ID);
            if ($value) {
                $selectCriteria->add(OrderPeer::ID, $value, $comparison);
            } else {
                $selectCriteria->setPrimaryTableName(OrderPeer::TABLE_NAME);
            }

        } else { // $values is Order object
            $criteria = $values->buildCriteria(); // gets full criteria
            $selectCriteria = $values->buildPkeyCriteria(); // gets criteria w/ primary key(s)
        }

        // set the correct dbName
        $criteria->setDbName(OrderPeer::DATABASE_NAME);

        return BasePeer::doUpdate($selectCriteria, $criteria, $con);
    }

    /**
     * Deletes all rows from the order table.
     *
     * @param      PropelPDO $con the connection to use
     * @return int             The number of affected rows (if supported by underlying database driver).
     * @throws PropelException
     */
    public static function doDeleteAll(PropelPDO $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }
        $affectedRows = 0; // initialize var to track total num of affected rows
        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();
            $affectedRows += BasePeer::doDeleteAll(OrderPeer::TABLE_NAME, $con, OrderPeer::DATABASE_NAME);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            OrderPeer::clearInstancePool();
            OrderPeer::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Performs a DELETE on the database, given a Order or Criteria object OR a primary key value.
     *
     * @param      mixed $values Criteria or Order object or primary key or array of primary keys
     *              which is used to create the DELETE statement
     * @param      PropelPDO $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *				if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
     public static function doDelete($values, PropelPDO $con = null)
     {
        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        if ($values instanceof Criteria) {
            // invalidate the cache for all objects of this type, since we have no
            // way of knowing (without running a query) what objects should be invalidated
            // from the cache based on this Criteria.
            OrderPeer::clearInstancePool();
            // rename for clarity
            $criteria = clone $values;
        } elseif ($values instanceof Order) { // it's a model object
            // invalidate the cache for this single object
            OrderPeer::removeInstanceFromPool($values);
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(OrderPeer::DATABASE_NAME);
            $criteria->add(OrderPeer::ID, (array) $values, Criteria::IN);
            // invalidate the cache for this object(s)
            foreach ((array) $values as $singleval) {
                OrderPeer::removeInstanceFromPool($singleval);
            }
        }

        // Set the correct dbName
        $criteria->setDbName(OrderPeer::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();

            $affectedRows += BasePeer::doDelete($criteria, $con);
            OrderPeer::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Validates all modified columns of given Order object.
     * If parameter $columns is either a single column name or an array of column names
     * than only those columns are validated.
     *
     * NOTICE: This does not apply to primary or foreign keys for now.
     *
     * @param      Order $obj The object to validate.
     * @param      mixed $cols Column name or array of column names.
     *
     * @return mixed TRUE if all columns are valid or the error message of the first invalid column.
     */
    public static function doValidate($obj, $cols = null)
    {
        $columns = array();

        if ($cols) {
            $dbMap = Propel::getDatabaseMap(OrderPeer::DATABASE_NAME);
            $tableMap = $dbMap->getTable(OrderPeer::TABLE_NAME);

            if (! is_array($cols)) {
                $cols = array($cols);
            }

            foreach ($cols as $colName) {
                if ($tableMap->hasColumn($colName)) {
                    $get = 'get' . $tableMap->getColumn($colName)->getPhpName();
                    $columns[$colName] = $obj->$get();
                }
            }
        } else {

        }

        return BasePeer::doValidate(OrderPeer::DATABASE_NAME, OrderPeer::TABLE_NAME, $columns);
    }

    /**
     * Retrieve a single object by pkey.
     *
     * @param      int $pk the primary key.
     * @param      PropelPDO $con the connection to use
     * @return Order
     */
    public static function retrieveByPK($pk, PropelPDO $con = null)
    {

        if (null !== ($obj = OrderPeer::getInstanceFromPool((string) $pk))) {
            return $obj;
        }

        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        $criteria = new Criteria(OrderPeer::DATABASE_NAME);
        $criteria->add(OrderPeer::ID, $pk);

        $v = OrderPeer::doSelect($criteria, $con);

        return !empty($v) > 0 ? $v[0] : null;
    }

    /**
     * Retrieve multiple objects by pkey.
     *
     * @param      array $pks List of primary keys
     * @param      PropelPDO $con the connection to use
     * @return Order[]
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function retrieveByPKs($pks, PropelPDO $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(OrderPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        $objs = null;
        if (empty($pks)) {
            $objs = array();
        } else {
            $criteria = new Criteria(OrderPeer::DATABASE_NAME);
            $criteria->add(OrderPeer::ID, $pks, Criteria::IN);
            $objs = OrderPeer::doSelect($criteria, $con);
        }

        return $objs;
    }

} // BaseOrderPeer

// This is the static code needed to register the TableMap for this table with the main Propel class.
//
BaseOrderPeer::buildTableMap();

