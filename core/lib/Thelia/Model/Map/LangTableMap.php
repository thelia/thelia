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
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;


/**
 * This class defines the structure of the 'lang' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class LangTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.LangTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'lang';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\Lang';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.Lang';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 17;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 17;

    /**
     * the column name for the ID field
     */
    const ID = 'lang.ID';

    /**
     * the column name for the TITLE field
     */
    const TITLE = 'lang.TITLE';

    /**
     * the column name for the CODE field
     */
    const CODE = 'lang.CODE';

    /**
     * the column name for the LOCALE field
     */
    const LOCALE = 'lang.LOCALE';

    /**
     * the column name for the URL field
     */
    const URL = 'lang.URL';

    /**
     * the column name for the DATE_FORMAT field
     */
    const DATE_FORMAT = 'lang.DATE_FORMAT';

    /**
     * the column name for the TIME_FORMAT field
     */
    const TIME_FORMAT = 'lang.TIME_FORMAT';

    /**
     * the column name for the DATETIME_FORMAT field
     */
    const DATETIME_FORMAT = 'lang.DATETIME_FORMAT';

    /**
     * the column name for the DECIMAL_SEPARATOR field
     */
    const DECIMAL_SEPARATOR = 'lang.DECIMAL_SEPARATOR';

    /**
     * the column name for the THOUSANDS_SEPARATOR field
     */
    const THOUSANDS_SEPARATOR = 'lang.THOUSANDS_SEPARATOR';

    /**
     * the column name for the DECIMALS field
     */
    const DECIMALS = 'lang.DECIMALS';

    /**
     * the column name for the ACTIVE field
     */
    const ACTIVE = 'lang.ACTIVE';

    /**
     * the column name for the VISIBLE field
     */
    const VISIBLE = 'lang.VISIBLE';

    /**
     * the column name for the BY_DEFAULT field
     */
    const BY_DEFAULT = 'lang.BY_DEFAULT';

    /**
     * the column name for the POSITION field
     */
    const POSITION = 'lang.POSITION';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'lang.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'lang.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('Id', 'Title', 'Code', 'Locale', 'Url', 'DateFormat', 'TimeFormat', 'DatetimeFormat', 'DecimalSeparator', 'ThousandsSeparator', 'Decimals', 'Active', 'Visible', 'ByDefault', 'Position', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'title', 'code', 'locale', 'url', 'dateFormat', 'timeFormat', 'datetimeFormat', 'decimalSeparator', 'thousandsSeparator', 'decimals', 'active', 'visible', 'byDefault', 'position', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(LangTableMap::ID, LangTableMap::TITLE, LangTableMap::CODE, LangTableMap::LOCALE, LangTableMap::URL, LangTableMap::DATE_FORMAT, LangTableMap::TIME_FORMAT, LangTableMap::DATETIME_FORMAT, LangTableMap::DECIMAL_SEPARATOR, LangTableMap::THOUSANDS_SEPARATOR, LangTableMap::DECIMALS, LangTableMap::ACTIVE, LangTableMap::VISIBLE, LangTableMap::BY_DEFAULT, LangTableMap::POSITION, LangTableMap::CREATED_AT, LangTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'TITLE', 'CODE', 'LOCALE', 'URL', 'DATE_FORMAT', 'TIME_FORMAT', 'DATETIME_FORMAT', 'DECIMAL_SEPARATOR', 'THOUSANDS_SEPARATOR', 'DECIMALS', 'ACTIVE', 'VISIBLE', 'BY_DEFAULT', 'POSITION', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('id', 'title', 'code', 'locale', 'url', 'date_format', 'time_format', 'datetime_format', 'decimal_separator', 'thousands_separator', 'decimals', 'active', 'visible', 'by_default', 'position', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'Title' => 1, 'Code' => 2, 'Locale' => 3, 'Url' => 4, 'DateFormat' => 5, 'TimeFormat' => 6, 'DatetimeFormat' => 7, 'DecimalSeparator' => 8, 'ThousandsSeparator' => 9, 'Decimals' => 10, 'Active' => 11, 'Visible' => 12, 'ByDefault' => 13, 'Position' => 14, 'CreatedAt' => 15, 'UpdatedAt' => 16, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'title' => 1, 'code' => 2, 'locale' => 3, 'url' => 4, 'dateFormat' => 5, 'timeFormat' => 6, 'datetimeFormat' => 7, 'decimalSeparator' => 8, 'thousandsSeparator' => 9, 'decimals' => 10, 'active' => 11, 'visible' => 12, 'byDefault' => 13, 'position' => 14, 'createdAt' => 15, 'updatedAt' => 16, ),
        self::TYPE_COLNAME       => array(LangTableMap::ID => 0, LangTableMap::TITLE => 1, LangTableMap::CODE => 2, LangTableMap::LOCALE => 3, LangTableMap::URL => 4, LangTableMap::DATE_FORMAT => 5, LangTableMap::TIME_FORMAT => 6, LangTableMap::DATETIME_FORMAT => 7, LangTableMap::DECIMAL_SEPARATOR => 8, LangTableMap::THOUSANDS_SEPARATOR => 9, LangTableMap::DECIMALS => 10, LangTableMap::ACTIVE => 11, LangTableMap::VISIBLE => 12, LangTableMap::BY_DEFAULT => 13, LangTableMap::POSITION => 14, LangTableMap::CREATED_AT => 15, LangTableMap::UPDATED_AT => 16, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'TITLE' => 1, 'CODE' => 2, 'LOCALE' => 3, 'URL' => 4, 'DATE_FORMAT' => 5, 'TIME_FORMAT' => 6, 'DATETIME_FORMAT' => 7, 'DECIMAL_SEPARATOR' => 8, 'THOUSANDS_SEPARATOR' => 9, 'DECIMALS' => 10, 'ACTIVE' => 11, 'VISIBLE' => 12, 'BY_DEFAULT' => 13, 'POSITION' => 14, 'CREATED_AT' => 15, 'UPDATED_AT' => 16, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'title' => 1, 'code' => 2, 'locale' => 3, 'url' => 4, 'date_format' => 5, 'time_format' => 6, 'datetime_format' => 7, 'decimal_separator' => 8, 'thousands_separator' => 9, 'decimals' => 10, 'active' => 11, 'visible' => 12, 'by_default' => 13, 'position' => 14, 'created_at' => 15, 'updated_at' => 16, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, )
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
        $this->setName('lang');
        $this->setPhpName('Lang');
        $this->setClassName('\\Thelia\\Model\\Lang');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('TITLE', 'Title', 'VARCHAR', false, 100, null);
        $this->addColumn('CODE', 'Code', 'VARCHAR', false, 10, null);
        $this->addColumn('LOCALE', 'Locale', 'VARCHAR', false, 45, null);
        $this->addColumn('URL', 'Url', 'VARCHAR', false, 255, null);
        $this->addColumn('DATE_FORMAT', 'DateFormat', 'VARCHAR', false, 45, null);
        $this->addColumn('TIME_FORMAT', 'TimeFormat', 'VARCHAR', false, 45, null);
        $this->addColumn('DATETIME_FORMAT', 'DatetimeFormat', 'VARCHAR', false, 45, null);
        $this->addColumn('DECIMAL_SEPARATOR', 'DecimalSeparator', 'VARCHAR', false, 45, null);
        $this->addColumn('THOUSANDS_SEPARATOR', 'ThousandsSeparator', 'VARCHAR', false, 45, null);
        $this->addColumn('DECIMALS', 'Decimals', 'VARCHAR', false, 45, null);
        $this->addColumn('ACTIVE', 'Active', 'BOOLEAN', false, 1, false);
        $this->addColumn('VISIBLE', 'Visible', 'TINYINT', false, null, 0);
        $this->addColumn('BY_DEFAULT', 'ByDefault', 'TINYINT', false, null, null);
        $this->addColumn('POSITION', 'Position', 'INTEGER', false, null, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Order', '\\Thelia\\Model\\Order', RelationMap::ONE_TO_MANY, array('id' => 'lang_id', ), 'RESTRICT', 'RESTRICT', 'Orders');
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
        return $withPrefix ? LangTableMap::CLASS_DEFAULT : LangTableMap::OM_CLASS;
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
     * @return array (Lang object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = LangTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = LangTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + LangTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = LangTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            LangTableMap::addInstanceToPool($obj, $key);
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
            $key = LangTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = LangTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                LangTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(LangTableMap::ID);
            $criteria->addSelectColumn(LangTableMap::TITLE);
            $criteria->addSelectColumn(LangTableMap::CODE);
            $criteria->addSelectColumn(LangTableMap::LOCALE);
            $criteria->addSelectColumn(LangTableMap::URL);
            $criteria->addSelectColumn(LangTableMap::DATE_FORMAT);
            $criteria->addSelectColumn(LangTableMap::TIME_FORMAT);
            $criteria->addSelectColumn(LangTableMap::DATETIME_FORMAT);
            $criteria->addSelectColumn(LangTableMap::DECIMAL_SEPARATOR);
            $criteria->addSelectColumn(LangTableMap::THOUSANDS_SEPARATOR);
            $criteria->addSelectColumn(LangTableMap::DECIMALS);
            $criteria->addSelectColumn(LangTableMap::ACTIVE);
            $criteria->addSelectColumn(LangTableMap::VISIBLE);
            $criteria->addSelectColumn(LangTableMap::BY_DEFAULT);
            $criteria->addSelectColumn(LangTableMap::POSITION);
            $criteria->addSelectColumn(LangTableMap::CREATED_AT);
            $criteria->addSelectColumn(LangTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.TITLE');
            $criteria->addSelectColumn($alias . '.CODE');
            $criteria->addSelectColumn($alias . '.LOCALE');
            $criteria->addSelectColumn($alias . '.URL');
            $criteria->addSelectColumn($alias . '.DATE_FORMAT');
            $criteria->addSelectColumn($alias . '.TIME_FORMAT');
            $criteria->addSelectColumn($alias . '.DATETIME_FORMAT');
            $criteria->addSelectColumn($alias . '.DECIMAL_SEPARATOR');
            $criteria->addSelectColumn($alias . '.THOUSANDS_SEPARATOR');
            $criteria->addSelectColumn($alias . '.DECIMALS');
            $criteria->addSelectColumn($alias . '.ACTIVE');
            $criteria->addSelectColumn($alias . '.VISIBLE');
            $criteria->addSelectColumn($alias . '.BY_DEFAULT');
            $criteria->addSelectColumn($alias . '.POSITION');
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
        return Propel::getServiceContainer()->getDatabaseMap(LangTableMap::DATABASE_NAME)->getTable(LangTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(LangTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(LangTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new LangTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a Lang or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Lang object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(LangTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\Lang) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(LangTableMap::DATABASE_NAME);
            $criteria->add(LangTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = LangQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { LangTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { LangTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the lang table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return LangQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Lang or Criteria object.
     *
     * @param mixed               $criteria Criteria or Lang object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(LangTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Lang object
        }

        if ($criteria->containsKey(LangTableMap::ID) && $criteria->keyContainsValue(LangTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.LangTableMap::ID.')');
        }


        // Set the correct dbName
        $query = LangQuery::create()->mergeWith($criteria);

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

} // LangTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
LangTableMap::buildTableMap();
