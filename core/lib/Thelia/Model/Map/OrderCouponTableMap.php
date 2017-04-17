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
use Thelia\Model\OrderCoupon;
use Thelia\Model\OrderCouponQuery;


/**
 * This class defines the structure of the 'order_coupon' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class OrderCouponTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.OrderCouponTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'order_coupon';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\OrderCoupon';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.OrderCoupon';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 18;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 18;

    /**
     * the column name for the ID field
     */
    const ID = 'order_coupon.ID';

    /**
     * the column name for the ORDER_ID field
     */
    const ORDER_ID = 'order_coupon.ORDER_ID';

    /**
     * the column name for the CODE field
     */
    const CODE = 'order_coupon.CODE';

    /**
     * the column name for the TYPE field
     */
    const TYPE = 'order_coupon.TYPE';

    /**
     * the column name for the AMOUNT field
     */
    const AMOUNT = 'order_coupon.AMOUNT';

    /**
     * the column name for the TITLE field
     */
    const TITLE = 'order_coupon.TITLE';

    /**
     * the column name for the SHORT_DESCRIPTION field
     */
    const SHORT_DESCRIPTION = 'order_coupon.SHORT_DESCRIPTION';

    /**
     * the column name for the DESCRIPTION field
     */
    const DESCRIPTION = 'order_coupon.DESCRIPTION';

    /**
     * the column name for the START_DATE field
     */
    const START_DATE = 'order_coupon.START_DATE';

    /**
     * the column name for the EXPIRATION_DATE field
     */
    const EXPIRATION_DATE = 'order_coupon.EXPIRATION_DATE';

    /**
     * the column name for the IS_CUMULATIVE field
     */
    const IS_CUMULATIVE = 'order_coupon.IS_CUMULATIVE';

    /**
     * the column name for the IS_REMOVING_POSTAGE field
     */
    const IS_REMOVING_POSTAGE = 'order_coupon.IS_REMOVING_POSTAGE';

    /**
     * the column name for the IS_AVAILABLE_ON_SPECIAL_OFFERS field
     */
    const IS_AVAILABLE_ON_SPECIAL_OFFERS = 'order_coupon.IS_AVAILABLE_ON_SPECIAL_OFFERS';

    /**
     * the column name for the SERIALIZED_CONDITIONS field
     */
    const SERIALIZED_CONDITIONS = 'order_coupon.SERIALIZED_CONDITIONS';

    /**
     * the column name for the PER_CUSTOMER_USAGE_COUNT field
     */
    const PER_CUSTOMER_USAGE_COUNT = 'order_coupon.PER_CUSTOMER_USAGE_COUNT';

    /**
     * the column name for the USAGE_CANCELED field
     */
    const USAGE_CANCELED = 'order_coupon.USAGE_CANCELED';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'order_coupon.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'order_coupon.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('Id', 'OrderId', 'Code', 'Type', 'Amount', 'Title', 'ShortDescription', 'Description', 'StartDate', 'ExpirationDate', 'IsCumulative', 'IsRemovingPostage', 'IsAvailableOnSpecialOffers', 'SerializedConditions', 'PerCustomerUsageCount', 'UsageCanceled', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'orderId', 'code', 'type', 'amount', 'title', 'shortDescription', 'description', 'startDate', 'expirationDate', 'isCumulative', 'isRemovingPostage', 'isAvailableOnSpecialOffers', 'serializedConditions', 'perCustomerUsageCount', 'usageCanceled', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(OrderCouponTableMap::ID, OrderCouponTableMap::ORDER_ID, OrderCouponTableMap::CODE, OrderCouponTableMap::TYPE, OrderCouponTableMap::AMOUNT, OrderCouponTableMap::TITLE, OrderCouponTableMap::SHORT_DESCRIPTION, OrderCouponTableMap::DESCRIPTION, OrderCouponTableMap::START_DATE, OrderCouponTableMap::EXPIRATION_DATE, OrderCouponTableMap::IS_CUMULATIVE, OrderCouponTableMap::IS_REMOVING_POSTAGE, OrderCouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS, OrderCouponTableMap::SERIALIZED_CONDITIONS, OrderCouponTableMap::PER_CUSTOMER_USAGE_COUNT, OrderCouponTableMap::USAGE_CANCELED, OrderCouponTableMap::CREATED_AT, OrderCouponTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'ORDER_ID', 'CODE', 'TYPE', 'AMOUNT', 'TITLE', 'SHORT_DESCRIPTION', 'DESCRIPTION', 'START_DATE', 'EXPIRATION_DATE', 'IS_CUMULATIVE', 'IS_REMOVING_POSTAGE', 'IS_AVAILABLE_ON_SPECIAL_OFFERS', 'SERIALIZED_CONDITIONS', 'PER_CUSTOMER_USAGE_COUNT', 'USAGE_CANCELED', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('id', 'order_id', 'code', 'type', 'amount', 'title', 'short_description', 'description', 'start_date', 'expiration_date', 'is_cumulative', 'is_removing_postage', 'is_available_on_special_offers', 'serialized_conditions', 'per_customer_usage_count', 'usage_canceled', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'OrderId' => 1, 'Code' => 2, 'Type' => 3, 'Amount' => 4, 'Title' => 5, 'ShortDescription' => 6, 'Description' => 7, 'StartDate' => 8, 'ExpirationDate' => 9, 'IsCumulative' => 10, 'IsRemovingPostage' => 11, 'IsAvailableOnSpecialOffers' => 12, 'SerializedConditions' => 13, 'PerCustomerUsageCount' => 14, 'UsageCanceled' => 15, 'CreatedAt' => 16, 'UpdatedAt' => 17, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'orderId' => 1, 'code' => 2, 'type' => 3, 'amount' => 4, 'title' => 5, 'shortDescription' => 6, 'description' => 7, 'startDate' => 8, 'expirationDate' => 9, 'isCumulative' => 10, 'isRemovingPostage' => 11, 'isAvailableOnSpecialOffers' => 12, 'serializedConditions' => 13, 'perCustomerUsageCount' => 14, 'usageCanceled' => 15, 'createdAt' => 16, 'updatedAt' => 17, ),
        self::TYPE_COLNAME       => array(OrderCouponTableMap::ID => 0, OrderCouponTableMap::ORDER_ID => 1, OrderCouponTableMap::CODE => 2, OrderCouponTableMap::TYPE => 3, OrderCouponTableMap::AMOUNT => 4, OrderCouponTableMap::TITLE => 5, OrderCouponTableMap::SHORT_DESCRIPTION => 6, OrderCouponTableMap::DESCRIPTION => 7, OrderCouponTableMap::START_DATE => 8, OrderCouponTableMap::EXPIRATION_DATE => 9, OrderCouponTableMap::IS_CUMULATIVE => 10, OrderCouponTableMap::IS_REMOVING_POSTAGE => 11, OrderCouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS => 12, OrderCouponTableMap::SERIALIZED_CONDITIONS => 13, OrderCouponTableMap::PER_CUSTOMER_USAGE_COUNT => 14, OrderCouponTableMap::USAGE_CANCELED => 15, OrderCouponTableMap::CREATED_AT => 16, OrderCouponTableMap::UPDATED_AT => 17, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'ORDER_ID' => 1, 'CODE' => 2, 'TYPE' => 3, 'AMOUNT' => 4, 'TITLE' => 5, 'SHORT_DESCRIPTION' => 6, 'DESCRIPTION' => 7, 'START_DATE' => 8, 'EXPIRATION_DATE' => 9, 'IS_CUMULATIVE' => 10, 'IS_REMOVING_POSTAGE' => 11, 'IS_AVAILABLE_ON_SPECIAL_OFFERS' => 12, 'SERIALIZED_CONDITIONS' => 13, 'PER_CUSTOMER_USAGE_COUNT' => 14, 'USAGE_CANCELED' => 15, 'CREATED_AT' => 16, 'UPDATED_AT' => 17, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'order_id' => 1, 'code' => 2, 'type' => 3, 'amount' => 4, 'title' => 5, 'short_description' => 6, 'description' => 7, 'start_date' => 8, 'expiration_date' => 9, 'is_cumulative' => 10, 'is_removing_postage' => 11, 'is_available_on_special_offers' => 12, 'serialized_conditions' => 13, 'per_customer_usage_count' => 14, 'usage_canceled' => 15, 'created_at' => 16, 'updated_at' => 17, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, )
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
        $this->setName('order_coupon');
        $this->setPhpName('OrderCoupon');
        $this->setClassName('\\Thelia\\Model\\OrderCoupon');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('ORDER_ID', 'OrderId', 'INTEGER', 'order', 'ID', true, null, null);
        $this->addColumn('CODE', 'Code', 'VARCHAR', true, 45, null);
        $this->addColumn('TYPE', 'Type', 'VARCHAR', true, 255, null);
        $this->addColumn('AMOUNT', 'Amount', 'DECIMAL', true, 16, 0);
        $this->addColumn('TITLE', 'Title', 'VARCHAR', true, 255, null);
        $this->addColumn('SHORT_DESCRIPTION', 'ShortDescription', 'LONGVARCHAR', true, null, null);
        $this->addColumn('DESCRIPTION', 'Description', 'CLOB', true, null, null);
        $this->addColumn('START_DATE', 'StartDate', 'TIMESTAMP', false, null, null);
        $this->addColumn('EXPIRATION_DATE', 'ExpirationDate', 'TIMESTAMP', true, null, null);
        $this->addColumn('IS_CUMULATIVE', 'IsCumulative', 'BOOLEAN', true, 1, null);
        $this->addColumn('IS_REMOVING_POSTAGE', 'IsRemovingPostage', 'BOOLEAN', true, 1, null);
        $this->addColumn('IS_AVAILABLE_ON_SPECIAL_OFFERS', 'IsAvailableOnSpecialOffers', 'BOOLEAN', true, 1, null);
        $this->addColumn('SERIALIZED_CONDITIONS', 'SerializedConditions', 'LONGVARCHAR', true, null, null);
        $this->addColumn('PER_CUSTOMER_USAGE_COUNT', 'PerCustomerUsageCount', 'BOOLEAN', true, 1, null);
        $this->addColumn('USAGE_CANCELED', 'UsageCanceled', 'BOOLEAN', false, 1, false);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Order', '\\Thelia\\Model\\Order', RelationMap::MANY_TO_ONE, array('order_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('OrderCouponCountry', '\\Thelia\\Model\\OrderCouponCountry', RelationMap::ONE_TO_MANY, array('id' => 'coupon_id', ), null, null, 'OrderCouponCountries');
        $this->addRelation('OrderCouponModule', '\\Thelia\\Model\\OrderCouponModule', RelationMap::ONE_TO_MANY, array('id' => 'coupon_id', ), 'CASCADE', null, 'OrderCouponModules');
        $this->addRelation('Country', '\\Thelia\\Model\\Country', RelationMap::MANY_TO_MANY, array(), 'CASCADE', null, 'Countries');
        $this->addRelation('Module', '\\Thelia\\Model\\Module', RelationMap::MANY_TO_MANY, array(), 'CASCADE', null, 'Modules');
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
        );
    } // getBehaviors()
    /**
     * Method to invalidate the instance pool of all tables related to order_coupon     * by a foreign key with ON DELETE CASCADE
     */
    public static function clearRelatedInstancePool()
    {
        // Invalidate objects in ".$this->getClassNameFromBuilder($joinedTableTableMapBuilder)." instance pool,
        // since one or more of them may be deleted by ON DELETE CASCADE/SETNULL rule.
                OrderCouponModuleTableMap::clearInstancePool();
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
        return $withPrefix ? OrderCouponTableMap::CLASS_DEFAULT : OrderCouponTableMap::OM_CLASS;
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
     * @return array (OrderCoupon object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = OrderCouponTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = OrderCouponTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + OrderCouponTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = OrderCouponTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            OrderCouponTableMap::addInstanceToPool($obj, $key);
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
            $key = OrderCouponTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = OrderCouponTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                OrderCouponTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(OrderCouponTableMap::ID);
            $criteria->addSelectColumn(OrderCouponTableMap::ORDER_ID);
            $criteria->addSelectColumn(OrderCouponTableMap::CODE);
            $criteria->addSelectColumn(OrderCouponTableMap::TYPE);
            $criteria->addSelectColumn(OrderCouponTableMap::AMOUNT);
            $criteria->addSelectColumn(OrderCouponTableMap::TITLE);
            $criteria->addSelectColumn(OrderCouponTableMap::SHORT_DESCRIPTION);
            $criteria->addSelectColumn(OrderCouponTableMap::DESCRIPTION);
            $criteria->addSelectColumn(OrderCouponTableMap::START_DATE);
            $criteria->addSelectColumn(OrderCouponTableMap::EXPIRATION_DATE);
            $criteria->addSelectColumn(OrderCouponTableMap::IS_CUMULATIVE);
            $criteria->addSelectColumn(OrderCouponTableMap::IS_REMOVING_POSTAGE);
            $criteria->addSelectColumn(OrderCouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS);
            $criteria->addSelectColumn(OrderCouponTableMap::SERIALIZED_CONDITIONS);
            $criteria->addSelectColumn(OrderCouponTableMap::PER_CUSTOMER_USAGE_COUNT);
            $criteria->addSelectColumn(OrderCouponTableMap::USAGE_CANCELED);
            $criteria->addSelectColumn(OrderCouponTableMap::CREATED_AT);
            $criteria->addSelectColumn(OrderCouponTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.ORDER_ID');
            $criteria->addSelectColumn($alias . '.CODE');
            $criteria->addSelectColumn($alias . '.TYPE');
            $criteria->addSelectColumn($alias . '.AMOUNT');
            $criteria->addSelectColumn($alias . '.TITLE');
            $criteria->addSelectColumn($alias . '.SHORT_DESCRIPTION');
            $criteria->addSelectColumn($alias . '.DESCRIPTION');
            $criteria->addSelectColumn($alias . '.START_DATE');
            $criteria->addSelectColumn($alias . '.EXPIRATION_DATE');
            $criteria->addSelectColumn($alias . '.IS_CUMULATIVE');
            $criteria->addSelectColumn($alias . '.IS_REMOVING_POSTAGE');
            $criteria->addSelectColumn($alias . '.IS_AVAILABLE_ON_SPECIAL_OFFERS');
            $criteria->addSelectColumn($alias . '.SERIALIZED_CONDITIONS');
            $criteria->addSelectColumn($alias . '.PER_CUSTOMER_USAGE_COUNT');
            $criteria->addSelectColumn($alias . '.USAGE_CANCELED');
            $criteria->addSelectColumn($alias . '.CREATED_AT');
            $criteria->addSelectColumn($alias . '.UPDATED_AT');
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
        return Propel::getServiceContainer()->getDatabaseMap(OrderCouponTableMap::DATABASE_NAME)->getTable(OrderCouponTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(OrderCouponTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(OrderCouponTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new OrderCouponTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a OrderCoupon or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or OrderCoupon object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(OrderCouponTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\OrderCoupon) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(OrderCouponTableMap::DATABASE_NAME);
            $criteria->add(OrderCouponTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = OrderCouponQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { OrderCouponTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { OrderCouponTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the order_coupon table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return OrderCouponQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a OrderCoupon or Criteria object.
     *
     * @param mixed               $criteria Criteria or OrderCoupon object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderCouponTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from OrderCoupon object
        }

        if ($criteria->containsKey(OrderCouponTableMap::ID) && $criteria->keyContainsValue(OrderCouponTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.OrderCouponTableMap::ID.')');
        }


        // Set the correct dbName
        $query = OrderCouponQuery::create()->mergeWith($criteria);

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

} // OrderCouponTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
OrderCouponTableMap::buildTableMap();
