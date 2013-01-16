<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'customer_title' table.
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
class CustomerTitleTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.CustomerTitleTableMap';

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
        $this->setName('customer_title');
        $this->setPhpName('CustomerTitle');
        $this->setClassname('Thelia\\Model\\CustomerTitle');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('DEFAULT_UTILITY', 'DefaultUtility', 'INTEGER', true, null, 0);
        $this->addColumn('POSITION', 'Position', 'VARCHAR', true, 45, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', true, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', true, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Address', 'Thelia\\Model\\Address', RelationMap::ONE_TO_MANY, array('id' => 'customer_title_id', ), null, null, 'Addresss');
        $this->addRelation('Customer', 'Thelia\\Model\\Customer', RelationMap::ONE_TO_MANY, array('id' => 'customer_title_id', ), 'SET NULL', null, 'Customers');
        $this->addRelation('CustomerTitleDesc', 'Thelia\\Model\\CustomerTitleDesc', RelationMap::ONE_TO_MANY, array('id' => 'customer_title_id', ), 'CASCADE', null, 'CustomerTitleDescs');
    } // buildRelations()

} // CustomerTitleTableMap
