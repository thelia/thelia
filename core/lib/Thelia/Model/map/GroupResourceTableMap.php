<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'group_resource' table.
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
class GroupResourceTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.GroupResourceTableMap';

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
        $this->setName('group_resource');
        $this->setPhpName('GroupResource');
        $this->setClassname('Thelia\\Model\\GroupResource');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('GROUP_ID', 'GroupId', 'INTEGER', true, null, null);
        $this->addColumn('RESOURCE_ID', 'ResourceId', 'INTEGER', true, null, null);
        $this->addColumn('READ', 'Read', 'TINYINT', false, null, 0);
        $this->addColumn('WRITE', 'Write', 'TINYINT', false, null, 0);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', true, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', true, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Group', 'Thelia\\Model\\Group', RelationMap::ONE_TO_ONE, array('group_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Resource', 'Thelia\\Model\\Resource', RelationMap::ONE_TO_ONE, array('resource_id' => 'id', ), 'CASCADE', 'RESTRICT');
    } // buildRelations()

} // GroupResourceTableMap
