<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'image_i18n' table.
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
class ImageI18nTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.ImageI18nTableMap';

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
        $this->setName('image_i18n');
        $this->setPhpName('ImageI18n');
        $this->setClassname('Thelia\\Model\\ImageI18n');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(false);
        // columns
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'image', 'ID', true, null, null);
        $this->addPrimaryKey('LOCALE', 'Locale', 'VARCHAR', true, 5, 'en_EN');
        $this->addColumn('TITLE', 'Title', 'VARCHAR', false, 255, null);
        $this->addColumn('DESCRIPTION', 'Description', 'CLOB', false, null, null);
        $this->addColumn('CHAPO', 'Chapo', 'LONGVARCHAR', false, null, null);
        $this->addColumn('POSTSCRIPTUM', 'Postscriptum', 'LONGVARCHAR', false, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Image', 'Thelia\\Model\\Image', RelationMap::MANY_TO_ONE, array('id' => 'id', ), 'CASCADE', null);
    } // buildRelations()

} // ImageI18nTableMap
