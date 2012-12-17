<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'attribute_combination' table.
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
class AttributeCombinationTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.AttributeCombinationTableMap';

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
        $this->setName('attribute_combination');
        $this->setPhpName('AttributeCombination');
        $this->setClassname('Thelia\\Model\\AttributeCombination');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addPrimaryKey('ATTRIBUTE_ID', 'AttributeId', 'INTEGER', true, null, null);
        $this->addPrimaryKey('COMBINATION_ID', 'CombinationId', 'INTEGER', true, null, null);
        $this->addPrimaryKey('ATTRIBUTE_AV_ID', 'AttributeAvId', 'INTEGER', true, null, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', true, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', true, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Attribute', 'Thelia\\Model\\Attribute', RelationMap::ONE_TO_ONE, array('attribute_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('AttributeAv', 'Thelia\\Model\\AttributeAv', RelationMap::ONE_TO_ONE, array('attribute_av_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Combination', 'Thelia\\Model\\Combination', RelationMap::ONE_TO_ONE, array('combination_id' => 'id', ), 'CASCADE', 'RESTRICT');
    } // buildRelations()

} // AttributeCombinationTableMap
