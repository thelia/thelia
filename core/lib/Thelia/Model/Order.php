<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Map\OrderProductTaxTableMap;
use Thelia\Model\Base\Order as BaseOrder;
use Thelia\Model\Tools\ModelEventDispatcherTrait;

class Order extends BaseOrder
{
    use ModelEventDispatcherTrait;

    protected $choosenDeliveryAddress = null;
    protected $choosenInvoiceAddress = null;

    protected $disableVersioning = false;

    /**
     * @param null $choosenDeliveryAddress
     */
    public function setChoosenDeliveryAddress($choosenDeliveryAddress)
    {
        $this->choosenDeliveryAddress = $choosenDeliveryAddress;

        return $this;
    }

    /**
     * @param boolean $disableVersionning
     */
    public function setDisableVersioning($disableVersioning)
    {
        $this->disableVersioning = (bool) $disableVersioning;

        return $this;
    }

    public function isVersioningDisable()
    {
        return $this->disableVersioning;
    }

    public function isVersioningNecessary($con = null)
    {
        if ($this->isVersioningDisable()) {
            return false;
        } else {
            return parent::isVersioningNecessary($con);
        }
    }

    /**
     * @return null
     */
    public function getChoosenDeliveryAddress()
    {
        return $this->choosenDeliveryAddress;
    }

    /**
     * @param null $choosenInvoiceAddress
     */
    public function setChoosenInvoiceAddress($choosenInvoiceAddress)
    {
        $this->choosenInvoiceAddress = $choosenInvoiceAddress;

        return $this;
    }

    /**
     * @return null
     */
    public function getChoosenInvoiceAddress()
    {
        return $this->choosenInvoiceAddress;
    }

    public function preSave(ConnectionInterface $con = null)
    {
        if ($this->isPaid() && null === $this->getInvoiceDate()) {
            $this
                ->setInvoiceDate(time());
        }

        return parent::preSave($con);
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::ORDER_BEFORE_CREATE, new OrderEvent($this));

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function postInsert(ConnectionInterface $con = null)
    {
        $this->setRef($this->generateRef())
            ->setDisableVersioning(true)
            ->save($con);
        $this->dispatchEvent(TheliaEvents::ORDER_AFTER_CREATE, new OrderEvent($this));
    }

    public function generateRef()
    {
        return sprintf('ORD%s', str_pad($this->getId(), 12, 0, STR_PAD_LEFT));
    }

    /**
     * Compute this order amount.
     *
     * The order amount amount is only avaible once the order is persisted in database.
     * Duting invoice process, use all cart methods instead of order methods (the order doest not exists at this moment)
     *
     * @param  float|int $tax             (output only) returns the tax amount for this order
     * @param  bool      $includePostage  if true, the postage cost is included to the total
     * @param  bool      $includeDiscount if true, the discount will be included to the total
     * @return float
     */
    public function getTotalAmount(&$tax = 0, $includePostage = true, $includeDiscount = true)
    {
        $amount = 0;
        $tax = 0;

        /* browse all products */
        foreach ($this->getOrderProducts() as $orderProduct) {
            $taxAmountQuery = OrderProductTaxQuery::create();

            if ($orderProduct->getWasInPromo() == 1) {
                $taxAmountQuery->withColumn('SUM(' . OrderProductTaxTableMap::PROMO_AMOUNT . ')', 'total_tax');
            } else {
                $taxAmountQuery->withColumn('SUM(' . OrderProductTaxTableMap::AMOUNT . ')', 'total_tax');
            }

            $taxAmount = $taxAmountQuery->filterByOrderProductId($orderProduct->getId(), Criteria::EQUAL)
                ->findOne();
            $amount += ($orderProduct->getWasInPromo() == 1 ? $orderProduct->getPromoPrice() : $orderProduct->getPrice()) * $orderProduct->getQuantity();
            $tax += $taxAmount->getVirtualColumn('total_tax') * $orderProduct->getQuantity();
        }

        $total = $amount + $tax;

        // @todo : manage discount : free postage ?
        if (true === $includeDiscount) {
            $total -= $this->getDiscount();

            if ($total<0) {
                $total = 0;
            }
        }

        if (false !== $includePostage) {
            $total += $this->getPostage();
        }

        return $total;
    }

    
    /**
     * Return the postage without tax
     * @return float|int
     */    
    public function getUntaxedPostage() {
        // get default tax rule
        $taxRuleQuery = new TaxRuleQuery();
        $taxRule = $taxRuleQuery->findOneByIsDefault(true);
        // get default country
        $countryQuery = new CountryQuery();
        $country = $countryQuery->findOneByByDefault(true);
        // get calculator for this tax / country
        $calculator = new \Thelia\TaxEngine\Calculator();
        $calculator->loadTaxRuleWithoutProduct($taxRule,$country);        
        // return untaxed price
        return round($calculator->getUntaxedPrice($this->getPostage()),2);        
    }

    
    /**
     * Set the status of the current order to NOT PAID
     */
    public function setNotPaid()
    {
        $this->setStatusHelper(OrderStatus::CODE_NOT_PAID);
    }

    /**
     * Check if the current status of this order is NOT PAID
     *
     * @return bool true if this order is NOT PAID, false otherwise.
     */
    public function isNotPaid()
    {
        return $this->hasStatusHelper(OrderStatus::CODE_NOT_PAID);
    }

    /**
     * Set the status of the current order to PAID
     */
    public function setPaid()
    {
        $this->setStatusHelper(OrderStatus::CODE_PAID);
    }

    /**
     * Check if the current status of this order is PAID
     *
     * @return bool true if this order is PAID, false otherwise.
     */
    public function isPaid()
    {
        return $this->hasStatusHelper(OrderStatus::CODE_PAID);
    }

    /**
     * Set the status of the current order to PROCESSING
     */
    public function setProcessing()
    {
        $this->setStatusHelper(OrderStatus::CODE_PROCESSING);
    }

    /**
     * Check if the current status of this order is PROCESSING
     *
     * @return bool true if this order is PROCESSING, false otherwise.
     */
    public function isProcessing()
    {
        return $this->hasStatusHelper(OrderStatus::CODE_PROCESSING);
    }

    /**
     * Set the status of the current order to SENT
     */
    public function setSent()
    {
        $this->setStatusHelper(OrderStatus::CODE_SENT);
    }

    /**
     * Check if the current status of this order is SENT
     *
     * @return bool true if this order is SENT, false otherwise.
     */
    public function isSent()
    {
        return $this->hasStatusHelper(OrderStatus::CODE_SENT);
    }

    /**
     * Set the status of the current order to CANCELED
     */
    public function setCancelled()
    {
        $this->setStatusHelper(OrderStatus::CODE_CANCELED);
    }

    /**
     * Check if the current status of this order is CANCELED
     *
     * @return bool true if this order is CANCELED, false otherwise.
     */
    public function isCancelled()
    {
        return $this->hasStatusHelper(OrderStatus::CODE_CANCELED);
    }

    /**
     * Set the status of the current order to the provided status
     *
     * @param string $statusCode the status code, one of OrderStatus::CODE_xxx constants.
     */
    public function setStatusHelper($statusCode)
    {
        if (null !== $ordeStatus = OrderStatusQuery::create()->findOneByCode($statusCode)) {
            $this->setOrderStatus($ordeStatus)->save();
        }
    }

    /**
     * Check if the current status of this order is $statusCode
     *
     * @param  string $statusCode the status code, one of OrderStatus::CODE_xxx constants.
     * @return bool   true if this order have the provided status, false otherwise.
     */
    public function hasStatusHelper($statusCode)
    {
        return $this->getOrderStatus()->getCode() == $statusCode;
    }

}
