<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'attribute_av' table.
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
class AttributeAvTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.AttributeAvTableMap';

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
        $this->setName('attribute_av');
        $this->setPhpName('AttributeAv');
        $this->setClassname('Thelia\\Model\\AttributeAv');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addForeignKey('ATTRIBUTE_ID', 'AttributeId', 'INTEGER', 'attribute', 'ID', true, null, null);
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
        $this->addRelation('Attribute', 'Thelia\\Model\\Attribute', RelationMap::MANY_TO_ONE, array('attribute_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('AttributeCombination', 'Thelia\\Model\\AttributeCombination', RelationMap::ONE_TO_MANY, array('id' => 'attribute_av_id', ), 'CASCADE', 'RESTRICT', 'AttributeCombinations');
        $this->addRelation('AttributeAvI18n', 'Thelia\\Model\\AttributeAvI18n', RelationMap::ONE_TO_MANY, array('id' => 'id', ), 'CASCADE', null, 'AttributeAvI18ns');
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
            'i18n' => array('i18n_table' => '%TABLE%_i18n', 'i18n_phpname' => '%PHPNAME%I18n', 'i18n_columns' => 'title, description, chapo, postscriptum', 'locale_column' => 'locale', 'default_locale' => '', 'locale_alias' => '', ),
        );
    } // getBehaviors()

} // AttributeAvTableMap
