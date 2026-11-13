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

namespace Thelia\Domain\Subscription;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Checkout\Exception\GuestCheckoutNotAllowedException;
use Thelia\Domain\Checkout\Exception\InvalidPaymentException;
use Thelia\Domain\Subscription\Exception\SubscriptionCartRefusedException;
use Thelia\Model\Cart;
use Thelia\Model\CartItemQuery;
use Thelia\Model\Customer;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\SubscriptionPlanQuery;

/**
 * The rules a cart holding a subscription obeys.
 *
 * A subscription is one line: one sale element, a quantity and a plan. The cart that holds
 * it holds nothing else, so that the order it becomes, and the installments that copy it,
 * are the subscription and nothing more. Whoever subscribes is a customer who is signed in
 * (a guest has no account to manage the subscription from), and pays with a module that can
 * charge the installments without them.
 *
 * Judged on CART_ADDITEM, above the guard of the bundles (150) and the cart (128), so that
 * the form, the API and a module going through CartFacade meet the same refusals; the two
 * checks that hold when the order is placed are called by CheckoutPaymentService.
 */
final readonly class SubscriptionCartGuard implements EventSubscriberInterface
{
    public function __construct(
        private SecurityContext $securityContext,
        private RecurringPaymentModuleResolver $recurringPaymentModuleResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [TheliaEvents::CART_ADDITEM => ['judgeAnAddition', 160]];
    }

    /**
     * @throws SubscriptionCartRefusedException
     */
    public function judgeAnAddition(CartEvent $event): void
    {
        $cart = $event->getCart();
        $planId = $event->getSubscriptionPlanId();

        if (null === $planId) {
            if ($this->holdsASubscriptionLine($cart)) {
                throw SubscriptionCartRefusedException::otherLineInASubscriptionCart();
            }

            return;
        }

        $customer = $cart->getCustomer() ?? $this->securityContext->getCustomerUser();

        if (!$customer instanceof Customer || $customer->isGuest()) {
            throw SubscriptionCartRefusedException::customerRequired();
        }

        // Fresh from the database: a line added a moment ago is as much in the way.
        if (!$cart->isNew() && CartItemQuery::create()->filterByCartId($cart->getId())->exists()) {
            throw SubscriptionCartRefusedException::mixedWithOtherLines();
        }

        $plan = SubscriptionPlanQuery::create()->findPk($planId);

        if (null === $plan || 1 !== (int) $plan->getVisible()) {
            throw SubscriptionCartRefusedException::planNotAvailable();
        }

        $saleElements = null === $event->getProductSaleElementsId() ? null : ProductSaleElementsQuery::create()->findPk($event->getProductSaleElementsId());

        if (null === $saleElements || (int) $saleElements->getProductId() !== (int) $plan->getProductId()) {
            throw SubscriptionCartRefusedException::planNotOfTheProduct();
        }
    }

    public function holdsASubscriptionLine(Cart $cart): bool
    {
        if ($cart->isNew()) {
            return false;
        }

        return CartItemQuery::create()
            ->filterByCartId($cart->getId())
            ->filterBySubscriptionPlanId(null, Criteria::ISNOTNULL)
            ->exists();
    }

    /**
     * The same question asked of the lines the cart already loaded, for the callers that ask
     * it for each payment module in turn.
     */
    public function loadedCartHoldsASubscriptionLine(Cart $cart): bool
    {
        foreach ($cart->getCartItems() as $line) {
            if (null !== $line->getSubscriptionPlanId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * What holds when the order is placed, whatever path placed it.
     *
     * @throws GuestCheckoutNotAllowedException
     * @throws InvalidPaymentException
     */
    public function refuseUnlessSubscribable(Cart $cart, int $paymentModuleId): void
    {
        if (!$this->holdsASubscriptionLine($cart)) {
            return;
        }

        $customer = $cart->getCustomer();

        if (!$customer instanceof Customer || $customer->isGuest()) {
            throw new GuestCheckoutNotAllowedException(Translator::getInstance()->trans('A subscription needs an account: sign in to order it.'));
        }

        if (null === $this->recurringPaymentModuleResolver->chargingModule($paymentModuleId)) {
            throw new InvalidPaymentException(Translator::getInstance()->trans('This payment module cannot charge the installments of a subscription.'));
        }
    }
}
