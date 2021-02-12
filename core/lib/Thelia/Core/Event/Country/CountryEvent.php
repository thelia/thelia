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

namespace Thelia\Core\Event\Country;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Country;

/**
 * Class CountryEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 *
 * @deprecated since 2.4, please use \Thelia\Model\Event\CountryEvent
 */
class CountryEvent extends ActionEvent
{
    /*
     * @var \Thelia\Model\Country
     */
    protected $country;

    public function __construct(Country $country = null)
    {
        $this->country = $country;
    }

    /**
     * @param mixed $country
     */
    public function setCountry(Country $country)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * @return \Thelia\Model\Country|null
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @return bool
     */
    public function hasCountry()
    {
        return null !== $this->country;
    }
}
