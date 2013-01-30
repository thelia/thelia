<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'resource_i18n' table.
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
class ResourceI18nTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.ResourceI18nTableMap';

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
        $this->setName('resource_i18n');
        $this->setPhpName('ResourceI18n');
        $this->setClassname('Thelia\\Model\\ResourceI18n');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(false);
        // columns
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'resource', 'ID', true, null, null);
        $this->addPrimaryKey('LOCALE', 'Locale', 'VARCHAR', true, 5, 'en_EN');
        $this->addColumn('TITLE', 'Title', 'VARCHAR', false, 255, null);
        $this->addColumn('DESCRIPTION', 'Description', 'CLOB', false, null, null);
        $this->addColumn('CHAPO', 'Chapo', 'LONGVARCHAR', false, null, null);
        $this->addColumn('POSTSCRIPTUM', 'Postscriptum', 'LONGVARCHAR', false, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Resource', 'Thelia\\Model\\Resource', RelationMap::MANY_TO_ONE, array('id' => 'id', ), 'CASCADE', null);
    } // buildRelations()

} // ResourceI18nTableMap
