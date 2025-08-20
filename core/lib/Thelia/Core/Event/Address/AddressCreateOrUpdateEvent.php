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
    protected Customer $customer;
    protected Address $address;

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

    public function getAddress1(): string
    {
        return $this->address1;
    }

    public function getAddress2(): ?string
    {
        return $this->address2;
    }

    public function getAddress3(): ?string
    {
        return $this->address3;
    }

    public function getCellphone(): ?string
    {
        return $this->cellphone;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function getCountry(): int
    {
        return $this->country;
    }

    public function getState(): ?int
    {
        return $this->state;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getTitle(): ?int
    {
        return $this->title;
    }

    public function getZipcode(): string
    {
        return $this->zipcode;
    }

    public function getIsDefault(): int
    {
        return $this->isDefault ? 1 : 0;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setAddress(Address $address): void
    {
        $this->address = $address;
        $this->setCustomer($address->getCustomer());
    }

    public function getAddress(): Address
    {
        return $this->address;
    }
}
