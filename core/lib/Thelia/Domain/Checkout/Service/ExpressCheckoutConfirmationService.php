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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\SecurityContext;
use Thelia\Domain\Checkout\DTO\CheckoutPlacementResult;
use Thelia\Domain\Checkout\DTO\ExpressCheckoutRequest;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutConfirmationDeniedException;
use Thelia\Domain\Checkout\Exception\ExpressCheckoutRefusedException;
use Thelia\Model\Cart;
use Thelia\Model\Customer;

/**
 * The shop's side of "the buyer paid in the wallet".
 *
 * A module only reads its provider's answer. Everything that can cost the buyer their
 * cart or their money is done here, once, for every wallet: the proof that the request
 * comes from a page served for this cart, a cart that belongs to the buyer in session,
 * then the placement with all its guards.
 *
 * The wallet is offered in the checkout, where the buyer is already identified, signed
 * in or as a guest. This service never opens anybody: a session that holds no buyer is
 * refused.
 */
final readonly class ExpressCheckoutConfirmationService
{
    public function __construct(
        private ExpressCheckoutGate $gate,
        private ExpressCheckoutPlacementService $placementService,
        private SecurityContext $securityContext,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @throws ExpressCheckoutConfirmationDeniedException when the request is not one the shop reads at all
     * @throws ExpressCheckoutRefusedException            when the shop and the wallet do not agree
     * @throws PropelException
     */
    public function confirm(Request $request, string $moduleCode, string $token): CheckoutPlacementResult
    {
        $access = $this->gate->open($request, $moduleCode, $token);

        /** @var Session $session the gate refuses a request without one */
        $session = $request->getSession();

        $answer = $access->module->readExpressPaymentConfirmation($request, $access->cart);

        $cart = $this->cartOfTheIdentifiedBuyer($session, $access->cart);

        $result = $this->placementService->place(new ExpressCheckoutRequest(
            $cart,
            $access->paymentModuleId,
            $answer->totalTaxIncludedShownToTheBuyer,
            $answer->consentAnswers,
        ));

        // A paid order is settled on the next read of the session cart: the guest leaves
        // the session and an empty cart takes this one's place. It is read here rather
        // than left to the next page, because the theme's confirmation page replaces the
        // cart without reading it, and the guest would stay behind for the next checkout
        // on this browser with an address book the theme hides from them.
        if ($result->paid) {
            $session->getSessionCart($this->dispatcher);
        }

        return $result;
    }

    /**
     * The cart, belonging to the buyer the session holds.
     *
     * The checkout binds the cart when it identifies the buyer, so it is normally already
     * theirs. A cart that is not yet is bound here, read again first: the session knows the
     * buyer, and that read settles which row is the cart from here on.
     *
     * @throws ExpressCheckoutRefusedException when the session holds no buyer
     * @throws PropelException
     */
    private function cartOfTheIdentifiedBuyer(Session $session, Cart $cart): Cart
    {
        if (null !== $cart->getCustomerId()) {
            return $cart;
        }

        $customer = $this->securityContext->getCustomerUser();

        if (!$customer instanceof Customer) {
            throw ExpressCheckoutRefusedException::becauseTheBuyerIsNotIdentified();
        }

        $cart = $this->gate->cartToPayFor($session);
        $cart->setCustomerId($customer->getId())->save();
        $session->setSessionCart($cart);

        return $cart;
    }
}
