<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Event\Country;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Country;

/**
 * Class CountryEvent
 * @package Thelia\Core\Event\Country
 * @author Manuel Raynaud <manu@raynaud.io>
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
     * @return null|\Thelia\Model\Country
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
