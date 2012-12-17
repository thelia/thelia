<?php

namespace Thelia\Model\om;

use \BasePeer;
use \Criteria;
use \PDO;
use \PDOStatement;
use \Propel;
use \PropelException;
use \PropelPDO;
use Thelia\Model\AttributeAvPeer;
use Thelia\Model\AttributeCombination;
use Thelia\Model\AttributeCombinationPeer;
use Thelia\Model\AttributePeer;
use Thelia\Model\CombinationPeer;
use Thelia\Model\map\AttributeCombinationTableMap;

/**
 * Base static class for performing query and update operations on the 'attribute_combination' table.
 *
 *
 *
 * @package propel.generator.Thelia.Model.om
 */
abstract class BaseAttributeCombinationPeer
{

    /** the default database name for this class */
    const DATABASE_NAME = 'mydb';

    /** the table name for this class */
    const TABLE_NAME = 'attribute_combination';

    /** the related Propel class for this table */
    const OM_CLASS = 'Thelia\\Model\\AttributeCombination';

    /** the related TableMap class for this table */
    const TM_CLASS = 'AttributeCombinationTableMap';

    /** The total number of columns. */
    const NUM_COLUMNS = 6;

    /** The number of lazy-loaded columns. */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /** The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS) */
    const NUM_HYDRATE_COLUMNS = 6;

    /** the column name for the ID field */
    const ID = 'attribute_combination.ID';

    /** the column name for the ATTRIBUTE_ID field */
    const ATTRIBUTE_ID = 'attribute_combination.ATTRIBUTE_ID';

    /** the column name for the COMBINATION_ID field */
    const COMBINATION_ID = 'attribute_combination.COMBINATION_ID';

    /** the column name for the ATTRIBUTE_AV_ID field */
    const ATTRIBUTE_AV_ID = 'attribute_combination.ATTRIBUTE_AV_ID';

    /** the column name for the CREATED_AT field */
    const CREATED_AT = 'attribute_combination.CREATED_AT';

    /** the column name for the UPDATED_AT field */
    const UPDATED_AT = 'attribute_combination.UPDATED_AT';

    /** The default string format for model objects of the related table **/
    const DEFAULT_STRING_FORMAT = 'YAML';

    /**
     * An identiy map to hold any loaded instances of AttributeCombination objects.
     * This must be public so that other peer classes can access this when hydrating from JOIN
     * queries.
     * @var        array AttributeCombination[]
     */
    public static $instances = array();


    /**
     * holds an array of fieldnames
     *
     * first dimension keys are the type constants
     * e.g. AttributeCombinationPeer::$fieldNames[AttributeCombinationPeer::TYPE_PHPNAME][0] = 'Id'
     */
    protected static $fieldNames = array (
        BasePeer::TYPE_PHPNAME => array ('Id', 'AttributeId', 'CombinationId', 'AttributeAvId', 'CreatedAt', 'UpdatedAt', ),
        BasePeer::TYPE_STUDLYPHPNAME => array ('id', 'attributeId', 'combinationId', 'attributeAvId', 'createdAt', 'updatedAt', ),
        BasePeer::TYPE_COLNAME => array (AttributeCombinationPeer::ID, AttributeCombinationPeer::ATTRIBUTE_ID, AttributeCombinationPeer::COMBINATION_ID, AttributeCombinationPeer::ATTRIBUTE_AV_ID, AttributeCombinationPeer::CREATED_AT, AttributeCombinationPeer::UPDATED_AT, ),
        BasePeer::TYPE_RAW_COLNAME => array ('ID', 'ATTRIBUTE_ID', 'COMBINATION_ID', 'ATTRIBUTE_AV_ID', 'CREATED_AT', 'UPDATED_AT', ),
        BasePeer::TYPE_FIELDNAME => array ('id', 'attribute_id', 'combination_id', 'attribute_av_id', 'created_at', 'updated_At', ),
        BasePeer::TYPE_NUM => array (0, 1, 2, 3, 4, 5, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. AttributeCombinationPeer::$fieldNames[BasePeer::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        BasePeer::TYPE_PHPNAME => array ('Id' => 0, 'AttributeId' => 1, 'CombinationId' => 2, 'AttributeAvId' => 3, 'CreatedAt' => 4, 'UpdatedAt' => 5, ),
        BasePeer::TYPE_STUDLYPHPNAME => array ('id' => 0, 'attributeId' => 1, 'combinationId' => 2, 'attributeAvId' => 3, 'createdAt' => 4, 'updatedAt' => 5, ),
        BasePeer::TYPE_COLNAME => array (AttributeCombinationPeer::ID => 0, AttributeCombinationPeer::ATTRIBUTE_ID => 1, AttributeCombinationPeer::COMBINATION_ID => 2, AttributeCombinationPeer::ATTRIBUTE_AV_ID => 3, AttributeCombinationPeer::CREATED_AT => 4, AttributeCombinationPeer::UPDATED_AT => 5, ),
        BasePeer::TYPE_RAW_COLNAME => array ('ID' => 0, 'ATTRIBUTE_ID' => 1, 'COMBINATION_ID' => 2, 'ATTRIBUTE_AV_ID' => 3, 'CREATED_AT' => 4, 'UPDATED_AT' => 5, ),
        BasePeer::TYPE_FIELDNAME => array ('id' => 0, 'attribute_id' => 1, 'combination_id' => 2, 'attribute_av_id' => 3, 'created_at' => 4, 'updated_At' => 5, ),
        BasePeer::TYPE_NUM => array (0, 1, 2, 3, 4, 5, )
    );

    /**
     * Translates a fieldname to another type
     *
     * @param      string $name field name
     * @param      string $fromType One of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME
     *                         BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM
     * @param      string $toType   One of the class type constants
     * @return string          translated name of the field.
     * @throws PropelException - if the specified name could not be found in the fieldname mappings.
     */
    public static function translateFieldName($name, $fromType, $toType)
    {
        $toNames = AttributeCombinationPeer::getFieldNames($toType);
        $key = isset(AttributeCombinationPeer::$fieldKeys[$fromType][$name]) ? AttributeCombinationPeer::$fieldKeys[$fromType][$name] : null;
        if ($key === null) {
            throw new PropelException("'$name' could not be found in the field names of type '$fromType'. These are: " . print_r(AttributeCombinationPeer::$fieldKeys[$fromType], true));
        }

        return $toNames[$key];
    }

    /**
     * Returns an array of field names.
     *
     * @param      string $type The type of fieldnames to return:
     *                      One of the class type constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME
     *                      BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM
     * @return array           A list of field names
     * @throws PropelException - if the type is not valid.
     */
    public static function getFieldNames($type = BasePeer::TYPE_PHPNAME)
    {
        if (!array_key_exists($type, AttributeCombinationPeer::$fieldNames)) {
            throw new PropelException('Method getFieldNames() expects the parameter $type to be one of the class constants BasePeer::TYPE_PHPNAME, BasePeer::TYPE_STUDLYPHPNAME, BasePeer::TYPE_COLNAME, BasePeer::TYPE_FIELDNAME, BasePeer::TYPE_NUM. ' . $type . ' was given.');
        }

        return AttributeCombinationPeer::$fieldNames[$type];
    }

    /**
     * Convenience method which changes table.column to alias.column.
     *
     * Using this method you can maintain SQL abstraction while using column aliases.
     * <code>
     *		$c->addAlias("alias1", TablePeer::TABLE_NAME);
     *		$c->addJoin(TablePeer::alias("alias1", TablePeer::PRIMARY_KEY_COLUMN), TablePeer::PRIMARY_KEY_COLUMN);
     * </code>
     * @param      string $alias The alias for the current table.
     * @param      string $column The column name for current table. (i.e. AttributeCombinationPeer::COLUMN_NAME).
     * @return string
     */
    public static function alias($alias, $column)
    {
        return str_replace(AttributeCombinationPeer::TABLE_NAME.'.', $alias.'.', $column);
    }

    /**
     * Add all the columns needed to create a new object.
     *
     * Note: any columns that were marked with lazyLoad="true" in the
     * XML schema will not be added to the select list and only loaded
     * on demand.
     *
     * @param      Criteria $criteria object containing the columns to add.
     * @param      string   $alias    optional table alias
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function addSelectColumns(Criteria $criteria, $alias = null)
    {
        if (null === $alias) {
            $criteria->addSelectColumn(AttributeCombinationPeer::ID);
            $criteria->addSelectColumn(AttributeCombinationPeer::ATTRIBUTE_ID);
            $criteria->addSelectColumn(AttributeCombinationPeer::COMBINATION_ID);
            $criteria->addSelectColumn(AttributeCombinationPeer::ATTRIBUTE_AV_ID);
            $criteria->addSelectColumn(AttributeCombinationPeer::CREATED_AT);
            $criteria->addSelectColumn(AttributeCombinationPeer::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.ATTRIBUTE_ID');
            $criteria->addSelectColumn($alias . '.COMBINATION_ID');
            $criteria->addSelectColumn($alias . '.ATTRIBUTE_AV_ID');
            $criteria->addSelectColumn($alias . '.CREATED_AT');
            $criteria->addSelectColumn($alias . '.UPDATED_AT');
        }
    }

    /**
     * Returns the number of rows matching criteria.
     *
     * @param      Criteria $criteria
     * @param      boolean $distinct Whether to select only distinct columns; deprecated: use Criteria->setDistinct() instead.
     * @param      PropelPDO $con
     * @return int Number of matching rows.
     */
    public static function doCount(Criteria $criteria, $distinct = false, PropelPDO $con = null)
    {
        // we may modify criteria, so copy it first
        $criteria = clone $criteria;

        // We need to set the primary table name, since in the case that there are no WHERE columns
        // it will be impossible for the BasePeer::createSelectSql() method to determine which
        // tables go into the FROM clause.
        $criteria->setPrimaryTableName(AttributeCombinationPeer::TABLE_NAME);

        if ($distinct && !in_array(Criteria::DISTINCT, $criteria->getSelectModifiers())) {
            $criteria->setDistinct();
        }

        if (!$criteria->hasSelectClause()) {
            AttributeCombinationPeer::addSelectColumns($criteria);
        }

        $criteria->clearOrderByColumns(); // ORDER BY won't ever affect the count
        $criteria->setDbName(AttributeCombinationPeer::DATABASE_NAME); // Set the correct dbName

        if ($con === null) {
            $con = Propel::getConnection(AttributeCombinationPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }
        // BasePeer returns a PDOStatement
        $stmt = BasePeer::doCount($criteria, $con);

        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $count = (int) $row[0];
        } else {
            $count = 0; // no rows returned; we infer that means 0 matches.
        }
        $stmt->closeCursor();

        return $count;
    }
    /**
     * Selects one object from the DB.
     *
     * @param      Criteria $criteria object used to create the SELECT statement.
     * @param      PropelPDO $con
     * @return                 AttributeCombination
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelectOne(Criteria $criteria, PropelPDO $con = null)
    {
        $critcopy = clone $criteria;
        $critcopy->setLimit(1);
        $objects = AttributeCombinationPeer::doSelect($critcopy, $con);
        if ($objects) {
            return $objects[0];
        }

        return null;
    }
    /**
     * Selects several row from the DB.
     *
     * @param      Criteria $criteria The Criteria object used to build the SELECT statement.
     * @param      PropelPDO $con
     * @return array           Array of selected Objects
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doSelect(Criteria $criteria, PropelPDO $con = null)
    {
        return AttributeCombinationPeer::populateObjects(AttributeCombinationPeer::doSelectStmt($criteria, $con));
    }
    /**
     * Prepares the Criteria object and uses the parent doSelect() method to execute a PDOStatement.
     *
     * Use this method directly if you want to work with an executed statement durirectly (for example
     * to perform your own object hydration).
     *
     * @param      Criteria $criteria The Criteria object used to build the SELECT statement.
     * @param      PropelPDO $con The connection to use
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     * @return PDOStatement The executed PDOStatement object.
     * @see        BasePeer::doSelect()
     */
    public static function doSelectStmt(Criteria $criteria, PropelPDO $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(AttributeCombinationPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }

        if (!$criteria->hasSelectClause()) {
            $criteria = clone $criteria;
            AttributeCombinationPeer::addSelectColumns($criteria);
        }

        // Set the correct dbName
        $criteria->setDbName(AttributeCombinationPeer::DATABASE_NAME);

        // BasePeer returns a PDOStatement
        return BasePeer::doSelect($criteria, $con);
    }
    /**
     * Adds an object to the instance pool.
     *
     * Propel keeps cached copies of objects in an instance pool when they are retrieved
     * from the database.  In some cases -- especially when you override doSelect*()
     * methods in your stub classes -- you may need to explicitly add objects
     * to the cache in order to ensure that the same objects are always returned by doSelect*()
     * and retrieveByPK*() calls.
     *
     * @param      AttributeCombination $obj A AttributeCombination object.
     * @param      string $key (optional) key to use for instance map (for performance boost if key was already calculated externally).
     */
    public static function addInstanceToPool($obj, $key = null)
    {
        if (Propel::isInstancePoolingEnabled()) {
            if ($key === null) {
                $key = serialize(array((string) $obj->getId(), (string) $obj->getAttributeId(), (string) $obj->getCombinationId(), (string) $obj->getAttributeAvId()));
            } // if key === null
            AttributeCombinationPeer::$instances[$key] = $obj;
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
     * @param      mixed $value A AttributeCombination object or a primary key value.
     *
     * @return void
     * @throws PropelException - if the value is invalid.
     */
    public static function removeInstanceFromPool($value)
    {
        if (Propel::isInstancePoolingEnabled() && $value !== null) {
            if (is_object($value) && $value instanceof AttributeCombination) {
                $key = serialize(array((string) $value->getId(), (string) $value->getAttributeId(), (string) $value->getCombinationId(), (string) $value->getAttributeAvId()));
            } elseif (is_array($value) && count($value) === 4) {
                // assume we've been passed a primary key
                $key = serialize(array((string) $value[0], (string) $value[1], (string) $value[2], (string) $value[3]));
            } else {
                $e = new PropelException("Invalid value passed to removeInstanceFromPool().  Expected primary key or AttributeCombination object; got " . (is_object($value) ? get_class($value) . ' object.' : var_export($value,true)));
                throw $e;
            }

            unset(AttributeCombinationPeer::$instances[$key]);
        }
    } // removeInstanceFromPool()

    /**
     * Retrieves a string version of the primary key from the DB resultset row that can be used to uniquely identify a row in this table.
     *
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, a serialize()d version of the primary key will be returned.
     *
     * @param      string $key The key (@see getPrimaryKeyHash()) for this instance.
     * @return   AttributeCombination Found object or null if 1) no instance exists for specified key or 2) instance pooling has been disabled.
     * @see        getPrimaryKeyHash()
     */
    public static function getInstanceFromPool($key)
    {
        if (Propel::isInstancePoolingEnabled()) {
            if (isset(AttributeCombinationPeer::$instances[$key])) {
                return AttributeCombinationPeer::$instances[$key];
            }
        }

        return null; // just to be explicit
    }

    /**
     * Clear the instance pool.
     *
     * @return void
     */
    public static function clearInstancePool()
    {
        AttributeCombinationPeer::$instances = array();
    }

    /**
     * Method to invalidate the instance pool of all tables related to attribute_combination
     * by a foreign key with ON DELETE CASCADE
     */
    public static function clearRelatedInstancePool()
    {
        // Invalidate objects in AttributePeer instance pool,
        // since one or more of them may be deleted by ON DELETE CASCADE/SETNULL rule.
        AttributePeer::clearInstancePool();
        // Invalidate objects in AttributeAvPeer instance pool,
        // since one or more of them may be deleted by ON DELETE CASCADE/SETNULL rule.
        AttributeAvPeer::clearInstancePool();
        // Invalidate objects in CombinationPeer instance pool,
        // since one or more of them may be deleted by ON DELETE CASCADE/SETNULL rule.
        CombinationPeer::clearInstancePool();
    }

    /**
     * Retrieves a string version of the primary key from the DB resultset row that can be used to uniquely identify a row in this table.
     *
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, a serialize()d version of the primary key will be returned.
     *
     * @param      array $row PropelPDO resultset row.
     * @param      int $startcol The 0-based offset for reading from the resultset row.
     * @return string A string version of PK or null if the components of primary key in result array are all null.
     */
    public static function getPrimaryKeyHashFromRow($row, $startcol = 0)
    {
        // If the PK cannot be derived from the row, return null.
        if ($row[$startcol] === null && $row[$startcol + 1] === null && $row[$startcol + 2] === null && $row[$startcol + 3] === null) {
            return null;
        }

        return serialize(array((string) $row[$startcol], (string) $row[$startcol + 1], (string) $row[$startcol + 2], (string) $row[$startcol + 3]));
    }

    /**
     * Retrieves the primary key from the DB resultset row
     * For tables with a single-column primary key, that simple pkey value will be returned.  For tables with
     * a multi-column primary key, an array of the primary key columns will be returned.
     *
     * @param      array $row PropelPDO resultset row.
     * @param      int $startcol The 0-based offset for reading from the resultset row.
     * @return mixed The primary key of the row
     */
    public static function getPrimaryKeyFromRow($row, $startcol = 0)
    {

        return array((int) $row[$startcol], (int) $row[$startcol + 1], (int) $row[$startcol + 2], (int) $row[$startcol + 3]);
    }

    /**
     * The returned array will contain objects of the default type or
     * objects that inherit from the default.
     *
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function populateObjects(PDOStatement $stmt)
    {
        $results = array();

        // set the class once to avoid overhead in the loop
        $cls = AttributeCombinationPeer::getOMClass();
        // populate the object(s)
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $key = AttributeCombinationPeer::getPrimaryKeyHashFromRow($row, 0);
            if (null !== ($obj = AttributeCombinationPeer::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                AttributeCombinationPeer::addInstanceToPool($obj, $key);
            } // if key exists
        }
        $stmt->closeCursor();

        return $results;
    }
    /**
     * Populates an object of the default type or an object that inherit from the default.
     *
     * @param      array $row PropelPDO resultset row.
     * @param      int $startcol The 0-based offset for reading from the resultset row.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     * @return array (AttributeCombination object, last column rank)
     */
    public static function populateObject($row, $startcol = 0)
    {
        $key = AttributeCombinationPeer::getPrimaryKeyHashFromRow($row, $startcol);
        if (null !== ($obj = AttributeCombinationPeer::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $startcol, true); // rehydrate
            $col = $startcol + AttributeCombinationPeer::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = AttributeCombinationPeer::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $startcol);
            AttributeCombinationPeer::addInstanceToPool($obj, $key);
        }

        return array($obj, $col);
    }

    /**
     * Returns the TableMap related to this peer.
     * This method is not needed for general use but a specific application could have a need.
     * @return TableMap
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function getTableMap()
    {
        return Propel::getDatabaseMap(AttributeCombinationPeer::DATABASE_NAME)->getTable(AttributeCombinationPeer::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this peer class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getDatabaseMap(BaseAttributeCombinationPeer::DATABASE_NAME);
      if (!$dbMap->hasTable(BaseAttributeCombinationPeer::TABLE_NAME)) {
        $dbMap->addTableObject(new AttributeCombinationTableMap());
      }
    }

    /**
     * The class that the Peer will make instances of.
     *
     *
     * @return string ClassName
     */
    public static function getOMClass()
    {
        return AttributeCombinationPeer::OM_CLASS;
    }

    /**
     * Performs an INSERT on the database, given a AttributeCombination or Criteria object.
     *
     * @param      mixed $values Criteria or AttributeCombination object containing data that is used to create the INSERT statement.
     * @param      PropelPDO $con the PropelPDO connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doInsert($values, PropelPDO $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(AttributeCombinationPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        if ($values instanceof Criteria) {
            $criteria = clone $values; // rename for clarity
        } else {
            $criteria = $values->buildCriteria(); // build Criteria from AttributeCombination object
        }

        if ($criteria->containsKey(AttributeCombinationPeer::ID) && $criteria->keyContainsValue(AttributeCombinationPeer::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.AttributeCombinationPeer::ID.')');
        }


        // Set the correct dbName
        $criteria->setDbName(AttributeCombinationPeer::DATABASE_NAME);

        try {
            // use transaction because $criteria could contain info
            // for more than one table (I guess, conceivably)
            $con->beginTransaction();
            $pk = BasePeer::doInsert($criteria, $con);
            $con->commit();
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }

        return $pk;
    }

    /**
     * Performs an UPDATE on the database, given a AttributeCombination or Criteria object.
     *
     * @param      mixed $values Criteria or AttributeCombination object containing data that is used to create the UPDATE statement.
     * @param      PropelPDO $con The connection to use (specify PropelPDO connection object to exert more control over transactions).
     * @return int             The number of affected rows (if supported by underlying database driver).
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
    public static function doUpdate($values, PropelPDO $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(AttributeCombinationPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        $selectCriteria = new Criteria(AttributeCombinationPeer::DATABASE_NAME);

        if ($values instanceof Criteria) {
            $criteria = clone $values; // rename for clarity

            $comparison = $criteria->getComparison(AttributeCombinationPeer::ID);
            $value = $criteria->remove(AttributeCombinationPeer::ID);
            if ($value) {
                $selectCriteria->add(AttributeCombinationPeer::ID, $value, $comparison);
            } else {
                $selectCriteria->setPrimaryTableName(AttributeCombinationPeer::TABLE_NAME);
            }

            $comparison = $criteria->getComparison(AttributeCombinationPeer::ATTRIBUTE_ID);
            $value = $criteria->remove(AttributeCombinationPeer::ATTRIBUTE_ID);
            if ($value) {
                $selectCriteria->add(AttributeCombinationPeer::ATTRIBUTE_ID, $value, $comparison);
            } else {
                $selectCriteria->setPrimaryTableName(AttributeCombinationPeer::TABLE_NAME);
            }

            $comparison = $criteria->getComparison(AttributeCombinationPeer::COMBINATION_ID);
            $value = $criteria->remove(AttributeCombinationPeer::COMBINATION_ID);
            if ($value) {
                $selectCriteria->add(AttributeCombinationPeer::COMBINATION_ID, $value, $comparison);
            } else {
                $selectCriteria->setPrimaryTableName(AttributeCombinationPeer::TABLE_NAME);
            }

            $comparison = $criteria->getComparison(AttributeCombinationPeer::ATTRIBUTE_AV_ID);
            $value = $criteria->remove(AttributeCombinationPeer::ATTRIBUTE_AV_ID);
            if ($value) {
                $selectCriteria->add(AttributeCombinationPeer::ATTRIBUTE_AV_ID, $value, $comparison);
            } else {
                $selectCriteria->setPrimaryTableName(AttributeCombinationPeer::TABLE_NAME);
            }

        } else { // $values is AttributeCombination object
            $criteria = $values->buildCriteria(); // gets full criteria
            $selectCriteria = $values->buildPkeyCriteria(); // gets criteria w/ primary key(s)
        }

        // set the correct dbName
        $criteria->setDbName(AttributeCombinationPeer::DATABASE_NAME);

        return BasePeer::doUpdate($selectCriteria, $criteria, $con);
    }

    /**
     * Deletes all rows from the attribute_combination table.
     *
     * @param      PropelPDO $con the connection to use
     * @return int             The number of affected rows (if supported by underlying database driver).
     * @throws PropelException
     */
    public static function doDeleteAll(PropelPDO $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection(AttributeCombinationPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }
        $affectedRows = 0; // initialize var to track total num of affected rows
        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();
            $affectedRows += AttributeCombinationPeer::doOnDeleteCascade(new Criteria(AttributeCombinationPeer::DATABASE_NAME), $con);
            $affectedRows += BasePeer::doDeleteAll(AttributeCombinationPeer::TABLE_NAME, $con, AttributeCombinationPeer::DATABASE_NAME);
            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            AttributeCombinationPeer::clearInstancePool();
            AttributeCombinationPeer::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * Performs a DELETE on the database, given a AttributeCombination or Criteria object OR a primary key value.
     *
     * @param      mixed $values Criteria or AttributeCombination object or primary key or array of primary keys
     *              which is used to create the DELETE statement
     * @param      PropelPDO $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).  This includes CASCADE-related rows
     *				if supported by native driver or if emulated using Propel.
     * @throws PropelException Any exceptions caught during processing will be
     *		 rethrown wrapped into a PropelException.
     */
     public static function doDelete($values, PropelPDO $con = null)
     {
        if ($con === null) {
            $con = Propel::getConnection(AttributeCombinationPeer::DATABASE_NAME, Propel::CONNECTION_WRITE);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = clone $values;
        } elseif ($values instanceof AttributeCombination) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(AttributeCombinationPeer::DATABASE_NAME);
            // primary key is composite; we therefore, expect
            // the primary key passed to be an array of pkey values
            if (count($values) == count($values, COUNT_RECURSIVE)) {
                // array is not multi-dimensional
                $values = array($values);
            }
            foreach ($values as $value) {
                $criterion = $criteria->getNewCriterion(AttributeCombinationPeer::ID, $value[0]);
                $criterion->addAnd($criteria->getNewCriterion(AttributeCombinationPeer::ATTRIBUTE_ID, $value[1]));
                $criterion->addAnd($criteria->getNewCriterion(AttributeCombinationPeer::COMBINATION_ID, $value[2]));
                $criterion->addAnd($criteria->getNewCriterion(AttributeCombinationPeer::ATTRIBUTE_AV_ID, $value[3]));
                $criteria->addOr($criterion);
            }
        }

        // Set the correct dbName
        $criteria->setDbName(AttributeCombinationPeer::DATABASE_NAME);

        $affectedRows = 0; // initialize var to track total num of affected rows

        try {
            // use transaction because $criteria could contain info
            // for more than one table or we could emulating ON DELETE CASCADE, etc.
            $con->beginTransaction();

            // cloning the Criteria in case it's modified by doSelect() or doSelectStmt()
            $c = clone $criteria;
            $affectedRows += AttributeCombinationPeer::doOnDeleteCascade($c, $con);

            // Because this db requires some delete cascade/set null emulation, we have to
            // clear the cached instance *after* the emulation has happened (since
            // instances get re-added by the select statement contained therein).
            if ($values instanceof Criteria) {
                AttributeCombinationPeer::clearInstancePool();
            } elseif ($values instanceof AttributeCombination) { // it's a model object
                AttributeCombinationPeer::removeInstanceFromPool($values);
            } else { // it's a primary key, or an array of pks
                foreach ((array) $values as $singleval) {
                    AttributeCombinationPeer::removeInstanceFromPool($singleval);
                }
            }

            $affectedRows += BasePeer::doDelete($criteria, $con);
            AttributeCombinationPeer::clearRelatedInstancePool();
            $con->commit();

            return $affectedRows;
        } catch (PropelException $e) {
            $con->rollBack();
            throw $e;
        }
    }

    /**
     * This is a method for emulating ON DELETE CASCADE for DBs that don't support this
     * feature (like MySQL or SQLite).
     *
     * This method is not very speedy because it must perform a query first to get
     * the implicated records and then perform the deletes by calling those Peer classes.
     *
     * This method should be used within a transaction if possible.
     *
     * @param      Criteria $criteria
     * @param      PropelPDO $con
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    protected static function doOnDeleteCascade(Criteria $criteria, PropelPDO $con)
    {
        // initialize var to track total num of affected rows
        $affectedRows = 0;

        // first find the objects that are implicated by the $criteria
        $objects = AttributeCombinationPeer::doSelect($criteria, $con);
        foreach ($objects as $obj) {


            // delete related Attribute objects
            $criteria = new Criteria(AttributePeer::DATABASE_NAME);

            $criteria->add(AttributePeer::ID, $obj->getAttributeId());
            $affectedRows += AttributePeer::doDelete($criteria, $con);

            // delete related AttributeAv objects
            $criteria = new Criteria(AttributeAvPeer::DATABASE_NAME);

            $criteria->add(AttributeAvPeer::ID, $obj->getAttributeAvId());
            $affectedRows += AttributeAvPeer::doDelete($criteria, $con);

            // delete related Combination objects
            $criteria = new Criteria(CombinationPeer::DATABASE_NAME);

            $criteria->add(CombinationPeer::ID, $obj->getCombinationId());
            $affectedRows += CombinationPeer::doDelete($criteria, $con);
        }

        return $affectedRows;
    }

    /**
     * Validates all modified columns of given AttributeCombination object.
     * If parameter $columns is either a single column name or an array of column names
     * than only those columns are validated.
     *
     * NOTICE: This does not apply to primary or foreign keys for now.
     *
     * @param      AttributeCombination $obj The object to validate.
     * @param      mixed $cols Column name or array of column names.
     *
     * @return mixed TRUE if all columns are valid or the error message of the first invalid column.
     */
    public static function doValidate($obj, $cols = null)
    {
        $columns = array();

        if ($cols) {
            $dbMap = Propel::getDatabaseMap(AttributeCombinationPeer::DATABASE_NAME);
            $tableMap = $dbMap->getTable(AttributeCombinationPeer::TABLE_NAME);

            if (! is_array($cols)) {
                $cols = array($cols);
            }

            foreach ($cols as $colName) {
                if ($tableMap->hasColumn($colName)) {
                    $get = 'get' . $tableMap->getColumn($colName)->getPhpName();
                    $columns[$colName] = $obj->$get();
                }
            }
        } else {

        }

        return BasePeer::doValidate(AttributeCombinationPeer::DATABASE_NAME, AttributeCombinationPeer::TABLE_NAME, $columns);
    }

    /**
     * Retrieve object using using composite pkey values.
     * @param   int $id
     * @param   int $attribute_id
     * @param   int $combination_id
     * @param   int $attribute_av_id
     * @param      PropelPDO $con
     * @return   AttributeCombination
     */
    public static function retrieveByPK($id, $attribute_id, $combination_id, $attribute_av_id, PropelPDO $con = null) {
        $_instancePoolKey = serialize(array((string) $id, (string) $attribute_id, (string) $combination_id, (string) $attribute_av_id));
         if (null !== ($obj = AttributeCombinationPeer::getInstanceFromPool($_instancePoolKey))) {
             return $obj;
        }

        if ($con === null) {
            $con = Propel::getConnection(AttributeCombinationPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }
        $criteria = new Criteria(AttributeCombinationPeer::DATABASE_NAME);
        $criteria->add(AttributeCombinationPeer::ID, $id);
        $criteria->add(AttributeCombinationPeer::ATTRIBUTE_ID, $attribute_id);
        $criteria->add(AttributeCombinationPeer::COMBINATION_ID, $combination_id);
        $criteria->add(AttributeCombinationPeer::ATTRIBUTE_AV_ID, $attribute_av_id);
        $v = AttributeCombinationPeer::doSelect($criteria, $con);

        return !empty($v) ? $v[0] : null;
    }
} // BaseAttributeCombinationPeer

// This is the static code needed to register the TableMap for this table with the main Propel class.
//
BaseAttributeCombinationPeer::buildTableMap();

