<?php

namespace Thelia\Model\map;

use \RelationMap;
use \TableMap;


/**
 * This class defines the structure of the 'order' table.
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
class OrderTableMap extends TableMap
{

    /**
     * The (dot-path) name of this class
     */
    const CLASS_NAME = 'Thelia.Model.map.OrderTableMap';

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
        $this->setName('order');
        $this->setPhpName('Order');
        $this->setClassname('Thelia\\Model\\Order');
        $this->setPackage('Thelia.Model');
        $this->setUseIdGenerator(true);
        // columns
        $this->addPrimaryKey('ID', 'Id', 'INTEGER', true, null, null);
        $this->addColumn('REF', 'Ref', 'VARCHAR', false, 45, null);
        $this->addForeignKey('CUSTOMER_ID', 'CustomerId', 'INTEGER', 'customer', 'ID', true, null, null);
        $this->addForeignKey('ADDRESS_INVOICE', 'AddressInvoice', 'INTEGER', 'order_address', 'ID', false, null, null);
        $this->addForeignKey('ADDRESS_DELIVERY', 'AddressDelivery', 'INTEGER', 'order_address', 'ID', false, null, null);
        $this->addColumn('INVOICE_DATE', 'InvoiceDate', 'DATE', false, null, null);
        $this->addForeignKey('CURRENCY_ID', 'CurrencyId', 'INTEGER', 'currency', 'ID', false, null, null);
        $this->addColumn('CURRENCY_RATE', 'CurrencyRate', 'FLOAT', true, null, null);
        $this->addColumn('TRANSACTION', 'Transaction', 'VARCHAR', false, 100, null);
        $this->addColumn('DELIVERY_NUM', 'DeliveryNum', 'VARCHAR', false, 100, null);
        $this->addColumn('INVOICE', 'Invoice', 'VARCHAR', false, 100, null);
        $this->addColumn('POSTAGE', 'Postage', 'FLOAT', false, null, null);
        $this->addColumn('PAYMENT', 'Payment', 'VARCHAR', true, 45, null);
        $this->addColumn('CARRIER', 'Carrier', 'VARCHAR', true, 45, null);
        $this->addForeignKey('STATUS_ID', 'StatusId', 'INTEGER', 'order_status', 'ID', false, null, null);
        $this->addColumn('LANG', 'Lang', 'VARCHAR', true, 10, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', false, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', false, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('Currency', 'Thelia\\Model\\Currency', RelationMap::MANY_TO_ONE, array('currency_id' => 'id', ), 'SET NULL', 'RESTRICT');
        $this->addRelation('Customer', 'Thelia\\Model\\Customer', RelationMap::MANY_TO_ONE, array('customer_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('OrderAddressRelatedByAddressInvoice', 'Thelia\\Model\\OrderAddress', RelationMap::MANY_TO_ONE, array('address_invoice' => 'id', ), 'SET NULL', 'RESTRICT');
        $this->addRelation('OrderAddressRelatedByAddressDelivery', 'Thelia\\Model\\OrderAddress', RelationMap::MANY_TO_ONE, array('address_delivery' => 'id', ), 'SET NULL', 'RESTRICT');
        $this->addRelation('OrderStatus', 'Thelia\\Model\\OrderStatus', RelationMap::MANY_TO_ONE, array('status_id' => 'id', ), 'SET NULL', 'RESTRICT');
        $this->addRelation('OrderProduct', 'Thelia\\Model\\OrderProduct', RelationMap::ONE_TO_MANY, array('id' => 'order_id', ), 'CASCADE', 'RESTRICT', 'OrderProducts');
        $this->addRelation('CouponOrder', 'Thelia\\Model\\CouponOrder', RelationMap::ONE_TO_MANY, array('id' => 'order_id', ), 'CASCADE', 'RESTRICT', 'CouponOrders');
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

} // OrderTableMap
