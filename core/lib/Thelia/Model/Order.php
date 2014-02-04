<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;

use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Order as BaseOrder;
use Thelia\Model\Base\OrderProductTaxQuery;
use Thelia\Model\Map\OrderProductTaxTableMap;


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
    public function getTotalAmount(&$tax = 0, $includePostage = true, $includeDiscount = true)
    {
        $amount = 0;
        $tax = 0;

        /* browse all products */
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

        // @todo : manage discount : free postage ?
        if(true === $includeDiscount) {
            $total -= $this->getDiscount();

            if($total<0) {
                $total = 0;
            } else {
                $total = round($total, 2);
            }
        }

        if(false !== $includePostage) {
            $total += $this->getPostage();
        }

        return $total;
    }
}
