<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'feature_prod' table.
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
class FeatureProdTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.FeatureProdTableMap';

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
        $this->setName('feature_prod');
        $this->setPhpName('FeatureProd');
        $this->setClassname('Thelia\\Model\\FeatureProd');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('id', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('product_id', 'ProductId', 'INTEGER', 'product', 'id', true, null, null);
        $this->addForeignKey('feature_id', 'FeatureId', 'INTEGER', 'feature', 'id', true, null, null);
        $this->addForeignKey('feature_av_id', 'FeatureAvId', 'INTEGER', 'feature_av', 'id', false, null, null);
        $this->addColumn('by_default', 'ByDefault', 'VARCHAR', false, 255, null);
        $this->addColumn('position', 'Position', 'INTEGER', false, null, null);
        $this->addColumn('created_at', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('updated_at', 'UpdatedAt', 'TIMESTAMP', false, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Product', 'Thelia\\Model\\Product', RelationMap::MANY_TO_ONE, array('product_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Feature', 'Thelia\\Model\\Feature', RelationMap::MANY_TO_ONE, array('feature_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('FeatureAv', 'Thelia\\Model\\FeatureAv', RelationMap::MANY_TO_ONE, array('feature_av_id' => 'id', ), 'CASCADE', 'RESTRICT');
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
            'timestampable' =>  array (
  'create_column' => 'created_at',
  'update_column' => 'updated_at',
  'disable_updated_at' => 'false',
),
        );
    } // getBehaviors()

} // FeatureProdTableMap
