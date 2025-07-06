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
    /**
     * @var OrderAddress
     */
    protected $orderAddress;

    /**
     * @var Order
     */
    protected $order;

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
     * @return string
     */
    public function getCellphone()
    {
        return $this->cellphone;
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

    public function setOrderAddress(OrderAddress $orderAddress): void
    {
        $this->orderAddress = $orderAddress;
    }

    public function setOrder(Order $order): void
    {
        $this->order = $order;
    }

    /**
     * @return OrderAddress
     */
    public function getOrderAddress()
    {
        return $this->orderAddress;
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }
}
