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

    public function setCustomerDefaultDeliveryAddress(Cart $cart): void
    {
        $customer = $cart->getCustomer();

        if (null === $customer) {
            return;
        }

        $defaultCartAddress = $this->cartAddressService->getOrCreateCartAddressFromAddress($customer->getDefaultAddress());

        if (null === $defaultCartAddress) {
            throw new \RuntimeException(Translator::getInstance()->trans('Customer default address is null'));
        }

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

        if (null !== $customer) {
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
                $cart->setAddressDeliveryId($defaultAddress->getId())->save();
            }
        }

        $cart->setDeliveryModuleId($virtualDeliveryModule->getId())->save();

        $addressId = $cart->getAddressDeliveryId();
        if (null !== $addressId) {
            $address = AddressQuery::create()->findPk($addressId);
            $country = $address?->getCountry();
            if (null !== $country) {
                $state = $address->getState();
                $estimate = $this->postageEstimator->estimatePostageForCountry($cart, $country, $state);

                $cart
                    ->setPostage((float) ($estimate['postage'] ?? 0.0))
                    ->setPostageTax((float) ($estimate['tax'] ?? 0.0))
                    ->save();
            }
        }
    }
}
