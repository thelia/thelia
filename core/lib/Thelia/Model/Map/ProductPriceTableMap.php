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
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductPriceQuery;


/**
 * This class defines the structure of the 'product_price' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class ProductPriceTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.ProductPriceTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'product_price';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\ProductPrice';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.ProductPrice';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 7;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 7;

    /**
     * the column name for the PRODUCT_SALE_ELEMENTS_ID field
     */
    const PRODUCT_SALE_ELEMENTS_ID = 'product_price.PRODUCT_SALE_ELEMENTS_ID';

    /**
     * the column name for the CURRENCY_ID field
     */
    const CURRENCY_ID = 'product_price.CURRENCY_ID';

    /**
     * the column name for the PRICE field
     */
    const PRICE = 'product_price.PRICE';

    /**
     * the column name for the PROMO_PRICE field
     */
    const PROMO_PRICE = 'product_price.PROMO_PRICE';

    /**
     * the column name for the FROM_DEFAULT_CURRENCY field
     */
    const FROM_DEFAULT_CURRENCY = 'product_price.FROM_DEFAULT_CURRENCY';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'product_price.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'product_price.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('ProductSaleElementsId', 'CurrencyId', 'Price', 'PromoPrice', 'FromDefaultCurrency', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('productSaleElementsId', 'currencyId', 'price', 'promoPrice', 'fromDefaultCurrency', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(ProductPriceTableMap::PRODUCT_SALE_ELEMENTS_ID, ProductPriceTableMap::CURRENCY_ID, ProductPriceTableMap::PRICE, ProductPriceTableMap::PROMO_PRICE, ProductPriceTableMap::FROM_DEFAULT_CURRENCY, ProductPriceTableMap::CREATED_AT, ProductPriceTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('PRODUCT_SALE_ELEMENTS_ID', 'CURRENCY_ID', 'PRICE', 'PROMO_PRICE', 'FROM_DEFAULT_CURRENCY', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('product_sale_elements_id', 'currency_id', 'price', 'promo_price', 'from_default_currency', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('ProductSaleElementsId' => 0, 'CurrencyId' => 1, 'Price' => 2, 'PromoPrice' => 3, 'FromDefaultCurrency' => 4, 'CreatedAt' => 5, 'UpdatedAt' => 6, ),
        self::TYPE_STUDLYPHPNAME => array('productSaleElementsId' => 0, 'currencyId' => 1, 'price' => 2, 'promoPrice' => 3, 'fromDefaultCurrency' => 4, 'createdAt' => 5, 'updatedAt' => 6, ),
        self::TYPE_COLNAME       => array(ProductPriceTableMap::PRODUCT_SALE_ELEMENTS_ID => 0, ProductPriceTableMap::CURRENCY_ID => 1, ProductPriceTableMap::PRICE => 2, ProductPriceTableMap::PROMO_PRICE => 3, ProductPriceTableMap::FROM_DEFAULT_CURRENCY => 4, ProductPriceTableMap::CREATED_AT => 5, ProductPriceTableMap::UPDATED_AT => 6, ),
        self::TYPE_RAW_COLNAME   => array('PRODUCT_SALE_ELEMENTS_ID' => 0, 'CURRENCY_ID' => 1, 'PRICE' => 2, 'PROMO_PRICE' => 3, 'FROM_DEFAULT_CURRENCY' => 4, 'CREATED_AT' => 5, 'UPDATED_AT' => 6, ),
        self::TYPE_FIELDNAME     => array('product_sale_elements_id' => 0, 'currency_id' => 1, 'price' => 2, 'promo_price' => 3, 'from_default_currency' => 4, 'created_at' => 5, 'updated_at' => 6, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, )
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
        $this->setName('product_price');
        $this->setPhpName('ProductPrice');
        $this->setClassName('\\Thelia\\Model\\ProductPrice');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(false);
        // columns
        $this->addForeignPrimaryKey('PRODUCT_SALE_ELEMENTS_ID', 'ProductSaleElementsId', 'INTEGER' , 'product_sale_elements', 'ID', true, null, null);
        $this->addForeignPrimaryKey('CURRENCY_ID', 'CurrencyId', 'INTEGER' , 'currency', 'ID', true, null, null);
        $this->addColumn('PRICE', 'Price', 'DECIMAL', true, 16, 0);
        $this->addColumn('PROMO_PRICE', 'PromoPrice', 'DECIMAL', true, 16, 0);
        $this->addColumn('FROM_DEFAULT_CURRENCY', 'FromDefaultCurrency', 'BOOLEAN', true, 1, true);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('ProductSaleElements', '\\Thelia\\Model\\ProductSaleElements', RelationMap::MANY_TO_ONE, array('product_sale_elements_id' => 'id', ), 'CASCADE', null);
        $this->addRelation('Currency', '\\Thelia\\Model\\Currency', RelationMap::MANY_TO_ONE, array('currency_id' => 'id', ), 'CASCADE', null);
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
     * Adds an object to the instance pool.
     *
     * Propel keeps cached copies of objects in an instance pool when they are retrieved
     * from the database. In some cases you may need to explicitly add objects
     * to the cache in order to ensure that the same objects are always returned by find*()
     * and findPk*() calls.
     *
     * @param \Thelia\Model\ProductPrice $obj A \Thelia\Model\ProductPrice object.
     * @param string $key             (optional) key to use for instance map (for performance boost if key was already calculated externally).
     */
    public static function addInstanceToPool($obj, $key = null)
    {
        if (Propel::isInstancePoolingEnabled()) {
            if (null === $key) {
                $key = serialize(array((string) $obj->getProductSaleElementsId(), (string) $obj->getCurrencyId()));
            } // if key === null
            self::$instances[$key] = $obj;
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
     * @param mixed $value A \Thelia\Model\ProductPrice object or a primary key value.
     */
    public static function removeInstanceFromPool($value)
    {
        if (Propel::isInstancePoolingEnabled() && null !== $value) {
            if (is_object($value) && $value instanceof \Thelia\Model\ProductPrice) {
                $key = serialize(array((string) $value->getProductSaleElementsId(), (string) $value->getCurrencyId()));

            } elseif (is_array($value) && count($value) === 2) {
                // assume we've been passed a primary key";
                $key = serialize(array((string) $value[0], (string) $value[1]));
            } elseif ($value instanceof Criteria) {
                self::$instances = [];

                return;
            } else {
                $e = new PropelException("Invalid value passed to removeInstanceFromPool().  Expected primary key or \Thelia\Model\ProductPrice object; got " . (is_object($value) ? get_class($value) . ' object.' : var_export($value, true)));
                throw $e;
            }

            unset(self::$instances[$key]);
        }
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
        if ($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('ProductSaleElementsId', TableMap::TYPE_PHPNAME, $indexType)] === null && $row[TableMap::TYPE_NUM == $indexType ? 1 + $offset : static::translateFieldName('CurrencyId', TableMap::TYPE_PHPNAME, $indexType)] === null) {
            return null;
        }

        return serialize(array((string) $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('ProductSaleElementsId', TableMap::TYPE_PHPNAME, $indexType)], (string) $row[TableMap::TYPE_NUM == $indexType ? 1 + $offset : static::translateFieldName('CurrencyId', TableMap::TYPE_PHPNAME, $indexType)]));
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

            return $pks;
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
        return $withPrefix ? ProductPriceTableMap::CLASS_DEFAULT : ProductPriceTableMap::OM_CLASS;
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
     * @return array (ProductPrice object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = ProductPriceTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = ProductPriceTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + ProductPriceTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = ProductPriceTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            ProductPriceTableMap::addInstanceToPool($obj, $key);
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
            $key = ProductPriceTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = ProductPriceTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                ProductPriceTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(ProductPriceTableMap::PRODUCT_SALE_ELEMENTS_ID);
            $criteria->addSelectColumn(ProductPriceTableMap::CURRENCY_ID);
            $criteria->addSelectColumn(ProductPriceTableMap::PRICE);
            $criteria->addSelectColumn(ProductPriceTableMap::PROMO_PRICE);
            $criteria->addSelectColumn(ProductPriceTableMap::FROM_DEFAULT_CURRENCY);
            $criteria->addSelectColumn(ProductPriceTableMap::CREATED_AT);
            $criteria->addSelectColumn(ProductPriceTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.PRODUCT_SALE_ELEMENTS_ID');
            $criteria->addSelectColumn($alias . '.CURRENCY_ID');
            $criteria->addSelectColumn($alias . '.PRICE');
            $criteria->addSelectColumn($alias . '.PROMO_PRICE');
            $criteria->addSelectColumn($alias . '.FROM_DEFAULT_CURRENCY');
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
        return Propel::getServiceContainer()->getDatabaseMap(ProductPriceTableMap::DATABASE_NAME)->getTable(ProductPriceTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(ProductPriceTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(ProductPriceTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new ProductPriceTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a ProductPrice or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ProductPrice object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(ProductPriceTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\ProductPrice) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(ProductPriceTableMap::DATABASE_NAME);
            // primary key is composite; we therefore, expect
            // the primary key passed to be an array of pkey values
            if (count($values) == count($values, COUNT_RECURSIVE)) {
                // array is not multi-dimensional
                $values = array($values);
            }
            foreach ($values as $value) {
                $criterion = $criteria->getNewCriterion(ProductPriceTableMap::PRODUCT_SALE_ELEMENTS_ID, $value[0]);
                $criterion->addAnd($criteria->getNewCriterion(ProductPriceTableMap::CURRENCY_ID, $value[1]));
                $criteria->addOr($criterion);
            }
        }

        $query = ProductPriceQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { ProductPriceTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { ProductPriceTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the product_price table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return ProductPriceQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a ProductPrice or Criteria object.
     *
     * @param mixed               $criteria Criteria or ProductPrice object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ProductPriceTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from ProductPrice object
        }


        // Set the correct dbName
        $query = ProductPriceQuery::create()->mergeWith($criteria);

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

} // ProductPriceTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
ProductPriceTableMap::buildTableMap();
