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
        $this->order = $order;
    }

    /**
     * @param Address $address
     */
    public function setBillingAddress(Address $address)
    {
        $this->deliveryAddress = $address->getId();
    }

    /**
     * @param Address $address
     */
    public function setDeliveryAddress(Address $address)
    {
        $this->deliveryAddress = $address->getId();
    }

    /**
     * @param Module $module
     */
    public function setDeliveryModule(Module $module)
    {
        $this->deliveryModule = $module->getId();
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
        return AddressQuery::create()->findPk($this->billingAddress);
    }

    /**
     * @return array|mixed|Address
     */
    public function getDeliveryAddress()
    {
        return AddressQuery::create()->findPk($this->deliveryAddress);
    }

    /**
     * @return array|mixed|Address
     */
    public function getDeliveryModule()
    {
        return AddressQuery::create()->findPk($this->deliveryModule);
    }
}
