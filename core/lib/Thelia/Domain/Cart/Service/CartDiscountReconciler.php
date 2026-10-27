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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Promotion\Coupon\Service\CouponManager;
use Thelia\Domain\Shipping\Service\PostageHandler;
use Thelia\Model\Cart;
use Thelia\Model\Coupon;
use Thelia\Model\CouponQuery;
use Thelia\Model\Customer;

/**
 * Brings what depends on the amount of a cart (the discount of the promotions, the postage)
 * in line with its lines, once the lines moved.
 *
 * A cart change heard by the coupon action prices the discount of the session cart, but the
 * pricing of the lines at the payment is no cart change: it announces itself
 * (CART_LINES_REPRICED) and the coupon action prices the discount the way it does for any
 * change, with the codes the buyer typed in. A request with no session (the front API) hears
 * none of it, so the cart would keep the discount of the old total and the next call would
 * place the order on it: this prices the promotions that apply on their own, from the cart itself.
 *
 * Those cannot see a code typed in a browser session, which is nowhere on the cart (an order
 * paid through the API for a cart a browser holds): a discount the automatic promotions did not
 * give on the old lines is left as it is rather than rewritten without the code. It is priced
 * again with the codes at the next request of the session that holds the cart.
 */
final readonly class CartDiscountReconciler
{
    public function __construct(
        private CouponManager $couponManager,
        private PostageHandler $postageHandler,
        private RequestStack $requestStack,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Whether the discount of the cart is the one the promotions that apply on their own give,
     * so that pricing them again on the new lines loses no code. Asked before the lines move:
     * it compares the discount with the old lines it was written for.
     */
    public function discountIsThePromotionsOwn(Cart $cart, ?Customer $buyer): bool
    {
        if ($this->aSessionHoldsThisCart($cart) || !$this->aPromotionAppliesOnItsOwn()) {
            return true;
        }

        return abs((float) $cart->getDiscount() - $this->couponManager->discountOfCartWithoutSession($cart, $buyer)) < 0.005;
    }

    /**
     * @param bool $discountIsThePromotionsOwn the answer of discountIsThePromotionsOwn() before the lines moved
     */
    public function reconcile(Cart $cart, ?Customer $buyer, bool $discountIsThePromotionsOwn): void
    {
        // The lines were just written: the cart must not answer with the ones it held.
        $cart->clearCartItems();

        if ($this->aSessionHoldsThisCart($cart)) {
            $this->dispatcher->dispatch(new CartEvent($cart), TheliaEvents::CART_LINES_REPRICED);
        } elseif ($discountIsThePromotionsOwn && $this->aPromotionAppliesOnItsOwn()) {
            $cart
                ->setDiscount((string) $this->couponManager->discountOfCartWithoutSession($cart, $buyer))
                ->save();
        }

        // The postage the carrier quotes can follow the amount of the cart (thresholds).
        try {
            $this->postageHandler->handlePostageOnCart($cart);
        } catch (\RuntimeException) {
            // Already logged by the handler, which took the postage off the cart: the validation of
            // the checkout answers for a cart left with no postage.
        }
    }

    /**
     * The visitor of a browser has this cart in their session, with the codes they typed in, which
     * this cannot read: the session path answers for that cart.
     *
     * A session that was merely started is not one: an API request reads the customer through it
     * (and so starts it) while holding no cart.
     */
    private function aSessionHoldsThisCart(Cart $cart): bool
    {
        $request = $this->requestStack->getMainRequest();

        if (!$request?->hasSession(true)) {
            return false;
        }

        return (int) $request->getSession()->get(Session::SESSION_CART_ID_NAME) === (int) $cart->getId();
    }

    private function aPromotionAppliesOnItsOwn(): bool
    {
        return CouponQuery::create()->filterByTriggerMode(Coupon::TRIGGER_MODE_AUTOMATIC)->count() > 0;
    }
}
