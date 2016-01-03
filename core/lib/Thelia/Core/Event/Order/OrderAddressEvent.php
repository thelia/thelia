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
     * @var int|null state id
     */
    protected $state;

    /**
     * @var string phone
     */
    protected $phone;

    /**
     * @var string cellphone
     */
    protected $cellphone;

    /**
     * @var \Thelia\Model\OrderAddress
     */
    protected $orderAddress;

    /**
     * @var \Thelia\Model\Order
     */
    protected $order;

    public function __construct(
        $title,
        $firstname,
        $lastname,
        $address1,
        $address2,
        $address3,
        $zipcode,
        $city,
        $country,
        $phone,
        $company,
        $cellphone = null,
        $state = null
    ) {
        $this->address1 = $address1;
        $this->address2 = $address2;
        $this->address3 = $address3;
        $this->city = $city;
        $this->company = $company;
        $this->country = $country;
        $this->state= $state;
        $this->firstname = $firstname;
        $this->lastname = $lastname;
        $this->phone = $phone;
        $this->cellphone = $cellphone;
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
