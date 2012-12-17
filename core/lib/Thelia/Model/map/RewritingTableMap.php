<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'rewriting' table.
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
class RewritingTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.RewritingTableMap';

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
        $this->setName('rewriting');
        $this->setPhpName('Rewriting');
        $this->setClassname('Thelia\\Model\\Rewriting');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(false);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('URL', 'Url', 'VARCHAR', true, 255, null);
        $this->addForeignKey('PRODUCT_ID', 'ProductId', 'INTEGER', 'product', 'ID', false, null, null);
        $this->addForeignKey('CATEGORY_ID', 'CategoryId', 'INTEGER', 'category', 'ID', false, null, null);
        $this->addForeignKey('FOLDER_ID', 'FolderId', 'INTEGER', 'folder', 'ID', false, null, null);
        $this->addForeignKey('CONTENT_ID', 'ContentId', 'INTEGER', 'content', 'ID', false, null, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', true, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', true, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Product', 'Thelia\\Model\\Product', RelationMap::MANY_TO_ONE, array('product_id' => 'id', ), 'CASCADE', null);
        $this->addRelation('Category', 'Thelia\\Model\\Category', RelationMap::MANY_TO_ONE, array('category_id' => 'id', ), 'CASCADE', null);
        $this->addRelation('Folder', 'Thelia\\Model\\Folder', RelationMap::MANY_TO_ONE, array('folder_id' => 'id', ), 'CASCADE', null);
        $this->addRelation('Content', 'Thelia\\Model\\Content', RelationMap::MANY_TO_ONE, array('content_id' => 'id', ), 'CASCADE', null);
    } // buildRelations()

} // RewritingTableMap
