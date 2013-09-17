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

namespace Thelia\Core\Event;

use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Module;
use Thelia\Model\Order;

class OrderEvent extends ActionEvent
{
    protected $order = null;
    protected $billingAddress = null;
    protected $deliveryAddress = null;
    protected $deliveryModule = null;

    /**
     * @param Order $order
     */
    public function __construct(Order $order)
    {
        $this->setOrder($order);
    }

    /**
     * @param Order $order
     */
    public function setOrder(Order $order)
    {
        $this->order = $order;
    }

    /**
     * @param $address
     */
    public function setBillingAddress($address)
    {
        $this->deliveryAddress = $address;
    }

    /**
     * @param $address
     */
    public function setDeliveryAddress($address)
    {
        $this->deliveryAddress = $address;
    }

    /**
     * @param $module
     */
    public function setDeliveryModule($module)
    {
        $this->deliveryModule = $module;
    }

    /**
     * @return null|Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @return array|mixed|Address
     */
    public function getBillingAddress()
    {
        return $this->billingAddress;
    }

    /**
     * @return array|mixed|Address
     */
    public function getDeliveryAddress()
    {
        return $this->deliveryAddress;
    }

    /**
     * @return array|mixed|Address
     */
    public function getDeliveryModule()
    {
        return $this->deliveryModule;
    }
}
