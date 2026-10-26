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

namespace Thelia\Domain\Taxation\TaxEngine;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Domain\Localization\VisitorCountryFacade;
use Thelia\Model\Country;
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
        // Optional: a test or a theme may build the engine by hand with the two arguments it had.
        protected ?VisitorCountryFacade $visitorCountry = null,
    ) {
    }

    /**
     * Find Tax Country ID
     * First look for a picked delivery address country
     * Then look at the current customer default address country
     * Else, for a visitor with no account, the country the visitor shops in
     * (the detected one when the shop applies it), else the default website country.
     */
    public function getDeliveryCountry(): Country
    {
        $session = $this->getSession();
        $cart = $session instanceof Session ? $session->getSessionCart($this->dispatcher) : null;
        $currentDeliveryAddress = null;

        if ($cart) {
            $currentDeliveryAddress = $cart->getCartAddressRelatedByAddressDeliveryId();
        }

        if ($currentDeliveryAddress) {
            $this->taxCountry = $currentDeliveryAddress->getCountry();
            $this->taxState = $currentDeliveryAddress->getState();

            return $this->taxCountry;
        }

        /** @var Customer $customer */
        $customer = $this->getSession()?->getCustomerUser();

        if (!$customer) {
            $this->taxCountry = $this->visitorCountry?->currentCountry() ?? Country::getDefaultCountry();
            $this->taxState = null;

            return $this->taxCountry;
        }

        if ($customerDefaultAddress = $customer->getDefaultAddress()) {
            $this->taxCountry = $customerDefaultAddress->getCountry();
            $this->taxState = $customerDefaultAddress->getState();

            return $this->taxCountry;
        }

        $this->taxCountry = Country::getDefaultCountry();
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
        $request = $this->requestStack->getMainRequest();

        return $request?->hasSession() ? $request->getSession() : null;
    }
}
