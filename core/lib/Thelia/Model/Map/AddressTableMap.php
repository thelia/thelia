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
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;


/**
 * This class defines the structure of the 'address' table.
 *
 *
 *
 * This map class is used by Propel to do runtime db structure discovery.
 * For example, the createSelectSql() method checks the type of a given column used in an
 * ORDER BY clause to know whether it needs to apply SQL to make the ORDER BY case-insensitive
 * (i.e. if it's a text column type).
 *
 */
class AddressTableMap extends TableMap
{
    use InstancePoolTrait;
    use TableMapTrait;
    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.Map.AddressTableMap';

    /**
     * The default database name for this class
     */
    const DATABASE_NAME = 'thelia';

    /**
     * The table name for this class
     */
    const TABLE_NAME = 'address';

    /**
     * The related Propel class for this table
     */
    const OM_CLASS = '\\Thelia\\Model\\Address';

    /**
     * A class that can be returned by this tableMap
     */
    const CLASS_DEFAULT = 'Thelia.Model.Address';

    /**
     * The total number of columns
     */
    const NUM_COLUMNS = 19;

    /**
     * The number of lazy-loaded columns
     */
    const NUM_LAZY_LOAD_COLUMNS = 0;

    /**
     * The number of columns to hydrate (NUM_COLUMNS - NUM_LAZY_LOAD_COLUMNS)
     */
    const NUM_HYDRATE_COLUMNS = 19;

    /**
     * the column name for the ID field
     */
    const ID = 'address.ID';

    /**
     * the column name for the LABEL field
     */
    const LABEL = 'address.LABEL';

    /**
     * the column name for the CUSTOMER_ID field
     */
    const CUSTOMER_ID = 'address.CUSTOMER_ID';

    /**
     * the column name for the TITLE_ID field
     */
    const TITLE_ID = 'address.TITLE_ID';

    /**
     * the column name for the COMPANY field
     */
    const COMPANY = 'address.COMPANY';

    /**
     * the column name for the FIRSTNAME field
     */
    const FIRSTNAME = 'address.FIRSTNAME';

    /**
     * the column name for the LASTNAME field
     */
    const LASTNAME = 'address.LASTNAME';

    /**
     * the column name for the ADDRESS1 field
     */
    const ADDRESS1 = 'address.ADDRESS1';

    /**
     * the column name for the ADDRESS2 field
     */
    const ADDRESS2 = 'address.ADDRESS2';

    /**
     * the column name for the ADDRESS3 field
     */
    const ADDRESS3 = 'address.ADDRESS3';

    /**
     * the column name for the ZIPCODE field
     */
    const ZIPCODE = 'address.ZIPCODE';

    /**
     * the column name for the CITY field
     */
    const CITY = 'address.CITY';

    /**
     * the column name for the COUNTRY_ID field
     */
    const COUNTRY_ID = 'address.COUNTRY_ID';

    /**
     * the column name for the STATE_ID field
     */
    const STATE_ID = 'address.STATE_ID';

    /**
     * the column name for the PHONE field
     */
    const PHONE = 'address.PHONE';

    /**
     * the column name for the CELLPHONE field
     */
    const CELLPHONE = 'address.CELLPHONE';

    /**
     * the column name for the IS_DEFAULT field
     */
    const IS_DEFAULT = 'address.IS_DEFAULT';

    /**
     * the column name for the CREATED_AT field
     */
    const CREATED_AT = 'address.CREATED_AT';

    /**
     * the column name for the UPDATED_AT field
     */
    const UPDATED_AT = 'address.UPDATED_AT';

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
        self::TYPE_PHPNAME       => array('Id', 'Label', 'CustomerId', 'TitleId', 'Company', 'Firstname', 'Lastname', 'Address1', 'Address2', 'Address3', 'Zipcode', 'City', 'CountryId', 'StateId', 'Phone', 'Cellphone', 'IsDefault', 'CreatedAt', 'UpdatedAt', ),
        self::TYPE_STUDLYPHPNAME => array('id', 'label', 'customerId', 'titleId', 'company', 'firstname', 'lastname', 'address1', 'address2', 'address3', 'zipcode', 'city', 'countryId', 'stateId', 'phone', 'cellphone', 'isDefault', 'createdAt', 'updatedAt', ),
        self::TYPE_COLNAME       => array(AddressTableMap::ID, AddressTableMap::LABEL, AddressTableMap::CUSTOMER_ID, AddressTableMap::TITLE_ID, AddressTableMap::COMPANY, AddressTableMap::FIRSTNAME, AddressTableMap::LASTNAME, AddressTableMap::ADDRESS1, AddressTableMap::ADDRESS2, AddressTableMap::ADDRESS3, AddressTableMap::ZIPCODE, AddressTableMap::CITY, AddressTableMap::COUNTRY_ID, AddressTableMap::STATE_ID, AddressTableMap::PHONE, AddressTableMap::CELLPHONE, AddressTableMap::IS_DEFAULT, AddressTableMap::CREATED_AT, AddressTableMap::UPDATED_AT, ),
        self::TYPE_RAW_COLNAME   => array('ID', 'LABEL', 'CUSTOMER_ID', 'TITLE_ID', 'COMPANY', 'FIRSTNAME', 'LASTNAME', 'ADDRESS1', 'ADDRESS2', 'ADDRESS3', 'ZIPCODE', 'CITY', 'COUNTRY_ID', 'STATE_ID', 'PHONE', 'CELLPHONE', 'IS_DEFAULT', 'CREATED_AT', 'UPDATED_AT', ),
        self::TYPE_FIELDNAME     => array('id', 'label', 'customer_id', 'title_id', 'company', 'firstname', 'lastname', 'address1', 'address2', 'address3', 'zipcode', 'city', 'country_id', 'state_id', 'phone', 'cellphone', 'is_default', 'created_at', 'updated_at', ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, )
    );

    /**
     * holds an array of keys for quick access to the fieldnames array
     *
     * first dimension keys are the type constants
     * e.g. self::$fieldKeys[self::TYPE_PHPNAME]['Id'] = 0
     */
    protected static $fieldKeys = array (
        self::TYPE_PHPNAME       => array('Id' => 0, 'Label' => 1, 'CustomerId' => 2, 'TitleId' => 3, 'Company' => 4, 'Firstname' => 5, 'Lastname' => 6, 'Address1' => 7, 'Address2' => 8, 'Address3' => 9, 'Zipcode' => 10, 'City' => 11, 'CountryId' => 12, 'StateId' => 13, 'Phone' => 14, 'Cellphone' => 15, 'IsDefault' => 16, 'CreatedAt' => 17, 'UpdatedAt' => 18, ),
        self::TYPE_STUDLYPHPNAME => array('id' => 0, 'label' => 1, 'customerId' => 2, 'titleId' => 3, 'company' => 4, 'firstname' => 5, 'lastname' => 6, 'address1' => 7, 'address2' => 8, 'address3' => 9, 'zipcode' => 10, 'city' => 11, 'countryId' => 12, 'stateId' => 13, 'phone' => 14, 'cellphone' => 15, 'isDefault' => 16, 'createdAt' => 17, 'updatedAt' => 18, ),
        self::TYPE_COLNAME       => array(AddressTableMap::ID => 0, AddressTableMap::LABEL => 1, AddressTableMap::CUSTOMER_ID => 2, AddressTableMap::TITLE_ID => 3, AddressTableMap::COMPANY => 4, AddressTableMap::FIRSTNAME => 5, AddressTableMap::LASTNAME => 6, AddressTableMap::ADDRESS1 => 7, AddressTableMap::ADDRESS2 => 8, AddressTableMap::ADDRESS3 => 9, AddressTableMap::ZIPCODE => 10, AddressTableMap::CITY => 11, AddressTableMap::COUNTRY_ID => 12, AddressTableMap::STATE_ID => 13, AddressTableMap::PHONE => 14, AddressTableMap::CELLPHONE => 15, AddressTableMap::IS_DEFAULT => 16, AddressTableMap::CREATED_AT => 17, AddressTableMap::UPDATED_AT => 18, ),
        self::TYPE_RAW_COLNAME   => array('ID' => 0, 'LABEL' => 1, 'CUSTOMER_ID' => 2, 'TITLE_ID' => 3, 'COMPANY' => 4, 'FIRSTNAME' => 5, 'LASTNAME' => 6, 'ADDRESS1' => 7, 'ADDRESS2' => 8, 'ADDRESS3' => 9, 'ZIPCODE' => 10, 'CITY' => 11, 'COUNTRY_ID' => 12, 'STATE_ID' => 13, 'PHONE' => 14, 'CELLPHONE' => 15, 'IS_DEFAULT' => 16, 'CREATED_AT' => 17, 'UPDATED_AT' => 18, ),
        self::TYPE_FIELDNAME     => array('id' => 0, 'label' => 1, 'customer_id' => 2, 'title_id' => 3, 'company' => 4, 'firstname' => 5, 'lastname' => 6, 'address1' => 7, 'address2' => 8, 'address3' => 9, 'zipcode' => 10, 'city' => 11, 'country_id' => 12, 'state_id' => 13, 'phone' => 14, 'cellphone' => 15, 'is_default' => 16, 'created_at' => 17, 'updated_at' => 18, ),
        self::TYPE_NUM           => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, )
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
        $this->setName('address');
        $this->setPhpName('Address');
        $this->setClassName('\\Thelia\\Model\\Address');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('LABEL', 'Label', 'VARCHAR', false, 255, null);
        $this->addForeignKey('CUSTOMER_ID', 'CustomerId', 'INTEGER', 'customer', 'ID', true, null, null);
        $this->addForeignKey('TITLE_ID', 'TitleId', 'INTEGER', 'customer_title', 'ID', true, null, null);
        $this->addColumn('COMPANY', 'Company', 'VARCHAR', false, 255, null);
        $this->addColumn('FIRSTNAME', 'Firstname', 'VARCHAR', true, 255, null);
        $this->addColumn('LASTNAME', 'Lastname', 'VARCHAR', true, 255, null);
        $this->addColumn('ADDRESS1', 'Address1', 'VARCHAR', true, 255, null);
        $this->addColumn('ADDRESS2', 'Address2', 'VARCHAR', true, 255, null);
        $this->addColumn('ADDRESS3', 'Address3', 'VARCHAR', true, 255, null);
        $this->addColumn('ZIPCODE', 'Zipcode', 'VARCHAR', true, 10, null);
        $this->addColumn('CITY', 'City', 'VARCHAR', true, 255, null);
        $this->addForeignKey('COUNTRY_ID', 'CountryId', 'INTEGER', 'country', 'ID', true, null, null);
        $this->addForeignKey('STATE_ID', 'StateId', 'INTEGER', 'state', 'ID', false, null, null);
        $this->addColumn('PHONE', 'Phone', 'VARCHAR', false, 20, null);
        $this->addColumn('CELLPHONE', 'Cellphone', 'VARCHAR', false, 20, null);
        $this->addColumn('IS_DEFAULT', 'IsDefault', 'TINYINT', false, null, 0);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Customer', '\\Thelia\\Model\\Customer', RelationMap::MANY_TO_ONE, array('customer_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('CustomerTitle', '\\Thelia\\Model\\CustomerTitle', RelationMap::MANY_TO_ONE, array('title_id' => 'id', ), 'RESTRICT', 'RESTRICT');
        $this->addRelation('Country', '\\Thelia\\Model\\Country', RelationMap::MANY_TO_ONE, array('country_id' => 'id', ), 'RESTRICT', 'RESTRICT');
        $this->addRelation('State', '\\Thelia\\Model\\State', RelationMap::MANY_TO_ONE, array('state_id' => 'id', ), 'RESTRICT', 'RESTRICT');
        $this->addRelation('CartRelatedByAddressDeliveryId', '\\Thelia\\Model\\Cart', RelationMap::ONE_TO_MANY, array('id' => 'address_delivery_id', ), 'RESTRICT', 'RESTRICT', 'CartsRelatedByAddressDeliveryId');
        $this->addRelation('CartRelatedByAddressInvoiceId', '\\Thelia\\Model\\Cart', RelationMap::ONE_TO_MANY, array('id' => 'address_invoice_id', ), 'RESTRICT', 'RESTRICT', 'CartsRelatedByAddressInvoiceId');
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
        return $withPrefix ? AddressTableMap::CLASS_DEFAULT : AddressTableMap::OM_CLASS;
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
     * @return array (Address object, last column rank)
     */
    public static function populateObject($row, $offset = 0, $indexType = TableMap::TYPE_NUM)
    {
        $key = AddressTableMap::getPrimaryKeyHashFromRow($row, $offset, $indexType);
        if (null !== ($obj = AddressTableMap::getInstanceFromPool($key))) {
            // We no longer rehydrate the object, since this can cause data loss.
            // See http://www.propelorm.org/ticket/509
            // $obj->hydrate($row, $offset, true); // rehydrate
            $col = $offset + AddressTableMap::NUM_HYDRATE_COLUMNS;
        } else {
            $cls = AddressTableMap::OM_CLASS;
            $obj = new $cls();
            $col = $obj->hydrate($row, $offset, false, $indexType);
            AddressTableMap::addInstanceToPool($obj, $key);
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
            $key = AddressTableMap::getPrimaryKeyHashFromRow($row, 0, $dataFetcher->getIndexType());
            if (null !== ($obj = AddressTableMap::getInstanceFromPool($key))) {
                // We no longer rehydrate the object, since this can cause data loss.
                // See http://www.propelorm.org/ticket/509
                // $obj->hydrate($row, 0, true); // rehydrate
                $results[] = $obj;
            } else {
                $obj = new $cls();
                $obj->hydrate($row);
                $results[] = $obj;
                AddressTableMap::addInstanceToPool($obj, $key);
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
            $criteria->addSelectColumn(AddressTableMap::ID);
            $criteria->addSelectColumn(AddressTableMap::LABEL);
            $criteria->addSelectColumn(AddressTableMap::CUSTOMER_ID);
            $criteria->addSelectColumn(AddressTableMap::TITLE_ID);
            $criteria->addSelectColumn(AddressTableMap::COMPANY);
            $criteria->addSelectColumn(AddressTableMap::FIRSTNAME);
            $criteria->addSelectColumn(AddressTableMap::LASTNAME);
            $criteria->addSelectColumn(AddressTableMap::ADDRESS1);
            $criteria->addSelectColumn(AddressTableMap::ADDRESS2);
            $criteria->addSelectColumn(AddressTableMap::ADDRESS3);
            $criteria->addSelectColumn(AddressTableMap::ZIPCODE);
            $criteria->addSelectColumn(AddressTableMap::CITY);
            $criteria->addSelectColumn(AddressTableMap::COUNTRY_ID);
            $criteria->addSelectColumn(AddressTableMap::STATE_ID);
            $criteria->addSelectColumn(AddressTableMap::PHONE);
            $criteria->addSelectColumn(AddressTableMap::CELLPHONE);
            $criteria->addSelectColumn(AddressTableMap::IS_DEFAULT);
            $criteria->addSelectColumn(AddressTableMap::CREATED_AT);
            $criteria->addSelectColumn(AddressTableMap::UPDATED_AT);
        } else {
            $criteria->addSelectColumn($alias . '.ID');
            $criteria->addSelectColumn($alias . '.LABEL');
            $criteria->addSelectColumn($alias . '.CUSTOMER_ID');
            $criteria->addSelectColumn($alias . '.TITLE_ID');
            $criteria->addSelectColumn($alias . '.COMPANY');
            $criteria->addSelectColumn($alias . '.FIRSTNAME');
            $criteria->addSelectColumn($alias . '.LASTNAME');
            $criteria->addSelectColumn($alias . '.ADDRESS1');
            $criteria->addSelectColumn($alias . '.ADDRESS2');
            $criteria->addSelectColumn($alias . '.ADDRESS3');
            $criteria->addSelectColumn($alias . '.ZIPCODE');
            $criteria->addSelectColumn($alias . '.CITY');
            $criteria->addSelectColumn($alias . '.COUNTRY_ID');
            $criteria->addSelectColumn($alias . '.STATE_ID');
            $criteria->addSelectColumn($alias . '.PHONE');
            $criteria->addSelectColumn($alias . '.CELLPHONE');
            $criteria->addSelectColumn($alias . '.IS_DEFAULT');
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
        return Propel::getServiceContainer()->getDatabaseMap(AddressTableMap::DATABASE_NAME)->getTable(AddressTableMap::TABLE_NAME);
    }

    /**
     * Add a TableMap instance to the database for this tableMap class.
     */
    public static function buildTableMap()
    {
      $dbMap = Propel::getServiceContainer()->getDatabaseMap(AddressTableMap::DATABASE_NAME);
      if (!$dbMap->hasTable(AddressTableMap::TABLE_NAME)) {
        $dbMap->addTableObject(new AddressTableMap());
      }
    }

    /**
     * Performs a DELETE on the database, given a Address or Criteria object OR a primary key value.
     *
     * @param mixed               $values Criteria or Address object or primary key or array of primary keys
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
            $con = Propel::getServiceContainer()->getWriteConnection(AddressTableMap::DATABASE_NAME);
        }

        if ($values instanceof Criteria) {
            // rename for clarity
            $criteria = $values;
        } elseif ($values instanceof \Thelia\Model\Address) { // it's a model object
            // create criteria based on pk values
            $criteria = $values->buildPkeyCriteria();
        } else { // it's a primary key, or an array of pks
            $criteria = new Criteria(AddressTableMap::DATABASE_NAME);
            $criteria->add(AddressTableMap::ID, (array) $values, Criteria::IN);
        }

        $query = AddressQuery::create()->mergeWith($criteria);

        if ($values instanceof Criteria) { AddressTableMap::clearInstancePool();
        } elseif (!is_object($values)) { // it's a primary key, or an array of pks
            foreach ((array) $values as $singleval) { AddressTableMap::removeInstanceFromPool($singleval);
            }
        }

        return $query->delete($con);
    }

    /**
     * Deletes all rows from the address table.
     *
     * @param ConnectionInterface $con the connection to use
     * @return int The number of affected rows (if supported by underlying database driver).
     */
    public static function doDeleteAll(ConnectionInterface $con = null)
    {
        return AddressQuery::create()->doDeleteAll($con);
    }

    /**
     * Performs an INSERT on the database, given a Address or Criteria object.
     *
     * @param mixed               $criteria Criteria or Address object containing data that is used to create the INSERT statement.
     * @param ConnectionInterface $con the ConnectionInterface connection to use
     * @return mixed           The new primary key.
     * @throws PropelException Any exceptions caught during processing will be
     *         rethrown wrapped into a PropelException.
     */
    public static function doInsert($criteria, ConnectionInterface $con = null)
    {
        if (null === $con) {
            $con = Propel::getServiceContainer()->getWriteConnection(AddressTableMap::DATABASE_NAME);
        }

        if ($criteria instanceof Criteria) {
            $criteria = clone $criteria; // rename for clarity
        } else {
            $criteria = $criteria->buildCriteria(); // build Criteria from Address object
        }

        if ($criteria->containsKey(AddressTableMap::ID) && $criteria->keyContainsValue(AddressTableMap::ID) ) {
            throw new PropelException('Cannot insert a value for auto-increment primary key ('.AddressTableMap::ID.')');
        }


        // Set the correct dbName
        $query = AddressQuery::create()->mergeWith($criteria);

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

} // AddressTableMap
// This is the static code needed to register the TableMap for this table with the main Propel class.
//
AddressTableMap::buildTableMap();
