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
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'coupon_order', 'ORDER_ID', true, null, null);
        $this->addForeignPrimaryKey('ID', 'Id', 'INTEGER' , 'order_product', 'ORDER_ID', true, null, null);
        $this->addColumn('REF', 'Ref', 'VARCHAR', false, 45, null);
        $this->addColumn('CUSTOMER_ID', 'CustomerId', 'INTEGER', true, null, null);
        $this->addColumn('ADDRESS_INVOICE', 'AddressInvoice', 'INTEGER', false, null, null);
        $this->addColumn('ADDRESS_DELIVERY', 'AddressDelivery', 'INTEGER', false, null, null);
        $this->addColumn('INVOICE_DATE', 'InvoiceDate', 'DATE', false, null, null);
        $this->addColumn('CURRENCY_ID', 'CurrencyId', 'INTEGER', false, null, null);
        $this->addColumn('CURRENCY_RATE', 'CurrencyRate', 'FLOAT', true, null, null);
        $this->addColumn('TRANSACTION', 'Transaction', 'VARCHAR', false, 100, null);
        $this->addColumn('DELIVERY_NUM', 'DeliveryNum', 'VARCHAR', false, 100, null);
        $this->addColumn('INVOICE', 'Invoice', 'VARCHAR', false, 100, null);
        $this->addColumn('POSTAGE', 'Postage', 'FLOAT', false, null, null);
        $this->addColumn('PAYMENT', 'Payment', 'VARCHAR', true, 45, null);
        $this->addColumn('CARRIER', 'Carrier', 'VARCHAR', true, 45, null);
        $this->addColumn('STATUS_ID', 'StatusId', 'INTEGER', false, null, null);
        $this->addColumn('LANG', 'Lang', 'VARCHAR', true, 10, null);
        $this->addColumn('CREATED_AT', 'CreatedAt', 'TIMESTAMP', true, null, null);
        $this->addColumn('UPDATED_AT', 'UpdatedAt', 'TIMESTAMP', true, null, null);
        // validators
    } // initialize()

    /**
     * Build the RelationMap objects for this table relationships
     */
    public function buildRelations()
    {
        $this->addRelation('CouponOrder', 'Thelia\\Model\\CouponOrder', RelationMap::MANY_TO_ONE, array('id' => 'order_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('OrderProduct', 'Thelia\\Model\\OrderProduct', RelationMap::MANY_TO_ONE, array('id' => 'order_id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('Currency', 'Thelia\\Model\\Currency', RelationMap::ONE_TO_ONE, array('currency_id' => 'id', ), 'SET NULL', 'RESTRICT');
        $this->addRelation('Customer', 'Thelia\\Model\\Customer', RelationMap::ONE_TO_ONE, array('customer_id' => 'id', ), 'CASCADE', 'RESTRICT');
        $this->addRelation('OrderAddress', 'Thelia\\Model\\OrderAddress', RelationMap::ONE_TO_ONE, array('address_invoice' => 'id', ), 'SET NULL', 'RESTRICT');
        $this->addRelation('OrderAddress', 'Thelia\\Model\\OrderAddress', RelationMap::ONE_TO_ONE, array('address_delivery' => 'id', ), 'SET NULL', 'RESTRICT');
        $this->addRelation('OrderStatus', 'Thelia\\Model\\OrderStatus', RelationMap::ONE_TO_ONE, array('status_id' => 'id', ), 'SET NULL', 'RESTRICT');
    } // buildRelations()

} // OrderTableMap
