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

namespace Thelia\Domain\Cart\Service;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Customer\CustomerLoginEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Cart\Exception\ProductNotAvailableException;
use Thelia\Domain\Pricing\PricingActivityChecker;
use Thelia\Domain\Shipping\Service\PostageHandler;
use Thelia\Model\Cart;
use Thelia\Model\Currency;

/**
 * Keeps the cart to what the catalog shows its visitor, at what that visitor pays, on the
 * moments a line gets into a cart without the catalog being read.
 *
 *  - An addition of a product the visitor may not see is refused, whoever asks: the live
 *    components of a theme, CartFacade::addItem(), a module dispatching CART_ADDITEM. Above
 *    Thelia\Action\Cart::addItem (128), so a refused product never reaches the cart, and
 *    above the destination guard (200): a product that does not exist for the visitor is
 *    not worth a word about its delivery.
 *  - A visitor who signs in takes their cart with them: the lines they may no longer see are
 *    taken out of it (a table put in the cart as a visitor, a customer group that only
 *    browses sofas), and the lines are priced for the customer they turned out to be (the
 *    price of their group). Both after the login action (128) has attached the cart to the
 *    customer, and BEFORE the promotions are read again (10), so that the discount follows
 *    the lines that are left at the price they now have. The postage comes last (5), on the
 *    discount that was just priced.
 *  - A cart found again by its cookie loses the lines its visitor no longer sees, and the
 *    session prices the discount and the postage on what is left (CART_LINES_REMOVED): not
 *    while the cart is being restored, when no session cart exists yet and the listeners of
 *    the discount would restore it again, for ever.
 */
final class CartProductVisibilitySubscriber implements EventSubscriberInterface
{
    /**
     * Whether the cart of the sign-in being handled lost a line or moved a price: the postage
     * is quoted again only then. Set and read within one CUSTOMER_LOGIN dispatch.
     */
    private bool $theCartMovedAtSignIn = false;

    public function __construct(
        private readonly CartLineVisibility $cartLineVisibility,
        private readonly RequestStack $requestStack,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CartLinePricer $cartLinePricer,
        private readonly PricingActivityChecker $pricingActivityChecker,
        private readonly PostageHandler $postageHandler,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::CART_ADDITEM => ['refuseAHiddenProduct', 210],
            // Above the discount of the promotions (Action\Coupon, 10), which reads the lines.
            TheliaEvents::CUSTOMER_LOGIN => [
                ['settleTheCartOfTheCustomer', 100],
                ['quoteThePostageAgainAtSignIn', 5],
            ],
            TheliaEvents::CART_LINES_REMOVED => ['quoteThePostageAgain', 5],
        ];
    }

    public function refuseAHiddenProduct(CartEvent $event): void
    {
        $productSaleElementsId = $event->getProductSaleElementsId();

        if (null !== $productSaleElementsId && $this->cartLineVisibility->isSaleElementHidden($productSaleElementsId)) {
            throw new ProductNotAvailableException();
        }
    }

    public function settleTheCartOfTheCustomer(CustomerLoginEvent $event): void
    {
        $this->theCartMovedAtSignIn = false;

        $request = $this->requestStack->getMainRequest();

        // A sign-in with no session (a command line, a worker) has no cart to take along.
        if (null === $request || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        if (!$session instanceof Session) {
            return;
        }

        $cart = $session->getSessionCart($this->eventDispatcher);

        // First what the customer may not see: no price is worked out for a line that goes.
        $removed = [] !== $this->cartLineVisibility->removeHiddenLines($cart);

        // What the visitor put in the cart is the public price: the customer they turned out to
        // be may owe another one (the group they belong to). The login action only attaches the
        // cart, and the cart of a session is not duplicated and repriced the way a cart found by
        // its cookie is.
        $customer = $event->getCustomer();
        $moved = [];

        if ($this->pricingActivityChecker->pricesDependOnWhoIsAsking($customer)) {
            $cart->clearCartItems();
            $moved = $this->cartLinePricer->reprice($cart, $cart->getCurrency() ?? Currency::getDefaultCurrency(), $customer);
            $cart->clearCartItems();
        }

        $this->theCartMovedAtSignIn = $removed || [] !== $moved;
    }

    public function quoteThePostageAgainAtSignIn(CustomerLoginEvent $event): void
    {
        if (!$this->theCartMovedAtSignIn) {
            return;
        }

        $this->theCartMovedAtSignIn = false;

        $request = $this->requestStack->getMainRequest();
        $session = $request?->hasSession() ? $request->getSession() : null;

        if ($session instanceof Session) {
            $this->requote($session->getSessionCart($this->eventDispatcher));
        }
    }

    public function quoteThePostageAgain(CartEvent $event): void
    {
        $this->requote($event->getCart());
    }

    /**
     * The carrier of the cart quotes it again on the lines that are left (thresholds on the
     * amount of the cart). A cart with no carrier has no postage to quote.
     */
    private function requote(Cart $cart): void
    {
        if (null === $cart->getDeliveryModuleId()) {
            return;
        }

        try {
            $this->postageHandler->handlePostageOnCart($cart);
        } catch (\RuntimeException) {
            // Already logged by the handler, which took the postage off the cart: the validation of
            // the checkout answers for a cart left with no postage.
        }
    }
}
