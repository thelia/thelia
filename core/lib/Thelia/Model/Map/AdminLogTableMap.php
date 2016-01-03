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
use Thelia\Model\AdminLog;
use Thelia\Model\AdminLogQuery;


/**
 * This class defines the structure of the 'admin_log' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class AdminLogTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.AdminLogTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'admin_log';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\AdminLog';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.AdminLog';

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
    const ID = 'admin_log.ID';

    /**
     * the column name for the ADMIN_LOGIN field
     */
    const ADMIN_LOGIN = 'admin_log.ADMIN_LOGIN';

    /**
     * the column name for the ADMIN_FIRSTNAME field
     */
    const ADMIN_FIRSTNAME = 'admin_log.ADMIN_FIRSTNAME';

    /**
     * the column name for the ADMIN_LASTNAME field
     */
    const ADMIN_LASTNAME = 'admin_log.ADMIN_LASTNAME';

    /**
     * the column name for the RESOURCE field
     */
    const RESOURCE = 'admin_log.RESOURCE';

    /**
     * the column name for the RESOURCE_ID field
     */
    const RESOURCE_ID = 'admin_log.RESOURCE_ID';

    /**
     * the column name for the ACTION field
     */
    const ACTION = 'admin_log.ACTION';

    /**
     * the column name for the MESSAGE field
     */
    const MESSAGE = 'admin_log.MESSAGE';

    /**
     * the column name for the REQUEST field
     */
    const REQUEST = 'admin_log.REQUEST';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'admin_log.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'admin_log.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('Id', 'AdminLogin', 'AdminFirstname', 'AdminLastname', 'Resource', 'ResourceId', 'Action', 'Message', 'Request', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'adminLogin', 'adminFirstname', 'adminLastname', 'resource', 'resourceId', 'action', 'message', 'request', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(AdminLogTableMap::ID, AdminLogTableMap::ADMIN_LOGIN, AdminLogTableMap::ADMIN_FIRSTNAME, AdminLogTableMap::ADMIN_LASTNAME, AdminLogTableMap::RESOURCE, AdminLogTableMap::RESOURCE_ID, AdminLogTableMap::ACTION, AdminLogTableMap::MESSAGE, AdminLogTableMap::REQUEST, AdminLogTableMap::CREATED_AT, AdminLogTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'ADMIN_LOGIN', 'ADMIN_FIRSTNAME', 'ADMIN_LASTNAME', 'RESOURCE', 'RESOURCE_ID', 'ACTION', 'MESSAGE', 'REQUEST', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('id', 'admin_login', 'admin_firstname', 'admin_lastname', 'resource', 'resource_id', 'action', 'message', 'request', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'AdminLogin' => 1, 'AdminFirstname' => 2, 'AdminLastname' => 3, 'Resource' => 4, 'ResourceId' => 5, 'Action' => 6, 'Message' => 7, 'Request' => 8, 'CreatedAt' => 9, 'UpdatedAt' => 10, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'adminLogin' => 1, 'adminFirstname' => 2, 'adminLastname' => 3, 'resource' => 4, 'resourceId' => 5, 'action' => 6, 'message' => 7, 'request' => 8, 'createdAt' => 9, 'updatedAt' => 10, ),
        self::TYPE_COLNAME       => array(AdminLogTableMap::ID => 0, AdminLogTableMap::ADMIN_LOGIN => 1, AdminLogTableMap::ADMIN_FIRSTNAME => 2, AdminLogTableMap::ADMIN_LASTNAME => 3, AdminLogTableMap::RESOURCE => 4, AdminLogTableMap::RESOURCE_ID => 5, AdminLogTableMap::ACTION => 6, AdminLogTableMap::MESSAGE => 7, AdminLogTableMap::REQUEST => 8, AdminLogTableMap::CREATED_AT => 9, AdminLogTableMap::UPDATED_AT => 10, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'ADMIN_LOGIN' => 1, 'ADMIN_FIRSTNAME' => 2, 'ADMIN_LASTNAME' => 3, 'RESOURCE' => 4, 'RESOURCE_ID' => 5, 'ACTION' => 6, 'MESSAGE' => 7, 'REQUEST' => 8, 'CREATED_AT' => 9, 'UPDATED_AT' => 10, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'admin_login' => 1, 'admin_firstname' => 2, 'admin_lastname' => 3, 'resource' => 4, 'resource_id' => 5, 'action' => 6, 'message' => 7, 'request' => 8, 'created_at' => 9, 'updated_at' => 10, ),
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
        $this->setName('admin_log');
        $this->setPhpName('AdminLog');
        $this->setClassName('\\Thelia\\Model\\AdminLog');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('ADMIN_LOGIN', 'AdminLogin', 'VARCHAR', false, 255, null);
        $this->addColumn('ADMIN_FIRSTNAME', 'AdminFirstname', 'VARCHAR', false, 255, null);
        $this->addColumn('ADMIN_LASTNAME', 'AdminLastname', 'VARCHAR', false, 255, null);
        $this->addColumn('RESOURCE', 'Resource', 'VARCHAR', false, 255, null);
        $this->addColumn('RESOURCE_ID', 'ResourceId', 'INTEGER', false, null, null);
        $this->addColumn('ACTION', 'Action', 'VARCHAR', false, 255, null);
        $this->addColumn('MESSAGE', 'Message', 'LONGVARCHAR', false, null, null);
        $this->addColumn('REQUEST', 'Request', 'CLOB', false, null, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
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
        return $withPrefix ? AdminLogTableMap::CLASS_DEFAULT : AdminLogTableMap::OM_CLASS;
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
     * @return array (AdminLog object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = AdminLogTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = AdminLogTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + AdminLogTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = AdminLogTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            AdminLogTableMap::addInstanceToPool($obj, $key);
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
            $key = AdminLogTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = AdminLogTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                AdminLogTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(AdminLogTableMap::ID);
            $criteria->addSelectColumn(AdminLogTableMap::ADMIN_LOGIN);
            $criteria->addSelectColumn(AdminLogTableMap::ADMIN_FIRSTNAME);
            $criteria->addSelectColumn(AdminLogTableMap::ADMIN_LASTNAME);
            $criteria->addSelectColumn(AdminLogTableMap::RESOURCE);
            $criteria->addSelectColumn(AdminLogTableMap::RESOURCE_ID);
            $criteria->addSelectColumn(AdminLogTableMap::ACTION);
            $criteria->addSelectColumn(AdminLogTableMap::MESSAGE);
            $criteria->addSelectColumn(AdminLogTableMap::REQUEST);
            $criteria->addSelectColumn(AdminLogTableMap::CREATED_AT);
            $criteria->addSelectColumn(AdminLogTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.ADMIN_LOGIN');
            $criteria->addSelectColumn($alias . '.ADMIN_FIRSTNAME');
            $criteria->addSelectColumn($alias . '.ADMIN_LASTNAME');
            $criteria->addSelectColumn($alias . '.RESOURCE');
            $criteria->addSelectColumn($alias . '.RESOURCE_ID');
            $criteria->addSelectColumn($alias . '.ACTION');
            $criteria->addSelectColumn($alias . '.MESSAGE');
            $criteria->addSelectColumn($alias . '.REQUEST');
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
        return Propel::getServiceContainer()->getDatabaseMap(AdminLogTableMap::DATABASE_NAME)->getTable(AdminLogTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(AdminLogTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(AdminLogTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new AdminLogTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a AdminLog or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or AdminLog object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(AdminLogTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\AdminLog) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(AdminLogTableMap::DATABASE_NAME);
            $criteria->add(AdminLogTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = AdminLogQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { AdminLogTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { AdminLogTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the admin_log table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return AdminLogQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a AdminLog or Criteria object.
     *
     * @param mixed               $criteria Criteria or AdminLog object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(AdminLogTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from AdminLog object
        }

        if ($criteria->containsKey(AdminLogTableMap::ID) && $criteria->keyContainsValue(AdminLogTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.AdminLogTableMap::ID.')');
        }


        // Set the correct dbName
        $query = AdminLogQuery::create()->mergeWith($criteria);

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

} // AdminLogTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
AdminLogTableMap::buildTableMap();
