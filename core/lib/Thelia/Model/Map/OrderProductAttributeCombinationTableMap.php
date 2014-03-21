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
use Thelia\Model\OrderProductAttributeCombination;
use Thelia\Model\OrderProductAttributeCombinationQuery;


/**
 * This class defines the structure of the 'order_product_attribute_combination' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class OrderProductAttributeCombinationTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.OrderProductAttributeCombinationTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'order_product_attribute_combination';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\OrderProductAttributeCombination';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.OrderProductAttributeCombination';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 12;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 12;

    /**
     * the column name for the ID field
     */
    const ID = 'order_product_attribute_combination.ID';

    /**
     * the column name for the ORDER_PRODUCT_ID field
     */
    const ORDER_PRODUCT_ID = 'order_product_attribute_combination.ORDER_PRODUCT_ID';

    /**
     * the column name for the ATTRIBUTE_TITLE field
     */
    const ATTRIBUTE_TITLE = 'order_product_attribute_combination.ATTRIBUTE_TITLE';

    /**
     * the column name for the ATTRIBUTE_CHAPO field
     */
    const ATTRIBUTE_CHAPO = 'order_product_attribute_combination.ATTRIBUTE_CHAPO';

    /**
     * the column name for the ATTRIBUTE_DESCRIPTION field
     */
    const ATTRIBUTE_DESCRIPTION = 'order_product_attribute_combination.ATTRIBUTE_DESCRIPTION';

    /**
     * the column name for the ATTRIBUTE_POSTSCRIPTUM field
     */
    const ATTRIBUTE_POSTSCRIPTUM = 'order_product_attribute_combination.ATTRIBUTE_POSTSCRIPTUM';

    /**
     * the column name for the ATTRIBUTE_AV_TITLE field
     */
    const ATTRIBUTE_AV_TITLE = 'order_product_attribute_combination.ATTRIBUTE_AV_TITLE';

    /**
     * the column name for the ATTRIBUTE_AV_CHAPO field
     */
    const ATTRIBUTE_AV_CHAPO = 'order_product_attribute_combination.ATTRIBUTE_AV_CHAPO';

    /**
     * the column name for the ATTRIBUTE_AV_DESCRIPTION field
     */
    const ATTRIBUTE_AV_DESCRIPTION = 'order_product_attribute_combination.ATTRIBUTE_AV_DESCRIPTION';

    /**
     * the column name for the ATTRIBUTE_AV_POSTSCRIPTUM field
     */
    const ATTRIBUTE_AV_POSTSCRIPTUM = 'order_product_attribute_combination.ATTRIBUTE_AV_POSTSCRIPTUM';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'order_product_attribute_combination.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'order_product_attribute_combination.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('Id', 'OrderProductId', 'AttributeTitle', 'AttributeChapo', 'AttributeDescription', 'AttributePostscriptum', 'AttributeAvTitle', 'AttributeAvChapo', 'AttributeAvDescription', 'AttributeAvPostscriptum', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'orderProductId', 'attributeTitle', 'attributeChapo', 'attributeDescription', 'attributePostscriptum', 'attributeAvTitle', 'attributeAvChapo', 'attributeAvDescription', 'attributeAvPostscriptum', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(OrderProductAttributeCombinationTableMap::ID, OrderProductAttributeCombinationTableMap::ORDER_PRODUCT_ID, OrderProductAttributeCombinationTableMap::ATTRIBUTE_TITLE, OrderProductAttributeCombinationTableMap::ATTRIBUTE_CHAPO, OrderProductAttributeCombinationTableMap::ATTRIBUTE_DESCRIPTION, OrderProductAttributeCombinationTableMap::ATTRIBUTE_POSTSCRIPTUM, OrderProductAttributeCombinationTableMap::ATTRIBUTE_AV_TITLE, OrderProductAttributeCombinationTableMap::ATTRIBUTE_AV_CHAPO, OrderProductAttributeCombinationTableMap::ATTRIBUTE_AV_DESCRIPTION, OrderProductAttributeCombinationTableMap::ATTRIBUTE_AV_POSTSCRIPTUM, OrderProductAttributeCombinationTableMap::CREATED_AT, OrderProductAttributeCombinationTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'ORDER_PRODUCT_ID', 'ATTRIBUTE_TITLE', 'ATTRIBUTE_CHAPO', 'ATTRIBUTE_DESCRIPTION', 'ATTRIBUTE_POSTSCRIPTUM', 'ATTRIBUTE_AV_TITLE', 'ATTRIBUTE_AV_CHAPO', 'ATTRIBUTE_AV_DESCRIPTION', 'ATTRIBUTE_AV_POSTSCRIPTUM', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('id', 'order_product_id', 'attribute_title', 'attribute_chapo', 'attribute_description', 'attribute_postscriptum', 'attribute_av_title', 'attribute_av_chapo', 'attribute_av_description', 'attribute_av_postscriptum', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'OrderProductId' => 1, 'AttributeTitle' => 2, 'AttributeChapo' => 3, 'AttributeDescription' => 4, 'AttributePostscriptum' => 5, 'AttributeAvTitle' => 6, 'AttributeAvChapo' => 7, 'AttributeAvDescription' => 8, 'AttributeAvPostscriptum' => 9, 'CreatedAt' => 10, 'UpdatedAt' => 11, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'orderProductId' => 1, 'attributeTitle' => 2, 'attributeChapo' => 3, 'attributeDescription' => 4, 'attributePostscriptum' => 5, 'attributeAvTitle' => 6, 'attributeAvChapo' => 7, 'attributeAvDescription' => 8, 'attributeAvPostscriptum' => 9, 'createdAt' => 10, 'updatedAt' => 11, ),
        self::TYPE_COLNAME       => array(OrderProductAttributeCombinationTableMap::ID => 0, OrderProductAttributeCombinationTableMap::ORDER_PRODUCT_ID => 1, OrderProductAttributeCombinationTableMap::ATTRIBUTE_TITLE => 2, OrderProductAttributeCombinationTableMap::ATTRIBUTE_CHAPO => 3, OrderProductAttributeCombinationTableMap::ATTRIBUTE_DESCRIPTION => 4, OrderProductAttributeCombinationTableMap::ATTRIBUTE_POSTSCRIPTUM => 5, OrderProductAttributeCombinationTableMap::ATTRIBUTE_AV_TITLE => 6, OrderProductAttributeCombinationTableMap::ATTRIBUTE_AV_CHAPO => 7, OrderProductAttributeCombinationTableMap::ATTRIBUTE_AV_DESCRIPTION => 8, OrderProductAttributeCombinationTableMap::ATTRIBUTE_AV_POSTSCRIPTUM => 9, OrderProductAttributeCombinationTableMap::CREATED_AT => 10, OrderProductAttributeCombinationTableMap::UPDATED_AT => 11, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'ORDER_PRODUCT_ID' => 1, 'ATTRIBUTE_TITLE' => 2, 'ATTRIBUTE_CHAPO' => 3, 'ATTRIBUTE_DESCRIPTION' => 4, 'ATTRIBUTE_POSTSCRIPTUM' => 5, 'ATTRIBUTE_AV_TITLE' => 6, 'ATTRIBUTE_AV_CHAPO' => 7, 'ATTRIBUTE_AV_DESCRIPTION' => 8, 'ATTRIBUTE_AV_POSTSCRIPTUM' => 9, 'CREATED_AT' => 10, 'UPDATED_AT' => 11, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'order_product_id' => 1, 'attribute_title' => 2, 'attribute_chapo' => 3, 'attribute_description' => 4, 'attribute_postscriptum' => 5, 'attribute_av_title' => 6, 'attribute_av_chapo' => 7, 'attribute_av_description' => 8, 'attribute_av_postscriptum' => 9, 'created_at' => 10, 'updated_at' => 11, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, )
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
        $this->setName('order_product_attribute_combination');
        $this->setPhpName('OrderProductAttributeCombination');
        $this->setClassName('\\Thelia\\Model\\OrderProductAttributeCombination');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('ORDER_PRODUCT_ID', 'OrderProductId', 'INTEGER', 'order_product', 'ID', true, null, null);
        $this->addColumn('ATTRIBUTE_TITLE', 'AttributeTitle', 'VARCHAR', true, 255, null);
        $this->addColumn('ATTRIBUTE_CHAPO', 'AttributeChapo', 'LONGVARCHAR', false, null, null);
        $this->addColumn('ATTRIBUTE_DESCRIPTION', 'AttributeDescription', 'CLOB', false, null, null);
        $this->addColumn('ATTRIBUTE_POSTSCRIPTUM', 'AttributePostscriptum', 'LONGVARCHAR', false, null, null);
        $this->addColumn('ATTRIBUTE_AV_TITLE', 'AttributeAvTitle', 'VARCHAR', true, 255, null);
        $this->addColumn('ATTRIBUTE_AV_CHAPO', 'AttributeAvChapo', 'LONGVARCHAR', false, null, null);
        $this->addColumn('ATTRIBUTE_AV_DESCRIPTION', 'AttributeAvDescription', 'CLOB', false, null, null);
        $this->addColumn('ATTRIBUTE_AV_POSTSCRIPTUM', 'AttributeAvPostscriptum', 'LONGVARCHAR', false, null, null);
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
        return $withPrefix ? OrderProductAttributeCombinationTableMap::CLASS_DEFAULT : OrderProductAttributeCombinationTableMap::OM_CLASS;
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
     * @return array (OrderProductAttributeCombination object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = OrderProductAttributeCombinationTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = OrderProductAttributeCombinationTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + OrderProductAttributeCombinationTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = OrderProductAttributeCombinationTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            OrderProductAttributeCombinationTableMap::addInstanceToPool($obj, $key);
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
            $key = OrderProductAttributeCombinationTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = OrderProductAttributeCombinationTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                OrderProductAttributeCombinationTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(OrderProductAttributeCombinationTableMap::ID);
            $criteria->addSelectColumn(OrderProductAttributeCombinationTableMap::ORDER_PRODUCT_ID);
            $criteria->addSelectColumn(OrderProductAttributeCombinationTableMap::ATTRIBUTE_TITLE);
            $criteria->addSelectColumn(OrderProductAttributeCombinationTableMap::ATTRIBUTE_CHAPO);
            $criteria->addSelectColumn(OrderProductAttributeCombinationTableMap::ATTRIBUTE_DESCRIPTION);
            $criteria->addSelectColumn(OrderProductAttributeCombinationTableMap::ATTRIBUTE_POSTSCRIPTUM);
            $criteria->addSelectColumn(OrderProductAttributeCombinationTableMap::ATTRIBUTE_AV_TITLE);
            $criteria->addSelectColumn(OrderProductAttributeCombinationTableMap::ATTRIBUTE_AV_CHAPO);
            $criteria->addSelectColumn(OrderProductAttributeCombinationTableMap::ATTRIBUTE_AV_DESCRIPTION);
            $criteria->addSelectColumn(OrderProductAttributeCombinationTableMap::ATTRIBUTE_AV_POSTSCRIPTUM);
            $criteria->addSelectColumn(OrderProductAttributeCombinationTableMap::CREATED_AT);
            $criteria->addSelectColumn(OrderProductAttributeCombinationTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.ORDER_PRODUCT_ID');
            $criteria->addSelectColumn($alias . '.ATTRIBUTE_TITLE');
            $criteria->addSelectColumn($alias . '.ATTRIBUTE_CHAPO');
            $criteria->addSelectColumn($alias . '.ATTRIBUTE_DESCRIPTION');
            $criteria->addSelectColumn($alias . '.ATTRIBUTE_POSTSCRIPTUM');
            $criteria->addSelectColumn($alias . '.ATTRIBUTE_AV_TITLE');
            $criteria->addSelectColumn($alias . '.ATTRIBUTE_AV_CHAPO');
            $criteria->addSelectColumn($alias . '.ATTRIBUTE_AV_DESCRIPTION');
            $criteria->addSelectColumn($alias . '.ATTRIBUTE_AV_POSTSCRIPTUM');
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
        return Propel::getServiceContainer()->getDatabaseMap(OrderProductAttributeCombinationTableMap::DATABASE_NAME)->getTable(OrderProductAttributeCombinationTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(OrderProductAttributeCombinationTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(OrderProductAttributeCombinationTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new OrderProductAttributeCombinationTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a OrderProductAttributeCombination or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or OrderProductAttributeCombination object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(OrderProductAttributeCombinationTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\OrderProductAttributeCombination) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(OrderProductAttributeCombinationTableMap::DATABASE_NAME);
            $criteria->add(OrderProductAttributeCombinationTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = OrderProductAttributeCombinationQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { OrderProductAttributeCombinationTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { OrderProductAttributeCombinationTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the order_product_attribute_combination table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return OrderProductAttributeCombinationQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a OrderProductAttributeCombination or Criteria object.
     *
     * @param mixed               $criteria Criteria or OrderProductAttributeCombination object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderProductAttributeCombinationTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from OrderProductAttributeCombination object
        }

        if ($criteria->containsKey(OrderProductAttributeCombinationTableMap::ID) && $criteria->keyContainsValue(OrderProductAttributeCombinationTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.OrderProductAttributeCombinationTableMap::ID.')');
        }


        // Set the correct dbName
        $query = OrderProductAttributeCombinationQuery::create()->mergeWith($criteria);

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

} // OrderProductAttributeCombinationTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
OrderProductAttributeCombinationTableMap::buildTableMap();
