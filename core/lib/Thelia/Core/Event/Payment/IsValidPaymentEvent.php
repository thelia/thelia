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


namespace Thelia\Core\Event\Payment;

use Thelia\Model\Cart;
use Thelia\Module\PaymentModuleInterface;

/**
 * Class IsValidPaymentEvent
 * @package Thelia\Core\Event\Payment
 * @author Julien Chanséaume <julien@thelia.net>
 */
class IsValidPaymentEvent extends BasePaymentEvent
{
    /** @var Cart */
    protected $cart = null;

    /** @var bool */
    protected $validModule = false;

    /**
     * IsValidPaymentEvent constructor.
     *
     * @param PaymentModuleInterface $module
     * @param Cart $cart
     */
    public function __construct(PaymentModuleInterface $module, Cart $cart)
    {
        parent::__construct($module);
        $this->cart = $cart;
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
