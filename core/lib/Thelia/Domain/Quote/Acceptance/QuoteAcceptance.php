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
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Thelia\Domain\Company\CompanyPermission;
use Thelia\Domain\Company\Service\CompanyPermissionChecker;
use Thelia\Domain\Quote\Exception\QuoteActionRefusedException;
use Thelia\Domain\Quote\Exception\QuoteCurrencyUnavailableException;
use Thelia\Domain\Quote\Exception\QuoteLinesUnavailableException;
use Thelia\Domain\Quote\Exception\UnknownQuoteException;
use Thelia\Domain\Quote\Service\QuoteAccess;
use Thelia\Model\Cart;
use Thelia\Model\Customer;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteRevision;

/**
 * What accepting a quote needs besides the transaction the facade opens: the lock that serialises
 * the acceptances of one quote across the nodes that share the lock store, who may accept, and
 * the cart the buyer pays with.
 */
final readonly class QuoteAcceptance
{
    public const string LOCK_PREFIX = 'thelia.quote.';

    private const float LOCK_TTL_SECONDS = 30.0;

    public function __construct(
        private LockFactory $lockFactory,
        private QuoteAccess $access,
        private CompanyPermissionChecker $permissionChecker,
        private QuoteSellability $sellability,
        private QuoteCartBuilder $cartBuilder,
    ) {
    }

    /**
     * Waits for the acceptance of the same quote that is under way, if any: the second one then
     * reads what the first one wrote.
     */
    public function hold(Quote $quote): LockInterface
    {
        $lock = $this->lockFactory->createLock(self::LOCK_PREFIX.(int) $quote->getId(), self::LOCK_TTL_SECONDS);
        $lock->acquire(true);

        return $lock;
    }

    /**
     * Whoever answers the quote, and, for a buyer of a company, whoever may place orders for it.
     *
     * @throws UnknownQuoteException       when the buyer may not even read the quote
     * @throws QuoteActionRefusedException
     */
    public function assertMayAccept(Customer $buyer, Quote $quote): void
    {
        if (!$this->access->canRead($buyer, $quote)) {
            throw UnknownQuoteException::withId((int) $quote->getId());
        }

        if ($this->access->isCompanySuspended($buyer)) {
            throw QuoteActionRefusedException::companySuspended();
        }

        if (!$this->access->canAnswer($buyer, $quote) || !$this->permissionChecker->can($buyer, CompanyPermission::ORDER_PLACE)) {
            throw QuoteActionRefusedException::notPermitted();
        }
    }

    /**
     * @throws QuoteCurrencyUnavailableException
     * @throws QuoteLinesUnavailableException
     */
    public function assertSellable(Quote $quote, QuoteRevision $revision, ConnectionInterface $connection): void
    {
        $this->sellability->assert(
            $quote,
            [...QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find($connection)],
            $connection,
        );
    }

    public function cartFor(Quote $quote, QuoteRevision $revision, Customer $buyer, ConnectionInterface $connection): Cart
    {
        return $this->cartBuilder->cartFor($quote, $revision, $buyer, $connection);
    }
}
