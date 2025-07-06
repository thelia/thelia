<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Core\Event\Address;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Address;
use Thelia\Model\Customer;

/**
 * Class AddressCreateOrUpdateEvent.
 *
 * @author Manuel Raynaud <manu@raynaud.io>
 */
class AddressCreateOrUpdateEvent extends ActionEvent
{
    /**
     * @var Customer
     */
    protected $customer;

    /**
     * @var Address
     */
    protected $address;

    /**
     * @param string      $label
     * @param int         $title
     * @param string|null $company
     * @param string      $firstname
     * @param string      $lastname
     * @param string      $address1
     * @param string      $address2
     * @param string      $address3
     * @param string      $zipcode
     * @param string      $city
     * @param int         $country
     * @param int         $state
     * @param string      $cellphone
     * @param string      $phone
     * @param int         $isDefault
     */
    public function __construct(
        /**
         * @var string address label
         */
        protected $label,
        /**
         * @var int title id
         */
        protected $title,
        /**
         * @var string first name
         */
        protected $firstname,
        /**
         * @var string last name
         */
        protected $lastname,
        /**
         * @var string address
         */
        protected $address1,
        /**
         * @var string address line 2
         */
        protected $address2,
        /**
         * @var string address line 3
         */
        protected $address3,
        /**
         * @var string zipcode
         */
        protected $zipcode,
        /**
         * @var string city
         */
        protected $city,
        /**
         * @var int country id
         */
        protected $country,
        /**
         * @var string cell phone
         */
        protected $cellphone,
        /**
         * @var string phone
         */
        protected $phone,
        /**
         * @var string|null company name
         */
        protected $company,
        protected $isDefault = 0,
        /**
         * @var int state id
         */
        protected $state = null,
    ) {
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
     * @return string|null
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

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }

    /**
     * @return Customer
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    public function setAddress(Address $address): void
    {
        $this->address = $address;
        $this->setCustomer($address->getCustomer());
    }

    /**
     * @return Address
     */
    public function getAddress()
    {
        return $this->address;
    }
}
