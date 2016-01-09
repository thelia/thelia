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
use Thelia\Model\Admin;
use Thelia\Model\AdminQuery;


/**
 * This class defines the structure of the 'admin' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class AdminTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.AdminTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'admin';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\Admin';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.Admin';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 15;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 15;

    /**
     * the column name for the ID field
     */
    const ID = 'admin.ID';

    /**
     * the column name for the PROFILE_ID field
     */
    const PROFILE_ID = 'admin.PROFILE_ID';

    /**
     * the column name for the FIRSTNAME field
     */
    const FIRSTNAME = 'admin.FIRSTNAME';

    /**
     * the column name for the LASTNAME field
     */
    const LASTNAME = 'admin.LASTNAME';

    /**
     * the column name for the LOGIN field
     */
    const LOGIN = 'admin.LOGIN';

    /**
     * the column name for the PASSWORD field
     */
    const PASSWORD = 'admin.PASSWORD';

    /**
     * the column name for the LOCALE field
     */
    const LOCALE = 'admin.LOCALE';

    /**
     * the column name for the ALGO field
     */
    const ALGO = 'admin.ALGO';

    /**
     * the column name for the SALT field
     */
    const SALT = 'admin.SALT';

    /**
     * the column name for the REMEMBER_ME_TOKEN field
     */
    const REMEMBER_ME_TOKEN = 'admin.REMEMBER_ME_TOKEN';

    /**
     * the column name for the REMEMBER_ME_SERIAL field
     */
    const REMEMBER_ME_SERIAL = 'admin.REMEMBER_ME_SERIAL';

    /**
     * the column name for the EMAIL field
     */
    const EMAIL = 'admin.EMAIL';

    /**
     * the column name for the PASSWORD_RENEW_TOKEN field
     */
    const PASSWORD_RENEW_TOKEN = 'admin.PASSWORD_RENEW_TOKEN';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'admin.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'admin.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('Id', 'ProfileId', 'Firstname', 'Lastname', 'Login', 'Password', 'Locale', 'Algo', 'Salt', 'RememberMeToken', 'RememberMeSerial', 'Email', 'PasswordRenewToken', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'profileId', 'firstname', 'lastname', 'login', 'password', 'locale', 'algo', 'salt', 'rememberMeToken', 'rememberMeSerial', 'email', 'passwordRenewToken', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(AdminTableMap::ID, AdminTableMap::PROFILE_ID, AdminTableMap::FIRSTNAME, AdminTableMap::LASTNAME, AdminTableMap::LOGIN, AdminTableMap::PASSWORD, AdminTableMap::LOCALE, AdminTableMap::ALGO, AdminTableMap::SALT, AdminTableMap::REMEMBER_ME_TOKEN, AdminTableMap::REMEMBER_ME_SERIAL, AdminTableMap::EMAIL, AdminTableMap::PASSWORD_RENEW_TOKEN, AdminTableMap::CREATED_AT, AdminTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'PROFILE_ID', 'FIRSTNAME', 'LASTNAME', 'LOGIN', 'PASSWORD', 'LOCALE', 'ALGO', 'SALT', 'REMEMBER_ME_TOKEN', 'REMEMBER_ME_SERIAL', 'EMAIL', 'PASSWORD_RENEW_TOKEN', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('id', 'profile_id', 'firstname', 'lastname', 'login', 'password', 'locale', 'algo', 'salt', 'remember_me_token', 'remember_me_serial', 'email', 'password_renew_token', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'ProfileId' => 1, 'Firstname' => 2, 'Lastname' => 3, 'Login' => 4, 'Password' => 5, 'Locale' => 6, 'Algo' => 7, 'Salt' => 8, 'RememberMeToken' => 9, 'RememberMeSerial' => 10, 'Email' => 11, 'PasswordRenewToken' => 12, 'CreatedAt' => 13, 'UpdatedAt' => 14, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'profileId' => 1, 'firstname' => 2, 'lastname' => 3, 'login' => 4, 'password' => 5, 'locale' => 6, 'algo' => 7, 'salt' => 8, 'rememberMeToken' => 9, 'rememberMeSerial' => 10, 'email' => 11, 'passwordRenewToken' => 12, 'createdAt' => 13, 'updatedAt' => 14, ),
        self::TYPE_COLNAME       => array(AdminTableMap::ID => 0, AdminTableMap::PROFILE_ID => 1, AdminTableMap::FIRSTNAME => 2, AdminTableMap::LASTNAME => 3, AdminTableMap::LOGIN => 4, AdminTableMap::PASSWORD => 5, AdminTableMap::LOCALE => 6, AdminTableMap::ALGO => 7, AdminTableMap::SALT => 8, AdminTableMap::REMEMBER_ME_TOKEN => 9, AdminTableMap::REMEMBER_ME_SERIAL => 10, AdminTableMap::EMAIL => 11, AdminTableMap::PASSWORD_RENEW_TOKEN => 12, AdminTableMap::CREATED_AT => 13, AdminTableMap::UPDATED_AT => 14, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'PROFILE_ID' => 1, 'FIRSTNAME' => 2, 'LASTNAME' => 3, 'LOGIN' => 4, 'PASSWORD' => 5, 'LOCALE' => 6, 'ALGO' => 7, 'SALT' => 8, 'REMEMBER_ME_TOKEN' => 9, 'REMEMBER_ME_SERIAL' => 10, 'EMAIL' => 11, 'PASSWORD_RENEW_TOKEN' => 12, 'CREATED_AT' => 13, 'UPDATED_AT' => 14, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'profile_id' => 1, 'firstname' => 2, 'lastname' => 3, 'login' => 4, 'password' => 5, 'locale' => 6, 'algo' => 7, 'salt' => 8, 'remember_me_token' => 9, 'remember_me_serial' => 10, 'email' => 11, 'password_renew_token' => 12, 'created_at' => 13, 'updated_at' => 14, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, )
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
        $this->setName('admin');
        $this->setPhpName('Admin');
        $this->setClassName('\\Thelia\\Model\\Admin');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('PROFILE_ID', 'ProfileId', 'INTEGER', 'profile', 'ID', false, null, null);
        $this->addColumn('FIRSTNAME', 'Firstname', 'VARCHAR', true, 100, null);
        $this->addColumn('LASTNAME', 'Lastname', 'VARCHAR', true, 100, null);
        $this->addColumn('LOGIN', 'Login', 'VARCHAR', true, 100, null);
        $this->addColumn('PASSWORD', 'Password', 'VARCHAR', true, 128, null);
        $this->addColumn('LOCALE', 'Locale', 'VARCHAR', true, 45, null);
        $this->addColumn('ALGO', 'Algo', 'VARCHAR', false, 128, null);
        $this->addColumn('SALT', 'Salt', 'VARCHAR', false, 128, null);
        $this->addColumn('REMEMBER_ME_TOKEN', 'RememberMeToken', 'VARCHAR', false, 255, null);
        $this->addColumn('REMEMBER_ME_SERIAL', 'RememberMeSerial', 'VARCHAR', false, 255, null);
        $this->addColumn('EMAIL', 'Email', 'VARCHAR', true, 255, null);
        $this->addColumn('PASSWORD_RENEW_TOKEN', 'PasswordRenewToken', 'VARCHAR', false, 255, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Profile', '\\Thelia\\Model\\Profile', RelationMap::MANY_TO_ONE, array('profile_id' => 'id', ), 'RESTRICT', 'RESTRICT');
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
        return $withPrefix ? AdminTableMap::CLASS_DEFAULT : AdminTableMap::OM_CLASS;
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
     * @return array (Admin object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = AdminTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = AdminTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + AdminTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = AdminTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            AdminTableMap::addInstanceToPool($obj, $key);
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
            $key = AdminTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = AdminTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                AdminTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(AdminTableMap::ID);
            $criteria->addSelectColumn(AdminTableMap::PROFILE_ID);
            $criteria->addSelectColumn(AdminTableMap::FIRSTNAME);
            $criteria->addSelectColumn(AdminTableMap::LASTNAME);
            $criteria->addSelectColumn(AdminTableMap::LOGIN);
            $criteria->addSelectColumn(AdminTableMap::PASSWORD);
            $criteria->addSelectColumn(AdminTableMap::LOCALE);
            $criteria->addSelectColumn(AdminTableMap::ALGO);
            $criteria->addSelectColumn(AdminTableMap::SALT);
            $criteria->addSelectColumn(AdminTableMap::REMEMBER_ME_TOKEN);
            $criteria->addSelectColumn(AdminTableMap::REMEMBER_ME_SERIAL);
            $criteria->addSelectColumn(AdminTableMap::EMAIL);
            $criteria->addSelectColumn(AdminTableMap::PASSWORD_RENEW_TOKEN);
            $criteria->addSelectColumn(AdminTableMap::CREATED_AT);
            $criteria->addSelectColumn(AdminTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.PROFILE_ID');
            $criteria->addSelectColumn($alias . '.FIRSTNAME');
            $criteria->addSelectColumn($alias . '.LASTNAME');
            $criteria->addSelectColumn($alias . '.LOGIN');
            $criteria->addSelectColumn($alias . '.PASSWORD');
            $criteria->addSelectColumn($alias . '.LOCALE');
            $criteria->addSelectColumn($alias . '.ALGO');
            $criteria->addSelectColumn($alias . '.SALT');
            $criteria->addSelectColumn($alias . '.REMEMBER_ME_TOKEN');
            $criteria->addSelectColumn($alias . '.REMEMBER_ME_SERIAL');
            $criteria->addSelectColumn($alias . '.EMAIL');
            $criteria->addSelectColumn($alias . '.PASSWORD_RENEW_TOKEN');
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
        return Propel::getServiceContainer()->getDatabaseMap(AdminTableMap::DATABASE_NAME)->getTable(AdminTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(AdminTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(AdminTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new AdminTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a Admin or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Admin object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(AdminTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\Admin) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(AdminTableMap::DATABASE_NAME);
            $criteria->add(AdminTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = AdminQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { AdminTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { AdminTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the admin table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return AdminQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Admin or Criteria object.
     *
     * @param mixed               $criteria Criteria or Admin object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(AdminTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Admin object
        }

        if ($criteria->containsKey(AdminTableMap::ID) && $criteria->keyContainsValue(AdminTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.AdminTableMap::ID.')');
        }


        // Set the correct dbName
        $query = AdminQuery::create()->mergeWith($criteria);

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

} // AdminTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
AdminTableMap::buildTableMap();
