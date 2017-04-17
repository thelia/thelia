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
use Thelia\Model\CustomerVersion;
use Thelia\Model\CustomerVersionQuery;


/**
 * This class defines the structure of the 'customer_version' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class CustomerVersionTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.CustomerVersionTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'customer_version';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\CustomerVersion';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.CustomerVersion';

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
    const ID = 'customer_version.ID';

    /**
     * the column name for the TITLE_ID field
     */
    const TITLE_ID = 'customer_version.TITLE_ID';

    /**
     * the column name for the LANG_ID field
     */
    const LANG_ID = 'customer_version.LANG_ID';

    /**
     * the column name for the REF field
     */
    const REF = 'customer_version.REF';

    /**
     * the column name for the FIRSTNAME field
     */
    const FIRSTNAME = 'customer_version.FIRSTNAME';

    /**
     * the column name for the LASTNAME field
     */
    const LASTNAME = 'customer_version.LASTNAME';

    /**
     * the column name for the EMAIL field
     */
    const EMAIL = 'customer_version.EMAIL';

    /**
     * the column name for the PASSWORD field
     */
    const PASSWORD = 'customer_version.PASSWORD';

    /**
     * the column name for the ALGO field
     */
    const ALGO = 'customer_version.ALGO';

    /**
     * the column name for the RESELLER field
     */
    const RESELLER = 'customer_version.RESELLER';

    /**
     * the column name for the SPONSOR field
     */
    const SPONSOR = 'customer_version.SPONSOR';

    /**
     * the column name for the DISCOUNT field
     */
    const DISCOUNT = 'customer_version.DISCOUNT';

    /**
     * the column name for the REMEMBER_ME_TOKEN field
     */
    const REMEMBER_ME_TOKEN = 'customer_version.REMEMBER_ME_TOKEN';

    /**
     * the column name for the REMEMBER_ME_SERIAL field
     */
    const REMEMBER_ME_SERIAL = 'customer_version.REMEMBER_ME_SERIAL';

    /**
     * the column name for the ENABLE field
     */
    const ENABLE = 'customer_version.ENABLE';

    /**
     * the column name for the CONFIRMATION_TOKEN field
     */
    const CONFIRMATION_TOKEN = 'customer_version.CONFIRMATION_TOKEN';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'customer_version.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'customer_version.UPDATED_AT';

    /**
     * the column name for the VERSION field
     */
    const VERSION = 'customer_version.VERSION';

    /**
     * the column name for the VERSION_CREATED_AT field
     */
    const VERSION_CREATED_AT = 'customer_version.VERSION_CREATED_AT';

    /**
     * the column name for the VERSION_CREATED_BY field
     */
    const VERSION_CREATED_BY = 'customer_version.VERSION_CREATED_BY';

    /**
     * the column name for the ORDER_IDS field
     */
    const ORDER_IDS = 'customer_version.ORDER_IDS';

    /**
     * the column name for the ORDER_VERSIONS field
     */
    const ORDER_VERSIONS = 'customer_version.ORDER_VERSIONS';

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
        self::TYPE_PHPNAME       => array('Id', 'TitleId', 'LangId', 'Ref', 'Firstname', 'Lastname', 'Email', 'Password', 'Algo', 'Reseller', 'Sponsor', 'Discount', 'RememberMeToken', 'RememberMeSerial', 'Enable', 'ConfirmationToken', 'CreatedAt', 'UpdatedAt', 'Version', 'VersionCreatedAt', 'VersionCreatedBy', 'OrderIds', 'OrderVersions', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'titleId', 'langId', 'ref', 'firstname', 'lastname', 'email', 'password', 'algo', 'reseller', 'sponsor', 'discount', 'rememberMeToken', 'rememberMeSerial', 'enable', 'confirmationToken', 'createdAt', 'updatedAt', 'version', 'versionCreatedAt', 'versionCreatedBy', 'orderIds', 'orderVersions', ),
        self::TYPE_COLNAME       => array(CustomerVersionTableMap::ID, CustomerVersionTableMap::TITLE_ID, CustomerVersionTableMap::LANG_ID, CustomerVersionTableMap::REF, CustomerVersionTableMap::FIRSTNAME, CustomerVersionTableMap::LASTNAME, CustomerVersionTableMap::EMAIL, CustomerVersionTableMap::PASSWORD, CustomerVersionTableMap::ALGO, CustomerVersionTableMap::RESELLER, CustomerVersionTableMap::SPONSOR, CustomerVersionTableMap::DISCOUNT, CustomerVersionTableMap::REMEMBER_ME_TOKEN, CustomerVersionTableMap::REMEMBER_ME_SERIAL, CustomerVersionTableMap::ENABLE, CustomerVersionTableMap::CONFIRMATION_TOKEN, CustomerVersionTableMap::CREATED_AT, CustomerVersionTableMap::UPDATED_AT, CustomerVersionTableMap::VERSION, CustomerVersionTableMap::VERSION_CREATED_AT, CustomerVersionTableMap::VERSION_CREATED_BY, CustomerVersionTableMap::ORDER_IDS, CustomerVersionTableMap::ORDER_VERSIONS, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'TITLE_ID', 'LANG_ID', 'REF', 'FIRSTNAME', 'LASTNAME', 'EMAIL', 'PASSWORD', 'ALGO', 'RESELLER', 'SPONSOR', 'DISCOUNT', 'REMEMBER_ME_TOKEN', 'REMEMBER_ME_SERIAL', 'ENABLE', 'CONFIRMATION_TOKEN', 'CREATED_AT', 'UPDATED_AT', 'VERSION', 'VERSION_CREATED_AT', 'VERSION_CREATED_BY', 'ORDER_IDS', 'ORDER_VERSIONS', ),
        self::TYPE_FIELDNAME     => array('id', 'title_id', 'lang_id', 'ref', 'firstname', 'lastname', 'email', 'password', 'algo', 'reseller', 'sponsor', 'discount', 'remember_me_token', 'remember_me_serial', 'enable', 'confirmation_token', 'created_at', 'updated_at', 'version', 'version_created_at', 'version_created_by', 'order_ids', 'order_versions', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'TitleId' => 1, 'LangId' => 2, 'Ref' => 3, 'Firstname' => 4, 'Lastname' => 5, 'Email' => 6, 'Password' => 7, 'Algo' => 8, 'Reseller' => 9, 'Sponsor' => 10, 'Discount' => 11, 'RememberMeToken' => 12, 'RememberMeSerial' => 13, 'Enable' => 14, 'ConfirmationToken' => 15, 'CreatedAt' => 16, 'UpdatedAt' => 17, 'Version' => 18, 'VersionCreatedAt' => 19, 'VersionCreatedBy' => 20, 'OrderIds' => 21, 'OrderVersions' => 22, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'titleId' => 1, 'langId' => 2, 'ref' => 3, 'firstname' => 4, 'lastname' => 5, 'email' => 6, 'password' => 7, 'algo' => 8, 'reseller' => 9, 'sponsor' => 10, 'discount' => 11, 'rememberMeToken' => 12, 'rememberMeSerial' => 13, 'enable' => 14, 'confirmationToken' => 15, 'createdAt' => 16, 'updatedAt' => 17, 'version' => 18, 'versionCreatedAt' => 19, 'versionCreatedBy' => 20, 'orderIds' => 21, 'orderVersions' => 22, ),
        self::TYPE_COLNAME       => array(CustomerVersionTableMap::ID => 0, CustomerVersionTableMap::TITLE_ID => 1, CustomerVersionTableMap::LANG_ID => 2, CustomerVersionTableMap::REF => 3, CustomerVersionTableMap::FIRSTNAME => 4, CustomerVersionTableMap::LASTNAME => 5, CustomerVersionTableMap::EMAIL => 6, CustomerVersionTableMap::PASSWORD => 7, CustomerVersionTableMap::ALGO => 8, CustomerVersionTableMap::RESELLER => 9, CustomerVersionTableMap::SPONSOR => 10, CustomerVersionTableMap::DISCOUNT => 11, CustomerVersionTableMap::REMEMBER_ME_TOKEN => 12, CustomerVersionTableMap::REMEMBER_ME_SERIAL => 13, CustomerVersionTableMap::ENABLE => 14, CustomerVersionTableMap::CONFIRMATION_TOKEN => 15, CustomerVersionTableMap::CREATED_AT => 16, CustomerVersionTableMap::UPDATED_AT => 17, CustomerVersionTableMap::VERSION => 18, CustomerVersionTableMap::VERSION_CREATED_AT => 19, CustomerVersionTableMap::VERSION_CREATED_BY => 20, CustomerVersionTableMap::ORDER_IDS => 21, CustomerVersionTableMap::ORDER_VERSIONS => 22, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'TITLE_ID' => 1, 'LANG_ID' => 2, 'REF' => 3, 'FIRSTNAME' => 4, 'LASTNAME' => 5, 'EMAIL' => 6, 'PASSWORD' => 7, 'ALGO' => 8, 'RESELLER' => 9, 'SPONSOR' => 10, 'DISCOUNT' => 11, 'REMEMBER_ME_TOKEN' => 12, 'REMEMBER_ME_SERIAL' => 13, 'ENABLE' => 14, 'CONFIRMATION_TOKEN' => 15, 'CREATED_AT' => 16, 'UPDATED_AT' => 17, 'VERSION' => 18, 'VERSION_CREATED_AT' => 19, 'VERSION_CREATED_BY' => 20, 'ORDER_IDS' => 21, 'ORDER_VERSIONS' => 22, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'title_id' => 1, 'lang_id' => 2, 'ref' => 3, 'firstname' => 4, 'lastname' => 5, 'email' => 6, 'password' => 7, 'algo' => 8, 'reseller' => 9, 'sponsor' => 10, 'discount' => 11, 'remember_me_token' => 12, 'remember_me_serial' => 13, 'enable' => 14, 'confirmation_token' => 15, 'created_at' => 16, 'updated_at' => 17, 'version' => 18, 'version_created_at' => 19, 'version_created_by' => 20, 'order_ids' => 21, 'order_versions' => 22, ),
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
        $this->setName('customer_version');
        $this->setPhpName('CustomerVersion');
        $this->setClassName('\\Thelia\\Model\\CustomerVersion');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(false);
        // columns
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'customer', 'ID', true, null, null);
        $this->addColumn('TITLE_ID', 'TitleId', 'INTEGER', true, null, null);
        $this->addColumn('LANG_ID', 'LangId', 'INTEGER', false, null, null);
        $this->addColumn('REF', 'Ref', 'VARCHAR', false, 50, null);
        $this->addColumn('FIRSTNAME', 'Firstname', 'VARCHAR', true, 255, null);
        $this->addColumn('LASTNAME', 'Lastname', 'VARCHAR', true, 255, null);
        $this->addColumn('EMAIL', 'Email', 'VARCHAR', false, 255, null);
        $this->addColumn('PASSWORD', 'Password', 'VARCHAR', false, 255, null);
        $this->addColumn('ALGO', 'Algo', 'VARCHAR', false, 128, null);
        $this->addColumn('RESELLER', 'Reseller', 'TINYINT', false, null, null);
        $this->addColumn('SPONSOR', 'Sponsor', 'VARCHAR', false, 50, null);
        $this->addColumn('DISCOUNT', 'Discount', 'DECIMAL', false, 16, 0);
        $this->addColumn('REMEMBER_ME_TOKEN', 'RememberMeToken', 'VARCHAR', false, 255, null);
        $this->addColumn('REMEMBER_ME_SERIAL', 'RememberMeSerial', 'VARCHAR', false, 255, null);
        $this->addColumn('ENABLE', 'Enable', 'TINYINT', false, null, 0);
        $this->addColumn('CONFIRMATION_TOKEN', 'ConfirmationToken', 'VARCHAR', false, 255, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
        $this->addPrimaryKey('VERSION', 'Version', 'INTEGER', true, null, 0);
        $this->addColumn('VERSION_CREATED_AT', 'VersionCreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('VERSION_CREATED_BY', 'VersionCreatedBy', 'VARCHAR', false, 100, null);
        $this->addColumn('ORDER_IDS', 'OrderIds', 'ARRAY', false, null, null);
        $this->addColumn('ORDER_VERSIONS', 'OrderVersions', 'ARRAY', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Customer', '\\Thelia\\Model\\Customer', RelationMap::MANY_TO_ONE, array('id' => 'id', ), 'CASCADE', null);
    } // buildRelations()

    /**
     * Adds an object to the instance pool.
     *
     * Propel keeps cached copies of objects in an instance pool when they are retrieved
     * from the database. In some cases you may need to explicitly add objects
     * to the cache in order to ensure that the same objects are always returned by find*()
     * and findPk*() calls.
     *
     * @param \Thelia\Model\CustomerVersion $obj A \Thelia\Model\CustomerVersion object.
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
     * @param mixed $value A \Thelia\Model\CustomerVersion object or a primary key value.
     */
    public static function removeInstanceFromPool($value)
    {
        if (Propel::isInstancePoolingEnabled() && null !== $value) {
            if (is_object($value) && $value instanceof \Thelia\Model\CustomerVersion) {
                $key = serialize(array((string) $value->getId(), (string) $value->getVersion()));

            } elseif (is_array($value) && count($value) === 2) {
                // assume we've been passed a primary key";
                $key = serialize(array((string) $value[0], (string) $value[1]));
            } elseif ($value instanceof Criteria) {
                self::$instances = [];

                return;
            } else {
                $e = new PropelException("Invalid value passed to removeInstanceFromPool().  Expected primary key or \Thelia\Model\CustomerVersion object; got " . (is_object($value) ? get_class($value) . ' object.' : var_export($value, true)));
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
        if ($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)] === null && $row[TableMap::TYPE_NUM == $indexType ? 18 + $offset : static::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)] === null) {
            return null;
        }

        return serialize(array((string) $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)], (string) $row[TableMap::TYPE_NUM == $indexType ? 18 + $offset : static::translateFieldName('Version', TableMap::TYPE_PHPNAME, $indexType)]));
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
        return $withPrefix ? CustomerVersionTableMap::CLASS_DEFAULT : CustomerVersionTableMap::OM_CLASS;
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
     * @return array (CustomerVersion object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = CustomerVersionTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = CustomerVersionTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + CustomerVersionTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = CustomerVersionTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            CustomerVersionTableMap::addInstanceToPool($obj, $key);
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
            $key = CustomerVersionTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = CustomerVersionTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                CustomerVersionTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(CustomerVersionTableMap::ID);
            $criteria->addSelectColumn(CustomerVersionTableMap::TITLE_ID);
            $criteria->addSelectColumn(CustomerVersionTableMap::LANG_ID);
            $criteria->addSelectColumn(CustomerVersionTableMap::REF);
            $criteria->addSelectColumn(CustomerVersionTableMap::FIRSTNAME);
            $criteria->addSelectColumn(CustomerVersionTableMap::LASTNAME);
            $criteria->addSelectColumn(CustomerVersionTableMap::EMAIL);
            $criteria->addSelectColumn(CustomerVersionTableMap::PASSWORD);
            $criteria->addSelectColumn(CustomerVersionTableMap::ALGO);
            $criteria->addSelectColumn(CustomerVersionTableMap::RESELLER);
            $criteria->addSelectColumn(CustomerVersionTableMap::SPONSOR);
            $criteria->addSelectColumn(CustomerVersionTableMap::DISCOUNT);
            $criteria->addSelectColumn(CustomerVersionTableMap::REMEMBER_ME_TOKEN);
            $criteria->addSelectColumn(CustomerVersionTableMap::REMEMBER_ME_SERIAL);
            $criteria->addSelectColumn(CustomerVersionTableMap::ENABLE);
            $criteria->addSelectColumn(CustomerVersionTableMap::CONFIRMATION_TOKEN);
            $criteria->addSelectColumn(CustomerVersionTableMap::CREATED_AT);
            $criteria->addSelectColumn(CustomerVersionTableMap::UPDATED_AT);
            $criteria->addSelectColumn(CustomerVersionTableMap::VERSION);
            $criteria->addSelectColumn(CustomerVersionTableMap::VERSION_CREATED_AT);
            $criteria->addSelectColumn(CustomerVersionTableMap::VERSION_CREATED_BY);
            $criteria->addSelectColumn(CustomerVersionTableMap::ORDER_IDS);
            $criteria->addSelectColumn(CustomerVersionTableMap::ORDER_VERSIONS);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.TITLE_ID');
            $criteria->addSelectColumn($alias . '.LANG_ID');
            $criteria->addSelectColumn($alias . '.REF');
            $criteria->addSelectColumn($alias . '.FIRSTNAME');
            $criteria->addSelectColumn($alias . '.LASTNAME');
            $criteria->addSelectColumn($alias . '.EMAIL');
            $criteria->addSelectColumn($alias . '.PASSWORD');
            $criteria->addSelectColumn($alias . '.ALGO');
            $criteria->addSelectColumn($alias . '.RESELLER');
            $criteria->addSelectColumn($alias . '.SPONSOR');
            $criteria->addSelectColumn($alias . '.DISCOUNT');
            $criteria->addSelectColumn($alias . '.REMEMBER_ME_TOKEN');
            $criteria->addSelectColumn($alias . '.REMEMBER_ME_SERIAL');
            $criteria->addSelectColumn($alias . '.ENABLE');
            $criteria->addSelectColumn($alias . '.CONFIRMATION_TOKEN');
            $criteria->addSelectColumn($alias . '.CREATED_AT');
            $criteria->addSelectColumn($alias . '.UPDATED_AT');
            $criteria->addSelectColumn($alias . '.VERSION');
            $criteria->addSelectColumn($alias . '.VERSION_CREATED_AT');
            $criteria->addSelectColumn($alias . '.VERSION_CREATED_BY');
            $criteria->addSelectColumn($alias . '.ORDER_IDS');
            $criteria->addSelectColumn($alias . '.ORDER_VERSIONS');
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
        return Propel::getServiceContainer()->getDatabaseMap(CustomerVersionTableMap::DATABASE_NAME)->getTable(CustomerVersionTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(CustomerVersionTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(CustomerVersionTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new CustomerVersionTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a CustomerVersion or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or CustomerVersion object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(CustomerVersionTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\CustomerVersion) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(CustomerVersionTableMap::DATABASE_NAME);
            // primary key is composite; we therefore, expect
            // the primary key passed to be an array of pkey values
            if (count($values) == count($values, COUNT_RECURSIVE)) {
                // array is not multi-dimensional
                $values = array($values);
            }
            foreach ($values as $value) {
                $criterion = $criteria->getNewCriterion(CustomerVersionTableMap::ID, $value[0]);
                $criterion->addAnd($criteria->getNewCriterion(CustomerVersionTableMap::VERSION, $value[1]));
                $criteria->addOr($criterion);
            }
        }

        $query = CustomerVersionQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { CustomerVersionTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { CustomerVersionTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the customer_version table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return CustomerVersionQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a CustomerVersion or Criteria object.
     *
     * @param mixed               $criteria Criteria or CustomerVersion object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CustomerVersionTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from CustomerVersion object
        }


        // Set the correct dbName
        $query = CustomerVersionQuery::create()->mergeWith($criteria);

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

} // CustomerVersionTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
CustomerVersionTableMap::buildTableMap();
