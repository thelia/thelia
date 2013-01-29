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
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('FIRSTNAME', 'Firstname', 'VARCHAR', true, 100, null);
        $this->addColumn('LASTNAME', 'Lastname', 'VARCHAR', true, 100, null);
        $this->addColumn('LOGIN', 'Login', 'VARCHAR', true, 100, null);
        $this->addColumn('PASSWORD', 'Password', 'VARCHAR', true, 128, null);
        $this->addColumn('ALGO', 'Algo', 'VARCHAR', false, 128, null);
        $this->addColumn('SALT', 'Salt', 'VARCHAR', false, 128, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('AdminGroup', 'Thelia\\Model\\AdminGroup', RelationMap::ONE_TO_MANY, array('id' => 'admin_id', ), 'CASCADE', 'RESTRICT', 'AdminGroups');
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
            'timestampable' => array('create_column' => 'created_at', 'update_column' => 'updated_at', 'disable_updated_at' => 'false', ),
        );
    } // getBehaviors()

} // AdminTableMap
