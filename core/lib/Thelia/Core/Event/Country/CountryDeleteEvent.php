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

/**
 * Class CountryDeleteEvent
 * @package Thelia\Core\Event\Country
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class CountryDeleteEvent extends CountryEvent
{
    /**
     * @var int country id
     */
    protected $country_id;

    /**
     * @param int $country_id
     */
    public function __construct($country_id)
    {
        $this->country_id = $country_id;
    }

    /**
     * @param int $country_id
     */
    public function setCountryId($country_id)
    {
        $this->country_id = $country_id;
    }

    /**
     * @return int
     */
    public function getCountryId()
    {
        return $this->country_id;
    }
}
