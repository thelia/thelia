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
        $this->addColumn('STOCK', 'Stock', 'INTEGER', false, null, 0);
        $this->addColumn('VISIBLE', 'Visible', 'TINYINT', true, null, 0);
        $this->addColumn('WEIGHT', 'Weight', 'FLOAT', false, null, null);
        $this->addColumn('POSITION', 'Position', 'INTEGER', true, null, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('TaxRule', 'Thelia\\Model\\TaxRule', RelationMap::MANY_TO_ONE, array('tax_rule_id' => 'id', ), 'SET NULL', 'RESTRICT');
        $this->addRelation('ProductDesc', 'Thelia\\Model\\ProductDesc', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'ProductDescs');
        $this->addRelation('ProductCategory', 'Thelia\\Model\\ProductCategory', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'ProductCategorys');
        $this->addRelation('FeatureProd', 'Thelia\\Model\\FeatureProd', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'FeatureProds');
        $this->addRelation('Stock', 'Thelia\\Model\\Stock', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'Stocks');
        $this->addRelation('ContentAssoc', 'Thelia\\Model\\ContentAssoc', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'ContentAssocs');
        $this->addRelation('Image', 'Thelia\\Model\\Image', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'Images');
        $this->addRelation('Document', 'Thelia\\Model\\Document', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'Documents');
        $this->addRelation('AccessoryRelatedByProductId', 'Thelia\\Model\\Accessory', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'AccessorysRelatedByProductId');
        $this->addRelation('AccessoryRelatedByAccessory', 'Thelia\\Model\\Accessory', RelationMap::ONE_TO_MANY, array('id' => 'accessory', ), 'CASCADE', 'RESTRICT', 'AccessorysRelatedByAccessory');
        $this->addRelation('Rewriting', 'Thelia\\Model\\Rewriting', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'Rewritings');
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

} // ProductTableMap
