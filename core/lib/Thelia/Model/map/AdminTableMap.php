<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'admin' table.
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
class AdminTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.AdminTableMap';

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
        $this->setName('admin');
        $this->setPhpName('Admin');
        $this->setClassname('Thelia\\Model\\Admin');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('id', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('firstname', 'Firstname', 'VARCHAR', true, 100, null);
        $this->addColumn('lastname', 'Lastname', 'VARCHAR', true, 100, null);
        $this->addColumn('login', 'Login', 'VARCHAR', true, 100, null);
        $this->addColumn('password', 'Password', 'VARCHAR', true, 128, null);
        $this->addColumn('algo', 'Algo', 'VARCHAR', false, 128, null);
        $this->addColumn('salt', 'Salt', 'VARCHAR', false, 128, null);
        $this->addColumn('created_at', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('updated_at', 'UpdatedAt', 'TIMESTAMP', false, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('AdminGroup', 'Thelia\\Model\\AdminGroup', RelationMap::ONE_TO_MANY, array('id' => 'admin_id', ), 'CASCADE', 'RESTRICT', 'AdminGroups');
        $this->addRelation('Group', 'Thelia\\Model\\Group', RelationMap::MANY_TO_MANY, array(), 'CASCADE', 'RESTRICT', 'Groups');
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

} // AdminTableMap
