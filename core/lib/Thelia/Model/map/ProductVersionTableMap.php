<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'product_version' table.
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
class ProductVersionTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.ProductVersionTableMap';

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
        $this->setName('product_version');
        $this->setPhpName('ProductVersion');
        $this->setClassname('Thelia\\Model\\ProductVersion');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(false);
        // columns
        $this->addForeignPrimaryKey('id', 'Id', 'INTEGER' , 'product', 'id', true, null, null);
        $this->addColumn('tax_rule_id', 'TaxRuleId', 'INTEGER', false, null, null);
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
        $this->addPrimaryKey('version', 'Version', 'INTEGER', true, null, 0);
        $this->addColumn('version_created_at', 'VersionCreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('version_created_by', 'VersionCreatedBy', 'VARCHAR', false, 100, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Product', 'Thelia\\Model\\Product', RelationMap::MANY_TO_ONE, array('id' => 'id', ), 'CASCADE', null);
    } // buildRelations()

} // ProductVersionTableMap
