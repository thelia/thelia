<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'coupon' table.
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
class CouponTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.CouponTableMap';

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
        $this->setName('coupon');
        $this->setPhpName('Coupon');
        $this->setClassname('Thelia\\Model\\Coupon');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('id', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('code', 'Code', 'VARCHAR', true, 45, null);
        $this->addColumn('action', 'Action', 'VARCHAR', true, 255, null);
        $this->addColumn('value', 'Value', 'FLOAT', true, null, null);
        $this->addColumn('used', 'Used', 'TINYINT', false, null, null);
        $this->addColumn('available_since', 'AvailableSince', 'TIMESTAMP', false, null, null);
        $this->addColumn('date_limit', 'DateLimit', 'TIMESTAMP', false, null, null);
        $this->addColumn('activate', 'Activate', 'TINYINT', false, null, null);
        $this->addColumn('created_at', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('updated_at', 'UpdatedAt', 'TIMESTAMP', false, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('CouponRule', 'Thelia\\Model\\CouponRule', RelationMap::ONE_TO_MANY, array('id' => 'coupon_id', ), 'CASCADE', 'RESTRICT', 'CouponRules');
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
        );
    } // getBehaviors()

} // CouponTableMap
