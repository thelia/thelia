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
use Thelia\Model\CountryArea;
use Thelia\Model\CountryAreaQuery;


/**
 * This class defines the structure of the 'country_area' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class CountryAreaTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.CountryAreaTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'country_area';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\CountryArea';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.CountryArea';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 6;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 6;

    /**
     * the column name for the ID field
     */
    const ID = 'country_area.ID';

    /**
     * the column name for the COUNTRY_ID field
     */
    const COUNTRY_ID = 'country_area.COUNTRY_ID';

    /**
     * the column name for the STATE_ID field
     */
    const STATE_ID = 'country_area.STATE_ID';

    /**
     * the column name for the AREA_ID field
     */
    const AREA_ID = 'country_area.AREA_ID';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'country_area.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'country_area.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('Id', 'CountryId', 'StateId', 'AreaId', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'countryId', 'stateId', 'areaId', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(CountryAreaTableMap::ID, CountryAreaTableMap::COUNTRY_ID, CountryAreaTableMap::STATE_ID, CountryAreaTableMap::AREA_ID, CountryAreaTableMap::CREATED_AT, CountryAreaTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'COUNTRY_ID', 'STATE_ID', 'AREA_ID', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('id', 'country_id', 'state_id', 'area_id', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'CountryId' => 1, 'StateId' => 2, 'AreaId' => 3, 'CreatedAt' => 4, 'UpdatedAt' => 5, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'countryId' => 1, 'stateId' => 2, 'areaId' => 3, 'createdAt' => 4, 'updatedAt' => 5, ),
        self::TYPE_COLNAME       => array(CountryAreaTableMap::ID => 0, CountryAreaTableMap::COUNTRY_ID => 1, CountryAreaTableMap::STATE_ID => 2, CountryAreaTableMap::AREA_ID => 3, CountryAreaTableMap::CREATED_AT => 4, CountryAreaTableMap::UPDATED_AT => 5, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'COUNTRY_ID' => 1, 'STATE_ID' => 2, 'AREA_ID' => 3, 'CREATED_AT' => 4, 'UPDATED_AT' => 5, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'country_id' => 1, 'state_id' => 2, 'area_id' => 3, 'created_at' => 4, 'updated_at' => 5, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, )
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
        $this->setName('country_area');
        $this->setPhpName('CountryArea');
        $this->setClassName('\\Thelia\\Model\\CountryArea');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        $this->setIsCrossRef(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('COUNTRY_ID', 'CountryId', 'INTEGER', 'country', 'ID', true, null, null);
        $this->addForeignKey('STATE_ID', 'StateId', 'INTEGER', 'state', 'ID', false, null, null);
        $this->addForeignKey('AREA_ID', 'AreaId', 'INTEGER', 'area', 'ID', true, null, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Area', '\\Thelia\\Model\\Area', RelationMap::MANY_TO_ONE, array('area_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Country', '\\Thelia\\Model\\Country', RelationMap::MANY_TO_ONE, array('country_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('State', '\\Thelia\\Model\\State', RelationMap::MANY_TO_ONE, array('state_id' => 'id', ), 'CASCADE', 'RESTRICT');
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
        return $withPrefix ? CountryAreaTableMap::CLASS_DEFAULT : CountryAreaTableMap::OM_CLASS;
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
     * @return array (CountryArea object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = CountryAreaTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = CountryAreaTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + CountryAreaTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = CountryAreaTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            CountryAreaTableMap::addInstanceToPool($obj, $key);
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
            $key = CountryAreaTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = CountryAreaTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                CountryAreaTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(CountryAreaTableMap::ID);
            $criteria->addSelectColumn(CountryAreaTableMap::COUNTRY_ID);
            $criteria->addSelectColumn(CountryAreaTableMap::STATE_ID);
            $criteria->addSelectColumn(CountryAreaTableMap::AREA_ID);
            $criteria->addSelectColumn(CountryAreaTableMap::CREATED_AT);
            $criteria->addSelectColumn(CountryAreaTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.COUNTRY_ID');
            $criteria->addSelectColumn($alias . '.STATE_ID');
            $criteria->addSelectColumn($alias . '.AREA_ID');
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
        return Propel::getServiceContainer()->getDatabaseMap(CountryAreaTableMap::DATABASE_NAME)->getTable(CountryAreaTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(CountryAreaTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(CountryAreaTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new CountryAreaTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a CountryArea or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or CountryArea object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(CountryAreaTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\CountryArea) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(CountryAreaTableMap::DATABASE_NAME);
            $criteria->add(CountryAreaTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = CountryAreaQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { CountryAreaTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { CountryAreaTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the country_area table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return CountryAreaQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a CountryArea or Criteria object.
     *
     * @param mixed               $criteria Criteria or CountryArea object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CountryAreaTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from CountryArea object
        }

        if ($criteria->containsKey(CountryAreaTableMap::ID) && $criteria->keyContainsValue(CountryAreaTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.CountryAreaTableMap::ID.')');
        }


        // Set the correct dbName
        $query = CountryAreaQuery::create()->mergeWith($criteria);

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

} // CountryAreaTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
CountryAreaTableMap::buildTableMap();
