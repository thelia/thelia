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
use Thelia\Domain\OrderReturn\Exception\ReturnNotAllowedException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Customer;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderReturnLineQuery;
use Thelia\Model\OrderReturnQuery;
use Thelia\Model\OrderReturnStatus;
use Thelia\Model\OrderReturnStatusQuery;

/**
 * The single source of truth for whether a return may be opened, and for which
 * quantity. The customer front (Flexy), the front API and the back-office all
 * go through it, so the rules of the return specification are enforced once:
 *
 *  - the returns feature is enabled on the shop;
 *  - the order is paid and still inside the retraction window;
 *  - the order belongs to the customer opening the return;
 *  - a virtual product is never returnable;
 *  - the requested quantity may not exceed the ordered quantity, cumulative
 *    open requests included.
 *
 * A refused or expired return frees the quantity it held.
 */
final class ReturnEligibilityChecker
{
    public const ENABLED_CONFIG_KEY = 'order_return_enabled';
    public const WINDOW_CONFIG_KEY = 'order_return_window_days';
    public const DEFAULT_WINDOW_DAYS = 14;

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
     * Whether the retraction window is still open for the order. The window is
     * counted from the order creation date; the shipping date will replace it
     * once #168 stores it.
     */
    public function isWithinReturnWindow(Order $order): bool
    {
        $status = $order->getOrderStatus();
        if (null === $status || !$status->isPaid(false)) {
            return false;
        }

        $createdAt = $order->getCreatedAt();
        if (null === $createdAt) {
            return false;
        }

        return (clone $createdAt)->modify(\sprintf('+%d days', $this->windowDays())) >= new \DateTime();
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
     * Whether the product line may be returned at all (i.e. it is not virtual).
     */
    public function isProductReturnable(OrderProduct $orderProduct): bool
    {
        return 1 !== (int) $orderProduct->getVirtual();
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
     * The caller opens it - OrderReturnFrontCreateProcessor and
     * OrderReturnAdminCreateProcessor both do - which is also what makes the
     * read and the insert one atomic step.
     */
    public function lockLine(OrderProduct $orderProduct): void
    {
        $connection = Propel::getWriteConnection(OrderProductTableMap::DATABASE_NAME);

        $statement = $connection->prepare(
            'SELECT '.OrderProductTableMap::COL_ID
            .' FROM '.OrderProductTableMap::TABLE_NAME
            .' WHERE '.OrderProductTableMap::COL_ID.' = :id FOR UPDATE'
        );
        $statement->execute([':id' => (int) $orderProduct->getId()]);
        $statement->closeCursor();
    }

    /**
     * The quantity of the line that can still be returned: the ordered quantity
     * minus the quantities already requested by open returns.
     *
     * @param int|null $excludeReturnId a return to leave out of the cumulative count,
     *                                  typically the one being edited
     */
    public function remainingReturnableQuantity(OrderProduct $orderProduct, ?int $excludeReturnId = null): float
    {
        $consumingStatusIds = $this->consumingStatusIds();

        $query = OrderReturnLineQuery::create()
            ->filterByOrderProductId((int) $orderProduct->getId())
            ->useOrderReturnQuery()
                ->filterByStatusId($consumingStatusIds, Criteria::IN)
            ->endUse();

        if (null !== $excludeReturnId) {
            $query->filterByOrderReturnId($excludeReturnId, Criteria::NOT_EQUAL);
        }

        $alreadyRequested = 0.0;
        foreach ($query->find() as $line) {
            $alreadyRequested += (float) $line->getQuantity();
        }

        $remaining = (float) $orderProduct->getQuantity() - $alreadyRequested;

        return $remaining > 0 ? $remaining : 0.0;
    }

    /**
     * Assert that the given quantity of a product line may be returned by the customer.
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

        if ($quantity > $this->remainingReturnableQuantity($orderProduct, $excludeReturnId)) {
            throw new ReturnNotAllowedException('The returned quantity exceeds the returnable quantity of this line.');
        }
    }

    /**
     * Assert the postage of the order is not already carried by another still-open return.
     *
     * @throws ReturnNotAllowedException
     */
    public function assertPostageNotAlreadyReturned(Order $order, ?int $excludeReturnId = null): void
    {
        $query = OrderReturnQuery::create()
            ->filterByOrderId((int) $order->getId())
            ->filterByIncludePostage(true)
            ->filterByStatusId($this->consumingStatusIds(), Criteria::IN);

        if (null !== $excludeReturnId) {
            $query->filterById($excludeReturnId, Criteria::NOT_EQUAL);
        }

        if ($query->count() > 0) {
            throw new ReturnNotAllowedException('The postage of this order is already included in another return.');
        }
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
