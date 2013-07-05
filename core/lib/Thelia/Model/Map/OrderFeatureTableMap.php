<?php

namespace Thelia\Model\Map;

use Propel\Runtime\Propel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\InstancePoolTrait;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Map\RelationMap;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Map\TableMapTrait;
use Thelia\Model\OrderFeature;
use Thelia\Model\OrderFeatureQuery;


/**
 * This class defines the structure of the 'order_feature' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class OrderFeatureTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.OrderFeatureTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'order_feature';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\OrderFeature';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.OrderFeature';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 6;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 6;

    /**
     * the column name for the ID field
     */
    const ID = 'order_feature.ID';

    /**
     * the column name for the ORDER_PRODUCT_ID field
     */
    const ORDER_PRODUCT_ID = 'order_feature.ORDER_PRODUCT_ID';

    /**
     * the column name for the FEATURE_DESC field
     */
    const FEATURE_DESC = 'order_feature.FEATURE_DESC';

    /**
     * the column name for the FEATURE_AV_DESC field
     */
    const FEATURE_AV_DESC = 'order_feature.FEATURE_AV_DESC';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'order_feature.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'order_feature.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('Id', 'OrderProductId', 'FeatureDesc', 'FeatureAvDesc', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'orderProductId', 'featureDesc', 'featureAvDesc', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(OrderFeatureTableMap::ID, OrderFeatureTableMap::ORDER_PRODUCT_ID, OrderFeatureTableMap::FEATURE_DESC, OrderFeatureTableMap::FEATURE_AV_DESC, OrderFeatureTableMap::CREATED_AT, OrderFeatureTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'ORDER_PRODUCT_ID', 'FEATURE_DESC', 'FEATURE_AV_DESC', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('id', 'order_product_id', 'feature_desc', 'feature_av_desc', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'OrderProductId' => 1, 'FeatureDesc' => 2, 'FeatureAvDesc' => 3, 'CreatedAt' => 4, 'UpdatedAt' => 5, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'orderProductId' => 1, 'featureDesc' => 2, 'featureAvDesc' => 3, 'createdAt' => 4, 'updatedAt' => 5, ),
        self::TYPE_COLNAME       => array(OrderFeatureTableMap::ID => 0, OrderFeatureTableMap::ORDER_PRODUCT_ID => 1, OrderFeatureTableMap::FEATURE_DESC => 2, OrderFeatureTableMap::FEATURE_AV_DESC => 3, OrderFeatureTableMap::CREATED_AT => 4, OrderFeatureTableMap::UPDATED_AT => 5, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'ORDER_PRODUCT_ID' => 1, 'FEATURE_DESC' => 2, 'FEATURE_AV_DESC' => 3, 'CREATED_AT' => 4, 'UPDATED_AT' => 5, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'order_product_id' => 1, 'feature_desc' => 2, 'feature_av_desc' => 3, 'created_at' => 4, 'updated_at' => 5, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, )
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
        $this->setName('order_feature');
        $this->setPhpName('OrderFeature');
        $this->setClassName('\\Thelia\\Model\\OrderFeature');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('ORDER_PRODUCT_ID', 'OrderProductId', 'INTEGER', 'order_product', 'ID', true, null, null);
        $this->addColumn('FEATURE_DESC', 'FeatureDesc', 'VARCHAR', false, 255, null);
        $this->addColumn('FEATURE_AV_DESC', 'FeatureAvDesc', 'VARCHAR', false, 255, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('OrderProduct', '\\Thelia\\Model\\OrderProduct', RelationMap::MANY_TO_ONE, array('order_product_id' => 'id', ), 'CASCADE', 'RESTRICT');
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
        return $withPrefix ? OrderFeatureTableMap::CLASS_DEFAULT : OrderFeatureTableMap::OM_CLASS;
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
     * @return array (OrderFeature object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = OrderFeatureTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = OrderFeatureTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + OrderFeatureTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = OrderFeatureTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            OrderFeatureTableMap::addInstanceToPool($obj, $key);
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
            $key = OrderFeatureTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = OrderFeatureTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                OrderFeatureTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(OrderFeatureTableMap::ID);
            $criteria->addSelectColumn(OrderFeatureTableMap::ORDER_PRODUCT_ID);
            $criteria->addSelectColumn(OrderFeatureTableMap::FEATURE_DESC);
            $criteria->addSelectColumn(OrderFeatureTableMap::FEATURE_AV_DESC);
            $criteria->addSelectColumn(OrderFeatureTableMap::CREATED_AT);
            $criteria->addSelectColumn(OrderFeatureTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.ORDER_PRODUCT_ID');
            $criteria->addSelectColumn($alias . '.FEATURE_DESC');
            $criteria->addSelectColumn($alias . '.FEATURE_AV_DESC');
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
        return Propel::getServiceContainer()->getDatabaseMap(OrderFeatureTableMap::DATABASE_NAME)->getTable(OrderFeatureTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(OrderFeatureTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(OrderFeatureTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new OrderFeatureTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a OrderFeature or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or OrderFeature object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(OrderFeatureTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\OrderFeature) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(OrderFeatureTableMap::DATABASE_NAME);
            $criteria->add(OrderFeatureTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = OrderFeatureQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { OrderFeatureTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { OrderFeatureTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the order_feature table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return OrderFeatureQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a OrderFeature or Criteria object.
     *
     * @param mixed               $criteria Criteria or OrderFeature object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderFeatureTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from OrderFeature object
        }

        if ($criteria->containsKey(OrderFeatureTableMap::ID) && $criteria->keyContainsValue(OrderFeatureTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.OrderFeatureTableMap::ID.')');
        }


        // Set the correct dbName
        $query = OrderFeatureQuery::create()->mergeWith($criteria);

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

} // OrderFeatureTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
OrderFeatureTableMap::buildTableMap();
