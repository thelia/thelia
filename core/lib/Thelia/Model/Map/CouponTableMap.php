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
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;


/**
 * This class defines the structure of the 'coupon' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class CouponTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.CouponTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'coupon';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\Coupon';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.Coupon';

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
    const ID = 'coupon.ID';

    /**
     * the column name for the CODE field
     */
    const CODE = 'coupon.CODE';

    /**
     * the column name for the TYPE field
     */
    const TYPE = 'coupon.TYPE';

    /**
     * the column name for the SERIALIZED_EFFECTS field
     */
    const SERIALIZED_EFFECTS = 'coupon.SERIALIZED_EFFECTS';

    /**
     * the column name for the IS_ENABLED field
     */
    const IS_ENABLED = 'coupon.IS_ENABLED';

    /**
     * the column name for the START_DATE field
     */
    const START_DATE = 'coupon.START_DATE';

    /**
     * the column name for the EXPIRATION_DATE field
     */
    const EXPIRATION_DATE = 'coupon.EXPIRATION_DATE';

    /**
     * the column name for the MAX_USAGE field
     */
    const MAX_USAGE = 'coupon.MAX_USAGE';

    /**
     * the column name for the IS_CUMULATIVE field
     */
    const IS_CUMULATIVE = 'coupon.IS_CUMULATIVE';

    /**
     * the column name for the IS_REMOVING_POSTAGE field
     */
    const IS_REMOVING_POSTAGE = 'coupon.IS_REMOVING_POSTAGE';

    /**
     * the column name for the IS_AVAILABLE_ON_SPECIAL_OFFERS field
     */
    const IS_AVAILABLE_ON_SPECIAL_OFFERS = 'coupon.IS_AVAILABLE_ON_SPECIAL_OFFERS';

    /**
     * the column name for the IS_USED field
     */
    const IS_USED = 'coupon.IS_USED';

    /**
     * the column name for the SERIALIZED_CONDITIONS field
     */
    const SERIALIZED_CONDITIONS = 'coupon.SERIALIZED_CONDITIONS';

    /**
     * the column name for the PER_CUSTOMER_USAGE_COUNT field
     */
    const PER_CUSTOMER_USAGE_COUNT = 'coupon.PER_CUSTOMER_USAGE_COUNT';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'coupon.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'coupon.UPDATED_AT';

    /**
     * the column name for the VERSION field
     */
    const VERSION = 'coupon.VERSION';

    /**
     * the column name for the VERSION_CREATED_AT field
     */
    const VERSION_CREATED_AT = 'coupon.VERSION_CREATED_AT';

    /**
     * the column name for the VERSION_CREATED_BY field
     */
    const VERSION_CREATED_BY = 'coupon.VERSION_CREATED_BY';

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
        self::TYPE_PHPNAME       => array('Id', 'Code', 'Type', 'SerializedEffects', 'IsEnabled', 'StartDate', 'ExpirationDate', 'MaxUsage', 'IsCumulative', 'IsRemovingPostage', 'IsAvailableOnSpecialOffers', 'IsUsed', 'SerializedConditions', 'PerCustomerUsageCount', 'CreatedAt', 'UpdatedAt', 'Version', 'VersionCreatedAt', 'VersionCreatedBy', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'code', 'type', 'serializedEffects', 'isEnabled', 'startDate', 'expirationDate', 'maxUsage', 'isCumulative', 'isRemovingPostage', 'isAvailableOnSpecialOffers', 'isUsed', 'serializedConditions', 'perCustomerUsageCount', 'createdAt', 'updatedAt', 'version', 'versionCreatedAt', 'versionCreatedBy', ),
        self::TYPE_COLNAME       => array(CouponTableMap::ID, CouponTableMap::CODE, CouponTableMap::TYPE, CouponTableMap::SERIALIZED_EFFECTS, CouponTableMap::IS_ENABLED, CouponTableMap::START_DATE, CouponTableMap::EXPIRATION_DATE, CouponTableMap::MAX_USAGE, CouponTableMap::IS_CUMULATIVE, CouponTableMap::IS_REMOVING_POSTAGE, CouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS, CouponTableMap::IS_USED, CouponTableMap::SERIALIZED_CONDITIONS, CouponTableMap::PER_CUSTOMER_USAGE_COUNT, CouponTableMap::CREATED_AT, CouponTableMap::UPDATED_AT, CouponTableMap::VERSION, CouponTableMap::VERSION_CREATED_AT, CouponTableMap::VERSION_CREATED_BY, ),
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
        self::TYPE_COLNAME       => array(CouponTableMap::ID => 0, CouponTableMap::CODE => 1, CouponTableMap::TYPE => 2, CouponTableMap::SERIALIZED_EFFECTS => 3, CouponTableMap::IS_ENABLED => 4, CouponTableMap::START_DATE => 5, CouponTableMap::EXPIRATION_DATE => 6, CouponTableMap::MAX_USAGE => 7, CouponTableMap::IS_CUMULATIVE => 8, CouponTableMap::IS_REMOVING_POSTAGE => 9, CouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS => 10, CouponTableMap::IS_USED => 11, CouponTableMap::SERIALIZED_CONDITIONS => 12, CouponTableMap::PER_CUSTOMER_USAGE_COUNT => 13, CouponTableMap::CREATED_AT => 14, CouponTableMap::UPDATED_AT => 15, CouponTableMap::VERSION => 16, CouponTableMap::VERSION_CREATED_AT => 17, CouponTableMap::VERSION_CREATED_BY => 18, ),
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
        $this->setName('coupon');
        $this->setPhpName('Coupon');
        $this->setClassName('\\Thelia\\Model\\Coupon');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
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
        $this->addColumn('VERSION', 'Version', 'INTEGER', false, null, 0);
        $this->addColumn('VERSION_CREATED_AT', 'VersionCreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('VERSION_CREATED_BY', 'VersionCreatedBy', 'VARCHAR', false, 100, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('CouponCountry', '\\Thelia\\Model\\CouponCountry', RelationMap::ONE_TO_MANY, array('id' => 'coupon_id', ), 'CASCADE', null, 'CouponCountries');
        $this->addRelation('CouponModule', '\\Thelia\\Model\\CouponModule', RelationMap::ONE_TO_MANY, array('id' => 'coupon_id', ), 'CASCADE', null, 'CouponModules');
        $this->addRelation('CouponCustomerCount', '\\Thelia\\Model\\CouponCustomerCount', RelationMap::ONE_TO_MANY, array('id' => 'coupon_id', ), 'CASCADE', 'RESTRICT', 'CouponCustomerCounts');
        $this->addRelation('CouponI18n', '\\Thelia\\Model\\CouponI18n', RelationMap::ONE_TO_MANY, array('id' => 'id', ), 'CASCADE', null, 'CouponI18ns');
        $this->addRelation('CouponVersion', '\\Thelia\\Model\\CouponVersion', RelationMap::ONE_TO_MANY, array('id' => 'id', ), 'CASCADE', null, 'CouponVersions');
        $this->addRelation('Country', '\\Thelia\\Model\\Country', RelationMap::MANY_TO_MANY, array(), 'CASCADE', null, 'Countries');
        $this->addRelation('Module', '\\Thelia\\Model\\Module', RelationMap::MANY_TO_MANY, array(), 'CASCADE', null, 'Modules');
        $this->addRelation('Customer', '\\Thelia\\Model\\Customer', RelationMap::MANY_TO_MANY, array(), 'CASCADE', 'RESTRICT', 'Customers');
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
            'i18n' => array('i18n_table' => '%TABLE%_i18n', 'i18n_phpname' => '%PHPNAME%I18n', 'i18n_columns' => 'title, short_description, description', 'locale_column' => 'locale', 'locale_length' => '5', 'default_locale' => '', 'locale_alias' => '', ),
            'versionable' => array('version_column' => 'version', 'version_table' => '', 'log_created_at' => 'true', 'log_created_by' => 'true', 'log_comment' => 'false', 'version_created_at_column' => 'version_created_at', 'version_created_by_column' => 'version_created_by', 'version_comment_column' => 'version_comment', ),
        );
    } // getBehaviors()
    /**
     * Method to invalidate the instance pool of all tables related to coupon     * by a foreign key with ON DELETE CASCADE
     */
    public static function clearRelatedInstancePool()
    {
        // Invalidate objects in ".$this->getClassNameFromBuilder($joinedTableTableMapBuilder)." instance pool,
        // since one or more of them may be deleted by ON DELETE CASCADE/SETNULL rule.
                CouponCountryTableMap::clearInstancePool();
                CouponModuleTableMap::clearInstancePool();
                CouponCustomerCountTableMap::clearInstancePool();
                CouponI18nTableMap::clearInstancePool();
                CouponVersionTableMap::clearInstancePool();
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
        return $withPrefix ? CouponTableMap::CLASS_DEFAULT : CouponTableMap::OM_CLASS;
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
     * @return array (Coupon object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = CouponTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = CouponTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + CouponTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = CouponTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            CouponTableMap::addInstanceToPool($obj, $key);
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
            $key = CouponTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = CouponTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                CouponTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(CouponTableMap::ID);
            $criteria->addSelectColumn(CouponTableMap::CODE);
            $criteria->addSelectColumn(CouponTableMap::TYPE);
            $criteria->addSelectColumn(CouponTableMap::SERIALIZED_EFFECTS);
            $criteria->addSelectColumn(CouponTableMap::IS_ENABLED);
            $criteria->addSelectColumn(CouponTableMap::START_DATE);
            $criteria->addSelectColumn(CouponTableMap::EXPIRATION_DATE);
            $criteria->addSelectColumn(CouponTableMap::MAX_USAGE);
            $criteria->addSelectColumn(CouponTableMap::IS_CUMULATIVE);
            $criteria->addSelectColumn(CouponTableMap::IS_REMOVING_POSTAGE);
            $criteria->addSelectColumn(CouponTableMap::IS_AVAILABLE_ON_SPECIAL_OFFERS);
            $criteria->addSelectColumn(CouponTableMap::IS_USED);
            $criteria->addSelectColumn(CouponTableMap::SERIALIZED_CONDITIONS);
            $criteria->addSelectColumn(CouponTableMap::PER_CUSTOMER_USAGE_COUNT);
            $criteria->addSelectColumn(CouponTableMap::CREATED_AT);
            $criteria->addSelectColumn(CouponTableMap::UPDATED_AT);
            $criteria->addSelectColumn(CouponTableMap::VERSION);
            $criteria->addSelectColumn(CouponTableMap::VERSION_CREATED_AT);
            $criteria->addSelectColumn(CouponTableMap::VERSION_CREATED_BY);
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
        return Propel::getServiceContainer()->getDatabaseMap(CouponTableMap::DATABASE_NAME)->getTable(CouponTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(CouponTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(CouponTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new CouponTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a Coupon or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Coupon object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(CouponTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\Coupon) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(CouponTableMap::DATABASE_NAME);
            $criteria->add(CouponTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = CouponQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { CouponTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { CouponTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the coupon table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return CouponQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Coupon or Criteria object.
     *
     * @param mixed               $criteria Criteria or Coupon object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CouponTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Coupon object
        }

        if ($criteria->containsKey(CouponTableMap::ID) && $criteria->keyContainsValue(CouponTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.CouponTableMap::ID.')');
        }


        // Set the correct dbName
        $query = CouponQuery::create()->mergeWith($criteria);

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

} // CouponTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
CouponTableMap::buildTableMap();
