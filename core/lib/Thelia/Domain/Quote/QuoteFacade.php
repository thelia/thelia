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

namespace Thelia\Domain\Quote;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Quote\Acceptance\QuoteAcceptance;
use Thelia\Domain\Quote\Acceptance\QuoteSellability;
use Thelia\Domain\Quote\DTO\QuoteDraftInput;
use Thelia\Domain\Quote\DTO\QuoteDraftLine;
use Thelia\Domain\Quote\DTO\QuoteDuplication;
use Thelia\Domain\Quote\DTO\QuoteItemRequestInput;
use Thelia\Domain\Quote\DTO\QuoteRequestInput;
use Thelia\Domain\Quote\DTO\QuoteTotals;
use Thelia\Domain\Quote\Enum\QuoteMode;
use Thelia\Domain\Quote\Enum\QuoteRevisionAuthor;
use Thelia\Domain\Quote\Enum\QuoteRevisionState;
use Thelia\Domain\Quote\Enum\QuoteState;
use Thelia\Domain\Quote\Event\QuoteAcceptedEvent;
use Thelia\Domain\Quote\Event\QuoteCounteredEvent;
use Thelia\Domain\Quote\Event\QuoteCreatedEvent;
use Thelia\Domain\Quote\Event\QuoteDeletedEvent;
use Thelia\Domain\Quote\Event\QuoteDraftSavedEvent;
use Thelia\Domain\Quote\Event\QuoteDuplicatedEvent;
use Thelia\Domain\Quote\Event\QuoteEvents;
use Thelia\Domain\Quote\Event\QuoteExpiredEvent;
use Thelia\Domain\Quote\Event\QuoteExtendedEvent;
use Thelia\Domain\Quote\Event\QuoteRejectedEvent;
use Thelia\Domain\Quote\Event\QuoteRemindedEvent;
use Thelia\Domain\Quote\Event\QuoteRequestedEvent;
use Thelia\Domain\Quote\Event\QuoteSentEvent;
use Thelia\Domain\Quote\Exception\CounterOfferRefusedException;
use Thelia\Domain\Quote\Exception\QuoteActionRefusedException;
use Thelia\Domain\Quote\Exception\QuoteCurrencyUnavailableException;
use Thelia\Domain\Quote\Exception\QuoteDraftRefusedException;
use Thelia\Domain\Quote\Exception\QuoteLinesUnavailableException;
use Thelia\Domain\Quote\Exception\QuoteRequestRefusedException;
use Thelia\Domain\Quote\Exception\QuoteTransitionRefusedException;
use Thelia\Domain\Quote\Exception\UnknownQuoteException;
use Thelia\Domain\Quote\Service\QuoteAccess;
use Thelia\Domain\Quote\Service\QuoteCatalogPrices;
use Thelia\Domain\Quote\Service\QuoteLineSnapshot;
use Thelia\Domain\Quote\Service\QuoteLineWriter;
use Thelia\Domain\Quote\Service\QuoteNoteSanitizer;
use Thelia\Domain\Quote\Service\QuoteRefAllocator;
use Thelia\Domain\Quote\Service\QuoteRevisionFreezer;
use Thelia\Domain\Quote\Service\QuoteRevisionGuard;
use Thelia\Domain\Quote\Service\QuoteTotalsCalculator;
use Thelia\Model\Admin;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\Customer;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\QuoteItemTableMap;
use Thelia\Model\Map\QuoteRevisionTableMap;
use Thelia\Model\Map\QuoteTableMap;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\Quote;
use Thelia\Model\QuoteItem;
use Thelia\Model\QuoteItemQuery;
use Thelia\Model\QuoteItemRequest;
use Thelia\Model\QuoteQuery;
use Thelia\Model\QuoteRevision;
use Thelia\Model\QuoteRevisionQuery;
use Thelia\Module\BaseModule;

/**
 * The one way a quote changes, whoever asks: the storefront, the API, the back office or a command.
 *
 * Every operation is one transaction that starts by locking the row of the quote by its primary
 * key, reads the quote again under the lock, and moves it along the map of
 * {@see QuoteTransitions}; the shop is told with an event once the transaction is committed. What
 * is refused is refused before anything is written, with an exception of the
 * Quote\Exception namespace whose reason() does not depend on the language.
 *
 * A revision is the proposal of one side at one moment. The first one is the request of the buyer;
 * each sending of the shop adds one; once sent a revision never changes, with one exception that is
 * meant: {@see extend()} moves the last day of validity of the revision the quote stands at along
 * with the one of the quote, since that is the revision the buyer answers. The quote stands at the
 * last revision sent (`current_revision`), and the shop works on at most one draft after it.
 */
final readonly class QuoteFacade
{
    private const string AMOUNT_PATTERN = '/^\d{1,10}(\.\d{1,6})?$/D';

    private const float MAXIMUM_QUANTITY = 9_999_999.0;

    private const int DEFAULT_VALIDITY_DAYS = 30;

    public function __construct(
        private EventDispatcherInterface $dispatcher,
        private ClockInterface $clock,
        private QuoteTransitions $transitions,
        private QuoteAccess $access,
        private QuoteTotalsCalculator $totalsCalculator,
        private QuoteRefAllocator $refAllocator,
        private QuoteLineWriter $lineWriter,
        private QuoteCatalogPrices $catalogPrices,
        private QuoteNoteSanitizer $sanitizer,
        private QuoteRevisionGuard $revisionGuard,
        private QuoteRevisionFreezer $revisionFreezer,
        private QuoteSellability $sellability,
        private ?QuoteAcceptance $acceptance = null,
    ) {
    }

    // ------------------------------------------------------------------
    // The buyer
    // ------------------------------------------------------------------

    /**
     * Turns the lines of a cart into the first revision of a new quote. The cart is left as it is.
     *
     * The prices are those of the cart, which is the price the buyer was shown: they are written
     * as the request and as the catalog price of each line. The coupon of the cart does not come
     * along: the quote says so ({@see Quote::getCouponDropped()}) and the buyer is told.
     *
     * @throws QuoteRequestRefusedException
     */
    public function requestFromCart(Cart $cart, Customer $buyer, QuoteRequestInput $input): Quote
    {
        if (QuoteMode::Disabled === $this->mode()) {
            throw QuoteRequestRefusedException::quotesAreDisabled();
        }

        if (!$this->access->canRequest($buyer)) {
            throw QuoteRequestRefusedException::notPermitted();
        }

        if ($this->access->isCompanySuspended($buyer)) {
            throw QuoteRequestRefusedException::companySuspended();
        }

        if ((int) $cart->getCustomerId() !== (int) $buyer->getId()) {
            throw QuoteRequestRefusedException::cartNotOwned();
        }

        // The prices of that cart are the ones that were negotiated: asked again they would be
        // written as the catalog prices of a new quote.
        if (null !== $cart->getQuoteId()) {
            throw QuoteRequestRefusedException::cartOfAQuote();
        }

        if (null !== $input->neededBy && $this->isPast($input->neededBy)) {
            throw QuoteRequestRefusedException::neededByPast();
        }

        $invoiceAddressId = $this->addressOf($buyer, $input->invoiceAddressId);
        $deliveryAddressId = $this->addressOf($buyer, $input->deliveryAddressId);

        $quote = $this->inTransaction(function (ConnectionInterface $connection) use ($cart, $buyer, $input, $invoiceAddressId, $deliveryAddressId): Quote {
            $lines = [];
            $invalidLines = [];
            $couponDropped = (float) $cart->getDiscount() > 0.0;
            $cartLines = CartItemQuery::create()->filterByCartId($cart->getId())->orderById()->find($connection);
            $snapshots = $this->lineWriter->snapshots(
                array_map(static fn ($line): int => (int) $line->getProductSaleElementsId(), [...$cartLines]),
                $this->localeOf($buyer),
                $connection,
            );

            foreach ($cartLines as $cartLine) {
                if (null !== $cartLine->getOfferedByCouponId()) {
                    $couponDropped = true;

                    continue;
                }

                $snapshot = $snapshots[(int) $cartLine->getProductSaleElementsId()] ?? null;

                // A bundle holds a composition and a subscription a plan, which a line of a quote
                // has no room for: quoting them as simple lines would sell something else.
                if (null === $snapshot || null !== $cartLine->getSubscriptionPlanId() || (null !== $cartLine->getBundleComposition() && '' !== $cartLine->getBundleComposition())) {
                    $invalidLines[] = (int) $cartLine->getId();

                    continue;
                }

                $price = number_format(round($cartLine->getRealPrice(), 6), 6, '.', '');
                $lines[] = [
                    'position' => \count($lines) + 1,
                    'snapshot' => $snapshot,
                    'quantity' => (float) $cartLine->getQuantity(),
                    'catalogPrice' => $price,
                    'price' => $price,
                ];
            }

            if ([] !== $invalidLines) {
                throw QuoteRequestRefusedException::invalidLines($invalidLines);
            }

            if ([] === $lines) {
                throw QuoteRequestRefusedException::emptyCart();
            }

            $now = $this->clock->now();
            $quote = (new Quote())
                ->setRef('~'.bin2hex(random_bytes(8)))
                ->setCustomerId((int) $buyer->getId())
                ->setCompanyId($this->access->companyIdFor($buyer))
                ->setState(QuoteState::Requested->value)
                ->setCurrentRevision(1)
                ->setCurrencyId((int) ($cart->getCurrencyId() ?? Currency::getDefaultCurrency()->getId()))
                ->setBuyerComment($this->sanitizer->buyerComment($input->comment))
                ->setNeededBy($input->neededBy)
                ->setCouponDropped($couponDropped ? 1 : 0)
                ->setInvoiceAddressId($invoiceAddressId)
                ->setDeliveryAddressId($deliveryAddressId);
            $quote->save($connection);

            $revision = (new QuoteRevision())
                ->setQuoteId($quote->getId())
                ->setNumber(1)
                ->setAuthor(QuoteRevisionAuthor::Customer->value)
                ->setState(QuoteRevisionState::Sent->value)
                ->setDiscount('0.000000')
                ->setPostage('0.000000')
                ->setSentAt($now);
            $revision->save($connection);

            $this->lineWriter->insert((int) $revision->getId(), $lines, $connection);
            $this->revisionFreezer->freeze(
                $quote,
                $revision,
                QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find($connection),
                $connection,
            );

            // The number comes last: its row stays locked until the commit.
            $quote->setRef($this->refAllocator->allocate($connection))->save($connection);

            return $quote;
        });

        $this->dispatcher->dispatch(new QuoteRequestedEvent($quote, 1), QuoteEvents::REQUESTED);

        return $quote;
    }

    /**
     * Answers the current revision with what the buyer would rather have on some of its lines.
     * The revision does not change and no revision is added: the quote waits for the shop.
     *
     * @param list<QuoteItemRequestInput> $requests
     *
     * @throws UnknownQuoteException
     * @throws QuoteActionRefusedException
     * @throws QuoteTransitionRefusedException
     * @throws CounterOfferRefusedException
     */
    public function counterOffer(Quote $quote, Customer $buyer, int $revisionNumber, array $requests): Quote
    {
        $this->inTransaction(function (ConnectionInterface $connection) use ($quote, $buyer, $revisionNumber, $requests): void {
            $this->lock($quote, $connection);
            $this->assertCanAnswer($quote, $buyer);

            if ($this->access->isCompanySuspended($buyer)) {
                throw QuoteActionRefusedException::companySuspended();
            }

            $this->transitions->assertCanMove($this->effectiveState($quote, $this->today()), QuoteState::Countered);
            $revision = $this->currentRevisionOf($quote, $revisionNumber, $connection);

            $lines = [];

            foreach (QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->find($connection) as $line) {
                $lines[(int) $line->getId()] = $line;
            }

            if ([] === $requests) {
                throw CounterOfferRefusedException::nothingAsked();
            }

            $seen = [];
            $discussed = [];

            foreach ($requests as $request) {
                if (!isset($lines[$request->quoteItemId])) {
                    throw CounterOfferRefusedException::unknownLine();
                }

                if (isset($seen[$request->quoteItemId])) {
                    throw CounterOfferRefusedException::duplicateLine();
                }

                $seen[$request->quoteItemId] = true;

                if (null === $request->quantity && null === $request->price) {
                    throw CounterOfferRefusedException::nothingAsked();
                }

                if (null !== $request->quantity && !$this->isQuantity($request->quantity)) {
                    throw CounterOfferRefusedException::invalidQuantity();
                }

                if (null !== $request->price && (!$this->isAmount($request->price) || (float) $request->price <= 0.0)) {
                    throw CounterOfferRefusedException::invalidPrice();
                }

                // Asking for what the offer already says leaves the line as it is: it is not discussed.
                if ($this->asksForMoreThanTheOffer($request, $lines[$request->quoteItemId])) {
                    $discussed[] = $request;
                }
            }

            if ([] === $discussed) {
                throw CounterOfferRefusedException::nothingAsked();
            }

            foreach ($discussed as $request) {
                (new QuoteItemRequest())
                    ->setQuoteItemId($request->quoteItemId)
                    ->setQuantity($request->quantity)
                    ->setPrice(null === $request->price ? null : $this->decimal($request->price))
                    ->save($connection);
            }

            $quote->setState(QuoteState::Countered->value)->save($connection);
        });

        $this->dispatcher->dispatch(new QuoteCounteredEvent($quote, $revisionNumber), QuoteEvents::COUNTERED);

        return $quote;
    }

    /**
     * @throws UnknownQuoteException
     * @throws QuoteActionRefusedException
     * @throws QuoteTransitionRefusedException
     */
    public function reject(Quote $quote, Customer $buyer, int $revisionNumber): Quote
    {
        $this->inTransaction(function (ConnectionInterface $connection) use ($quote, $buyer, $revisionNumber): void {
            $this->lock($quote, $connection);
            $this->assertCanAnswer($quote, $buyer);
            $this->transitions->assertCanMove($this->effectiveState($quote, $this->today()), QuoteState::Rejected);
            $this->currentRevisionOf($quote, $revisionNumber, $connection);

            $quote->setState(QuoteState::Rejected->value)->save($connection);
        });

        $this->dispatcher->dispatch(new QuoteRejectedEvent($quote, $revisionNumber), QuoteEvents::REJECTED);

        return $quote;
    }

    /**
     * Accepts the revision the quote stands at, and gives the cart the buyer pays it with. The cart
     * is not put in the session: that is for whoever holds it (see QuoteCartSwitcher).
     *
     * The prices come from the quote and nowhere else: the buyer names a revision, never an amount.
     * A quote that was accepted and not ordered yet gives its cart again (the buyer who left the
     * checkout comes back), or a new one when that cart is gone; nothing is announced twice.
     *
     * @throws UnknownQuoteException
     * @throws QuoteActionRefusedException
     * @throws QuoteTransitionRefusedException
     * @throws QuoteLinesUnavailableException
     * @throws QuoteCurrencyUnavailableException
     */
    public function accept(Quote $quote, Customer $buyer, int $revisionNumber): Cart
    {
        $acceptance = $this->acceptance ?? throw new \LogicException('This facade was built without what accepts a quote.');
        $held = $acceptance->hold($quote);

        try {
            [$cart, $becameAccepted] = $this->inTransaction(function (ConnectionInterface $connection) use ($acceptance, $quote, $buyer, $revisionNumber): array {
                $this->lock($quote, $connection);
                $acceptance->assertMayAccept($buyer, $quote);

                $state = $this->effectiveState($quote, $this->today());

                // An ordered quote goes back to accepted when its order is cancelled, never by an answer
                // of the buyer: that would give a second order for the same quote.
                if (QuoteState::Ordered === $state) {
                    throw QuoteTransitionRefusedException::notAllowed($state, QuoteState::Accepted);
                }

                if (QuoteState::Accepted !== $state) {
                    $this->transitions->assertCanMove($state, QuoteState::Accepted);
                }

                $revision = $this->currentRevisionOf($quote, $revisionNumber, $connection);
                $acceptance->assertSellable($quote, $revision, $connection);
                $cart = $acceptance->cartFor($quote, $revision, $buyer, $connection);

                if (QuoteState::Accepted !== $state) {
                    $quote->setState(QuoteState::Accepted->value)->save($connection);
                }

                return [$cart, QuoteState::Accepted !== $state];
            });
        } finally {
            $held->release();
        }

        if ($becameAccepted) {
            $this->dispatcher->dispatch(new QuoteAcceptedEvent($quote, $revisionNumber), QuoteEvents::ACCEPTED);
        }

        return $cart;
    }

    // ------------------------------------------------------------------
    // The shop
    // ------------------------------------------------------------------

    /**
     * Starts a quote the shop writes for an account, with no request behind it: a draft whose first
     * revision is the shop's own, and which has no line yet.
     *
     * @throws QuoteDraftRefusedException
     */
    public function createForCustomer(Customer $customer, ?Admin $admin): Quote
    {
        if ($customer->isNew() || $customer->isGuest()) {
            throw QuoteDraftRefusedException::customerCannotHaveQuotes();
        }

        $quote = $this->inTransaction(function (ConnectionInterface $connection) use ($customer, $admin): Quote {
            $address = $customer->getDefaultAddress();
            $quote = $this->startQuote(
                $customer,
                $this->access->companyIdFor($customer),
                (int) Currency::getDefaultCurrency()->getId(),
                $address?->getId(),
                $address?->getId(),
                $admin,
                $connection,
            );

            $quote->setRef($this->refAllocator->allocate($connection))->save($connection);

            return $quote;
        });

        $this->dispatcher->dispatch(new QuoteCreatedEvent($quote, 1), QuoteEvents::CREATED);

        return $quote;
    }

    /**
     * Starts a quote for the account the source was written for, in the same currency, for the
     * company the account belongs to today (none when it has left the one of the source, and the
     * former colleagues then do not read the copy), and writes the lines of the revision the source stands at into its draft: the
     * prices, the discount, the postage, the carrier and the note are carried over as they are, since
     * nothing is converted. A line whose product can no longer be ordered is left out, and the
     * result says which.
     *
     * Everything happens in one transaction: when the draft cannot be written, no quote is left behind
     * and no number is used.
     *
     * @throws UnknownQuoteException
     * @throws QuoteDraftRefusedException
     */
    public function duplicate(Quote $source, ?Admin $admin): QuoteDuplication
    {
        $duplication = $this->inTransaction(function (ConnectionInterface $connection) use ($source, $admin): QuoteDuplication {
            $this->lock($source, $connection);

            $customer = CustomerQuery::create()->findPk($source->getCustomerId(), $connection);

            if (!$customer instanceof Customer || $customer->isGuest()) {
                throw QuoteDraftRefusedException::customerCannotHaveQuotes();
            }

            $revision = QuoteRevisionQuery::create()
                ->filterByQuoteId($source->getId())
                ->filterByNumber($source->getCurrentRevision())
                ->findOne($connection) ?? throw UnknownQuoteException::withId((int) $source->getId());

            $lines = [...QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find($connection)];
            $partition = $this->sellability->partition($lines, $connection);

            $copy = $this->startQuote(
                $customer,
                $this->access->companyIdFor($customer),
                (int) $source->getCurrencyId(),
                $this->addressKeptOrDefault($customer, $source->getInvoiceAddressId()),
                $this->addressKeptOrDefault($customer, $source->getDeliveryAddressId()),
                $admin,
                $connection,
            );

            $input = new QuoteDraftInput(
                array_map(
                    static fn (QuoteItem $line): QuoteDraftLine => new QuoteDraftLine(null, (int) $line->getProductSaleElementsId(), (float) $line->getQuantity(), (string) $line->getPrice()),
                    $partition['sellable'],
                ),
                (string) ($revision->getDiscount() ?? '0'),
                (string) ($revision->getPostage() ?? '0'),
                $revision->getDeliveryModuleId() ?? $source->getDeliveryModuleId(),
                $revision->getMerchantNote(),
            );
            $this->assertDraftShape($input);
            $this->writeDraft($copy, $admin, $input, $connection);

            // The number comes last: its row stays locked until the commit.
            $copy->setRef($this->refAllocator->allocate($connection))->save($connection);

            return new QuoteDuplication($copy, $partition['unavailable']);
        });

        $this->dispatcher->dispatch(new QuoteDuplicatedEvent($duplication->quote, $source, 1), QuoteEvents::DUPLICATED);

        return $duplication;
    }

    /**
     * Writes the revision the shop is working on, all of it: the lines, the discount, the postage,
     * the carrier, the note and the last day of validity replace what the draft held. When the
     * revision the quote stands at was sent, the draft is the next revision, created now; a
     * revision that was sent is never written again.
     *
     * A line the shop adds is set against the price the buyer would pay for it today.
     *
     * @throws UnknownQuoteException
     * @throws QuoteTransitionRefusedException
     * @throws QuoteDraftRefusedException
     */
    public function saveDraft(Quote $quote, ?Admin $admin, QuoteDraftInput $draft): QuoteRevision
    {
        $this->assertDraftShape($draft);

        $revision = $this->inTransaction(function (ConnectionInterface $connection) use ($quote, $admin, $draft): QuoteRevision {
            $this->lock($quote, $connection);

            return $this->writeDraft($quote, $admin, $draft, $connection);
        });

        $this->dispatcher->dispatch(new QuoteDraftSavedEvent($quote, (int) $revision->getNumber()), QuoteEvents::DRAFT_SAVED);

        return $revision;
    }

    /**
     * Writes the draft and sends it in the same transaction: the draft the shop sees is the one that
     * is sent, whatever else saves the draft meanwhile, and a draft that cannot be sent is not saved.
     *
     * @throws UnknownQuoteException
     * @throws QuoteTransitionRefusedException
     * @throws QuoteDraftRefusedException
     */
    public function saveAndSend(Quote $quote, ?Admin $admin, QuoteDraftInput $draft): Quote
    {
        $this->assertDraftShape($draft);

        $revisionNumber = $this->inTransaction(function (ConnectionInterface $connection) use ($quote, $admin, $draft): int {
            $this->lock($quote, $connection);
            $this->writeDraft($quote, $admin, $draft, $connection);

            // The lines are read again from the rows that were just written.
            QuoteRevisionTableMap::clearInstancePool();
            QuoteItemTableMap::clearInstancePool();

            return $this->sendLocked($quote, $admin, $connection);
        });

        $this->dispatcher->dispatch(new QuoteSentEvent($quote, $revisionNumber), QuoteEvents::SENT);

        return $quote;
    }

    /**
     * Sends the draft to the buyer: it becomes final, its totals are frozen with the tax rules of
     * the day, the quote becomes quoted at that revision and valid until the day the shop chose
     * with the draft, or else for the number of days of the shop settings.
     *
     * @throws UnknownQuoteException
     * @throws QuoteTransitionRefusedException
     * @throws QuoteDraftRefusedException
     */
    public function send(Quote $quote, ?Admin $admin): Quote
    {
        $revisionNumber = $this->inTransaction(function (ConnectionInterface $connection) use ($quote, $admin): int {
            $this->lock($quote, $connection);

            return $this->sendLocked($quote, $admin, $connection);
        });

        $this->dispatcher->dispatch(new QuoteSentEvent($quote, $revisionNumber), QuoteEvents::SENT);

        return $quote;
    }

    /**
     * Deletes a quote the shop never sent, with its revisions, their lines, what the buyer countered
     * on them and the copies of the addresses, in one transaction: the foreign keys of those tables
     * go with the quote. A quote whose revision the shop sent is the record of what was offered, and
     * a quote an order came from is named by that order: both stay.
     *
     * @throws UnknownQuoteException
     * @throws QuoteTransitionRefusedException
     */
    public function delete(Quote $quote): void
    {
        $revisionNumber = $this->inTransaction(function (ConnectionInterface $connection) use ($quote): int {
            $this->lock($quote, $connection);
            $state = QuoteState::from((string) $quote->getState());

            if (QuoteRevisionQuery::create()
                ->filterByQuoteId($quote->getId())
                ->filterByAuthor(QuoteRevisionAuthor::Admin->value)
                ->filterByState(QuoteRevisionState::Sent->value)
                ->count($connection) > 0) {
                throw QuoteTransitionRefusedException::cannotBeDeletedOnceSent($state);
            }

            if (OrderQuery::create()->filterByQuoteId($quote->getId())->count($connection) > 0) {
                throw QuoteTransitionRefusedException::cannotBeDeletedOnceOrdered($state);
            }

            $revisionNumber = (int) $quote->getCurrentRevision();
            $quote->delete($connection);

            QuoteTableMap::clearInstancePool();
            QuoteRevisionTableMap::clearInstancePool();
            QuoteItemTableMap::clearInstancePool();

            return $revisionNumber;
        });

        $this->dispatcher->dispatch(new QuoteDeletedEvent($quote, $revisionNumber), QuoteEvents::DELETED);
    }

    /**
     * Pushes the last day of validity back, and brings an expired quote back to quoted.
     *
     * @throws UnknownQuoteException
     * @throws QuoteTransitionRefusedException when the quote is neither waiting for the buyer nor expired
     * @throws QuoteDraftRefusedException      when the day is already past, or no later than the last day of a quote that is still valid
     */
    public function extend(Quote $quote, \DateTimeImmutable $validUntil): Quote
    {
        $this->inTransaction(function (ConnectionInterface $connection) use ($quote, $validUntil): void {
            $this->lock($quote, $connection);
            $state = $this->effectiveState($quote, $this->today());

            if (QuoteState::Quoted !== $state) {
                $this->transitions->assertCanMove($state, QuoteState::Quoted);

                if (QuoteState::Expired !== $state) {
                    throw QuoteTransitionRefusedException::notAllowed($state, QuoteState::Quoted);
                }
            }

            if ($this->isPast($validUntil)) {
                throw QuoteDraftRefusedException::validityInThePast();
            }

            $day = $this->dayOf($validUntil);
            $currentDay = $this->dayOf($quote->getValidUntil());

            // A quote still valid is pushed back, never shortened: that is not an extension.
            if (QuoteState::Quoted === $state && null !== $currentDay && null !== $day && $day <= $currentDay) {
                throw QuoteDraftRefusedException::validityNotExtended();
            }

            $quote->setState(QuoteState::Quoted->value)->setValidUntil($day)->save($connection);

            // The revision the buyer answers is valid as long as the quote is.
            QuoteRevisionQuery::create()
                ->filterByQuoteId($quote->getId())
                ->filterByNumber($quote->getCurrentRevision())
                ->filterByState(QuoteRevisionState::Sent->value)
                ->update(['ValidUntil' => $day], $connection);
            QuoteRevisionTableMap::clearInstancePool();
        });

        $this->dispatcher->dispatch(new QuoteExtendedEvent($quote, (int) $quote->getCurrentRevision()), QuoteEvents::EXTENDED);

        return $quote;
    }

    /**
     * Records that the buyer is reminded of a quote that waits for them; the e-mail goes out on
     * the event, with the revision the quote stands at.
     *
     * @throws UnknownQuoteException
     * @throws QuoteTransitionRefusedException
     */
    public function remind(Quote $quote): Quote
    {
        $this->inTransaction(function (ConnectionInterface $connection) use ($quote): void {
            $this->lock($quote, $connection);
            $state = $this->effectiveState($quote, $this->today());

            if (QuoteState::Quoted !== $state) {
                throw QuoteTransitionRefusedException::notAllowed($state, QuoteState::Quoted);
            }

            $quote->setRemindedAt($this->clock->now())->save($connection);
        });

        $this->dispatcher->dispatch(new QuoteRemindedEvent($quote, (int) $quote->getCurrentRevision()), QuoteEvents::REMINDED);

        return $quote;
    }

    /**
     * Writes the expiry the reading already shows: every quote that waits for the buyer and whose
     * last day of validity is before the day. The quotes are locked and written in one transaction,
     * so that a second run changes nothing and a buyer who accepts at the same moment is either
     * first or told it is too late; the shop is told of each one once the transaction is committed.
     *
     * @return int the number of quotes that became expired
     */
    public function expireOverdue(\DateTimeImmutable $today): int
    {
        $this->transitions->assertCanMove(QuoteState::Quoted, QuoteState::Expired);

        /** @var list<int> $expiredIds */
        $expiredIds = $this->inTransaction(static function (ConnectionInterface $connection) use ($today): array {
            $select = $connection->prepare(
                'SELECT `id` FROM `quote` WHERE `state` = :quoted AND `valid_until` IS NOT NULL AND `valid_until` < :today ORDER BY `id` FOR UPDATE'
            );
            $select->bindValue(':quoted', QuoteState::Quoted->value);
            $select->bindValue(':today', $today->format('Y-m-d'));
            $select->execute();
            $ids = array_map('intval', $select->fetchAll(\PDO::FETCH_COLUMN));

            if ([] === $ids) {
                return [];
            }

            $update = $connection->prepare(
                'UPDATE `quote` SET `state` = ?, `updated_at` = NOW() WHERE `state` = ? AND `id` IN ('.implode(',', array_fill(0, \count($ids), '?')).')'
            );
            $update->execute([QuoteState::Expired->value, QuoteState::Quoted->value, ...$ids]);

            return $ids;
        });

        QuoteTableMap::clearInstancePool();

        foreach ($expiredIds as $id) {
            $quote = QuoteQuery::create()->findPk($id);

            if ($quote instanceof Quote) {
                $this->dispatcher->dispatch(new QuoteExpiredEvent($quote, (int) $quote->getCurrentRevision()), QuoteEvents::EXPIRED);
            }
        }

        return \count($expiredIds);
    }

    // ------------------------------------------------------------------
    // Reading
    // ------------------------------------------------------------------

    /**
     * The state of the quote on the day, which is expired for a quote that waits for the buyer
     * past its last valid day, whatever is written.
     */
    public function effectiveState(Quote $quote, \DateTimeImmutable $today): QuoteState
    {
        return $this->transitions->effectiveState(
            QuoteState::from((string) $quote->getState()),
            $this->dayOf($quote->getValidUntil()),
            $today,
        );
    }

    /**
     * What a revision comes to. A revision that was sent says it as it was frozen when it was
     * sent, whatever the tax rules and the address book say now; only a draft is worked out, with
     * the rules of the day (see {@see QuoteTotalsCalculator}).
     */
    public function totals(QuoteRevision $revision): QuoteTotals
    {
        if (QuoteRevisionState::Sent->value === $revision->getState() && $this->isFrozen($revision)) {
            return new QuoteTotals(
                $this->decimal((string) $revision->getItemsUntaxed()),
                $this->decimal((string) $revision->getItemsTax()),
                $this->decimal((string) $revision->getPostage()),
                $this->decimal((string) $revision->getPostageTax()),
                $this->decimal((string) $revision->getDiscount()),
                $this->decimal((string) $revision->getTotalTaxed()),
                $this->decimal((string) $revision->getTotalUntaxed()),
            );
        }

        /** @var Quote|null $quote Propel finds none for an orphan id. */
        $quote = $revision->getQuote();
        $quote ??= throw UnknownQuoteException::withId((int) $revision->getQuoteId());

        return $this->totalsCalculator->calculate(
            $quote,
            $revision,
            QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find(),
        );
    }

    // ------------------------------------------------------------------
    // Inside
    // ------------------------------------------------------------------

    /**
     * Sends the draft of a quote that is locked, inside the transaction of the caller.
     *
     * @return int the number of the revision that was sent
     */
    private function sendLocked(Quote $quote, ?Admin $admin, ConnectionInterface $connection): int
    {
        $today = $this->today();
        $this->transitions->assertCanMove($this->effectiveState($quote, $today), QuoteState::Quoted);

        $revision = $this->draftOf($quote, $connection) ?? throw QuoteDraftRefusedException::nothingToSend();
        $this->revisionGuard->assertDraft($revision);
        $lines = [...QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId())->orderByPosition()->find($connection)];

        if ([] === $lines) {
            throw QuoteDraftRefusedException::noLines();
        }

        $validUntil = $this->dayOf($revision->getValidUntil());

        if (null !== $validUntil && $this->isPast($validUntil)) {
            throw QuoteDraftRefusedException::validityInThePast();
        }

        $locale = $this->localeOf($quote->getCustomer($connection));
        $snapshots = $this->lineWriter->snapshots(
            array_map(static fn (QuoteItem $line): int => (int) $line->getProductSaleElementsId(), $lines),
            $locale,
            $connection,
        );

        // The tax rule of the day is the one the order will apply: the lines are brought to it.
        foreach ($lines as $line) {
            $snapshot = $snapshots[(int) $line->getProductSaleElementsId()] ?? throw QuoteDraftRefusedException::lineUnavailable();

            if ($snapshot->taxRuleId !== $line->getTaxRuleId() || $snapshot->taxRuleTitle !== $line->getTaxRuleTitle()) {
                $line->setTaxRuleId($snapshot->taxRuleId)->setTaxRuleTitle($snapshot->taxRuleTitle)->save($connection);
            }
        }

        $quote->setLocale($locale);
        $totals = $this->totalsCalculator->calculate($quote, $revision, $lines);

        if (round((float) $revision->getDiscount(), 2) > round((float) $totals->itemsUntaxed + (float) $totals->itemsTax, 2)) {
            throw QuoteDraftRefusedException::discountAboveTotal();
        }

        $now = $this->clock->now();
        $validUntil ??= $today->modify('+'.$this->validityDays().' days');
        $revision
            ->setState(QuoteRevisionState::Sent->value)
            ->setSentAt($now)
            ->setValidUntil($validUntil)
            ->setAdminId($revision->getAdminId() ?? $admin?->getId());
        $this->revisionFreezer->freeze($quote, $revision, $lines, $connection, $totals);

        // What the revision proposes becomes what the quote says.
        $quote
            ->setState(QuoteState::Quoted->value)
            ->setCurrentRevision((int) $revision->getNumber())
            ->setSentAt($now)
            ->setRemindedAt(null)
            ->setValidUntil($validUntil)
            ->setDeliveryModuleId($revision->getDeliveryModuleId())
            ->save($connection);

        return (int) $revision->getNumber();
    }

    /**
     * Writes the draft of a quote that is locked, inside the transaction of the caller.
     */
    private function writeDraft(Quote $quote, ?Admin $admin, QuoteDraftInput $draft, ConnectionInterface $connection): QuoteRevision
    {
        $this->transitions->assertCanBeDrafted($this->effectiveState($quote, $this->today()));

        $revision = $this->draftOf($quote, $connection);

        if (!$revision instanceof QuoteRevision) {
            $number = 1 + (int) QuoteRevisionQuery::create()->filterByQuoteId($quote->getId())->orderByNumber(Criteria::DESC)->findOne($connection)?->getNumber();
            $revision = (new QuoteRevision())
                ->setQuoteId($quote->getId())
                ->setNumber($number)
                ->setAuthor(QuoteRevisionAuthor::Admin->value)
                ->setState(QuoteRevisionState::Draft->value);
        }

        $this->revisionGuard->assertDraft($revision);

        if (null !== $draft->validUntil && $this->isPast($draft->validUntil)) {
            throw QuoteDraftRefusedException::validityInThePast();
        }

        $deliveryModuleId = $this->deliveryModuleIdOf($draft->deliveryModuleId);
        $note = $this->sanitizer->merchantNote($draft->merchantNote);

        $this->writeLines($quote, $revision, $draft, $connection);

        // The validity and the carrier stay on the draft, and the quote is not written: it goes on
        // saying what the buyer was last sent until the draft is sent in its turn.
        $revision
            ->setAdminId($admin?->getId() ?? $revision->getAdminId())
            ->setMerchantNote($note)
            ->setDiscount($this->decimal($draft->discount))
            ->setPostage($this->decimal($draft->postage))
            ->setDeliveryModuleId($deliveryModuleId)
            ->setValidUntil(null === $draft->validUntil ? null : $this->dayOf($draft->validUntil))
            ->save($connection);

        return $revision;
    }

    /**
     * The quote a shop starts, with the first revision that is the shop's own and has no line yet.
     * The number is allocated by the caller, last.
     */
    private function startQuote(Customer $customer, ?int $companyId, int $currencyId, ?int $invoiceAddressId, ?int $deliveryAddressId, ?Admin $admin, ConnectionInterface $connection): Quote
    {
        $quote = (new Quote())
            ->setRef('~'.bin2hex(random_bytes(8)))
            ->setCustomerId((int) $customer->getId())
            ->setCompanyId($companyId)
            ->setState(QuoteState::Draft->value)
            ->setCurrentRevision(1)
            ->setCurrencyId($currencyId)
            ->setInvoiceAddressId($invoiceAddressId)
            ->setDeliveryAddressId($deliveryAddressId);
        $quote->save($connection);

        (new QuoteRevision())
            ->setQuoteId($quote->getId())
            ->setNumber(1)
            ->setAuthor(QuoteRevisionAuthor::Admin->value)
            ->setAdminId($admin?->getId())
            ->setState(QuoteRevisionState::Draft->value)
            ->setDiscount('0.000000')
            ->setPostage('0.000000')
            ->save($connection);

        return $quote;
    }

    private function mode(): QuoteMode
    {
        return QuoteMode::fromConfig((string) ConfigQuery::read('quote_mode', QuoteMode::Disabled->value));
    }

    private function validityDays(): int
    {
        $days = (int) ConfigQuery::read('quote_validity_days', (string) self::DEFAULT_VALIDITY_DAYS);

        return $days > 0 ? $days : self::DEFAULT_VALIDITY_DAYS;
    }

    private function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTime(0, 0);
    }

    /**
     * Whether the calendar day is behind today: the day as it was written, whatever the time zone
     * of the clock.
     */
    private function isPast(\DateTimeInterface $day): bool
    {
        return $day->format('Y-m-d') < $this->today()->format('Y-m-d');
    }

    private function dayOf(string|\DateTimeInterface|null $date): ?\DateTimeImmutable
    {
        if (null === $date || '' === $date) {
            return null;
        }

        $date = $date instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($date) : new \DateTimeImmutable($date);

        return $date->setTime(0, 0);
    }

    private function localeOf(?Customer $customer): string
    {
        $lang = null === $customer?->getLangId() ? null : LangQuery::create()->findPk($customer->getLangId());

        return (string) ($lang?->getLocale() ?? Lang::getDefaultLanguage()->getLocale());
    }

    /**
     * The address of the buyer a quote is written with: the one asked for when the buyer may use
     * it, else their default address (none when they have none).
     */
    private function addressOf(Customer $buyer, ?int $addressId): ?int
    {
        if (null === $addressId) {
            return $this->access->defaultAddressIdOf($buyer);
        }

        if (!$this->access->canUseAddressId($buyer, $addressId)) {
            throw QuoteRequestRefusedException::addressRefused();
        }

        return $addressId;
    }

    /**
     * The address of an earlier quote, when the account may still put it on one: an address that was
     * shared with a company the account has left is not carried over, the default address stands in.
     */
    private function addressKeptOrDefault(Customer $customer, ?int $addressId): ?int
    {
        if (null !== $addressId && $this->access->canUseAddressId($customer, $addressId)) {
            return $addressId;
        }

        return $this->access->defaultAddressIdOf($customer);
    }

    private function deliveryModuleIdOf(?int $moduleId): ?int
    {
        if (null === $moduleId) {
            return null;
        }

        $module = ModuleQuery::create()->findPk($moduleId);

        if (!$module instanceof Module || BaseModule::DELIVERY_MODULE_TYPE !== (int) $module->getType()) {
            throw QuoteDraftRefusedException::unknownDeliveryModule();
        }

        return $moduleId;
    }

    private function assertCanAnswer(Quote $quote, Customer $buyer): void
    {
        if (!$this->access->canRead($buyer, $quote)) {
            throw UnknownQuoteException::withId((int) $quote->getId());
        }

        if (!$this->access->canAnswer($buyer, $quote)) {
            throw QuoteActionRefusedException::notPermitted();
        }
    }

    /**
     * The revision the quote stands at, which is the only one a buyer answers.
     */
    private function currentRevisionOf(Quote $quote, int $revisionNumber, ConnectionInterface $connection): QuoteRevision
    {
        $revision = QuoteRevisionQuery::create()
            ->filterByQuoteId($quote->getId())
            ->filterByNumber($revisionNumber)
            ->filterByState(QuoteRevisionState::Sent->value)
            ->findOne($connection);

        if (!$revision instanceof QuoteRevision || $revisionNumber !== (int) $quote->getCurrentRevision()) {
            throw QuoteActionRefusedException::revisionIsNotTheCurrentOne();
        }

        return $revision;
    }

    private function draftOf(Quote $quote, ConnectionInterface $connection): ?QuoteRevision
    {
        return QuoteRevisionQuery::create()
            ->filterByQuoteId($quote->getId())
            ->filterByState(QuoteRevisionState::Draft->value)
            ->findOne($connection);
    }

    /**
     * Whether the amounts of a revision were written on it when it was sent.
     */
    private function isFrozen(QuoteRevision $revision): bool
    {
        return null !== $revision->getItemsUntaxed()
            && null !== $revision->getItemsTax()
            && null !== $revision->getPostageTax()
            && null !== $revision->getTotalTaxed()
            && null !== $revision->getTotalUntaxed();
    }

    /**
     * Writes the lines of a draft: a line of the draft that is kept keeps its id, a line of an
     * earlier revision or a new one is a new row, a line left out is deleted. The revision is
     * saved first when it is new, so that its lines have a parent.
     */
    private function writeLines(Quote $quote, QuoteRevision $revision, QuoteDraftInput $draft, ConnectionInterface $connection): void
    {
        $currency = CurrencyQuery::create()->findPk($quote->getCurrencyId(), $connection) ?? throw UnknownQuoteException::withId((int) $quote->getId());
        $customer = $quote->getCustomer($connection);
        $snapshots = $this->lineWriter->snapshots(
            array_map(static fn (QuoteDraftLine $line): int => $line->productSaleElementsId, $draft->lines),
            $this->localeOf($customer),
            $connection,
        );

        $known = [];
        $askedIds = array_values(array_filter(array_map(static fn (QuoteDraftLine $line): ?int => $line->quoteItemId, $draft->lines)));

        if ([] !== $askedIds) {
            foreach (QuoteItemQuery::create()
                ->filterById($askedIds)
                ->useQuoteRevisionQuery()->filterByQuoteId($quote->getId())->endUse()
                ->find($connection) as $item) {
                $known[(int) $item->getId()] = $item;
            }
        }

        $used = [];
        $toPrice = [];

        foreach ($draft->lines as $line) {
            if (!isset($snapshots[$line->productSaleElementsId])) {
                throw QuoteDraftRefusedException::unknownSaleElement();
            }

            if (null !== $line->quoteItemId) {
                if (!isset($known[$line->quoteItemId]) || isset($used[$line->quoteItemId])) {
                    throw QuoteDraftRefusedException::unknownLine();
                }

                $used[$line->quoteItemId] = true;

                if ((int) $known[$line->quoteItemId]->getProductSaleElementsId() === $line->productSaleElementsId) {
                    continue;
                }
            }

            $toPrice[$line->productSaleElementsId] = ($toPrice[$line->productSaleElementsId] ?? 0.0) + $line->quantity;
        }

        $catalogPrices = $this->catalogPrices->resolve($toPrice, $currency, $customer);
        $inserts = [];
        $updates = [];
        $kept = [];

        foreach ($draft->lines as $index => $line) {
            $snapshot = $snapshots[$line->productSaleElementsId];
            $item = null === $line->quoteItemId ? null : $known[$line->quoteItemId];
            $inherits = null !== $item && (int) $item->getProductSaleElementsId() === $line->productSaleElementsId;
            $catalogPrice = $inherits ? (string) $item->getCatalogPrice() : ($catalogPrices[$line->productSaleElementsId] ?? throw QuoteDraftRefusedException::noCatalogPrice());

            if (null !== $item && (int) $item->getQuoteRevisionId() === (int) $revision->getId()) {
                $updates[] = $this->fillLine($item, $index + 1, $snapshot, $line, $catalogPrice);
                $kept[] = (int) $item->getId();

                continue;
            }

            $inserts[] = [
                'position' => $index + 1,
                'snapshot' => $snapshot,
                'quantity' => $line->quantity,
                'catalogPrice' => $catalogPrice,
                'price' => $this->decimal($line->price),
            ];
        }

        // Everything that can be refused is behind: the revision is written, then its lines.
        $revision->save($connection);

        foreach ($updates as $update) {
            $update->save($connection);
        }

        $stale = QuoteItemQuery::create()->filterByQuoteRevisionId($revision->getId());

        if ([] !== $kept) {
            $stale->filterById($kept, Criteria::NOT_IN);
        }

        $stale->delete($connection);

        if ([] !== $inserts) {
            $this->lineWriter->insert((int) $revision->getId(), $inserts, $connection);
        }
    }

    private function fillLine(QuoteItem $item, int $position, QuoteLineSnapshot $snapshot, QuoteDraftLine $line, string $catalogPrice): QuoteItem
    {
        return $item
            ->setPosition($position)
            ->setProductId($snapshot->productId)
            ->setProductSaleElementsId($snapshot->productSaleElementsId)
            ->setProductRef($snapshot->productRef)
            ->setProductSaleElementsRef($snapshot->productSaleElementsRef)
            ->setTitle($snapshot->title)
            ->setQuantity($line->quantity)
            ->setCatalogPrice($catalogPrice)
            ->setPrice($this->decimal($line->price))
            ->setTaxRuleId($snapshot->taxRuleId)
            ->setTaxRuleTitle($snapshot->taxRuleTitle);
    }

    private function assertDraftShape(QuoteDraftInput $draft): void
    {
        if (!$this->isAmount($draft->discount)) {
            throw QuoteDraftRefusedException::invalidDiscount();
        }

        if (!$this->isAmount($draft->postage)) {
            throw QuoteDraftRefusedException::invalidPostage();
        }

        if (null !== $draft->merchantNote && mb_strlen($draft->merchantNote) > QuoteNoteSanitizer::MAXIMUM_LENGTH) {
            throw QuoteDraftRefusedException::noteTooLong();
        }

        foreach ($draft->lines as $line) {
            if (!$this->isQuantity($line->quantity)) {
                throw QuoteDraftRefusedException::invalidQuantity();
            }

            if (!$this->isAmount($line->price)) {
                throw QuoteDraftRefusedException::invalidPrice();
            }
        }
    }

    private function asksForMoreThanTheOffer(QuoteItemRequestInput $request, QuoteItem $line): bool
    {
        return (null !== $request->quantity && abs($request->quantity - (float) $line->getQuantity()) > 1e-9)
            || (null !== $request->price && $this->decimal($request->price) !== $this->decimal((string) $line->getPrice()));
    }

    private function isAmount(string $amount): bool
    {
        return 1 === preg_match(self::AMOUNT_PATTERN, $amount);
    }

    private function isQuantity(float $quantity): bool
    {
        return is_finite($quantity) && $quantity > 0.0 && $quantity <= self::MAXIMUM_QUANTITY;
    }

    private function decimal(string $amount): string
    {
        return number_format((float) $amount, 6, '.', '');
    }

    /**
     * Locks the row of the quote by its primary key, before anything else is read, and reads the
     * quote again from it: what the caller held may be older than the lock.
     *
     * @throws UnknownQuoteException
     */
    private function lock(Quote $quote, ConnectionInterface $connection): void
    {
        QuoteTableMap::clearInstancePool();
        QuoteRevisionTableMap::clearInstancePool();
        QuoteItemTableMap::clearInstancePool();

        $id = (int) $quote->getId();

        if (0 === $id || !QuoteQuery::create()->filterById($id)->lockForUpdate()->findOne($connection) instanceof Quote) {
            throw UnknownQuoteException::withId($id);
        }

        $quote->reload(false, $connection);
    }

    private function inTransaction(callable $work): mixed
    {
        $connection = Propel::getConnection(QuoteTableMap::DATABASE_NAME);
        $connection->beginTransaction();

        try {
            $result = $work($connection);
            $connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }
}
