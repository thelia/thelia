<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Order as BaseOrder;
use Thelia\Model\Base\OrderProductTaxQuery;
use Thelia\Model\Map\OrderProductTaxTableMap;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\Map\OrderTableMap;
use \PDO;

class Order extends BaseOrder
{
    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    public $chosenDeliveryAddress = null;
    public $chosenInvoiceAddress = null;

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setRef($this->generateRef());

        $this->dispatchEvent(TheliaEvents::ORDER_BEFORE_CREATE, new OrderEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::ORDER_AFTER_CREATE, new OrderEvent($this));
    }

    public function generateRef()
    {
        /* order addresses are unique */
        return uniqid('ORD', true);
    }

    /**
     * calculate the total amount
     *
     * @param int  $tax
     * @param bool $includePostage
     *
     * @return float|int|string
     */
    public function getTotalAmount(&$tax = 0, $includePostage = true)
    {
        $amount = 0;
        $tax = 0;

        /* browse all products */
        $orderProductIds = array();
        foreach($this->getOrderProducts() as $orderProduct) {
            $taxAmountQuery = OrderProductTaxQuery::create();

            if($orderProduct->getWasInPromo() == 1) {
                $taxAmountQuery->withColumn('SUM(' . OrderProductTaxTableMap::PROMO_AMOUNT . ')', 'total_tax');
            } else {
                $taxAmountQuery->withColumn('SUM(' . OrderProductTaxTableMap::AMOUNT . ')', 'total_tax');
            }

            $taxAmount = $taxAmountQuery->filterByOrderProductId($orderProduct->getId(), Criteria::EQUAL)
                ->findOne();
            $amount += ($orderProduct->getWasInPromo() == 1 ? $orderProduct->getPromoPrice() : $orderProduct->getPrice()) * $orderProduct->getQuantity();
            $tax += round($taxAmount->getVirtualColumn('total_tax'), 2) * $orderProduct->getQuantity();
        }

        $total = $amount + $tax;

        if(false !== $includePostage) {
            $total += $this->getPostage();
        }

        return $total; // @todo : manage discount
    }

    /**
     * PROPEL SHOULD FIX IT
     *
     * Insert the row in the database.
     *
     * @param      ConnectionInterface $con
     *
     * @throws PropelException
     * @see doSave()
     */
    protected function doInsert(ConnectionInterface $con)
    {
        $modifiedColumns = array();
        $index = 0;

        $this->modifiedColumns[] = OrderTableMap::ID;
        if (null !== $this->id) {
            throw new PropelException('Cannot insert a value for auto-increment primary key (' . OrderTableMap::ID . ')');
        }

        // check the columns in natural order for more readable SQL queries
        if ($this->isColumnModified(OrderTableMap::ID)) {
            $modifiedColumns[':p' . $index++]  = 'ID';
        }
        if ($this->isColumnModified(OrderTableMap::REF)) {
            $modifiedColumns[':p' . $index++]  = 'REF';
        }
        if ($this->isColumnModified(OrderTableMap::CUSTOMER_ID)) {
            $modifiedColumns[':p' . $index++]  = 'CUSTOMER_ID';
        }
        if ($this->isColumnModified(OrderTableMap::INVOICE_ORDER_ADDRESS_ID)) {
            $modifiedColumns[':p' . $index++]  = 'INVOICE_ORDER_ADDRESS_ID';
        }
        if ($this->isColumnModified(OrderTableMap::DELIVERY_ORDER_ADDRESS_ID)) {
            $modifiedColumns[':p' . $index++]  = 'DELIVERY_ORDER_ADDRESS_ID';
        }
        if ($this->isColumnModified(OrderTableMap::INVOICE_DATE)) {
            $modifiedColumns[':p' . $index++]  = 'INVOICE_DATE';
        }
        if ($this->isColumnModified(OrderTableMap::CURRENCY_ID)) {
            $modifiedColumns[':p' . $index++]  = 'CURRENCY_ID';
        }
        if ($this->isColumnModified(OrderTableMap::CURRENCY_RATE)) {
            $modifiedColumns[':p' . $index++]  = 'CURRENCY_RATE';
        }
        if ($this->isColumnModified(OrderTableMap::TRANSACTION_REF)) {
            $modifiedColumns[':p' . $index++]  = 'TRANSACTION_REF';
        }
        if ($this->isColumnModified(OrderTableMap::DELIVERY_REF)) {
            $modifiedColumns[':p' . $index++]  = 'DELIVERY_REF';
        }
        if ($this->isColumnModified(OrderTableMap::INVOICE_REF)) {
            $modifiedColumns[':p' . $index++]  = 'INVOICE_REF';
        }
        if ($this->isColumnModified(OrderTableMap::POSTAGE)) {
            $modifiedColumns[':p' . $index++]  = 'POSTAGE';
        }
        if ($this->isColumnModified(OrderTableMap::PAYMENT_MODULE_ID)) {
            $modifiedColumns[':p' . $index++]  = 'PAYMENT_MODULE_ID';
        }
        if ($this->isColumnModified(OrderTableMap::DELIVERY_MODULE_ID)) {
            $modifiedColumns[':p' . $index++]  = 'DELIVERY_MODULE_ID';
        }
        if ($this->isColumnModified(OrderTableMap::STATUS_ID)) {
            $modifiedColumns[':p' . $index++]  = 'STATUS_ID';
        }
        if ($this->isColumnModified(OrderTableMap::LANG_ID)) {
            $modifiedColumns[':p' . $index++]  = 'LANG_ID';
        }
        if ($this->isColumnModified(OrderTableMap::CREATED_AT)) {
            $modifiedColumns[':p' . $index++]  = 'CREATED_AT';
        }
        if ($this->isColumnModified(OrderTableMap::UPDATED_AT)) {
            $modifiedColumns[':p' . $index++]  = 'UPDATED_AT';
        }

        $db = Propel::getServiceContainer()->getAdapter(OrderTableMap::DATABASE_NAME);

        if ($db->useQuoteIdentifier()) {
            $tableName = $db->quoteIdentifierTable(OrderTableMap::TABLE_NAME);
        } else {
            $tableName = OrderTableMap::TABLE_NAME;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $tableName,
            implode(', ', $modifiedColumns),
            implode(', ', array_keys($modifiedColumns))
        );

        try {
            $stmt = $con->prepare($sql);
            foreach ($modifiedColumns as $identifier => $columnName) {
                switch ($columnName) {
                    case 'ID':
                        $stmt->bindValue($identifier, $this->id, PDO::PARAM_INT);
                        break;
                    case 'REF':
                        $stmt->bindValue($identifier, $this->ref, PDO::PARAM_STR);
                        break;
                    case 'CUSTOMER_ID':
                        $stmt->bindValue($identifier, $this->customer_id, PDO::PARAM_INT);
                        break;
                    case 'INVOICE_ORDER_ADDRESS_ID':
                        $stmt->bindValue($identifier, $this->invoice_order_address_id, PDO::PARAM_INT);
                        break;
                    case 'DELIVERY_ORDER_ADDRESS_ID':
                        $stmt->bindValue($identifier, $this->delivery_order_address_id, PDO::PARAM_INT);
                        break;
                    case 'INVOICE_DATE':
                        $stmt->bindValue($identifier, $this->invoice_date ? $this->invoice_date->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'CURRENCY_ID':
                        $stmt->bindValue($identifier, $this->currency_id, PDO::PARAM_INT);
                        break;
                    case 'CURRENCY_RATE':
                        $stmt->bindValue($identifier, $this->currency_rate, PDO::PARAM_STR);
                        break;
                    case 'TRANSACTION_REF':
                        $stmt->bindValue($identifier, $this->transaction_ref, PDO::PARAM_STR);
                        break;
                    case 'DELIVERY_REF':
                        $stmt->bindValue($identifier, $this->delivery_ref, PDO::PARAM_STR);
                        break;
                    case 'INVOICE_REF':
                        $stmt->bindValue($identifier, $this->invoice_ref, PDO::PARAM_STR);
                        break;
                    case 'POSTAGE':
                        $stmt->bindValue($identifier, $this->postage, PDO::PARAM_STR);
                        break;
                    case 'PAYMENT_MODULE_ID':
                        $stmt->bindValue($identifier, $this->payment_module_id, PDO::PARAM_INT);
                        break;
                    case 'DELIVERY_MODULE_ID':
                        $stmt->bindValue($identifier, $this->delivery_module_id, PDO::PARAM_INT);
                        break;
                    case 'STATUS_ID':
                        $stmt->bindValue($identifier, $this->status_id, PDO::PARAM_INT);
                        break;
                    case 'LANG_ID':
                        $stmt->bindValue($identifier, $this->lang_id, PDO::PARAM_INT);
                        break;
                    case 'CREATED_AT':
                        $stmt->bindValue($identifier, $this->created_at ? $this->created_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                    case 'UPDATED_AT':
                        $stmt->bindValue($identifier, $this->updated_at ? $this->updated_at->format("Y-m-d H:i:s") : null, PDO::PARAM_STR);
                        break;
                }
            }
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute INSERT statement [%s]', $sql), 0, $e);
        }

        try {
            $pk = $con->lastInsertId();
        } catch (Exception $e) {
            throw new PropelException('Unable to get autoincrement id.', 0, $e);
        }
        $this->setId($pk);

        $this->setNew(false);
    }
}
