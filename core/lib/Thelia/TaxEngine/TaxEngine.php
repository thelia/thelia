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

use LogicException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\AddressQuery;
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
    protected $taxCountry;

    protected $taxState;

    public function __construct(protected RequestStack $requestStack)
    {
    }

    /**
     * Find Tax Country Id
     * First look for a picked delivery address country
     * Then look at the current customer default address country
     * Else look at the default website country.
     *
     */
    public function getDeliveryCountry(): Country
    {
        if (null !== $this->taxCountry) {
            return $this->taxCountry;
        }

        /* is there a logged in customer ? */
        /** @var Customer $customer */
        if (null !== $customer = $this->getSession()?->getCustomerUser()) {
            if (
                null !== $this->getSession()?->getOrder()
                    && null !== $this->getSession()?->getOrder()->getChoosenDeliveryAddress()
                    && null !== $currentDeliveryAddress = AddressQuery::create()->findPk($this->getSession()?->getOrder()->getChoosenDeliveryAddress())
            ) {
                $this->taxCountry = $currentDeliveryAddress->getCountry();
                $this->taxState = $currentDeliveryAddress->getState();
            } else {
                $customerDefaultAddress = $customer->getDefaultAddress();
                $this->taxCountry = $customerDefaultAddress->getCountry();
                $this->taxState = $customerDefaultAddress->getState();
            }
        }

        if (null === $this->taxCountry) {
            $this->taxCountry = CountryQuery::create()->findOneByByDefault(1);
            $this->taxState = null;
        }

        if(null === $this->taxCountry) {
            throw new LogicException('No country found for tax calculation.');
        }

        return $this->taxCountry;
    }

    /**
     * Find Tax State Id.
     *
     * First look for a picked delivery address state
     * Then look at the current customer default address state
     * Else null
     *
     * @return State|null
     *
     * @since 2.3.0-alpha1
     */
    public function getDeliveryState()
    {
        if (null === $this->taxCountry) {
            /* is there a logged in customer ? */
            $this->getDeliveryCountry();
        }

        return $this->taxState;
    }

    protected function getSession(): ?SessionInterface
    {
        return $this->requestStack?->getCurrentRequest()?->getSession();
    }
}
