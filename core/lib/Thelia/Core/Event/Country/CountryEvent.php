<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Core\Event\Country;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Country;

/**
 * Class CountryEvent
 * @package Thelia\Core\Event\Country
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
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
