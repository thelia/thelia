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

namespace Thelia\Core\Event\Delivery;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Address;
use Thelia\Model\Country;
use Thelia\Model\PickupLocation;
use Thelia\Model\State;

/**
 * Class PickupLocationEvent
 * @package Thelia\Core\Event\Delivery
 * @author Damien Foulhoux <dfoulhoux@openstudio.com>
 */
class PickupLocationEvent extends ActionEvent
{
    /**
     * @var string|null
     */
    protected $address;
    /**
     * @var string|null
     */
    protected $city;
    /**
     * @var string|null
     */
    protected $zipCode;
    /**
     * @var State|null
     */
    protected $state;
    /**
     * @var Country|null
     */
    protected $country;
    /**
     * @var integer|null
     */
    protected $radius;
    /**
     * @var integer|null
     */
    protected $maxRelays;
    /**
     * @var integer|null
     */
    protected $orderWeight;
    /**
     * @var array|null
     */
    protected $moduleIds;
    /**
     * @var array
     */
    protected $locations = [];

    /**
     * PickupLocationEvent constructor.
     *
     * @param integer|null $radius
     * @param integer|null $maxRelays
     * @param string|null $address
     * @param string|null $city
     * @param string|null $zipCode
     * @param integer|null $orderWeight
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function __construct(
        Address $addressModel = null,
        $radius = null,
        $maxRelays = null,
        $address = null,
        $city = null,
        $zipCode = null,
        $orderWeight = null,
        State $state = null,
        Country $country = null,
        array $moduleIds = null
    ) {
        $this->radius = $radius !== null ? $radius : 20000;
        $this->maxRelays = $maxRelays !== null ? $maxRelays : 15;
        $this->orderWeight = $orderWeight;
        $this->address = $address;
        $this->city = $city;
        $this->zipCode = $zipCode;
        $this->state = $state;
        $this->country = $country;
        $this->moduleIds = $moduleIds;

        if (null !== $addressModel) {
            $this->address = $addressModel->getAddress1();
            $this->city = $addressModel->getCity();
            $this->zipCode = $addressModel->getZipcode();
            $this->state = $addressModel->getState();
            $this->country = $addressModel->getCountry();
        }

        if ($this->address === null && $this->city === null && $this->zipCode === null) {
            throw new \Exception("Not enough informations to retrieve pickup locations");
        }
    }

    /**
     * @return string|null
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param string|null $address
     */
    public function setAddress($address)
    {
        $this->address = $address;
    }

    /**
     * @return string|null
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @param string|null $city
     */
    public function setCity($city)
    {
        $this->city = $city;
    }

    /**
     * @return string|null
     */
    public function getZipCode()
    {
        return $this->zipCode;
    }

    /**
     * @param string|null $zipCode
     */
    public function setZipCode($zipCode)
    {
        $this->zipCode = $zipCode;
    }

    /**
     * @return State|null
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param State|null $state
     */
    public function setState($state)
    {
        $this->state = $state;
    }

    /**
     * @return Country|null
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @param Country|null $country
     */
    public function setCountry($country)
    {
        $this->country = $country;
    }

    /**
     * @return int|null
     */
    public function getRadius()
    {
        return $this->radius;
    }

    /**
     * @param int|null $radius
     */
    public function setRadius($radius)
    {
        $this->radius = $radius;
    }

    /**
     * @return array|null
     */
    public function getModuleIds()
    {
        return $this->moduleIds;
    }

    /**
     * @param array|null $moduleIds
     */
    public function setModuleIds($moduleIds)
    {
        $this->moduleIds = $moduleIds;
    }

    /** @return array */
    public function getLocations()
    {
        return $this->locations;
    }

    /**
     * @param $locations PickupLocation[]
     * @return PickupLocationEvent
     */
    public function setLocations($locations)
    {
        $this->locations = $locations;
        return $this;
    }

    /** @param $location PickupLocation
     * @return PickupLocationEvent
     */
    public function appendLocation($location)
    {
        $this->locations[] = $location;
        return $this;
    }

    /**
     * @return integer|null
     */
    public function getOrderWeight()
    {
        return $this->orderWeight;
    }

    /**
     * @param integer|null $orderWeight
     * @return PickupLocationEvent
     */
    public function setOrderWeight($orderWeight)
    {
        $this->orderWeight = $orderWeight;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getMaxRelays()
    {
        return $this->maxRelays;
    }

    /**
     * @param int|null $maxRelays
     * @return PickupLocationEvent
     */
    public function setMaxRelays($maxRelays)
    {
        $this->maxRelays = $maxRelays;

        return $this;
    }
}
