<?php

namespace Thelia\Model\Map;

use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\InstancePoolTrait;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\DataFetcher\DataFetcherInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\RelationMap;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Map\TableMapTrait;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;


/**
 * This class defines the structure of the 'order' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class OrderTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.OrderTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'order';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\Order';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.Order';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 25;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 25;

    /**
     * the column name for the ID field
     */
    const ID = 'order.ID';

    /**
     * the column name for the REF field
     */
    const REF = 'order.REF';

    /**
     * the column name for the CUSTOMER_ID field
     */
    const CUSTOMER_ID = 'order.CUSTOMER_ID';

    /**
     * the column name for the INVOICE_ORDER_ADDRESS_ID field
     */
    const INVOICE_ORDER_ADDRESS_ID = 'order.INVOICE_ORDER_ADDRESS_ID';

    /**
     * the column name for the DELIVERY_ORDER_ADDRESS_ID field
     */
    const DELIVERY_ORDER_ADDRESS_ID = 'order.DELIVERY_ORDER_ADDRESS_ID';

    /**
     * the column name for the INVOICE_DATE field
     */
    const INVOICE_DATE = 'order.INVOICE_DATE';

    /**
     * the column name for the CURRENCY_ID field
     */
    const CURRENCY_ID = 'order.CURRENCY_ID';

    /**
     * the column name for the CURRENCY_RATE field
     */
    const CURRENCY_RATE = 'order.CURRENCY_RATE';

    /**
     * the column name for the TRANSACTION_REF field
     */
    const TRANSACTION_REF = 'order.TRANSACTION_REF';

    /**
     * the column name for the DELIVERY_REF field
     */
    const DELIVERY_REF = 'order.DELIVERY_REF';

    /**
     * the column name for the INVOICE_REF field
     */
    const INVOICE_REF = 'order.INVOICE_REF';

    /**
     * the column name for the DISCOUNT field
     */
    const DISCOUNT = 'order.DISCOUNT';

    /**
     * the column name for the POSTAGE field
     */
    const POSTAGE = 'order.POSTAGE';

    /**
     * the column name for the POSTAGE_TAX field
     */
    const POSTAGE_TAX = 'order.POSTAGE_TAX';

    /**
     * the column name for the POSTAGE_TAX_RULE_TITLE field
     */
    const POSTAGE_TAX_RULE_TITLE = 'order.POSTAGE_TAX_RULE_TITLE';

    /**
     * the column name for the PAYMENT_MODULE_ID field
     */
    const PAYMENT_MODULE_ID = 'order.PAYMENT_MODULE_ID';

    /**
     * the column name for the DELIVERY_MODULE_ID field
     */
    const DELIVERY_MODULE_ID = 'order.DELIVERY_MODULE_ID';

    /**
     * the column name for the STATUS_ID field
     */
    const STATUS_ID = 'order.STATUS_ID';

    /**
     * the column name for the LANG_ID field
     */
    const LANG_ID = 'order.LANG_ID';

    /**
     * the column name for the CART_ID field
     */
    const CART_ID = 'order.CART_ID';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'order.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'order.UPDATED_AT';

    /**
     * the column name for the VERSION field
     */
    const VERSION = 'order.VERSION';

    /**
     * the column name for the VERSION_CREATED_AT field
     */
    const VERSION_CREATED_AT = 'order.VERSION_CREATED_AT';

    /**
     * the column name for the VERSION_CREATED_BY field
     */
    const VERSION_CREATED_BY = 'order.VERSION_CREATED_BY';

    /**
     * The default string format for model objects of the related table
     */
    const DEFAULT_STRING_FORMAT = 'YAML';

    /**
     * holds an array of fieldnames
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldNames[self::TYPE_PHPNAME][0] = 'Id'
     */
    protected static $fieldNames = array (
        self::TYPE_PHPNAME       => array('Id', 'Ref', 'CustomerId', 'InvoiceOrderAddressId', 'DeliveryOrderAddressId', 'InvoiceDate', 'CurrencyId', 'CurrencyRate', 'TransactionRef', 'DeliveryRef', 'InvoiceRef', 'Discount', 'Postage', 'PostageTax', 'PostageTaxRuleTitle', 'PaymentModuleId', 'DeliveryModuleId', 'StatusId', 'LangId', 'CartId', 'CreatedAt', 'UpdatedAt', 'Version', 'VersionCreatedAt', 'VersionCreatedBy', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'ref', 'customerId', 'invoiceOrderAddressId', 'deliveryOrderAddressId', 'invoiceDate', 'currencyId', 'currencyRate', 'transactionRef', 'deliveryRef', 'invoiceRef', 'discount', 'postage', 'postageTax', 'postageTaxRuleTitle', 'paymentModuleId', 'deliveryModuleId', 'statusId', 'langId', 'cartId', 'createdAt', 'updatedAt', 'version', 'versionCreatedAt', 'versionCreatedBy', ),
        self::TYPE_COLNAME       => array(OrderTableMap::ID, OrderTableMap::REF, OrderTableMap::CUSTOMER_ID, OrderTableMap::INVOICE_ORDER_ADDRESS_ID, OrderTableMap::DELIVERY_ORDER_ADDRESS_ID, OrderTableMap::INVOICE_DATE, OrderTableMap::CURRENCY_ID, OrderTableMap::CURRENCY_RATE, OrderTableMap::TRANSACTION_REF, OrderTableMap::DELIVERY_REF, OrderTableMap::INVOICE_REF, OrderTableMap::DISCOUNT, OrderTableMap::POSTAGE, OrderTableMap::POSTAGE_TAX, OrderTableMap::POSTAGE_TAX_RULE_TITLE, OrderTableMap::PAYMENT_MODULE_ID, OrderTableMap::DELIVERY_MODULE_ID, OrderTableMap::STATUS_ID, OrderTableMap::LANG_ID, OrderTableMap::CART_ID, OrderTableMap::CREATED_AT, OrderTableMap::UPDATED_AT, OrderTableMap::VERSION, OrderTableMap::VERSION_CREATED_AT, OrderTableMap::VERSION_CREATED_BY, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'REF', 'CUSTOMER_ID', 'INVOICE_ORDER_ADDRESS_ID', 'DELIVERY_ORDER_ADDRESS_ID', 'INVOICE_DATE', 'CURRENCY_ID', 'CURRENCY_RATE', 'TRANSACTION_REF', 'DELIVERY_REF', 'INVOICE_REF', 'DISCOUNT', 'POSTAGE', 'POSTAGE_TAX', 'POSTAGE_TAX_RULE_TITLE', 'PAYMENT_MODULE_ID', 'DELIVERY_MODULE_ID', 'STATUS_ID', 'LANG_ID', 'CART_ID', 'CREATED_AT', 'UPDATED_AT', 'VERSION', 'VERSION_CREATED_AT', 'VERSION_CREATED_BY', ),
        self::TYPE_FIELDNAME     => array('id', 'ref', 'customer_id', 'invoice_order_address_id', 'delivery_order_address_id', 'invoice_date', 'currency_id', 'currency_rate', 'transaction_ref', 'delivery_ref', 'invoice_ref', 'discount', 'postage', 'postage_tax', 'postage_tax_rule_title', 'payment_module_id', 'delivery_module_id', 'status_id', 'lang_id', 'cart_id', 'created_at', 'updated_at', 'version', 'version_created_at', 'version_created_by', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'Ref' => 1, 'CustomerId' => 2, 'InvoiceOrderAddressId' => 3, 'DeliveryOrderAddressId' => 4, 'InvoiceDate' => 5, 'CurrencyId' => 6, 'CurrencyRate' => 7, 'TransactionRef' => 8, 'DeliveryRef' => 9, 'InvoiceRef' => 10, 'Discount' => 11, 'Postage' => 12, 'PostageTax' => 13, 'PostageTaxRuleTitle' => 14, 'PaymentModuleId' => 15, 'DeliveryModuleId' => 16, 'StatusId' => 17, 'LangId' => 18, 'CartId' => 19, 'CreatedAt' => 20, 'UpdatedAt' => 21, 'Version' => 22, 'VersionCreatedAt' => 23, 'VersionCreatedBy' => 24, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'ref' => 1, 'customerId' => 2, 'invoiceOrderAddressId' => 3, 'deliveryOrderAddressId' => 4, 'invoiceDate' => 5, 'currencyId' => 6, 'currencyRate' => 7, 'transactionRef' => 8, 'deliveryRef' => 9, 'invoiceRef' => 10, 'discount' => 11, 'postage' => 12, 'postageTax' => 13, 'postageTaxRuleTitle' => 14, 'paymentModuleId' => 15, 'deliveryModuleId' => 16, 'statusId' => 17, 'langId' => 18, 'cartId' => 19, 'createdAt' => 20, 'updatedAt' => 21, 'version' => 22, 'versionCreatedAt' => 23, 'versionCreatedBy' => 24, ),
        self::TYPE_COLNAME       => array(OrderTableMap::ID => 0, OrderTableMap::REF => 1, OrderTableMap::CUSTOMER_ID => 2, OrderTableMap::INVOICE_ORDER_ADDRESS_ID => 3, OrderTableMap::DELIVERY_ORDER_ADDRESS_ID => 4, OrderTableMap::INVOICE_DATE => 5, OrderTableMap::CURRENCY_ID => 6, OrderTableMap::CURRENCY_RATE => 7, OrderTableMap::TRANSACTION_REF => 8, OrderTableMap::DELIVERY_REF => 9, OrderTableMap::INVOICE_REF => 10, OrderTableMap::DISCOUNT => 11, OrderTableMap::POSTAGE => 12, OrderTableMap::POSTAGE_TAX => 13, OrderTableMap::POSTAGE_TAX_RULE_TITLE => 14, OrderTableMap::PAYMENT_MODULE_ID => 15, OrderTableMap::DELIVERY_MODULE_ID => 16, OrderTableMap::STATUS_ID => 17, OrderTableMap::LANG_ID => 18, OrderTableMap::CART_ID => 19, OrderTableMap::CREATED_AT => 20, OrderTableMap::UPDATED_AT => 21, OrderTableMap::VERSION => 22, OrderTableMap::VERSION_CREATED_AT => 23, OrderTableMap::VERSION_CREATED_BY => 24, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'REF' => 1, 'CUSTOMER_ID' => 2, 'INVOICE_ORDER_ADDRESS_ID' => 3, 'DELIVERY_ORDER_ADDRESS_ID' => 4, 'INVOICE_DATE' => 5, 'CURRENCY_ID' => 6, 'CURRENCY_RATE' => 7, 'TRANSACTION_REF' => 8, 'DELIVERY_REF' => 9, 'INVOICE_REF' => 10, 'DISCOUNT' => 11, 'POSTAGE' => 12, 'POSTAGE_TAX' => 13, 'POSTAGE_TAX_RULE_TITLE' => 14, 'PAYMENT_MODULE_ID' => 15, 'DELIVERY_MODULE_ID' => 16, 'STATUS_ID' => 17, 'LANG_ID' => 18, 'CART_ID' => 19, 'CREATED_AT' => 20, 'UPDATED_AT' => 21, 'VERSION' => 22, 'VERSION_CREATED_AT' => 23, 'VERSION_CREATED_BY' => 24, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'ref' => 1, 'customer_id' => 2, 'invoice_order_address_id' => 3, 'delivery_order_address_id' => 4, 'invoice_date' => 5, 'currency_id' => 6, 'currency_rate' => 7, 'transaction_ref' => 8, 'delivery_ref' => 9, 'invoice_ref' => 10, 'discount' => 11, 'postage' => 12, 'postage_tax' => 13, 'postage_tax_rule_title' => 14, 'payment_module_id' => 15, 'delivery_module_id' => 16, 'status_id' => 17, 'lang_id' => 18, 'cart_id' => 19, 'created_at' => 20, 'updated_at' => 21, 'version' => 22, 'version_created_at' => 23, 'version_created_by' => 24, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, )
    );

    /**
     * Initialize the table attributes and columns
     * Relations are not initialized by this method since they are lazy loaded
     *
     * @return void
     * @throws PropelException
     */
    public function initialize()
    {
        // attributes
        $this->setName('order');
        $this->setPhpName('Order');
        $this->setClassName('\\Thelia\\Model\\Order');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('REF', 'Ref', 'VARCHAR', false, 45, null);
        $this->addForeignKey('CUSTOMER_ID', 'CustomerId', 'INTEGER', 'customer', 'ID', true, null, null);
        $this->addForeignKey('INVOICE_ORDER_ADDRESS_ID', 'InvoiceOrderAddressId', 'INTEGER', 'order_address', 'ID', true, null, null);
        $this->addForeignKey('DELIVERY_ORDER_ADDRESS_ID', 'DeliveryOrderAddressId', 'INTEGER', 'order_address', 'ID', true, null, null);
        $this->addColumn('INVOICE_DATE', 'InvoiceDate', 'TIMESTAMP', false, null, null);
        $this->addForeignKey('CURRENCY_ID', 'CurrencyId', 'INTEGER', 'currency', 'ID', true, null, null);
        $this->addColumn('CURRENCY_RATE', 'CurrencyRate', 'FLOAT', true, null, null);
        $this->addColumn('TRANSACTION_REF', 'TransactionRef', 'VARCHAR', false, 100, null);
        $this->addColumn('DELIVERY_REF', 'DeliveryRef', 'VARCHAR', false, 100, null);
        $this->addColumn('INVOICE_REF', 'InvoiceRef', 'VARCHAR', false, 100, null);
        $this->addColumn('DISCOUNT', 'Discount', 'DECIMAL', false, 16, 0);
        $this->addColumn('POSTAGE', 'Postage', 'DECIMAL', true, 16, 0);
        $this->addColumn('POSTAGE_TAX', 'PostageTax', 'DECIMAL', true, 16, 0);
        $this->addColumn('POSTAGE_TAX_RULE_TITLE', 'PostageTaxRuleTitle', 'VARCHAR', false, 255, null);
        $this->addForeignKey('PAYMENT_MODULE_ID', 'PaymentModuleId', 'INTEGER', 'module', 'ID', true, null, null);
        $this->addForeignKey('DELIVERY_MODULE_ID', 'DeliveryModuleId', 'INTEGER', 'module', 'ID', true, null, null);
        $this->addForeignKey('STATUS_ID', 'StatusId', 'INTEGER', 'order_status', 'ID', true, null, null);
        $this->addForeignKey('LANG_ID', 'LangId', 'INTEGER', 'lang', 'ID', true, null, null);
        $this->addColumn('CART_ID', 'CartId', 'INTEGER', true, null, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('VERSION', 'Version', 'INTEGER', false, null, 0);
        $this->addColumn('VERSION_CREATED_AT', 'VersionCreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('VERSION_CREATED_BY', 'VersionCreatedBy', 'VARCHAR', false, 100, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Currency', '\\Thelia\\Model\\Currency', RelationMap::MANY_TO_ONE, array('currency_id' => 'id', ), 'RESTRICT', 'RESTRICT');
        $this->addRelation('Customer', '\\Thelia\\Model\\Customer', RelationMap::MANY_TO_ONE, array('customer_id' => 'id', ), 'RESTRICT', 'RESTRICT');
        $this->addRelation('OrderAddressRelatedByInvoiceOrderAddressId', '\\Thelia\\Model\\OrderAddress', RelationMap::MANY_TO_ONE, array('invoice_order_address_id' => 'id', ), 'RESTRICT', 'RESTRICT');
        $this->addRelation('OrderAddressRelatedByDeliveryOrderAddressId', '\\Thelia\\Model\\OrderAddress', RelationMap::MANY_TO_ONE, array('delivery_order_address_id' => 'id', ), 'RESTRICT', 'RESTRICT');
        $this->addRelation('OrderStatus', '\\Thelia\\Model\\OrderStatus', RelationMap::MANY_TO_ONE, array('status_id' => 'id', ), 'RESTRICT', 'RESTRICT');
        $this->addRelation('ModuleRelatedByPaymentModuleId', '\\Thelia\\Model\\Module', RelationMap::MANY_TO_ONE, array('payment_module_id' => 'id', ), 'RESTRICT', 'RESTRICT');
        $this->addRelation('ModuleRelatedByDeliveryModuleId', '\\Thelia\\Model\\Module', RelationMap::MANY_TO_ONE, array('delivery_module_id' => 'id', ), 'RESTRICT', 'RESTRICT');
        $this->addRelation('Lang', '\\Thelia\\Model\\Lang', RelationMap::MANY_TO_ONE, array('lang_id' => 'id', ), 'RESTRICT', 'RESTRICT');
        $this->addRelation('OrderProduct', '\\Thelia\\Model\\OrderProduct', RelationMap::ONE_TO_MANY, array('id' => 'order_id', ), 'CASCADE', 'RESTRICT', 'OrderProducts');
        $this->addRelation('OrderCoupon', '\\Thelia\\Model\\OrderCoupon', RelationMap::ONE_TO_MANY, array('id' => 'order_id', ), 'CASCADE', 'RESTRICT', 'OrderCoupons');
        $this->addRelation('OrderVersion', '\\Thelia\\Model\\OrderVersion', RelationMap::ONE_TO_MANY, array('id' => 'id', ), 'CASCADE', null, 'OrderVersions');
    } // buildRelations()

    /**
     *
     * Gets the list of behaviors registered for this table
     *
     * @return array Associative array (name => parameters) of behaviors
     */
    public function getBehaviors()
    {
        return array(
            'timestampable' => array('create_column' => 'created_at', 'update_column' => 'updated_at', ),
            'versionable' => array('version_column' => 'version', 'version_table' => '', 'log_created_at' => 'true', 'log_created_by' => 'true', 'log_comment' => 'false', 'version_created_at_column' => 'version_created_at', 'version_created_by_column' => 'version_created_by', 'version_comment_column' => 'version_comment', ),
        );
    } // getBehaviors()
    /**
     * Method to invalidate the instance pool of all tables related to order     * by a foreign key with ON DELETE CASCADE
     */
    public static function clearRelatedInstancePool()
    {
        // Invalidate objects in ".$this->getClassNameFromBuilder($joinedTableTableMapBuilder)." instance pool,
        // since one or more of them may be deleted by ON DELETE CASCADE/SETNULL rule.
                OrderProductTableMap::clearInstancePool();
                OrderCouponTableMap::clearInstancePool();
                OrderVersionTableMap::clearInstancePool();
            }

    /**
     * Retrieves a string version of the primary key from the DB resultset row that can be used to uniquely identify a row in this table.
     *
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, a serialize()d version of the primary key will be returned.
     *
     * @param array  $row       resultset row.
     * @param int    $offset    The 0-based offset for reading from the resultset row.
     * @param string $indexType One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                           TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM
     */
    public static function getPrimaryKeyHashFromRow($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        // If the PK cannot be derived from the row, return NULL.
        if ($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)] === null) {
            return null;
        }

        return (string) $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)];
    }

    /**
     * Retrieves the primary key from the DB resultset row
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, an array of the primary key columns will be returned.
     *
     * @param array  $row       resultset row.
     * @param int    $offset    The 0-based offset for reading from the resultset row.
     * @param string $indexType One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                           TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM
     *
     * @return mixed The primary key of the row
     */
    public static function getPrimaryKeyFromRow($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {

            return (int) $row[
                            $indexType == TableMap::TYPE_NUM
                            ? 0 + $offset
                            : self::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)
                        ];
    }

    /**
     * The class that the tableMap will make instances of.
     *
     * If $withPrefix is true, the returned path
     * uses a dot-path notation which is translated into a path
     * relative to a location on the PHP include_path.
     * (e.g. path.to.MyClass -> 'path/to/MyClass.php')
     *
     * @param boolean $withPrefix Whether or not to return the path with the class name
     * @return string path.to.ClassName
     */
    public static function getOMClass($withPrefix = true)
    {
        return $withPrefix ? OrderTableMap::CLASS_DEFAULT : OrderTableMap::OM_CLASS;
    }

    /**
     * Populates an object of the default type or an object that inherit from the default.
     *
     * @param array  $row       row returned by DataFetcher->fetch().
     * @param int    $offset    The 0-based offset for reading from the resultset row.
     * @param string $indexType The index type of $row. Mostly DataFetcher->getIndexType().
                                 One of the class type constants TableMap::TYPE_PHPNAME, TableMap::TYPE_STUDLYPHPNAME
     *                           TableMap::TYPE_COLNAME, TableMap::TYPE_FIELDNAME, TableMap::TYPE_NUM.
     *
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     * @return array (Order object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = OrderTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = OrderTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + OrderTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = OrderTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            OrderTableMap::addInstanceToPool($obj, $key);
        }

        return array($obj, $col);
    }

    /**
     * The returned array will contain objects of the default type or
     * objects that inherit from the default.
     *
     * @param DataFetcherInterface $dataFetcher
     * @return array
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function populateObjects(DataFetcherInterface $dataFetcher)
    {
        $results = array();

        // set the class once to avoid overhead in the loop
        $cls = static::getOMClass(false);
        // populate the object(s)
        while ($row = $dataFetcher->fetch()) {
            $key = OrderTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = OrderTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                OrderTableMap::addInstanceToPool($obj, $key);
            } // if key exists
        }

        return $results;
    }
    /**
     * Add all the columns needed to create a new object.
     *
     * Note: any columns that were marked with lazyLoad="true" in the
     * XML schema will not be added to the select list and only loaded
     * on demand.
     *
     * @param Criteria $criteria object containing the columns to add.
     * @param string   $alias    optional table alias
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function addSelectColumns(Criteria $criteria, $alias = null)
    {
        if (null === $alias) {
            $criteria->addSelectColumn(OrderTableMap::ID);
            $criteria->addSelectColumn(OrderTableMap::REF);
            $criteria->addSelectColumn(OrderTableMap::CUSTOMER_ID);
            $criteria->addSelectColumn(OrderTableMap::INVOICE_ORDER_ADDRESS_ID);
            $criteria->addSelectColumn(OrderTableMap::DELIVERY_ORDER_ADDRESS_ID);
            $criteria->addSelectColumn(OrderTableMap::INVOICE_DATE);
            $criteria->addSelectColumn(OrderTableMap::CURRENCY_ID);
            $criteria->addSelectColumn(OrderTableMap::CURRENCY_RATE);
            $criteria->addSelectColumn(OrderTableMap::TRANSACTION_REF);
            $criteria->addSelectColumn(OrderTableMap::DELIVERY_REF);
            $criteria->addSelectColumn(OrderTableMap::INVOICE_REF);
            $criteria->addSelectColumn(OrderTableMap::DISCOUNT);
            $criteria->addSelectColumn(OrderTableMap::POSTAGE);
            $criteria->addSelectColumn(OrderTableMap::POSTAGE_TAX);
            $criteria->addSelectColumn(OrderTableMap::POSTAGE_TAX_RULE_TITLE);
            $criteria->addSelectColumn(OrderTableMap::PAYMENT_MODULE_ID);
            $criteria->addSelectColumn(OrderTableMap::DELIVERY_MODULE_ID);
            $criteria->addSelectColumn(OrderTableMap::STATUS_ID);
            $criteria->addSelectColumn(OrderTableMap::LANG_ID);
            $criteria->addSelectColumn(OrderTableMap::CART_ID);
            $criteria->addSelectColumn(OrderTableMap::CREATED_AT);
            $criteria->addSelectColumn(OrderTableMap::UPDATED_AT);
            $criteria->addSelectColumn(OrderTableMap::VERSION);
            $criteria->addSelectColumn(OrderTableMap::VERSION_CREATED_AT);
            $criteria->addSelectColumn(OrderTableMap::VERSION_CREATED_BY);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.REF');
            $criteria->addSelectColumn($alias . '.CUSTOMER_ID');
            $criteria->addSelectColumn($alias . '.INVOICE_ORDER_ADDRESS_ID');
            $criteria->addSelectColumn($alias . '.DELIVERY_ORDER_ADDRESS_ID');
            $criteria->addSelectColumn($alias . '.INVOICE_DATE');
            $criteria->addSelectColumn($alias . '.CURRENCY_ID');
            $criteria->addSelectColumn($alias . '.CURRENCY_RATE');
            $criteria->addSelectColumn($alias . '.TRANSACTION_REF');
            $criteria->addSelectColumn($alias . '.DELIVERY_REF');
            $criteria->addSelectColumn($alias . '.INVOICE_REF');
            $criteria->addSelectColumn($alias . '.DISCOUNT');
            $criteria->addSelectColumn($alias . '.POSTAGE');
            $criteria->addSelectColumn($alias . '.POSTAGE_TAX');
            $criteria->addSelectColumn($alias . '.POSTAGE_TAX_RULE_TITLE');
            $criteria->addSelectColumn($alias . '.PAYMENT_MODULE_ID');
            $criteria->addSelectColumn($alias . '.DELIVERY_MODULE_ID');
            $criteria->addSelectColumn($alias . '.STATUS_ID');
            $criteria->addSelectColumn($alias . '.LANG_ID');
            $criteria->addSelectColumn($alias . '.CART_ID');
            $criteria->addSelectColumn($alias . '.CREATED_AT');
            $criteria->addSelectColumn($alias . '.UPDATED_AT');
            $criteria->addSelectColumn($alias . '.VERSION');
            $criteria->addSelectColumn($alias . '.VERSION_CREATED_AT');
            $criteria->addSelectColumn($alias . '.VERSION_CREATED_BY');
        }
    }

    /**
     * Returns the TableMap related to this object.
     * This method is not needed for general use but a specific application could have a need.
     * @return TableMap
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function getTableMap()
    {
        return Propel::getServiceContainer()->getDatabaseMap(OrderTableMap::DATABASE_NAME)->getTable(OrderTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(OrderTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(OrderTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new OrderTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a Order or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Order object or primary key or array of primary keys
     *              which is used to create the DELETE statement
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *                if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
     public static function doDelete($values, ConnectionInterface $con = null)
     {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\Order) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(OrderTableMap::DATABASE_NAME);
            $criteria->add(OrderTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = OrderQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { OrderTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { OrderTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the order table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return OrderQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Order or Criteria object.
     *
     * @param mixed               $criteria Criteria or Order object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Order object
        }

        if ($criteria->containsKey(OrderTableMap::ID) && $criteria->keyContainsValue(OrderTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.OrderTableMap::ID.')');
        }


        // Set the correct dbName
        $query = OrderQuery::create()->mergeWith($criteria);

        try {
            // use transaction because $criteria could contain info
            // for more than one table (I guess, conceivably)
            $con->beginTransaction();
            $pk = $query->doInsert($con);
            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $pk;
    }

} // OrderTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
OrderTableMap::buildTableMap();
