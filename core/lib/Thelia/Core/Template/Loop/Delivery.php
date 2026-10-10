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

namespace Thelia\Core\Template\Loop;

use Thelia\Core\Event\Delivery\DeliveryPostageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Domain\Shipping\ProductRule\CartShippingRules;
use Thelia\Domain\Shipping\ProductRule\ProductShippingRuleQuoter;
use Thelia\Model\AddressQuery;
use Thelia\Model\AreaDeliveryModuleQuery;
use Thelia\Model\Cart;
use Thelia\Model\CountryQuery;
use Thelia\Model\Module;
use Thelia\Model\OrderPostage;
use Thelia\Model\StateQuery;
use Thelia\Module\BaseModule;
use Thelia\Module\DeliveryModuleInterface;
use Thelia\Module\DeliveryModuleWithStateInterface;
use Thelia\Module\Exception\DeliveryException;

/**
 * Class Delivery.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Etienne Roudeix <eroudeix@gmail.com>
 *
 * @method int getAddress()
 * @method int getCountry()
 * @method int getState()
 */
class Delivery extends BaseSpecificModule
{
    /**
     * Nullable: the loop executor builds a loop with no argument when it is not a service.
     */
    public function __construct(
        protected readonly ?CartShippingRules $cartShippingRules = null,
        protected readonly ?ProductShippingRuleQuoter $productShippingRuleQuoter = null,
    ) {
    }

    protected function getArgDefinitions(): ArgumentCollection
    {
        $collection = parent::getArgDefinitions();

        $collection
            ->addArgument(Argument::createIntTypeArgument('address'))
            ->addArgument(Argument::createIntTypeArgument('country'))
            ->addArgument(Argument::createIntTypeArgument('state'));

        return $collection;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        $cart = $this->getMainRequest()->getSession()->getSessionCart($this->dispatcher);
        $address = $this->getDeliveryAddress();

        $country = $this->getCurrentCountry();

        if (null === $country) {
            $country = null !== $address ? $address->getCountry() : CountryQuery::create()->findOneByByDefault(true);
        }

        $state = $this->getCurrentState();

        if (null === $state && null !== $address) {
            $state = $address->getState();
        }

        $virtual = $cart->isVirtual();

        // The postal code counts only for the country of the address it belongs to.
        $zipCode = null !== $address && $address->getCountryId() === $country?->getId() ? $address->getZipcode() : null;

        /** @var Module $deliveryModule */
        foreach ($loopResult->getResultDataCollection() as $deliveryModule) {
            $areaDeliveryModule = AreaDeliveryModuleQuery::create()
                ->findByCountryAndModule($country, $deliveryModule, $state, $zipCode);

            if (null === $areaDeliveryModule && false === $virtual) {
                continue;
            }

            /** @var DeliveryModuleInterface|DeliveryModuleWithStateInterface $moduleInstance */
            $moduleInstance = $deliveryModule->getDeliveryModuleInstance($this->container);

            if (true === $virtual
                && false === $moduleInstance->handleVirtualProductDelivery()
                && false === $this->getBackendContext()
            ) {
                continue;
            }

            // A carrier a product of the cart refuses is not offered for it.
            if ($this->cartShippingRules?->for($cart)->excludes((int) $deliveryModule->getId())) {
                continue;
            }

            $loopResultRow = new LoopResultRow($deliveryModule);

            try {
                // Check if module is valid, by calling isValidDelivery(),
                // or catching a DeliveryException.
                $deliveryPostageEvent = null;

                $askCarrier = function (Cart $quotedCart) use ($moduleInstance, $address, $country, $state, &$deliveryPostageEvent): ?OrderPostage {
                    $deliveryPostageEvent = new DeliveryPostageEvent($moduleInstance, $quotedCart, $address, $country, $state);
                    $this->dispatcher->dispatch(
                        $deliveryPostageEvent,
                        TheliaEvents::MODULE_DELIVERY_GET_POSTAGE,
                    );

                    return $deliveryPostageEvent->isValidModule() ? $deliveryPostageEvent->getPostage() : null;
                };

                // The shipping rules of the products: the carrier prices what it
                // carries, the surcharges come on top, as on the cart.
                $postage = null !== $this->productShippingRuleQuoter && null !== $country
                    ? $this->productShippingRuleQuoter->quote($deliveryModule, $cart, $country, $state, $askCarrier)
                    : $askCarrier($cart);

                if ($deliveryPostageEvent instanceof DeliveryPostageEvent && $deliveryPostageEvent->isValidModule()) {
                    $postage ??= new OrderPostage();

                    $loopResultRow
                        ->set('ID', $deliveryModule->getId())
                        ->set('CODE', $deliveryModule->getCode())
                        ->set('TITLE', $deliveryModule->getVirtualColumn('i18n_TITLE'))
                        ->set('CHAPO', $deliveryModule->getVirtualColumn('i18n_CHAPO'))
                        ->set('DESCRIPTION', $deliveryModule->getVirtualColumn('i18n_DESCRIPTION'))
                        ->set('POSTSCRIPTUM', $deliveryModule->getVirtualColumn('i18n_POSTSCRIPTUM'))
                        ->set('POSTAGE', $postage->getAmount())
                        ->set('POSTAGE_TAX', $postage->getAmountTax())
                        ->set('POSTAGE_UNTAXED', $postage->getUntaxedAmount())
                        ->set('POSTAGE_TAX_RULE_TITLE', $postage->getTaxRuleTitle())
                        ->set('DELIVERY_DATE', $deliveryPostageEvent->getDeliveryDate());

                    // add additional data if it exists
                    if ($deliveryPostageEvent->hasAdditionalData()) {
                        foreach ($deliveryPostageEvent->getAdditionalData() as $key => $value) {
                            $loopResultRow->set($key, $value);
                        }
                    }

                    $this->addOutputFields($loopResultRow, $deliveryModule);

                    $loopResult->addRow($loopResultRow);
                }
            } catch (DeliveryException) {
                // Module is not available
            }
        }

        return $loopResult;
    }

    protected function getModuleType(): int
    {
        return BaseModule::DELIVERY_MODULE_TYPE;
    }

    protected function getCurrentCountry(): mixed
    {
        $countryId = $this->getCountry();

        if (null !== $countryId) {
            $country = CountryQuery::create()->findPk($countryId);

            if (null === $country) {
                throw new \InvalidArgumentException('Cannot found country id: `'.$countryId.'` in delivery loop');
            }

            return $country;
        }

        return null;
    }

    protected function getCurrentState(): mixed
    {
        $stateId = $this->getState();

        if (null !== $stateId) {
            $state = StateQuery::create()->findPk($stateId);

            if (null === $state) {
                throw new \InvalidArgumentException('Cannot found state id: `'.$stateId.'` in delivery loop');
            }

            return $state;
        }

        return null;
    }

    protected function getDeliveryAddress(): mixed
    {
        $address = null;

        $addressId = $this->getAddress();

        if (empty($addressId)) {
            $addressId = $this->getMainRequest()->getSession()->getOrder()->getChoosenDeliveryAddress();
        }

        if (!empty($addressId)) {
            $address = AddressQuery::create()->findPk($addressId);
        }

        return $address;
    }
}
