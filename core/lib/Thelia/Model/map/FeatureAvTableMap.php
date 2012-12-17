<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'feature_av' table.
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
class FeatureAvTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.FeatureAvTableMap';

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
        $this->setName('feature_av');
        $this->setPhpName('FeatureAv');
        $this->setClassname('Thelia\\Model\\FeatureAv');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'feature_av_desc', 'FEATURE_AV_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'feature_prod', 'FEATURE_AV_ID', true, null, null);
        $this->addColumn('FEATURE_ID', 'FeatureId', 'INTEGER', true, null, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', true, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', true, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('FeatureAvDesc', 'Thelia\\Model\\FeatureAvDesc', RelationMap::MANY_TO_ONE, array('id' => 'feature_av_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('FeatureProd', 'Thelia\\Model\\FeatureProd', RelationMap::MANY_TO_ONE, array('id' => 'feature_av_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Feature', 'Thelia\\Model\\Feature', RelationMap::ONE_TO_ONE, array('feature_id' => 'id', ), 'CASCADE', 'RESTRICT');
    } // buildRelations()

} // FeatureAvTableMap
