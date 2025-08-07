<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\Base\Order as BaseOrder;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderProductTaxTableMap;
use Thelia\Module\BaseModuleInterface;
use Thelia\Module\PaymentModuleInterface;
use Thelia\TaxEngine\Calculator;

class Order extends BaseOrder
{
    protected ?int $choosenDeliveryAddress = null;

    protected ?int $choosenInvoiceAddress = null;

    protected bool $disableVersioning = false;

    /**
     * @param int $choosenDeliveryAddress the choosen delivery address ID
     *
     * @return $this
     */
    public function setChoosenDeliveryAddress(int $choosenDeliveryAddress)
    {
        $this->choosenDeliveryAddress = $choosenDeliveryAddress;

        return $this;
    }

    /**
     * @return $this
     */
    public function setDisableVersioning(bool $disableVersioning)
    {
        $this->disableVersioning = (bool) $disableVersioning;

        return $this;
    }

    public function isVersioningDisable()
    {
        return $this->disableVersioning;
    }

    public function isVersioningNecessary($con = null): bool
    {
        if ($this->isVersioningDisable()) {
            return false;
        }

        return parent::isVersioningNecessary($con);
    }

    /**
     * @return int|null the choosen delivery address ID
     */
    public function getChoosenDeliveryAddress(): ?int
    {
        return $this->choosenDeliveryAddress;
    }

    /**
     * @param int $choosenInvoiceAddress the choosen invoice address
     *
     * @return $this
     */
    public function setChoosenInvoiceAddress(int $choosenInvoiceAddress)
    {
        $this->choosenInvoiceAddress = $choosenInvoiceAddress;

        return $this;
    }

    /**
     * @return int|null the choosen invoice address ID
     */
    public function getChoosenInvoiceAddress(): ?int
    {
        return $this->choosenInvoiceAddress;
    }

    /**
     * @throws PropelException
     */
    public function preSave(?ConnectionInterface $con = null): bool
    {
        if ($this->isPaid(false) && null === $this->getInvoiceDate()) {
            $this
                ->setInvoiceDate(time());
        }

        return parent::preSave($con);
    }

    /**
     * @throws PropelException
     */
    public function postInsert(?ConnectionInterface $con = null): void
    {
        parent::postInsert($con);

        $this->setRef($this->generateRef())
            ->setDisableVersioning(true)
            ->save($con);
    }

    public function generateRef()
    {
        return \sprintf('ORD%s', str_pad((string) $this->getId(), 12, '0', \STR_PAD_LEFT));
    }

    /**
     * Compute this order amount with taxes. The tax amount is returned in the $tax parameter.
     *
     * The order amount is only available once the order is persisted in database.
     * During invoice process, use all cart methods instead of order methods (the order doest not exists at this moment)
     *
     * @param float|int $tax             (output only) returns the tax amount for this order
     * @param bool      $includePostage  if true, the postage cost is included to the total
     * @param bool      $includeDiscount if true, the discount will be included to the total
     *
     * @throws PropelException
     */
    public function getTotalAmount(float|int &$tax = 0, bool $includePostage = true, bool $includeDiscount = true): float
    {
        // To prevent price changes in pre-2.4 orders, use the legacy calculation method
        if ($this->getId() <= ConfigQuery::read('last_legacy_rounding_order_id', 0)) {
            return $this->getTotalAmountLegacy($tax, $includePostage, $includeDiscount);
        }

        // Cache the query result. Wa have to une and array indexed on the order ID, as the cache ios static
        // and may cache results for several orders, for example in the order list in the back-office.
        static $queryResult = [];

        $id = $this->getId();

        if (!isset($queryResult[$id]) || null === $queryResult[$id]) {
            // Shoud be the same rounding method as in CartItem::getTotalTaxedPrice()
            // For each order line, we round quantity x taxed price.
            $query = '
                SELECT
                    SUM(
                        '.OrderProductTableMap::COL_QUANTITY.'
                        *
                        (
                            ROUND(
                                IF('.OrderProductTableMap::COL_WAS_IN_PROMO.'=1, '.OrderProductTableMap::COL_PROMO_PRICE.', '.OrderProductTableMap::COL_PRICE.'),
                                2
                            )
                            +
                            (
                                SELECT COALESCE(
                                    SUM(
                                        ROUND(
                                            IF('.OrderProductTableMap::COL_WAS_IN_PROMO.'=1, '.OrderProductTaxTableMap::COL_PROMO_AMOUNT.', '.OrderProductTaxTableMap::COL_AMOUNT.'),
                                            2
                                        )
                                    ),
                                0)
                                FROM '.OrderProductTaxTableMap::TABLE_NAME.'
                                WHERE '.OrderProductTaxTableMap::COL_ORDER_PRODUCT_ID.' = '.OrderProductTableMap::COL_ID.'
                            )
                        )
                    ) as total_taxed_price,
                    SUM(
                        '.OrderProductTableMap::COL_QUANTITY.'
                        *
                        ROUND(
                            IF(
                                '.OrderProductTableMap::COL_WAS_IN_PROMO.'=1,
                                '.OrderProductTableMap::COL_PROMO_PRICE.',
                                '.OrderProductTableMap::COL_PRICE.'
                            ), 2
                        )
                    ) as total_untaxed_price
                from
                    '.OrderProductTableMap::TABLE_NAME.'
                where
                    '.OrderProductTableMap::COL_ORDER_ID.'=:order_id
            ';

            $con = Propel::getConnection();
            $stmt = $con->prepare($query);

            if (false === $stmt->execute([':order_id' => $this->getId()])) {
                throw new TheliaProcessException(\sprintf('Failed to get order total and order tax: %s (%s)', $stmt->errorInfo(), $stmt->errorCode()));
            }

            $queryResult[$id] = $stmt->fetch(\PDO::FETCH_OBJ);
        }

        $total = (float) $queryResult[$id]->total_taxed_price;
        $tax = $total - (float) $queryResult[$id]->total_untaxed_price;

        if (true === $includeDiscount) {
            $total -= $this->getDiscount();
            $tax -= $this->getDiscount() - Calculator::getUntaxedOrderDiscount($this);

            if ($total < 0) {
                $total = 0;
            }

            if ($tax < 0) {
                $tax = 0;
            }
        }

        if (false !== $includePostage) {
            $total += (float) $this->getPostage();
            $tax += (float) $this->getPostageTax();
        }

        return $total;
    }

    /**
     * This is thge legacy way of computing this order amount with taxes. The tax amount is returned in the $tax parameter.
     *
     * The order amount is only available once the order is persisted in database.
     * During invoice process, use all cart methods instead of order methods (the order doest not exists at this moment)
     *
     * @param float|int $tax             (output only) returns the tax amount for this order
     * @param bool      $includePostage  if true, the postage cost is included to the total
     * @param bool      $includeDiscount if true, the discount will be included to the total
     *
     * @throws PropelException
     */
    public function getTotalAmountLegacy(float|int &$tax = 0, bool $includePostage = true, bool $includeDiscount = true): float
    {
        $amount = (float) OrderProductQuery::create()
            ->filterByOrderId($this->getId())
            ->withColumn('SUM(
                    '.OrderProductTableMap::COL_QUANTITY.'
                    * IF('.OrderProductTableMap::COL_WAS_IN_PROMO.' = 1, '.OrderProductTableMap::COL_PROMO_PRICE.', '.OrderProductTableMap::COL_PRICE.')
                )', 'total_amount')
            ->select(['total_amount'])
            ->findOne();

        $tax = (float) OrderProductTaxQuery::create()
            ->useOrderProductQuery()
            ->filterByOrderId($this->getId())
            ->endUse()
            ->withColumn('SUM(
                    '.OrderProductTableMap::COL_QUANTITY.'
                    * IF('.OrderProductTableMap::COL_WAS_IN_PROMO.' = 1, '.OrderProductTaxTableMap::COL_PROMO_AMOUNT.', '.OrderProductTaxTableMap::COL_AMOUNT.')
                )', 'total_tax')
            ->select(['total_tax'])
            ->findOne();

        $total = $amount + $tax;

        // @todo : manage discount : free postage ?
        if (true === $includeDiscount) {
            $total -= $this->getDiscount();

            if ($total < 0) {
                $total = 0;
            }
        }

        if (false !== $includePostage) {
            $total += (float) $this->getPostage();
            $tax += (float) $this->getPostageTax();
        }

        return $total;
    }

    /**
     * Compute this order weight.
     *
     * The order weight is only available once the order is persisted in database.
     * During invoice process, use all cart methods instead of order methods (the order doest not exists at this moment)
     *
     * @throws PropelException
     */
    public function getWeight(): float
    {
        $weight = 0;

        /* browse all products */
        foreach ($this->getOrderProducts() as $orderProduct) {
            $weight += $orderProduct->getQuantity() * (float) $orderProduct->getWeight();
        }

        return $weight;
    }

    /**
     * Return the postage without tax.
     */
    public function getUntaxedPostage(): float|int
    {
        return 0 < $this->getPostageTax()
            ? $this->getPostage() - $this->getPostageTax()
            : (float) $this->getPostage();
    }

    /**
     * Check if the current order contains at less 1 virtual product with a file to download.
     *
     * @return bool true if this order have at less 1 file to download, false otherwise
     */
    public function hasVirtualProduct(): bool
    {
        $virtualProductCount = OrderProductQuery::create()
            ->filterByOrderId($this->getId())
            ->filterByVirtual(1, Criteria::EQUAL)
            ->count();

        return 0 !== $virtualProductCount;
    }

    /**
     * Set the status of the current order to NOT PAID.
     *
     * @throws PropelException
     */
    public function setNotPaid(): void
    {
        $this->setStatusHelper(OrderStatus::CODE_NOT_PAID);
    }

    /**
     * Check if the current status of this order is NOT PAID.
     *
     * @param bool $exact if true, the status should be the exact required status, not a derived one
     *
     * @return bool true if this order is NOT PAID, false otherwise
     *
     * @throws PropelException
     */
    public function isNotPaid(bool $exact = true): bool
    {
        return $this->getOrderStatus()->isNotPaid($exact);
    }

    /**
     * Set the status of the current order to PAID.
     *
     * @throws PropelException
     */
    public function setPaid(): void
    {
        $this->setStatusHelper(OrderStatus::CODE_PAID);
    }

    /**
     * Check if the current status of this order is PAID.
     *
     * @param bool $exact if true, the status should be the exact required status, not a derived one
     *
     * @return bool true if this order is PAID, false otherwise
     *
     * @throws PropelException
     */
    public function isPaid(bool $exact = true): bool
    {
        return $this->getOrderStatus()->isPaid($exact);
    }

    /**
     * Set the status of the current order to PROCESSING.
     *
     * @throws PropelException
     */
    public function setProcessing(): void
    {
        $this->setStatusHelper(OrderStatus::CODE_PROCESSING);
    }

    /**
     * Check if the current status of this order is PROCESSING.
     *
     * @param bool $exact if true, the status should be the exact required status, not a derived one
     *
     * @return bool true if this order is PROCESSING, false otherwise
     *
     * @throws PropelException
     */
    public function isProcessing(bool $exact = true): bool
    {
        return $this->getOrderStatus()->isProcessing($exact);
    }

    /**
     * Set the status of the current order to SENT.
     *
     * @throws PropelException
     */
    public function setSent(): void
    {
        $this->setStatusHelper(OrderStatus::CODE_SENT);
    }

    /**
     * Check if the current status of this order is SENT.
     *
     * @param bool $exact if true, the status should be the exact required status, not a derived one
     *
     * @return bool true if this order is SENT, false otherwise
     *
     * @throws PropelException
     */
    public function isSent(bool $exact = true): bool
    {
        return $this->getOrderStatus()->isSent($exact);
    }

    /**
     * Set the status of the current order to CANCELED.
     *
     * @throws PropelException
     */
    public function setCancelled(): void
    {
        $this->setStatusHelper(OrderStatus::CODE_CANCELED);
    }

    /**
     * Check if the current status of this order is CANCELED.
     *
     * @param bool $exact if true, the status should be the exact required status, not a derived one
     *
     * @return bool true if this order is CANCELED, false otherwise
     *
     * @throws PropelException
     */
    public function isCancelled(bool $exact = true): bool
    {
        return $this->getOrderStatus()->isCancelled($exact);
    }

    /**
     * Set the status of the current order to REFUNDED.
     *
     * @throws PropelException
     */
    public function setRefunded(): void
    {
        $this->setStatusHelper(OrderStatus::CODE_REFUNDED);
    }

    /**
     * Check if the current status of this order is REFUNDED.
     *
     * @param bool $exact if true, the status should be the exact required status, not a derived one
     *
     * @return bool true if this order is REFUNDED, false otherwise
     *
     * @throws PropelException
     */
    public function isRefunded(bool $exact = true): bool
    {
        return $this->getOrderStatus()->isRefunded($exact);
    }

    /**
     * Set the status of the current order to the provided status.
     *
     * @param string $statusCode the status code, one of OrderStatus::CODE_xxx constants
     *
     * @throws PropelException
     */
    public function setStatusHelper(string $statusCode): void
    {
        if (null !== $ordeStatus = OrderStatusQuery::create()->findOneByCode($statusCode)) {
            $this->setOrderStatus($ordeStatus)->save();
        }
    }

    /**
     * Get an instance of the payment module.
     *
     * @throws TheliaProcessException
     */
    public function getPaymentModuleInstance(): PaymentModuleInterface
    {
        if (null === $paymentModule = ModuleQuery::create()->findPk($this->getPaymentModuleId())) {
            throw new TheliaProcessException('Payment module ID='.$this->getPaymentModuleId().' was not found.');
        }

        return $paymentModule->createInstance();
    }

    /**
     * Get an instance of the delivery module.
     *
     * @throws TheliaProcessException
     */
    public function getDeliveryModuleInstance(): BaseModuleInterface
    {
        if (null === $deliveryModule = ModuleQuery::create()->findPk($this->getDeliveryModuleId())) {
            throw new TheliaProcessException('Delivery module ID='.$this->getDeliveryModuleId().' was not found.');
        }

        return $deliveryModule->createInstance();
    }

    /**
     * Check if stock was decreased at stock creation for this order.
     * TODO : we definitely have to store modules in an order_modules table juste like order_product and other order related information.
     *
     * @return bool true if the stock was decreased at order creation, false otherwise
     */
    public function isStockManagedOnOrderCreation(EventDispatcherInterface $dispatcher): bool
    {
        $paymentModule = $this->getPaymentModuleInstance();

        $event = new ManageStockOnCreationEvent($paymentModule);

        $dispatcher->dispatch(
            $event,
            TheliaEvents::getModuleEvent(
                TheliaEvents::MODULE_PAYMENT_MANAGE_STOCK,
                $paymentModule->getCode(),
            ),
        );

        return $event->getManageStock() ?? $paymentModule->manageStockOnCreation();
    }
}
