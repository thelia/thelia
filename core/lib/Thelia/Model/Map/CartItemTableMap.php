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
use Thelia\Model\CartItem;
use Thelia\Model\CartItemQuery;


/**
 * This class defines the structure of the 'cart_item' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class CartItemTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.CartItemTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'cart_item';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\CartItem';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.CartItem';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 11;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 11;

    /**
     * the column name for the ID field
     */
    const ID = 'cart_item.ID';

    /**
     * the column name for the CART_ID field
     */
    const CART_ID = 'cart_item.CART_ID';

    /**
     * the column name for the PRODUCT_ID field
     */
    const PRODUCT_ID = 'cart_item.PRODUCT_ID';

    /**
     * the column name for the QUANTITY field
     */
    const QUANTITY = 'cart_item.QUANTITY';

    /**
     * the column name for the PRODUCT_SALE_ELEMENTS_ID field
     */
    const PRODUCT_SALE_ELEMENTS_ID = 'cart_item.PRODUCT_SALE_ELEMENTS_ID';

    /**
     * the column name for the PRICE field
     */
    const PRICE = 'cart_item.PRICE';

    /**
     * the column name for the PROMO_PRICE field
     */
    const PROMO_PRICE = 'cart_item.PROMO_PRICE';

    /**
     * the column name for the PRICE_END_OF_LIFE field
     */
    const PRICE_END_OF_LIFE = 'cart_item.PRICE_END_OF_LIFE';

    /**
     * the column name for the PROMO field
     */
    const PROMO = 'cart_item.PROMO';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'cart_item.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'cart_item.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('Id', 'CartId', 'ProductId', 'Quantity', 'ProductSaleElementsId', 'Price', 'PromoPrice', 'PriceEndOfLife', 'Promo', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'cartId', 'productId', 'quantity', 'productSaleElementsId', 'price', 'promoPrice', 'priceEndOfLife', 'promo', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(CartItemTableMap::ID, CartItemTableMap::CART_ID, CartItemTableMap::PRODUCT_ID, CartItemTableMap::QUANTITY, CartItemTableMap::PRODUCT_SALE_ELEMENTS_ID, CartItemTableMap::PRICE, CartItemTableMap::PROMO_PRICE, CartItemTableMap::PRICE_END_OF_LIFE, CartItemTableMap::PROMO, CartItemTableMap::CREATED_AT, CartItemTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'CART_ID', 'PRODUCT_ID', 'QUANTITY', 'PRODUCT_SALE_ELEMENTS_ID', 'PRICE', 'PROMO_PRICE', 'PRICE_END_OF_LIFE', 'PROMO', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('id', 'cart_id', 'product_id', 'quantity', 'product_sale_elements_id', 'price', 'promo_price', 'price_end_of_life', 'promo', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'CartId' => 1, 'ProductId' => 2, 'Quantity' => 3, 'ProductSaleElementsId' => 4, 'Price' => 5, 'PromoPrice' => 6, 'PriceEndOfLife' => 7, 'Promo' => 8, 'CreatedAt' => 9, 'UpdatedAt' => 10, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'cartId' => 1, 'productId' => 2, 'quantity' => 3, 'productSaleElementsId' => 4, 'price' => 5, 'promoPrice' => 6, 'priceEndOfLife' => 7, 'promo' => 8, 'createdAt' => 9, 'updatedAt' => 10, ),
        self::TYPE_COLNAME       => array(CartItemTableMap::ID => 0, CartItemTableMap::CART_ID => 1, CartItemTableMap::PRODUCT_ID => 2, CartItemTableMap::QUANTITY => 3, CartItemTableMap::PRODUCT_SALE_ELEMENTS_ID => 4, CartItemTableMap::PRICE => 5, CartItemTableMap::PROMO_PRICE => 6, CartItemTableMap::PRICE_END_OF_LIFE => 7, CartItemTableMap::PROMO => 8, CartItemTableMap::CREATED_AT => 9, CartItemTableMap::UPDATED_AT => 10, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'CART_ID' => 1, 'PRODUCT_ID' => 2, 'QUANTITY' => 3, 'PRODUCT_SALE_ELEMENTS_ID' => 4, 'PRICE' => 5, 'PROMO_PRICE' => 6, 'PRICE_END_OF_LIFE' => 7, 'PROMO' => 8, 'CREATED_AT' => 9, 'UPDATED_AT' => 10, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'cart_id' => 1, 'product_id' => 2, 'quantity' => 3, 'product_sale_elements_id' => 4, 'price' => 5, 'promo_price' => 6, 'price_end_of_life' => 7, 'promo' => 8, 'created_at' => 9, 'updated_at' => 10, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, )
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
        $this->setName('cart_item');
        $this->setPhpName('CartItem');
        $this->setClassName('\\Thelia\\Model\\CartItem');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('CART_ID', 'CartId', 'INTEGER', 'cart', 'ID', true, null, null);
        $this->addForeignKey('PRODUCT_ID', 'ProductId', 'INTEGER', 'product', 'ID', true, null, null);
        $this->addColumn('QUANTITY', 'Quantity', 'FLOAT', false, null, 1);
        $this->addForeignKey('PRODUCT_SALE_ELEMENTS_ID', 'ProductSaleElementsId', 'INTEGER', 'product_sale_elements', 'ID', true, null, null);
        $this->addColumn('PRICE', 'Price', 'DECIMAL', false, 16, 0);
        $this->addColumn('PROMO_PRICE', 'PromoPrice', 'DECIMAL', false, 16, 0);
        $this->addColumn('PRICE_END_OF_LIFE', 'PriceEndOfLife', 'TIMESTAMP', false, null, null);
        $this->addColumn('PROMO', 'Promo', 'INTEGER', false, null, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Cart', '\\Thelia\\Model\\Cart', RelationMap::MANY_TO_ONE, array('cart_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Product', '\\Thelia\\Model\\Product', RelationMap::MANY_TO_ONE, array('product_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('ProductSaleElements', '\\Thelia\\Model\\ProductSaleElements', RelationMap::MANY_TO_ONE, array('product_sale_elements_id' => 'id', ), 'CASCADE', 'RESTRICT');
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
        return $withPrefix ? CartItemTableMap::CLASS_DEFAULT : CartItemTableMap::OM_CLASS;
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
     * @return array (CartItem object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = CartItemTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = CartItemTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + CartItemTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = CartItemTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            CartItemTableMap::addInstanceToPool($obj, $key);
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
            $key = CartItemTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = CartItemTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                CartItemTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(CartItemTableMap::ID);
            $criteria->addSelectColumn(CartItemTableMap::CART_ID);
            $criteria->addSelectColumn(CartItemTableMap::PRODUCT_ID);
            $criteria->addSelectColumn(CartItemTableMap::QUANTITY);
            $criteria->addSelectColumn(CartItemTableMap::PRODUCT_SALE_ELEMENTS_ID);
            $criteria->addSelectColumn(CartItemTableMap::PRICE);
            $criteria->addSelectColumn(CartItemTableMap::PROMO_PRICE);
            $criteria->addSelectColumn(CartItemTableMap::PRICE_END_OF_LIFE);
            $criteria->addSelectColumn(CartItemTableMap::PROMO);
            $criteria->addSelectColumn(CartItemTableMap::CREATED_AT);
            $criteria->addSelectColumn(CartItemTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.CART_ID');
            $criteria->addSelectColumn($alias . '.PRODUCT_ID');
            $criteria->addSelectColumn($alias . '.QUANTITY');
            $criteria->addSelectColumn($alias . '.PRODUCT_SALE_ELEMENTS_ID');
            $criteria->addSelectColumn($alias . '.PRICE');
            $criteria->addSelectColumn($alias . '.PROMO_PRICE');
            $criteria->addSelectColumn($alias . '.PRICE_END_OF_LIFE');
            $criteria->addSelectColumn($alias . '.PROMO');
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
        return Propel::getServiceContainer()->getDatabaseMap(CartItemTableMap::DATABASE_NAME)->getTable(CartItemTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(CartItemTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(CartItemTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new CartItemTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a CartItem or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or CartItem object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(CartItemTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\CartItem) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(CartItemTableMap::DATABASE_NAME);
            $criteria->add(CartItemTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = CartItemQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { CartItemTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { CartItemTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the cart_item table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return CartItemQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a CartItem or Criteria object.
     *
     * @param mixed               $criteria Criteria or CartItem object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CartItemTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from CartItem object
        }

        if ($criteria->containsKey(CartItemTableMap::ID) && $criteria->keyContainsValue(CartItemTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.CartItemTableMap::ID.')');
        }


        // Set the correct dbName
        $query = CartItemQuery::create()->mergeWith($criteria);

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

} // CartItemTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
CartItemTableMap::buildTableMap();
