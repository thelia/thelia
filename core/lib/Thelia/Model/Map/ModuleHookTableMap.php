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
use Thelia\Model\ModuleHook;
use Thelia\Model\ModuleHookQuery;


/**
 * This class defines the structure of the 'module_hook' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class ModuleHookTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.ModuleHookTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'module_hook';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\ModuleHook';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.ModuleHook';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 10;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 10;

    /**
     * the column name for the ID field
     */
    const ID = 'module_hook.ID';

    /**
     * the column name for the MODULE_ID field
     */
    const MODULE_ID = 'module_hook.MODULE_ID';

    /**
     * the column name for the HOOK_ID field
     */
    const HOOK_ID = 'module_hook.HOOK_ID';

    /**
     * the column name for the CLASSNAME field
     */
    const CLASSNAME = 'module_hook.CLASSNAME';

    /**
     * the column name for the METHOD field
     */
    const METHOD = 'module_hook.METHOD';

    /**
     * the column name for the ACTIVE field
     */
    const ACTIVE = 'module_hook.ACTIVE';

    /**
     * the column name for the HOOK_ACTIVE field
     */
    const HOOK_ACTIVE = 'module_hook.HOOK_ACTIVE';

    /**
     * the column name for the MODULE_ACTIVE field
     */
    const MODULE_ACTIVE = 'module_hook.MODULE_ACTIVE';

    /**
     * the column name for the POSITION field
     */
    const POSITION = 'module_hook.POSITION';

    /**
     * the column name for the TEMPLATES field
     */
    const TEMPLATES = 'module_hook.TEMPLATES';

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
        self::TYPE_PHPNAME       => array('Id', 'ModuleId', 'HookId', 'Classname', 'Method', 'Active', 'HookActive', 'ModuleActive', 'Position', 'Templates', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'moduleId', 'hookId', 'classname', 'method', 'active', 'hookActive', 'moduleActive', 'position', 'templates', ),
        self::TYPE_COLNAME       => array(ModuleHookTableMap::ID, ModuleHookTableMap::MODULE_ID, ModuleHookTableMap::HOOK_ID, ModuleHookTableMap::CLASSNAME, ModuleHookTableMap::METHOD, ModuleHookTableMap::ACTIVE, ModuleHookTableMap::HOOK_ACTIVE, ModuleHookTableMap::MODULE_ACTIVE, ModuleHookTableMap::POSITION, ModuleHookTableMap::TEMPLATES, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'MODULE_ID', 'HOOK_ID', 'CLASSNAME', 'METHOD', 'ACTIVE', 'HOOK_ACTIVE', 'MODULE_ACTIVE', 'POSITION', 'TEMPLATES', ),
        self::TYPE_FIELDNAME     => array('id', 'module_id', 'hook_id', 'classname', 'method', 'active', 'hook_active', 'module_active', 'position', 'templates', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'ModuleId' => 1, 'HookId' => 2, 'Classname' => 3, 'Method' => 4, 'Active' => 5, 'HookActive' => 6, 'ModuleActive' => 7, 'Position' => 8, 'Templates' => 9, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'moduleId' => 1, 'hookId' => 2, 'classname' => 3, 'method' => 4, 'active' => 5, 'hookActive' => 6, 'moduleActive' => 7, 'position' => 8, 'templates' => 9, ),
        self::TYPE_COLNAME       => array(ModuleHookTableMap::ID => 0, ModuleHookTableMap::MODULE_ID => 1, ModuleHookTableMap::HOOK_ID => 2, ModuleHookTableMap::CLASSNAME => 3, ModuleHookTableMap::METHOD => 4, ModuleHookTableMap::ACTIVE => 5, ModuleHookTableMap::HOOK_ACTIVE => 6, ModuleHookTableMap::MODULE_ACTIVE => 7, ModuleHookTableMap::POSITION => 8, ModuleHookTableMap::TEMPLATES => 9, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'MODULE_ID' => 1, 'HOOK_ID' => 2, 'CLASSNAME' => 3, 'METHOD' => 4, 'ACTIVE' => 5, 'HOOK_ACTIVE' => 6, 'MODULE_ACTIVE' => 7, 'POSITION' => 8, 'TEMPLATES' => 9, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'module_id' => 1, 'hook_id' => 2, 'classname' => 3, 'method' => 4, 'active' => 5, 'hook_active' => 6, 'module_active' => 7, 'position' => 8, 'templates' => 9, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, )
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
        $this->setName('module_hook');
        $this->setPhpName('ModuleHook');
        $this->setClassName('\\Thelia\\Model\\ModuleHook');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('MODULE_ID', 'ModuleId', 'INTEGER', 'module', 'ID', true, null, null);
        $this->addForeignKey('HOOK_ID', 'HookId', 'INTEGER', 'hook', 'ID', true, null, null);
        $this->addColumn('CLASSNAME', 'Classname', 'VARCHAR', false, 255, null);
        $this->addColumn('METHOD', 'Method', 'VARCHAR', false, 255, null);
        $this->addColumn('ACTIVE', 'Active', 'BOOLEAN', true, 1, null);
        $this->addColumn('HOOK_ACTIVE', 'HookActive', 'BOOLEAN', true, 1, null);
        $this->addColumn('MODULE_ACTIVE', 'ModuleActive', 'BOOLEAN', true, 1, null);
        $this->addColumn('POSITION', 'Position', 'INTEGER', true, null, null);
        $this->addColumn('TEMPLATES', 'Templates', 'LONGVARCHAR', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Module', '\\Thelia\\Model\\Module', RelationMap::MANY_TO_ONE, array('module_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Hook', '\\Thelia\\Model\\Hook', RelationMap::MANY_TO_ONE, array('hook_id' => 'id', ), 'CASCADE', 'RESTRICT');
    } // buildRelations()

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
        return $withPrefix ? ModuleHookTableMap::CLASS_DEFAULT : ModuleHookTableMap::OM_CLASS;
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
     * @return array (ModuleHook object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = ModuleHookTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = ModuleHookTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + ModuleHookTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = ModuleHookTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            ModuleHookTableMap::addInstanceToPool($obj, $key);
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
            $key = ModuleHookTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = ModuleHookTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                ModuleHookTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(ModuleHookTableMap::ID);
            $criteria->addSelectColumn(ModuleHookTableMap::MODULE_ID);
            $criteria->addSelectColumn(ModuleHookTableMap::HOOK_ID);
            $criteria->addSelectColumn(ModuleHookTableMap::CLASSNAME);
            $criteria->addSelectColumn(ModuleHookTableMap::METHOD);
            $criteria->addSelectColumn(ModuleHookTableMap::ACTIVE);
            $criteria->addSelectColumn(ModuleHookTableMap::HOOK_ACTIVE);
            $criteria->addSelectColumn(ModuleHookTableMap::MODULE_ACTIVE);
            $criteria->addSelectColumn(ModuleHookTableMap::POSITION);
            $criteria->addSelectColumn(ModuleHookTableMap::TEMPLATES);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.MODULE_ID');
            $criteria->addSelectColumn($alias . '.HOOK_ID');
            $criteria->addSelectColumn($alias . '.CLASSNAME');
            $criteria->addSelectColumn($alias . '.METHOD');
            $criteria->addSelectColumn($alias . '.ACTIVE');
            $criteria->addSelectColumn($alias . '.HOOK_ACTIVE');
            $criteria->addSelectColumn($alias . '.MODULE_ACTIVE');
            $criteria->addSelectColumn($alias . '.POSITION');
            $criteria->addSelectColumn($alias . '.TEMPLATES');
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
        return Propel::getServiceContainer()->getDatabaseMap(ModuleHookTableMap::DATABASE_NAME)->getTable(ModuleHookTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(ModuleHookTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(ModuleHookTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new ModuleHookTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a ModuleHook or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or ModuleHook object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(ModuleHookTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\ModuleHook) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(ModuleHookTableMap::DATABASE_NAME);
            $criteria->add(ModuleHookTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = ModuleHookQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { ModuleHookTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { ModuleHookTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the module_hook table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return ModuleHookQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a ModuleHook or Criteria object.
     *
     * @param mixed               $criteria Criteria or ModuleHook object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(ModuleHookTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from ModuleHook object
        }

        if ($criteria->containsKey(ModuleHookTableMap::ID) && $criteria->keyContainsValue(ModuleHookTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.ModuleHookTableMap::ID.')');
        }


        // Set the correct dbName
        $query = ModuleHookQuery::create()->mergeWith($criteria);

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

} // ModuleHookTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
ModuleHookTableMap::buildTableMap();
