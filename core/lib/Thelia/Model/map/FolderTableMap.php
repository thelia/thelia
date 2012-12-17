<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'folder' table.
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
class FolderTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.FolderTableMap';

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
        $this->setName('folder');
        $this->setPhpName('Folder');
        $this->setClassname('Thelia\\Model\\Folder');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'content_folder', 'FOLDER_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'document', 'FOLDER_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'folder_desc', 'FOLDER_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'image', 'FOLDER_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'rewriting', 'FOLDER_ID', true, null, null);
        $this->addColumn('PARENT', 'Parent', 'INTEGER', true, null, null);
        $this->addColumn('LINK', 'Link', 'VARCHAR', false, 255, null);
        $this->addColumn('VISIBLE', 'Visible', 'TINYINT', false, null, null);
        $this->addColumn('POSITION', 'Position', 'INTEGER', false, null, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', true, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', true, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('ContentFolder', 'Thelia\\Model\\ContentFolder', RelationMap::MANY_TO_ONE, array('id' => 'folder_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Document', 'Thelia\\Model\\Document', RelationMap::MANY_TO_ONE, array('id' => 'folder_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('FolderDesc', 'Thelia\\Model\\FolderDesc', RelationMap::MANY_TO_ONE, array('id' => 'folder_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Image', 'Thelia\\Model\\Image', RelationMap::MANY_TO_ONE, array('id' => 'folder_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Rewriting', 'Thelia\\Model\\Rewriting', RelationMap::MANY_TO_ONE, array('id' => 'folder_id', ), 'CASCADE', 'RESTRICT');
    } // buildRelations()

} // FolderTableMap
