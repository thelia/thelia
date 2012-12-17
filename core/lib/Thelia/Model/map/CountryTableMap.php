<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


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
 * @package    propel.generator.Thelia.Model.map
 */
class CountryTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.CountryTableMap';

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
        $this->setName('country');
        $this->setPhpName('Country');
        $this->setClassname('Thelia\\Model\\Country');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(false);
        // columns
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'country_desc', 'COUNTRY_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'tax_rule_country', 'COUNTRY_ID', true, null, null);
        $this->addColumn('AREA_ID', 'AreaId', 'INTEGER', false, null, null);
        $this->addColumn('ISOCODE', 'Isocode', 'VARCHAR', true, 4, null);
        $this->addColumn('ISOALPHA2', 'Isoalpha2', 'VARCHAR', false, 2, null);
        $this->addColumn('ISOALPHA3', 'Isoalpha3', 'VARCHAR', false, 4, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('CountryDesc', 'Thelia\\Model\\CountryDesc', RelationMap::MANY_TO_ONE, array('id' => 'country_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('TaxRuleCountry', 'Thelia\\Model\\TaxRuleCountry', RelationMap::MANY_TO_ONE, array('id' => 'country_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Area', 'Thelia\\Model\\Area', RelationMap::ONE_TO_ONE, array('area_id' => 'id', ), 'SET NULL', 'RESTRICT');
    } // buildRelations()

} // CountryTableMap
