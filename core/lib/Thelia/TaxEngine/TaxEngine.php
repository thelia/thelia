<?php

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

    /** @var RequestStack */
    protected $requestStack;

    public function __construct(
        RequestStack $requestStack,
    ) {
        $this->requestStack = $requestStack;
    }

    /**
     * Find Tax Country Id
     * First look for a picked delivery address country
     * Then look at the current customer default address country
     * Else look at the default website country.
     *
     * @return Country|null
     */
    public function getDeliveryCountry()
    {
        if (null === $this->taxCountry) {
            /* is there a logged in customer ? */
            /** @var Customer $customer */
            if (null !== $customer = $this->getSession()?->getCustomerUser()) {
                if (
                    null !== $this->getSession()->getOrder()
                        && null !== $this->getSession()->getOrder()->getChoosenDeliveryAddress()
                        && null !== $currentDeliveryAddress = AddressQuery::create()->findPk($this->getSession()->getOrder()->getChoosenDeliveryAddress())
                ) {
                    $this->taxCountry = $currentDeliveryAddress->getCountry();
                    $this->taxState = $currentDeliveryAddress->getState();
                } else {
                    $customerDefaultAddress = $customer->getDefaultAddress();
                    if (isset($customerDefaultAddress)) {
                        $this->taxCountry = $customerDefaultAddress->getCountry();
                        $this->taxState = $customerDefaultAddress->getState();
                    }
                }
            }

            if (null == $this->taxCountry) {
                $this->taxCountry = CountryQuery::create()->findOneByByDefault(1);
                $this->taxState = null;
            }
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

    protected function getSession()
    {
        return $this->requestStack?->getCurrentRequest()?->getSession();
    }
}
