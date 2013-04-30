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
        $this->addPrimaryKey('id', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('code', 'Code', 'VARCHAR', true, 30, null);
        $this->addColumn('created_at', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('updated_at', 'UpdatedAt', 'TIMESTAMP', false, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('AdminGroup', 'Thelia\\Model\\AdminGroup', RelationMap::ONE_TO_MANY, array('id' => 'group_id', ), 'CASCADE', 'RESTRICT', 'AdminGroups');
        $this->addRelation('GroupResource', 'Thelia\\Model\\GroupResource', RelationMap::ONE_TO_MANY, array('id' => 'group_id', ), 'CASCADE', 'RESTRICT', 'GroupResources');
        $this->addRelation('GroupModule', 'Thelia\\Model\\GroupModule', RelationMap::ONE_TO_MANY, array('id' => 'group_id', ), 'CASCADE', 'CASCADE', 'GroupModules');
        $this->addRelation('GroupI18n', 'Thelia\\Model\\GroupI18n', RelationMap::ONE_TO_MANY, array('id' => 'id', ), 'CASCADE', null, 'GroupI18ns');
        $this->addRelation('Admin', 'Thelia\\Model\\Admin', RelationMap::MANY_TO_MANY, array(), 'CASCADE', 'RESTRICT', 'Admins');
        $this->addRelation('Resource', 'Thelia\\Model\\Resource', RelationMap::MANY_TO_MANY, array(), 'CASCADE', 'RESTRICT', 'Resources');
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
            'i18n' =>  array (
  'i18n_table' => '%TABLE%_i18n',
  'i18n_phpname' => '%PHPNAME%I18n',
  'i18n_columns' => 'title, description, chapo, postscriptum',
  'i18n_pk_name' => NULL,
  'locale_column' => 'locale',
  'default_locale' => NULL,
  'locale_alias' => '',
),
        );
    } // getBehaviors()

} // GroupTableMap
