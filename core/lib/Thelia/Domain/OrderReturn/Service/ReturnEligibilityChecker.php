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

namespace Thelia\Domain\OrderReturn\Service;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Thelia\Config\DatabaseConfiguration;
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Order;
use Thelia\Model\OrderHistoryQuery;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderReturnLineQuery;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;
use Thelia\Model\OrderStatus;

/**
 * The single source of truth for whether a return may be opened, and for which
 * quantity. The customer front (Flexy), the front API and the back-office all
 * go through it, so the rules of the return specification are enforced once:
 *
 *  - the returns feature is enabled on the shop;
 *  - the order is paid, or in one of the statuses the shop opens returns on,
 *    and still inside the retraction window;
 *  - the order belongs to the customer opening the return;
 *  - a virtual product is never returnable;
 *  - the requested quantity may not exceed the ordered quantity, cumulative
 *    open requests included, unless the shop counts each request on its own.
 *
 * A refused or expired return frees the quantity it held.
 */
final class ReturnEligibilityChecker
{
    public const ENABLED_CONFIG_KEY = 'order_return_enabled';
    public const WINDOW_CONFIG_KEY = 'order_return_window_days';
    public const DEFAULT_WINDOW_DAYS = 14;

    /**
     * The order statuses a return may be opened on, as a comma separated list of
     * status codes, compared on their effective code. Empty by default: any paid
     * status, the rule until now. A shop that takes back what it has shipped only
     * lists `sent`, and the return button, the form and every opening path follow.
     */
    public const ORDER_STATUSES_CONFIG_KEY = 'order_return_order_statuses';

    /**
     * Whether the returns already opened on a line hold part of its quantity.
     * On by default: a unit sent back once cannot be asked back a second time.
     * A shop that settles an exchange with a new parcel turns it off, so the
     * goods it shipped again can be returned in turn: each request is then
     * bounded by the ordered quantity alone.
     */
    public const CUMULATIVE_QUANTITY_CONFIG_KEY = 'order_return_cumulative_quantity';

    /**
     * Quantities are floats - Thelia sells by weight and by length as much as
     * by the piece - so the remaining quantity is a subtraction of floats and
     * carries its representation error: 0.7 - 0.3 is 0.39999999999999997, and
     * a strict comparison refuses the 0.4 the shop displays. Six decimals is
     * finer than any quantity a shop sells in and coarser than the error, so
     * it separates the two without ever granting a unit that is not there.
     */
    public const QUANTITY_PRECISION = 6;
    public const QUANTITY_TOLERANCE = 1e-6;

    public function __construct(
        private readonly OrderReturnWriteTransaction $transaction,
    ) {
    }

    /**
     * Whether the returns feature is enabled on the shop.
     */
    public function isFeatureEnabled(): bool
    {
        return '1' === ConfigQuery::read(self::ENABLED_CONFIG_KEY, '0');
    }

    /**
     * The configured retraction window, in days.
     */
    public function windowDays(): int
    {
        return (int) ConfigQuery::read(self::WINDOW_CONFIG_KEY, (string) self::DEFAULT_WINDOW_DAYS);
    }

    /**
     * The order status codes a return may be opened on; an empty list means any
     * paid status.
     *
     * @return list<string>
     */
    public function openingStatusCodes(): array
    {
        $codes = array_map('trim', explode(',', (string) ConfigQuery::read(self::ORDER_STATUSES_CONFIG_KEY, '')));

        return array_values(array_filter($codes, static fn (string $code): bool => '' !== $code));
    }

    /**
     * Whether the status of the order lets a return be opened on it.
     */
    public function isOpeningStatus(OrderStatus $status): bool
    {
        $codes = $this->openingStatusCodes();

        return [] === $codes ? $status->isPaid(false) : $status->hasStatusHelper($codes);
    }

    /**
     * Whether the open returns of a line hold part of its returnable quantity.
     */
    public function isQuantityCumulative(): bool
    {
        return '0' !== ConfigQuery::read(self::CUMULATIVE_QUANTITY_CONFIG_KEY, '1');
    }

    /**
     * Whether the retraction window is still open for the order.
     *
     * The window runs from the day the goods left, which is the day the order last
     * moved to the "sent" status in its history: a customer who waited three weeks
     * for a delivery has not spent their retraction period waiting for it. "Last"
     * rather than "first" because an order sent again after coming back starts a new
     * window, which is the reading that favours the buyer.
     *
     * An order placed before the history existed, or one that has not been marked as
     * sent, has no such date on file and falls back to its creation date — the rule
     * that applied to it until now, so no window closes or opens because of this.
     */
    public function isWithinReturnWindow(Order $order): bool
    {
        $status = $order->getOrderStatus();
        if (null === $status || !$this->isOpeningStatus($status)) {
            return false;
        }

        $windowStart = $this->returnWindowStartDate($order);
        if (null === $windowStart) {
            return false;
        }

        return (clone $windowStart)->modify(\sprintf('+%d days', $this->windowDays())) >= new \DateTime();
    }

    /**
     * The date the retraction window is counted from: the last shipment, failing
     * that the order creation.
     */
    private function returnWindowStartDate(Order $order): ?\DateTimeInterface
    {
        $orderId = $order->getId();

        if (null !== $orderId) {
            $shipment = OrderHistoryQuery::create()
                ->findLastStatusChangeTo($orderId, OrderStatus::CODE_SENT);

            $shippedAt = $shipment?->getCreatedAt();

            if ($shippedAt instanceof \DateTimeInterface) {
                return $shippedAt;
            }
        }

        return $order->getCreatedAt();
    }

    /**
     * Whether the customer may still open a return for this order: the feature
     * is on, the order is paid, the window is open and at least one line is
     * still returnable.
     */
    public function isReturnable(Order $order): bool
    {
        return $this->isFeatureEnabled()
            && $this->isWithinReturnWindow($order)
            && [] !== $this->returnableLines($order);
    }

    /**
     * The order lines that can still be returned, with the quantity still available.
     *
     * @return list<array{order_product: OrderProduct, remaining: float}>
     */
    public function returnableLines(Order $order): array
    {
        $lines = [];

        foreach ($order->getOrderProducts() as $orderProduct) {
            if (!$this->isProductReturnable($orderProduct)) {
                continue;
            }

            $remaining = $this->remainingReturnableQuantity($orderProduct);
            if ($remaining <= 0) {
                continue;
            }

            $lines[] = ['order_product' => $orderProduct, 'remaining' => $remaining];
        }

        return $lines;
    }

    /**
     * Assert a customer may open a return on this order at all: the feature is
     * on, the order belongs to the customer, it is paid and the window is open.
     * The merchant-initiated path does not go through this gate.
     *
     * @throws ReturnNotAllowedException
     */
    public function assertOrderReturnable(Order $order, Customer $customer): void
    {
        if (!$this->isFeatureEnabled()) {
            throw new ReturnNotAllowedException('The product returns feature is disabled.');
        }

        if ((int) $order->getCustomerId() !== (int) $customer->getId()) {
            throw new ReturnNotAllowedException('This order cannot be used for a return.');
        }

        if (!$this->isWithinReturnWindow($order)) {
            throw new ReturnNotAllowedException('The return window for this order has closed.');
        }
    }

    /**
     * Whether the product line may be returned at all: it has to be something that was
     * shipped, so neither a virtual product nor a service the shop invoiced beside the
     * goods. A gift wrapping has nothing to send back, and offering to take it back
     * would promise a refund the return flow has no unit to count.
     */
    public function isProductReturnable(OrderProduct $orderProduct): bool
    {
        return 1 !== (int) $orderProduct->getVirtual() && $orderProduct->isProductLine();
    }

    /**
     * Holds the order product row until the end of the transaction in progress,
     * so that the quantity still returnable on it cannot be read by two
     * requests at once.
     *
     * Reading the remaining quantity and writing the return that consumes it
     * are two steps: between them, a second request asking for the same line
     * reads the same remaining quantity and is allowed the same units, and one
     * ordered unit comes back twice. The window is small and the rate limiter
     * does not close it - twenty requests an hour are twenty chances, and two
     * requests a millisecond apart are within the quota.
     *
     * The lock is only worth taking inside the transaction that writes the
     * return: a `FOR UPDATE` outside one is released as soon as it is taken.
     * The caller opens it through OrderReturnWriteTransaction, which also
     * takes the locks before anything is read, and makes the read and the
     * insert one atomic step.
     *
     * The order row is locked with the line, before it: every path takes the
     * order, then its lines by increasing id, see OrderReturnWriteTransaction.
     */
    public function lockLine(OrderProduct $orderProduct): void
    {
        $this->transaction->lock((int) $orderProduct->getOrderId(), [(int) $orderProduct->getId()]);
    }

    /**
     * lockLine() for several lines of an order at once, in the one order every
     * path takes them in: two requests asking for [A, B] and [B, A] would
     * otherwise each hold their first line and wait for the other's.
     *
     * @param iterable<OrderProduct> $orderProducts
     */
    public function lockLines(Order $order, iterable $orderProducts): void
    {
        $orderProductIds = [];
        foreach ($orderProducts as $orderProduct) {
            $orderProductIds[] = (int) $orderProduct->getId();
        }

        $this->transaction->lock((int) $order->getId(), $orderProductIds);
    }

    /**
     * The quantity of the line that can still be returned: the ordered quantity
     * minus the quantities already requested by open returns, or the ordered
     * quantity itself when the shop does not count the returns cumulatively.
     *
     * This is the figure shown to the customer and the merchant: a plain read,
     * which never waits on a return being written.
     *
     * @param int|null $excludeReturnId a return to leave out of the cumulative count,
     *                                  typically the one being edited
     */
    public function remainingReturnableQuantity(OrderProduct $orderProduct, ?int $excludeReturnId = null): float
    {
        return $this->computeRemainingQuantity($orderProduct, $excludeReturnId, lockingRead: false);
    }

    /**
     * The quantities are read as plain values, never as models: the instance
     * pool hands back a line already loaded in the process as it was then, so
     * a quantity changed since would be counted at its old value. They are
     * summed here rather than by SUM(): the column is a FLOAT, which SQL adds
     * in single precision (1000.3 is 1000.2999877929688 there), while PDO hands
     * out the rounded figure the shop displays.
     *
     * $lockingRead is for a write transaction that may have read before it
     * locked its rows, see OrderReturnWriteTransaction: there only a locking
     * read sees the returns committed since. It goes to the write connection,
     * where the lock and the transaction are.
     */
    private function computeRemainingQuantity(OrderProduct $orderProduct, ?int $excludeReturnId, bool $lockingRead): float
    {
        if (!$this->isQuantityCumulative()) {
            $ordered = round((float) $orderProduct->getQuantity(), self::QUANTITY_PRECISION);

            return $ordered > 0 ? $ordered : 0.0;
        }

        $consumingStatusIds = $this->consumingStatusIds();

        $query = OrderReturnLineQuery::create()
            ->filterByOrderProductId((int) $orderProduct->getId())
            ->useOrderReturnQuery()
                ->filterByStatusId($consumingStatusIds, Criteria::IN)
            ->endUse();

        if (null !== $excludeReturnId) {
            $query->filterByOrderReturnId($excludeReturnId, Criteria::NOT_EQUAL);
        }

        $connection = null;
        if ($lockingRead) {
            $query->lockForShare();
            $connection = Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME);
        }

        $alreadyRequested = 0.0;
        foreach ($query->select(['Quantity'])->find($connection) as $quantity) {
            $alreadyRequested += (float) $quantity;
        }

        $remaining = round((float) $orderProduct->getQuantity() - $alreadyRequested, self::QUANTITY_PRECISION);

        return $remaining > 0 ? $remaining : 0.0;
    }

    /**
     * Assert that the given quantity of a product line may be returned by the customer.
     *
     * @param int|null $excludeReturnId a return to leave out of the cumulative count
     *
     * @throws ReturnNotAllowedException
     */
    public function assertReturnable(
        Order $order,
        Customer $customer,
        OrderProduct $orderProduct,
        float $quantity,
        ?int $excludeReturnId = null,
    ): void {
        if ((int) $order->getCustomerId() !== (int) $customer->getId()) {
            throw new ReturnNotAllowedException('This order cannot be used for a return.');
        }

        if ((int) $orderProduct->getOrderId() !== (int) $order->getId()) {
            throw new ReturnNotAllowedException('This product line does not belong to the order.');
        }

        if (!$this->isProductReturnable($orderProduct)) {
            throw new ReturnNotAllowedException('A virtual product cannot be returned.');
        }

        if ($quantity <= 0) {
            throw new ReturnNotAllowedException('The returned quantity must be positive.');
        }

        $remaining = $this->computeRemainingQuantity($orderProduct, $excludeReturnId, lockingRead: $this->needsLockingReads());

        if ($quantity > $remaining + self::QUANTITY_TOLERANCE) {
            throw new ReturnNotAllowedException('The returned quantity exceeds the returnable quantity of this line.');
        }
    }

    /**
     * Assert the postage of the order is not already carried by another still-open return.
     *
     * The postage belongs to the order, not to a line: two returns on two
     * different lines share no line lock, and both would count no return
     * carrying the postage and both carry it back. The order row is held
     * instead, until the end of the transaction that writes the return. The
     * count is a locking read only in a transaction that may have read before
     * it locked, for the same reason as in computeRemainingQuantity().
     *
     * @throws ReturnNotAllowedException
     */
    public function assertPostageNotAlreadyReturned(Order $order, ?int $excludeReturnId = null): void
    {
        $this->transaction->lock((int) $order->getId(), []);

        $query = OrderReturnQuery::create()
            ->filterByOrderId((int) $order->getId())
            ->filterByIncludePostage(true)
            ->filterByStatusId($this->consumingStatusIds(), Criteria::IN);

        if (null !== $excludeReturnId) {
            $query->filterById($excludeReturnId, Criteria::NOT_EQUAL);
        }

        if ($this->needsLockingReads()) {
            $query->lockForShare();
        }

        if ($query->count(Propel::getWriteConnection(DatabaseConfiguration::THELIA_CONNECTION_NAME)) > 0) {
            throw new ReturnNotAllowedException('The postage of this order is already included in another return.');
        }
    }

    /**
     * Whether the reads that grant units have to lock to see the last committed
     * rows: outside the transaction of an outermost OrderReturnWriteTransaction,
     * the first plain read may have come before the locks. Such a read may
     * deadlock; it never grants a unit twice.
     */
    private function needsLockingReads(): bool
    {
        return !$this->transaction->readsSeeCommits();
    }

    /**
     * The ids of the return statuses that still hold the returned quantity
     * (every status but the refused and expired ones).
     *
     * @return list<int>
     */
    private function consumingStatusIds(): array
    {
        $statusQuery = OrderReturnStatusQuery::create();
        $freed = [OrderReturnStatus::CODE_REFUSED, OrderReturnStatus::CODE_EXPIRED];

        $ids = [];
        foreach ($statusQuery->find() as $status) {
            if (!$status->hasStatusHelper($freed)) {
                $ids[] = (int) $status->getId();
            }
        }

        return $ids;
    }
}
