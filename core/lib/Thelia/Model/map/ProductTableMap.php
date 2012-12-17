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
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('TAX_RULE_ID', 'TaxRuleId', 'INTEGER', 'tax_rule', 'ID', false, null, null);
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
        $this->addRelation('TaxRule', 'Thelia\\Model\\TaxRule', RelationMap::MANY_TO_ONE, array('tax_rule_id' => 'id', ), 'SET NULL', null);
        $this->addRelation('AccessoryRelatedByProductId', 'Thelia\\Model\\Accessory', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', null, 'AccessorysRelatedByProductId');
        $this->addRelation('AccessoryRelatedByAccessory', 'Thelia\\Model\\Accessory', RelationMap::ONE_TO_MANY, array('id' => 'accessory', ), 'CASCADE', null, 'AccessorysRelatedByAccessory');
        $this->addRelation('ContentAssoc', 'Thelia\\Model\\ContentAssoc', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', null, 'ContentAssocs');
        $this->addRelation('Document', 'Thelia\\Model\\Document', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', null, 'Documents');
        $this->addRelation('FeatureProd', 'Thelia\\Model\\FeatureProd', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', null, 'FeatureProds');
        $this->addRelation('Image', 'Thelia\\Model\\Image', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', null, 'Images');
        $this->addRelation('ProductCategory', 'Thelia\\Model\\ProductCategory', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', null, 'ProductCategorys');
        $this->addRelation('ProductDesc', 'Thelia\\Model\\ProductDesc', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', null, 'ProductDescs');
        $this->addRelation('Rewriting', 'Thelia\\Model\\Rewriting', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', null, 'Rewritings');
        $this->addRelation('Stock', 'Thelia\\Model\\Stock', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', null, 'Stocks');
    } // buildRelations()

} // ProductTableMap
