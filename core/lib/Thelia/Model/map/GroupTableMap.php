<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'group' table.
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
class GroupTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.GroupTableMap';

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
        $this->setName('group');
        $this->setPhpName('Group');
        $this->setClassname('Thelia\\Model\\Group');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'admin_group', 'GROUP_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'group_desc', 'GROUP_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'group_module', 'GROUP_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'group_resource', 'GROUP_ID', true, null, null);
        $this->addColumn('CODE', 'Code', 'VARCHAR', true, 30, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', true, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', true, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('AdminGroup', 'Thelia\\Model\\AdminGroup', RelationMap::MANY_TO_ONE, array('id' => 'group_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('GroupDesc', 'Thelia\\Model\\GroupDesc', RelationMap::MANY_TO_ONE, array('id' => 'group_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('GroupModule', 'Thelia\\Model\\GroupModule', RelationMap::MANY_TO_ONE, array('id' => 'group_id', ), 'CASCADE', 'CASCADE');
        $this->addRelation('GroupResource', 'Thelia\\Model\\GroupResource', RelationMap::MANY_TO_ONE, array('id' => 'group_id', ), 'CASCADE', 'RESTRICT');
    } // buildRelations()

} // GroupTableMap
