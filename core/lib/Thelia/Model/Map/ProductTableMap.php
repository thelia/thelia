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
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;


/**
 * This class defines the structure of the 'product' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class ProductTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.ProductTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'product';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\Product';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.Product';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 17;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 17;

    /**
     * the column name for the ID field
     */
    const ID = 'product.ID';

    /**
     * the column name for the TAX_RULE_ID field
     */
    const TAX_RULE_ID = 'product.TAX_RULE_ID';

    /**
     * the column name for the REF field
     */
    const REF = 'product.REF';

    /**
     * the column name for the PRICE field
     */
    const PRICE = 'product.PRICE';

    /**
     * the column name for the PRICE2 field
     */
    const PRICE2 = 'product.PRICE2';

    /**
     * the column name for the ECOTAX field
     */
    const ECOTAX = 'product.ECOTAX';

    /**
     * the column name for the NEWNESS field
     */
    const NEWNESS = 'product.NEWNESS';

    /**
     * the column name for the PROMO field
     */
    const PROMO = 'product.PROMO';

    /**
     * the column name for the QUANTITY field
     */
    const QUANTITY = 'product.QUANTITY';

    /**
     * the column name for the VISIBLE field
     */
    const VISIBLE = 'product.VISIBLE';

    /**
     * the column name for the WEIGHT field
     */
    const WEIGHT = 'product.WEIGHT';

    /**
     * the column name for the POSITION field
     */
    const POSITION = 'product.POSITION';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'product.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'product.UPDATED_AT';

    /**
     * the column name for the VERSION field
     */
    const VERSION = 'product.VERSION';

    /**
     * the column name for the VERSION_CREATED_AT field
     */
    const VERSION_CREATED_AT = 'product.VERSION_CREATED_AT';

    /**
     * the column name for the VERSION_CREATED_BY field
     */
    const VERSION_CREATED_BY = 'product.VERSION_CREATED_BY';

    /**
     * The default string format for model objects of the related table
     */
    const DEFAULT_STRING_FORMAT = 'YAML';

    // i18n behavior

    /**
     * The default locale to use for translations.
     *
     * @var string
     */
    const DEFAULT_LOCALE = 'en_US';

    /**
     * holds an array of fieldnames
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldNames[self::TYPE_PHPNAME][0] = 'Id'
     */
    protected static $fieldNames = array (
        self::TYPE_PHPNAME       => array('Id', 'TaxRuleId', 'Ref', 'Price', 'Price2', 'Ecotax', 'Newness', 'Promo', 'Quantity', 'Visible', 'Weight', 'Position', 'CreatedAt', 'UpdatedAt', 'Version', 'VersionCreatedAt', 'VersionCreatedBy', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'taxRuleId', 'ref', 'price', 'price2', 'ecotax', 'newness', 'promo', 'quantity', 'visible', 'weight', 'position', 'createdAt', 'updatedAt', 'version', 'versionCreatedAt', 'versionCreatedBy', ),
        self::TYPE_COLNAME       => array(ProductTableMap::ID, ProductTableMap::TAX_RULE_ID, ProductTableMap::REF, ProductTableMap::PRICE, ProductTableMap::PRICE2, ProductTableMap::ECOTAX, ProductTableMap::NEWNESS, ProductTableMap::PROMO, ProductTableMap::QUANTITY, ProductTableMap::VISIBLE, ProductTableMap::WEIGHT, ProductTableMap::POSITION, ProductTableMap::CREATED_AT, ProductTableMap::UPDATED_AT, ProductTableMap::VERSION, ProductTableMap::VERSION_CREATED_AT, ProductTableMap::VERSION_CREATED_BY, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'TAX_RULE_ID', 'REF', 'PRICE', 'PRICE2', 'ECOTAX', 'NEWNESS', 'PROMO', 'QUANTITY', 'VISIBLE', 'WEIGHT', 'POSITION', 'CREATED_AT', 'UPDATED_AT', 'VERSION', 'VERSION_CREATED_AT', 'VERSION_CREATED_BY', ),
        self::TYPE_FIELDNAME     => array('id', 'tax_rule_id', 'ref', 'price', 'price2', 'ecotax', 'newness', 'promo', 'quantity', 'visible', 'weight', 'position', 'created_at', 'updated_at', 'version', 'version_created_at', 'version_created_by', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'TaxRuleId' => 1, 'Ref' => 2, 'Price' => 3, 'Price2' => 4, 'Ecotax' => 5, 'Newness' => 6, 'Promo' => 7, 'Quantity' => 8, 'Visible' => 9, 'Weight' => 10, 'Position' => 11, 'CreatedAt' => 12, 'UpdatedAt' => 13, 'Version' => 14, 'VersionCreatedAt' => 15, 'VersionCreatedBy' => 16, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'taxRuleId' => 1, 'ref' => 2, 'price' => 3, 'price2' => 4, 'ecotax' => 5, 'newness' => 6, 'promo' => 7, 'quantity' => 8, 'visible' => 9, 'weight' => 10, 'position' => 11, 'createdAt' => 12, 'updatedAt' => 13, 'version' => 14, 'versionCreatedAt' => 15, 'versionCreatedBy' => 16, ),
        self::TYPE_COLNAME       => array(ProductTableMap::ID => 0, ProductTableMap::TAX_RULE_ID => 1, ProductTableMap::REF => 2, ProductTableMap::PRICE => 3, ProductTableMap::PRICE2 => 4, ProductTableMap::ECOTAX => 5, ProductTableMap::NEWNESS => 6, ProductTableMap::PROMO => 7, ProductTableMap::QUANTITY => 8, ProductTableMap::VISIBLE => 9, ProductTableMap::WEIGHT => 10, ProductTableMap::POSITION => 11, ProductTableMap::CREATED_AT => 12, ProductTableMap::UPDATED_AT => 13, ProductTableMap::VERSION => 14, ProductTableMap::VERSION_CREATED_AT => 15, ProductTableMap::VERSION_CREATED_BY => 16, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'TAX_RULE_ID' => 1, 'REF' => 2, 'PRICE' => 3, 'PRICE2' => 4, 'ECOTAX' => 5, 'NEWNESS' => 6, 'PROMO' => 7, 'QUANTITY' => 8, 'VISIBLE' => 9, 'WEIGHT' => 10, 'POSITION' => 11, 'CREATED_AT' => 12, 'UPDATED_AT' => 13, 'VERSION' => 14, 'VERSION_CREATED_AT' => 15, 'VERSION_CREATED_BY' => 16, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'tax_rule_id' => 1, 'ref' => 2, 'price' => 3, 'price2' => 4, 'ecotax' => 5, 'newness' => 6, 'promo' => 7, 'quantity' => 8, 'visible' => 9, 'weight' => 10, 'position' => 11, 'created_at' => 12, 'updated_at' => 13, 'version' => 14, 'version_created_at' => 15, 'version_created_by' => 16, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, )
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
        $this->setName('product');
        $this->setPhpName('Product');
        $this->setClassName('\\Thelia\\Model\\Product');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('TAX_RULE_ID', 'TaxRuleId', 'INTEGER', 'tax_rule', 'ID', false, null, null);
        $this->addColumn('REF', 'Ref', 'VARCHAR', true, 255, null);
        $this->addColumn('PRICE', 'Price', 'FLOAT', true, null, null);
        $this->addColumn('PRICE2', 'Price2', 'FLOAT', false, null, null);
        $this->addColumn('ECOTAX', 'Ecotax', 'FLOAT', false, null, null);
        $this->addColumn('NEWNESS', 'Newness', 'TINYINT', false, null, 0);
        $this->addColumn('PROMO', 'Promo', 'TINYINT', false, null, 0);
        $this->addColumn('QUANTITY', 'Quantity', 'INTEGER', false, null, 0);
        $this->addColumn('VISIBLE', 'Visible', 'TINYINT', true, null, 0);
        $this->addColumn('WEIGHT', 'Weight', 'FLOAT', false, null, null);
        $this->addColumn('POSITION', 'Position', 'INTEGER', true, null, null);
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
        $this->addRelation('TaxRule', '\\Thelia\\Model\\TaxRule', RelationMap::MANY_TO_ONE, array('tax_rule_id' => 'id', ), 'SET NULL', 'RESTRICT');
        $this->addRelation('ProductCategory', '\\Thelia\\Model\\ProductCategory', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'ProductCategories');
        $this->addRelation('FeatureProd', '\\Thelia\\Model\\FeatureProd', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'FeatureProds');
        $this->addRelation('Stock', '\\Thelia\\Model\\Stock', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'Stocks');
        $this->addRelation('ContentAssoc', '\\Thelia\\Model\\ContentAssoc', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'ContentAssocs');
        $this->addRelation('Image', '\\Thelia\\Model\\Image', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'Images');
        $this->addRelation('Document', '\\Thelia\\Model\\Document', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'Documents');
        $this->addRelation('AccessoryRelatedByProductId', '\\Thelia\\Model\\Accessory', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'AccessoriesRelatedByProductId');
        $this->addRelation('AccessoryRelatedByAccessory', '\\Thelia\\Model\\Accessory', RelationMap::ONE_TO_MANY, array('id' => 'accessory', ), 'CASCADE', 'RESTRICT', 'AccessoriesRelatedByAccessory');
        $this->addRelation('Rewriting', '\\Thelia\\Model\\Rewriting', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'Rewritings');
        $this->addRelation('ProductI18n', '\\Thelia\\Model\\ProductI18n', RelationMap::ONE_TO_MANY, array('id' => 'id', ), 'CASCADE', null, 'ProductI18ns');
        $this->addRelation('ProductVersion', '\\Thelia\\Model\\ProductVersion', RelationMap::ONE_TO_MANY, array('id' => 'id', ), 'CASCADE', null, 'ProductVersions');
        $this->addRelation('Category', '\\Thelia\\Model\\Category', RelationMap::MANY_TO_MANY, array(), 'CASCADE', 'RESTRICT', 'Categories');
        $this->addRelation('ProductRelatedByAccessory', '\\Thelia\\Model\\Product', RelationMap::MANY_TO_MANY, array(), 'CASCADE', 'RESTRICT', 'ProductsRelatedByAccessory');
        $this->addRelation('ProductRelatedByProductId', '\\Thelia\\Model\\Product', RelationMap::MANY_TO_MANY, array(), 'CASCADE', 'RESTRICT', 'ProductsRelatedByProductId');
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
            'i18n' => array('i18n_table' => '%TABLE%_i18n', 'i18n_phpname' => '%PHPNAME%I18n', 'i18n_columns' => 'title, description, chapo, postscriptum', 'locale_column' => 'locale', 'locale_length' => '5', 'default_locale' => '', 'locale_alias' => '', ),
            'versionable' => array('version_column' => 'version', 'version_table' => '', 'log_created_at' => 'true', 'log_created_by' => 'true', 'log_comment' => 'false', 'version_created_at_column' => 'version_created_at', 'version_created_by_column' => 'version_created_by', 'version_comment_column' => 'version_comment', ),
        );
    } // getBehaviors()
    /**
     * Method to invalidate the instance pool of all tables related to product     * by a foreign key with ON DELETE CASCADE
     */
    public static function clearRelatedInstancePool()
    {
        // Invalidate objects in ".$this->getClassNameFromBuilder($joinedTableTableMapBuilder)." instance pool,
        // since one or more of them may be deleted by ON DELETE CASCADE/SETNULL rule.
                ProductCategoryTableMap::clearInstancePool();
                FeatureProdTableMap::clearInstancePool();
                StockTableMap::clearInstancePool();
                ContentAssocTableMap::clearInstancePool();
                ImageTableMap::clearInstancePool();
                DocumentTableMap::clearInstancePool();
                AccessoryTableMap::clearInstancePool();
                RewritingTableMap::clearInstancePool();
                ProductI18nTableMap::clearInstancePool();
                ProductVersionTableMap::clearInstancePool();
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
        return $withPrefix ? ProductTableMap::CLASS_DEFAULT : ProductTableMap::OM_CLASS;
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
     * @return array (Product object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = ProductTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = ProductTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + ProductTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = ProductTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            ProductTableMap::addInstanceToPool($obj, $key);
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
            $key = ProductTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = ProductTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                ProductTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(ProductTableMap::ID);
            $criteria->addSelectColumn(ProductTableMap::TAX_RULE_ID);
            $criteria->addSelectColumn(ProductTableMap::REF);
            $criteria->addSelectColumn(ProductTableMap::PRICE);
            $criteria->addSelectColumn(ProductTableMap::PRICE2);
            $criteria->addSelectColumn(ProductTableMap::ECOTAX);
            $criteria->addSelectColumn(ProductTableMap::NEWNESS);
            $criteria->addSelectColumn(ProductTableMap::PROMO);
            $criteria->addSelectColumn(ProductTableMap::QUANTITY);
            $criteria->addSelectColumn(ProductTableMap::VISIBLE);
            $criteria->addSelectColumn(ProductTableMap::WEIGHT);
            $criteria->addSelectColumn(ProductTableMap::POSITION);
            $criteria->addSelectColumn(ProductTableMap::CREATED_AT);
            $criteria->addSelectColumn(ProductTableMap::UPDATED_AT);
            $criteria->addSelectColumn(ProductTableMap::VERSION);
            $criteria->addSelectColumn(ProductTableMap::VERSION_CREATED_AT);
            $criteria->addSelectColumn(ProductTableMap::VERSION_CREATED_BY);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.TAX_RULE_ID');
            $criteria->addSelectColumn($alias . '.REF');
            $criteria->addSelectColumn($alias . '.PRICE');
            $criteria->addSelectColumn($alias . '.PRICE2');
            $criteria->addSelectColumn($alias . '.ECOTAX');
            $criteria->addSelectColumn($alias . '.NEWNESS');
            $criteria->addSelectColumn($alias . '.PROMO');
            $criteria->addSelectColumn($alias . '.QUANTITY');
            $criteria->addSelectColumn($alias . '.VISIBLE');
            $criteria->addSelectColumn($alias . '.WEIGHT');
            $criteria->addSelectColumn($alias . '.POSITION');
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
        return Propel::getServiceContainer()->getDatabaseMap(ProductTableMap::DATABASE_NAME)->getTable(ProductTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(ProductTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(ProductTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new ProductTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a Product or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Product object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(ProductTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\Product) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(ProductTableMap::DATABASE_NAME);
            $criteria->add(ProductTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = ProductQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { ProductTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { ProductTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the product table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return ProductQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Product or Criteria object.
     *
     * @param mixed               $criteria Criteria or Product object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ProductTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Product object
        }

        if ($criteria->containsKey(ProductTableMap::ID) && $criteria->keyContainsValue(ProductTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.ProductTableMap::ID.')');
        }


        // Set the correct dbName
        $query = ProductQuery::create()->mergeWith($criteria);

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

} // ProductTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
ProductTableMap::buildTableMap();
