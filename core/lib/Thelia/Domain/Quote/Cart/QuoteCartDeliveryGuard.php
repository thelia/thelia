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

namespace Thelia\Domain\Quote\Cart;

use Thelia\Domain\Checkout\Exception\QuoteDeliveryImposedException;
use Thelia\Domain\Quote\Service\QuoteAccess;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\Module;
use Thelia\Model\ModuleQuery;
use Thelia\Model\Quote;
use Thelia\Module\BaseModule;

/**
 * The carrier and the delivery address of the cart of a quote are the ones the quote names.
 *
 * What the quote leaves open is asked once: a quote with no carrier (or whose carrier the shop
 * switched off since), or no address the buyer can use, takes the first choice of the checkout, and
 * that choice is then held on the cart like what the quote names. Asked again with another one,
 * the cart refuses.
 */
final readonly class QuoteCartDeliveryGuard
{
    public function __construct(
        private QuoteCartInspector $inspector,
        private QuoteAccess $access,
    ) {
    }

    /**
     * @param int|null $deliveryAddressId the id of the address of the address book the cart ships to
     *
     * @throws QuoteDeliveryImposedException
     */
    public function refuseAnotherDelivery(Cart $cart, ?int $deliveryModuleId, ?int $deliveryAddressId): void
    {
        $this->refuseAnotherCarrier($cart, $deliveryModuleId);
        $this->refuseAnotherAddress($cart, $deliveryAddressId);
    }

    /**
     * @throws QuoteDeliveryImposedException
     */
    public function refuseAnotherCarrier(Cart $cart, ?int $deliveryModuleId): void
    {
        $quote = $this->inspector->quoteOf($cart);

        if (!$quote instanceof Quote) {
            return;
        }

        $imposed = $this->carrierOf($quote->getDeliveryModuleId()) ?? $this->carrierOf($cart->getDeliveryModuleId());

        if (null !== $imposed && (int) $imposed !== (int) $deliveryModuleId) {
            throw QuoteDeliveryImposedException::aboutTheCarrier();
        }
    }

    /**
     * @throws QuoteDeliveryImposedException
     */
    public function refuseAnotherAddress(Cart $cart, ?int $deliveryAddressId): void
    {
        $quote = $this->inspector->quoteOf($cart);

        if (!$quote instanceof Quote) {
            return;
        }

        $onTheCart = $cart->getCartAddressRelatedByAddressDeliveryId();
        $imposed = $this->addressOf($quote, $cart);

        // No address on the quote the buyer can use, and none chosen yet: the first one goes through.
        if (null === $imposed && null === $onTheCart) {
            return;
        }

        $imposed ??= (int) $onTheCart->getAddressId();

        if ($imposed !== (int) $deliveryAddressId) {
            throw QuoteDeliveryImposedException::aboutTheAddress();
        }
    }

    /**
     * An address typed in at the checkout belongs to no address book: it goes through only on a quote
     * that names no address and while the cart has none, and is then the one the cart holds. Taking
     * the address off the cart again, which a refused express payment does to leave the cart as it
     * found it, is not a choice and goes through.
     *
     * @throws QuoteDeliveryImposedException
     */
    public function refuseAnotherTypedAddress(Cart $cart, ?CartAddress $typed): void
    {
        $quote = $this->inspector->quoteOf($cart);

        if (!$quote instanceof Quote || null === $typed) {
            return;
        }

        $onTheCart = $cart->getCartAddressRelatedByAddressDeliveryId();

        if (null === $this->addressOf($quote, $cart) && null === $onTheCart) {
            return;
        }

        if (null === $onTheCart || (int) $typed->getId() !== (int) $onTheCart->getId()) {
            throw QuoteDeliveryImposedException::aboutTheAddress();
        }
    }

    /**
     * The address the quote ships to, when it still stands and the buyer of the cart may use it.
     */
    public function addressOf(Quote $quote, Cart $cart): ?int
    {
        $addressId = $quote->getDeliveryAddressId();
        $buyer = $cart->getCustomer();

        if (null === $addressId || null === $buyer || !$this->access->canUseAddressId($buyer, $addressId)) {
            return null;
        }

        return null === AddressQuery::create()->findPk($addressId) ? null : (int) $addressId;
    }

    /**
     * The carrier, when it still is a delivery module and is switched on: a carrier the shop
     * switched off after the quote was sent is no longer one the buyer can be held to, and counts
     * as no carrier at all.
     */
    private function carrierOf(?int $moduleId): ?int
    {
        if (null === $moduleId) {
            return null;
        }

        $module = ModuleQuery::create()->findPk($moduleId);

        return $module instanceof Module && BaseModule::DELIVERY_MODULE_TYPE === (int) $module->getType() && 0 !== (int) $module->getActivate() ? (int) $moduleId : null;
    }
}
