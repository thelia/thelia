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

/**
 * Class PickupLocationEvent
 * @package Thelia\Core\Event\Delivery
 * @author Damien Foulhoux <dfoulhoux@openstudio.com>
 */
class PickupLocationEvent extends ActionEvent
{

    protected $locations = [];

    /** @var int address id */
    protected $address_id = null;

    /** @var Address */
    protected $address;
    
    /** @var State */
    protected $state;
    
    /** @var Country */
    protected $country;

    /**
     * PickupLocationEvent constructor.
     * @param  int address id
     * @param Address $address
     * @param State $state
     * @param Country $country
     */
    public function __construct(
        $address_id,
        $address = null,
        $state = null,
        $country = null
    ) {
        $this->address_id = $address_id;
        $this->address = $address;
        $this->state = $state;
        $this->country = $country;
    }

    /** @return int */
    public function getAdrressId()
    {
        return $this->address_id;
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
