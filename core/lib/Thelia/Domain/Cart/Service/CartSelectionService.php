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
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Translation\Translator;
use Thelia\Domain\Checkout\DTO\CheckoutDTO;
use Thelia\Domain\Checkout\EventBuilder\CartCheckoutEventBuilder;
use Thelia\Domain\Checkout\Exception\DestinationRestrictedException;
use Thelia\Domain\Promotion\Coupon\Type\FreeProduct;
use Thelia\Domain\Shipping\DestinationRestriction\Destination;
use Thelia\Domain\Shipping\DestinationRestriction\DestinationRestrictionChecker;
use Thelia\Domain\Shipping\Service\PostageHandler;
use Thelia\Model\AddressQuery;
use Thelia\Model\Lang;

class CartSelectionService
{
    public function __construct(
        protected EventDispatcherInterface $eventDispatcher,
        protected CartCheckoutEventBuilder $cartCheckoutEventBuilder,
        protected PostageHandler $postageHandler,
        protected ?DestinationRestrictionChecker $destinationRestrictionChecker = null,
        protected ?RequestStack $requestStack = null,
    ) {
    }

    public function setDeliveryModule(CheckoutDTO $checkoutDTO): void
    {
        $cartCheckoutEvent = $this->cartCheckoutEventBuilder->buildEvent($checkoutDTO);
        $this->eventDispatcher->dispatch($cartCheckoutEvent, TheliaEvents::CART_SET_DELIVERY_MODULE);

        $this->postageHandler->handlePostageOnCart($checkoutDTO->getCart());
    }

    /**
     * @throws DestinationRestrictedException when products of the cart cannot ship there
     */
    public function setDeliveryAddress(CheckoutDTO $checkoutDTO): void
    {
        $this->refuseARestrictedDestination($checkoutDTO);

        $cartCheckoutEvent = $this->cartCheckoutEventBuilder->buildEvent($checkoutDTO);
        $this->eventDispatcher->dispatch($cartCheckoutEvent, TheliaEvents::CART_SET_DELIVERY_ADDRESS);

        $this->postageHandler->handlePostageOnCart($checkoutDTO->getCart());
    }

    /**
     * The address is refused before it is written, naming the products of the cart that
     * cannot ship there, so the buyer can pick another one or empty the cart.
     *
     * Only what travels is judged here: a download follows the invoice address, which
     * the buyer may not have chosen yet, and is judged at the placement of the order.
     * An address of another customer is not looked at, as the action that writes the
     * address ignores it too. A gift offered by a promotion is not judged either: the
     * buyer cannot remove it, and writing the address applies the promotions again,
     * which withdraws the gift this destination refuses (the offered lines, and the
     * line a free product promotion holds as its gift).
     */
    private function refuseARestrictedDestination(CheckoutDTO $checkoutDTO): void
    {
        $addressId = $checkoutDTO->getDeliveryAddressId();

        if (null === $this->destinationRestrictionChecker || null === $addressId || !$this->destinationRestrictionChecker->hasActiveGroup()) {
            return;
        }

        $cart = $checkoutDTO->getCart();
        $address = AddressQuery::create()
            ->filterByCustomerId($cart->getCustomerId())
            ->filterById($addressId)
            ->findOne();

        if (null === $address) {
            return;
        }

        $giftCartItemIds = $this->freeProductGiftCartItemIds();
        $boughtProductIds = [];

        foreach ($cart->getCartItems() as $cartItem) {
            if (1 !== (int) $cartItem->getIsOffered() && !\in_array((int) $cartItem->getId(), $giftCartItemIds, true)) {
                $boughtProductIds[] = (int) $cartItem->getProductId();
            }
        }

        $lines = $this->destinationRestrictionChecker->violationsForProducts(
            $boughtProductIds,
            Destination::of($address),
            null,
            Translator::getInstance()->getLocale() ?: Lang::getDefaultLanguage()->getLocale(),
        );

        if ([] !== $lines) {
            throw new DestinationRestrictedException($lines);
        }
    }

    /**
     * @return list<int>
     */
    private function freeProductGiftCartItemIds(): array
    {
        $request = $this->requestStack?->getMainRequest();

        if (null === $request || !$request->hasSession()) {
            return [];
        }

        return FreeProduct::giftCartItemIds($request->getSession());
    }

    public function setInvoiceAddress(CheckoutDTO $checkoutDTO): void
    {
        $cartCheckoutEvent = $this->cartCheckoutEventBuilder->buildEvent($checkoutDTO);
        $this->eventDispatcher->dispatch($cartCheckoutEvent, TheliaEvents::CART_SET_INVOICE_ADDRESS);

        $this->postageHandler->handlePostageOnCart($checkoutDTO->getCart());
    }

    public function setPaymentModule(CheckoutDTO $checkoutDTO): void
    {
        $cartCheckoutEvent = $this->cartCheckoutEventBuilder->buildEvent($checkoutDTO);
        $this->eventDispatcher->dispatch($cartCheckoutEvent, TheliaEvents::CART_SET_PAYMENT_MODULE);

        $this->postageHandler->handlePostageOnCart($checkoutDTO->getCart());
    }
}
