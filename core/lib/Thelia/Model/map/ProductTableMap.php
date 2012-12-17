<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'product' table.
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
class ProductTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.ProductTableMap';

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
        $this->setName('product');
        $this->setPhpName('Product');
        $this->setClassname('Thelia\\Model\\Product');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'accessory', 'PRODUCT_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'accessory', 'ACCESSORY', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'content_assoc', 'PRODUCT_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'document', 'PRODUCT_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'feature_prod', 'PRODUCT_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'image', 'PRODUCT_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'product_category', 'PRODUCT_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'product_desc', 'PRODUCT_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'rewriting', 'PRODUCT_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'stock', 'PRODUCT_ID', true, null, null);
        $this->addColumn('TAX_RULE_ID', 'TaxRuleId', 'INTEGER', false, null, null);
        $this->addColumn('REF', 'Ref', 'VARCHAR', true, 255, null);
        $this->addColumn('PRICE', 'Price', 'FLOAT', true, null, null);
        $this->addColumn('PRICE2', 'Price2', 'FLOAT', false, null, null);
        $this->addColumn('ECOTAX', 'Ecotax', 'FLOAT', false, null, null);
        $this->addColumn('NEWNESS', 'Newness', 'TINYINT', false, null, 0);
        $this->addColumn('PROMO', 'Promo', 'TINYINT', false, null, 0);
        $this->addColumn('QUANTITY', 'Quantity', 'INTEGER', false, null, 0);
        $this->addColumn('VISIBLE', 'Visible', 'TINYINT', true, null, 0);
        $this->addColumn('WEIGHT', 'Weight', 'FLOAT', false, null, null);
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
        $this->addRelation('Accessory', 'Thelia\\Model\\Accessory', RelationMap::MANY_TO_ONE, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Accessory', 'Thelia\\Model\\Accessory', RelationMap::MANY_TO_ONE, array('id' => 'accessory', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('ContentAssoc', 'Thelia\\Model\\ContentAssoc', RelationMap::MANY_TO_ONE, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Document', 'Thelia\\Model\\Document', RelationMap::MANY_TO_ONE, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('FeatureProd', 'Thelia\\Model\\FeatureProd', RelationMap::MANY_TO_ONE, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Image', 'Thelia\\Model\\Image', RelationMap::MANY_TO_ONE, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('ProductCategory', 'Thelia\\Model\\ProductCategory', RelationMap::MANY_TO_ONE, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('ProductDesc', 'Thelia\\Model\\ProductDesc', RelationMap::MANY_TO_ONE, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Rewriting', 'Thelia\\Model\\Rewriting', RelationMap::MANY_TO_ONE, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Stock', 'Thelia\\Model\\Stock', RelationMap::MANY_TO_ONE, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('TaxRule', 'Thelia\\Model\\TaxRule', RelationMap::ONE_TO_ONE, array('tax_rule_id' => 'id', ), 'SET NULL', 'RESTRICT');
    } // buildRelations()

} // ProductTableMap
