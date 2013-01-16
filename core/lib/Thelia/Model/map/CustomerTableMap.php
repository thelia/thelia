<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


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
 * @package    propel.generator.Thelia.Model.map
 */
class CustomerTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.CustomerTableMap';

    /**
     * Initialize the table attributes, columns and validators
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
        $this->setClassname('Thelia\\Model\\Customer');
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
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', true, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', true, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('CustomerTitle', 'Thelia\\Model\\CustomerTitle', RelationMap::MANY_TO_ONE, array('customer_title_id' => 'id', ), 'SET NULL', null);
        $this->addRelation('Address', 'Thelia\\Model\\Address', RelationMap::ONE_TO_MANY, array('id' => 'customer_id', ), 'CASCADE', null, 'Addresss');
        $this->addRelation('Order', 'Thelia\\Model\\Order', RelationMap::ONE_TO_MANY, array('id' => 'customer_id', ), 'CASCADE', null, 'Orders');
    } // buildRelations()

} // CustomerTableMap
