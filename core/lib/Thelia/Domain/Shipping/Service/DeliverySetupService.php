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
     * already has when it has one. Nothing for a cart without a customer, nor for a guest:
     * the default address of a guest row may be another buyer's (see setupVirtualDelivery()).
     */
    public function setCustomerDefaultDeliveryAddress(Cart $cart): void
    {
        $customer = $cart->getCustomer();

        if (null === $customer || $customer->isGuest()) {
            return;
        }

        $defaultAddress = $customer->getDefaultAddress();

        if (null === $defaultAddress) {
            throw new \RuntimeException(Translator::getInstance()->trans('Customer default address is null'));
        }

        $defaultCartAddress = $this->cartAddressService->getOrCreateCartAddressFromAddress(
            $defaultAddress,
            $cart->getCartAddressRelatedByAddressDeliveryId(),
        );

        $cart->setAddressDeliveryId($defaultCartAddress->getId())->save();
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

        // Never the default address of a guest row: the row is reused by whoever types the
        // same e-mail address, and each identification makes its own address the default
        // one, so it may be another buyer's. A guest keeps the address the cart has, if any.
        if (null !== $customer && !$customer->isGuest()) {
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
}
