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

namespace Thelia\Domain\Checkout\Service;

use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Lock\LockFactory;
use Thelia\Domain\Checkout\CheckoutFacade;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementRequest;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementResult;
use Thelia\Domain\Checkout\DTO\CheckoutViolation;
use Thelia\Domain\Checkout\DTO\OrderPaymentRequest;
use Thelia\Domain\Checkout\DTO\PaymentAction;
use Thelia\Domain\Checkout\Exception\CheckoutPlacementInProgressException;
use Thelia\Domain\Checkout\Exception\CheckoutRefusedException;
use Thelia\Domain\Checkout\Exception\UnknownConsentException;
use Thelia\Domain\Order\Exception\CartAlreadyOrderedException;
use Thelia\Model\CheckoutStep;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;

/**
 * Placing an order for a cart, with nobody at a browser.
 *
 * Everything the checkout of a theme reads off the session is handed over instead, and
 * the order still goes down the one path orders go down: ORDER_PAY, its listener, its
 * emails, its payment module call. There is deliberately no second way to write an order
 * — a second way is a second set of rules to keep in step with the first, and the ones
 * that get forgotten are the ones nobody sees, the stock and the consents.
 *
 * Three things happen here that the tunnel of a theme spreads over several screens: the
 * carrier a cart with nothing to ship is never asked about is settled, every refusal is
 * gathered rather than the first one raised, and a cart that has already been ordered is
 * recognised as such instead of being ordered again.
 *
 * ## A cart is ordered once, and what makes that true
 *
 * Three things, and only the last of them is a guarantee.
 *
 * The lock below keeps two requests of the same server from placing the same cart at
 * once, and it answers the second one honestly rather than inventing an order. It is only
 * as shared as the store behind `LOCK_DSN`: the shipped default, `flock`, is a file on the
 * local disk, so on two application servers behind a load balancer each server has a lock
 * of its own and neither sees the other. **A shop running on more than one node must point
 * `LOCK_DSN` at a store every node shares** — Redis, Memcached, or the database
 * (`symfony/lock` calls these remote stores). This is a deployment setting and the core
 * does not change it: a single-server shop pays nothing for a file lock, and a shop that
 * needs a shared one is a shop whose infrastructure is already being decided.
 *
 * The re-read that follows narrows the window further, and closes nothing on its own: a
 * read and a write are two statements.
 *
 * What holds whatever the lock store is, and whatever the number of nodes, is the guard
 * inside the transaction the order is written in — {@see \Thelia\Domain\Order\OrderFacade}
 * locks the row of the cart and re-reads its orders before writing a thing. It answers
 * with {@see CartAlreadyOrderedException}, which is caught below and turned into the same
 * `alreadyPlaced` answer the re-read gives. The theme goes through that guard too, since
 * it goes through the same facade, and it takes no lock at all.
 */
final readonly class CheckoutPlacementService
{
    /**
     * How long the placement may hold the cart before the lock is considered abandoned:
     * long enough to cover a payment module that talks to a gateway before answering,
     * short enough that a request killed mid-placement does not hold the cart for the
     * rest of the afternoon.
     *
     * A store has to implement expiry for this to mean anything. `FlockStore`, the shipped
     * default, does not: a file lock lives as long as the process holding it and is
     * released by the operating system when that process dies, which covers the same
     * accident by another route. A shared store — Redis, Memcached, PDO — honours it.
     */
    private const PLACEMENT_LOCK_TTL_SECONDS = 120.0;

    public function __construct(
        private CheckoutFacade $checkoutFacade,
        private CheckoutValidationService $validationService,
        private CheckoutPaymentService $paymentService,
        private ConsentAnswerRecorder $consentAnswerRecorder,
        private SubmittedConsentAnswers $submittedConsentAnswers,
        private LockFactory $lockFactory,
    ) {
    }

    /**
     * A cart that already carries an order comes back with `alreadyPlaced: true`, and the
     * payment is deliberately not raised again: the modules were called once, and calling
     * them a second time is how a buyer ends up with two authorisations. The consequence
     * is that the result of such a call carries no payment action, whatever the first call
     * answered — a caller that lost the answer to a placement that wanted a redirection
     * gets the order back and no redirection, and has to read the state of the order to
     * know what is left to do. `paid` and `orderStatusCode` are read back off the row for
     * exactly that, and `GET /front/account/orders/{id}` is where the rest is.
     *
     * @throws CheckoutRefusedException             when the cart still has something to settle — every one of them at once
     * @throws CheckoutPlacementInProgressException when another request is placing this very cart
     * @throws PropelException
     * @throws \Exception
     */
    public function place(CheckoutPlacementRequest $request): CheckoutPlacementResult
    {
        $cart = $request->cart;
        $cartId = (int) $cart->getId();

        $lock = $this->lockFactory->createLock('thelia.checkout.placement.'.$cartId, self::PLACEMENT_LOCK_TTL_SECONDS);

        if (!$lock->acquire()) {
            // Another request got here first. If it is already done, its order is the
            // answer; if it is still inside a payment module, there is nothing to say yet
            // but "wait" — inventing a second order to avoid saying so is how a buyer
            // ends up charged twice.
            $orderOfTheOtherRequest = $this->existingOrderFor($cartId);

            return $orderOfTheOtherRequest instanceof Order
                ? $this->resultOf($orderOfTheOtherRequest, PaymentAction::none(), alreadyPlaced: true)
                : throw new CheckoutPlacementInProgressException();
        }

        try {
            $alreadyPlaced = $this->existingOrderFor($cartId);

            if ($alreadyPlaced instanceof Order) {
                // No second order, no second stock movement and, above all, no second
                // call to the payment module: the buyer already owes this once.
                return $this->resultOf($alreadyPlaced, PaymentAction::none(), alreadyPlaced: true);
            }

            // The delivery of a cart with nothing to ship is the one question the buyer is
            // never asked and the order cannot be written without. The tunnel of a theme
            // settles it as the buyer walks past the step; a caller with no steps to walk
            // past meets the same rule here.
            $this->checkoutFacade->settleVirtualDeliveryIfNeeded($cart);

            // Before anything is asked of the cart: the guard that refuses an order over
            // a consent reads the answers of this request, and so does the proof written
            // on the order a moment later. Recording them here rather than in the caller
            // is what keeps the two reading the same thing.
            $this->recordTheConsentsAnsweredInTheRequest($request);

            $violations = $this->validationService->collectViolations($cart);

            if ([] !== $violations) {
                throw new CheckoutRefusedException($violations);
            }

            try {
                $outcome = $this->paymentService->payAndReturnOutcome(
                    OrderPaymentRequest::ofTheChoicesOnTheCart($request),
                );
            } catch (CartAlreadyOrderedException $race) {
                // Another node wrote the order between the re-read above and the insert:
                // the lock this request holds is local to the server it runs on, and the
                // database is what arbitrated. Same answer as the re-read would have given
                // a moment later, and no second order.
                return $this->resultOf($this->orderById($race->orderId), PaymentAction::none(), alreadyPlaced: true);
            }

            return $this->resultOf(
                $outcome->placedOrder,
                PaymentAction::fromPaymentResponse($outcome->paymentResponse),
                alreadyPlaced: false,
            );
        } finally {
            // The answers recorded for this placement must not outlive it: an order that
            // was written froze them already, and a refusal must not leave them behind
            // for the next placement of the same process to inherit. Only the answers
            // handed over with this request are dropped — a session belongs to the theme.
            $this->submittedConsentAnswers->clear();
            $lock->release();
        }
    }

    /**
     * A consent the shop is not asking for is refused the way everything else about this
     * placement is refused: the same payload, with a code of its own to branch on. It is
     * a fault of the caller rather than of the cart, and answering it with a failure
     * would have a client retry a request that will never work.
     *
     * @throws CheckoutRefusedException
     * @throws PropelException
     */
    private function recordTheConsentsAnsweredInTheRequest(CheckoutPlacementRequest $request): void
    {
        try {
            $this->consentAnswerRecorder->record($request->consentAnswers, (string) $request->lang->getLocale());
        } catch (UnknownConsentException $unknown) {
            throw new CheckoutRefusedException([CheckoutViolation::fromRefusal(CheckoutStep::CODE_PAYMENT, $unknown)], previous: $unknown);
        }
    }

    /**
     * The order this cart has already been turned into, if it has one that still stands.
     *
     * @throws PropelException
     */
    private function existingOrderFor(int $cartId): ?Order
    {
        return OrderQuery::findStandingOrderOfCart($cartId);
    }

    /**
     * The order the database said already exists. It was read a moment ago, inside the
     * transaction that refused to write a second one, so nothing here can find nothing.
     *
     * @throws PropelException
     */
    private function orderById(int $orderId): Order
    {
        return OrderQuery::create()->findPk($orderId)
            ?? throw new \LogicException(\sprintf('Order %d was named by the placement guard and cannot be read back.', $orderId));
    }

    /**
     * The status is read back off the row rather than assumed: a module that settles the
     * payment while it is being called — an order that costs nothing, a wallet with
     * enough in it — leaves a paid order behind, and reporting it as waiting for its
     * payment would have the front show a buyer a bill they have already settled.
     *
     * @throws PropelException
     */
    private function resultOf(Order $order, PaymentAction $paymentAction, bool $alreadyPlaced): CheckoutPlacementResult
    {
        $order->reload();

        return new CheckoutPlacementResult(
            (int) $order->getId(),
            (string) $order->getRef(),
            (string) $order->getOrderStatus()?->getCode(),
            $order->isPaid(),
            $paymentAction,
            $alreadyPlaced,
        );
    }
}
