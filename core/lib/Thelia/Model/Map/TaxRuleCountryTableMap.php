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
use Thelia\Model\TaxRuleCountry;
use Thelia\Model\TaxRuleCountryQuery;


/**
 * This class defines the structure of the 'tax_rule_country' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class TaxRuleCountryTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.TaxRuleCountryTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'tax_rule_country';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\TaxRuleCountry';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.TaxRuleCountry';

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
     * the column name for the TAX_RULE_ID field
     */
    const TAX_RULE_ID = 'tax_rule_country.TAX_RULE_ID';

    /**
     * the column name for the COUNTRY_ID field
     */
    const COUNTRY_ID = 'tax_rule_country.COUNTRY_ID';

    /**
     * the column name for the TAX_ID field
     */
    const TAX_ID = 'tax_rule_country.TAX_ID';

    /**
     * the column name for the POSITION field
     */
    const POSITION = 'tax_rule_country.POSITION';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'tax_rule_country.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'tax_rule_country.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('TaxRuleId', 'CountryId', 'TaxId', 'Position', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('taxRuleId', 'countryId', 'taxId', 'position', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(TaxRuleCountryTableMap::TAX_RULE_ID, TaxRuleCountryTableMap::COUNTRY_ID, TaxRuleCountryTableMap::TAX_ID, TaxRuleCountryTableMap::POSITION, TaxRuleCountryTableMap::CREATED_AT, TaxRuleCountryTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('TAX_RULE_ID', 'COUNTRY_ID', 'TAX_ID', 'POSITION', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('tax_rule_id', 'country_id', 'tax_id', 'position', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('TaxRuleId' => 0, 'CountryId' => 1, 'TaxId' => 2, 'Position' => 3, 'CreatedAt' => 4, 'UpdatedAt' => 5, ),
        self::TYPE_STUDLYPHPNAME => array('taxRuleId' => 0, 'countryId' => 1, 'taxId' => 2, 'position' => 3, 'createdAt' => 4, 'updatedAt' => 5, ),
        self::TYPE_COLNAME       => array(TaxRuleCountryTableMap::TAX_RULE_ID => 0, TaxRuleCountryTableMap::COUNTRY_ID => 1, TaxRuleCountryTableMap::TAX_ID => 2, TaxRuleCountryTableMap::POSITION => 3, TaxRuleCountryTableMap::CREATED_AT => 4, TaxRuleCountryTableMap::UPDATED_AT => 5, ),
        self::TYPE_RAW_COLNAME   => array('TAX_RULE_ID' => 0, 'COUNTRY_ID' => 1, 'TAX_ID' => 2, 'POSITION' => 3, 'CREATED_AT' => 4, 'UPDATED_AT' => 5, ),
        self::TYPE_FIELDNAME     => array('tax_rule_id' => 0, 'country_id' => 1, 'tax_id' => 2, 'position' => 3, 'created_at' => 4, 'updated_at' => 5, ),
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
        $this->setName('tax_rule_country');
        $this->setPhpName('TaxRuleCountry');
        $this->setClassName('\\Thelia\\Model\\TaxRuleCountry');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(false);
        // columns
        $this->addForeignPrimaryKey('TAX_RULE_ID', 'TaxRuleId', 'INTEGER' , 'tax_rule', 'ID', true, null, null);
        $this->addForeignPrimaryKey('COUNTRY_ID', 'CountryId', 'INTEGER' , 'country', 'ID', true, null, null);
        $this->addForeignPrimaryKey('TAX_ID', 'TaxId', 'INTEGER' , 'tax', 'ID', true, null, null);
        $this->addColumn('POSITION', 'Position', 'INTEGER', true, null, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Tax', '\\Thelia\\Model\\Tax', RelationMap::MANY_TO_ONE, array('tax_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('TaxRule', '\\Thelia\\Model\\TaxRule', RelationMap::MANY_TO_ONE, array('tax_rule_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Country', '\\Thelia\\Model\\Country', RelationMap::MANY_TO_ONE, array('country_id' => 'id', ), 'CASCADE', 'RESTRICT');
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
     * @param \Thelia\Model\TaxRuleCountry $obj A \Thelia\Model\TaxRuleCountry object.
     * @param string $key             (optional) key to use for instance map (for performance boost if key was already calculated externally).
     */
    public static function addInstanceToPool($obj, $key = null)
    {
        if (Propel::isInstancePoolingEnabled()) {
            if (null === $key) {
                $key = serialize(array((string) $obj->getTaxRuleId(), (string) $obj->getCountryId(), (string) $obj->getTaxId()));
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
     * @param mixed $value A \Thelia\Model\TaxRuleCountry object or a primary key value.
     */
    public static function removeInstanceFromPool($value)
    {
        if (Propel::isInstancePoolingEnabled() && null !== $value) {
            if (is_object($value) && $value instanceof \Thelia\Model\TaxRuleCountry) {
                $key = serialize(array((string) $value->getTaxRuleId(), (string) $value->getCountryId(), (string) $value->getTaxId()));

            } elseif (is_array($value) && count($value) === 3) {
                // assume we've been passed a primary key";
                $key = serialize(array((string) $value[0], (string) $value[1], (string) $value[2]));
            } elseif ($value instanceof Criteria) {
                self::$instances = [];

                return;
            } else {
                $e = new PropelException("Invalid value passed to removeInstanceFromPool().  Expected primary key or \Thelia\Model\TaxRuleCountry object; got " . (is_object($value) ? get_class($value) . ' object.' : var_export($value, true)));
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
        if ($row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('TaxRuleId', TableMap::TYPE_PHPNAME, $indexType)] === null && $row[TableMap::TYPE_NUM == $indexType ? 1 + $offset : static::translateFieldName('CountryId', TableMap::TYPE_PHPNAME, $indexType)] === null && $row[TableMap::TYPE_NUM == $indexType ? 2 + $offset : static::translateFieldName('TaxId', TableMap::TYPE_PHPNAME, $indexType)] === null) {
            return null;
        }

        return serialize(array((string) $row[TableMap::TYPE_NUM == $indexType ? 0 + $offset : static::translateFieldName('TaxRuleId', TableMap::TYPE_PHPNAME, $indexType)], (string) $row[TableMap::TYPE_NUM == $indexType ? 1 + $offset : static::translateFieldName('CountryId', TableMap::TYPE_PHPNAME, $indexType)], (string) $row[TableMap::TYPE_NUM == $indexType ? 2 + $offset : static::translateFieldName('TaxId', TableMap::TYPE_PHPNAME, $indexType)]));
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
        return $withPrefix ? TaxRuleCountryTableMap::CLASS_DEFAULT : TaxRuleCountryTableMap::OM_CLASS;
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
     * @return array (TaxRuleCountry object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = TaxRuleCountryTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = TaxRuleCountryTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + TaxRuleCountryTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = TaxRuleCountryTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            TaxRuleCountryTableMap::addInstanceToPool($obj, $key);
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
            $key = TaxRuleCountryTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = TaxRuleCountryTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                TaxRuleCountryTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(TaxRuleCountryTableMap::TAX_RULE_ID);
            $criteria->addSelectColumn(TaxRuleCountryTableMap::COUNTRY_ID);
            $criteria->addSelectColumn(TaxRuleCountryTableMap::TAX_ID);
            $criteria->addSelectColumn(TaxRuleCountryTableMap::POSITION);
            $criteria->addSelectColumn(TaxRuleCountryTableMap::CREATED_AT);
            $criteria->addSelectColumn(TaxRuleCountryTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.TAX_RULE_ID');
            $criteria->addSelectColumn($alias . '.COUNTRY_ID');
            $criteria->addSelectColumn($alias . '.TAX_ID');
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
        return Propel::getServiceContainer()->getDatabaseMap(TaxRuleCountryTableMap::DATABASE_NAME)->getTable(TaxRuleCountryTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(TaxRuleCountryTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(TaxRuleCountryTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new TaxRuleCountryTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a TaxRuleCountry or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or TaxRuleCountry object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(TaxRuleCountryTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\TaxRuleCountry) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(TaxRuleCountryTableMap::DATABASE_NAME);
            // primary key is composite; we therefore, expect
            // the primary key passed to be an array of pkey values
            if (count($values) == count($values, COUNT_RECURSIVE)) {
                // array is not multi-dimensional
                $values = array($values);
            }
            foreach ($values as $value) {
                $criterion = $criteria->getNewCriterion(TaxRuleCountryTableMap::TAX_RULE_ID, $value[0]);
                $criterion->addAnd($criteria->getNewCriterion(TaxRuleCountryTableMap::COUNTRY_ID, $value[1]));
                $criterion->addAnd($criteria->getNewCriterion(TaxRuleCountryTableMap::TAX_ID, $value[2]));
                $criteria->addOr($criterion);
            }
        }

        $query = TaxRuleCountryQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { TaxRuleCountryTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { TaxRuleCountryTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the tax_rule_country table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return TaxRuleCountryQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a TaxRuleCountry or Criteria object.
     *
     * @param mixed               $criteria Criteria or TaxRuleCountry object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(TaxRuleCountryTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from TaxRuleCountry object
        }


        // Set the correct dbName
        $query = TaxRuleCountryQuery::create()->mergeWith($criteria);

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

} // TaxRuleCountryTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
TaxRuleCountryTableMap::buildTableMap();
