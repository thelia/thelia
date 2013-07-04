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
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;


/**
 * This class defines the structure of the 'customer' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class CustomerTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.CustomerTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'customer';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\Customer';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.Customer';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 24;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 24;

    /**
     * the column name for the ID field
     */
    const ID = 'customer.ID';

    /**
     * the column name for the REF field
     */
    const REF = 'customer.REF';

    /**
     * the column name for the CUSTOMER_TITLE_ID field
     */
    const CUSTOMER_TITLE_ID = 'customer.CUSTOMER_TITLE_ID';

    /**
     * the column name for the COMPANY field
     */
    const COMPANY = 'customer.COMPANY';

    /**
     * the column name for the FIRSTNAME field
     */
    const FIRSTNAME = 'customer.FIRSTNAME';

    /**
     * the column name for the LASTNAME field
     */
    const LASTNAME = 'customer.LASTNAME';

    /**
     * the column name for the ADDRESS1 field
     */
    const ADDRESS1 = 'customer.ADDRESS1';

    /**
     * the column name for the ADDRESS2 field
     */
    const ADDRESS2 = 'customer.ADDRESS2';

    /**
     * the column name for the ADDRESS3 field
     */
    const ADDRESS3 = 'customer.ADDRESS3';

    /**
     * the column name for the ZIPCODE field
     */
    const ZIPCODE = 'customer.ZIPCODE';

    /**
     * the column name for the CITY field
     */
    const CITY = 'customer.CITY';

    /**
     * the column name for the COUNTRY_ID field
     */
    const COUNTRY_ID = 'customer.COUNTRY_ID';

    /**
     * the column name for the PHONE field
     */
    const PHONE = 'customer.PHONE';

    /**
     * the column name for the CELLPHONE field
     */
    const CELLPHONE = 'customer.CELLPHONE';

    /**
     * the column name for the EMAIL field
     */
    const EMAIL = 'customer.EMAIL';

    /**
     * the column name for the PASSWORD field
     */
    const PASSWORD = 'customer.PASSWORD';

    /**
     * the column name for the ALGO field
     */
    const ALGO = 'customer.ALGO';

    /**
     * the column name for the SALT field
     */
    const SALT = 'customer.SALT';

    /**
     * the column name for the RESELLER field
     */
    const RESELLER = 'customer.RESELLER';

    /**
     * the column name for the LANG field
     */
    const LANG = 'customer.LANG';

    /**
     * the column name for the SPONSOR field
     */
    const SPONSOR = 'customer.SPONSOR';

    /**
     * the column name for the DISCOUNT field
     */
    const DISCOUNT = 'customer.DISCOUNT';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'customer.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'customer.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('Id', 'Ref', 'CustomerTitleId', 'Company', 'Firstname', 'Lastname', 'Address1', 'Address2', 'Address3', 'Zipcode', 'City', 'CountryId', 'Phone', 'Cellphone', 'Email', 'Password', 'Algo', 'Salt', 'Reseller', 'Lang', 'Sponsor', 'Discount', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'ref', 'customerTitleId', 'company', 'firstname', 'lastname', 'address1', 'address2', 'address3', 'zipcode', 'city', 'countryId', 'phone', 'cellphone', 'email', 'password', 'algo', 'salt', 'reseller', 'lang', 'sponsor', 'discount', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(CustomerTableMap::ID, CustomerTableMap::REF, CustomerTableMap::CUSTOMER_TITLE_ID, CustomerTableMap::COMPANY, CustomerTableMap::FIRSTNAME, CustomerTableMap::LASTNAME, CustomerTableMap::ADDRESS1, CustomerTableMap::ADDRESS2, CustomerTableMap::ADDRESS3, CustomerTableMap::ZIPCODE, CustomerTableMap::CITY, CustomerTableMap::COUNTRY_ID, CustomerTableMap::PHONE, CustomerTableMap::CELLPHONE, CustomerTableMap::EMAIL, CustomerTableMap::PASSWORD, CustomerTableMap::ALGO, CustomerTableMap::SALT, CustomerTableMap::RESELLER, CustomerTableMap::LANG, CustomerTableMap::SPONSOR, CustomerTableMap::DISCOUNT, CustomerTableMap::CREATED_AT, CustomerTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'REF', 'CUSTOMER_TITLE_ID', 'COMPANY', 'FIRSTNAME', 'LASTNAME', 'ADDRESS1', 'ADDRESS2', 'ADDRESS3', 'ZIPCODE', 'CITY', 'COUNTRY_ID', 'PHONE', 'CELLPHONE', 'EMAIL', 'PASSWORD', 'ALGO', 'SALT', 'RESELLER', 'LANG', 'SPONSOR', 'DISCOUNT', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('id', 'ref', 'customer_title_id', 'company', 'firstname', 'lastname', 'address1', 'address2', 'address3', 'zipcode', 'city', 'country_id', 'phone', 'cellphone', 'email', 'password', 'algo', 'salt', 'reseller', 'lang', 'sponsor', 'discount', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'Ref' => 1, 'CustomerTitleId' => 2, 'Company' => 3, 'Firstname' => 4, 'Lastname' => 5, 'Address1' => 6, 'Address2' => 7, 'Address3' => 8, 'Zipcode' => 9, 'City' => 10, 'CountryId' => 11, 'Phone' => 12, 'Cellphone' => 13, 'Email' => 14, 'Password' => 15, 'Algo' => 16, 'Salt' => 17, 'Reseller' => 18, 'Lang' => 19, 'Sponsor' => 20, 'Discount' => 21, 'CreatedAt' => 22, 'UpdatedAt' => 23, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'ref' => 1, 'customerTitleId' => 2, 'company' => 3, 'firstname' => 4, 'lastname' => 5, 'address1' => 6, 'address2' => 7, 'address3' => 8, 'zipcode' => 9, 'city' => 10, 'countryId' => 11, 'phone' => 12, 'cellphone' => 13, 'email' => 14, 'password' => 15, 'algo' => 16, 'salt' => 17, 'reseller' => 18, 'lang' => 19, 'sponsor' => 20, 'discount' => 21, 'createdAt' => 22, 'updatedAt' => 23, ),
        self::TYPE_COLNAME       => array(CustomerTableMap::ID => 0, CustomerTableMap::REF => 1, CustomerTableMap::CUSTOMER_TITLE_ID => 2, CustomerTableMap::COMPANY => 3, CustomerTableMap::FIRSTNAME => 4, CustomerTableMap::LASTNAME => 5, CustomerTableMap::ADDRESS1 => 6, CustomerTableMap::ADDRESS2 => 7, CustomerTableMap::ADDRESS3 => 8, CustomerTableMap::ZIPCODE => 9, CustomerTableMap::CITY => 10, CustomerTableMap::COUNTRY_ID => 11, CustomerTableMap::PHONE => 12, CustomerTableMap::CELLPHONE => 13, CustomerTableMap::EMAIL => 14, CustomerTableMap::PASSWORD => 15, CustomerTableMap::ALGO => 16, CustomerTableMap::SALT => 17, CustomerTableMap::RESELLER => 18, CustomerTableMap::LANG => 19, CustomerTableMap::SPONSOR => 20, CustomerTableMap::DISCOUNT => 21, CustomerTableMap::CREATED_AT => 22, CustomerTableMap::UPDATED_AT => 23, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'REF' => 1, 'CUSTOMER_TITLE_ID' => 2, 'COMPANY' => 3, 'FIRSTNAME' => 4, 'LASTNAME' => 5, 'ADDRESS1' => 6, 'ADDRESS2' => 7, 'ADDRESS3' => 8, 'ZIPCODE' => 9, 'CITY' => 10, 'COUNTRY_ID' => 11, 'PHONE' => 12, 'CELLPHONE' => 13, 'EMAIL' => 14, 'PASSWORD' => 15, 'ALGO' => 16, 'SALT' => 17, 'RESELLER' => 18, 'LANG' => 19, 'SPONSOR' => 20, 'DISCOUNT' => 21, 'CREATED_AT' => 22, 'UPDATED_AT' => 23, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'ref' => 1, 'customer_title_id' => 2, 'company' => 3, 'firstname' => 4, 'lastname' => 5, 'address1' => 6, 'address2' => 7, 'address3' => 8, 'zipcode' => 9, 'city' => 10, 'country_id' => 11, 'phone' => 12, 'cellphone' => 13, 'email' => 14, 'password' => 15, 'algo' => 16, 'salt' => 17, 'reseller' => 18, 'lang' => 19, 'sponsor' => 20, 'discount' => 21, 'created_at' => 22, 'updated_at' => 23, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, )
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
        $this->setName('customer');
        $this->setPhpName('Customer');
        $this->setClassName('\\Thelia\\Model\\Customer');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('REF', 'Ref', 'VARCHAR', true, 50, null);
        $this->addForeignKey('CUSTOMER_TITLE_ID', 'CustomerTitleId', 'INTEGER', 'customer_title', 'ID', false, null, null);
        $this->addColumn('COMPANY', 'Company', 'VARCHAR', false, 255, null);
        $this->addColumn('FIRSTNAME', 'Firstname', 'VARCHAR', true, 255, null);
        $this->addColumn('LASTNAME', 'Lastname', 'VARCHAR', true, 255, null);
        $this->addColumn('ADDRESS1', 'Address1', 'VARCHAR', true, 255, null);
        $this->addColumn('ADDRESS2', 'Address2', 'VARCHAR', false, 255, null);
        $this->addColumn('ADDRESS3', 'Address3', 'VARCHAR', false, 255, null);
        $this->addColumn('ZIPCODE', 'Zipcode', 'VARCHAR', false, 10, null);
        $this->addColumn('CITY', 'City', 'VARCHAR', true, 255, null);
        $this->addColumn('COUNTRY_ID', 'CountryId', 'INTEGER', true, null, null);
        $this->addColumn('PHONE', 'Phone', 'VARCHAR', false, 20, null);
        $this->addColumn('CELLPHONE', 'Cellphone', 'VARCHAR', false, 20, null);
        $this->addColumn('EMAIL', 'Email', 'VARCHAR', false, 50, null);
        $this->addColumn('PASSWORD', 'Password', 'VARCHAR', false, 255, null);
        $this->addColumn('ALGO', 'Algo', 'VARCHAR', false, 128, null);
        $this->addColumn('SALT', 'Salt', 'VARCHAR', false, 128, null);
        $this->addColumn('RESELLER', 'Reseller', 'TINYINT', false, null, null);
        $this->addColumn('LANG', 'Lang', 'VARCHAR', false, 10, null);
        $this->addColumn('SPONSOR', 'Sponsor', 'VARCHAR', false, 50, null);
        $this->addColumn('DISCOUNT', 'Discount', 'FLOAT', false, null, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('CustomerTitle', '\\Thelia\\Model\\CustomerTitle', RelationMap::MANY_TO_ONE, array('customer_title_id' => 'id', ), 'SET NULL', 'RESTRICT');
        $this->addRelation('Address', '\\Thelia\\Model\\Address', RelationMap::ONE_TO_MANY, array('id' => 'customer_id', ), 'CASCADE', 'RESTRICT', 'Addresses');
        $this->addRelation('Order', '\\Thelia\\Model\\Order', RelationMap::ONE_TO_MANY, array('id' => 'customer_id', ), 'CASCADE', 'RESTRICT', 'Orders');
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
     * Method to invalidate the instance pool of all tables related to customer     * by a foreign key with ON DELETE CASCADE
     */
    public static function clearRelatedInstancePool()
    {
        // Invalidate objects in ".$this->getClassNameFromBuilder($joinedTableTableMapBuilder)." instance pool,
        // since one or more of them may be deleted by ON DELETE CASCADE/SETNULL rule.
                AddressTableMap::clearInstancePool();
                OrderTableMap::clearInstancePool();
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
        return $withPrefix ? CustomerTableMap::CLASS_DEFAULT : CustomerTableMap::OM_CLASS;
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
     * @return array (Customer object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = CustomerTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = CustomerTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + CustomerTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = CustomerTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            CustomerTableMap::addInstanceToPool($obj, $key);
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
            $key = CustomerTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = CustomerTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                CustomerTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(CustomerTableMap::ID);
            $criteria->addSelectColumn(CustomerTableMap::REF);
            $criteria->addSelectColumn(CustomerTableMap::CUSTOMER_TITLE_ID);
            $criteria->addSelectColumn(CustomerTableMap::COMPANY);
            $criteria->addSelectColumn(CustomerTableMap::FIRSTNAME);
            $criteria->addSelectColumn(CustomerTableMap::LASTNAME);
            $criteria->addSelectColumn(CustomerTableMap::ADDRESS1);
            $criteria->addSelectColumn(CustomerTableMap::ADDRESS2);
            $criteria->addSelectColumn(CustomerTableMap::ADDRESS3);
            $criteria->addSelectColumn(CustomerTableMap::ZIPCODE);
            $criteria->addSelectColumn(CustomerTableMap::CITY);
            $criteria->addSelectColumn(CustomerTableMap::COUNTRY_ID);
            $criteria->addSelectColumn(CustomerTableMap::PHONE);
            $criteria->addSelectColumn(CustomerTableMap::CELLPHONE);
            $criteria->addSelectColumn(CustomerTableMap::EMAIL);
            $criteria->addSelectColumn(CustomerTableMap::PASSWORD);
            $criteria->addSelectColumn(CustomerTableMap::ALGO);
            $criteria->addSelectColumn(CustomerTableMap::SALT);
            $criteria->addSelectColumn(CustomerTableMap::RESELLER);
            $criteria->addSelectColumn(CustomerTableMap::LANG);
            $criteria->addSelectColumn(CustomerTableMap::SPONSOR);
            $criteria->addSelectColumn(CustomerTableMap::DISCOUNT);
            $criteria->addSelectColumn(CustomerTableMap::CREATED_AT);
            $criteria->addSelectColumn(CustomerTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.REF');
            $criteria->addSelectColumn($alias . '.CUSTOMER_TITLE_ID');
            $criteria->addSelectColumn($alias . '.COMPANY');
            $criteria->addSelectColumn($alias . '.FIRSTNAME');
            $criteria->addSelectColumn($alias . '.LASTNAME');
            $criteria->addSelectColumn($alias . '.ADDRESS1');
            $criteria->addSelectColumn($alias . '.ADDRESS2');
            $criteria->addSelectColumn($alias . '.ADDRESS3');
            $criteria->addSelectColumn($alias . '.ZIPCODE');
            $criteria->addSelectColumn($alias . '.CITY');
            $criteria->addSelectColumn($alias . '.COUNTRY_ID');
            $criteria->addSelectColumn($alias . '.PHONE');
            $criteria->addSelectColumn($alias . '.CELLPHONE');
            $criteria->addSelectColumn($alias . '.EMAIL');
            $criteria->addSelectColumn($alias . '.PASSWORD');
            $criteria->addSelectColumn($alias . '.ALGO');
            $criteria->addSelectColumn($alias . '.SALT');
            $criteria->addSelectColumn($alias . '.RESELLER');
            $criteria->addSelectColumn($alias . '.LANG');
            $criteria->addSelectColumn($alias . '.SPONSOR');
            $criteria->addSelectColumn($alias . '.DISCOUNT');
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
        return Propel::getServiceContainer()->getDatabaseMap(CustomerTableMap::DATABASE_NAME)->getTable(CustomerTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(CustomerTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(CustomerTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new CustomerTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a Customer or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Customer object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(CustomerTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\Customer) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(CustomerTableMap::DATABASE_NAME);
            $criteria->add(CustomerTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = CustomerQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { CustomerTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { CustomerTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the customer table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return CustomerQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Customer or Criteria object.
     *
     * @param mixed               $criteria Criteria or Customer object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(CustomerTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Customer object
        }

        if ($criteria->containsKey(CustomerTableMap::ID) && $criteria->keyContainsValue(CustomerTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.CustomerTableMap::ID.')');
        }


        // Set the correct dbName
        $query = CustomerQuery::create()->mergeWith($criteria);

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

} // CustomerTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
CustomerTableMap::buildTableMap();
