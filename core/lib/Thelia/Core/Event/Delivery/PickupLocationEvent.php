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

namespace Thelia\Core\Event\Delivery;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Address;
use Thelia\Model\PickupLocation;
use Thelia\Model\State;
use Thelia\Model\Country;

/**
 * Class PickupLocationEvent
 * @package Thelia\Core\Event\Delivery
 * @author Damien Foulhoux <dfoulhoux@openstudio.com>
 */
class PickupLocationEvent extends ActionEvent
{

    protected $locations = [];

    /** @var int address id */
    protected $addressId = null;

    /** @var Address */
    protected $address;
    
    /** @var State */
    protected $state;
    
    /** @var Country */
    protected $country;

    /**
     * PickupLocationEvent constructor.
     * @param int $addressId
     * @param Address $address
     * @param State $state
     * @param Country $country
     */
    public function __construct(
        $addressId,
        Address $address = null,
        State $state = null,
        Country $country = null
    ) {
        $this->addressId = $addressId;
        $this->address = $address;
        $this->state = $state;
        $this->country = $country;
    }

    /** @return int */
    public function getAdrressId()
    {
        return $this->addressId;
    }
    
    /** @return Address */
    public function getAdrress()
    {
        return $this->address;
    }

    /** @return State */
    public function getState()
    {
        return $this->getAddress() !== null ? $this->getAddress()->getState() : $this->state;
    }

    /** @return Country */
    public function getCountry()
    {
        return $this->getAddress() !== null ? $this->getAddress()->getCountry() : $this->country;
    }
    
    /** @return array */
    public function getLocations()
    {   
        return $this->locations;
    }

    /** 
     * @param $locations PickupLocationEvent[]
     * @return Thelia\Core\Event\Delivery\PickupLocationEvent
    */
    public function setLocations($locations)
    {
        $this->locations = $locations;
        return $this;
    }

    /** @param $location PickupLocation */
    public function appendLocation($location)
    {
        $this->locations[] = $location;
        return $this;
    }
    
}
