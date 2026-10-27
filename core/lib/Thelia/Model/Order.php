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
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\Payment\ManageStockOnCreationEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Domain\Order\Service\SequenceOrderRefGenerator;
use Thelia\Domain\Sequence\GaplessSequenceGenerator;
use Thelia\Domain\Taxation\TaxEngine\TaxCalculatorResolverTrait;
use Thelia\Exception\TheliaProcessException;
use Thelia\Model\Base\Order as BaseOrder;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\OrderProductTaxTableMap;
use Thelia\Model\Map\OrderTableMap;
use Thelia\Module\BaseModuleInterface;
use Thelia\Module\PaymentModuleInterface;

class Order extends BaseOrder
{
    use TaxCalculatorResolverTrait;

    private static int $writeGeneration = 0;

    /** @var array{untaxed: float, taxed: float}|null */
    private ?array $serviceLinesTotals = null;

    protected ?int $choosenDeliveryAddress = null;

    protected ?int $choosenInvoiceAddress = null;

    protected bool $disableVersioning = false;

    protected bool $refGenerationDeferred = false;

    protected ?Cart $cart = null;

    /**
     * @return $this
     */
    public function setDisableVersioning(bool $disableVersioning): static
    {
        $this->disableVersioning = $disableVersioning;

        return $this;
    }

    public function isVersioningDisable(): bool
    {
        return $this->disableVersioning;
    }

    public function isVersioningNecessary(?ConnectionInterface $con = null): bool
    {
        if ($this->isVersioningDisable()) {
            return false;
        }

        return parent::isVersioningNecessary($con);
    }

    /**
     * @param int $choosenDeliveryAddress the choosen delivery address ID
     *
     * @deprecated Use the cart model
     *
     * @return $this
     */
    public function setChoosenDeliveryAddress($choosenDeliveryAddress): static
    {
        return $this;
    }

    /**
     * @return int|null the choosen delivery address ID
     *
     * @deprecated Use the cart model
     */
    public function getChoosenDeliveryAddress(): ?int
    {
        // The cart points at its own copy of the address, in `cart_address`.
        // Every caller looks the answer up in `address`.
        $this->choosenDeliveryAddress = $this->getCart()
            ?->getCartAddressRelatedByAddressDeliveryId()
            ?->getAddressId();

        return $this->choosenDeliveryAddress;
    }

    /**
     * @param int $choosenInvoiceAddress the choosen invoice address
     *
     * @return $this
     *
     * @deprecated Use the cart model
     */
    public function setChoosenInvoiceAddress(int $choosenInvoiceAddress): static
    {
        $this->choosenInvoiceAddress = $choosenInvoiceAddress;

        return $this;
    }

    /**
     * @return int|null the choosen invoice address ID
     *
     * @deprecated Use the cart model
     */
    public function getChoosenInvoiceAddress(): ?int
    {
        $this->choosenInvoiceAddress = $this->getCart()
            ?->getCartAddressRelatedByAddressInvoiceId()
            ?->getAddressId();

        return $this->choosenInvoiceAddress;
    }

    /**
     * How many order rows this process has written or deleted so far.
     *
     * A reader that memoizes an answer drawn from `order` rows keys it on this value:
     * the answer stands until an order is saved or deleted, whatever the path that
     * wrote it — the status flow, a direct save(), a delete.
     */
    public static function writeGeneration(): int
    {
        return self::$writeGeneration;
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
    /**
     * Defer reference generation to the caller (e.g. OrderFacade, which
     * allocates the ref from the gapless sequence just before commit, keeping
     * the sequence lock window as short as possible).
     *
     * @return $this
     */
    public function deferRefGeneration(bool $deferred = true): static
    {
        $this->refGenerationDeferred = $deferred;

        return $this;
    }

    public function postSave(?ConnectionInterface $con = null): void
    {
        ++self::$writeGeneration;

        parent::postSave($con);
    }

    public function postDelete(?ConnectionInterface $con = null): void
    {
        ++self::$writeGeneration;

        parent::postDelete($con);
    }

    public function postInsert(?ConnectionInterface $con = null): void
    {
        parent::postInsert($con);

        if ($this->refGenerationDeferred || null !== $this->getRef()) {
            return;
        }

        $this->setRef($this->generateRef($con))
            ->setDisableVersioning(true)
            ->save($con);
    }

    /**
     * Allocates the next reference from the gapless order_ref sequence.
     *
     * Each call consumes a number: only call this to assign a reference that
     * will be persisted. Since Thelia 3 the reference is no longer derived
     * from the order id.
     */
    public function generateRef(?ConnectionInterface $con = null): string
    {
        $con ??= Propel::getConnection(OrderTableMap::DATABASE_NAME);

        return SequenceOrderRefGenerator::format(
            (new GaplessSequenceGenerator())->next(SequenceOrderRefGenerator::SEQUENCE_NAME, $con)
        );
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
        if (ConfigQuery::isOrderWithLegacyRounding((int) $this->getId())) {
            return $this->getTotalAmountLegacy($tax, $includePostage, $includeDiscount);
        }

        // Cache the query result. Wa have to une and array indexed on the order ID, as the cache ios static
        // and may cache results for several orders, for example in the order list in the back-office.
        // The rounding mode and the reading of the promotional price are part of the key: they decide what the query computes.
        static $queryResult = [];

        $roundingMode = ConfigQuery::getOrderRoundingMode((int) $this->getId());
        $textPromoPrice = ConfigQuery::isOrderWithTextPromoPrice((int) $this->getId());
        $id = $this->getId().'-'.$roundingMode.'-'.(int) $textPromoPrice;

        if (!isset($queryResult[$id]) || null === $queryResult[$id]) {
            $con = Propel::getConnection();
            $stmt = $con->prepare($this->buildTotalAmountQuery($roundingMode, $textPromoPrice));

            if (false === $stmt->execute([':order_id' => $this->getId()])) {
                throw new TheliaProcessException(\sprintf('Failed to get order total and order tax: %s (%s)', implode(', ', $stmt->errorInfo()), $stmt->errorCode()));
            }

            $queryResult[$id] = $stmt->fetch(\PDO::FETCH_OBJ);
        }

        $total = (float) $queryResult[$id]->total_taxed_price;
        $tax = $total - (float) $queryResult[$id]->total_untaxed_price;

        if (true === $includeDiscount) {
            $total -= $this->getDiscount();
            $tax -= $this->getDiscount() - $this->getUntaxedDiscount();

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
     * Totals the order lines the way CartItem::getTotalPrice() and
     * CartItem::getTotalTaxedPrice() total the cart lines, so that the amount
     * charged matches the amount the customer saw in the cart.
     *
     * With ROUNDING_MODE_SUM_OF_ROUNDINGS every unit amount is rounded to the
     * cent before being multiplied by the quantity. With
     * ROUNDING_MODE_ROUNDING_OF_SUMS the multiplication happens at the
     * precision the prices are stored with, and only the line total is rounded.
     *
     * With $textPromoPrice the promotional price is read as a text, the way it was
     * read while order_product.promo_price was a VARCHAR. The type of IF() is the
     * one of its operands, so a text promotional price makes the whole unit price a
     * text, the price of a line that is not on promotion included, and ROUND() then
     * reads it as a floating point number.
     */
    private function buildTotalAmountQuery(int $roundingMode, bool $textPromoPrice): string
    {
        $roundingOfSums = ConfigQuery::ROUNDING_MODE_ROUNDING_OF_SUMS === $roundingMode;

        $unitPrice = 'IF('.OrderProductTableMap::COL_WAS_IN_PROMO.'=1, '.$this->promoPriceOperand($textPromoPrice).', '.OrderProductTableMap::COL_PRICE.')';
        $unitTax = 'IF('.OrderProductTableMap::COL_WAS_IN_PROMO.'=1, '.OrderProductTaxTableMap::COL_PROMO_AMOUNT.', '.OrderProductTaxTableMap::COL_AMOUNT.')';

        if (!$roundingOfSums) {
            $unitPrice = 'ROUND('.$unitPrice.', 2)';
            $unitTax = 'ROUND('.$unitTax.', 2)';
        }

        $unitTaxes = '(
            SELECT COALESCE(SUM('.$unitTax.'), 0)
            FROM '.OrderProductTaxTableMap::TABLE_NAME.'
            WHERE '.OrderProductTaxTableMap::COL_ORDER_PRODUCT_ID.' = '.OrderProductTableMap::COL_ID.'
        )';

        $taxedLineTotal = OrderProductTableMap::COL_QUANTITY.' * ('.$unitPrice.' + '.$unitTaxes.')';
        $untaxedLineTotal = OrderProductTableMap::COL_QUANTITY.' * '.$unitPrice;

        if ($roundingOfSums) {
            $taxedLineTotal = 'ROUND('.$taxedLineTotal.', 2)';
            $untaxedLineTotal = 'ROUND('.$untaxedLineTotal.', 2)';
        }

        return '
            SELECT
                SUM('.$taxedLineTotal.') as total_taxed_price,
                SUM('.$untaxedLineTotal.') as total_untaxed_price
            FROM '.OrderProductTableMap::TABLE_NAME.'
            WHERE '.OrderProductTableMap::COL_ORDER_ID.' = :order_id
        ';
    }

    private function promoPriceOperand(bool $textPromoPrice): string
    {
        return $textPromoPrice
            ? 'COALESCE(CAST('.OrderProductTableMap::COL_PROMO_PRICE.' AS CHAR), \'\')'
            : OrderProductTableMap::COL_PROMO_PRICE;
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
                    * IF('.OrderProductTableMap::COL_WAS_IN_PROMO.' = 1, '.$this->promoPriceOperand(ConfigQuery::isOrderWithTextPromoPrice((int) $this->getId())).', '.OrderProductTableMap::COL_PRICE.')
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
     *
     * The postage column is stored tax included, like Cart::getPostage().
     */
    public function getUntaxedPostage(): float|int
    {
        return 0 < $this->getPostageTax()
            ? ((float) $this->getPostage() - (float) $this->getPostageTax())
            : (float) $this->getPostage();
    }

    /**
     * The part of the discount that is not tax. One reading for the order loop (DISCOUNT_WITHOUT_TAX)
     * and the API (amountDiscountWithoutTaxes), so that they cannot drift apart.
     *
     * @throws PropelException
     */
    public function getUntaxedDiscount(): float
    {
        // Legacy orders have no discount tax calculation: their discount is read untaxed as it is stored.
        if (ConfigQuery::isOrderWithLegacyRounding((int) $this->getId())) {
            return (float) $this->getDiscount();
        }

        return (float) $this->createTaxCalculator()->computeUntaxedOrderDiscount($this);
    }

    /**
     * The order total without tax, the way getTotalAmount() builds it: the tax it reports
     * is taken out of the total it reports, so the two always add up.
     *
     * @throws PropelException
     */
    public function getUntaxedTotalAmount(bool $includePostage = true, bool $includeDiscount = true): float
    {
        $tax = 0;
        $total = $this->getTotalAmount($tax, $includePostage, $includeDiscount);

        return $total - $tax;
    }

    /**
     * What the buyer pays for the goods once the discount is taken off and the postage left out,
     * to the cent: the API's totalAmountWithTaxesAfterDiscount. The one reading of it, so that a
     * theme which holds the model and the API resource cannot show two figures.
     *
     * @throws PropelException
     */
    public function getTotalAmountWithTaxesAfterDiscount(): float
    {
        return round(round($this->getTotalAmount(), 2) - round((float) $this->getPostage(), 2), 2);
    }

    /**
     * The goods of the order without tax, before the discount and without the postage, to the
     * cent: the API's totalAmountWithoutTaxes, which the order loop publishes uncut as
     * TOTAL_ITEMS_AMOUNT.
     *
     * @throws PropelException
     */
    public function getTotalAmountWithoutTaxes(): float
    {
        return round($this->getUntaxedTotalAmount(false, false), 2);
    }

    /**
     * The articles of the order without tax, before the discount and without the postage, to the
     * cent, the service lines (a gift wrapping) left out: the API's totalGoodsWithoutTaxes and
     * the order loop's TOTAL_GOODS_AMOUNT. totalAmountWithoutTaxes counts those lines in.
     *
     * @throws PropelException
     */
    public function getTotalGoodsWithoutTaxes(): float
    {
        return round($this->getTotalAmountWithoutTaxes() - $this->serviceLinesTotal('untaxed'), 2);
    }

    /**
     * The same articles with their tax: the API's totalGoodsWithTaxes and the order loop's
     * TOTAL_TAXED_GOODS_AMOUNT.
     *
     * @throws PropelException
     */
    public function getTotalGoodsWithTaxes(): float
    {
        $tax = 0;

        return round(round($this->getTotalAmount($tax, false, false), 2) - $this->serviceLinesTotal('taxed'), 2);
    }

    /**
     * What the service lines of the order were charged, for their whole quantity, read once for
     * the two figures: both goods totals ask for it, and a collection of orders prints both for
     * each of its orders, which made two queries an order. Held by the instance and forgotten when
     * its lines move (reload, addOrderProduct, clearOrderProducts).
     *
     * @param 'untaxed'|'taxed' $kind
     *
     * @throws PropelException
     */
    private function serviceLinesTotal(string $kind): float
    {
        if (null === $this->serviceLinesTotals) {
            $totals = ['untaxed' => 0.0, 'taxed' => 0.0];

            foreach (OrderProductQuery::create()->filterByOrderId($this->getId())->filterByLineType(OrderProduct::LINE_TYPE_SERVICE)->find() as $serviceLine) {
                $charged = $serviceLine->getChargedLineTotals();
                $totals['untaxed'] += $charged['untaxed'];
                $totals['taxed'] += $charged['taxed'];
            }

            $this->serviceLinesTotals = $totals;
        }

        return $this->serviceLinesTotals[$kind];
    }

    public function reload(bool $deep = false, ?ConnectionInterface $con = null): void
    {
        $this->serviceLinesTotals = null;

        parent::reload($deep, $con);
    }

    public function addOrderProduct(OrderProduct $l): static
    {
        $this->serviceLinesTotals = null;

        return parent::addOrderProduct($l);
    }

    public function clearOrderProducts(): static
    {
        $this->serviceLinesTotals = null;

        return parent::clearOrderProducts();
    }

    /**
     * The goods of the order without tax, discount taken off, postage left out, to the cent:
     * the API's totalAmountWithoutTaxesAfterDiscount.
     *
     * @throws PropelException
     */
    public function getTotalAmountWithoutTaxesAfterDiscount(): float
    {
        return round($this->getUntaxedTotalAmount(false), 2);
    }

    /**
     * The postage without its tax, to the cent: the API's totalShippingWithoutTaxes.
     */
    public function getTotalShippingWithoutTaxes(): float
    {
        return round((float) $this->getUntaxedPostage(), 2);
    }

    /**
     * The discount without its tax, to the cent: the API's amountDiscountWithoutTaxes.
     *
     * @throws PropelException
     */
    public function getAmountDiscountWithoutTaxes(): float
    {
        return round($this->getUntaxedDiscount(), 2);
    }

    /**
     * What the buyer pays, tax included: the goods, the postage, the discount taken off, to the cent.
     * The API's totalAmount, and the sum of the two figures a recap prints above it
     * (getTotalAmountWithTaxesAfterDiscount() and getTotalShippingWithTaxes()).
     *
     * @throws PropelException
     */
    public function getTotalAmountWithTaxesWithShipping(): float
    {
        return round($this->getTotalAmount(), 2);
    }

    /**
     * The discount, tax included, to the cent: the API's amountDiscountWithTaxes. Its untaxed part
     * is getAmountDiscountWithoutTaxes().
     */
    public function getAmountDiscountWithTaxes(): float
    {
        return round((float) $this->getDiscount(), 2);
    }

    /**
     * The postage, tax included, to the cent: the API's totalShippingWithTaxes. Its untaxed part is
     * getTotalShippingWithoutTaxes().
     */
    public function getTotalShippingWithTaxes(): float
    {
        return round((float) $this->getPostage(), 2);
    }

    /**
     * The tax of the postage, to the cent: the API's postageTax.
     */
    public function getTotalShippingTaxAmount(): float
    {
        return round((float) $this->getPostageTax(), 2);
    }

    /**
     * What the order is worth without tax, postage included and discount taken off, to the
     * cent: the API's totalAmountWithoutTaxesWithShipping and the order loop's TOTAL_AMOUNT.
     * It is the sum of the two figures a recap prints above it, so that the recap adds up.
     *
     * @throws PropelException
     */
    public function getTotalAmountWithoutTaxesWithShipping(): float
    {
        return round($this->getTotalAmountWithoutTaxesAfterDiscount() + $this->getTotalShippingWithoutTaxes(), 2);
    }

    /**
     * All the tax of the order, postage included and discount taken off: the API's
     * totalTaxAmount and the order loop's TOTAL_TAX. It is what is left of the total
     * once the untaxed goods and the untaxed postage are taken out, each cut to the cent,
     * so that untaxed goods + untaxed postage + tax = total, whichever rounding mode the
     * order was invoiced with, and the untaxed total + tax = total with it.
     *
     * @throws PropelException
     */
    public function getTotalTaxAmount(): float
    {
        return round(
            round($this->getTotalAmount(), 2)
            - $this->getTotalAmountWithoutTaxesAfterDiscount()
            - $this->getTotalShippingWithoutTaxes(),
            2,
        );
    }

    public function getVatExempted(): bool
    {
        return 1 === $this->getOrderAddressRelatedByInvoiceOrderAddressId()?->getVatExempted();
    }

    /**
     * Check if the current order contains at least 1 virtual product, whether it has a document to
     * download or not. Virtual delivery modules that do not rely on the core document mechanism use
     * this method, so its scope is intentionally broad.
     *
     * @return bool true if this order has at least 1 virtual product, false otherwise
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
     * Check if the current order contains at least 1 virtual product having a document to download.
     *
     * @return bool true if this order has at least 1 file to download, false otherwise
     */
    public function hasVirtualProductWithDocument(): bool
    {
        $downloadableProductCount = OrderProductQuery::create()
            ->filterByOrderId($this->getId())
            ->filterByVirtual(1, Criteria::EQUAL)
            ->filterByVirtualDocument(null, Criteria::NOT_EQUAL)
            ->count();

        return 0 !== $downloadableProductCount;
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
     * Cancel the order through the status flow.
     *
     * Raised as ORDER_UPDATE_STATUS rather than written to the row: that is what gives
     * back the stock taken at the placement, and what runs the listeners a shop has on
     * its statuses. The dispatcher is asked for the same way isStockManagedOnOrderCreation()
     * asks for it — a model has no service of its own to reach for.
     *
     * @throws PropelException
     */
    public function setCancelled(EventDispatcherInterface $dispatcher): void
    {
        $cancelled = OrderStatusQuery::create()->findOneByCode(OrderStatus::CODE_CANCELED);

        if (null === $cancelled) {
            return;
        }

        $dispatcher->dispatch(
            (new OrderEvent($this))->setStatus((int) $cancelled->getId()),
            TheliaEvents::ORDER_UPDATE_STATUS,
        );
    }

    /**
     * The code the customer group of the order has now, null once that group is deleted.
     *
     * Not frozen: the order stores the group id, the discount rate and the taxed or
     * untaxed display of the group at the time of the order, and the code is read on the
     * group as it is today, so a renamed group shows its new code on its old orders.
     */
    public function getCustomerGroupCode(): ?string
    {
        return $this->getCustomerGroup()?->getCode();
    }

    /**
     * The day the buyer asked to be delivered, as Y-m-d: a calendar day of the shop, which
     * a reader must not shift into another time zone. Null when the carrier offered none.
     */
    public function getDeliveryDay(): ?string
    {
        $day = $this->getDeliveryDate('Y-m-d');

        return \is_string($day) ? $day : null;
    }

    /**
     * The local hour the slot the buyer picked starts at, as H:i, null without a slot.
     */
    public function getDeliverySlotStartsAt(): ?string
    {
        $hour = $this->getDeliverySlotStart('H:i');

        return \is_string($hour) ? $hour : null;
    }

    /**
     * The local hour the slot the buyer picked ends at, as H:i, null without a slot.
     */
    public function getDeliverySlotEndsAt(): ?string
    {
        $hour = $this->getDeliverySlotEnd('H:i');

        return \is_string($hour) ? $hour : null;
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
            throw new TheliaProcessException(\sprintf('The payment module of order "%s" is not installed anymore (%s).', (string) $this->getRef(), $this->getPaymentModuleTitle() ?? 'ID='.$this->getPaymentModuleId()));
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
            throw new TheliaProcessException(\sprintf('The delivery module of order "%s" is not installed anymore (%s).', (string) $this->getRef(), $this->getDeliveryModuleTitle() ?? 'ID='.$this->getDeliveryModuleId()));
        }

        return $deliveryModule->createInstance();
    }

    /**
     * Check if stock was decreased at stock creation for this order.
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

    protected function getCart(): ?Cart
    {
        if (!$cartId = $this->getCartId()) {
            return null;
        }

        return CartQuery::create()->filterById($cartId)->findOne();
    }

    public function setPostageTax(?string $v = null): static
    {
        return parent::setPostageTax($v ?? '0');
    }
}
