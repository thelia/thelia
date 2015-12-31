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
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;


/**
 * This class defines the structure of the 'country' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class CountryTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.CountryTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'country';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\Country';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.Country';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 12;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 12;

    /**
     * the column name for the ID field
     */
    const ID = 'country.ID';

    /**
     * the column name for the VISIBLE field
     */
    const VISIBLE = 'country.VISIBLE';

    /**
     * the column name for the ISOCODE field
     */
    const ISOCODE = 'country.ISOCODE';

    /**
     * the column name for the ISOALPHA2 field
     */
    const ISOALPHA2 = 'country.ISOALPHA2';

    /**
     * the column name for the ISOALPHA3 field
     */
    const ISOALPHA3 = 'country.ISOALPHA3';

    /**
     * the column name for the HAS_STATES field
     */
    const HAS_STATES = 'country.HAS_STATES';

    /**
     * the column name for the NEED_ZIP_CODE field
     */
    const NEED_ZIP_CODE = 'country.NEED_ZIP_CODE';

    /**
     * the column name for the ZIP_CODE_FORMAT field
     */
    const ZIP_CODE_FORMAT = 'country.ZIP_CODE_FORMAT';

    /**
     * the column name for the BY_DEFAULT field
     */
    const BY_DEFAULT = 'country.BY_DEFAULT';

    /**
     * the column name for the SHOP_COUNTRY field
     */
    const SHOP_COUNTRY = 'country.SHOP_COUNTRY';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'country.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'country.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('Id', 'Visible', 'Isocode', 'Isoalpha2', 'Isoalpha3', 'HasStates', 'NeedZipCode', 'ZipCodeFormat', 'ByDefault', 'ShopCountry', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'visible', 'isocode', 'isoalpha2', 'isoalpha3', 'hasStates', 'needZipCode', 'zipCodeFormat', 'byDefault', 'shopCountry', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(CountryTableMap::ID, CountryTableMap::VISIBLE, CountryTableMap::ISOCODE, CountryTableMap::ISOALPHA2, CountryTableMap::ISOALPHA3, CountryTableMap::HAS_STATES, CountryTableMap::NEED_ZIP_CODE, CountryTableMap::ZIP_CODE_FORMAT, CountryTableMap::BY_DEFAULT, CountryTableMap::SHOP_COUNTRY, CountryTableMap::CREATED_AT, CountryTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'VISIBLE', 'ISOCODE', 'ISOALPHA2', 'ISOALPHA3', 'HAS_STATES', 'NEED_ZIP_CODE', 'ZIP_CODE_FORMAT', 'BY_DEFAULT', 'SHOP_COUNTRY', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('id', 'visible', 'isocode', 'isoalpha2', 'isoalpha3', 'has_states', 'need_zip_code', 'zip_code_format', 'by_default', 'shop_country', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'Visible' => 1, 'Isocode' => 2, 'Isoalpha2' => 3, 'Isoalpha3' => 4, 'HasStates' => 5, 'NeedZipCode' => 6, 'ZipCodeFormat' => 7, 'ByDefault' => 8, 'ShopCountry' => 9, 'CreatedAt' => 10, 'UpdatedAt' => 11, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'visible' => 1, 'isocode' => 2, 'isoalpha2' => 3, 'isoalpha3' => 4, 'hasStates' => 5, 'needZipCode' => 6, 'zipCodeFormat' => 7, 'byDefault' => 8, 'shopCountry' => 9, 'createdAt' => 10, 'updatedAt' => 11, ),
        self::TYPE_COLNAME       => array(CountryTableMap::ID => 0, CountryTableMap::VISIBLE => 1, CountryTableMap::ISOCODE => 2, CountryTableMap::ISOALPHA2 => 3, CountryTableMap::ISOALPHA3 => 4, CountryTableMap::HAS_STATES => 5, CountryTableMap::NEED_ZIP_CODE => 6, CountryTableMap::ZIP_CODE_FORMAT => 7, CountryTableMap::BY_DEFAULT => 8, CountryTableMap::SHOP_COUNTRY => 9, CountryTableMap::CREATED_AT => 10, CountryTableMap::UPDATED_AT => 11, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'VISIBLE' => 1, 'ISOCODE' => 2, 'ISOALPHA2' => 3, 'ISOALPHA3' => 4, 'HAS_STATES' => 5, 'NEED_ZIP_CODE' => 6, 'ZIP_CODE_FORMAT' => 7, 'BY_DEFAULT' => 8, 'SHOP_COUNTRY' => 9, 'CREATED_AT' => 10, 'UPDATED_AT' => 11, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'visible' => 1, 'isocode' => 2, 'isoalpha2' => 3, 'isoalpha3' => 4, 'has_states' => 5, 'need_zip_code' => 6, 'zip_code_format' => 7, 'by_default' => 8, 'shop_country' => 9, 'created_at' => 10, 'updated_at' => 11, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, )
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
        $this->setName('country');
        $this->setPhpName('Country');
        $this->setClassName('\\Thelia\\Model\\Country');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('VISIBLE', 'Visible', 'TINYINT', true, null, 0);
        $this->addColumn('ISOCODE', 'Isocode', 'VARCHAR', true, 4, null);
        $this->addColumn('ISOALPHA2', 'Isoalpha2', 'VARCHAR', false, 2, null);
        $this->addColumn('ISOALPHA3', 'Isoalpha3', 'VARCHAR', false, 4, null);
        $this->addColumn('HAS_STATES', 'HasStates', 'TINYINT', false, null, 0);
        $this->addColumn('NEED_ZIP_CODE', 'NeedZipCode', 'TINYINT', false, null, 0);
        $this->addColumn('ZIP_CODE_FORMAT', 'ZipCodeFormat', 'VARCHAR', false, 20, null);
        $this->addColumn('BY_DEFAULT', 'ByDefault', 'TINYINT', false, null, 0);
        $this->addColumn('SHOP_COUNTRY', 'ShopCountry', 'BOOLEAN', true, 1, false);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('State', '\\Thelia\\Model\\State', RelationMap::ONE_TO_MANY, array('id' => 'country_id', ), 'CASCADE', 'RESTRICT', 'States');
        $this->addRelation('TaxRuleCountry', '\\Thelia\\Model\\TaxRuleCountry', RelationMap::ONE_TO_MANY, array('id' => 'country_id', ), 'CASCADE', 'RESTRICT', 'TaxRuleCountries');
        $this->addRelation('Address', '\\Thelia\\Model\\Address', RelationMap::ONE_TO_MANY, array('id' => 'country_id', ), 'RESTRICT', 'RESTRICT', 'Addresses');
        $this->addRelation('OrderAddress', '\\Thelia\\Model\\OrderAddress', RelationMap::ONE_TO_MANY, array('id' => 'country_id', ), 'RESTRICT', 'RESTRICT', 'OrderAddresses');
        $this->addRelation('CouponCountry', '\\Thelia\\Model\\CouponCountry', RelationMap::ONE_TO_MANY, array('id' => 'country_id', ), 'CASCADE', null, 'CouponCountries');
        $this->addRelation('OrderCouponCountry', '\\Thelia\\Model\\OrderCouponCountry', RelationMap::ONE_TO_MANY, array('id' => 'country_id', ), 'CASCADE', null, 'OrderCouponCountries');
        $this->addRelation('CountryArea', '\\Thelia\\Model\\CountryArea', RelationMap::ONE_TO_MANY, array('id' => 'country_id', ), 'CASCADE', 'RESTRICT', 'CountryAreas');
        $this->addRelation('CountryI18n', '\\Thelia\\Model\\CountryI18n', RelationMap::ONE_TO_MANY, array('id' => 'id', ), 'CASCADE', null, 'CountryI18ns');
        $this->addRelation('Coupon', '\\Thelia\\Model\\Coupon', RelationMap::MANY_TO_MANY, array(), 'CASCADE', null, 'Coupons');
        $this->addRelation('OrderCoupon', '\\Thelia\\Model\\OrderCoupon', RelationMap::MANY_TO_MANY, array(), null, null, 'OrderCoupons');
        $this->addRelation('Area', '\\Thelia\\Model\\Area', RelationMap::MANY_TO_MANY, array(), 'CASCADE', 'RESTRICT', 'Areas');
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
            'i18n' => array('i18n_table' => '%TABLE%_i18n', 'i18n_phpname' => '%PHPNAME%I18n', 'i18n_columns' => 'title, description, chapo, postscriptum', 'locale_column' => 'locale', 'locale_length' => '5', 'default_locale' => '', 'locale_alias' => '', ),
        );
    } // getBehaviors()
    /**
     * Method to invalidate the instance pool of all tables related to country     * by a foreign key with ON DELETE CASCADE
     */
    public static function clearRelatedInstancePool()
    {
        // Invalidate objects in ".$this->getClassNameFromBuilder($joinedTableTableMapBuilder)." instance pool,
        // since one or more of them may be deleted by ON DELETE CASCADE/SETNULL rule.
                StateTableMap::clearInstancePool();
                TaxRuleCountryTableMap::clearInstancePool();
                CouponCountryTableMap::clearInstancePool();
                OrderCouponCountryTableMap::clearInstancePool();
                CountryAreaTableMap::clearInstancePool();
                CountryI18nTableMap::clearInstancePool();
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
        return $withPrefix ? CountryTableMap::CLASS_DEFAULT : CountryTableMap::OM_CLASS;
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
     * @return array (Country object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = CountryTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = CountryTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + CountryTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = CountryTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            CountryTableMap::addInstanceToPool($obj, $key);
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
            $key = CountryTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = CountryTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                CountryTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(CountryTableMap::ID);
            $criteria->addSelectColumn(CountryTableMap::VISIBLE);
            $criteria->addSelectColumn(CountryTableMap::ISOCODE);
            $criteria->addSelectColumn(CountryTableMap::ISOALPHA2);
            $criteria->addSelectColumn(CountryTableMap::ISOALPHA3);
            $criteria->addSelectColumn(CountryTableMap::HAS_STATES);
            $criteria->addSelectColumn(CountryTableMap::NEED_ZIP_CODE);
            $criteria->addSelectColumn(CountryTableMap::ZIP_CODE_FORMAT);
            $criteria->addSelectColumn(CountryTableMap::BY_DEFAULT);
            $criteria->addSelectColumn(CountryTableMap::SHOP_COUNTRY);
            $criteria->addSelectColumn(CountryTableMap::CREATED_AT);
            $criteria->addSelectColumn(CountryTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.VISIBLE');
            $criteria->addSelectColumn($alias . '.ISOCODE');
            $criteria->addSelectColumn($alias . '.ISOALPHA2');
            $criteria->addSelectColumn($alias . '.ISOALPHA3');
            $criteria->addSelectColumn($alias . '.HAS_STATES');
            $criteria->addSelectColumn($alias . '.NEED_ZIP_CODE');
            $criteria->addSelectColumn($alias . '.ZIP_CODE_FORMAT');
            $criteria->addSelectColumn($alias . '.BY_DEFAULT');
            $criteria->addSelectColumn($alias . '.SHOP_COUNTRY');
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
        return Propel::getServiceContainer()->getDatabaseMap(CountryTableMap::DATABASE_NAME)->getTable(CountryTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(CountryTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(CountryTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new CountryTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a Country or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Country object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(CountryTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\Country) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(CountryTableMap::DATABASE_NAME);
            $criteria->add(CountryTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = CountryQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { CountryTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { CountryTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the country table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return CountryQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Country or Criteria object.
     *
     * @param mixed               $criteria Criteria or Country object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CountryTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Country object
        }

        if ($criteria->containsKey(CountryTableMap::ID) && $criteria->keyContainsValue(CountryTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.CountryTableMap::ID.')');
        }


        // Set the correct dbName
        $query = CountryQuery::create()->mergeWith($criteria);

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

} // CountryTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
CountryTableMap::buildTableMap();
