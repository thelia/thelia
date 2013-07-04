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
use Thelia\Model\GroupResource;
use Thelia\Model\GroupResourceQuery;


/**
 * This class defines the structure of the 'group_resource' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class GroupResourceTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.GroupResourceTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'group_resource';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\GroupResource';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.GroupResource';

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
     * the column name for the ID field
     */
    const ID = 'group_resource.ID';

    /**
     * the column name for the GROUP_ID field
     */
    const GROUP_ID = 'group_resource.GROUP_ID';

    /**
     * the column name for the RESOURCE_ID field
     */
    const RESOURCE_ID = 'group_resource.RESOURCE_ID';

    /**
     * the column name for the READ field
     */
    const READ = 'group_resource.READ';

    /**
     * the column name for the WRITE field
     */
    const WRITE = 'group_resource.WRITE';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'group_resource.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'group_resource.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('Id', 'GroupId', 'ResourceId', 'Read', 'Write', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'groupId', 'resourceId', 'read', 'write', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(GroupResourceTableMap::ID, GroupResourceTableMap::GROUP_ID, GroupResourceTableMap::RESOURCE_ID, GroupResourceTableMap::READ, GroupResourceTableMap::WRITE, GroupResourceTableMap::CREATED_AT, GroupResourceTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'GROUP_ID', 'RESOURCE_ID', 'READ', 'WRITE', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('id', 'group_id', 'resource_id', 'read', 'write', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'GroupId' => 1, 'ResourceId' => 2, 'Read' => 3, 'Write' => 4, 'CreatedAt' => 5, 'UpdatedAt' => 6, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'groupId' => 1, 'resourceId' => 2, 'read' => 3, 'write' => 4, 'createdAt' => 5, 'updatedAt' => 6, ),
        self::TYPE_COLNAME       => array(GroupResourceTableMap::ID => 0, GroupResourceTableMap::GROUP_ID => 1, GroupResourceTableMap::RESOURCE_ID => 2, GroupResourceTableMap::READ => 3, GroupResourceTableMap::WRITE => 4, GroupResourceTableMap::CREATED_AT => 5, GroupResourceTableMap::UPDATED_AT => 6, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'GROUP_ID' => 1, 'RESOURCE_ID' => 2, 'READ' => 3, 'WRITE' => 4, 'CREATED_AT' => 5, 'UPDATED_AT' => 6, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'group_id' => 1, 'resource_id' => 2, 'read' => 3, 'write' => 4, 'created_at' => 5, 'updated_at' => 6, ),
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
        $this->setName('group_resource');
        $this->setPhpName('GroupResource');
        $this->setClassName('\\Thelia\\Model\\GroupResource');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        $this->setIsCrossRef(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addForeignPrimaryKey('GROUP_ID', 'GroupId', 'INTEGER' , 'group', 'ID', true, null, null);
        $this->addForeignPrimaryKey('RESOURCE_ID', 'ResourceId', 'INTEGER' , 'resource', 'ID', true, null, null);
        $this->addColumn('READ', 'Read', 'TINYINT', false, null, 0);
        $this->addColumn('WRITE', 'Write', 'TINYINT', false, null, 0);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Group', '\\Thelia\\Model\\Group', RelationMap::MANY_TO_ONE, array('group_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Resource', '\\Thelia\\Model\\Resource', RelationMap::MANY_TO_ONE, array('resource_id' => 'id', ), 'CASCADE', 'RESTRICT');
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
     * @param \Thelia\Model\GroupResource $obj A \Thelia\Model\GroupResource object.
     * @param string $key             (optional) key to use for instance map (for performance boost if key was already calculated externally).
     */
    public static function addInstanceToPool($obj, $key = null)
    {
        if (Propel::isInstancePoolingEnabled()) {
            if (null === $key) {
                $key = serialize(array((string) $obj->getId(), (string) $obj->getGroupId(), (string) $obj->getResourceId()));
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
     * @param mixed $value A \Thelia\Model\GroupResource object or a primary key value.
     */
    public static function removeInstanceFromPool($value)
    {
        if (Propel::isInstancePoolingEnabled() && null !== $value) {
            if (is_object($value) && $value instanceof \Thelia\Model\GroupResource) {
                $key = serialize(array((string) $value->getId(), (string) $value->getGroupId(), (string) $value->getResourceId()));

            } elseif (is_array($value) && count($value) === 3) {
                // assume we've been passed a primary key";
                $key = serialize(array((string) $value[0], (string) $value[1], (string) $value[2]));
            } elseif ($value instanceof Criteria) {
                self::$instances = [];

                return;
            } else {
                $e = new PropelException("Invalid value passed to removeInstanceFromPool().  Expected primary key or \Thelia\Model\GroupResource object; got " . (is_object($value) ? get_class($value) . ' object.' : var_export($value, true)));
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
        if ($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)] === null && $row[TableMap::TYPE_NUM == $indexType ? 1 + $offset : static::translateFieldName('GroupId', TableMap::TYPE_PHPNAME, $indexType)] === null && $row[TableMap::TYPE_NUM == $indexType ? 2 + $offset : static::translateFieldName('ResourceId', TableMap::TYPE_PHPNAME, $indexType)] === null) {
            return null;
        }

        return serialize(array((string) $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('Id', TableMap::TYPE_PHPNAME, $indexType)], (string) $row[TableMap::TYPE_NUM == $indexType ? 1 + $offset : static::translateFieldName('GroupId', TableMap::TYPE_PHPNAME, $indexType)], (string) $row[TableMap::TYPE_NUM == $indexType ? 2 + $offset : static::translateFieldName('ResourceId', TableMap::TYPE_PHPNAME, $indexType)]));
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
        return $withPrefix ? GroupResourceTableMap::CLASS_DEFAULT : GroupResourceTableMap::OM_CLASS;
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
     * @return array (GroupResource object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = GroupResourceTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = GroupResourceTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + GroupResourceTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = GroupResourceTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            GroupResourceTableMap::addInstanceToPool($obj, $key);
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
            $key = GroupResourceTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = GroupResourceTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                GroupResourceTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(GroupResourceTableMap::ID);
            $criteria->addSelectColumn(GroupResourceTableMap::GROUP_ID);
            $criteria->addSelectColumn(GroupResourceTableMap::RESOURCE_ID);
            $criteria->addSelectColumn(GroupResourceTableMap::READ);
            $criteria->addSelectColumn(GroupResourceTableMap::WRITE);
            $criteria->addSelectColumn(GroupResourceTableMap::CREATED_AT);
            $criteria->addSelectColumn(GroupResourceTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.GROUP_ID');
            $criteria->addSelectColumn($alias . '.RESOURCE_ID');
            $criteria->addSelectColumn($alias . '.READ');
            $criteria->addSelectColumn($alias . '.WRITE');
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
        return Propel::getServiceContainer()->getDatabaseMap(GroupResourceTableMap::DATABASE_NAME)->getTable(GroupResourceTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(GroupResourceTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(GroupResourceTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new GroupResourceTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a GroupResource or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or GroupResource object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(GroupResourceTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\GroupResource) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(GroupResourceTableMap::DATABASE_NAME);
            // primary key is composite; we therefore, expect
            // the primary key passed to be an array of pkey values
            if (count($values) == count($values, COUNT_RECURSIVE)) {
                // array is not multi-dimensional
                $values = array($values);
            }
            foreach ($values as $value) {
                $criterion = $criteria->getNewCriterion(GroupResourceTableMap::ID, $value[0]);
                $criterion->addAnd($criteria->getNewCriterion(GroupResourceTableMap::GROUP_ID, $value[1]));
                $criterion->addAnd($criteria->getNewCriterion(GroupResourceTableMap::RESOURCE_ID, $value[2]));
                $criteria->addOr($criterion);
            }
        }

        $query = GroupResourceQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { GroupResourceTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { GroupResourceTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the group_resource table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return GroupResourceQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a GroupResource or Criteria object.
     *
     * @param mixed               $criteria Criteria or GroupResource object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(GroupResourceTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from GroupResource object
        }

        if ($criteria->containsKey(GroupResourceTableMap::ID) && $criteria->keyContainsValue(GroupResourceTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.GroupResourceTableMap::ID.')');
        }


        // Set the correct dbName
        $query = GroupResourceQuery::create()->mergeWith($criteria);

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

} // GroupResourceTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
GroupResourceTableMap::buildTableMap();
