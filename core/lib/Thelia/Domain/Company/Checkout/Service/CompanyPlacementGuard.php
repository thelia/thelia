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

namespace Thelia\Domain\Company\Checkout\Service;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Psr\Clock\ClockInterface;
use Thelia\Core\Security\User\UserInterface;
use Thelia\Domain\Company\Checkout\DTO\BuyerPlacementHold;
use Thelia\Domain\Company\Checkout\Exception\CompanyOrderRefusedException;
use Thelia\Domain\Company\Service\CompanyMembershipReader;
use Thelia\Model\CompanyCustomerQuery;
use Thelia\Model\Order;

/**
 * What the placement of an order asks of the company of its buyer, inside the transaction the
 * order is written in.
 *
 * The checkout judged the cart a moment before, outside any lock: two carts of the same buyer
 * can both pass it. The row binding the buyer to the company is therefore locked first, by its
 * primary key and before the transaction reads anything it does not lock, so that its view of
 * the database is taken once the other placements of that buyer are over; then the order is
 * judged on its own amount, and on the orders the buyer placed before it, and a refusal rolls
 * the order back.
 *
 * Asked only of a placement made for a buyer at the keyboard: an order written from the back
 * office, or the installment of a subscription, is not bound by the limits of the buyer.
 */
final readonly class CompanyPlacementGuard
{
    public function __construct(
        private CompanyMembershipReader $membershipReader,
        private CompanySpendingJudge $judge,
        private CompanySpendingReader $spending,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Before the transaction: whether the buyer belongs to a company, and the row to lock when
     * they do. A customer with no company costs the membership lookup the order records anyway.
     *
     * @throws PropelException
     */
    public function prepare(UserInterface $customer): ?BuyerPlacementHold
    {
        $customerId = (int) $customer->getId();

        if (null === $this->membershipReader->membershipOf($customerId)) {
            return null;
        }

        /** @var int|string|null $rowId */
        $rowId = CompanyCustomerQuery::create()->filterByCustomerId($customerId)->select('Id')->findOne();

        return null === $rowId ? null : new BuyerPlacementHold($customerId, (int) $rowId);
    }

    /**
     * First statement of the transaction. The membership is then read again, as the buyer is now:
     * the one read before the lock may be older than a role or a limit changed meanwhile.
     */
    public function lock(BuyerPlacementHold $hold, ConnectionInterface $connection): void
    {
        $statement = $connection->prepare('SELECT `id` FROM `company_customer` WHERE `id` = :id FOR UPDATE');
        $statement->bindValue(':id', $hold->companyCustomerId, \PDO::PARAM_INT);
        $statement->execute();

        $this->membershipReader->forget($hold->customerId);
    }

    /**
     * Judges the order that was just written, lines included, against the company of its buyer.
     *
     * @throws CompanyOrderRefusedException
     */
    public function verify(BuyerPlacementHold $hold, Order $order, ConnectionInterface $connection): void
    {
        $membership = $this->membershipReader->membershipOf($hold->customerId);

        if (null === $membership) {
            return;
        }

        $orderId = (int) $order->getId();

        $refusals = $this->judge->refusalsFor(
            $membership,
            $this->spending->totalOfOrder($orderId, $connection),
            fn (): string => $this->spending->spentSince(
                $membership->companyId,
                $hold->customerId,
                SpendingPeriod::from((string) $membership->period)->startAt($this->clock->now()),
                $orderId,
                $connection,
            ),
        );

        if ([] !== $refusals) {
            throw $refusals[0];
        }
    }
}
