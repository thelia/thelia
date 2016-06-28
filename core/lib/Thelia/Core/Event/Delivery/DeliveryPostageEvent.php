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
use Thelia\Model\Cart;
use Thelia\Model\Country;
use Thelia\Model\OrderPostage;
use Thelia\Model\State;
use Thelia\Module\DeliveryModuleInterface;

/**
 * Class DeliveryPostageEvent
 * @package Thelia\Core\Event\Delivery
 * @author Julien Chanséaume <julien@thelia.net>
 */
class DeliveryPostageEvent extends ActionEvent
{
    /** @var DeliveryModuleInterface */
    protected $module = null;

    /** @var Cart */
    protected $cart = null;

    /** @var Address */
    protected $address = null;

    /** @var Country */
    protected $country = null;

    /** @var State */
    protected $state = null;

    /** @var bool */
    protected $validModule = false;

    /** @var OrderPostage|null */
    protected $postage = null;

    /** @var \DateTime|null */
    protected $deliveryDate = null;

    /** @var array */
    protected $additionalData = [];

    /**
     * DeliveryPostageEvent constructor.
     * @param DeliveryModuleInterface $module
     * @param Country $country
     * @param Cart $cart
     * @param State $state
     */
    public function __construct(
        DeliveryModuleInterface $module,
        Cart $cart,
        Address $address = null,
        Country $country = null,
        State $state = null
    ) {
        $this->module = $module;
        $this->cart = $cart;
        $this->address = $address;
        $this->country = $country;
        $this->state = $state;
    }

    /**
     * @return Cart
     */
    public function getCart()
    {
        return $this->cart;
    }

    /**
     * @param Cart $cart
     */
    public function setCart($cart)
    {
        $this->cart = $cart;
        return $this;
    }

    /**
     * @return Address
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * @param Address $address
     */
    public function setAddress($address)
    {
        $this->address = $address;
        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getDeliveryDate()
    {
        return $this->deliveryDate;
    }

    /**
     * @param \DateTime|null $deliveryDate
     */
    public function setDeliveryDate($deliveryDate)
    {
        $this->deliveryDate = $deliveryDate;
        return $this;
    }

    /**
     * @return DeliveryModuleInterface
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * @param DeliveryModuleInterface $module
     */
    public function setModule($module)
    {
        $this->module = $module;
        return $this;
    }

    /**
     * @return null|OrderPostage
     */
    public function getPostage()
    {
        return $this->postage;
    }

    /**
     * @param null|double|OrderPostage $postage
     */
    public function setPostage($postage)
    {
        $this->postage = OrderPostage::loadFromPostage($postage);
        return $this;
    }

    /**
     * @return boolean
     */
    public function isValidModule()
    {
        return $this->validModule;
    }

    /**
     * @param boolean $validModule
     */
    public function setValidModule($validModule)
    {
        $this->validModule = $validModule;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasAdditionalData()
    {
        return count($this->additionalData) > 0;
    }

    /**
     * @return array
     */
    public function getAdditionalData()
    {
        return $this->additionalData;
    }

    /**
     * @param array $additionalData
     */
    public function setAdditionalData($additionalData)
    {
        $this->additionalData = $additionalData;
        return $this;
    }

    /**
     * @param string $key the key of the additional data
     * @param mixed $value the value of the additional data
     *
     * return $this
     */
    public function addAdditionalData($key, $value)
    {
        $this->additionalData[$key] = $value;

        return $this;
    }

    /**
     * @return Country|null
     */
    public function getCountry()
    {
        return $this->getAddress() !== null ? $this->getAddress()->getCountry() : $this->country;
    }

    /**
     * @return State|null
     */
    public function getState()
    {
        return $this->getAddress() !== null ? $this->getAddress()->getState() : $this->state;
    }
}
