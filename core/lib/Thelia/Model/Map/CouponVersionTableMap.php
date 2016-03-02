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
use Thelia\Model\CouponVersion;
use Thelia\Model\CouponVersionQuery;


/**
 * This class defines the structure of the 'coupon_version' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class CouponVersionTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.CouponVersionTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'coupon_version';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\CouponVersion';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.CouponVersion';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 19;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 19;

    /**
     * the column name for the ID field
     */
    const ID = 'coupon_version.ID';

    /**
     * the column name for the CODE field
     */
    const CODE = 'coupon_version.CODE';

    /**
     * the column name for the TYPE field
     */
    const TYPE = 'coupon_version.TYPE';

    /**
     * the column name for the SERIALIZED_EFFECTS field
     */
    const SERIALIZED_EFFECTS = 'coupon_version.SERIALIZED_EFFECTS';

    /**
     * the column name for the IS_ENABLED field
     */
    const IS_ENABLED = 'coupon_version.IS_ENABLED';

    /**
     * the column name for the START_DATE field
     */
    const START_DATE = 'coupon_version.START_DATE';

    /**
     * the column name for the EXPIRATION_DATE field
     */
    const EXPIRATION_DATE = 'coupon_version.EXPIRATION_DATE';

    /**
     * the column name for the MAX_USAGE field
     */
    const MAX_USAGE = 'coupon_version.MAX_USAGE';

    /**
     * the column name for the IS_CUMULATIVE field
     */
    const IS_CUMULATIVE = 'coupon_version.IS_CUMULATIVE';

    /**
     * the column name for the IS_REMOVING_POSTAGE field
     */
    const IS_REMOVING_POSTAGE = 'coupon_version.IS_REMOVING_POSTAGE';

    /**
     * the column name for the IS_AVAILABLE_ON_SPECIAL_OFFERS field
     */
    const IS_AVAILABLE_ON_SPECIAL_OFFERS = 'coupon_version.IS_AVAILABLE_ON_SPECIAL_OFFERS';

    /**
     * the column name for the IS_USED field
     */
    const IS_USED = 'coupon_version.IS_USED';

    /**
     * the column name for the SERIALIZED_CONDITIONS field
     */
    const SERIALIZED_CONDITIONS = 'coupon_version.SERIALIZED_CONDITIONS';

    /**
     * the column name for the PER_CUSTOMER_USAGE_COUNT field
     */
    const PER_CUSTOMER_USAGE_COUNT = 'coupon_version.PER_CUSTOMER_USAGE_COUNT';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'coupon_version.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'coupon_version.UPDATED_AT';

    /**
     * the column name for the VERSION field
     */
    const VERSION = 'coupon_version.VERSION';

    /**
     * the column name for the VERSION_CREATED_AT field
     */
    const VERSION_CREATED_AT = 'coupon_version.VERSION_CREATED_AT';

    /**
     * the column name for the VERSION_CREATED_BY field
     */
    const VERSION_CREATED_BY = 'coupon_version.VERSION_CREATED_BY';

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
        self::TYPE_PHPNAME       => array('Id', 'Code', 'Type', 'SerializedEffects', 'IsEnabled', 'StartDate', 'ExpirationDate', 'MaxUsage', 'IsCumulative', 'IsRemovingPostage', 'IsAvailableOnSpecialOffers', 'IsUsed', 'SerializedConditions', 'PerCustomerUsageCount', 'CreatedAt', 'UpdatedAt', 'Version', 'VersionCreatedAt', 'VersionCreatedBy', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'code', 'type', 'serializedEffects', 'isEnabled', 'startDate', 'expirationDate', 'maxUsage', 'isCumulative', 'isRemovingPostage', 'isAvailableOnSpecialOffers', 'isUsed', 'serializedConditions', 'perCustomerUsageCount', 'createdAt', 'updatedAt', 'version', 'versionCreatedAt', 'versionCreatedBy', ),
        self::TYPE_COLNAME       => array(CouponVersionTableMap::ID, CouponVersionTableMap::CODE, CouponVersionTableMap::TYPE, CouponVersionTableMap::SERIALIZED_EFFECTS, CouponVersionTableMap::IS_ENABLED, CouponVersionTableMap::START_DATE, CouponVersionTableMap::EXPIRATION_DATE, CouponVersionTableMap::MAX_USAGE, CouponVersionTableMap::IS_CUMULATIVE, CouponVersionTableMap::IS_REMOVING_POSTAGE, CouponVersionTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS, CouponVersionTableMap::IS_USED, CouponVersionTableMap::SERIALIZED_CONDITIONS, CouponVersionTableMap::PER_CUSTOMER_USAGE_COUNT, CouponVersionTableMap::CREATED_AT, CouponVersionTableMap::UPDATED_AT, CouponVersionTableMap::VERSION, CouponVersionTableMap::VERSION_CREATED_AT, CouponVersionTableMap::VERSION_CREATED_BY, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'CODE', 'TYPE', 'SERIALIZED_EFFECTS', 'IS_ENABLED', 'START_DATE', 'EXPIRATION_DATE', 'MAX_USAGE', 'IS_CUMULATIVE', 'IS_REMOVING_POSTAGE', 'IS_AVAILABLE_ON_SPECIAL_OFFERS', 'IS_USED', 'SERIALIZED_CONDITIONS', 'PER_CUSTOMER_USAGE_COUNT', 'CREATED_AT', 'UPDATED_AT', 'VERSION', 'VERSION_CREATED_AT', 'VERSION_CREATED_BY', ),
        self::TYPE_FIELDNAME     => array('id', 'code', 'type', 'serialized_effects', 'is_enabled', 'start_date', 'expiration_date', 'max_usage', 'is_cumulative', 'is_removing_postage', 'is_available_on_special_offers', 'is_used', 'serialized_conditions', 'per_customer_usage_count', 'created_at', 'updated_at', 'version', 'version_created_at', 'version_created_by', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'Code' => 1, 'Type' => 2, 'SerializedEffects' => 3, 'IsEnabled' => 4, 'StartDate' => 5, 'ExpirationDate' => 6, 'MaxUsage' => 7, 'IsCumulative' => 8, 'IsRemovingPostage' => 9, 'IsAvailableOnSpecialOffers' => 10, 'IsUsed' => 11, 'SerializedConditions' => 12, 'PerCustomerUsageCount' => 13, 'CreatedAt' => 14, 'UpdatedAt' => 15, 'Version' => 16, 'VersionCreatedAt' => 17, 'VersionCreatedBy' => 18, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'code' => 1, 'type' => 2, 'serializedEffects' => 3, 'isEnabled' => 4, 'startDate' => 5, 'expirationDate' => 6, 'maxUsage' => 7, 'isCumulative' => 8, 'isRemovingPostage' => 9, 'isAvailableOnSpecialOffers' => 10, 'isUsed' => 11, 'serializedConditions' => 12, 'perCustomerUsageCount' => 13, 'createdAt' => 14, 'updatedAt' => 15, 'version' => 16, 'versionCreatedAt' => 17, 'versionCreatedBy' => 18, ),
        self::TYPE_COLNAME       => array(CouponVersionTableMap::ID => 0, CouponVersionTableMap::CODE => 1, CouponVersionTableMap::TYPE => 2, CouponVersionTableMap::SERIALIZED_EFFECTS => 3, CouponVersionTableMap::IS_ENABLED => 4, CouponVersionTableMap::START_DATE => 5, CouponVersionTableMap::EXPIRATION_DATE => 6, CouponVersionTableMap::MAX_USAGE => 7, CouponVersionTableMap::IS_CUMULATIVE => 8, CouponVersionTableMap::IS_REMOVING_POSTAGE => 9, CouponVersionTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS => 10, CouponVersionTableMap::IS_USED => 11, CouponVersionTableMap::SERIALIZED_CONDITIONS => 12, CouponVersionTableMap::PER_CUSTOMER_USAGE_COUNT => 13, CouponVersionTableMap::CREATED_AT => 14, CouponVersionTableMap::UPDATED_AT => 15, CouponVersionTableMap::VERSION => 16, CouponVersionTableMap::VERSION_CREATED_AT => 17, CouponVersionTableMap::VERSION_CREATED_BY => 18, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'CODE' => 1, 'TYPE' => 2, 'SERIALIZED_EFFECTS' => 3, 'IS_ENABLED' => 4, 'START_DATE' => 5, 'EXPIRATION_DATE' => 6, 'MAX_USAGE' => 7, 'IS_CUMULATIVE' => 8, 'IS_REMOVING_POSTAGE' => 9, 'IS_AVAILABLE_ON_SPECIAL_OFFERS' => 10, 'IS_USED' => 11, 'SERIALIZED_CONDITIONS' => 12, 'PER_CUSTOMER_USAGE_COUNT' => 13, 'CREATED_AT' => 14, 'UPDATED_AT' => 15, 'VERSION' => 16, 'VERSION_CREATED_AT' => 17, 'VERSION_CREATED_BY' => 18, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'code' => 1, 'type' => 2, 'serialized_effects' => 3, 'is_enabled' => 4, 'start_date' => 5, 'expiration_date' => 6, 'max_usage' => 7, 'is_cumulative' => 8, 'is_removing_postage' => 9, 'is_available_on_special_offers' => 10, 'is_used' => 11, 'serialized_conditions' => 12, 'per_customer_usage_count' => 13, 'created_at' => 14, 'updated_at' => 15, 'version' => 16, 'version_created_at' => 17, 'version_created_by' => 18, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, )
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
        $this->setName('coupon_version');
        $this->setPhpName('CouponVersion');
        $this->setClassName('\\Thelia\\Model\\CouponVersion');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(false);
        // columns
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'coupon', 'ID', true, null, null);
        $this->addColumn('CODE', 'Code', 'VARCHAR', true, 45, null);
        $this->addColumn('TYPE', 'Type', 'VARCHAR', true, 255, null);
        $this->addColumn('SERIALIZED_EFFECTS', 'SerializedEffects', 'CLOB', true, null, null);
        $this->addColumn('IS_ENABLED', 'IsEnabled', 'BOOLEAN', true, 1, null);
        $this->addColumn('START_DATE', 'StartDate', 'TIMESTAMP', false, null, null);
        $this->addColumn('EXPIRATION_DATE', 'ExpirationDate', 'TIMESTAMP', false, null, null);
        $this->addColumn('MAX_USAGE', 'MaxUsage', 'INTEGER', true, null, null);
        $this->addColumn('IS_CUMULATIVE', 'IsCumulative', 'BOOLEAN', true, 1, null);
        $this->addColumn('IS_REMOVING_POSTAGE', 'IsRemovingPostage', 'BOOLEAN', true, 1, null);
        $this->addColumn('IS_AVAILABLE_ON_SPECIAL_OFFERS', 'IsAvailableOnSpecialOffers', 'BOOLEAN', true, 1, null);
        $this->addColumn('IS_USED', 'IsUsed', 'BOOLEAN', true, 1, null);
        $this->addColumn('SERIALIZED_CONDITIONS', 'SerializedConditions', 'LONGVARCHAR', true, null, null);
        $this->addColumn('PER_CUSTOMER_USAGE_COUNT', 'PerCustomerUsageCount', 'BOOLEAN', true, 1, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
        $this->addPrimaryKey('VERSION', 'Version', 'INTEGER', true, null, 0);
        $this->addColumn('VERSION_CREATED_AT', 'VersionCreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('VERSION_CREATED_BY', 'VersionCreatedBy', 'VARCHAR', false, 100, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Coupon', '\\Thelia\\Model\\Coupon', RelationMap::MANY_TO_ONE, array('id' => 'id', ), 'CASCADE', null);
    } // buildRelations()

    /**
     * Adds an object to the instance pool.
     *
     * Propel keeps cached copies of objects in an instance pool when they are retrieved
     * from the database. In some cases you may need to explicitly add objects
     * to the cache in order to ensure that the same objects are always returned by find*()
     * and findPk*() calls.
     *
     * @param \Thelia\Model\CouponVersion $obj A \Thelia\Model\CouponVersion object.
     * @param string $key             (optional) key to use for instance map (for performance boost if key was already calculated externally).
     */
    public static function addInstanceToPool($obj, $key = null)
    {
        if (Propel::isInstancePoolingEnabled()) {
            if (null === $key) {
                $key = serialize(array((string) $obj->getId(), (string) $obj->getVersion()));
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
     * @param mixed $value A \Thelia\Model\CouponVersion object or a primary key value.
     */
    public static function removeInstanceFromPool($value)
    {
        if (Propel::isInstancePoolingEnabled() && null !== $value) {
            if (is_object($value) && $value instanceof \Thelia\Model\CouponVersion) {
                $key = serialize(array((string) $value->getId(), (string) $value->getVersion()));

            } elseif (is_array($value) && count($value) === 2) {
                // assume we've been passed a primary key";
                $key = serialize(array((string) $value[0], (string) $value[1]));
            } elseif ($value instanceof Criteria) {
                self::$instances = [];

                return;
            } else {
                $e = new PropelException("Invalid value passed to removeInstanceFromPool().  Expected primary key or \Thelia\Model\CouponVersion object; got " . (is_object($value) ? get_class($value) . ' object.' : var_export($value, true)));
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
        if ($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)] === null && $row[TableMap::TYPE_NUM == $indexType ? 16 + $offset : static::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)] === null) {
            return null;
        }

        return serialize(array((string) $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)], (string) $row[TableMap::TYPE_NUM == $indexType ? 16 + $offset : static::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)]));
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
        return $withPrefix ? CouponVersionTableMap::CLASS_DEFAULT : CouponVersionTableMap::OM_CLASS;
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
     * @return array (CouponVersion object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = CouponVersionTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = CouponVersionTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + CouponVersionTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = CouponVersionTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            CouponVersionTableMap::addInstanceToPool($obj, $key);
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
            $key = CouponVersionTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = CouponVersionTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                CouponVersionTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(CouponVersionTableMap::ID);
            $criteria->addSelectColumn(CouponVersionTableMap::CODE);
            $criteria->addSelectColumn(CouponVersionTableMap::TYPE);
            $criteria->addSelectColumn(CouponVersionTableMap::SERIALIZED_EFFECTS);
            $criteria->addSelectColumn(CouponVersionTableMap::IS_ENABLED);
            $criteria->addSelectColumn(CouponVersionTableMap::START_DATE);
            $criteria->addSelectColumn(CouponVersionTableMap::EXPIRATION_DATE);
            $criteria->addSelectColumn(CouponVersionTableMap::MAX_USAGE);
            $criteria->addSelectColumn(CouponVersionTableMap::IS_CUMULATIVE);
            $criteria->addSelectColumn(CouponVersionTableMap::IS_REMOVING_POSTAGE);
            $criteria->addSelectColumn(CouponVersionTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS);
            $criteria->addSelectColumn(CouponVersionTableMap::IS_USED);
            $criteria->addSelectColumn(CouponVersionTableMap::SERIALIZED_CONDITIONS);
            $criteria->addSelectColumn(CouponVersionTableMap::PER_CUSTOMER_USAGE_COUNT);
            $criteria->addSelectColumn(CouponVersionTableMap::CREATED_AT);
            $criteria->addSelectColumn(CouponVersionTableMap::UPDATED_AT);
            $criteria->addSelectColumn(CouponVersionTableMap::VERSION);
            $criteria->addSelectColumn(CouponVersionTableMap::VERSION_CREATED_AT);
            $criteria->addSelectColumn(CouponVersionTableMap::VERSION_CREATED_BY);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.CODE');
            $criteria->addSelectColumn($alias . '.TYPE');
            $criteria->addSelectColumn($alias . '.SERIALIZED_EFFECTS');
            $criteria->addSelectColumn($alias . '.IS_ENABLED');
            $criteria->addSelectColumn($alias . '.START_DATE');
            $criteria->addSelectColumn($alias . '.EXPIRATION_DATE');
            $criteria->addSelectColumn($alias . '.MAX_USAGE');
            $criteria->addSelectColumn($alias . '.IS_CUMULATIVE');
            $criteria->addSelectColumn($alias . '.IS_REMOVING_POSTAGE');
            $criteria->addSelectColumn($alias . '.IS_AVAILABLE_ON_SPECIAL_OFFERS');
            $criteria->addSelectColumn($alias . '.IS_USED');
            $criteria->addSelectColumn($alias . '.SERIALIZED_CONDITIONS');
            $criteria->addSelectColumn($alias . '.PER_CUSTOMER_USAGE_COUNT');
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
        return Propel::getServiceContainer()->getDatabaseMap(CouponVersionTableMap::DATABASE_NAME)->getTable(CouponVersionTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(CouponVersionTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(CouponVersionTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new CouponVersionTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a CouponVersion or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or CouponVersion object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(CouponVersionTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\CouponVersion) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(CouponVersionTableMap::DATABASE_NAME);
            // primary key is composite; we therefore, expect
            // the primary key passed to be an array of pkey values
            if (count($values) == count($values, COUNT_RECURSIVE)) {
                // array is not multi-dimensional
                $values = array($values);
            }
            foreach ($values as $value) {
                $criterion = $criteria->getNewCriterion(CouponVersionTableMap::ID, $value[0]);
                $criterion->addAnd($criteria->getNewCriterion(CouponVersionTableMap::VERSION, $value[1]));
                $criteria->addOr($criterion);
            }
        }

        $query = CouponVersionQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { CouponVersionTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { CouponVersionTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the coupon_version table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return CouponVersionQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a CouponVersion or Criteria object.
     *
     * @param mixed               $criteria Criteria or CouponVersion object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CouponVersionTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from CouponVersion object
        }


        // Set the correct dbName
        $query = CouponVersionQuery::create()->mergeWith($criteria);

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

} // CouponVersionTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
CouponVersionTableMap::buildTableMap();
