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

namespace Thelia\Core\Event\Address;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Address;
use Thelia\Model\Customer;

/**
 * Class AddressCreateOrUpdateEvent
 * @package Thelia\Core\Event
 * @author Manuel Raynaud <manu@raynaud.io>
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
     * @var int state id
     */
    protected $state;

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

    public function __construct(
        $label,
        $title,
        $firstname,
        $lastname,
        $address1,
        $address2,
        $address3,
        $zipcode,
        $city,
        $country,
        $cellphone,
        $phone,
        $company,
        $isDefault = 0,
        $state = null
    ) {
        $this->address1 = $address1;
        $this->address2 = $address2;
        $this->address3 = $address3;
        $this->cellphone = $cellphone;
        $this->city = $city;
        $this->company = $company;
        $this->country = $country;
        $this->state = $state;
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
     * @return int|null
     */
    public function getState()
    {
        return $this->state;
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
