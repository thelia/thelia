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

namespace Thelia\Core\Event\Address;
use Symfony\Component\EventDispatcher\Event;
use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Address;
use Thelia\Model\Customer;

/**
 * Class AddressCreateOrUpdateEvent
 * @package Thelia\Core\Event
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class AddressCreateOrUpdateEvent extends ActionEvent
{
    /**
     * @var string address label
     */
    protected $label;

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
     * @var string cell phone
     */
    protected $cellphone;

    /**
     * @var string phone
     */
    protected $phone;

    /**
     * @var \Thelia\Model\Customer
     */
    protected $customer;

    /**
     * @var \Thelia\Model\Address
     */
    protected $address;

    /**
     * @var int
     */
    protected $isDefault;

    public function __construct($label, $title, $firstname, $lastname, $address1, $address2, $address3, $zipcode, $city, $country, $cellphone, $phone, $company, $isDefault = 0)
    {
        $this->address1 = $address1;
        $this->address2 = $address2;
        $this->address3 = $address3;
        $this->cellphone = $cellphone;
        $this->city = $city;
        $this->company = $company;
        $this->country = $country;
        $this->firstname = $firstname;
        $this->label = $label;
        $this->lastname = $lastname;
        $this->phone = $phone;
        $this->title = $title;
        $this->zipcode = $zipcode;
        $this->isDefault = $isDefault;
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
    public function getCellphone()
    {
        return $this->cellphone;
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
    public function getLabel()
    {
        return $this->label;
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
     * @return int
     */
    public function getIsDefault()
    {
        return $this->isDefault;
    }

    /**
     * @param \Thelia\Model\Customer $customer
     */
    public function setCustomer(Customer $customer)
    {
        $this->customer = $customer;
    }

    /**
     * @return \Thelia\Model\Customer
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    /**
     * @param \Thelia\Model\Address $address
     */
    public function setAddress(Address $address)
    {
        $this->address = $address;
        $this->setCustomer($address->getCustomer());
    }

    /**
     * @return \Thelia\Model\Address
     */
    public function getAddress()
    {
        return $this->address;
    }

}
