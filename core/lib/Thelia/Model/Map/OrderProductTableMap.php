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
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductQuery;


/**
 * This class defines the structure of the 'order_product' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class OrderProductTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.OrderProductTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'order_product';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\OrderProduct';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.OrderProduct';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 23;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 23;

    /**
     * the column name for the ID field
     */
    const ID = 'order_product.ID';

    /**
     * the column name for the ORDER_ID field
     */
    const ORDER_ID = 'order_product.ORDER_ID';

    /**
     * the column name for the PRODUCT_REF field
     */
    const PRODUCT_REF = 'order_product.PRODUCT_REF';

    /**
     * the column name for the PRODUCT_SALE_ELEMENTS_REF field
     */
    const PRODUCT_SALE_ELEMENTS_REF = 'order_product.PRODUCT_SALE_ELEMENTS_REF';

    /**
     * the column name for the PRODUCT_SALE_ELEMENTS_ID field
     */
    const PRODUCT_SALE_ELEMENTS_ID = 'order_product.PRODUCT_SALE_ELEMENTS_ID';

    /**
     * the column name for the TITLE field
     */
    const TITLE = 'order_product.TITLE';

    /**
     * the column name for the CHAPO field
     */
    const CHAPO = 'order_product.CHAPO';

    /**
     * the column name for the DESCRIPTION field
     */
    const DESCRIPTION = 'order_product.DESCRIPTION';

    /**
     * the column name for the POSTSCRIPTUM field
     */
    const POSTSCRIPTUM = 'order_product.POSTSCRIPTUM';

    /**
     * the column name for the QUANTITY field
     */
    const QUANTITY = 'order_product.QUANTITY';

    /**
     * the column name for the PRICE field
     */
    const PRICE = 'order_product.PRICE';

    /**
     * the column name for the PROMO_PRICE field
     */
    const PROMO_PRICE = 'order_product.PROMO_PRICE';

    /**
     * the column name for the WAS_NEW field
     */
    const WAS_NEW = 'order_product.WAS_NEW';

    /**
     * the column name for the WAS_IN_PROMO field
     */
    const WAS_IN_PROMO = 'order_product.WAS_IN_PROMO';

    /**
     * the column name for the WEIGHT field
     */
    const WEIGHT = 'order_product.WEIGHT';

    /**
     * the column name for the EAN_CODE field
     */
    const EAN_CODE = 'order_product.EAN_CODE';

    /**
     * the column name for the TAX_RULE_TITLE field
     */
    const TAX_RULE_TITLE = 'order_product.TAX_RULE_TITLE';

    /**
     * the column name for the TAX_RULE_DESCRIPTION field
     */
    const TAX_RULE_DESCRIPTION = 'order_product.TAX_RULE_DESCRIPTION';

    /**
     * the column name for the PARENT field
     */
    const PARENT = 'order_product.PARENT';

    /**
     * the column name for the VIRTUAL field
     */
    const VIRTUAL = 'order_product.VIRTUAL';

    /**
     * the column name for the VIRTUAL_DOCUMENT field
     */
    const VIRTUAL_DOCUMENT = 'order_product.VIRTUAL_DOCUMENT';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'order_product.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'order_product.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('Id', 'OrderId', 'ProductRef', 'ProductSaleElementsRef', 'ProductSaleElementsId', 'Title', 'Chapo', 'Description', 'Postscriptum', 'Quantity', 'Price', 'PromoPrice', 'WasNew', 'WasInPromo', 'Weight', 'EanCode', 'TaxRuleTitle', 'TaxRuleDescription', 'Parent', 'Virtual', 'VirtualDocument', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'orderId', 'productRef', 'productSaleElementsRef', 'productSaleElementsId', 'title', 'chapo', 'description', 'postscriptum', 'quantity', 'price', 'promoPrice', 'wasNew', 'wasInPromo', 'weight', 'eanCode', 'taxRuleTitle', 'taxRuleDescription', 'parent', 'virtual', 'virtualDocument', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(OrderProductTableMap::ID, OrderProductTableMap::ORDER_ID, OrderProductTableMap::PRODUCT_REF, OrderProductTableMap::PRODUCT_SALE_ELEMENTS_REF, OrderProductTableMap::PRODUCT_SALE_ELEMENTS_ID, OrderProductTableMap::TITLE, OrderProductTableMap::CHAPO, OrderProductTableMap::DESCRIPTION, OrderProductTableMap::POSTSCRIPTUM, OrderProductTableMap::QUANTITY, OrderProductTableMap::PRICE, OrderProductTableMap::PROMO_PRICE, OrderProductTableMap::WAS_NEW, OrderProductTableMap::WAS_IN_PROMO, OrderProductTableMap::WEIGHT, OrderProductTableMap::EAN_CODE, OrderProductTableMap::TAX_RULE_TITLE, OrderProductTableMap::TAX_RULE_DESCRIPTION, OrderProductTableMap::PARENT, OrderProductTableMap::VIRTUAL, OrderProductTableMap::VIRTUAL_DOCUMENT, OrderProductTableMap::CREATED_AT, OrderProductTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'ORDER_ID', 'PRODUCT_REF', 'PRODUCT_SALE_ELEMENTS_REF', 'PRODUCT_SALE_ELEMENTS_ID', 'TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM', 'QUANTITY', 'PRICE', 'PROMO_PRICE', 'WAS_NEW', 'WAS_IN_PROMO', 'WEIGHT', 'EAN_CODE', 'TAX_RULE_TITLE', 'TAX_RULE_DESCRIPTION', 'PARENT', 'VIRTUAL', 'VIRTUAL_DOCUMENT', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('id', 'order_id', 'product_ref', 'product_sale_elements_ref', 'product_sale_elements_id', 'title', 'chapo', 'description', 'postscriptum', 'quantity', 'price', 'promo_price', 'was_new', 'was_in_promo', 'weight', 'ean_code', 'tax_rule_title', 'tax_rule_description', 'parent', 'virtual', 'virtual_document', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'OrderId' => 1, 'ProductRef' => 2, 'ProductSaleElementsRef' => 3, 'ProductSaleElementsId' => 4, 'Title' => 5, 'Chapo' => 6, 'Description' => 7, 'Postscriptum' => 8, 'Quantity' => 9, 'Price' => 10, 'PromoPrice' => 11, 'WasNew' => 12, 'WasInPromo' => 13, 'Weight' => 14, 'EanCode' => 15, 'TaxRuleTitle' => 16, 'TaxRuleDescription' => 17, 'Parent' => 18, 'Virtual' => 19, 'VirtualDocument' => 20, 'CreatedAt' => 21, 'UpdatedAt' => 22, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'orderId' => 1, 'productRef' => 2, 'productSaleElementsRef' => 3, 'productSaleElementsId' => 4, 'title' => 5, 'chapo' => 6, 'description' => 7, 'postscriptum' => 8, 'quantity' => 9, 'price' => 10, 'promoPrice' => 11, 'wasNew' => 12, 'wasInPromo' => 13, 'weight' => 14, 'eanCode' => 15, 'taxRuleTitle' => 16, 'taxRuleDescription' => 17, 'parent' => 18, 'virtual' => 19, 'virtualDocument' => 20, 'createdAt' => 21, 'updatedAt' => 22, ),
        self::TYPE_COLNAME       => array(OrderProductTableMap::ID => 0, OrderProductTableMap::ORDER_ID => 1, OrderProductTableMap::PRODUCT_REF => 2, OrderProductTableMap::PRODUCT_SALE_ELEMENTS_REF => 3, OrderProductTableMap::PRODUCT_SALE_ELEMENTS_ID => 4, OrderProductTableMap::TITLE => 5, OrderProductTableMap::CHAPO => 6, OrderProductTableMap::DESCRIPTION => 7, OrderProductTableMap::POSTSCRIPTUM => 8, OrderProductTableMap::QUANTITY => 9, OrderProductTableMap::PRICE => 10, OrderProductTableMap::PROMO_PRICE => 11, OrderProductTableMap::WAS_NEW => 12, OrderProductTableMap::WAS_IN_PROMO => 13, OrderProductTableMap::WEIGHT => 14, OrderProductTableMap::EAN_CODE => 15, OrderProductTableMap::TAX_RULE_TITLE => 16, OrderProductTableMap::TAX_RULE_DESCRIPTION => 17, OrderProductTableMap::PARENT => 18, OrderProductTableMap::VIRTUAL => 19, OrderProductTableMap::VIRTUAL_DOCUMENT => 20, OrderProductTableMap::CREATED_AT => 21, OrderProductTableMap::UPDATED_AT => 22, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'ORDER_ID' => 1, 'PRODUCT_REF' => 2, 'PRODUCT_SALE_ELEMENTS_REF' => 3, 'PRODUCT_SALE_ELEMENTS_ID' => 4, 'TITLE' => 5, 'CHAPO' => 6, 'DESCRIPTION' => 7, 'POSTSCRIPTUM' => 8, 'QUANTITY' => 9, 'PRICE' => 10, 'PROMO_PRICE' => 11, 'WAS_NEW' => 12, 'WAS_IN_PROMO' => 13, 'WEIGHT' => 14, 'EAN_CODE' => 15, 'TAX_RULE_TITLE' => 16, 'TAX_RULE_DESCRIPTION' => 17, 'PARENT' => 18, 'VIRTUAL' => 19, 'VIRTUAL_DOCUMENT' => 20, 'CREATED_AT' => 21, 'UPDATED_AT' => 22, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'order_id' => 1, 'product_ref' => 2, 'product_sale_elements_ref' => 3, 'product_sale_elements_id' => 4, 'title' => 5, 'chapo' => 6, 'description' => 7, 'postscriptum' => 8, 'quantity' => 9, 'price' => 10, 'promo_price' => 11, 'was_new' => 12, 'was_in_promo' => 13, 'weight' => 14, 'ean_code' => 15, 'tax_rule_title' => 16, 'tax_rule_description' => 17, 'parent' => 18, 'virtual' => 19, 'virtual_document' => 20, 'created_at' => 21, 'updated_at' => 22, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, )
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
        $this->setName('order_product');
        $this->setPhpName('OrderProduct');
        $this->setClassName('\\Thelia\\Model\\OrderProduct');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('ORDER_ID', 'OrderId', 'INTEGER', 'order', 'ID', true, null, null);
        $this->addColumn('PRODUCT_REF', 'ProductRef', 'VARCHAR', true, 255, null);
        $this->addColumn('PRODUCT_SALE_ELEMENTS_REF', 'ProductSaleElementsRef', 'VARCHAR', true, 255, null);
        $this->addColumn('PRODUCT_SALE_ELEMENTS_ID', 'ProductSaleElementsId', 'INTEGER', false, null, null);
        $this->addColumn('TITLE', 'Title', 'VARCHAR', false, 255, null);
        $this->addColumn('CHAPO', 'Chapo', 'LONGVARCHAR', false, null, null);
        $this->addColumn('DESCRIPTION', 'Description', 'CLOB', false, null, null);
        $this->addColumn('POSTSCRIPTUM', 'Postscriptum', 'LONGVARCHAR', false, null, null);
        $this->addColumn('QUANTITY', 'Quantity', 'FLOAT', true, null, null);
        $this->addColumn('PRICE', 'Price', 'DECIMAL', true, 16, 0);
        $this->addColumn('PROMO_PRICE', 'PromoPrice', 'DECIMAL', false, 16, 0);
        $this->addColumn('WAS_NEW', 'WasNew', 'TINYINT', true, null, null);
        $this->addColumn('WAS_IN_PROMO', 'WasInPromo', 'TINYINT', true, null, null);
        $this->addColumn('WEIGHT', 'Weight', 'VARCHAR', false, 45, null);
        $this->addColumn('EAN_CODE', 'EanCode', 'VARCHAR', false, 255, null);
        $this->addColumn('TAX_RULE_TITLE', 'TaxRuleTitle', 'VARCHAR', false, 255, null);
        $this->addColumn('TAX_RULE_DESCRIPTION', 'TaxRuleDescription', 'CLOB', false, null, null);
        $this->addColumn('PARENT', 'Parent', 'INTEGER', false, null, null);
        $this->addColumn('VIRTUAL', 'Virtual', 'TINYINT', true, null, 0);
        $this->addColumn('VIRTUAL_DOCUMENT', 'VirtualDocument', 'VARCHAR', false, 255, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Order', '\\Thelia\\Model\\Order', RelationMap::MANY_TO_ONE, array('order_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('OrderProductAttributeCombination', '\\Thelia\\Model\\OrderProductAttributeCombination', RelationMap::ONE_TO_MANY, array('id' => 'order_product_id', ), 'CASCADE', 'RESTRICT', 'OrderProductAttributeCombinations');
        $this->addRelation('OrderProductTax', '\\Thelia\\Model\\OrderProductTax', RelationMap::ONE_TO_MANY, array('id' => 'order_product_id', ), 'CASCADE', 'RESTRICT', 'OrderProductTaxes');
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
     * Method to invalidate the instance pool of all tables related to order_product     * by a foreign key with ON DELETE CASCADE
     */
    public static function clearRelatedInstancePool()
    {
        // Invalidate objects in ".$this->getClassNameFromBuilder($joinedTableTableMapBuilder)." instance pool,
        // since one or more of them may be deleted by ON DELETE CASCADE/SETNULL rule.
                OrderProductAttributeCombinationTableMap::clearInstancePool();
                OrderProductTaxTableMap::clearInstancePool();
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
        return $withPrefix ? OrderProductTableMap::CLASS_DEFAULT : OrderProductTableMap::OM_CLASS;
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
     * @return array (OrderProduct object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = OrderProductTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = OrderProductTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + OrderProductTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = OrderProductTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            OrderProductTableMap::addInstanceToPool($obj, $key);
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
            $key = OrderProductTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = OrderProductTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                OrderProductTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(OrderProductTableMap::ID);
            $criteria->addSelectColumn(OrderProductTableMap::ORDER_ID);
            $criteria->addSelectColumn(OrderProductTableMap::PRODUCT_REF);
            $criteria->addSelectColumn(OrderProductTableMap::PRODUCT_SALE_ELEMENTS_REF);
            $criteria->addSelectColumn(OrderProductTableMap::PRODUCT_SALE_ELEMENTS_ID);
            $criteria->addSelectColumn(OrderProductTableMap::TITLE);
            $criteria->addSelectColumn(OrderProductTableMap::CHAPO);
            $criteria->addSelectColumn(OrderProductTableMap::DESCRIPTION);
            $criteria->addSelectColumn(OrderProductTableMap::POSTSCRIPTUM);
            $criteria->addSelectColumn(OrderProductTableMap::QUANTITY);
            $criteria->addSelectColumn(OrderProductTableMap::PRICE);
            $criteria->addSelectColumn(OrderProductTableMap::PROMO_PRICE);
            $criteria->addSelectColumn(OrderProductTableMap::WAS_NEW);
            $criteria->addSelectColumn(OrderProductTableMap::WAS_IN_PROMO);
            $criteria->addSelectColumn(OrderProductTableMap::WEIGHT);
            $criteria->addSelectColumn(OrderProductTableMap::EAN_CODE);
            $criteria->addSelectColumn(OrderProductTableMap::TAX_RULE_TITLE);
            $criteria->addSelectColumn(OrderProductTableMap::TAX_RULE_DESCRIPTION);
            $criteria->addSelectColumn(OrderProductTableMap::PARENT);
            $criteria->addSelectColumn(OrderProductTableMap::VIRTUAL);
            $criteria->addSelectColumn(OrderProductTableMap::VIRTUAL_DOCUMENT);
            $criteria->addSelectColumn(OrderProductTableMap::CREATED_AT);
            $criteria->addSelectColumn(OrderProductTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.ORDER_ID');
            $criteria->addSelectColumn($alias . '.PRODUCT_REF');
            $criteria->addSelectColumn($alias . '.PRODUCT_SALE_ELEMENTS_REF');
            $criteria->addSelectColumn($alias . '.PRODUCT_SALE_ELEMENTS_ID');
            $criteria->addSelectColumn($alias . '.TITLE');
            $criteria->addSelectColumn($alias . '.CHAPO');
            $criteria->addSelectColumn($alias . '.DESCRIPTION');
            $criteria->addSelectColumn($alias . '.POSTSCRIPTUM');
            $criteria->addSelectColumn($alias . '.QUANTITY');
            $criteria->addSelectColumn($alias . '.PRICE');
            $criteria->addSelectColumn($alias . '.PROMO_PRICE');
            $criteria->addSelectColumn($alias . '.WAS_NEW');
            $criteria->addSelectColumn($alias . '.WAS_IN_PROMO');
            $criteria->addSelectColumn($alias . '.WEIGHT');
            $criteria->addSelectColumn($alias . '.EAN_CODE');
            $criteria->addSelectColumn($alias . '.TAX_RULE_TITLE');
            $criteria->addSelectColumn($alias . '.TAX_RULE_DESCRIPTION');
            $criteria->addSelectColumn($alias . '.PARENT');
            $criteria->addSelectColumn($alias . '.VIRTUAL');
            $criteria->addSelectColumn($alias . '.VIRTUAL_DOCUMENT');
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
        return Propel::getServiceContainer()->getDatabaseMap(OrderProductTableMap::DATABASE_NAME)->getTable(OrderProductTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(OrderProductTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(OrderProductTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new OrderProductTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a OrderProduct or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or OrderProduct object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(OrderProductTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\OrderProduct) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(OrderProductTableMap::DATABASE_NAME);
            $criteria->add(OrderProductTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = OrderProductQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { OrderProductTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { OrderProductTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the order_product table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return OrderProductQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a OrderProduct or Criteria object.
     *
     * @param mixed               $criteria Criteria or OrderProduct object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(OrderProductTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from OrderProduct object
        }

        if ($criteria->containsKey(OrderProductTableMap::ID) && $criteria->keyContainsValue(OrderProductTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.OrderProductTableMap::ID.')');
        }


        // Set the correct dbName
        $query = OrderProductQuery::create()->mergeWith($criteria);

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

} // OrderProductTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
OrderProductTableMap::buildTableMap();
