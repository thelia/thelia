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

namespace Thelia\Core\Event\Order;

use Thelia\Core\Event\ActionEvent;
use Thelia\Model\Order;
use Thelia\Model\OrderAddress;

class OrderAddressEvent extends ActionEvent
{
    protected OrderAddress $orderAddress;

    protected Order $order;

    /**
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
     * @param int|null    $state
     * @param string      $phone
     * @param string      $cellphone
     */
    public function __construct(
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
         * @var string phone
         */
        protected $phone,
        /**
         * @var string|null company name
         */
        protected $company,
        /**
         * @var string cellphone
         */
        protected $cellphone = null,
        /**
         * @var int|null state id
         */
        protected $state = null,
    ) {
    }

    public function getAddress1(): string
    {
        return $this->address1;
    }

    public function getAddress2(): string
    {
        return $this->address2;
    }

    public function getAddress3(): string
    {
        return $this->address3;
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

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getCellphone(): string
    {
        return $this->cellphone;
    }

    public function getTitle(): int
    {
        return $this->title;
    }

    public function getZipcode(): string
    {
        return $this->zipcode;
    }

    public function setOrderAddress(OrderAddress $orderAddress): void
    {
        $this->orderAddress = $orderAddress;
    }

    public function setOrder(Order $order): void
    {
        $this->order = $order;
    }

    public function getOrderAddress(): OrderAddress
    {
        return $this->orderAddress;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }
}
