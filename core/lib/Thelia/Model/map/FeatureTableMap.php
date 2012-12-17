<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'feature' table.
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
class FeatureTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.FeatureTableMap';

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
        $this->setName('feature');
        $this->setPhpName('Feature');
        $this->setClassname('Thelia\\Model\\Feature');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'feature_av', 'FEATURE_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'feature_category', 'FEATURE_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'feature_desc', 'FEATURE_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'feature_prod', 'FEATURE_ID', true, null, null);
        $this->addColumn('VISIBLE', 'Visible', 'INTEGER', false, null, 0);
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
        $this->addRelation('FeatureAv', 'Thelia\\Model\\FeatureAv', RelationMap::MANY_TO_ONE, array('id' => 'feature_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('FeatureCategory', 'Thelia\\Model\\FeatureCategory', RelationMap::MANY_TO_ONE, array('id' => 'feature_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('FeatureDesc', 'Thelia\\Model\\FeatureDesc', RelationMap::MANY_TO_ONE, array('id' => 'feature_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('FeatureProd', 'Thelia\\Model\\FeatureProd', RelationMap::MANY_TO_ONE, array('id' => 'feature_id', ), 'CASCADE', 'RESTRICT');
    } // buildRelations()

} // FeatureTableMap
