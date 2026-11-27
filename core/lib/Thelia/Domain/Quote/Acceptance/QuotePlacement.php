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

namespace Thelia\Domain\Quote\Acceptance;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Quote\Cart\QuoteOrderSlot;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Event\QuoteEvents;
use Thelia\Domain\Quote\Event\QuoteOrderedEvent;
use Thelia\Domain\Quote\Exception\QuoteAlreadyOrderedException;
use Thelia\Domain\Quote\Exception\QuoteCartChangedException;
use Thelia\Domain\Quote\Exception\QuoteCurrencyUnavailableException;
use Thelia\Domain\Quote\Exception\QuoteLinesUnavailableException;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;
use Thelia\Domain\Quote\Exception\UnknownQuoteException;
use Thelia\Domain\Quote\QuoteTransitions;
use Thelia\Model\Cart;
use Thelia\Model\Map\QuoteRevisionTableMap;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Model\QuoteQuery;
use Thelia\Model\QuoteRevision;
use Thelia\Model\QuoteRevisionQuery;

/**
 * Where the order of a quote is written: in the transaction of the order, the quote is locked
 * first and must stand accepted, and it becomes ordered with the order; the shop is told once the
 * transaction is committed.
 *
 * The lock is the row of the quote, read by its primary key before anything else of the
 * transaction reads: two orders of the same quote are one after the other, the second finds it
 * ordered. The unique key of the order is what holds when nothing else does, and is answered
 * like it.
 */
final readonly class QuotePlacement
{
    /**
     * The name of the unique key of the order on the quote, which the database names in its error.
     */
    public const string UNIQUE_KEY = 'uk_order_quote_id';

    public function __construct(
        private QuoteOrderSlot $slot,
        private QuoteTransitions $transitions = new QuoteTransitions(),
        private QuoteSellability $sellability = new QuoteSellability(),
        private QuoteCartLines $cartLines = new QuoteCartLines(),
    ) {
    }

    /**
     * Locks the quote of the cart, if the cart is one, and refuses when it cannot be ordered.
     *
     * @return QuoteBeingOrdered|null null for an ordinary cart
     *
     * @throws QuoteAlreadyOrderedException
     * @throws QuoteTransitionRefusedException
     * @throws QuoteLinesUnavailableException    a line of the accepted revision can no longer be sold
     * @throws QuoteCurrencyUnavailableException
     * @throws QuoteCartChangedException         the cart is not exactly the accepted revision
     * @throws UnknownQuoteException
     */
    public function begin(Cart $cart, ConnectionInterface $connection): ?QuoteBeingOrdered
    {
        $quoteId = $cart->getQuoteId();

        if (null === $quoteId) {
            return null;
        }

        $statement = $connection->prepare('SELECT `state`, `current_revision` FROM `quote` WHERE `id` = :id FOR UPDATE');
        $statement->bindValue(':id', $quoteId, \PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if (!\is_array($row)) {
            throw UnknownQuoteException::withId((int) $quoteId);
        }

        $state = QuoteState::from((string) $row['state']);

        if (QuoteState::Ordered === $state) {
            throw new QuoteAlreadyOrderedException();
        }

        if (QuoteState::Accepted !== $state) {
            throw QuoteTransitionRefusedException::notAllowed($state, QuoteState::Ordered);
        }

        // An order of the quote that was cancelled holds its place on the quote until now.
        $this->slot->releaseFromCancelledOrders($cart);

        $revisionNumber = (int) ($cart->getQuoteRevision() ?? $row['current_revision']);
        $this->assertCartIsTheAcceptedRevision($cart, (int) $quoteId, $revisionNumber, $connection);

        return new QuoteBeingOrdered((int) $quoteId, $revisionNumber);
    }

    /**
     * What is paid is what was accepted: a line the catalog took away (the cart loses it with the
     * product, the quote keeps it) or a quantity that changed is no order of this quote. Judged on
     * the quote locked above, before anything of the order is written.
     */
    private function assertCartIsTheAcceptedRevision(Cart $cart, int $quoteId, int $revisionNumber, ConnectionInterface $connection): void
    {
        QuoteTableMap::clearInstancePool();
        QuoteRevisionTableMap::clearInstancePool();

        $quote = QuoteQuery::create()->findPk($quoteId, $connection) ?? throw UnknownQuoteException::withId($quoteId);
        $revision = QuoteRevisionQuery::create()->filterByQuoteId($quoteId)->filterByNumber($revisionNumber)->findOne($connection);

        if (!$revision instanceof QuoteRevision) {
            throw new QuoteCartChangedException();
        }

        $this->sellability->assert($quote, $this->cartLines->linesOf($revision, $connection), $connection);

        if (!$this->cartLines->holds($cart, $revision, $connection)) {
            throw new QuoteCartChangedException();
        }
    }

    /**
     * What a failed write of the order is: the refusal of a second order for the quote when the
     * database says the unique key of the quote was broken, null for any other failure.
     */
    public function refusalOf(\Throwable $failure): ?QuoteAlreadyOrderedException
    {
        // The database speaks in the exception at the bottom of the chain Propel wraps it in.
        for ($cause = $failure; null !== $cause; $cause = $cause->getPrevious()) {
            if ($cause instanceof \PDOException
                && 1062 === (int) ($cause->errorInfo[1] ?? 0)
                && str_contains($cause->getMessage(), self::UNIQUE_KEY)) {
                return new QuoteAlreadyOrderedException($failure);
            }
        }

        return null;
    }

    /**
     * The quote is ordered with its order, in the same transaction.
     */
    public function markOrdered(QuoteBeingOrdered $quote, ConnectionInterface $connection): void
    {
        $this->transitions->assertCanMove(QuoteState::Accepted, QuoteState::Ordered);

        $statement = $connection->prepare('UPDATE `quote` SET `state` = :ordered, `updated_at` = NOW() WHERE `id` = :id AND `state` = :accepted');
        $statement->bindValue(':ordered', QuoteState::Ordered->value);
        $statement->bindValue(':accepted', QuoteState::Accepted->value);
        $statement->bindValue(':id', $quote->quoteId, \PDO::PARAM_INT);
        $statement->execute();

        if (1 !== $statement->rowCount()) {
            throw new QuoteAlreadyOrderedException();
        }

        QuoteTableMap::clearInstancePool();
    }

    /**
     * Once the order is committed.
     */
    public function announce(QuoteBeingOrdered $quote, EventDispatcherInterface $dispatcher): void
    {
        QuoteTableMap::clearInstancePool();
        $model = QuoteQuery::create()->findPk($quote->quoteId);

        if (null !== $model) {
            $dispatcher->dispatch(new QuoteOrderedEvent($model, $quote->revisionNumber), QuoteEvents::ORDERED);
        }
    }
}
