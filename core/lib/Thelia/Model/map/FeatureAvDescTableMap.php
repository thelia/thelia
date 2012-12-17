<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'feature_av_desc' table.
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
class FeatureAvDescTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.FeatureAvDescTableMap';

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
        $this->setName('feature_av_desc');
        $this->setPhpName('FeatureAvDesc');
        $this->setClassname('Thelia\\Model\\FeatureAvDesc');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('FEATURE_AV_ID', 'FeatureAvId', 'INTEGER', 'feature_av', 'ID', true, null, null);
        $this->addColumn('LANG', 'Lang', 'VARCHAR', false, 10, null);
        $this->addColumn('TITLE', 'Title', 'VARCHAR', false, 255, null);
        $this->addColumn('DESCRIPTION', 'Description', 'LONGVARCHAR', true, null, null);
        $this->addColumn('CHAPO', 'Chapo', 'LONGVARCHAR', true, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('FeatureAv', 'Thelia\\Model\\FeatureAv', RelationMap::MANY_TO_ONE, array('feature_av_id' => 'id', ), 'CASCADE', null);
    } // buildRelations()

} // FeatureAvDescTableMap
