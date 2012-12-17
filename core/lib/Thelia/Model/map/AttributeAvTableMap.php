<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'attribute_av' table.
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
class AttributeAvTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.AttributeAvTableMap';

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
        $this->setName('attribute_av');
        $this->setPhpName('AttributeAv');
        $this->setClassname('Thelia\\Model\\AttributeAv');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'attribute_av_desc', 'ATTRIBUTE_AV_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'attribute_combination', 'ATTRIBUTE_AV_ID', true, null, null);
        $this->addColumn('ATTRIBUTE_ID', 'AttributeId', 'INTEGER', true, null, null);
        $this->addColumn('POSITION', 'Position', 'INTEGER', true, null, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', true, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', true, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('AttributeAvDesc', 'Thelia\\Model\\AttributeAvDesc', RelationMap::MANY_TO_ONE, array('id' => 'attribute_av_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('AttributeCombination', 'Thelia\\Model\\AttributeCombination', RelationMap::MANY_TO_ONE, array('id' => 'attribute_av_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Attribute', 'Thelia\\Model\\Attribute', RelationMap::ONE_TO_ONE, array('attribute_id' => 'id', ), 'CASCADE', 'RESTRICT');
    } // buildRelations()

} // AttributeAvTableMap
