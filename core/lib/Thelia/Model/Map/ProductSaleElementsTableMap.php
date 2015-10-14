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
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;


/**
 * This class defines the structure of the 'product_sale_elements' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class ProductSaleElementsTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.ProductSaleElementsTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'product_sale_elements';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\ProductSaleElements';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.ProductSaleElements';

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
    const ID = 'product_sale_elements.ID';

    /**
     * the column name for the PRODUCT_ID field
     */
    const PRODUCT_ID = 'product_sale_elements.PRODUCT_ID';

    /**
     * the column name for the REF field
     */
    const REF = 'product_sale_elements.REF';

    /**
     * the column name for the QUANTITY field
     */
    const QUANTITY = 'product_sale_elements.QUANTITY';

    /**
     * the column name for the PROMO field
     */
    const PROMO = 'product_sale_elements.PROMO';

    /**
     * the column name for the NEWNESS field
     */
    const NEWNESS = 'product_sale_elements.NEWNESS';

    /**
     * the column name for the WEIGHT field
     */
    const WEIGHT = 'product_sale_elements.WEIGHT';

    /**
     * the column name for the IS_DEFAULT field
     */
    const IS_DEFAULT = 'product_sale_elements.IS_DEFAULT';

    /**
     * the column name for the EAN_CODE field
     */
    const EAN_CODE = 'product_sale_elements.EAN_CODE';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'product_sale_elements.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'product_sale_elements.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('Id', 'ProductId', 'Ref', 'Quantity', 'Promo', 'Newness', 'Weight', 'IsDefault', 'EanCode', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'productId', 'ref', 'quantity', 'promo', 'newness', 'weight', 'isDefault', 'eanCode', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(ProductSaleElementsTableMap::ID, ProductSaleElementsTableMap::PRODUCT_ID, ProductSaleElementsTableMap::REF, ProductSaleElementsTableMap::QUANTITY, ProductSaleElementsTableMap::PROMO, ProductSaleElementsTableMap::NEWNESS, ProductSaleElementsTableMap::WEIGHT, ProductSaleElementsTableMap::IS_DEFAULT, ProductSaleElementsTableMap::EAN_CODE, ProductSaleElementsTableMap::CREATED_AT, ProductSaleElementsTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'PRODUCT_ID', 'REF', 'QUANTITY', 'PROMO', 'NEWNESS', 'WEIGHT', 'IS_DEFAULT', 'EAN_CODE', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('id', 'product_id', 'ref', 'quantity', 'promo', 'newness', 'weight', 'is_default', 'ean_code', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'ProductId' => 1, 'Ref' => 2, 'Quantity' => 3, 'Promo' => 4, 'Newness' => 5, 'Weight' => 6, 'IsDefault' => 7, 'EanCode' => 8, 'CreatedAt' => 9, 'UpdatedAt' => 10, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'productId' => 1, 'ref' => 2, 'quantity' => 3, 'promo' => 4, 'newness' => 5, 'weight' => 6, 'isDefault' => 7, 'eanCode' => 8, 'createdAt' => 9, 'updatedAt' => 10, ),
        self::TYPE_COLNAME       => array(ProductSaleElementsTableMap::ID => 0, ProductSaleElementsTableMap::PRODUCT_ID => 1, ProductSaleElementsTableMap::REF => 2, ProductSaleElementsTableMap::QUANTITY => 3, ProductSaleElementsTableMap::PROMO => 4, ProductSaleElementsTableMap::NEWNESS => 5, ProductSaleElementsTableMap::WEIGHT => 6, ProductSaleElementsTableMap::IS_DEFAULT => 7, ProductSaleElementsTableMap::EAN_CODE => 8, ProductSaleElementsTableMap::CREATED_AT => 9, ProductSaleElementsTableMap::UPDATED_AT => 10, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'PRODUCT_ID' => 1, 'REF' => 2, 'QUANTITY' => 3, 'PROMO' => 4, 'NEWNESS' => 5, 'WEIGHT' => 6, 'IS_DEFAULT' => 7, 'EAN_CODE' => 8, 'CREATED_AT' => 9, 'UPDATED_AT' => 10, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'product_id' => 1, 'ref' => 2, 'quantity' => 3, 'promo' => 4, 'newness' => 5, 'weight' => 6, 'is_default' => 7, 'ean_code' => 8, 'created_at' => 9, 'updated_at' => 10, ),
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
        $this->setName('product_sale_elements');
        $this->setPhpName('ProductSaleElements');
        $this->setClassName('\\Thelia\\Model\\ProductSaleElements');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('PRODUCT_ID', 'ProductId', 'INTEGER', 'product', 'ID', true, null, null);
        $this->addColumn('REF', 'Ref', 'VARCHAR', true, 255, null);
        $this->addColumn('QUANTITY', 'Quantity', 'FLOAT', true, null, null);
        $this->addColumn('PROMO', 'Promo', 'TINYINT', false, null, 0);
        $this->addColumn('NEWNESS', 'Newness', 'TINYINT', false, null, 0);
        $this->addColumn('WEIGHT', 'Weight', 'FLOAT', false, null, 0);
        $this->addColumn('IS_DEFAULT', 'IsDefault', 'BOOLEAN', false, 1, false);
        $this->addColumn('EAN_CODE', 'EanCode', 'VARCHAR', false, 255, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Product', '\\Thelia\\Model\\Product', RelationMap::MANY_TO_ONE, array('product_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('AttributeCombination', '\\Thelia\\Model\\AttributeCombination', RelationMap::ONE_TO_MANY, array('id' => 'product_sale_elements_id', ), 'CASCADE', 'RESTRICT', 'AttributeCombinations');
        $this->addRelation('CartItem', '\\Thelia\\Model\\CartItem', RelationMap::ONE_TO_MANY, array('id' => 'product_sale_elements_id', ), 'CASCADE', 'RESTRICT', 'CartItems');
        $this->addRelation('ProductPrice', '\\Thelia\\Model\\ProductPrice', RelationMap::ONE_TO_MANY, array('id' => 'product_sale_elements_id', ), 'CASCADE', null, 'ProductPrices');
        $this->addRelation('ProductSaleElementsProductImage', '\\Thelia\\Model\\ProductSaleElementsProductImage', RelationMap::ONE_TO_MANY, array('id' => 'product_sale_elements_id', ), 'CASCADE', 'RESTRICT', 'ProductSaleElementsProductImages');
        $this->addRelation('ProductSaleElementsProductDocument', '\\Thelia\\Model\\ProductSaleElementsProductDocument', RelationMap::ONE_TO_MANY, array('id' => 'product_sale_elements_id', ), 'CASCADE', 'RESTRICT', 'ProductSaleElementsProductDocuments');
        $this->addRelation('ProductImage', '\\Thelia\\Model\\ProductImage', RelationMap::MANY_TO_MANY, array(), 'CASCADE', 'RESTRICT', 'ProductImages');
        $this->addRelation('ProductDocument', '\\Thelia\\Model\\ProductDocument', RelationMap::MANY_TO_MANY, array(), 'CASCADE', 'RESTRICT', 'ProductDocuments');
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
     * Method to invalidate the instance pool of all tables related to product_sale_elements     * by a foreign key with ON DELETE CASCADE
     */
    public static function clearRelatedInstancePool()
    {
        // Invalidate objects in ".$this->getClassNameFromBuilder($joinedTableTableMapBuilder)." instance pool,
        // since one or more of them may be deleted by ON DELETE CASCADE/SETNULL rule.
                AttributeCombinationTableMap::clearInstancePool();
                CartItemTableMap::clearInstancePool();
                ProductPriceTableMap::clearInstancePool();
                ProductSaleElementsProductImageTableMap::clearInstancePool();
                ProductSaleElementsProductDocumentTableMap::clearInstancePool();
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
        return $withPrefix ? ProductSaleElementsTableMap::CLASS_DEFAULT : ProductSaleElementsTableMap::OM_CLASS;
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
     * @return array (ProductSaleElements object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = ProductSaleElementsTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = ProductSaleElementsTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + ProductSaleElementsTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = ProductSaleElementsTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            ProductSaleElementsTableMap::addInstanceToPool($obj, $key);
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
            $key = ProductSaleElementsTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = ProductSaleElementsTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                ProductSaleElementsTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(ProductSaleElementsTableMap::ID);
            $criteria->addSelectColumn(ProductSaleElementsTableMap::PRODUCT_ID);
            $criteria->addSelectColumn(ProductSaleElementsTableMap::REF);
            $criteria->addSelectColumn(ProductSaleElementsTableMap::QUANTITY);
            $criteria->addSelectColumn(ProductSaleElementsTableMap::PROMO);
            $criteria->addSelectColumn(ProductSaleElementsTableMap::NEWNESS);
            $criteria->addSelectColumn(ProductSaleElementsTableMap::WEIGHT);
            $criteria->addSelectColumn(ProductSaleElementsTableMap::IS_DEFAULT);
            $criteria->addSelectColumn(ProductSaleElementsTableMap::EAN_CODE);
            $criteria->addSelectColumn(ProductSaleElementsTableMap::CREATED_AT);
            $criteria->addSelectColumn(ProductSaleElementsTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.PRODUCT_ID');
            $criteria->addSelectColumn($alias . '.REF');
            $criteria->addSelectColumn($alias . '.QUANTITY');
            $criteria->addSelectColumn($alias . '.PROMO');
            $criteria->addSelectColumn($alias . '.NEWNESS');
            $criteria->addSelectColumn($alias . '.WEIGHT');
            $criteria->addSelectColumn($alias . '.IS_DEFAULT');
            $criteria->addSelectColumn($alias . '.EAN_CODE');
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
        return Propel::getServiceContainer()->getDatabaseMap(ProductSaleElementsTableMap::DATABASE_NAME)->getTable(ProductSaleElementsTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(ProductSaleElementsTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(ProductSaleElementsTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new ProductSaleElementsTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a ProductSaleElements or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ProductSaleElements object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\ProductSaleElements) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(ProductSaleElementsTableMap::DATABASE_NAME);
            $criteria->add(ProductSaleElementsTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = ProductSaleElementsQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { ProductSaleElementsTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { ProductSaleElementsTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the product_sale_elements table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return ProductSaleElementsQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a ProductSaleElements or Criteria object.
     *
     * @param mixed               $criteria Criteria or ProductSaleElements object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ProductSaleElementsTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from ProductSaleElements object
        }

        if ($criteria->containsKey(ProductSaleElementsTableMap::ID) && $criteria->keyContainsValue(ProductSaleElementsTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.ProductSaleElementsTableMap::ID.')');
        }


        // Set the correct dbName
        $query = ProductSaleElementsQuery::create()->mergeWith($criteria);

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

} // ProductSaleElementsTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
ProductSaleElementsTableMap::buildTableMap();
