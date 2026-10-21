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

namespace Thelia\Domain\Shipping\Service;

use Thelia\Core\Translation\Translator;
use Thelia\Domain\Cart\Service\CartAddressService;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartAddress;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;

final readonly class DeliverySetupService
{
    public function __construct(
        private PostageEstimator $postageEstimator,
        private CartAddressService $cartAddressService,
    ) {
    }

    /**
     * Copies the default address of the cart's customer onto the cart, into the copy the cart
     * already has when it has one, a copy typed by hand included. Nothing for a cart without
     * a customer, nor for a guest: the default address of a guest row may be another buyer's
     * (see setupVirtualDelivery()).
     *
     * @return bool whether the cart is delivered to another place: it had no copy, or its
     *              copy changed country, state, postal code or city, which the carriers and
     *              their postage depend on; false when nothing was copied
     */
    public function setCustomerDefaultDeliveryAddress(Cart $cart): bool
    {
        $customer = $cart->getCustomer();

        if (null === $customer || $customer->isGuest()) {
            return false;
        }

        $defaultAddress = $customer->getDefaultAddress();

        if (null === $defaultAddress) {
            throw new \RuntimeException(Translator::getInstance()->trans('Customer default address is null'));
        }

        $copy = $cart->getCartAddressRelatedByAddressDeliveryId();
        $placeBefore = null === $copy ? null : self::placeOf($copy);

        $defaultCartAddress = $this->cartAddressService->getOrCreateCartAddressFromAddress($defaultAddress, $copy);

        $cart->setAddressDeliveryId($defaultCartAddress->getId())->save();

        return $placeBefore !== self::placeOf($defaultCartAddress);
    }

    public function setupVirtualDelivery(Cart $cart): void
    {
        $virtualDeliveryModule = ModuleQuery::create()
            ->filterByActivate(1)
            ->filterByType(BaseModule::DELIVERY_MODULE_TYPE)
            ->filterByCode('VirtualProductDelivery')
            ->findOne();

        if (null === $virtualDeliveryModule) {
            throw new \RuntimeException(Translator::getInstance()->trans('Virtual delivery module not found'));
        }

        $customer = $cart->getCustomer();

        // Never an address of a guest row: the row is reused by whoever types the same e-mail
        // address, and each identification makes its own address the default one, so its
        // default address, and its first one, may be another buyer's. A guest keeps the
        // delivery address the cart has, else takes the billing address of this cart.
        if (true === $customer?->isGuest()) {
            $this->giveTheGuestTheBillingAddressOfTheCart($cart);
        } elseif (null !== $customer) {
            $defaultAddress = AddressQuery::create()
                ->filterByCustomerId($customer->getId())
                ->filterByIsDefault(1)
                ->findOne();

            if (null === $defaultAddress) {
                $defaultAddress = AddressQuery::create()
                    ->filterByCustomerId($customer->getId())
                    ->findOne();
            }

            if (null !== $defaultAddress) {
                $cartAddress = $this->cartAddressService->getOrCreateCartAddressFromAddress(
                    $defaultAddress,
                    $cart->getCartAddressRelatedByAddressDeliveryId(),
                );

                $cart->setAddressDeliveryId($cartAddress->getId())->save();
            }
        }

        $cart->setDeliveryModuleId($virtualDeliveryModule->getId())->save();

        $deliveryAddress = $cart->getCartAddressRelatedByAddressDeliveryId();
        $country = $deliveryAddress?->getCountry();

        if (null !== $country) {
            $estimate = $this->postageEstimator->estimatePostageForCountry($cart, $country, $deliveryAddress->getState(), $deliveryAddress->getZipcode());

            $cart
                ->setPostage((string) ($estimate->getBestPostageAmount() ?? 0))
                ->setPostageTax((string) ($estimate->getBestPostageTax() ?? 0))
                ->save();
        }
    }

    /**
     * The billing address on a guest's cart was given for this cart, by this buyer: a cart
     * with nothing to ship and no delivery address takes a copy of it, its own row, so that
     * a later change of one leaves the other as it is.
     */
    private function giveTheGuestTheBillingAddressOfTheCart(Cart $cart): void
    {
        if (null !== $cart->getCartAddressRelatedByAddressDeliveryId()) {
            return;
        }

        $billingAddress = $cart->getCartAddressRelatedByAddressInvoiceId();

        if (null === $billingAddress) {
            return;
        }

        $deliveryAddress = $billingAddress->copy();
        $deliveryAddress->save();

        $cart->setAddressDeliveryId($deliveryAddress->getId())->save();
    }

    /**
     * Where a copy delivers to, as the carriers read it: the country, the state, the postal
     * code and the city (the same four DeliveryAddressCopy of the Flexy theme compares).
     *
     * @return array{0: int|null, 1: int|null, 2: string, 3: string}
     */
    private static function placeOf(CartAddress $copy): array
    {
        return [$copy->getCountryId(), $copy->getStateId(), (string) $copy->getZipcode(), (string) $copy->getCity()];
    }
}
