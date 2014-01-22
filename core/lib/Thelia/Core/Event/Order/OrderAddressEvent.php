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

namespace Thelia\Core\Event\Order;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;

class OrderAddressEvent extends ActionEvent
{
    /**
     * @var int title id
     */
    protected $title;

    /**
     * @var string|null company name
     */
    protected $company;

    /**
     * @var string first name
     */
    protected $firstname;

    /**
     * @var string last name
     */
    protected $lastname;

    /**
     * @var string address
     */
    protected $address1;

    /**
     * @var string address line 2
     */
    protected $address2;

    /**
     * @var string address line 3
     */
    protected $address3;

    /**
     * @var string zipcode
     */
    protected $zipcode;

    /**
     * @var string city
     */
    protected $city;

    /**
     * @var int country id
     */
    protected $country;

    /**
     * @var string phone
     */
    protected $phone;

    /**
     * @var \Thelia\Model\OrderAddress
     */
    protected $orderAddress;

    /**
     * @var \Thelia\Model\Order
     */
    protected $order;

    public function __construct($title, $firstname, $lastname, $address1, $address2, $address3, $zipcode, $city, $country, $phone, $company)
    {
        $this->address1 = $address1;
        $this->address2 = $address2;
        $this->address3 = $address3;
        $this->city = $city;
        $this->company = $company;
        $this->country = $country;
        $this->firstname = $firstname;
        $this->lastname = $lastname;
        $this->phone = $phone;
        $this->title = $title;
        $this->zipcode = $zipcode;
    }

    /**
     * @return string
     */
    public function getAddress1()
    {
        return $this->address1;
    }

    /**
     * @return string
     */
    public function getAddress2()
    {
        return $this->address2;
    }

    /**
     * @return string
     */
    public function getAddress3()
    {
        return $this->address3;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @return null|string
     */
    public function getCompany()
    {
        return $this->company;
    }

    /**
     * @return int
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * @return string
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * @return string
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @return int
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getZipcode()
    {
        return $this->zipcode;
    }

    /**
     * @param \Thelia\Model\OrderAddress $orderAddress
     */
    public function setOrderAddress(OrderAddress $orderAddress)
    {
        $this->orderAddress = $orderAddress;
    }

    /**
     * @param \Thelia\Model\Order $order
     */
    public function setOrder(Order $order)
    {
        $this->order = $order;
    }

    /**
     * @return \Thelia\Model\OrderAddress
     */
    public function getOrderAddress()
    {
        return $this->orderAddress;
    }

    /**
     * @return \Thelia\Model\Order
     */
    public function getOrder()
    {
        return $this->order;
    }
}
