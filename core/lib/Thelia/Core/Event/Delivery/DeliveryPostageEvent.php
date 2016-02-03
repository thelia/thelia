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

    /** @var DeliveryModuleInterface  */
    protected $module = null;

    /** @var Cart  */
    protected $cart = null;

    /** @var Country  */
    protected $country = null;

    /** @var State  */
    protected $state = null;

    /** @var bool */
    protected $validModule = false;

    /** @var OrderPostage|null  */
    protected $postage = null;

    /** @var \DateTime|null  */
    protected $deliveryDate = null;


    /**
     * DeliveryPostageEvent constructor.
     * @param DeliveryModuleInterface $module
     * @param Country $country
     * @param Cart $cart
     * @param State $state
     */
    public function __construct(DeliveryModuleInterface $module, Cart $cart, Country $country, State $state)
    {
        $this->module = $module;
        $this->cart = $cart;
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
     * @return Country
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @param Country $country
     */
    public function setCountry($country)
    {
        $this->country = $country;
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
     * @param null|OrderPostage $postage
     */
    public function setPostage($postage)
    {
        $this->postage = $postage;
        return $this;
    }

    /**
     * @return State
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param State $state
     */
    public function setState($state)
    {
        $this->state = $state;
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
}
