<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'category' table.
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
class CategoryTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.CategoryTableMap';

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
        $this->setName('category');
        $this->setPhpName('Category');
        $this->setClassname('Thelia\\Model\\Category');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'attribute_category', 'CATEGORY_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'category_desc', 'CATEGORY_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'content_assoc', 'CATEGORY_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'document', 'CATEGORY_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'feature_category', 'CATEGORY_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'image', 'CATEGORY_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'product_category', 'CATEGORY_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'rewriting', 'CATEGORY_ID', true, null, null);
        $this->addColumn('PARENT', 'Parent', 'INTEGER', false, null, null);
        $this->addColumn('LINK', 'Link', 'VARCHAR', false, 255, null);
        $this->addColumn('VISIBLE', 'Visible', 'TINYINT', true, null, null);
        $this->addColumn('POSITION', 'Position', 'INTEGER', true, null, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', true, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', true, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('AttributeCategory', 'Thelia\\Model\\AttributeCategory', RelationMap::MANY_TO_ONE, array('id' => 'category_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('CategoryDesc', 'Thelia\\Model\\CategoryDesc', RelationMap::MANY_TO_ONE, array('id' => 'category_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('ContentAssoc', 'Thelia\\Model\\ContentAssoc', RelationMap::MANY_TO_ONE, array('id' => 'category_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Document', 'Thelia\\Model\\Document', RelationMap::MANY_TO_ONE, array('id' => 'category_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('FeatureCategory', 'Thelia\\Model\\FeatureCategory', RelationMap::MANY_TO_ONE, array('id' => 'category_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Image', 'Thelia\\Model\\Image', RelationMap::MANY_TO_ONE, array('id' => 'category_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('ProductCategory', 'Thelia\\Model\\ProductCategory', RelationMap::MANY_TO_ONE, array('id' => 'category_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Rewriting', 'Thelia\\Model\\Rewriting', RelationMap::MANY_TO_ONE, array('id' => 'category_id', ), 'CASCADE', 'RESTRICT');
    } // buildRelations()

} // CategoryTableMap
