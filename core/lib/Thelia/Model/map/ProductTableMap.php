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
        $this->addPrimaryKey('id', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('tax_rule_id', 'TaxRuleId', 'INTEGER', 'tax_rule', 'id', false, null, null);
        $this->addColumn('ref', 'Ref', 'VARCHAR', true, 255, null);
        $this->addColumn('price', 'Price', 'FLOAT', true, null, null);
        $this->addColumn('price2', 'Price2', 'FLOAT', false, null, null);
        $this->addColumn('ecotax', 'Ecotax', 'FLOAT', false, null, null);
        $this->addColumn('newness', 'Newness', 'TINYINT', false, null, 0);
        $this->addColumn('promo', 'Promo', 'TINYINT', false, null, 0);
        $this->addColumn('stock', 'Stock', 'INTEGER', false, null, 0);
        $this->addColumn('visible', 'Visible', 'TINYINT', true, null, 0);
        $this->addColumn('weight', 'Weight', 'FLOAT', false, null, null);
        $this->addColumn('position', 'Position', 'INTEGER', true, null, null);
        $this->addColumn('created_at', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('updated_at', 'UpdatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('version', 'Version', 'INTEGER', false, null, 0);
        $this->addColumn('version_created_at', 'VersionCreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('version_created_by', 'VersionCreatedBy', 'VARCHAR', false, 100, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('TaxRule', 'Thelia\\Model\\TaxRule', RelationMap::MANY_TO_ONE, array('tax_rule_id' => 'id', ), 'SET NULL', 'RESTRICT');
        $this->addRelation('ProductCategory', 'Thelia\\Model\\ProductCategory', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'ProductCategorys');
        $this->addRelation('FeatureProd', 'Thelia\\Model\\FeatureProd', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'FeatureProds');
        $this->addRelation('Stock', 'Thelia\\Model\\Stock', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'Stocks');
        $this->addRelation('ContentAssoc', 'Thelia\\Model\\ContentAssoc', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'ContentAssocs');
        $this->addRelation('Image', 'Thelia\\Model\\Image', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'Images');
        $this->addRelation('Document', 'Thelia\\Model\\Document', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'Documents');
        $this->addRelation('AccessoryRelatedByProductId', 'Thelia\\Model\\Accessory', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'AccessorysRelatedByProductId');
        $this->addRelation('AccessoryRelatedByAccessory', 'Thelia\\Model\\Accessory', RelationMap::ONE_TO_MANY, array('id' => 'accessory', ), 'CASCADE', 'RESTRICT', 'AccessorysRelatedByAccessory');
        $this->addRelation('Rewriting', 'Thelia\\Model\\Rewriting', RelationMap::ONE_TO_MANY, array('id' => 'product_id', ), 'CASCADE', 'RESTRICT', 'Rewritings');
        $this->addRelation('ProductI18n', 'Thelia\\Model\\ProductI18n', RelationMap::ONE_TO_MANY, array('id' => 'id', ), 'CASCADE', null, 'ProductI18ns');
        $this->addRelation('ProductVersion', 'Thelia\\Model\\ProductVersion', RelationMap::ONE_TO_MANY, array('id' => 'id', ), 'CASCADE', null, 'ProductVersions');
        $this->addRelation('Category', 'Thelia\\Model\\Category', RelationMap::MANY_TO_MANY, array(), 'CASCADE', 'RESTRICT', 'Categorys');
        $this->addRelation('ProductRelatedByAccessory', 'Thelia\\Model\\Product', RelationMap::MANY_TO_MANY, array(), 'CASCADE', 'RESTRICT', 'ProductsRelatedByAccessory');
        $this->addRelation('ProductRelatedByProductId', 'Thelia\\Model\\Product', RelationMap::MANY_TO_MANY, array(), 'CASCADE', 'RESTRICT', 'ProductsRelatedByProductId');
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
            'versionable' =>  array (
  'version_column' => 'version',
  'version_table' => '',
  'log_created_at' => 'true',
  'log_created_by' => 'true',
  'log_comment' => 'false',
  'version_created_at_column' => 'version_created_at',
  'version_created_by_column' => 'version_created_by',
  'version_comment_column' => 'version_comment',
),
        );
    } // getBehaviors()

} // ProductTableMap
