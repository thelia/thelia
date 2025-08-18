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

namespace Thelia\TaxEngine;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\CountryQuery;
use Thelia\Model\Customer;
use Thelia\Model\State;

/**
 * Class TaxEngine.
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class TaxEngine
{
    protected ?Country $taxCountry = null;
    protected ?State $taxState = null;

    public function __construct(
        protected RequestStack $requestStack,
        protected EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * Find Tax Country ID
     * First look for a picked delivery address country
     * Then look at the current customer default address country
     * Else look at the default website country.
     */
    public function getDeliveryCountry(): Country
    {
        /** @var Cart $cart */
        $cart = $this->getSession()->getSessionCart($this->dispatcher);
        $currentDeliveryAddress = null;

        if ($cart) {
            $currentDeliveryAddress = AddressQuery::create()->findPk($cart->getAddressDeliveryId());
        }

        if ($currentDeliveryAddress) {
            $this->taxCountry = $currentDeliveryAddress->getCountry();
            $this->taxState = $currentDeliveryAddress->getState();

            return $this->taxCountry;
        }

        /** @var Customer $customer */
        $customer = $this->getSession()?->getCustomerUser();

        if (!$customer) {
            $this->taxCountry = CountryQuery::create()->findOneByByDefault(1);
            $this->taxState = null;

            return $this->taxCountry;
        }

        if ($customerDefaultAddress = $customer->getDefaultAddress()) {
            $this->taxCountry = $customerDefaultAddress->getCountry();
            $this->taxState = $customerDefaultAddress->getState();

            return $this->taxCountry;
        }

        $this->taxCountry = CountryQuery::create()->findOneByByDefault(1);
        $this->taxState = null;

        return $this->taxCountry;
    }

    /**
     * Find Tax State Id.
     *
     * First look for a picked delivery address state
     * Then look at the current customer default address state
     * Else null
     */
    public function getDeliveryState(): ?State
    {
        if (null === $this->taxCountry) {
            $this->getDeliveryCountry();
        }

        return $this->taxState;
    }

    protected function getSession(): ?SessionInterface
    {
        return $this->requestStack?->getCurrentRequest()?->getSession();
    }
}
