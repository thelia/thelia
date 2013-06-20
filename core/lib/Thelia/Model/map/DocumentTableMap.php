<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'document' table.
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
class DocumentTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.DocumentTableMap';

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
        $this->setName('document');
        $this->setPhpName('Document');
        $this->setClassname('Thelia\\Model\\Document');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('id', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('product_id', 'ProductId', 'INTEGER', 'product', 'id', false, null, null);
        $this->addForeignKey('category_id', 'CategoryId', 'INTEGER', 'category', 'id', false, null, null);
        $this->addForeignKey('folder_id', 'FolderId', 'INTEGER', 'folder', 'id', false, null, null);
        $this->addForeignKey('content_id', 'ContentId', 'INTEGER', 'content', 'id', false, null, null);
        $this->addColumn('file', 'File', 'VARCHAR', true, 255, null);
        $this->addColumn('position', 'Position', 'INTEGER', false, null, null);
        $this->addColumn('created_at', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('updated_at', 'UpdatedAt', 'TIMESTAMP', false, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Product', 'Thelia\\Model\\Product', RelationMap::MANY_TO_ONE, array('product_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Category', 'Thelia\\Model\\Category', RelationMap::MANY_TO_ONE, array('category_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Content', 'Thelia\\Model\\Content', RelationMap::MANY_TO_ONE, array('content_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Folder', 'Thelia\\Model\\Folder', RelationMap::MANY_TO_ONE, array('folder_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('DocumentI18n', 'Thelia\\Model\\DocumentI18n', RelationMap::ONE_TO_MANY, array('id' => 'id', ), 'CASCADE', null, 'DocumentI18ns');
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

} // DocumentTableMap
