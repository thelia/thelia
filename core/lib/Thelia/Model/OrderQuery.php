<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Thelia\Model\Base\OrderQuery as BaseOrderQuery;
use \PDO;
use Thelia\Model\Map\OrderTableMap;

/**
 * Skeleton subclass for performing query and update operations on the 'order' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class OrderQuery extends BaseOrderQuery
{
    /**
     * PROPEL SHOULD FIX IT
     *
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     ConnectionInterface $con A connection object
     *
     * @return   Order A model object, or null if the key is not found
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT ID, REF, CUSTOMER_ID, INVOICE_ORDER_ADDRESS_ID, DELIVERY_ORDER_ADDRESS_ID, INVOICE_DATE, CURRENCY_ID, CURRENCY_RATE, TRANSACTION_REF, DELIVERY_REF, INVOICE_REF, POSTAGE, PAYMENT_MODULE_ID, DELIVERY_MODULE_ID, STATUS_ID, LANG_ID, CREATED_AT, UPDATED_AT FROM `order` WHERE ID = :p0';
        try {
            $stmt = $con->prepare($sql);
            $stmt->bindValue(':p0', $key, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), 0, $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $obj = new Order();
            $obj->hydrate($row);
            OrderTableMap::addInstanceToPool($obj, (string) $key);
        }
        $stmt->closeCursor();

        return $obj;
    }

} // OrderQuery
